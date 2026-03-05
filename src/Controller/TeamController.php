<?php

namespace App\Controller;

use App\Exception\ApiProblemException;
use App\Repository\TeamRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\PlayerRepository;
use App\Repository\TeamStatRepository;
use App\Repository\SeasonRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\Player;
use App\Entity\Registration;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api')]
class TeamController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Team) {
            throw new \InvalidArgumentException('Entity must be an instance of Team');
        }

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'captain' => $entity->getCaptain() ? $entity->getCaptain()->getName() : null,
            'captain_id' => $entity->getCaptain() ? $entity->getCaptain()->getId() : null
        ];
    }

    /**
     * Formate les données complètes d'une équipe avec joueurs et statistiques
     */
    private function formatTeamWithDetails(Team $team, PlayerRepository $playerRepository, TeamStatRepository $teamStatRepository): array
    {
        $teamData = $this->formatEntityData($team);

        $players = $playerRepository->findBy(['team' => $team]);
        $playersData = array_map(function ($player) {
            return [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'discord' => $player->getDiscord()
            ];
        }, $players);

        $teamStats = $teamStatRepository->findBy(['team' => $team]);
        $statsData = array_map(function ($teamStat) use ($teamStatRepository, $team) {
            $division = $teamStat->getDivision();

            $allTeamStatsInDivision = $teamStatRepository->findBy(['division' => $division]);

            usort($allTeamStatsInDivision, function ($a, $b) {
                return $b->getPoints() - $a->getPoints();
            });

            $position = 1;
            foreach ($allTeamStatsInDivision as $index => $stat) {
                if ($stat->getTeam()->getId() === $team->getId()) {
                    $position = $index + 1;
                    break;
                }
            }

            return [
                'division_id' => $division->getId(),
                'division_name' => $division->getName(),
                'season_id' => $division->getSeason()->getId(),
                'season_name' => $division->getSeason()->getName(),
                'position' => $position,
                'total_teams' => count($allTeamStatsInDivision),
                'wins' => $teamStat->getWins(),
                'losses' => $teamStat->getLosses(),
                'ties' => $teamStat->getTies(),
                'winRounds' => $teamStat->getWinRounds(),
                'looseRounds' => $teamStat->getLooseRounds(),
                'points' => $teamStat->getPoints()
            ];
        }, $teamStats);

        return [
            'team' => $teamData,
            'players' => $playersData,
            'stats' => $statsData,
            'players_count' => count($playersData),
            'divisions_count' => count($statsData)
        ];
    }

    #[Route('/teams/{id}', name: 'app_team_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getTeam(int $id, Request $request, PlayerRepository $playerRepository, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');

        // Support ?expand=players,stats pour inclure les détails
        $expand = $request->query->get('expand', '');
        $expandFields = array_filter(array_map('trim', explode(',', $expand)));

        if (!empty($expandFields)) {
            return $this->json($this->formatTeamWithDetails($team, $playerRepository, $teamStatRepository));
        }

        return $this->json($this->formatEntityData($team));
    }

    #[Route('/teams', name: 'app_teams', methods: ['GET'])]
    public function getTeams(TeamRepository $teamRepository): JsonResponse
    {
        $teams = $teamRepository->findAll();
        $data = array_map(function ($team) {
            return $this->formatEntityData($team);
        }, $teams);
        return $this->json($data);
    }

    /**
     * POST /api/teams - Créer une équipe
     *
     * Body minimal : {"name": "..."}
     * Avec capitaine : {"name": "...", "captain": true} (utilise l'utilisateur authentifié)
     */
    #[Route('/teams', name: 'app_team_create', methods: ['POST'])]
    public function createTeam(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);

        if (!isset($data['name']) || empty(trim($data['name']))) {
            throw ApiProblemException::validationError('Team name is required', [['field' => 'name', 'message' => 'This value should not be blank.']]);
        }

        $name = trim($data['name']);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
            throw ApiProblemException::validationError('Team name must be between 2 and 50 characters', [['field' => 'name', 'message' => 'This value must be between 2 and 50 characters.']]);
        }

        $team = new Team();
        $team->setName($name);

        // Si captain=true, utiliser l'utilisateur authentifié comme capitaine
        if (isset($data['captain']) && $data['captain'] === true) {
            try {
                $user = $this->getAuthenticatedUser();
                $this->checkModificationPermissions();
            } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
                throw ApiProblemException::unauthorized($e->getMessage());
            }

            $team->setCaptainUser($user);

            $member = new TeamMember();
            $member->setUser($user);
            $member->setRole(TeamMember::ROLE_CAPTAIN);
            $team->addMember($member);

            $this->entityManager->persist($team);
            $this->entityManager->flush();

            $this->logger->info('Team created with captain', ['entity' => Team::class, 'id' => $team->getId()]);

            $response = $this->json([
                'id' => $team->getId(),
                'name' => $team->getName(),
                'captain' => [
                    'discord_id' => $user->getDiscordId(),
                    'discord_username' => $user->getDiscordUsername(),
                    'role' => TeamMember::ROLE_CAPTAIN,
                ],
            ], 201);
            $response->headers->set('Location', $request->getPathInfo() . '/' . $team->getId());
            return $response;
        }

        return $this->securedCreateEntity($team, $request);
    }

    #[Route('/teams/{id}', name: 'app_team_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateTeam(int $id, Request $request): JsonResponse
    {
        $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');
        $data = $this->getRequestData($request);

        $team->setName($data['name']);

        return $this->securedUpdateEntity($team);
    }

    #[Route('/teams/{id}', name: 'app_team_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patchTeam(int $id, Request $request): JsonResponse
    {
        $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');
        $data = $this->getRequestData($request);

        if (isset($data['name'])) {
            $team->setName($data['name']);
        }

        return $this->securedUpdateEntity($team);
    }

    #[Route('/teams/{id}', name: 'app_team_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteTeam(int $id): JsonResponse
    {
        $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');

        return $this->securedDeleteEntity($team, 'Team');
    }

    // ==========================================
    // Team Members Management (ROLE_USER)
    // ==========================================

    #[Route('/teams/{teamId}/members', name: 'app_team_list_members', methods: ['GET'])]
    public function listTeamMembers(int $teamId): JsonResponse
    {
        try {
            $this->getAuthenticatedUser();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::unauthorized($e->getMessage());
        }

        $team = $this->findEntityOrFail(Team::class, $teamId, 'Team');

        $membersData = array_map(function (TeamMember $member) {
            $user = $member->getUser();
            return [
                'role' => $member->getRole(),
                'discord_id' => $user->getDiscordId(),
                'discord_username' => $user->getDiscordUsername(),
                'joined_at' => $member->getJoinedAt()->format('Y-m-d H:i:s'),
            ];
        }, $team->getMembers()->toArray());

        return $this->json([
            'team' => [
                'id' => $team->getId(),
                'name' => $team->getName(),
            ],
            'members' => array_values($membersData),
        ]);
    }

    #[Route('/teams/{teamId}/members', name: 'app_team_add_member', methods: ['POST'])]
    public function addTeamMember(int $teamId, Request $request, UserRepository $userRepository, TeamMemberRepository $teamMemberRepository): JsonResponse
    {
        try {
            $currentUser = $this->getAuthenticatedUser();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::unauthorized($e->getMessage());
        }

        $team = $this->findEntityOrFail(Team::class, $teamId, 'Team');
        $data = $this->getRequestData($request);

        if (!isset($data['discord_id'])) {
            throw ApiProblemException::validationError('discord_id is required', [['field' => 'discord_id', 'message' => 'This value should not be blank.']]);
        }

        $currentMembership = $teamMemberRepository->findByTeamAndUser($team, $currentUser);
        if (!$currentMembership || !$currentMembership->isCaptain()) {
            throw ApiProblemException::forbidden('Only team captains can add members');
        }

        $targetUser = $userRepository->findByDiscordId($data['discord_id']);
        if (!$targetUser) {
            throw ApiProblemException::notFound('User not found. They must link their Discord account first.');
        }

        $existingMembership = $teamMemberRepository->findByTeamAndUser($team, $targetUser);
        if ($existingMembership) {
            throw ApiProblemException::conflict('This user is already a member of the team');
        }

        $member = new TeamMember();
        $member->setUser($targetUser);
        $member->setRole(TeamMember::ROLE_MEMBER);
        $team->addMember($member);

        $this->entityManager->flush();

        $response = $this->json([
            'role' => TeamMember::ROLE_MEMBER,
            'discord_id' => $targetUser->getDiscordId(),
            'discord_username' => $targetUser->getDiscordUsername(),
            'joined_at' => $member->getJoinedAt()->format('Y-m-d H:i:s'),
        ], 201);
        $response->headers->set('Location', $request->getPathInfo());
        return $response;
    }

    #[Route('/teams/{teamId}/members', name: 'app_team_remove_member', methods: ['DELETE'])]
    public function removeTeamMember(int $teamId, Request $request, UserRepository $userRepository, TeamMemberRepository $teamMemberRepository): JsonResponse
    {
        try {
            $currentUser = $this->getAuthenticatedUser();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::unauthorized($e->getMessage());
        }

        $team = $this->findEntityOrFail(Team::class, $teamId, 'Team');
        $data = $this->getRequestData($request);

        $currentMembership = $teamMemberRepository->findByTeamAndUser($team, $currentUser);

        $targetDiscordId = $data['discord_id'] ?? null;
        $isSelfRemoval = !$targetDiscordId || $targetDiscordId === $currentUser->getDiscordId();

        if ($isSelfRemoval) {
            if (!$currentMembership) {
                throw ApiProblemException::badRequest('You are not a member of this team');
            }

            if ($currentMembership->isCaptain()) {
                $captainCount = $teamMemberRepository->countCaptainsByTeam($team);
                if ($captainCount <= 1) {
                    throw ApiProblemException::badRequest('You are the last captain. Promote another member before leaving.');
                }
            }

            $team->removeMember($currentMembership);
            $this->entityManager->remove($currentMembership);
            $this->entityManager->flush();

            return new JsonResponse(null, 204);
        } else {
            if (!$currentMembership || !$currentMembership->isCaptain()) {
                throw ApiProblemException::forbidden('Only team captains can remove members');
            }

            $targetUser = $userRepository->findByDiscordId($targetDiscordId);
            if (!$targetUser) {
                throw ApiProblemException::notFound('User not found');
            }

            $targetMembership = $teamMemberRepository->findByTeamAndUser($team, $targetUser);
            if (!$targetMembership) {
                throw ApiProblemException::badRequest('This user is not a member of the team');
            }

            $team->removeMember($targetMembership);
            $this->entityManager->remove($targetMembership);
            $this->entityManager->flush();

            return new JsonResponse(null, 204);
        }
    }

    #[Route('/teams/{teamId}/members/role', name: 'app_team_change_role', methods: ['PATCH'])]
    public function changeTeamMemberRole(int $teamId, Request $request, UserRepository $userRepository, TeamMemberRepository $teamMemberRepository): JsonResponse
    {
        try {
            $currentUser = $this->getAuthenticatedUser();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::unauthorized($e->getMessage());
        }

        $team = $this->findEntityOrFail(Team::class, $teamId, 'Team');
        $data = $this->getRequestData($request);

        if (!isset($data['discord_id']) || !isset($data['role'])) {
            throw ApiProblemException::badRequest('discord_id and role are required');
        }

        $newRole = $data['role'];
        if (!in_array($newRole, [TeamMember::ROLE_CAPTAIN, TeamMember::ROLE_MEMBER])) {
            throw ApiProblemException::badRequest('Invalid role. Must be "captain" or "member"');
        }

        $currentMembership = $teamMemberRepository->findByTeamAndUser($team, $currentUser);
        if (!$currentMembership || !$currentMembership->isCaptain()) {
            throw ApiProblemException::forbidden('Only team captains can change roles');
        }

        $targetUser = $userRepository->findByDiscordId($data['discord_id']);
        if (!$targetUser) {
            throw ApiProblemException::notFound('User not found');
        }

        $targetMembership = $teamMemberRepository->findByTeamAndUser($team, $targetUser);
        if (!$targetMembership) {
            throw ApiProblemException::badRequest('This user is not a member of the team');
        }

        if ($targetMembership->isCaptain() && $newRole === TeamMember::ROLE_MEMBER) {
            $captainCount = $teamMemberRepository->countCaptainsByTeam($team);
            if ($captainCount <= 1) {
                throw ApiProblemException::badRequest('Cannot demote the last captain. Promote another member first.');
            }
        }

        $targetMembership->setRole($newRole);
        $this->entityManager->flush();

        return $this->json([
            'discord_id' => $targetUser->getDiscordId(),
            'discord_username' => $targetUser->getDiscordUsername(),
            'role' => $targetMembership->getRole(),
        ]);
    }
}
