<?php

namespace App\Controller;

use App\Repository\GameStatusRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\GameStatus;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class GameStatusController extends AbstractController
{
    /**
     * Formate les données de base d'un statut de match
     */
    private function formatGameStatusData(GameStatus $gameStatus): array
    {
        return [
            'id' => $gameStatus->getId(),
            'name' => $gameStatus->getName()
        ];
    }

    #[Route('/gameStatus', name: 'app_game_status', methods: ['GET'])]
    public function getGameStatuses(Request $request, GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $id = $request->query->get('id');
        
        // Si un ID est fourni, retourner le statut spécifique
        if ($id) {
            $gameStatus = $gameStatusRepository->find($id);
            if (!$gameStatus) {
                return $this->json(['error' => 'Game status not found'], 404);
            }
            return $this->json($this->formatGameStatusData($gameStatus));
        }
        
        // Sinon, retourner tous les statuts
        $gameStatuses = $gameStatusRepository->findAll();
        $data = array_map(function ($gameStatus) {
            return $this->formatGameStatusData($gameStatus);
        }, $gameStatuses);
        return $this->json($data);
    }

    // #[Route('/gameStatus', name: 'app_game_status_create', methods: ['POST'])]
    // public function createGameStatus(Request $request, GameStatus $gameStatus, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $gameStatus->setName($data['name']);
    //     $em->persist($gameStatus);
    //     $em->flush();
    //     return $this->json([
    //         'id' => $gameStatus->getId(),
    //         'name' => $gameStatus->getName()
    //     ]);
    // }

    // #[Route('/gameStatus/{id}', name: 'app_game_status_update', methods: ['PUT'])]
    // public function updateGameStatus(Request $request, GameStatus $gameStatus, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $gameStatus->setName($data['name']);
    //     $em->persist($gameStatus);
    //     $em->flush();
    //     return $this->json([
    //         'id' => $gameStatus->getId(),
    //         'name' => $gameStatus->getName()
    //     ]);
    // }

    // #[Route('/gameStatus/{id}', name: 'app_game_status_patch', methods: ['PATCH'])]
    // public function patchGameStatus(Request $request, GameStatus $gameStatus, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     if (isset($data['name'])) {
    //         $gameStatus->setName($data['name']);
    //     }
    //     $em->persist($gameStatus);
    //     $em->flush();
    //     return $this->json([
    //         'id' => $gameStatus->getId(),
    //         'name' => $gameStatus->getName()
    //     ]);
    // }

    // #[Route('/gameStatus/{id}', name: 'app_game_status_delete', methods: ['DELETE'])]
    // public function deleteGameStatus(GameStatus $gameStatus, EntityManager $em): JsonResponse
    // {
    //     $em->remove($gameStatus);
    //     $em->flush();
    //     return $this->json([
    //         'message' => 'GameStatus deleted successfully'
    //     ]);
    // }
}
