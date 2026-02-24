<?php

namespace App\Controller;

use App\Repository\GameStatusRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\GameStatus;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api')]
class GameStatusController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof GameStatus) {
            throw new \InvalidArgumentException('Entity must be an instance of GameStatus');
        }
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName()
        ];
    }

    #[Route('/gameStatus/{id}', name: 'app_game_status_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getGameStatus(int $id): JsonResponse
    {
        return $this->getEntityById('App\Entity\GameStatus', $id, 'Game status');
    }

    #[Route('/gameStatus', name: 'app_game_status', methods: ['GET'])]
    public function getGameStatuses(Request $request, GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $id = $request->query->get('id');

        // Backward compatibility - deprecated
        if ($id) {
            $this->logger->warning('Deprecated: Using ?id parameter for game status. Use /gameStatus/{id} instead', ['id' => $id]);
            return $this->getEntityById('App\Entity\GameStatus', $id, 'Game status');
        }

        // Sinon, retourner tous les statuts
        $gameStatuses = $gameStatusRepository->findAll();
        $data = array_map(function ($gameStatus) {
            return $this->formatEntityData($gameStatus);
        }, $gameStatuses);
        return $this->json($data);
    }

    #[Route('/gameStatus', name: 'app_game_status_create', methods: ['POST'])]
    public function createGameStatus(Request $request): JsonResponse
    {
        try {
            $data = $this->getRequestData($request);
            $gameStatus = new GameStatus();

            $gameStatus->setName($data['name']);

            return $this->securedCreateEntity($gameStatus);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/gameStatus/{id}', name: 'app_game_status_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateGameStatus(int $id, Request $request): JsonResponse
    {
        try {
            $gameStatus = $this->findEntityOrFail('App\Entity\GameStatus', $id, 'GameStatus');
            $data = $this->getRequestData($request);

            $gameStatus->setName($data['name']);

            return $this->securedUpdateEntity($gameStatus);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/gameStatus/{id}', name: 'app_game_status_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patchGameStatus(int $id, Request $request): JsonResponse
    {
        try {
            $gameStatus = $this->findEntityOrFail('App\Entity\GameStatus', $id, 'GameStatus');
            $data = $this->getRequestData($request);

            if (isset($data['name'])) {
                $gameStatus->setName($data['name']);
            }

            return $this->securedUpdateEntity($gameStatus);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/gameStatus/{id}', name: 'app_game_status_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteGameStatus(int $id): JsonResponse
    {
        try {
            $gameStatus = $this->findEntityOrFail('App\Entity\GameStatus', $id, 'GameStatus');

            return $this->securedDeleteEntity($gameStatus, 'GameStatus');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
