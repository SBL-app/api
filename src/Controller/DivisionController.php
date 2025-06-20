<?php

namespace App\Controller;

use App\Repository\DivisionRepository;
use App\Repository\TeamStatRepository;
use App\Repository\TeamRepository;
use App\Repository\SeasonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Division;
use App\Entity\Season;
use App\Repository\PlayerRepository;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class DivisionController extends AbstractController
{
    #[Route('/divisions', name: 'app_division', methods: ['GET'])]
    public function getDivisions(DivisionRepository $divisionRepository): JsonResponse
    {
        $divisions = $divisionRepository->findAll();
        $data = array_map(function ($division) {
            return [
                'id' => $division->getId(),
                'name' => $division->getName(),
                'season' => $division->getSeason() ? $division->getSeason()->getId() : null
            ];
        }, $divisions);
        return $this->json($data);
    }

    #[Route('/division/{id}', name: 'app_division_show', methods: ['GET'])]
    public function getDivision(Division $division): JsonResponse
    {
        $seasonId = $division->getSeason() ? $division->getSeason()->getId() : null;
        return $this->json([
            'id' => $division->getId(),
            'name' => $division->getName(),
            'season_id' => $seasonId,
            'season_name' => $division->getSeason() ? $division->getSeason()->getName() : ''
        ]);
    }

    #[Route('/division/season/{id}', name: 'app_division_season', methods: ['GET'])]
    public function getDivisionBySeason(DivisionRepository $divisionRepository, TeamStatRepository $teamStatRepository, TeamRepository $teamRepository, int $id): JsonResponse
    {
        $divisions = $divisionRepository->findBy(['season' => $id]);
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

    #[Route('/division/{id}/teams', name: 'app_division_teams', methods: ['GET'])]
    public function getTeamsByDivision(Division $division, TeamStatRepository $teamStatRepository, TeamRepository $teamRepository, PlayerRepository $playerRepository): JsonResponse
    {
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

    #[Route('/division/{id}/games', name: 'app_division_games', methods: ['GET'])]
    public function getGamesByDivision(Division $division, GameRepository $gameRepository): JsonResponse
    {
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

    // #[Route('/division', name: 'app_division_create', methods: ['POST'])]
    // public function createDivision(Request $request, Division $division, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $division = new Division();
    //     $division->setName($data['name']);

    //     if (isset($data['season'])) {
    //         $season = $em->getRepository(Season::class)->find($data['season']);
    //         if (!$season) {
    //             return $this->json(['error' => 'Season not found'], Response::HTTP_BAD_REQUEST);
    //         }
    //         $division->setSeason($season);
    //     } else {
    //         $division->setSeason(null);
    //     }

    //     $em->persist($division);
    //     $em->flush();

    //     return $this->json([
    //         'id' => $division->getId(),
    //         'name' => $division->getName(),
    //         'season' => $division->getSeason() ? $division->getSeason()->getId() : null
    //     ]);
    // }

    // #[Route('/division/{id}', name: 'app_division_update', methods: ['PUT'])]
    // public function updateDivision(Request $request, Division $division, EntityManager $em, SeasonRepository $seasonRepository): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $division->setName($data['name']);
        
    //     if (isset($data['season'])) {
    //         $season = $seasonRepository->find($data['season']);
    //         if (!$season) {
    //             return $this->json(['error' => 'Season not found'], Response::HTTP_BAD_REQUEST);
    //         }
    //         $division->setSeason($season);
    //     }
        
    //     $em->persist($division);
    //     $em->flush();
        
    //     return $this->json([
    //         'id' => $division->getId(),
    //         'name' => $division->getName(),
    //         'season' => $division->getSeason() ? $division->getSeason()->getId() : null
    //     ]);
    // }

    // #[Route('/division/{id}', name: 'app_division_patch', methods: ['PATCH'])]
    // public function patchDivision(Request $request, Division $division, EntityManager $em, SeasonRepository $seasonRepository): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     if (isset($data['name'])) {
    //         $division->setName($data['name']);
    //     }
    //     if (isset($data['season'])) {
    //         $season = $seasonRepository->find($data['season']);
    //         if (!$season) {
    //             return $this->json(['error' => 'Season not found'], Response::HTTP_BAD_REQUEST);
    //         }
    //         $division->setSeason($season);
    //     }
    //     $em->persist($division);
    //     $em->flush();
    //     return $this->json([
    //         'id' => $division->getId(),
    //         'name' => $division->getName(),
    //         'season' => $division->getSeason() ? $division->getSeason()->getId() : null
    //     ]);
    // }

    // #[Route('/division/{id}', name: 'app_division_delete', methods: ['DELETE'])]
    // public function deleteDivision(Division $division, EntityManager $em): JsonResponse
    // {
    //     $em->remove($division);
    //     $em->flush();
    //     return $this->json([
    //         'message' => 'Division deleted successfully'
    //     ]);
    // }
}
