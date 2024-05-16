<?php

namespace App\Controller;

use App\Repository\TeamRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Team;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;

class TeamController extends AbstractController
{
    #[Route('/team', name: 'app_team', methods: ['GET'])]
    public function getTeams(TeamRepository $teamRepository): JsonResponse
    {
        $teams = $teamRepository->findAll();
        $data = array_map(function ($team) {
            return [
                'id' => $team->getId(),
                'name' => $team->getName(),
                'capitain' => $team->getCapitain()->getName() ?? 'No capitain assigned'
            ];
        }, $teams);
        return $this->json($data);
    }

    #[Route('/team/{id}', name: 'app_team_show', methods: ['GET'])]
    public function getTeam(Team $team): JsonResponse
    {
        return $this->json([
            'id' => $team->getId(),
            'name' => $team->getName(),
            'capitain' => $team->getCapitain()->getName() ?? 'No capitain assigned'
        ]);
    }
    
    #[Route('/team', name: 'app_team_create', methods: ['POST'])]
    public function createTeam(Request $request, Team $team, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $team->setName($data['name']);
        $team->setCapitain($data['capitain']);
        $em->persist($team);
        $em->flush();
        return $this->json([
            'id' => $team->getId(),
            'name' => $team->getName(),
            'capitain' => $team->getCapitain()->getName() ?? 'No capitain assigned'
        ]);
    }

    #[Route('/team/{id}', name: 'app_team_update', methods: ['PUT'])]
    public function updateTeam(Request $request, Team $team, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $team->setName($data['name']);
        $team->setCapitain($data['capitain']);
        $em->persist($team);
        $em->flush();
        return $this->json([
            'id' => $team->getId(),
            'name' => $team->getName(),
            'capitain' => $team->getCapitain()->getName() ?? 'No capitain assigned'
        ]);
    }

    #[Route('/team/{id}', name: 'app_team_patch', methods: ['PATCH'])]
    public function patchTeam(Request $request, Team $team, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) {
            $team->setName($data['name']);
        }
        if (isset($data['capitain'])) {
            $team->setCapitain($data['capitain']);
        }
        $em->persist($team);
        $em->flush();
        return $this->json([
            'id' => $team->getId(),
            'name' => $team->getName(),
            'capitain' => $team->getCapitain()->getName() ?? 'No capitain assigned'
        ]);
    }

    #[Route('/team/{id}', name: 'app_team_delete', methods: ['DELETE'])]
    public function deleteTeam(Team $team, EntityManager $em): JsonResponse
    {
        $em->remove($team);
        $em->flush();
        return $this->json(null, 204);
    }
}
