<?php

namespace App\Controller;

use App\Entity\MatchProposal;
use App\Repository\MatchProposalRepository;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Repository\TeamRepository;
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

    #[Route('/match-proposals', name: 'app_match_proposals', methods: ['GET'])]
    public function getMatchProposals(Request $request, MatchProposalRepository $proposalRepository, GameRepository $gameRepository): JsonResponse
    {
        $gameId = $request->query->get('game_id');
        $id = $request->query->get('id');

        // Backward compatibility - deprecated
        if ($id) {
            $this->logger->warning('Deprecated: Using ?id parameter for match proposal. Use /match-proposals/{id} instead', ['id' => $id]);
            return $this->getEntityById('App\Entity\MatchProposal', $id, 'MatchProposal');
        }

        if ($gameId) {
            $game = $gameRepository->find($gameId);
            if (!$game) {
                return $this->json(['error' => 'Game not found'], 404);
            }
            $proposals = $proposalRepository->findByGame($game);
        } else {
            $proposals = $proposalRepository->findAll();
        }

        $data = array_map(fn($proposal) => $this->formatEntityData($proposal), $proposals);
        return $this->json($data);
    }

    #[Route('/match-proposals/pending', name: 'app_match_proposals_pending', methods: ['GET'])]
    public function getPendingProposals(Request $request, MatchProposalRepository $proposalRepository, UserRepository $userRepository): JsonResponse
    {
        $userId = $request->query->get('user_id');
        $discordId = $request->query->get('discord_id');

        if (!$userId && !$discordId) {
            return $this->json(['error' => 'user_id or discord_id is required'], 400);
        }

        if ($discordId) {
            $user = $userRepository->findByDiscordId($discordId);
        } else {
            $user = $userRepository->find($userId);
        }

        if (!$user) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $receivedProposals = $proposalRepository->findPendingByReceiver($user);
        $sentProposals = $proposalRepository->findPendingByProposer($user);

        return $this->json([
            'received' => array_map(fn($p) => $this->formatEntityData($p), $receivedProposals),
            'sent' => array_map(fn($p) => $this->formatEntityData($p), $sentProposals),
        ]);
    }

    #[Route('/match-proposals', name: 'app_match_proposal_create', methods: ['POST'])]
    public function createProposal(
        Request $request,
        GameRepository $gameRepository,
        UserRepository $userRepository,
        TeamRepository $teamRepository,
        MatchProposalRepository $proposalRepository
    ): JsonResponse {
        try {
            $this->checkModificationPermissions();
            $data = $this->getRequestData($request);

            // Validation des champs requis
            if (!isset($data['game_id'])) {
                return $this->json(['error' => 'game_id is required'], 400);
            }
            if (!isset($data['proposer_discord_id'])) {
                return $this->json(['error' => 'proposer_discord_id is required'], 400);
            }
            if (!isset($data['proposed_date'])) {
                return $this->json(['error' => 'proposed_date is required'], 400);
            }

            // Récupérer le match
            $game = $gameRepository->find($data['game_id']);
            if (!$game) {
                return $this->json(['error' => 'Game not found'], 404);
            }

            // Récupérer le proposer via son Discord ID
            $proposer = $userRepository->findByDiscordId($data['proposer_discord_id']);
            if (!$proposer) {
                return $this->json(['error' => 'Proposer user not found. Make sure the user has linked their Discord account.'], 404);
            }

            // Vérifier que le proposer est capitaine d'une des équipes du match
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
                return $this->json(['error' => 'You must be a team captain for this game to propose a date'], 403);
            }

            if (!$receiverTeam || !$receiverTeam->getCaptainUser()) {
                return $this->json(['error' => 'The opposing team has no captain set'], 400);
            }

            $receiver = $receiverTeam->getCaptainUser();

            // Créer la proposition
            $proposal = new MatchProposal();
            $proposal->setGame($game);
            $proposal->setProposer($proposer);
            $proposal->setReceiver($receiver);
            $proposal->setProposedDate(new \DateTime($data['proposed_date']));
            $proposal->setStatus(MatchProposal::STATUS_PENDING);
            $proposal->setCreatedAt(new \DateTime());

            // Si c'est une contre-proposition
            if (isset($data['counter_to_id'])) {
                $counterTo = $proposalRepository->find($data['counter_to_id']);
                if ($counterTo) {
                    $proposal->setCounterTo($counterTo);
                    $proposal->setStatus(MatchProposal::STATUS_COUNTER);
                    // Mettre à jour le statut de la proposition précédente
                    $counterTo->setStatus(MatchProposal::STATUS_COUNTER);
                    $this->entityManager->persist($counterTo);
                }
            }

            $this->saveEntity($proposal);

            return $this->json([
                'message' => 'Proposal created successfully',
                'proposal' => $this->formatEntityData($proposal),
                'receiver_discord_id' => $receiver->getDiscordId(),
            ], 201);

        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->permissionDeniedResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/match-proposals/{id}', name: 'app_match_proposal_update', methods: ['PATCH'])]
    public function updateProposal(
        Request $request,
        int $id,
        MatchProposalRepository $proposalRepository,
        UserRepository $userRepository,
        GameRepository $gameRepository
    ): JsonResponse {
        try {
            $this->checkModificationPermissions();
            $proposal = $proposalRepository->find($id);
            if (!$proposal) {
                return $this->json(['error' => 'Proposal not found'], 404);
            }

            $data = $this->getRequestData($request);

            // Vérifier que l'utilisateur est autorisé à modifier cette proposition
            if (isset($data['discord_id'])) {
                $user = $userRepository->findByDiscordId($data['discord_id']);
                if (!$user) {
                    return $this->json(['error' => 'User not found'], 404);
                }

                // Seul le receiver peut accepter/refuser
                if ($proposal->getReceiver()->getId() !== $user->getId()) {
                    return $this->json(['error' => 'Only the receiver can accept or reject this proposal'], 403);
                }
            }

            if (isset($data['status'])) {
                $validStatuses = [
                    MatchProposal::STATUS_ACCEPTED,
                    MatchProposal::STATUS_REJECTED,
                ];

                if (!in_array($data['status'], $validStatuses)) {
                    return $this->json(['error' => 'Invalid status. Must be "accepted" or "rejected"'], 400);
                }

                $proposal->setStatus($data['status']);

                // Si accepté, mettre à jour la date du match
                if ($data['status'] === MatchProposal::STATUS_ACCEPTED) {
                    $game = $proposal->getGame();
                    $game->setDate($proposal->getProposedDate());
                    $this->entityManager->persist($game);

                    // Rejeter toutes les autres propositions en attente pour ce match
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

            return $this->json([
                'message' => 'Proposal updated successfully',
                'proposal' => $this->formatEntityData($proposal),
            ]);

        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->permissionDeniedResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/match-proposals/{id}', name: 'app_match_proposal_delete', methods: ['DELETE'])]
    public function deleteProposal(int $id, MatchProposalRepository $proposalRepository): JsonResponse
    {
        try {
            $proposal = $proposalRepository->find($id);
            if (!$proposal) {
                return $this->json(['error' => 'Proposal not found'], 404);
            }

            return $this->securedDeleteEntity($proposal, 'MatchProposal');
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}
