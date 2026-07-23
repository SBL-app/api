<?php

namespace App\Repository;

use App\Entity\Season;
use App\Entity\Team;
use App\Entity\Transfer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transfer>
 */
class TransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transfer::class);
    }

    /**
     * Nombre de transferts entrants d'une équipe pour une saison.
     */
    public function countByToTeamAndSeason(Team $team, Season $season): int
    {
        return $this->count(['toTeam' => $team, 'season' => $season]);
    }

    /**
     * @return Transfer[]
     */
    public function findByToTeamAndSeason(Team $team, Season $season): array
    {
        return $this->findBy(['toTeam' => $team, 'season' => $season], ['createdAt' => 'DESC']);
    }
}
