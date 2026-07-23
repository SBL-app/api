<?php

namespace App\Repository;

use App\Entity\Division;
use App\Entity\Team;
use App\Entity\TeamStat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamStat>
 */
class TeamStatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamStat::class);
    }

    public function findByTeamAndDivision(Team $team, Division $division): ?TeamStat
    {
        return $this->findOneBy([
            'team' => $team,
            'division' => $division,
        ]);
    }

    /**
     * @return TeamStat[]
     */
    public function findByDivision(Division $division): array
    {
        return $this->findBy(['division' => $division]);
    }

    /**
     * Retourne le TeamStat d'une équipe pour une saison donnée (via la division),
     * ou null si l'équipe n'est rattachée à aucune division de cette saison.
     */
    public function findOneByTeamAndSeason(Team $team, \App\Entity\Season $season): ?TeamStat
    {
        $result = $this->createQueryBuilder('ts')
            ->join('ts.division', 'd')
            ->andWhere('ts.team = :team')
            ->andWhere('d.season = :season')
            ->setParameter('team', $team)
            ->setParameter('season', $season)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof TeamStat ? $result : null;
    }

    //    /**
    //     * @return TeamStat[] Returns an array of TeamStat objects
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

    //    public function findOneBySomeField($value): ?TeamStat
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
