<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\MatchReport;
use App\Entity\Season;
use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MatchReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchReport::class);
    }

    /**
     * Compte le nombre de reports d'une équipe pour une saison donnée.
     * Le chemin est : MatchReport -> Game -> Division -> Season
     */
    public function countByTeamAndSeason(Team $team, Season $season): int
    {
        return (int) $this->createQueryBuilder('mr')
            ->select('COUNT(mr.id)')
            ->join('mr.game', 'g')
            ->join('g.division', 'd')
            ->where('mr.team = :team')
            ->andWhere('d.season = :season')
            ->setParameter('team', $team)
            ->setParameter('season', $season)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return MatchReport[]
     */
    public function findByGame(Game $game): array
    {
        return $this->findBy(['game' => $game], ['createdAt' => 'DESC']);
    }

    /**
     * @return MatchReport[]
     */
    public function findByTeamAndSeason(Team $team, Season $season): array
    {
        return $this->createQueryBuilder('mr')
            ->join('mr.game', 'g')
            ->join('g.division', 'd')
            ->where('mr.team = :team')
            ->andWhere('d.season = :season')
            ->setParameter('team', $team)
            ->setParameter('season', $season)
            ->orderBy('mr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
