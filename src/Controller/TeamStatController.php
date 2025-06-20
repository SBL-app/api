<?php

namespace App\Controller;

use App\Repository\TeamStatRepository;
use App\Repository\TeamRepository;
use App\Repository\DivisionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\TeamStat;

class TeamStatController extends AbstractController
{
    #[Route('/teamStats', name: 'app_team_stats', methods: ['GET'])]
    public function getTeamStats(TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findAll();
        $data = array_map(function ($teamStat){
            return [
                'id' => $teamStat->getId(),
                'team_id' => $teamStat->getTeam()->getId(),
                'team_name' => $teamStat->getTeam()->getName(),
                'division_id' => $teamStat->getDivision()->getId(),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses(),
                'ties' => $teamStat->getTies(),
                'winRounds' => $teamStat->getWinRounds(),
                'looseRounds' => $teamStat->getLooseRounds(),
                'points' => $teamStat->getPoints()
            ];
        }, $teamStats);
        return $this->json($data);
    }

    #[Route('/teamStats/{teamId}', name: 'app_team_stat_by_team', methods: ['GET'])]
    public function getTeamStat($teamId, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['team' => $teamId]);
        $data = array_map(function ($teamStat) use ($teamId){
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
                'points' => $teamStat->getPoints()
            ];
        }, $teamStats);
        return $this->json($data);
    }

    #[Route('/teamStats/{teamId}/{divisionId}', name: 'app_team_stat_by_team_and_division', methods: ['GET'])]
    public function getTeamStatByIDAndDivision($teamId, $divisionId, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['team' => $teamId]);
        $teamStats = $teamStatRepository->findBy(['division' => $divisionId]);
        $data = array_map(function ($teamStat) use ($teamId, $divisionId){
            return [
                'selected_team_id' => $teamId, // This is the team id that was passed in the URL '/teamStats/{id}
                'selected_division_id' => $divisionId, // This is the division id that was passed in the URL '/teamStats/{id}/{divisionId}
                'id' => $teamStat->getId(),
                'team_id' => $teamStat->getTeam()->getId(),
                'team_name' => $teamStat->getTeam()->getName(), // This is the team name that was passed in the URL '/teamStats/{id}
                'division_id' => $teamStat->getDivision()->getId(),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses(),
                'ties' => $teamStat->getTies(),
                'winRounds' => $teamStat->getWinRounds(),
                'looseRounds' => $teamStat->getLooseRounds(),
                'points' => $teamStat->getPoints()
            ];
        }, $teamStats);
        return $this->json($data);
    }

    #[Route('/teamStats/division/{divisionId}', name: 'app_team_stat_by_division', methods: ['GET'])]
    public function getTeamStatByDivision($divisionId, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['division' => $divisionId]);
        $data = array_map(function ($teamStat) use ($divisionId){
            return [
                'selected_division_id' => $divisionId, // This is the division id that was passed in the URL '/teamStats/{id}/{divisionId}
                'id' => $teamStat->getId(),
                'team_id' => $teamStat->getTeam()->getId(),
                'team_name' => $teamStat->getTeam()->getName(), // This is the team name that was passed in the URL '/teamStats/{id}
                'division_id' => $teamStat->getDivision()->getId(),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses(),
                'ties' => $teamStat->getTies(),
                'winRounds' => $teamStat->getWinRounds(),
                'looseRounds' => $teamStat->getLooseRounds(),
                'points' => $teamStat->getPoints()
            ];
        }, $teamStats);
        return $this->json($data);
    }

    // #[Route('/teamStats', name: 'app_team_stats_create', methods: ['POST'])]
    // public function createTeamStat(Request $request, TeamRepository $teamRepository, DivisionRepository $divisionRepository, EntityManager $entityManager): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $teamId = $data['team'];
    //     $divisionId = $data['division'];

    //     $team = $teamRepository->find($teamId);
    //     $division = $divisionRepository->find($divisionId);

    //     if (!$team) {
    //         return $this->json([
    //         'error' => 'Invalid team id'
    //         ], 400);
    //     }

    //     if (!$division) {
    //         return $this->json([
    //         'error' => 'Invalid division id'
    //         ], 400);
    //     }

    //     $teamStat = new TeamStat();
    //     $teamStat->setTeam($team);
    //     $teamStat->setDivision($division);
    //     $teamStat->setWins($data['wins']? $data['wins'] : 0);
    //     $teamStat->setLosses($data['losses']? $data['losses'] : 0);
    //     $teamStat->setTies($data['ties']? $data['ties'] : 0);
    //     $teamStat->setWinRounds($data['winRounds']? $data['winRounds'] : 0);
    //     $teamStat->setLooseRounds($data['looseRounds']? $data['looseRounds'] : 0);
    //     $teamStat->setPoints($data['points']? $data['points'] : 0);

    //     $entityManager->persist($teamStat);
    //     $entityManager->flush();

    //     return $this->json([
    //         'id' => $teamStat->getId(),
    //         'team_id' => $teamStat->getTeam()->getId(),
    //         'team_name' => $teamStat->getTeam()->getName(), // This is the team name that was passed in the URL '/teamStats/{id}
    //         'division_id' => $teamStat->getDivision()->getId(),
    //         'wins' => $teamStat->getWins(),
    //         'losses' => $teamStat->getLosses(),
    //         'ties' => $teamStat->getTies(),
    //         'winRounds' => $teamStat->getWinRounds(),
    //         'looseRounds' => $teamStat->getLooseRounds(),
    //         'points' => $teamStat->getPoints()
    //     ]);
    // }

    // #[Route('/teamStats/{teamId}/{divisionId}', name: 'app_team_stats_put', methods: ['PUT'])]
    // public function updateTeamStat($teamId, $divisionId, Request $request, TeamStatRepository $teamStatRepository, EntityManager $entityManager): JsonResponse
    // {
    //     $teamStats = $teamStatRepository->findBy(['team' => $teamId]);
    //     $teamStats = $teamStatRepository->findBy(['division' => $divisionId]);
    //     $data = json_decode($request->getContent(), true);
    //     $teamStat = $teamStats[0];

    //     if (!$teamStat) {
    //         return $this->json([
    //             'error' => 'Team Stat not found'
    //         ], 404);
    //     }

    //     $teamStat->setWins($data['wins']);
    //     $teamStat->setLosses($data['losses']);
    //     $teamStat->setTies($data['ties']);
    //     $teamStat->setWinRounds($data['winRounds']);
    //     $teamStat->setLooseRounds($data['looseRounds']);
    //     $teamStat->setPoints($data['points']);

    //     $entityManager->persist($teamStat);
    //     $entityManager->flush();

    //     return $this->json([
    //         'id' => $teamStat->getId(),
    //         'team_id' => $teamStat->getTeam()->getId(),
    //         'team_name' => $teamStat->getTeam()->getName(), // This is the team name that was passed in the URL '/teamStats/{id}
    //         'division_id' => $teamStat->getDivision()->getId(),
    //         'wins' => $teamStat->getWins(),
    //         'losses' => $teamStat->getLosses(),
    //         'ties' => $teamStat->getTies(),
    //         'points' => $teamStat->getPoints()
    //     ]);
    // }

    // #[Route('/teamStats/{teamId}/{divisionId}', name: 'app_team_stats_patch', methods: ['PATCH'])]
    // public function patchTeamStat($teamId, $divisionId, Request $request, TeamStatRepository $teamStatRepository, EntityManager $entityManager): JsonResponse
    // {
    //     $teamStats = $teamStatRepository->findBy(['team' => $teamId]);
    //     $teamStats = $teamStatRepository->findBy(['division' => $divisionId]);
    //     $data = json_decode($request->getContent(), true);
    //     $teamStat = $teamStats[0];

    //     if (!$teamStat) {
    //         return $this->json([
    //             'error' => 'Team Stat not found'
    //         ], 404);
    //     }
    //     if (isset($data['wins'])) {
    //         $teamStat->setWins($data['wins']);
    //     }
    //     if (isset($data['losses'])) {
    //         $teamStat->setLosses($data['losses']);
    //     }
    //     if (isset($data['ties'])) {
    //         $teamStat->setTies($data['ties']);
    //     }
    //     if (isset($data['winRounds'])) {
    //         $teamStat->setWinRounds($data['winRounds']);
    //     }
    //     if (isset($data['looseRounds'])) {
    //         $teamStat->setLooseRounds($data['looseRounds']);
    //     }
    //     if (isset($data['points'])) {
    //         $teamStat->setPoints($data['points']);
    //     }

    //     $entityManager->persist($teamStat);
    //     $entityManager->flush();

    //     return $this->json([
    //         'id' => $teamStat->getId(),
    //         'team_id' => $teamStat->getTeam()->getId(),
    //         'team_name' => $teamStat->getTeam()->getName(), // This is the team name that was passed in the URL '/teamStats/{id}
    //         'division_id' => $teamStat->getDivision()->getId(),
    //         'wins' => $teamStat->getWins(),
    //         'losses' => $teamStat->getLosses(),
    //         'ties' => $teamStat->getTies(),
    //         'winRounds' => $teamStat->getWinRounds(),
    //         'looseRounds' => $teamStat->getLooseRounds(),
    //         'points' => $teamStat->getPoints()
    //     ]);
    // }

    // #[Route('/teamStats/{teamId}/{divisionId}', name: 'app_team_stats_delete', methods: ['DELETE'])]
    // public function deleteTeamStat($teamId,$divisionId,TeamStatRepository $teamStatRepository,EntityManager $em): JsonResponse
    // {
    //     $teamStats = $teamStatRepository->findBy(['team' => $teamId]);
    //     $teamStats = $teamStatRepository->findBy(['division' => $divisionId]);

    //     $em->remove($teamStats[0]);
    //     $em->flush();
    //     return $this->json([
    //         'message' => 'Team Stat deleted successfully'
    //     ]);
    // }
}