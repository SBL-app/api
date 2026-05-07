<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Game;
use App\Repository\GameRepository;

class GameController extends AbstractController
{
    private function formatGame(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'date' => $game->getDate() ? $game->getDate()->format('Y-m-d H:i:s') : null,
            'week' => $game->getWeek(),
            'team1' => $game->getTeam1() ? $game->getTeam1()->getName() : null,
            'team2' => $game->getTeam2() ? $game->getTeam2()->getName() : null,
            'score1' => $game->getScore1(),
            'score2' => $game->getScore2(),
            'winner' => $game->getWinner(),
            'status' => $game->getStatus() ? $game->getStatus()->getName() : null,
            'division' => $game->getDivision() ? $game->getDivision()->getName() : null,
        ];
    }

    #[Route('/games/{id}', name: 'app_game_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getGame(Game $game): JsonResponse
    {
        return $this->json($this->formatGame($game));
    }

    /**
     * GET /games — liste avec filtres optionnels
     *
     * ?division_id=X  filtre par division
     * ?team_id=X      filtre par équipe (team1 ou team2)
     */
    #[Route('/games', name: 'app_games', methods: ['GET'])]
    public function getGames(Request $request, GameRepository $gameRepository): JsonResponse
    {
        $divisionId = $request->query->get('division_id');
        $teamId = $request->query->get('team_id');

        if ($teamId) {
            $games = array_merge(
                $gameRepository->findBy(['team1' => $teamId]),
                $gameRepository->findBy(['team2' => $teamId])
            );
            if ($divisionId) {
                $games = array_filter($games, fn($g) => $g->getDivision()?->getId() == $divisionId);
                $games = array_values($games);
            }
        } elseif ($divisionId) {
            $games = $gameRepository->findBy(['division' => $divisionId]);
        } else {
            $games = $gameRepository->findAll();
        }

        return $this->json(array_map(fn($g) => $this->formatGame($g), $games));
    }
}
