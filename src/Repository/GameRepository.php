<?php

namespace App\Repository;

use App\Entity\Division;
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
     * Trouve les matchs dont la date est entre $from et $to et dont le rappel n'a pas encore été envoyé.
     *
     * @return Game[]
     */
    public function findGamesForReminder(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.date > :from')
            ->andWhere('g.date <= :to')
            ->andWhere('g.reminderSentAt IS NULL')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
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

    /**
     * @return Game[]
     */
    public function findPlayedByDivision(Division $division): array
    {
        return $this->createQueryBuilder('g')
            ->join('g.status', 's')
            ->where('g.division = :division')
            ->andWhere('s.name = :status')
            ->setParameter('division', $division)
            ->setParameter('status', 'played')
            ->getQuery()
            ->getResult();
    }
}
