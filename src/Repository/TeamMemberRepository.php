<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamMember>
 *
 * @method TeamMember|null find($id, $lockMode = null, $lockVersion = null)
 * @method TeamMember|null findOneBy(array $criteria, array $orderBy = null)
 * @method TeamMember[]    findAll()
 * @method TeamMember[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeamMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamMember::class);
    }

    /**
     * @return TeamMember[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user]);
    }

    public function findByTeamAndUser(Team $team, User $user): ?TeamMember
    {
        return $this->findOneBy(['team' => $team, 'user' => $user]);
    }

    /**
     * @return TeamMember[]
     */
    public function findCaptainsByTeam(Team $team): array
    {
        return $this->findBy(['team' => $team, 'role' => TeamMember::ROLE_CAPTAIN]);
    }

    public function countCaptainsByTeam(Team $team): int
    {
        return $this->count(['team' => $team, 'role' => TeamMember::ROLE_CAPTAIN]);
    }
}
