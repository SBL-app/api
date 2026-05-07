<?php

namespace App\Controller;

use App\Repository\DivisionRepository;
use App\Repository\TeamStatRepository;
use App\Repository\TeamRepository;
use App\Repository\PlayerRepository;
use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Division;

class DivisionController extends AbstractController
{
    private function formatDivision(Division $division): array
    {
        return [
            'id' => $division->getId(),
            'name' => $division->getName(),
            'season_id' => $division->getSeason() ? $division->getSeason()->getId() : null,
            'season_name' => $division->getSeason() ? $division->getSeason()->getName() : '',
        ];
    }

    #[Route('/divisions/{id}', name: 'app_division_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getDivision(Division $division): JsonResponse
    {
        return $this->json($this->formatDivision($division));
    }

    #[Route('/divisions', name: 'app_divisions', methods: ['GET'])]
    public function getDivisions(DivisionRepository $divisionRepository): JsonResponse
    {
        $data = array_map(fn($d) => $this->formatDivision($d), $divisionRepository->findAll());
        return $this->json($data);
    }

    #[Route('/seasons/{seasonId}/divisions', name: 'app_divisions_by_season', methods: ['GET'], requirements: ['seasonId' => '\d+'])]
    public function getDivisionsBySeason(int $seasonId, DivisionRepository $divisionRepository, TeamStatRepository $teamStatRepository, TeamRepository $teamRepository): JsonResponse
    {
        $divisions = $divisionRepository->findBy(['season' => $seasonId]);
        $data = array_map(function ($division) use ($teamStatRepository, $teamRepository) {
            $teams = $teamStatRepository->findBy(['division' => $division]);
            $teamData = array_map(function ($teamStat) use ($teamRepository) {
                $team = $teamStat->getTeam();
                $teamEntity = $teamRepository->find($team->getId());
                return [
                    'id' => $team->getId(),
                    'name' => $teamEntity->getName(),
                    'wins' => $teamStat->getWins(),
                    'losses' => $teamStat->getLosses(),
                    'points' => $teamStat->getPoints(),
                ];
            }, $teams);
            return [
                'id' => $division->getId(),
                'name' => $division->getName(),
                'season' => $division->getSeason() ? $division->getSeason()->getId() : null,
                'teams' => $teamData,
            ];
        }, $divisions);
        return $this->json($data);
    }

    #[Route('/divisions/{divisionId}/teams', name: 'app_division_teams', methods: ['GET'], requirements: ['divisionId' => '\d+'])]
    public function getTeamsByDivision(int $divisionId, DivisionRepository $divisionRepository, TeamStatRepository $teamStatRepository, TeamRepository $teamRepository, PlayerRepository $playerRepository): JsonResponse
    {
        $division = $divisionRepository->find($divisionId);
        if (!$division) {
            return $this->json(['error' => 'Division not found'], 404);
        }

        $teamStats = $teamStatRepository->findBy(['division' => $division]);
        usort($teamStats, fn($a, $b) => $b->getPoints() - $a->getPoints());

        $data = array_map(function ($teamStat) use ($teamRepository, $playerRepository) {
            $team = $teamStat->getTeam();
            $teamEntity = $teamRepository->find($team->getId());
            $members = array_map(fn($p) => [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'discord' => $p->getDiscord(),
            ], $playerRepository->findBy(['team' => $teamEntity]));

            return [
                'id' => $team->getId(),
                'name' => $teamEntity->getName(),
                'captain' => $teamEntity->getCapitain() ? $teamEntity->getCapitain()->getName() : null,
                'members' => $members,
            ];
        }, $teamStats);

        return $this->json($data);
    }

    #[Route('/divisions/{divisionId}/games', name: 'app_division_games', methods: ['GET'], requirements: ['divisionId' => '\d+'])]
    public function getGamesByDivision(int $divisionId, DivisionRepository $divisionRepository, GameRepository $gameRepository): JsonResponse
    {
        $division = $divisionRepository->find($divisionId);
        if (!$division) {
            return $this->json(['error' => 'Division not found'], 404);
        }

        $rep = [];
        foreach ($gameRepository->findBy(['division' => $division]) as $game) {
            $week = $game->getWeek();
            if (!isset($rep[$week])) {
                $rep[$week] = ['week' => $week, 'games' => []];
            }
            $rep[$week]['games'][] = [
                'id' => $game->getId(),
                'date' => $game->getDate() ? $game->getDate()->format('d-m-Y') : null,
                'team1' => $game->getTeam1() ? $game->getTeam1()->getName() : null,
                'team2' => $game->getTeam2() ? $game->getTeam2()->getName() : null,
                'score1' => $game->getScore1(),
                'score2' => $game->getScore2(),
                'winner' => $game->getWinner(),
                'status' => $game->getStatus() ? $game->getStatus()->getName() : null,
            ];
        }
        return $this->json(array_values($rep));
    }
}
