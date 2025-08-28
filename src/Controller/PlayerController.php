<?php

namespace App\Controller;

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

    #[Route('/players', name: 'app_players', methods: ['GET'])]
    public function getPlayers(Request $request, PlayerRepository $playerRepository, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $id = $request->query->get('id');
        $teamFilter = $request->query->get('team');

        // Si un ID est fourni, retourner un seul joueur avec ses statistiques
        if ($id) {
            $player = $playerRepository->find($id);
            if (!$player) {
                return $this->notFoundError('Player');
            }

            return $this->json($this->formatPlayerWithStats($player, $teamStatRepository));
        }

        // Si un filtre d'équipe est fourni, filtrer par équipe
        if ($teamFilter) {
            $players = $playerRepository->findBy(['team' => $teamFilter]);
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
        try {
            $data = $this->getRequestData($request);
            $player = new Player();

            $player->setName($data['name'] ?? null);
            $player->setDiscord($data['discord'] ?? null);

            if (isset($data['team']) && $data['team']) {
                $team = $this->entityManager->getRepository(Team::class)->find($data['team']);
                if (!$team) {
                    return $this->json(['error' => 'Team not found'], 404);
                }
                $player->setTeam($team);
            } else {
                $player->setTeam(null);
            }

            $this->saveEntity($player);

            return $this->json($this->formatEntityData($player));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/players', name: 'app_player_update', methods: ['PUT'])]
    public function updatePlayer(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $player = $this->findEntityOrFail('App\Entity\Player', $id, 'Player');
            $data = $this->getRequestData($request);

            $player->setName($data['name']);
            $player->setDiscord($data['discord'] ?? null);

            if (isset($data['team'])) {
                if ($data['team']) {
                    $team = $this->entityManager->getRepository(Team::class)->find($data['team']);
                    if (!$team) {
                        return $this->json(['error' => 'Team not found'], 404);
                    }
                    $player->setTeam($team);
                } else {
                    $player->setTeam(null);
                }
            }

            $this->saveEntity($player);

            return $this->json($this->formatEntityData($player));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/players', name: 'app_player_patch', methods: ['PATCH'])]
    public function patchPlayer(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $player = $this->findEntityOrFail('App\Entity\Player', $id, 'Player');
            $data = $this->getRequestData($request);
            if (isset($data['name'])) {
                $player->setName($data['name']);
            }
            if (isset($data['discord'])) {
                $player->setDiscord($data['discord']);
            }
            if (isset($data['team'])) {
                if ($data['team']) {
                    $team = $this->entityManager->getRepository(Team::class)->find($data['team']);
                    if (!$team) {
                        return $this->json(['error' => 'Team not found'], 404);
                    }
                    $player->setTeam($team);
                } else {
                    $player->setTeam(null);
                }
            }

            $this->saveEntity($player);

            return $this->json($this->formatEntityData($player));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/players', name: 'app_player_delete', methods: ['DELETE'])]
    public function deletePlayer(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $player = $this->findEntityOrFail('App\Entity\Player', $id, 'Player');
            $this->deleteEntity($player);

            return $this->deleteSuccessResponse('Player');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
