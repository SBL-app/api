<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Game;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;

class GameController extends AbstractController
{
    #[Route('/game', name: 'app_game', methods: ['GET'])]
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
                'division' => $game->getDivisionId()->getName()
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

    #[Route('/game/division/{id}', name:'app_game_show_division', methods: ['GET'])]
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
                'division' => $game->getDivisionId()->getName()
            ];
        }, $games);
        return $this->json($data);
    }

    #[Route('/game', name: 'app_game_create', methods: ['POST'])]
    public function createGame(Request $request, Game $game, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $game->setDate(new \DateTime($data['date']));
        $game->setWeek($data['week']);
        $game->setTeam1($data['team1']);
        $game->setTeam2($data['team2']);
        $game->setScore1($data['score1']);
        $game->setScore2($data['score2']);
        $game->setWinner($data['winner']);
        $game->setStatus($data['status']);
        $game->setDivision($data['division']);
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
    public function updateGame(Request $request, Game $game, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $game->setDate(new \DateTime($data['date']));
        $game->setWeek($data['week']);
        $game->setTeam1($data['team1']);
        $game->setTeam2($data['team2']);
        $game->setScore1($data['score1']);
        $game->setScore2($data['score2']);
        $game->setWinner($data['winner']);
        $game->setStatus($data['status']);
        $game->setDivision($data['division']);
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

    #[Route('/game/{id}', name: 'app_game_update', methods: ['PATCH'])]
    public function patchGame(Request $request, Game $game, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['date'])) {
            $game->setDate(new \DateTime($data['date']));
        }
        if (isset($data['week'])) {
            $game->setWeek($data['week']);
        }
        if (isset($data['team1'])) {
            $game->setTeam1($data['team1']);
        }
        if (isset($data['team2'])) {
            $game->setTeam2($data['team2']);
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
            $game->setStatus($data['status']);
        }
        if (isset($data['division'])) {
            $game->setDivision($data['division']);
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
        return $this->json(null, 204);
    }
}
