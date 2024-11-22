<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Game;
use App\Repository\GameRepository;
use App\Repository\TeamRepository;
use App\Repository\GameStatusRepository;
use App\Repository\DivisionRepository;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class GameController extends AbstractController
{
    #[Route('/games', name: 'app_game', methods: ['GET'])]
    public function getGames(GameRepository $gameRepository): JsonResponse
    {
        $games = $gameRepository->findAll();
        $data = array_map(function ($game) {
            return [
                'id' => $game->getId(),
                'date' => $game->getDate()->format('Y-m-d H:i:s'),
                'week' => $game->getWeek(),
                'team1' => $game->getTeam1()->getName(),
                'team2' => $game->getTeam2()->getName(),
                'score1' => $game->getScore1(),
                'score2' => $game->getScore2(),
                'winner' => $game->getWinner(),
                'status' => $game->getStatus()->getName(),
                'division' => $game->getDivision()->getName()
            ];
        }, $games);
        return $this->json($data);
    }

    #[Route('/games/{divisionId}', name: 'app_game_division', methods: ['GET'])]
    public function getGamesByDivisionId(GameRepository $gameRepository, int $divisionId): JsonResponse
    {
        $games = $gameRepository->findBy(['division' => $divisionId]);
        $data = array_map(function ($game) {
            return [
                'id' => $game->getId(),
                'date' => $game->getDate()->format('Y-m-d H:i:s'),
                'week' => $game->getWeek(),
                'team1' => $game->getTeam1()->getName(),
                'team2' => $game->getTeam2()->getName(),
                'score1' => $game->getScore1(),
                'score2' => $game->getScore2(),
                'winner' => $game->getWinner(),
                'status' => $game->getStatus()->getName(),
                'division' => $game->getDivision()->getName()
            ];
        }, $games);
        return $this->json($data);
    }

    #[Route('/games/team/{teamId}', name: 'app_game_team', methods: ['GET'])]
    public function getGamesByTeamId(GameRepository $gameRepository, int $teamId): JsonResponse
    {
        $games = $gameRepository->findBy(['team1' => $teamId]);
        $games = array_merge($games, $gameRepository->findBy(['team2' => $teamId]));
        $data = array_map(function ($game) {
            return [
                'id' => $game->getId(),
                'date' => $game->getDate()->format('Y-m-d H:i:s'),
                'week' => $game->getWeek(),
                'team1' => $game->getTeam1()->getName(),
                'team2' => $game->getTeam2()->getName(),
                'score1' => $game->getScore1(),
                'score2' => $game->getScore2(),
                'winner' => $game->getWinner(),
                'status' => $game->getStatus()->getName(),
                'division' => $game->getDivision()->getName()
            ];
        }, $games);
        return $this->json($data);
    }

    #[Route('/game/{id}', name: 'app_game_show', methods: ['GET'])]
    public function getGame(Game $game): JsonResponse
    {
        return $this->json([
            'id' => $game->getId(),
            'date' => $game->getDate()->format('Y-m-d H:i:s'),
            'week' => $game->getWeek(),
            'team1' => $game->getTeam1()->getName(),
            'team2' => $game->getTeam2()->getName(),
            'score1' => $game->getScore1(),
            'score2' => $game->getScore2(),
            'winner' => $game->getWinner(),
            'status' => $game->getStatus()->getName(),
            'division' => $game->getDivision()->getName()
        ]);
    }

    #[Route('/game/division/{id}', name: 'app_game_show_division', methods: ['GET'])]
    public function getGamesByDivision(GameRepository $gameRepository, int $id): JsonResponse
    {
        $games = $gameRepository->findBy(['divisionId' => $id]);
        $data = array_map(function ($game) {
            return [
                'id' => $game->getId(),
                'date' => $game->getDate()->format('Y-m-d H:i:s'),
                'week' => $game->getWeek(),
                'team1' => $game->getTeam1()->getName(),
                'team2' => $game->getTeam2()->getName(),
                'score1' => $game->getScore1(),
                'score2' => $game->getScore2(),
                'winner' => $game->getWinner(),
                'status' => $game->getStatus()->getName(),
                'division' => $game->getDivision()->getName()
            ];
        }, $games);
        return $this->json($data);
    }

    #[Route('/game', name: 'app_game_create', methods: ['POST'])]
    public function createGame(Request $request, TeamRepository $teamRepository, GameStatusRepository $gameStatusRepository, DivisionRepository $divisionRepository, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $game = new Game();
        $game->setDate(new \DateTime($data['date']));
        $game->setWeek($data['week']);

        $team1 = $teamRepository->find($data['team1'] ?? null);
        if (!$team1) {
            return $this->json(['error' => 'Invalid team1 id'], 400);
        }
        $game->setTeam1($team1);

        $team2 = $teamRepository->find($data['team2'] ?? null);
        if (!$team2) {
            return $this->json(['error' => 'Invalid team2 id'], 400);
        }
        $game->setTeam2($team2);

        $status = $gameStatusRepository->find($data['status'] ?? null);
        if (!$status) {
            return $this->json(['error' => 'Invalid status id'], 400);
        }
        $game->setStatus($status);

        $division = $divisionRepository->find($data['division'] ?? null);
        if (!$division) {
            return $this->json(['error' => 'Invalid division id'], 400);
        }
        $game->setDivision($division);

        $game->setScore1($data['score1'] ?? null);
        $game->setScore2($data['score2'] ?? null);
        $game->setWinner($data['winner'] ?? null);

        $em->persist($game);
        $em->flush();

        return $this->json([
            'id' => $game->getId(),
            'date' => $game->getDate()->format('Y-m-d H:i:s'),
            'week' => $game->getWeek(),
            'team1' => $game->getTeam1()->getName(),
            'team2' => $game->getTeam2()->getName(),
            'score1' => $game->getScore1(),
            'score2' => $game->getScore2(),
            'winner' => $game->getWinner(),
            'status' => $game->getStatus()->getName(),
            'division' => $game->getDivision()->getName()
        ]);
    }

    #[Route('/game/{id}', name: 'app_game_update', methods: ['PUT'])]
    public function updateGame(Request $request, Game $game, EntityManager $em, TeamRepository $teamRepository, GameStatusRepository $gameStatusRepository, DivisionRepository $divisionRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $game->setDate(new \DateTime($data['date']));
        $game->setWeek($data['week']);

        $team1 = $teamRepository->find($data['team1']);
        if (!$team1) {
            return $this->json(['error' => 'Invalid team1 id'], 400);
        }
        $game->setTeam1($team1);

        $team2 = $teamRepository->find($data['team2']);
        if (!$team2) {
            return $this->json(['error' => 'Invalid team2 id'], 400);
        }
        $game->setTeam2($team2);

        $status = $gameStatusRepository->find($data['status']);
        if (!$status) {
            return $this->json(['error' => 'Invalid status id'], 400);
        }
        $game->setStatus($status);

        $division = $divisionRepository->find($data['division']);
        if (!$division) {
            return $this->json(['error' => 'Invalid division id'], 400);
        }
        $game->setDivision($division);

        $game->setScore1($data['score1']);
        $game->setScore2($data['score2']);
        $game->setWinner($data['winner']);

        $em->flush();

        return $this->json([
            'id' => $game->getId(),
            'date' => $game->getDate()->format('Y-m-d H:i:s'),
            'week' => $game->getWeek(),
            'team1' => $game->getTeam1()->getName(),
            'team2' => $game->getTeam2()->getName(),
            'score1' => $game->getScore1(),
            'score2' => $game->getScore2(),
            'winner' => $game->getWinner(),
            'status' => $game->getStatus()->getName(),
            'division' => $game->getDivision()->getName()
        ]);
    }

    #[Route('/game/{id}', name: 'app_game_patch', methods: ['PATCH'])]
    public function patchGame(Request $request, Game $game, EntityManager $em, TeamRepository $teamRepository, GameStatusRepository $gameStatusRepository, DivisionRepository $divisionRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['date'])) {
            $game->setDate(new \DateTime($data['date']));
        }
        if (isset($data['week'])) {
            $game->setWeek($data['week']);
        }
        if (isset($data['team1'])) {
            $team1 = $teamRepository->find($data['team1']);
            if (!$team1) {
                return $this->json(['error' => 'Invalid team1 id'], 400);
            }
            $game->setTeam1($team1);
        }
        if (isset($data['team2'])) {
            $team2 = $teamRepository->find($data['team2']);
            if (!$team2) {
                return $this->json(['error' => 'Invalid team2 id'], 400);
            }
            $game->setTeam2($team2);
        }
        if (isset($data['score1'])) {
            $game->setScore1($data['score1']);
        }
        if (isset($data['score2'])) {
            $game->setScore2($data['score2']);
        }
        if (isset($data['winner'])) {
            $game->setWinner($data['winner']);
        }
        if (isset($data['status'])) {
            $status = $gameStatusRepository->find($data['status']);
            if (!$status) {
                return $this->json(['error' => 'Invalid status id'], 400);
            }
            $game->setStatus($status);
        }
        if (isset($data['division'])) {
            $division = $divisionRepository->find($data['division']);
            if (!$division) {
                return $this->json(['error' => 'Invalid division id'], 400);
            }
            $game->setDivision($division);
        }
        $em->flush();
        return $this->json([
            'id' => $game->getId(),
            'date' => $game->getDate()->format('Y-m-d H:i:s'),
            'week' => $game->getWeek(),
            'team1' => $game->getTeam1()->getName(),
            'team2' => $game->getTeam2()->getName(),
            'score1' => $game->getScore1(),
            'score2' => $game->getScore2(),
            'winner' => $game->getWinner(),
            'status' => $game->getStatus()->getName(),
            'division' => $game->getDivision()->getName()
        ]);
    }

    #[Route('/game/{id}', name: 'app_game_delete', methods: ['DELETE'])]
    public function deleteGame(Game $game, EntityManager $em): JsonResponse
    {
        $em->remove($game);
        $em->flush();
        return $this->json([
            'message' => 'Game deleted successfully'
        ]);
    }
}
