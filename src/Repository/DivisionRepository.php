<?php

namespace App\Repository;

use App\Entity\Division;
use App\Entity\Season;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Division>
 */
class DivisionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Division::class);
    }

    public function hasNonFinalizedDivisionInSeason(Season $season): bool
    {
        $count = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.season = :season')
            ->andWhere('d.isFinalized = false')
            ->setParameter('season', $season)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
