<?php

namespace App\Controller;

use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Team;

class TeamController extends AbstractController
{
    private function formatTeam(Team $team): array
    {
        return [
            'id' => $team->getId(),
            'name' => $team->getName(),
            'captain' => $team->getCapitain() ? $team->getCapitain()->getName() : null,
        ];
    }

    #[Route('/teams/{id}', name: 'app_team_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getTeam(Team $team): JsonResponse
    {
        return $this->json($this->formatTeam($team));
    }

    #[Route('/teams', name: 'app_teams', methods: ['GET'])]
    public function getTeams(TeamRepository $teamRepository): JsonResponse
    {
        $teams = $teamRepository->findAll();
        return $this->json(array_map(fn($t) => $this->formatTeam($t), $teams));
    }
}
