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
    #[Route('/teamStats/{id}', name: 'app_team_stats', methods: ['GET'])]
    public function getTeamStats($id, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['team' => $id]);
        $data = array_map(function ($teamStat) use ($id){
            return [
                'selected_team_id' => $id, // This is the team id that was passed in the URL '/teamStats/{id}
                'id' => $teamStat->getId(),
                'team_id' => $teamStat->getTeam()->getId(),
                'division_id' => $teamStat->getDivision()->getId(),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses(),
                'points' => $teamStat->getPoints()
            ];
        }, $teamStats);
        return $this->json($data);
    }

    #[Route('/teamStats/{id}/{divisionId}', name: 'app_team_stats_show', methods: ['GET'])]
    public function getTeamStat($id, $divisionId, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['team' => $id]);
        $teamStats = $teamStatRepository->findBy(['division' => $divisionId]);
        $data = array_map(function ($teamStat) use ($id, $divisionId){
            return [
                'selected_team_id' => $id, // This is the team id that was passed in the URL '/teamStats/{id}
                'selected_division_id' => $divisionId, // This is the division id that was passed in the URL '/teamStats/{id}/{divisionId}
                'id' => $teamStat->getId(),
                'team_id' => $teamStat->getTeam()->getId(),
                'division_id' => $teamStat->getDivision()->getId(),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses(),
                'ties' => $teamStat->getTies(),
                'points' => $teamStat->getPoints()
            ];
        }, $teamStats);
        return $this->json($data);
    }

    #[Route('/teamStats', name: 'app_team_stats_create', methods: ['POST'])]
    public function createTeamStat(Request $request, TeamRepository $teamRepository, DivisionRepository $divisionRepository, EntityManager $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $teamId = $data['team'];
        $divisionId = $data['division'];

        $team = $teamRepository->find($teamId);
        $division = $divisionRepository->find($divisionId);

        if (!$team) {
            return $this->json([
            'error' => 'Invalid team id'
            ], 400);
        }

        if (!$division) {
            return $this->json([
            'error' => 'Invalid division id'
            ], 400);
        }

        $teamStat = new TeamStat();
        $teamStat->setTeam($team);
        $teamStat->setDivision($division);
        $teamStat->setWins($data['wins']);
        $teamStat->setLosses($data['losses']);
        $teamStat->setTies($data['ties']);
        $teamStat->setPoints($data['points']);

        $entityManager->persist($teamStat);
        $entityManager->flush();

        return $this->json([
            'id' => $teamStat->getId(),
            'team_id' => $teamStat->getTeam()->getId(),
            'division_id' => $teamStat->getDivision()->getId(),
            'wins' => $teamStat->getWins(),
            'losses' => $teamStat->getLosses(),
            'ties' => $teamStat->getTies(),
            'points' => $teamStat->getPoints()
        ]);
    }

    #[Route('/teamStats/{id}/{divisionId}', name: 'app_team_stats_update', methods: ['PUT'])]
    public function updateTeamStat($id, $divisionId, Request $request, TeamStatRepository $teamStatRepository, EntityManager $entityManager): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['team' => $id]);
        $teamStats = $teamStatRepository->findBy(['division' => $divisionId]);
        $data = json_decode($request->getContent(), true);
        $teamStat = $teamStats[0];

        if (!$teamStat) {
            return $this->json([
                'error' => 'Team Stat not found'
            ], 404);
        }

        $teamStat->setWins($data['wins']);
        $teamStat->setLosses($data['losses']);
        $teamStat->setTies($data['ties']);
        $teamStat->setPoints($data['points']);

        $entityManager->persist($teamStat);
        $entityManager->flush();

        return $this->json([
            'id' => $teamStat->getId(),
            'team_id' => $teamStat->getTeam()->getId(),
            'division_id' => $teamStat->getDivision()->getId(),
            'wins' => $teamStat->getWins(),
            'losses' => $teamStat->getLosses(),
            'ties' => $teamStat->getTies(),
            'points' => $teamStat->getPoints()
        ]);
    }

    #[Route('/teamStats/{id}/{divisionId}', name: 'app_team_stats_patch', methods: ['PATCH'])]
    public function patchTeamStat($id, $divisionId, Request $request, TeamStatRepository $teamStatRepository, EntityManager $entityManager): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['team' => $id]);
        $teamStats = $teamStatRepository->findBy(['division' => $divisionId]);
        $data = json_decode($request->getContent(), true);
        $teamStat = $teamStats[0];

        if (!$teamStat) {
            return $this->json([
                'error' => 'Team Stat not found'
            ], 404);
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

        if (isset($data['points'])) {
            $teamStat->setPoints($data['points']);
        }

        $entityManager->persist($teamStat);
        $entityManager->flush();

        return $this->json([
            'id' => $teamStat->getId(),
            'team_id' => $teamStat->getTeam()->getId(),
            'division_id' => $teamStat->getDivision()->getId(),
            'wins' => $teamStat->getWins(),
            'losses' => $teamStat->getLosses(),
            'ties' => $teamStat->getTies(),
            'points' => $teamStat->getPoints()
        ]);
    }

    #[Route('/teamStats/{id}/{divisionId}', name: 'app_team_stats_delete', methods: ['DELETE'])]
    public function deleteTeamStat($id,$divisionId,TeamStatRepository $teamStatRepository,EntityManager $em): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['team' => $id]);
        $teamStats = $teamStatRepository->findBy(['division' => $divisionId]);

        $em->remove($teamStats[0]);
        $em->flush();
        return $this->json([
            'message' => 'Team Stat deleted successfully'
        ]);
    }
}