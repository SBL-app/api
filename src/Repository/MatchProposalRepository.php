<?php

namespace App\Repository;

use App\Entity\MatchProposal;
use App\Entity\User;
use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchProposal>
 *
 * @method MatchProposal|null find($id, $lockMode = null, $lockVersion = null)
 * @method MatchProposal|null findOneBy(array $criteria, array $orderBy = null)
 * @method MatchProposal[]    findAll()
 * @method MatchProposal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MatchProposalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchProposal::class);
    }

    public function findByGame(Game $game): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.game = :game')
            ->setParameter('game', $game)
            ->orderBy('mp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingByReceiver(User $user): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.receiver = :user')
            ->andWhere('mp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', MatchProposal::STATUS_PENDING)
            ->orderBy('mp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingByProposer(User $user): array
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.proposer = :user')
            ->andWhere('mp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', MatchProposal::STATUS_PENDING)
            ->orderBy('mp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestPendingForGame(Game $game): ?MatchProposal
    {
        return $this->createQueryBuilder('mp')
            ->andWhere('mp.game = :game')
            ->andWhere('mp.status = :status')
            ->setParameter('game', $game)
            ->setParameter('status', MatchProposal::STATUS_PENDING)
            ->orderBy('mp.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
