<?php

namespace App\Controller;

use App\Exception\ApiProblemException;
use App\Repository\PlayerRepository;
use App\Repository\TeamStatRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Player;
use App\Entity\Team;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api')]
class PlayerController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Player) {
            throw new \InvalidArgumentException('Entity must be an instance of Player');
        }
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'discord' => $entity->getDiscord(),
            'team_id' => $entity->getTeam() ? $entity->getTeam()->getId() : null,
            'team_name' => $entity->getTeam() ? $entity->getTeam()->getName() : null
        ];
    }

    private function formatPlayerWithStats(Player $player, TeamStatRepository $teamStatRepository): array
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

        $playerData = $this->formatEntityData($player);
        return array_merge($playerData, ['stats' => $statData]);
    }

    private function resolveTeam(array $data): ?Team
    {
        if (!isset($data['team']) || !$data['team']) {
            return null;
        }

        $team = $this->entityManager->getRepository(Team::class)->find($data['team']);
        if (!$team) {
            throw ApiProblemException::notFound('Team not found');
        }
        return $team;
    }

    #[Route('/players/{id}', name: 'app_player_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getPlayer(int $id, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $player = $this->findEntityOrFail('App\Entity\Player', $id, 'Player');
        return $this->json($this->formatPlayerWithStats($player, $teamStatRepository));
    }

    #[Route('/players', name: 'app_players', methods: ['GET'])]
    public function getPlayers(Request $request, PlayerRepository $playerRepository): JsonResponse
    {
        $teamId = $request->query->get('team_id');

        if ($teamId) {
            $players = $playerRepository->findBy(['team' => $teamId]);
        } else {
            $players = $playerRepository->findAll();
        }

        $data = array_map(function ($player) {
            return $this->formatEntityData($player);
        }, $players);

        return $this->json($data);
    }

    #[Route('/players', name: 'app_player_create', methods: ['POST'])]
    public function createPlayer(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);
        $player = new Player();

        $player->setName($data['name'] ?? null);
        $player->setDiscord($data['discord'] ?? null);
        $player->setTeam($this->resolveTeam($data));

        return $this->securedCreateEntity($player, $request);
    }

    #[Route('/players/{id}', name: 'app_player_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updatePlayer(int $id, Request $request): JsonResponse
    {
        $player = $this->findEntityOrFail('App\Entity\Player', $id, 'Player');
        $data = $this->getRequestData($request);

        $player->setName($data['name']);
        $player->setDiscord($data['discord'] ?? null);

        if (isset($data['team'])) {
            $player->setTeam($this->resolveTeam($data));
        }

        return $this->securedUpdateEntity($player);
    }

    #[Route('/players/{id}', name: 'app_player_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patchPlayer(int $id, Request $request): JsonResponse
    {
        $player = $this->findEntityOrFail('App\Entity\Player', $id, 'Player');
        $data = $this->getRequestData($request);

        if (isset($data['name'])) {
            $player->setName($data['name']);
        }
        if (isset($data['discord'])) {
            $player->setDiscord($data['discord']);
        }
        if (isset($data['team'])) {
            $player->setTeam($this->resolveTeam($data));
        }

        return $this->securedUpdateEntity($player);
    }

    #[Route('/players/{id}', name: 'app_player_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deletePlayer(int $id): JsonResponse
    {
        $player = $this->findEntityOrFail('App\Entity\Player', $id, 'Player');
        return $this->securedDeleteEntity($player, 'Player');
    }
}
