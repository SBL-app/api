<?php

namespace App\Controller;

use App\Entity\Player;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\Transfer;
use App\Exception\ApiProblemException;
use App\Repository\SeasonRepository;
use App\Repository\TeamStatRepository;
use App\Repository\TransferRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Transferts de joueurs entre équipes (issue app#28).
 *
 * Règles :
 * - 2 transferts entrants maximum par équipe et par saison ;
 * - transfert autorisé si le niveau de division du joueur (division de son
 *   équipe d'origine) est <= niveau de division de l'équipe d'accueil + 1.
 *   Rappel : level 1 = division la plus haute.
 */
#[Route('/api')]
class TransferController extends BaseController
{
    public const MAX_TRANSFERS_PER_SEASON = 2;

    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Transfer) {
            throw new \InvalidArgumentException('Entity must be an instance of Transfer');
        }

        return [
            'id' => $entity->getId(),
            'player_id' => $entity->getPlayer()?->getId(),
            'player_name' => $entity->getPlayer()?->getName(),
            'from_team_id' => $entity->getFromTeam()?->getId(),
            'from_team_name' => $entity->getFromTeam()?->getName(),
            'to_team_id' => $entity->getToTeam()?->getId(),
            'to_team_name' => $entity->getToTeam()?->getName(),
            'season_id' => $entity->getSeason()?->getId(),
            'season_name' => $entity->getSeason()?->getName(),
            'created_at' => $entity->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * GET /api/teams/{teamId}/transfers[?season_id=]
     * Liste les transferts entrants d'une équipe (lecture publique).
     */
    #[Route('/teams/{teamId}/transfers', name: 'app_team_transfers_list', methods: ['GET'], requirements: ['teamId' => '\d+'])]
    public function listTransfers(int $teamId, Request $request, TransferRepository $transferRepository): JsonResponse
    {
        /** @var Team $team */
        $team = $this->findEntityOrFail(Team::class, $teamId, 'Team');

        $seasonId = $request->query->get('season_id');
        if ($seasonId) {
            $season = $this->findEntityOrFail(Season::class, $seasonId, 'Season');
            $transfers = $transferRepository->findByToTeamAndSeason($team, $season);
        } else {
            $transfers = $transferRepository->findBy(['toTeam' => $team], ['createdAt' => 'DESC']);
        }

        return $this->json(array_map(fn (Transfer $t) => $this->formatEntityData($t), $transfers));
    }

    /**
     * POST /api/teams/{teamId}/transfers
     * Body : {"player_id": <id>, "season_id"?: <id>}
     *
     * Le capitaine de l'équipe d'accueil (ou un admin) transfère un joueur
     * dans son équipe. Applique les deux règles métier.
     */
    #[Route('/teams/{teamId}/transfers', name: 'app_team_transfer_create', methods: ['POST'], requirements: ['teamId' => '\d+'])]
    public function createTransfer(
        int $teamId,
        Request $request,
        TransferRepository $transferRepository,
        TeamStatRepository $teamStatRepository,
        SeasonRepository $seasonRepository
    ): JsonResponse {
        try {
            $currentUser = $this->getAuthenticatedUser();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::unauthorized($e->getMessage());
        }

        /** @var Team $toTeam */
        $toTeam = $this->findEntityOrFail(Team::class, $teamId, 'Team');

        $isAdmin = $this->authService->userHasRole($currentUser, 'ROLE_ADMIN');
        if (!$isAdmin && !$toTeam->isCaptain($currentUser)) {
            throw ApiProblemException::forbidden('Only the destination team captain or an admin can register a transfer');
        }

        $data = $this->getRequestData($request);
        $playerId = $data['player_id'] ?? null;
        if (!$playerId) {
            throw ApiProblemException::validationError('player_id is required', [['field' => 'player_id', 'message' => 'This value should not be blank.']]);
        }

        /** @var Player $player */
        $player = $this->findEntityOrFail(Player::class, $playerId, 'Player');

        // Saison : depuis le corps, sinon la saison courante.
        if (isset($data['season_id'])) {
            /** @var Season $season */
            $season = $this->findEntityOrFail(Season::class, $data['season_id'], 'Season');
        } else {
            $season = $seasonRepository->findCurrent(new \DateTime());
            if (!$season) {
                throw ApiProblemException::badRequest('No season_id provided and no current season found');
            }
        }

        $fromTeam = $player->getTeam();

        if ($fromTeam && $fromTeam->getId() === $toTeam->getId()) {
            throw ApiProblemException::badRequest('Player already belongs to this team');
        }

        // Règle 1 : maximum de transferts entrants par saison.
        $count = $transferRepository->countByToTeamAndSeason($toTeam, $season);
        if ($count >= self::MAX_TRANSFERS_PER_SEASON) {
            throw ApiProblemException::badRequest(sprintf(
                'This team has reached the maximum number of transfers for this season (%d)',
                self::MAX_TRANSFERS_PER_SEASON
            ));
        }

        // Règle 2 : niveau du joueur <= niveau de l'équipe d'accueil + 1.
        // Évaluée uniquement si les deux divisions sont connues pour la saison.
        $toStat = $teamStatRepository->findOneByTeamAndSeason($toTeam, $season);
        $toLevel = $toStat?->getDivision()?->getLevel();

        $playerLevel = null;
        if ($fromTeam) {
            $fromStat = $teamStatRepository->findOneByTeamAndSeason($fromTeam, $season);
            $playerLevel = $fromStat?->getDivision()?->getLevel();
        }

        if ($toLevel !== null && $playerLevel !== null && $playerLevel > $toLevel + 1) {
            throw ApiProblemException::badRequest(sprintf(
                'Transfer not allowed: player division level (%d) must be at most destination team division level (%d) + 1',
                $playerLevel,
                $toLevel
            ));
        }

        // Application du transfert.
        $player->setTeam($toTeam);

        $transfer = new Transfer();
        $transfer->setPlayer($player);
        $transfer->setFromTeam($fromTeam);
        $transfer->setToTeam($toTeam);
        $transfer->setSeason($season);

        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->logger->info('Player transferred', [
            'player' => $player->getId(),
            'from_team' => $fromTeam?->getId(),
            'to_team' => $toTeam->getId(),
            'season' => $season->getId(),
        ]);

        $response = $this->json($this->formatEntityData($transfer), 201);
        $response->headers->set('Location', '/api/teams/' . $toTeam->getId() . '/transfers');
        return $response;
    }
}
