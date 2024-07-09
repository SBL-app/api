<?php

namespace App\Controller;

use App\Repository\PlayerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class PlayerController extends AbstractController
{
    #[Route('/players', name: 'app_player', methods: ['GET'])]
    public function getPlayers(PlayerRepository $playerRepository): JsonResponse
    {
        $players = $playerRepository->findAll();
        $data = array_map(function ($player) {
            return [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'discord' => $player->getDiscord(),
                'team' => $player->getTeam()
            ];
        }, $players);
        return $this->json($data);
    }

    #[Route('/player/{id}', name: 'app_player_show', methods: ['GET'])]
    public function getPlayer(Player $player): JsonResponse
    {
        return $this->json([
            'id' => $player->getId(),
            'name' => $player->getName(),
            'discord' => $player->getDiscord(),
            'team' => $player->getTeam()
        ]);
    }

    #[Route('/player', name: 'app_player_create', methods: ['POST'])]
    public function createPlayer(Request $request, Player $player, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $player->setName($data['name'] ?? null);
        $player->setDiscord($data['discord'] ?? null);
        $player->setTeam($data['team'] ?? null);
        $em->persist($player);
        $em->flush();
        return $this->json([
            'id' => $player->getId(),
            'name' => $player->getName(),
            'discord' => $player->getDiscord(),
            'team' => $player->getTeam()
        ]);
    }

    #[Route('/player/{id}', name: 'app_player_update', methods: ['PUT'])]
    public function updatePlayer(Request $request, Player $player, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $player->setName($data['name']);
        $player->setDiscord($data['discord' ?? null]);
        $player->setTeam($data['team' ?? null]);
        $em->persist($player);
        $em->flush();
        return $this->json([
            'id' => $player->getId(),
            'name' => $player->getName(),
            'discord' => $player->getDiscord(),
            'team' => $player->getTeam()
        ]);
    }

    #[Route('/player/{id}', name: 'app_player_patch', methods: ['PATCH'])]
    public function patchPlayer(Request $request, Player $player, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) {
            $player->setName($data['name']);
        }
        if (isset($data['discord'])) {
            $player->setDiscord($data['discord']);
        }
        if (isset($data['team'])) {
            $player->setTeam($data['team']);
        }
        $em->persist($player);
        $em->flush();
        return $this->json([
            'id' => $player->getId(),
            'name' => $player->getName(),
            'discord' => $player->getDiscord(),
            'team' => $player->getTeam()
        ]);
    }

    #[Route('/player/{id}', name: 'app_player_delete', methods: ['DELETE'])]
    public function deletePlayer(Player $player, EntityManager $em): JsonResponse
    {
        $em->remove($player);
        $em->flush();
        return $this->json([
            'message' => 'Player deleted successfully'
        ]);
    }
}
