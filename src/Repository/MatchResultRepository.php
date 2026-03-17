<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\MatchResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MatchResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchResult::class);
    }

    /**
     * @return MatchResult[]
     */
    public function findByGame(Game $game): array
    {
        return $this->findBy(['game' => $game], ['createdAt' => 'DESC']);
    }

    public function findPendingByGame(Game $game): ?MatchResult
    {
        return $this->findOneBy([
            'game' => $game,
            'status' => MatchResult::STATUS_PENDING,
        ]);
    }
}
