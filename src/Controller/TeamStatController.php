<?php

namespace App\Controller;

use App\Repository\TeamStatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class TeamStatController extends AbstractController
{
    private function formatTeamStat($teamStat): array
    {
        return [
            'id' => $teamStat->getId(),
            'team_id' => $teamStat->getTeam()->getId(),
            'team_name' => $teamStat->getTeam()->getName(),
            'division_id' => $teamStat->getDivision()->getId(),
            'division_name' => $teamStat->getDivision()->getName(),
            'season_id' => $teamStat->getDivision()->getSeason()->getId(),
            'season_name' => $teamStat->getDivision()->getSeason()->getName(),
            'wins' => $teamStat->getWins(),
            'losses' => $teamStat->getLosses(),
            'ties' => $teamStat->getTies(),
            'winRounds' => $teamStat->getWinRounds(),
            'looseRounds' => $teamStat->getLooseRounds(),
            'points' => $teamStat->getPoints(),
        ];
    }

    /**
     * GET /team-stats — liste avec filtres optionnels
     *
     * ?team_id=X                         stats d'une équipe
     * ?division_id=X                     stats d'une division
     * ?team_id=X&division_id=X           stat unique équipe+division
     */
    #[Route('/team-stats', name: 'app_team_stats', methods: ['GET'])]
    public function getTeamStats(Request $request, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamId = $request->query->get('team_id');
        $divisionId = $request->query->get('division_id');

        if ($teamId && $divisionId) {
            $teamStat = $teamStatRepository->findOneBy(['team' => $teamId, 'division' => $divisionId]);
            if (!$teamStat) {
                return $this->json(['error' => 'Team stat not found'], 404);
            }
            return $this->json($this->formatTeamStat($teamStat));
        }

        $criteria = [];
        if ($teamId) {
            $criteria['team'] = $teamId;
        }
        if ($divisionId) {
            $criteria['division'] = $divisionId;
        }

        $teamStats = $teamStatRepository->findBy($criteria);
        return $this->json(array_map(fn($s) => $this->formatTeamStat($s), $teamStats));
    }
}
