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
    /**
     * Formate les données de base d'un match
     */
    private function formatGameData(Game $game): array
    {
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
    }

    /**
     * Récupère les matchs d'une équipe avec filtrage optionnel par division
     */
    private function getGamesByTeam(GameRepository $gameRepository, int $teamId, ?int $divisionId = null): array
    {
        $criteria1 = ['team1' => $teamId];
        $criteria2 = ['team2' => $teamId];
        
        if ($divisionId) {
            $criteria1['division'] = $divisionId;
            $criteria2['division'] = $divisionId;
        }
        
        $games1 = $gameRepository->findBy($criteria1);
        $games2 = $gameRepository->findBy($criteria2);
        
        return array_merge($games1, $games2);
    }

    #[Route('/games', name: 'app_game', methods: ['GET'])]
    public function getGames(Request $request, GameRepository $gameRepository): JsonResponse
    {
        $id = $request->query->get('id');
        $divisionId = $request->query->get('division_id');
        $teamId = $request->query->get('team_id');
        
        // Si un ID est fourni, retourner le match spécifique
        if ($id) {
            $game = $gameRepository->find($id);
            if (!$game) {
                return $this->json(['error' => 'Game not found'], 404);
            }
            return $this->json($this->formatGameData($game));
        }
        
        // Si team_id est fourni (avec ou sans division_id)
        if ($teamId) {
            $games = $this->getGamesByTeam($gameRepository, (int)$teamId, $divisionId ? (int)$divisionId : null);
            
            if (empty($games)) {
                $errorMessage = $divisionId 
                    ? 'No games found for this team in this division'
                    : 'No games found for this team';
                return $this->json(['error' => $errorMessage], 404);
            }
            
            $data = array_map(function ($game) {
                return $this->formatGameData($game);
            }, $games);
            return $this->json($data);
        }
        
        // Si seulement division_id est fourni, retourner les matchs de cette division
        if ($divisionId) {
            $games = $gameRepository->findBy(['division' => $divisionId]);
            if (empty($games)) {
                return $this->json(['error' => 'No games found for this division'], 404);
            }
            $data = array_map(function ($game) {
                return $this->formatGameData($game);
            }, $games);
            return $this->json($data);
        }
        
        // Sinon, retourner tous les matchs
        $games = $gameRepository->findAll();
        $data = array_map(function ($game) {
            return $this->formatGameData($game);
        }, $games);
        return $this->json($data);
    }

    // #[Route('/game', name: 'app_game_create', methods: ['POST'])]
    // public function createGame(Request $request, TeamRepository $teamRepository, GameStatusRepository $gameStatusRepository, DivisionRepository $divisionRepository, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $game = new Game();
    //     $game->setDate(isset($data['date']) ? new \DateTime($data['date']) : null);
    //     $game->setWeek($data['week'] ?? null);

    //     $team1 = isset($data['team1']) ? $teamRepository->find($data['team1']) : null;
    //     if ($data['team1'] && !$team1) {
    //         return $this->json(['error' => 'Invalid team1 id'], 400);
    //     }
    //     $game->setTeam1($team1);

    //     $team2 = isset($data['team2']) ? $teamRepository->find($data['team2']) : null;
    //     if ($data['team2'] && !$team2) {
    //         return $this->json(['error' => 'Invalid team2 id'], 400);
    //     }
    //     $game->setTeam2($team2);

    //     $status = isset($data['status']) ? $gameStatusRepository->find($data['status']) : null;
    //     if ($data['status'] && !$status) {
    //         return $this->json(['error' => 'Invalid status id'], 400);
    //     }
    //     $game->setStatus($status);

    //     $division = isset($data['division']) ? $divisionRepository->find($data['division']) : null;
    //     if ($data['division'] && !$division) {
    //         return $this->json(['error' => 'Invalid division id'], 400);
    //     }
    //     $game->setDivision($division);

    //     $game->setScore1($data['score1'] ?? null);
    //     $game->setScore2($data['score2'] ?? null);
    //     $game->setWinner($data['winner'] ?? null);

    //     $em->persist($game);
    //     $em->flush();

    //     return $this->json([
    //         'id' => $game->getId(),
    //         'date' => $game->getDate() ? $game->getDate()->format('Y-m-d H:i:s') : null,
    //         'week' => $game->getWeek(),
    //         'team1' => $game->getTeam1() ? $game->getTeam1()->getName() : null,
    //         'team2' => $game->getTeam2() ? $game->getTeam2()->getName() : null,
    //         'score1' => $game->getScore1(),
    //         'score2' => $game->getScore2(),
    //         'winner' => $game->getWinner(),
    //         'status' => $game->getStatus() ? $game->getStatus()->getName() : null,
    //         'division' => $game->getDivision() ? $game->getDivision()->getName() : null
    //     ]);
    // }

    // #[Route('/game/{id}', name: 'app_game_update', methods: ['PUT'])]
    // public function updateGame(Request $request, Game $game, EntityManager $em, TeamRepository $teamRepository, GameStatusRepository $gameStatusRepository, DivisionRepository $divisionRepository): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $game->setDate(new \DateTime($data['date']));
    //     $game->setWeek($data['week']);

    //     $team1 = $teamRepository->find($data['team1']);
    //     if (!$team1) {
    //         return $this->json(['error' => 'Invalid team1 id'], 400);
    //     }
    //     $game->setTeam1($team1);

    //     $team2 = $teamRepository->find($data['team2']);
    //     if (!$team2) {
    //         return $this->json(['error' => 'Invalid team2 id'], 400);
    //     }
    //     $game->setTeam2($team2);

    //     $status = $gameStatusRepository->find($data['status']);
    //     if (!$status) {
    //         return $this->json(['error' => 'Invalid status id'], 400);
    //     }
    //     $game->setStatus($status);

    //     $division = $divisionRepository->find($data['division']);
    //     if (!$division) {
    //         return $this->json(['error' => 'Invalid division id'], 400);
    //     }
    //     $game->setDivision($division);

    //     $game->setScore1($data['score1']);
    //     $game->setScore2($data['score2']);
    //     $game->setWinner($data['winner']);

    //     $em->flush();

    //     return $this->json([
    //         'id' => $game->getId(),
    //         'date' => $game->getDate()->format('Y-m-d H:i:s'),
    //         'week' => $game->getWeek(),
    //         'team1' => $game->getTeam1()->getName(),
    //         'team2' => $game->getTeam2()->getName(),
    //         'score1' => $game->getScore1(),
    //         'score2' => $game->getScore2(),
    //         'winner' => $game->getWinner(),
    //         'status' => $game->getStatus()->getName(),
    //         'division' => $game->getDivision()->getName()
    //     ]);
    // }

    // #[Route('/game/{id}', name: 'app_game_patch', methods: ['PATCH'])]
    // public function patchGame(Request $request, Game $game, EntityManager $em, TeamRepository $teamRepository, GameStatusRepository $gameStatusRepository, DivisionRepository $divisionRepository): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     if (isset($data['date'])) {
    //         $game->setDate(new \DateTime($data['date']));
    //     }
    //     if (isset($data['week'])) {
    //         $game->setWeek($data['week']);
    //     }
    //     if (isset($data['team1'])) {
    //         $team1 = $teamRepository->find($data['team1']);
    //         if (!$team1) {
    //             return $this->json(['error' => 'Invalid team1 id'], 400);
    //         }
    //         $game->setTeam1($team1);
    //     }
    //     if (isset($data['team2'])) {
    //         $team2 = $teamRepository->find($data['team2']);
    //         if (!$team2) {
    //             return $this->json(['error' => 'Invalid team2 id'], 400);
    //         }
    //         $game->setTeam2($team2);
    //     }
    //     if (isset($data['score1'])) {
    //         $game->setScore1($data['score1']);
    //     }
    //     if (isset($data['score2'])) {
    //         $game->setScore2($data['score2']);
    //     }
    //     if (isset($data['winner'])) {
    //         $game->setWinner($data['winner']);
    //     }
    //     if (isset($data['status'])) {
    //         $status = $gameStatusRepository->find($data['status']);
    //         if (!$status) {
    //             return $this->json(['error' => 'Invalid status id'], 400);
    //         }
    //         $game->setStatus($status);
    //     }
    //     if (isset($data['division'])) {
    //         $division = $divisionRepository->find($data['division']);
    //         if (!$division) {
    //             return $this->json(['error' => 'Invalid division id'], 400);
    //         }
    //         $game->setDivision($division);
    //     }
    //     $em->flush();
    //     return $this->json([
    //         'id' => $game->getId(),
    //         'date' => $game->getDate()->format('Y-m-d H:i:s'),
    //         'week' => $game->getWeek(),
    //         'team1' => $game->getTeam1()->getName(),
    //         'team2' => $game->getTeam2()->getName(),
    //         'score1' => $game->getScore1(),
    //         'score2' => $game->getScore2(),
    //         'winner' => $game->getWinner(),
    //         'status' => $game->getStatus()->getName(),
    //         'division' => $game->getDivision()->getName()
    //     ]);
    // }

    // #[Route('/game/{id}', name: 'app_game_delete', methods: ['DELETE'])]
    // public function deleteGame(Game $game, EntityManager $em): JsonResponse
    // {
    //     $em->remove($game);
    //     $em->flush();
    //     return $this->json([
    //         'message' => 'Game deleted successfully'
    //     ]);
    // }
}
