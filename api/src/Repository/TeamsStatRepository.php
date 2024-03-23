<?php

namespace App\Repository;

use App\Entity\TeamsStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamsStat>
 *
 * @method TeamsStat|null find($id, $lockMode = null, $lockVersion = null)
 * @method TeamsStat|null findOneBy(array $criteria, array $orderBy = null)
 * @method TeamsStat[]    findAll()
 * @method TeamsStat[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TeamsStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamsStat::class);
    }

//    /**
//     * @return TeamsStat[] Returns an array of TeamsStat objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('t.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?TeamsStat
//    {
//        return $this->createQueryBuilder('t')
//            ->andWhere('t.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
