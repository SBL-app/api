<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GameResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameResult>
 *
 * @method GameResult|null find($id, $lockMode = null, $lockVersion = null)
 * @method GameResult|null findOneBy(array $criteria, array $orderBy = null)
 * @method GameResult[]    findAll()
 * @method GameResult[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GameResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameResult::class);
    }

    public function findPendingByGame(Game $game): ?GameResult
    {
        return $this->findOneBy([
            'game' => $game,
            'status' => GameResult::STATUS_PENDING_VALIDATION,
        ]);
    }

    public function findDisputedByGame(Game $game): ?GameResult
    {
        return $this->findOneBy([
            'game' => $game,
            'status' => GameResult::STATUS_DISPUTED,
        ]);
    }

    /**
     * @return GameResult[]
     */
    public function findExpiredPending(int $timeoutDays): array
    {
        $cutoff = new \DateTimeImmutable("-{$timeoutDays} days");

        return $this->createQueryBuilder('gr')
            ->where('gr.status = :status')
            ->andWhere('gr.createdAt < :cutoff')
            ->setParameter('status', GameResult::STATUS_PENDING_VALIDATION)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getResult();
    }

    public function findLatestByGame(Game $game): ?GameResult
    {
        return $this->createQueryBuilder('gr')
            ->where('gr.game = :game')
            ->setParameter('game', $game)
            ->orderBy('gr.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
