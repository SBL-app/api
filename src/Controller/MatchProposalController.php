<?php

namespace App\Controller;

use App\Entity\MatchProposal;
use App\Exception\ApiProblemException;
use App\Repository\MatchProposalRepository;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Repository\TeamRepository;
use App\Service\PushNotificationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class MatchProposalController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof MatchProposal) {
            throw new \InvalidArgumentException('Entity must be an instance of MatchProposal');
        }

        return [
            'id' => $entity->getId(),
            'game_id' => $entity->getGame()?->getId(),
            'game' => $entity->getGame() ? [
                'id' => $entity->getGame()->getId(),
                'team1' => $entity->getGame()->getTeam1()?->getName(),
                'team2' => $entity->getGame()->getTeam2()?->getName(),
                'week' => $entity->getGame()->getWeek(),
            ] : null,
            'proposer_id' => $entity->getProposer()?->getId(),
            'proposer' => $entity->getProposer() ? [
                'id' => $entity->getProposer()->getId(),
                'discord_id' => $entity->getProposer()->getDiscordId(),
                'discord_username' => $entity->getProposer()->getDiscordUsername(),
            ] : null,
            'receiver_id' => $entity->getReceiver()?->getId(),
            'receiver' => $entity->getReceiver() ? [
                'id' => $entity->getReceiver()->getId(),
                'discord_id' => $entity->getReceiver()->getDiscordId(),
                'discord_username' => $entity->getReceiver()->getDiscordUsername(),
            ] : null,
            'proposed_date' => $entity->getProposedDate()?->format('Y-m-d H:i:s'),
            'status' => $entity->getStatus(),
            'counter_to_id' => $entity->getCounterTo()?->getId(),
            'created_at' => $entity->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('/match-proposals/{id}', name: 'app_match_proposal_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getMatchProposal(int $id): JsonResponse
    {
        return $this->getEntityById('App\Entity\MatchProposal', $id, 'MatchProposal');
    }

    /**
     * GET /api/match-proposals - Liste des propositions avec filtres optionnels
     *
     * Filtres supportés :
     * - ?game_id=X : filtrer par match
     * - ?receiver_id=X : filtrer par receveur (user ID)
     * - ?discord_id=X : filtrer par receveur (discord ID)
     * - ?status=pending|accepted|rejected|counter : filtrer par statut
     */
    #[Route('/match-proposals', name: 'app_match_proposals', methods: ['GET'])]
    public function getMatchProposals(Request $request, MatchProposalRepository $proposalRepository, GameRepository $gameRepository, UserRepository $userRepository): JsonResponse
    {
        $gameId = $request->query->get('game_id');
        $receiverId = $request->query->get('receiver_id');
        $discordId = $request->query->get('discord_id');
        $status = $request->query->get('status');

        // Filtre par utilisateur (receiver_id ou discord_id) + status
        if ($receiverId || $discordId) {
            if ($discordId) {
                $user = $userRepository->findByDiscordId($discordId);
            } else {
                $user = $userRepository->find($receiverId);
            }

            if (!$user) {
                throw ApiProblemException::notFound('User not found');
            }

            // Si status=pending, retourner les propositions reçues et envoyées en attente
            if ($status === 'pending') {
                $receivedProposals = $proposalRepository->findPendingByReceiver($user);
                $sentProposals = $proposalRepository->findPendingByProposer($user);

                return $this->json([
                    'received' => array_map(fn($p) => $this->formatEntityData($p), $receivedProposals),
                    'sent' => array_map(fn($p) => $this->formatEntityData($p), $sentProposals),
                ]);
            }

            // Sinon filtrer les propositions reçues par cet utilisateur
            $criteria = ['receiver' => $user];
            if ($status) {
                $criteria['status'] = $status;
            }
            $proposals = $proposalRepository->findBy($criteria);
            $data = array_map(fn($p) => $this->formatEntityData($p), $proposals);
            return $this->json($data);
        }

        if ($gameId) {
            $game = $gameRepository->find($gameId);
            if (!$game) {
                throw ApiProblemException::notFound('Game not found');
            }
            $proposals = $proposalRepository->findByGame($game);
        } else {
            $proposals = $proposalRepository->findAll();
        }

        $data = array_map(fn($proposal) => $this->formatEntityData($proposal), $proposals);
        return $this->json($data);
    }

    #[Route('/match-proposals', name: 'app_match_proposal_create', methods: ['POST'])]
    public function createProposal(
        Request $request,
        GameRepository $gameRepository,
        UserRepository $userRepository,
        TeamRepository $teamRepository,
        MatchProposalRepository $proposalRepository,
        PushNotificationService $pushNotificationService,
    ): JsonResponse {
        try {
            $this->checkModificationPermissions();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::forbidden($e->getMessage());
        }

        $data = $this->getRequestData($request);

        if (!isset($data['game_id'])) {
            throw ApiProblemException::validationError('game_id is required', [['field' => 'game_id', 'message' => 'This value should not be blank.']]);
        }
        if (!isset($data['proposer_discord_id'])) {
            throw ApiProblemException::validationError('proposer_discord_id is required', [['field' => 'proposer_discord_id', 'message' => 'This value should not be blank.']]);
        }
        if (!isset($data['proposed_date'])) {
            throw ApiProblemException::validationError('proposed_date is required', [['field' => 'proposed_date', 'message' => 'This value should not be blank.']]);
        }

        $game = $gameRepository->find($data['game_id']);
        if (!$game) {
            throw ApiProblemException::notFound('Game not found');
        }

        $proposer = $userRepository->findByDiscordId($data['proposer_discord_id']);
        if (!$proposer) {
            throw ApiProblemException::notFound('Proposer user not found. Make sure the user has linked their Discord account.');
        }

        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();

        $proposerTeam = null;
        $receiverTeam = null;

        if ($team1 && $team1->getCaptainUser() && $team1->getCaptainUser()->getId() === $proposer->getId()) {
            $proposerTeam = $team1;
            $receiverTeam = $team2;
        } elseif ($team2 && $team2->getCaptainUser() && $team2->getCaptainUser()->getId() === $proposer->getId()) {
            $proposerTeam = $team2;
            $receiverTeam = $team1;
        }

        if (!$proposerTeam) {
            throw ApiProblemException::forbidden('You must be a team captain for this game to propose a date');
        }

        if (!$receiverTeam || !$receiverTeam->getCaptainUser()) {
            throw ApiProblemException::badRequest('The opposing team has no captain set');
        }

        $receiver = $receiverTeam->getCaptainUser();

        $proposal = new MatchProposal();
        $proposal->setGame($game);
        $proposal->setProposer($proposer);
        $proposal->setReceiver($receiver);
        $proposal->setProposedDate(new \DateTime($data['proposed_date']));
        $proposal->setStatus(MatchProposal::STATUS_PENDING);
        $proposal->setCreatedAt(new \DateTime());

        if (isset($data['counter_to_id'])) {
            $counterTo = $proposalRepository->find($data['counter_to_id']);
            if ($counterTo) {
                $proposal->setCounterTo($counterTo);
                $proposal->setStatus(MatchProposal::STATUS_COUNTER);
                $counterTo->setStatus(MatchProposal::STATUS_COUNTER);
                $this->entityManager->persist($counterTo);
            }
        }

        $this->saveEntity($proposal);

        $pushNotificationService->sendToUser(
            $receiver,
            'Nouveau match proposé',
            sprintf('%s vous propose un match le %s', $proposerTeam->getName(), $proposal->getProposedDate()->format('d/m/Y à H:i')),
            '/matches/proposals',
        );

        $response = $this->json([
            'proposal' => $this->formatEntityData($proposal),
            'receiver_discord_id' => $receiver->getDiscordId(),
        ], 201);
        $response->headers->set('Location', $request->getPathInfo() . '/' . $proposal->getId());
        return $response;
    }

    #[Route('/match-proposals/{id}', name: 'app_match_proposal_update', methods: ['PATCH'])]
    public function updateProposal(
        Request $request,
        int $id,
        MatchProposalRepository $proposalRepository,
        UserRepository $userRepository,
        GameRepository $gameRepository,
        PushNotificationService $pushNotificationService,
    ): JsonResponse {
        try {
            $this->checkModificationPermissions();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::forbidden($e->getMessage());
        }

        $proposal = $this->findEntityOrFail('App\Entity\MatchProposal', $id, 'Proposal');
        $data = $this->getRequestData($request);

        if (isset($data['discord_id'])) {
            $user = $userRepository->findByDiscordId($data['discord_id']);
            if (!$user) {
                throw ApiProblemException::notFound('User not found');
            }

            if ($proposal->getReceiver()->getId() !== $user->getId()) {
                throw ApiProblemException::forbidden('Only the receiver can accept or reject this proposal');
            }
        }

        if (isset($data['status'])) {
            $validStatuses = [
                MatchProposal::STATUS_ACCEPTED,
                MatchProposal::STATUS_REJECTED,
            ];

            if (!in_array($data['status'], $validStatuses)) {
                throw ApiProblemException::badRequest('Invalid status. Must be "accepted" or "rejected"');
            }

            $proposal->setStatus($data['status']);

            if ($data['status'] === MatchProposal::STATUS_ACCEPTED) {
                $game = $proposal->getGame();
                $game->setDate($proposal->getProposedDate());
                $this->entityManager->persist($game);

                $otherProposals = $proposalRepository->findBy([
                    'game' => $game,
                    'status' => MatchProposal::STATUS_PENDING,
                ]);
                foreach ($otherProposals as $other) {
                    if ($other->getId() !== $proposal->getId()) {
                        $other->setStatus(MatchProposal::STATUS_REJECTED);
                        $this->entityManager->persist($other);
                    }
                }
            }
        }

        $this->entityManager->flush();

        if (isset($data['status'])) {
            $game = $proposal->getGame();
            $receiver = $proposal->getReceiver();
            $receiverTeamName = null;

            if ($game->getTeam1()?->getCaptainUser()?->getId() === $receiver?->getId()) {
                $receiverTeamName = $game->getTeam1()?->getName();
            } elseif ($game->getTeam2()?->getCaptainUser()?->getId() === $receiver?->getId()) {
                $receiverTeamName = $game->getTeam2()?->getName();
            }
            $receiverTeamName ??= $receiver?->getDiscordUsername() ?? 'L\'adversaire';

            if ($data['status'] === MatchProposal::STATUS_ACCEPTED) {
                $pushNotificationService->sendToUser(
                    $proposal->getProposer(),
                    'Proposition acceptée',
                    sprintf('%s a accepté votre proposition de match', $receiverTeamName),
                    '/matches/proposals',
                );
            } elseif ($data['status'] === MatchProposal::STATUS_REJECTED) {
                $pushNotificationService->sendToUser(
                    $proposal->getProposer(),
                    'Proposition refusée',
                    sprintf('%s a refusé votre proposition de match', $receiverTeamName),
                    '/matches/proposals',
                );
            }
        }

        return $this->json($this->formatEntityData($proposal));
    }

    #[Route('/match-proposals/{id}', name: 'app_match_proposal_delete', methods: ['DELETE'])]
    public function deleteProposal(int $id): JsonResponse
    {
        $proposal = $this->findEntityOrFail('App\Entity\MatchProposal', $id, 'Proposal');
        return $this->securedDeleteEntity($proposal, 'MatchProposal');
    }
}
