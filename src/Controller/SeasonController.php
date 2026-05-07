<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Season;
use App\Repository\DivisionRepository;
use App\Repository\GameRepository;
use App\Repository\GameStatusRepository;
use App\Repository\SeasonRepository;
use App\Repository\RegistrationRepository;

class SeasonController extends AbstractController
{
    private function formatSeason(Season $season): array
    {
        return [
            'id' => $season->getId(),
            'name' => $season->getName(),
            'start_date' => $season->getStartDate()->format('d-m-Y'),
            'end_date' => $season->getEndDate()->format('d-m-Y'),
        ];
    }

    private function calcCompletion(Season $season, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): array
    {
        $total = 0;
        $finished = 0;
        $finishedStatus = $gameStatusRepository->findOneBy(['name' => 'joué']);
        foreach ($divisionRepository->findBy(['season' => $season]) as $division) {
            foreach ($gameRepository->findBy(['division' => $division]) as $game) {
                $total++;
                if ($game->getStatus() === $finishedStatus) {
                    $finished++;
                }
            }
        }
        return ['total' => $total, 'finished' => $finished, 'percentage' => $total > 0 ? ($finished / $total) * 100 : 0];
    }

    #[Route('/seasons/{id}', name: 'app_season_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getSeason(Season $season): JsonResponse
    {
        return $this->json($this->formatSeason($season));
    }

    #[Route('/seasons', name: 'app_seasons', methods: ['GET'])]
    public function getSeasons(SeasonRepository $seasonRepository, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $data = [];
        foreach ($seasonRepository->findAll() as $season) {
            $stats = $this->calcCompletion($season, $divisionRepository, $gameRepository, $gameStatusRepository);
            $data[] = array_merge($this->formatSeason($season), [
                'total_games' => $stats['total'],
                'finished_games' => $stats['finished'],
                'percentage' => number_format($stats['percentage'], 2),
            ]);
        }
        return $this->json($data);
    }

    /**
     * GET /seasons/{id}/completion — pourcentage de matchs joués
     *
     * ?decimal=N  précision décimale (défaut: 2)
     */
    #[Route('/seasons/{id}/completion', name: 'app_season_completion', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getSeasonCompletion(Season $season, Request $request, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $decimal = (int) $request->query->get('decimal', 2);
        $stats = $this->calcCompletion($season, $divisionRepository, $gameRepository, $gameStatusRepository);
        return $this->json(array_merge($this->formatSeason($season), [
            'total_games' => $stats['total'],
            'finished_games' => $stats['finished'],
            'percentage' => number_format($stats['percentage'], $decimal),
        ]));
    }

    #[Route('/seasons/{id}/teams', name: 'app_season_teams', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getSeasonTeams(Season $season, RegistrationRepository $registrationRepository): JsonResponse
    {
        $teamsData = array_map(fn($r) => [
            'id' => $r->getTeam()->getId(),
            'name' => $r->getTeam()->getName(),
        ], $registrationRepository->findBy(['season' => $season]));

        return $this->json(array_merge($this->formatSeason($season), ['teams' => $teamsData]));
    }

    #[Route('/seasons/{id}/games', name: 'app_season_games', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getSeasonGames(Season $season, DivisionRepository $divisionRepository, GameRepository $gameRepository): JsonResponse
    {
        $data = [];
        foreach ($divisionRepository->findBy(['season' => $season]) as $division) {
            foreach ($gameRepository->findBy(['division' => $division]) as $game) {
                $data[] = [
                    'id' => $game->getId(),
                    'date' => $game->getDate() ? $game->getDate()->format('d-m-Y') : null,
                    'week' => $game->getWeek(),
                    'team1' => $game->getTeam1() ? $game->getTeam1()->getName() : null,
                    'team2' => $game->getTeam2() ? $game->getTeam2()->getName() : null,
                    'score1' => $game->getScore1(),
                    'score2' => $game->getScore2(),
                    'winner' => $game->getWinner(),
                    'status' => $game->getStatus() ? $game->getStatus()->getName() : null,
                ];
            }
        }
        return $this->json($data);
    }
}
