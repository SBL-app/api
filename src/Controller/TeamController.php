<?php

namespace App\Controller;

use App\Repository\TeamRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class TeamController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Team) {
            throw new \InvalidArgumentException('Entity must be an instance of Team');
        }
        
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName()
        ];
    }

    #[Route('/teams', name: 'app_teams', methods: ['GET'])]
    public function getTeams(Request $request, TeamRepository $teamRepository): JsonResponse
    {
        $id = $request->query->get('id');
        
        // Si un ID est fourni, retourner l'équipe spécifique
        if ($id) {
            return $this->getEntityById('App\Entity\Team', $id, 'Team');
        }
        
        // Sinon, retourner toutes les équipes
        $teams = $teamRepository->findAll();
        $data = array_map(function ($team) {
            return $this->formatEntityData($team);
        }, $teams);
        return $this->json($data);
    }

    #[Route('/teams', name: 'app_team_create', methods: ['POST'])]
    public function createTeam(Request $request): JsonResponse
    {
        try {
            $data = $this->getRequestData($request);
            $team = new Team();
            
            $team->setName($data['name']);
            
            $this->saveEntity($team);
            
            return $this->json($this->formatEntityData($team));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/teams', name: 'app_team_update', methods: ['PUT'])]
    public function updateTeam(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');
            $data = $this->getRequestData($request);
            
            $team->setName($data['name']);
            
            $this->saveEntity($team);
            
            return $this->json($this->formatEntityData($team));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams', name: 'app_team_patch', methods: ['PATCH'])]
    public function patchTeam(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');
            $data = $this->getRequestData($request);
            
            if (isset($data['name'])) {
                $team->setName($data['name']);
            }
            
            $this->saveEntity($team);
            
            return $this->json($this->formatEntityData($team));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams', name: 'app_team_delete', methods: ['DELETE'])]
    public function deleteTeam(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');
            $this->deleteEntity($team);
            
            return $this->deleteSuccessResponse('Team');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
