<?php

namespace App\Controller;

use App\Repository\PlayerRepository;
use App\Repository\TeamStatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Player;
use App\Entity\Team;
use App\Entity\TeamStat;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class PlayerController extends AbstractController
{
    /**
     * Formate les données de base d'un joueur
     */
    private function formatPlayerData(Player $player): array
    {
        return [
            'id' => $player->getId(),
            'name' => $player->getName(),
            'discord' => $player->getDiscord(),
            'team_id' => $player->getTeam() ? $player->getTeam()->getId() : null,
            'team_name' => $player->getTeam() ? $player->getTeam()->getName() : null
        ];
    }

    #[Route('/players', name: 'app_players', methods: ['GET'])]
    public function getPlayers(Request $request, PlayerRepository $playerRepository, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $id = $request->query->get('id');
        $teamFilter = $request->query->get('team');
        
        // Si un ID est fourni, retourner un seul joueur avec ses statistiques
        if ($id) {
            $player = $playerRepository->find($id);
            if (!$player) {
                return $this->json(['error' => 'Player not found'], 404);
            }

            $team = $player->getTeam();
            $teamStats = $team ? $teamStatRepository->findBy(['team' => $team]) : [];
            $statData = array_map(function ($teamStat) use ($team) {
                return [
                    'team_id' => $team->getId(),
                    'team_name' => $team->getName(),
                    'division_id' => $teamStat->getDivision()->getId(),
                    'division_name' => $teamStat->getDivision()->getName(),
                    'season_id' => $teamStat->getDivision()->getSeason()->getId(),
                    'season_name' => $teamStat->getDivision()->getSeason()->getName(),
                    'wins' => $teamStat->getWins(),
                    'losses' => $teamStat->getLosses(),
                    'winRounds' => $teamStat->getWinRounds(),
                    'looseRounds' => $teamStat->getLooseRounds(),
                    'points' => $teamStat->getPoints(),
                ];
            }, $teamStats);

            $playerData = $this->formatPlayerData($player);
            return $this->json(array_merge($playerData, [
                'stats' => $statData
            ]));
        }

        // Si un filtre d'équipe est fourni, filtrer par équipe
        if ($teamFilter) {
            $players = $playerRepository->findBy(['team' => $teamFilter]);
        } else {
            // Sinon, retourner tous les joueurs
            $players = $playerRepository->findAll();
        }

        $data = array_map(function ($player) {
            return $this->formatPlayerData($player);
        }, $players);
        
        return $this->json($data);
    }

    // #[Route('/player', name: 'app_player_create', methods: ['POST'])]
    // public function createPlayer(Request $request, Player $player, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $player->setName($data['name'] ?? null);
    //     $player->setDiscord($data['discord'] ?? null);
    //     $team = $em->getRepository(Team::class)->find($data['team'] ?? null);
    //     if (!$team && isset($data['team'])) {
    //         return $this->json(['error' => 'Team not found'], Response::HTTP_NOT_FOUND);
    //     }
    //     $player->setTeam($team);
    //     $em->persist($player);
    //     $em->flush();
    //     return $this->json([
    //         'id' => $player->getId(),
    //         'name' => $player->getName(),
    //         'discord' => $player->getDiscord(),
    //         'team' => $player->getTeam() ? $player->getTeam()->getId() : null
    //     ]);
    // }

    // #[Route('/player/{id}', name: 'app_player_update', methods: ['PUT'])]
    // public function updatePlayer(Request $request, Player $player, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $player->setName($data['name']);
    //     $player->setDiscord($data['discord' ?? null]);
    //     $player->setTeam($data['team' ?? null]);
    //     $em->persist($player);
    //     $em->flush();
    //     return $this->json([
    //         'id' => $player->getId(),
    //         'name' => $player->getName(),
    //         'discord' => $player->getDiscord(),
    //         'team' => $player->getTeam() ? $player->getTeam()->getId() : null
    //     ]);
    // }

    // #[Route('/player/{id}', name: 'app_player_patch', methods: ['PATCH'])]
    // public function patchPlayer(Request $request, Player $player, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     if (isset($data['name'])) {
    //         $player->setName($data['name']);
    //     }
    //     if (isset($data['discord'])) {
    //         $player->setDiscord($data['discord']);
    //     }
    //     if (isset($data['team'])) {
    //         $player->setTeam($data['team']);
    //     }
    //     $em->persist($player);
    //     $em->flush();
    //     return $this->json([
    //         'id' => $player->getId(),
    //         'name' => $player->getName(),
    //         'discord' => $player->getDiscord(),
    //         'team' => $player->getTeam() ? $player->getTeam()->getId() : null
    //     ]);
    // }

    // #[Route('/player/{id}', name: 'app_player_delete', methods: ['DELETE'])]
    // public function deletePlayer(Player $player, EntityManager $em): JsonResponse
    // {
    //     $em->remove($player);
    //     $em->flush();
    //     return $this->json([
    //         'message' => 'Player deleted successfully'
    //     ]);
    // }
}
