<?php

namespace App\Controller;

use App\Repository\TeamStatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class TeamStatController extends AbstractController
{
    #[Route('/team/{id}/stats', name: 'app_team_stats', methods: ['GET'])]
    public function getTeamStats($id, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['teamId' => $id]);
        $data = array_map(function ($teamStat) {
            return [
                'id' => $teamStat->getId(),
                'team_id' => $teamStat->getTeam(),
                'division_id' => $teamStat->getDivision(),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses()
            ];
        }, $teamStats);
        return $this->json($data);
    }

    #[Route('/team/{id}/stats/{divisionId}', name: 'app_team_stats_show', methods: ['GET'])]
    public function getTeamStat($id, $divisionId, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['teamId' => $id],['divisionId' => $divisionId]);
        $data = array_map(function ($teamStat) {
            return [
                'id' => $teamStat->getId(),
                'team_id' => $teamStat->getTeam(),
                'division_id' => $teamStat->getDivision(),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses()
            ];
        }, $teamStats);
        return $this->json($data);
    }

    #[Route('/team/{id}/stats/{divisionId}', name: 'app_team_stats_update', methods: ['PUT'])]
    public function updateTeamStat($id, $divisionId, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['teamId' => $id],['divisionId' => $divisionId]);
        $data = array_map(function ($teamStat) {
            return [
                'id' => $teamStat->getId(),
                'team_id' => $teamStat->getTeam(),
                'division_id' => $teamStat->getDivision(),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses()
            ];
        }, $teamStats);
        return $this->json($data);
    }

    #[Route('/team/{id}/stats/{divisionId}', name: 'app_team_stats_update', methods: ['PATCH'])]
    public function patchTeamStat($id, $divisionId, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['teamId' => $id],['divisionId' => $divisionId]);
        $data = array_map(function ($teamStat) {
            return [
                'id' => $teamStat->getId(),
                'team_id' => $teamStat->getTeam(),
                'division_id' => $teamStat->getDivision(),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses()
            ];
        }, $teamStats);
        return $this->json($data);
    }


    #[Route('/team/{id}/stats/{divisionId}', name: 'app_team_stats_delete', methods: ['DELETE'])]
    public function deleteTeamStat($id, $divisionId, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['teamId' => $id],['divisionId' => $divisionId]);
        $data = array_map(function ($teamStat) {
            return [
                'id' => $teamStat->getId(),
                'team_id' => $teamStat->getTeam(),
                'division_id' => $teamStat->getDivision(),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses()
            ];
        }, $teamStats);
        return $this->json($data);
    }
}