<?php

namespace App\Controller;

use App\Repository\TeamRepository;
use App\Repository\PlayerRepository;
use App\Repository\TeamStatRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api')]
class TeamController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Team) {
            throw new \InvalidArgumentException('Entity must be an instance of Team');
        }

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'captain' => $entity->getCapitain() ? $entity->getCapitain()->getName() : null,
            'captain_id' => $entity->getCapitain() ? $entity->getCapitain()->getId() : null
        ];
    }

    /**
     * Formate les données complètes d'une équipe avec joueurs et statistiques
     */
    private function formatTeamWithDetails(Team $team, PlayerRepository $playerRepository, TeamStatRepository $teamStatRepository): array
    {
        // Informations de base de l'équipe
        $teamData = $this->formatEntityData($team);

        // Récupération des joueurs de l'équipe
        $players = $playerRepository->findBy(['team' => $team]);
        $playersData = array_map(function ($player) {
            return [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'discord' => $player->getDiscord()
            ];
        }, $players);

        // Récupération des statistiques de l'équipe dans toutes les divisions
        $teamStats = $teamStatRepository->findBy(['team' => $team]);
        $statsData = array_map(function ($teamStat) use ($teamStatRepository, $team) {
            $division = $teamStat->getDivision();

            // Récupération de toutes les équipes de cette division pour calculer la position
            $allTeamStatsInDivision = $teamStatRepository->findBy(['division' => $division]);

            // Tri par points décroissant pour déterminer le classement
            usort($allTeamStatsInDivision, function ($a, $b) {
                return $b->getPoints() - $a->getPoints();
            });

            // Recherche de la position de l'équipe courante
            $position = 1;
            foreach ($allTeamStatsInDivision as $index => $stat) {
                if ($stat->getTeam()->getId() === $team->getId()) {
                    $position = $index + 1;
                    break;
                }
            }

            return [
                'division_id' => $division->getId(),
                'division_name' => $division->getName(),
                'season_id' => $division->getSeason()->getId(),
                'season_name' => $division->getSeason()->getName(),
                'position' => $position,
                'total_teams' => count($allTeamStatsInDivision),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses(),
                'ties' => $teamStat->getTies(),
                'winRounds' => $teamStat->getWinRounds(),
                'looseRounds' => $teamStat->getLooseRounds(),
                'points' => $teamStat->getPoints()
            ];
        }, $teamStats);

        return [
            'team' => $teamData,
            'players' => $playersData,
            'stats' => $statsData,
            'players_count' => count($playersData),
            'divisions_count' => count($statsData)
        ];
    }

    #[Route('/teams', name: 'app_teams', methods: ['GET'])]
    public function getTeams(Request $request, TeamRepository $teamRepository): JsonResponse
    {
        $id = $request->query->get('id');

        // Si un ID est fourni, retourner l'équipe spécifique
        if ($id) {
            return $this->getEntityById('App\Entity\Team', $id, 'Team');
        }

        // Sinon, retourner toutes les équipes
        $teams = $teamRepository->findAll();
        $data = array_map(function ($team) {
            return $this->formatEntityData($team);
        }, $teams);
        return $this->json($data);
    }

    #[Route('/teams/details', name: 'app_team_details', methods: ['GET'])]
    public function getTeamDetails(Request $request, TeamRepository $teamRepository, PlayerRepository $playerRepository, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamId = $request->query->get('team_id');
        if (!$teamId) {
            return $this->json(['error' => 'Team ID is required'], 400);
        }

        try {
            // Récupération de l'équipe
            $team = $this->findEntityOrFail('App\Entity\Team', $teamId, 'Team');

            // Formatage des données complètes avec joueurs et statistiques
            $teamDetails = $this->formatTeamWithDetails($team, $playerRepository, $teamStatRepository);

            return $this->json($teamDetails);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams', name: 'app_team_create', methods: ['POST'])]
    public function createTeam(Request $request): JsonResponse
    {
        try {
            $data = $this->getRequestData($request);
            $team = new Team();

            $team->setName($data['name']);

            $this->saveEntity($team);

            return $this->json($this->formatEntityData($team));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/teams', name: 'app_team_update', methods: ['PUT'])]
    public function updateTeam(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');
            $data = $this->getRequestData($request);

            $team->setName($data['name']);

            $this->saveEntity($team);

            return $this->json($this->formatEntityData($team));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams', name: 'app_team_patch', methods: ['PATCH'])]
    public function patchTeam(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');
            $data = $this->getRequestData($request);

            if (isset($data['name'])) {
                $team->setName($data['name']);
            }

            $this->saveEntity($team);

            return $this->json($this->formatEntityData($team));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams', name: 'app_team_delete', methods: ['DELETE'])]
    public function deleteTeam(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');
            $this->deleteEntity($team);

            return $this->deleteSuccessResponse('Team');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
