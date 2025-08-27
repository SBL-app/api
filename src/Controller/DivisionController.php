<?php

namespace App\Controller;

use App\Repository\DivisionRepository;
use App\Repository\TeamStatRepository;
use App\Repository\TeamRepository;
use App\Repository\SeasonRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Division;
use App\Entity\Season;
use App\Repository\PlayerRepository;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api')]
class DivisionController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Division) {
            throw new \InvalidArgumentException('Entity must be an instance of Division');
        }

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'season_id' => $entity->getSeason() ? $entity->getSeason()->getId() : null,
            'season_name' => $entity->getSeason() ? $entity->getSeason()->getName() : ''
        ];
    }

    /**
     * Formate les données de base d'une division
     */
    private function formatDivisionData(Division $division): array
    {
        return $this->formatEntityData($division);
    }

    #[Route('/division', name: 'app_divisions', methods: ['GET'])]
    public function getDivisions(Request $request, DivisionRepository $divisionRepository): JsonResponse
    {
        $id = $request->query->get('id');

        // Si un ID est fourni, retourner une seule division
        if ($id) {
            $division = $divisionRepository->find($id);
            if (!$division) {
                return $this->json(['error' => 'Division not found'], 404);
            }

            return $this->json($this->formatDivisionData($division));
        }

        // Sinon, retourner toutes les divisions
        $divisions = $divisionRepository->findAll();
        $data = array_map(function ($division) {
            return $this->formatDivisionData($division);
        }, $divisions);
        return $this->json($data);
    }

    #[Route('/division/season', name: 'app_divisions_season', methods: ['GET'])]
    public function getDivisionBySeason(Request $request, DivisionRepository $divisionRepository, TeamStatRepository $teamStatRepository, TeamRepository $teamRepository): JsonResponse
    {
        $seasonId = $request->query->get('id');
        if (!$seasonId) {
            return $this->json(['error' => 'Season ID is required'], 400);
        }

        $divisions = $divisionRepository->findBy(['season' => $seasonId]);
        if (!$divisions) {
            return $this->json(['error' => 'No divisions found for this season'], 404);
        }

        $data = array_map(function ($division) use ($teamStatRepository, $teamRepository) {
            $seasonId = $division->getSeason() ? $division->getSeason()->getId() : null;
            $teams = $teamStatRepository->findBy(['division' => $division]);
            $teamData = array_map(function ($teamStat) use ($teamRepository) {
                $team = $teamStat->getTeam();
                $teamEntity = $teamRepository->find($team->getId());
                return [
                    'id' => $team->getId(),
                    'name' => $teamEntity->getName(),
                    'wins' => $teamStat->getWins(),
                    'losses' => $teamStat->getLosses(),
                    'points' => $teamStat->getPoints()
                ];
            }, $teams);
            return [
                'id' => $division->getId(),
                'name' => $division->getName(),
                'season' => $seasonId,
                'teams' => $teamData
            ];
        }, $divisions);
        return $this->json($data);
    }

    #[Route('/division/teams', name: 'app_division_teams', methods: ['GET'])]
    public function getTeamsByDivision(Request $request, DivisionRepository $divisionRepository, TeamStatRepository $teamStatRepository, TeamRepository $teamRepository, PlayerRepository $playerRepository): JsonResponse
    {
        $id = $request->query->get('id');
        if (!$id) {
            return $this->json(['error' => 'Division ID is required'], 400);
        }

        $division = $divisionRepository->find($id);
        if (!$division) {
            return $this->json(['error' => 'Division not found'], 404);
        }

        $teamStats = $teamStatRepository->findBy(['division' => $division]);
        usort($teamStats, function ($a, $b) {
            return $b->getPoints() - $a->getPoints();
        });
        $data = array_map(function ($teamStat) use ($teamRepository, $playerRepository) {
            $team = $teamStat->getTeam();
            $teamEntity = $teamRepository->find($team->getId());
            $players = $playerRepository->findBy(['team' => $teamEntity]);

            $members = array_map(function ($player) {
                return [
                    'id' => $player->getId(),
                    'name' => $player->getName(),
                    'discord' => $player->getDiscord()
                ];
            }, $players);

            return [
                'id' => $team->getId(),
                'name' => $teamEntity->getName(),
                'captain' => $teamEntity->getCapitain() ? $teamEntity->getCapitain()->getName() : null,
                'members' => $members
            ];
        }, $teamStats);

        return $this->json($data);
    }

    #[Route('/division/games', name: 'app_division_games', methods: ['GET'])]
    public function getGamesByDivision(Request $request, DivisionRepository $divisionRepository, GameRepository $gameRepository): JsonResponse
    {
        $id = $request->query->get('id');
        if (!$id) {
            return $this->json(['error' => 'Division ID is required'], 400);
        }

        $division = $divisionRepository->find($id);
        if (!$division) {
            return $this->json(['error' => 'Division not found'], 404);
        }

        $games = $gameRepository->findBy(['division' => $division]);
        $rep = [];

        foreach ($games as $game) {
            $week = $game->getWeek();
            if (!isset($rep[$week])) {
                $rep[$week] = [
                    'week' => $week,
                    'games' => []
                ];
            }
            $rep[$week]['games'][] = [
                'id' => $game->getId(),
                'date' => $game->getDate()->format('d-m-Y'),
                'team1' => $game->getTeam1()->getName(),
                'team2' => $game->getTeam2()->getName(),
                'score1' => $game->getScore1(),
                'score2' => $game->getScore2(),
                'winner' => $game->getWinner(),
                'status' => $game->getStatus()->getName()
            ];
        }
        $response = array_values($rep);
        return $this->json($response);
    }

    #[Route('/division', name: 'app_division_create', methods: ['POST'])]
    public function createDivision(Request $request): JsonResponse
    {
        try {
            $data = $this->getRequestData($request);
            $division = new Division();

            $division->setName($data['name']);

            if (isset($data['season'])) {
                $season = $this->findEntityOrFail('App\Entity\Season', $data['season'], 'Season');
                $division->setSeason($season);
            } else {
                $division->setSeason(null);
            }

            $this->saveEntity($division);

            return $this->json($this->formatEntityData($division));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/division', name: 'app_division_update', methods: ['PUT'])]
    public function updateDivision(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $division = $this->findEntityOrFail('App\Entity\Division', $id, 'Division');
            $data = $this->getRequestData($request);

            $division->setName($data['name']);

            if (isset($data['season'])) {
                $season = $this->findEntityOrFail('App\Entity\Season', $data['season'], 'Season');
                $division->setSeason($season);
            }

            $this->saveEntity($division);

            return $this->json($this->formatEntityData($division));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/division', name: 'app_division_patch', methods: ['PATCH'])]
    public function patchDivision(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $division = $this->findEntityOrFail('App\Entity\Division', $id, 'Division');
            $data = $this->getRequestData($request);

            if (isset($data['name'])) {
                $division->setName($data['name']);
            }
            if (isset($data['season'])) {
                $season = $this->findEntityOrFail('App\Entity\Season', $data['season'], 'Season');
                $division->setSeason($season);
            }

            $this->saveEntity($division);

            return $this->json($this->formatEntityData($division));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/division', name: 'app_division_delete', methods: ['DELETE'])]
    public function deleteDivision(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $division = $this->findEntityOrFail('App\Entity\Division', $id, 'Division');
            $this->deleteEntity($division);

            return $this->deleteSuccessResponse('Division');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
