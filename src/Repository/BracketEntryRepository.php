<?php

namespace App\Repository;

use App\Entity\Bracket;
use App\Entity\BracketEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BracketEntry>
 */
class BracketEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BracketEntry::class);
    }

    /**
     * @return BracketEntry[]
     */
    public function findByBracketOrderedBySeed(Bracket $bracket): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.bracket = :bracket')
            ->setParameter('bracket', $bracket)
            ->orderBy('e.seed', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
