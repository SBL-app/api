<?php

namespace App\Controller;

use App\Exception\ApiProblemException;
use App\Service\SeasonClosureService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Game;
use App\Repository\GameRepository;
use App\Repository\TeamRepository;
use App\Repository\GameStatusRepository;
use App\Repository\DivisionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api')]
class GameController extends BaseController
{
    /**
     * Formate les données de base d'un match
     */
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Game) {
            throw new \InvalidArgumentException('Entity must be an instance of Game');
        }
        return $this->formatGameData($entity);
    }

    /**
     * Formate les données d'un match avec vérification des valeurs nulles
     */
    private function formatGameData(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'date' => $game->getDate() ? $game->getDate()->format('Y-m-d H:i:s') : null,
            'week' => $game->getWeek(),
            'team1' => $game->getTeam1() ? $game->getTeam1()->getName() : null,
            'team2' => $game->getTeam2() ? $game->getTeam2()->getName() : null,
            'score1' => $game->getScore1(),
            'score2' => $game->getScore2(),
            'winner' => $game->getWinner(),
            'status' => $game->getStatus() ? $game->getStatus()->getName() : null,
            'division' => $game->getDivision() ? $game->getDivision()->getName() : null,
            'is_forfeit' => $game->isForfeit(),
            'forfeit_team' => $game->getForfeitTeam(),
            'forfeit_reason' => $game->getForfeitReason()
        ];
    }

    /**
     * Récupère les matchs d'une équipe avec filtrage optionnel par division
     */
    private function getGamesByTeam(GameRepository $gameRepository, int $teamId, ?int $divisionId = null): array
    {
        $criteria1 = ['team1' => $teamId];
        $criteria2 = ['team2' => $teamId];

        if ($divisionId) {
            $criteria1['division'] = $divisionId;
            $criteria2['division'] = $divisionId;
        }

        $games1 = $gameRepository->findBy($criteria1);
        $games2 = $gameRepository->findBy($criteria2);

        return array_merge($games1, $games2);
    }

    /**
     * Valide et associe les entités liées à un match
     */
    private function setGameRelations(Game $game, array $data): void
    {
        if (isset($data['team1'])) {
            $team1 = $this->entityManager->getRepository('App\Entity\Team')->find($data['team1']);
            if ($data['team1'] && !$team1) {
                throw ApiProblemException::badRequest('Invalid team1 id');
            }
            $game->setTeam1($team1);
        }

        if (isset($data['team2'])) {
            $team2 = $this->entityManager->getRepository('App\Entity\Team')->find($data['team2']);
            if ($data['team2'] && !$team2) {
                throw ApiProblemException::badRequest('Invalid team2 id');
            }
            $game->setTeam2($team2);
        }

        if (isset($data['status'])) {
            $status = $this->entityManager->getRepository('App\Entity\GameStatus')->find($data['status']);
            if ($data['status'] && !$status) {
                throw ApiProblemException::badRequest('Invalid status id');
            }
            $game->setStatus($status);
        }

        if (isset($data['division'])) {
            $division = $this->entityManager->getRepository('App\Entity\Division')->find($data['division']);
            if ($data['division'] && !$division) {
                throw ApiProblemException::badRequest('Invalid division id');
            }
            $game->setDivision($division);
        }
    }

    #[Route('/games/{id}', name: 'app_game_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getGame(int $id): JsonResponse
    {
        return $this->getEntityById('App\Entity\Game', $id, 'Game');
    }

    /**
     * GET /api/games - Liste des matchs avec filtres optionnels
     *
     * Filtres supportés :
     * - ?week=X : filtrer par semaine
     * - ?season_id=X : filtrer par saison (via divisions)
     * - ?scheduled=false : uniquement les matchs non planifiés (date null)
     * - ?team_id=X : filtrer par équipe
     * - ?division_id=X : filtrer par division
     */
    #[Route('/games', name: 'app_games', methods: ['GET'])]
    public function getGames(Request $request, GameRepository $gameRepository, DivisionRepository $divisionRepository): JsonResponse
    {
        $divisionId = $request->query->get('division_id');
        $teamId = $request->query->get('team_id');
        $week = $request->query->get('week');
        $seasonId = $request->query->get('season_id');
        $scheduled = $request->query->get('scheduled');

        // Filtre par semaine (anciennement /games/week et /games/unscheduled)
        if ($week) {
            $games = [];

            if ($seasonId) {
                $divisions = $divisionRepository->findBy(['season' => $seasonId]);
                foreach ($divisions as $division) {
                    $divisionGames = $gameRepository->findBy(['week' => (int)$week, 'division' => $division]);
                    $games = array_merge($games, $divisionGames);
                }
            } else {
                $games = $gameRepository->findBy(['week' => (int)$week]);
            }

            // Filtre scheduled=false → uniquement les matchs sans date
            if ($scheduled === 'false') {
                $games = array_filter($games, fn($game) => $game->getDate() === null);
                $games = array_values($games);
            }

            $data = array_map(fn($game) => $this->formatGameData($game), $games);
            return $this->json($data);
        }

        // Si team_id est fourni (avec ou sans division_id)
        if ($teamId) {
            $games = $this->getGamesByTeam($gameRepository, (int)$teamId, $divisionId ? (int)$divisionId : null);
            $data = array_map(fn($game) => $this->formatGameData($game), $games);
            return $this->json($data);
        }

        // Si seulement division_id est fourni
        if ($divisionId) {
            $games = $gameRepository->findBy(['division' => $divisionId]);
            $data = array_map(fn($game) => $this->formatGameData($game), $games);
            return $this->json($data);
        }

        // Sinon, retourner tous les matchs
        $games = $gameRepository->findAll();
        $data = array_map(fn($game) => $this->formatGameData($game), $games);
        return $this->json($data);
    }

    #[Route('/games', name: 'app_game_create', methods: ['POST'])]
    public function createGame(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);
        $game = new Game();

        $game->setDate(isset($data['date']) ? new \DateTime($data['date']) : null);
        $game->setWeek($data['week'] ?? null);

        if (isset($data['is_forfeit']) && $data['is_forfeit']) {
            $game->setIsForfeit(true);
            if (isset($data['forfeit_team'])) {
                $forfeitTeam = (int)$data['forfeit_team'];
                if ($forfeitTeam !== 1 && $forfeitTeam !== 2) {
                    throw ApiProblemException::badRequest('forfeit_team must be 1 or 2');
                }
                $game->setForfeitTeam($forfeitTeam);
            }
            if (isset($data['forfeit_reason'])) {
                $game->setForfeitReason($data['forfeit_reason']);
            }
        } else {
            $game->setScore1($data['score1'] ?? null);
            $game->setScore2($data['score2'] ?? null);
            $game->setWinner($data['winner'] ?? null);
        }

        $this->setGameRelations($game, $data);

        return $this->securedCreateEntity($game, $request);
    }

    #[Route('/games/{id}', name: 'app_game_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateGame(int $id, Request $request): JsonResponse
    {
        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');
        $data = $this->getRequestData($request);

        $game->setDate(new \DateTime($data['date']));
        $game->setWeek($data['week']);

        if (isset($data['is_forfeit']) && $data['is_forfeit']) {
            $game->setIsForfeit(true);
            if (isset($data['forfeit_team'])) {
                $forfeitTeam = (int)$data['forfeit_team'];
                if ($forfeitTeam !== 1 && $forfeitTeam !== 2) {
                    throw ApiProblemException::badRequest('forfeit_team must be 1 or 2');
                }
                $game->setForfeitTeam($forfeitTeam);
            }
            if (isset($data['forfeit_reason'])) {
                $game->setForfeitReason($data['forfeit_reason']);
            }
        } else {
            $game->setIsForfeit(false);
            $game->setForfeitTeam(null);
            $game->setForfeitReason(null);
            $game->setScore1($data['score1']);
            $game->setScore2($data['score2']);
            $game->setWinner($data['winner']);
        }

        $team1 = $this->findEntityOrFail('App\Entity\Team', $data['team1'], 'Team1');
        $team2 = $this->findEntityOrFail('App\Entity\Team', $data['team2'], 'Team2');
        $status = $this->findEntityOrFail('App\Entity\GameStatus', $data['status'], 'GameStatus');
        $division = $this->findEntityOrFail('App\Entity\Division', $data['division'], 'Division');

        $game->setTeam1($team1);
        $game->setTeam2($team2);
        $game->setStatus($status);
        $game->setDivision($division);

        return $this->securedUpdateEntity($game);
    }

    #[Route('/games/{id}', name: 'app_game_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patchGame(int $id, Request $request, SeasonClosureService $seasonClosureService): JsonResponse
    {
        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');
        $data = $this->getRequestData($request);

        if (isset($data['date'])) {
            $game->setDate(new \DateTime($data['date']));
        }
        if (isset($data['week'])) {
            $game->setWeek($data['week']);
        }

        if (isset($data['is_forfeit'])) {
            $game->setIsForfeit((bool)$data['is_forfeit']);
        }
        if (isset($data['forfeit_team'])) {
            $forfeitTeam = (int)$data['forfeit_team'];
            if ($forfeitTeam !== 1 && $forfeitTeam !== 2 && $forfeitTeam !== null) {
                throw ApiProblemException::badRequest('forfeit_team must be 1, 2, or null');
            }
            $game->setForfeitTeam($forfeitTeam);
        }
        if (isset($data['forfeit_reason'])) {
            $game->setForfeitReason($data['forfeit_reason']);
        }

        if (!$game->isForfeit()) {
            if (isset($data['score1'])) {
                $game->setScore1($data['score1']);
            }
            if (isset($data['score2'])) {
                $game->setScore2($data['score2']);
            }
            if (isset($data['winner'])) {
                $game->setWinner($data['winner']);
            }
        }

        $becomesPlayed = isset($data['status'])
            && $game->getStatus()?->getName() !== 'played';

        $this->setGameRelations($game, $data);

        $response = $this->securedUpdateEntity($game);

        if ($becomesPlayed && $game->getStatus()?->getName() === 'played') {
            try {
                $seasonClosureService->onGamePlayed($game);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to check season closure after game patch', [
                    'game_id' => $game->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }

    #[Route('/games/{id}', name: 'app_game_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteGame(int $id): JsonResponse
    {
        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        return $this->securedDeleteEntity($game, 'Game');
    }
}
