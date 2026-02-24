<?php

namespace App\Controller;

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
        // Informations de base de l'équipe
        $teamData = $this->formatEntityData($team);

        // Récupération des joueurs de l'équipe
        $players = $playerRepository->findBy(['team' => $team]);
        $playersData = array_map(function ($player) {
            return [
                'id' => $player->getId(),
                'name' => $player->getName(),
                'discord' => $player->getDiscord()
            ];
        }, $players);

        // Récupération des statistiques de l'équipe dans toutes les divisions
        $teamStats = $teamStatRepository->findBy(['team' => $team]);
        $statsData = array_map(function ($teamStat) use ($teamStatRepository, $team) {
            $division = $teamStat->getDivision();

            // Récupération de toutes les équipes de cette division pour calculer la position
            $allTeamStatsInDivision = $teamStatRepository->findBy(['division' => $division]);

            // Tri par points décroissant pour déterminer le classement
            usort($allTeamStatsInDivision, function ($a, $b) {
                return $b->getPoints() - $a->getPoints();
            });

            // Recherche de la position de l'équipe courante
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
    public function getTeam(int $id): JsonResponse
    {
        return $this->getEntityById('App\Entity\Team', $id, 'Team');
    }

    #[Route('/teams', name: 'app_teams', methods: ['GET'])]
    public function getTeams(Request $request, TeamRepository $teamRepository): JsonResponse
    {
        $id = $request->query->get('id');

        // Backward compatibility - deprecated
        if ($id) {
            $this->logger->warning('Deprecated: Using ?id parameter for team. Use /teams/{id} instead', ['id' => $id]);
            return $this->getEntityById('App\Entity\Team', $id, 'Team');
        }

        // Sinon, retourner toutes les équipes
        $teams = $teamRepository->findAll();
        $data = array_map(function ($team) {
            return $this->formatEntityData($team);
        }, $teams);
        return $this->json($data);
    }

    #[Route('/teams/details', name: 'app_team_details', methods: ['GET'])]
    public function getTeamDetails(Request $request, TeamRepository $teamRepository, PlayerRepository $playerRepository, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $teamId = $request->query->get('team_id');
        if (!$teamId) {
            return $this->json(['error' => 'Team ID is required'], 400);
        }

        try {
            // Récupération de l'équipe
            $team = $this->findEntityOrFail('App\Entity\Team', $teamId, 'Team');

            // Formatage des données complètes avec joueurs et statistiques
            $teamDetails = $this->formatTeamWithDetails($team, $playerRepository, $teamStatRepository);

            return $this->json($teamDetails);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams', name: 'app_team_create', methods: ['POST'])]
    public function createTeam(Request $request): JsonResponse
    {
        try {
            $data = $this->getRequestData($request);
            $team = new Team();

            $team->setName($data['name']);

            return $this->securedCreateEntity($team);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/teams/{id}', name: 'app_team_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateTeam(int $id, Request $request): JsonResponse
    {
        try {
            $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');
            $data = $this->getRequestData($request);

            $team->setName($data['name']);

            return $this->securedUpdateEntity($team);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams/{id}', name: 'app_team_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patchTeam(int $id, Request $request): JsonResponse
    {
        try {
            $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');
            $data = $this->getRequestData($request);

            if (isset($data['name'])) {
                $team->setName($data['name']);
            }

            return $this->securedUpdateEntity($team);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams/{id}', name: 'app_team_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteTeam(int $id): JsonResponse
    {
        try {
            $team = $this->findEntityOrFail('App\Entity\Team', $id, 'Team');

            return $this->securedDeleteEntity($team, 'Team');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams/register', name: 'app_team_register', methods: ['POST'])]
    public function registerTeam(Request $request, SeasonRepository $seasonRepository, UserRepository $userRepository): JsonResponse
    {
        try {
            $this->checkModificationPermissions();
            $data = $this->getRequestData($request);

            // Validation des champs requis
            if (!isset($data['name'])) {
                return $this->json(['error' => 'Team name is required'], 400);
            }
            if (!isset($data['captain_discord_id'])) {
                return $this->json(['error' => 'Captain Discord ID is required'], 400);
            }
            if (!isset($data['players']) || !is_array($data['players']) || count($data['players']) < 2) {
                return $this->json(['error' => 'At least 2 players are required'], 400);
            }

            // Vérifier que le capitaine existe dans la base users
            $captainUser = $userRepository->findByDiscordId($data['captain_discord_id']);
            if (!$captainUser) {
                return $this->json(['error' => 'Captain must have a linked Discord account'], 400);
            }

            // Trouver la saison en cours ou la prochaine
            $now = new \DateTime();
            $seasons = $seasonRepository->findAll();
            $currentSeason = null;

            foreach ($seasons as $season) {
                if ($season->getStartDate() <= $now && $season->getEndDate() >= $now) {
                    $currentSeason = $season;
                    break;
                }
            }

            if (!$currentSeason) {
                // Prendre la prochaine saison
                usort($seasons, fn($a, $b) => $a->getStartDate() <=> $b->getStartDate());
                foreach ($seasons as $season) {
                    if ($season->getStartDate() > $now) {
                        $currentSeason = $season;
                        break;
                    }
                }
            }

            if (!$currentSeason) {
                return $this->json(['error' => 'No current or upcoming season found for registration'], 400);
            }

            // Créer l'équipe
            $team = new Team();
            $team->setName($data['name']);
            $team->setCaptainUser($captainUser);
            $this->entityManager->persist($team);

            // Créer les joueurs
            $createdPlayers = [];
            foreach ($data['players'] as $playerData) {
                $player = new Player();
                $player->setName($playerData['name']);
                $player->setTeam($team);

                if (isset($playerData['discord'])) {
                    $player->setDiscord($playerData['discord']);
                }

                $this->entityManager->persist($player);
                $createdPlayers[] = [
                    'name' => $player->getName(),
                    'discord' => $player->getDiscord()
                ];

                // Si ce joueur est le capitaine, le définir aussi comme captain (Player)
                if (isset($playerData['discord_id']) && $playerData['discord_id'] === $data['captain_discord_id']) {
                    $team->setCaptain($player);
                }
            }

            // Créer l'inscription à la saison
            $registration = new Registration();
            $registration->setTeam($team);
            $registration->setSeason($currentSeason);
            $this->entityManager->persist($registration);

            $this->entityManager->flush();

            return $this->json([
                'message' => 'Team registered successfully',
                'team' => [
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                    'captain_user_id' => $captainUser->getId(),
                    'captain_discord_id' => $captainUser->getDiscordId()
                ],
                'players' => $createdPlayers,
                'season' => [
                    'id' => $currentSeason->getId(),
                    'name' => $currentSeason->getName()
                ]
            ], 201);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->permissionDeniedResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    // ==========================================
    // Team Management Endpoints (ROLE_USER)
    // ==========================================

    #[Route('/teams/create-with-captain', name: 'app_team_create_with_captain', methods: ['POST'])]
    public function createTeamWithCaptain(Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
            $data = $this->getRequestData($request);

            if (!isset($data['name']) || empty(trim($data['name']))) {
                return $this->json(['error' => 'Team name is required'], 400);
            }

            $name = trim($data['name']);
            if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
                return $this->json(['error' => 'Team name must be between 2 and 50 characters'], 400);
            }

            $team = new Team();
            $team->setName($name);
            $team->setCaptainUser($user);

            $member = new TeamMember();
            $member->setUser($user);
            $member->setRole(TeamMember::ROLE_CAPTAIN);
            $team->addMember($member);

            $this->entityManager->persist($team);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Team created successfully',
                'team' => [
                    'id' => $team->getId(),
                    'name' => $team->getName(),
                ],
                'captain' => [
                    'discord_id' => $user->getDiscordId(),
                    'discord_username' => $user->getDiscordUsername(),
                    'role' => TeamMember::ROLE_CAPTAIN,
                ],
            ], 201);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->authenticationRequiredResponse($e->getMessage());
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/teams/my-teams', name: 'app_team_my_teams', methods: ['GET'])]
    public function getMyTeams(TeamMemberRepository $teamMemberRepository): JsonResponse
    {
        try {
            $user = $this->getAuthenticatedUser();
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
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->authenticationRequiredResponse($e->getMessage());
        }
    }

    #[Route('/teams/{teamId}/members', name: 'app_team_list_members', methods: ['GET'])]
    public function listTeamMembers(int $teamId): JsonResponse
    {
        try {
            $this->getAuthenticatedUser();
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
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->authenticationRequiredResponse($e->getMessage());
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams/{teamId}/members', name: 'app_team_add_member', methods: ['POST'])]
    public function addTeamMember(int $teamId, Request $request, UserRepository $userRepository, TeamMemberRepository $teamMemberRepository): JsonResponse
    {
        try {
            $currentUser = $this->getAuthenticatedUser();
            $team = $this->findEntityOrFail(Team::class, $teamId, 'Team');
            $data = $this->getRequestData($request);

            if (!isset($data['discord_id'])) {
                return $this->json(['error' => 'discord_id is required'], 400);
            }

            // Verify current user is captain
            $currentMembership = $teamMemberRepository->findByTeamAndUser($team, $currentUser);
            if (!$currentMembership || !$currentMembership->isCaptain()) {
                return $this->json(['error' => 'Only team captains can add members'], 403);
            }

            // Find target user
            $targetUser = $userRepository->findByDiscordId($data['discord_id']);
            if (!$targetUser) {
                return $this->json(['error' => 'User not found. They must link their Discord account first.'], 404);
            }

            // Check if already a member
            $existingMembership = $teamMemberRepository->findByTeamAndUser($team, $targetUser);
            if ($existingMembership) {
                return $this->json(['error' => 'This user is already a member of the team'], 409);
            }

            $member = new TeamMember();
            $member->setUser($targetUser);
            $member->setRole(TeamMember::ROLE_MEMBER);
            $team->addMember($member);

            $this->entityManager->flush();

            return $this->json([
                'message' => 'Member added successfully',
                'member' => [
                    'discord_id' => $targetUser->getDiscordId(),
                    'discord_username' => $targetUser->getDiscordUsername(),
                    'role' => TeamMember::ROLE_MEMBER,
                    'joined_at' => $member->getJoinedAt()->format('Y-m-d H:i:s'),
                ],
            ], 201);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->authenticationRequiredResponse($e->getMessage());
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams/{teamId}/members', name: 'app_team_remove_member', methods: ['DELETE'])]
    public function removeTeamMember(int $teamId, Request $request, UserRepository $userRepository, TeamMemberRepository $teamMemberRepository): JsonResponse
    {
        try {
            $currentUser = $this->getAuthenticatedUser();
            $team = $this->findEntityOrFail(Team::class, $teamId, 'Team');
            $data = $this->getRequestData($request);

            $currentMembership = $teamMemberRepository->findByTeamAndUser($team, $currentUser);

            // Determine if this is self-removal or captain removing someone
            $targetDiscordId = $data['discord_id'] ?? null;
            $isSelfRemoval = !$targetDiscordId || $targetDiscordId === $currentUser->getDiscordId();

            if ($isSelfRemoval) {
                // Self-removal
                if (!$currentMembership) {
                    return $this->json(['error' => 'You are not a member of this team'], 400);
                }

                // If captain and last captain, block
                if ($currentMembership->isCaptain()) {
                    $captainCount = $teamMemberRepository->countCaptainsByTeam($team);
                    if ($captainCount <= 1) {
                        return $this->json(['error' => 'You are the last captain. Promote another member before leaving.'], 400);
                    }
                }

                $team->removeMember($currentMembership);
                $this->entityManager->remove($currentMembership);
                $this->entityManager->flush();

                return $this->json(['message' => 'You have left the team']);
            } else {
                // Captain removing someone
                if (!$currentMembership || !$currentMembership->isCaptain()) {
                    return $this->json(['error' => 'Only team captains can remove members'], 403);
                }

                $targetUser = $userRepository->findByDiscordId($targetDiscordId);
                if (!$targetUser) {
                    return $this->json(['error' => 'User not found'], 404);
                }

                $targetMembership = $teamMemberRepository->findByTeamAndUser($team, $targetUser);
                if (!$targetMembership) {
                    return $this->json(['error' => 'This user is not a member of the team'], 400);
                }

                $team->removeMember($targetMembership);
                $this->entityManager->remove($targetMembership);
                $this->entityManager->flush();

                return $this->json(['message' => 'Member removed successfully']);
            }
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->authenticationRequiredResponse($e->getMessage());
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/teams/{teamId}/members/role', name: 'app_team_change_role', methods: ['PATCH'])]
    public function changeTeamMemberRole(int $teamId, Request $request, UserRepository $userRepository, TeamMemberRepository $teamMemberRepository): JsonResponse
    {
        try {
            $currentUser = $this->getAuthenticatedUser();
            $team = $this->findEntityOrFail(Team::class, $teamId, 'Team');
            $data = $this->getRequestData($request);

            if (!isset($data['discord_id']) || !isset($data['role'])) {
                return $this->json(['error' => 'discord_id and role are required'], 400);
            }

            $newRole = $data['role'];
            if (!in_array($newRole, [TeamMember::ROLE_CAPTAIN, TeamMember::ROLE_MEMBER])) {
                return $this->json(['error' => 'Invalid role. Must be "captain" or "member"'], 400);
            }

            // Verify current user is captain
            $currentMembership = $teamMemberRepository->findByTeamAndUser($team, $currentUser);
            if (!$currentMembership || !$currentMembership->isCaptain()) {
                return $this->json(['error' => 'Only team captains can change roles'], 403);
            }

            // Find target user
            $targetUser = $userRepository->findByDiscordId($data['discord_id']);
            if (!$targetUser) {
                return $this->json(['error' => 'User not found'], 404);
            }

            $targetMembership = $teamMemberRepository->findByTeamAndUser($team, $targetUser);
            if (!$targetMembership) {
                return $this->json(['error' => 'This user is not a member of the team'], 400);
            }

            // If demoting from captain, check they're not the last captain
            if ($targetMembership->isCaptain() && $newRole === TeamMember::ROLE_MEMBER) {
                $captainCount = $teamMemberRepository->countCaptainsByTeam($team);
                if ($captainCount <= 1) {
                    return $this->json(['error' => 'Cannot demote the last captain. Promote another member first.'], 400);
                }
            }

            $targetMembership->setRole($newRole);
            $this->entityManager->flush();

            return $this->json([
                'message' => 'Role updated successfully',
                'member' => [
                    'discord_id' => $targetUser->getDiscordId(),
                    'discord_username' => $targetUser->getDiscordUsername(),
                    'role' => $targetMembership->getRole(),
                ],
            ]);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->authenticationRequiredResponse($e->getMessage());
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
