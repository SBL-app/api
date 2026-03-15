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
}
