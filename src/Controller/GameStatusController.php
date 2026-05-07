<?php

namespace App\Controller;

use App\Repository\GameStatusRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\GameStatus;

class GameStatusController extends AbstractController
{
    #[Route('/game-statuses/{id}', name: 'app_game_status_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getGameStatus(GameStatus $gameStatus): JsonResponse
    {
        return $this->json(['id' => $gameStatus->getId(), 'name' => $gameStatus->getName()]);
    }

    #[Route('/game-statuses', name: 'app_game_statuses', methods: ['GET'])]
    public function getGameStatuses(GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $data = array_map(fn($s) => ['id' => $s->getId(), 'name' => $s->getName()], $gameStatusRepository->findAll());
        return $this->json($data);
    }
}
