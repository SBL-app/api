<?php

namespace App\Controller;

use App\Repository\TeamStatRepository;
use App\Repository\TeamRepository;
use App\Repository\DivisionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\TeamStat;

#[Route('/api')]
class TeamStatController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof TeamStat) {
            throw new \InvalidArgumentException('Entity must be an instance of TeamStat');
        }

        return [
            'id' => $entity->getId(),
            'team_id' => $entity->getTeam()->getId(),
            'team_name' => $entity->getTeam()->getName(),
            'division_id' => $entity->getDivision()->getId(),
            'division_name' => $entity->getDivision()->getName(),
            'season_id' => $entity->getDivision()->getSeason()->getId(),
            'season_name' => $entity->getDivision()->getSeason()->getName(),
            'wins' => $entity->getWins(),
            'losses' => $entity->getLosses(),
            'ties' => $entity->getTies(),
            'winRounds' => $entity->getWinRounds(),
            'looseRounds' => $entity->getLooseRounds(),
            'points' => $entity->getPoints()
        ];
    }

    /**
     * Formate les données de base d'une statistique d'équipe
     */
    private function formatTeamStatData(TeamStat $teamStat): array
    {
        return $this->formatEntityData($teamStat);
    }

    #[Route('/teamStats', name: 'app_team_stats', methods: ['GET'])]
    public function getTeamStats(Request $request, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamId = $request->query->get('team_id');
        $divisionId = $request->query->get('division_id');

        // Si team_id ET division_id sont fournis, retourner la stat spécifique
        if ($teamId && $divisionId) {
            $teamStat = $teamStatRepository->findOneBy(['team' => $teamId, 'division' => $divisionId]);
            if (!$teamStat) {
                return $this->json(['error' => 'Team stat not found for this team and division'], 404);
            }
            return $this->json($this->formatTeamStatData($teamStat));
        }

        // Si seulement team_id est fourni, retourner toutes les stats de cette équipe
        if ($teamId) {
            $teamStats = $teamStatRepository->findBy(['team' => $teamId]);
            if (empty($teamStats)) {
                return $this->json(['error' => 'No team stats found for this team'], 404);
            }
            $data = array_map(function ($teamStat) {
                return $this->formatTeamStatData($teamStat);
            }, $teamStats);
            return $this->json($data);
        }

        // Si seulement division_id est fourni, retourner toutes les stats de cette division
        if ($divisionId) {
            $teamStats = $teamStatRepository->findBy(['division' => $divisionId]);
            if (empty($teamStats)) {
                return $this->json(['error' => 'No team stats found for this division'], 404);
            }
            $data = array_map(function ($teamStat) {
                return $this->formatTeamStatData($teamStat);
            }, $teamStats);
            return $this->json($data);
        }

        // Sinon, retourner toutes les statistiques
        $teamStats = $teamStatRepository->findAll();
        $data = array_map(function ($teamStat) {
            return $this->formatTeamStatData($teamStat);
        }, $teamStats);
        return $this->json($data);
    }

    #[Route('/teamStats', name: 'app_team_stats_create', methods: ['POST'])]
    public function createTeamStat(Request $request): JsonResponse
    {
        try {
            $data = $this->getRequestData($request);

            $team = $this->findEntityOrFail('App\Entity\Team', $data['team'], 'Team');
            $division = $this->findEntityOrFail('App\Entity\Division', $data['division'], 'Division');

            $teamStat = new TeamStat();
            $teamStat->setTeam($team);
            $teamStat->setDivision($division);
            $teamStat->setWins($data['wins'] ?? 0);
            $teamStat->setLosses($data['losses'] ?? 0);
            $teamStat->setTies($data['ties'] ?? 0);
            $teamStat->setWinRounds($data['winRounds'] ?? 0);
            $teamStat->setLooseRounds($data['looseRounds'] ?? 0);
            $teamStat->setPoints($data['points'] ?? 0);

            $this->saveEntity($teamStat);

            return $this->json($this->formatEntityData($teamStat));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teamStats', name: 'app_team_stats_update', methods: ['PUT'])]
    public function updateTeamStat(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $teamStat = $this->findEntityOrFail('App\Entity\TeamStat', $id, 'TeamStat');
            $data = $this->getRequestData($request);

            $team = $this->findEntityOrFail('App\Entity\Team', $data['team'], 'Team');
            $division = $this->findEntityOrFail('App\Entity\Division', $data['division'], 'Division');

            $teamStat->setTeam($team);
            $teamStat->setDivision($division);
            $teamStat->setWins($data['wins'] ?? 0);
            $teamStat->setLosses($data['losses'] ?? 0);
            $teamStat->setTies($data['ties'] ?? 0);
            $teamStat->setWinRounds($data['winRounds'] ?? 0);
            $teamStat->setLooseRounds($data['looseRounds'] ?? 0);
            $teamStat->setPoints($data['points'] ?? 0);

            $this->saveEntity($teamStat);

            return $this->json($this->formatEntityData($teamStat));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teamStats', name: 'app_team_stats_patch', methods: ['PATCH'])]
    public function patchTeamStat(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $teamStat = $this->findEntityOrFail('App\Entity\TeamStat', $id, 'TeamStat');
            $data = $this->getRequestData($request);

            if (isset($data['team'])) {
                $team = $this->findEntityOrFail('App\Entity\Team', $data['team'], 'Team');
                $teamStat->setTeam($team);
            }
            if (isset($data['division'])) {
                $division = $this->findEntityOrFail('App\Entity\Division', $data['division'], 'Division');
                $teamStat->setDivision($division);
            }
            if (isset($data['wins'])) {
                $teamStat->setWins($data['wins']);
            }
            if (isset($data['losses'])) {
                $teamStat->setLosses($data['losses']);
            }
            if (isset($data['ties'])) {
                $teamStat->setTies($data['ties']);
            }
            if (isset($data['winRounds'])) {
                $teamStat->setWinRounds($data['winRounds']);
            }
            if (isset($data['looseRounds'])) {
                $teamStat->setLooseRounds($data['looseRounds']);
            }
            if (isset($data['points'])) {
                $teamStat->setPoints($data['points']);
            }

            $this->saveEntity($teamStat);

            return $this->json($this->formatEntityData($teamStat));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teamStats', name: 'app_team_stats_delete', methods: ['DELETE'])]
    public function deleteTeamStat(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $teamStat = $this->findEntityOrFail('App\Entity\TeamStat', $id, 'TeamStat');
            $this->deleteEntity($teamStat);

            return $this->deleteSuccessResponse('TeamStat');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
