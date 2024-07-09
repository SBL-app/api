<?php

namespace App\Controller;

use App\Repository\GameStatusRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\GameStatus;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;

class GameStatusController extends AbstractController
{
    #[Route('/game/status', name: 'app_game_status', methods: ['GET'])]
    public function getGameStatuses(GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $gameStatuses = $gameStatusRepository->findAll();
        $data = array_map(function ($gameStatus) {
            return [
                'id' => $gameStatus->getId(),
                'name' => $gameStatus->getName()
            ];
        }, $gameStatuses);
        return $this->json($data);
    }

    #[Route('/game/status/{id}', name: 'app_game_status_show', methods: ['GET'])]
    public function getGameStatus(GameStatus $gameStatus): JsonResponse
    {
        return $this->json([
            'id' => $gameStatus->getId(),
            'name' => $gameStatus->getName()
        ]);
    }

    #[Route('/game/status', name: 'app_game_status_create', methods: ['POST'])]
    public function createGameStatus(Request $request, GameStatus $gameStatus, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $gameStatus->setName($data['name']);
        $em->persist($gameStatus);
        $em->flush();
        return $this->json([
            'id' => $gameStatus->getId(),
            'name' => $gameStatus->getName()
        ]);
    }

    #[Route('/game/status/{id}', name: 'app_game_status_update', methods: ['PUT'])]
    public function updateGameStatus(Request $request, GameStatus $gameStatus, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $gameStatus->setName($data['name']);
        $em->persist($gameStatus);
        $em->flush();
        return $this->json([
            'id' => $gameStatus->getId(),
            'name' => $gameStatus->getName()
        ]);
    }

    #[Route('/game/status/{id}', name: 'app_game_status_patch', methods: ['PATCH'])]
    public function patchGameStatus(Request $request, GameStatus $gameStatus, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) {
            $gameStatus->setName($data['name']);
        }
        $em->persist($gameStatus);
        $em->flush();
        return $this->json([
            'id' => $gameStatus->getId(),
            'name' => $gameStatus->getName()
        ]);
    }

    #[Route('/game/status/{id}', name: 'app_game_status_delete', methods: ['DELETE'])]
    public function deleteGameStatus(GameStatus $gameStatus, EntityManager $em): JsonResponse
    {
        $em->remove($gameStatus);
        $em->flush();
        return $this->json(null, 204);
    }
}
