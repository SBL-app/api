<?php

namespace App\Controller;

use App\Repository\PlayerRepository;
use App\Repository\TeamStatRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Player;

class PlayerController extends AbstractController
{
    private function formatPlayer(Player $player): array
    {
        return [
            'id' => $player->getId(),
            'name' => $player->getName(),
            'discord' => $player->getDiscord(),
            'team_id' => $player->getTeam() ? $player->getTeam()->getId() : null,
            'team_name' => $player->getTeam() ? $player->getTeam()->getName() : null,
        ];
    }

    #[Route('/players/{id}', name: 'app_player_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getPlayer(Player $player, TeamStatRepository $teamStatRepository): JsonResponse
    {
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

        return $this->json(array_merge($this->formatPlayer($player), ['stats' => $statData]));
    }

    /**
     * GET /players — liste avec filtre optionnel
     *
     * ?team_id=X  filtre par équipe
     */
    #[Route('/players', name: 'app_players', methods: ['GET'])]
    public function getPlayers(Request $request, PlayerRepository $playerRepository): JsonResponse
    {
        $teamId = $request->query->get('team_id');
        $players = $teamId
            ? $playerRepository->findBy(['team' => $teamId])
            : $playerRepository->findAll();

        return $this->json(array_map(fn($p) => $this->formatPlayer($p), $players));
    }
}
