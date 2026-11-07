<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\TeamMember;
use App\Exception\ApiProblemException;
use App\Repository\TeamMemberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/users/me/notification-preferences', name: 'app_user_me_notification_prefs', methods: ['GET'])]
    public function getNotificationPreferences(): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::unauthorized($e->getMessage());
        }

        return $this->json($this->formatPreferences($user));
    }

    /**
     * PUT /api/users/me/notification-preferences
     * Corps : {"channels": {"discord": true, "push": false, "mail": true}, "email": "a@b.c"}
     */
    #[Route('/users/me/notification-preferences', name: 'app_user_me_notification_prefs_update', methods: ['PUT'])]
    public function updateNotificationPreferences(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::unauthorized($e->getMessage());
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 422);
        }

        if (array_key_exists('email', $data)) {
            $email = is_string($data['email']) ? trim($data['email']) : null;
            if ($email === '') {
                $email = null;
            }
            if ($email !== null && (strlen($email) > 180 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
                return $this->json(['error' => 'Invalid email'], 422);
            }
            $user->setEmail($email);
        }

        if (isset($data['channels'])) {
            if (!is_array($data['channels'])) {
                return $this->json(['error' => 'channels must be an object'], 422);
            }
            $enabled = $user->getNotificationChannels();
            foreach ($data['channels'] as $channel => $on) {
                if (!in_array($channel, User::CHANNELS, true) || !is_bool($on)) {
                    return $this->json(['error' => 'Unknown channel or non-boolean value: ' . $channel], 422);
                }
                $enabled = $on
                    ? array_merge($enabled, [$channel])
                    : array_diff($enabled, [$channel]);
            }
            $user->setNotificationChannels(array_unique($enabled));
        }

        if ($user->wantsNotificationChannel(User::CHANNEL_MAIL) && $user->getEmail() === null) {
            return $this->json(['error' => 'An email is required to enable mail notifications'], 422);
        }

        $em->flush();

        return $this->json($this->formatPreferences($user));
    }

    private function formatPreferences(User $user): array
    {
        $channels = [];
        foreach (User::CHANNELS as $channel) {
            $channels[$channel] = $user->wantsNotificationChannel($channel);
        }

        return ['channels' => $channels, 'email' => $user->getEmail()];
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
