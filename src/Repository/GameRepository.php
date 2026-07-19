<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * Matchs sans date planifiée (date IS NULL) pour une semaine et une saison données.
     *
     * @return Game[]
     */
    public function findUnscheduled(int $week, int $seasonId): array
    {
        return $this->createQueryBuilder('g')
            ->innerJoin('g.division', 'd')
            ->andWhere('g.date IS NULL')
            ->andWhere('g.week = :week')
            ->andWhere('d.season = :season')
            ->setParameter('week', $week)
            ->setParameter('season', $seasonId)
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Numéro de semaine le plus élevé du calendrier d'une saison (null si aucun match).
     */
    public function findMaxWeekForSeason(int $seasonId): ?int
    {
        $max = $this->createQueryBuilder('g')
            ->select('MAX(g.week)')
            ->innerJoin('g.division', 'd')
            ->andWhere('d.season = :season')
            ->setParameter('season', $seasonId)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $max ? (int) $max : null;
    }

    //    /**
    //     * @return Game[] Returns an array of Game objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('g.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Game
    //    {
    //        return $this->createQueryBuilder('g')
    //            ->andWhere('g.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
