<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\TeamMember;
use App\Exception\ApiProblemException;
use App\Repository\TeamMemberRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class UserController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof User) {
            throw new \InvalidArgumentException('Entity must be an instance of User');
        }
        return [
            'id' => $entity->getId(),
            'username' => $entity->getUsername(),
            'roles' => $entity->getRoles(),
            'last_login' => $entity->getLastLogin()?->format('Y-m-d H:i:s'),
            'is_active' => $entity->isActive()
        ];
    }

    #[Route('/users/me', name: 'app_user_me', methods: ['GET'])]
    public function getMe(): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::unauthorized($e->getMessage());
        }

        return $this->json($this->formatEntityData($user));
    }

    #[Route('/users/me/teams', name: 'app_user_me_teams', methods: ['GET'])]
    public function getMyTeams(TeamMemberRepository $teamMemberRepository): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::unauthorized($e->getMessage());
        }

        $memberships = $teamMemberRepository->findByUser($user);

        $teamsData = array_map(function (TeamMember $membership) {
            $team = $membership->getTeam();
            return [
                'team' => [
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                ],
                'role' => $membership->getRole(),
                'joined_at' => $membership->getJoinedAt()->format('Y-m-d H:i:s'),
                'members_count' => $team->getMembers()->count(),
            ];
        }, $memberships);

        return $this->json($teamsData);
    }
}
