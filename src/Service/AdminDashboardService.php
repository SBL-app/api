<?php

namespace App\Service;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\MatchResult;
use App\Entity\Registration;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Agrégats en lecture seule pour le dashboard admin.
 */
class AdminDashboardService
{
    public const STATUS_PENDING_RESULT = 'pending_result';
    public const STATUS_REPORTED = 'reported';
    public const STATUS_PLAYED = 'played';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getAggregates(): array
    {
        return [
            'pending_results' => $this->countGamesByStatus(self::STATUS_PENDING_RESULT),
            'contested_results' => $this->countContestedResults(),
            'reported_games' => $this->countGamesByStatus(self::STATUS_REPORTED, true),
            'pending_registrations' => $this->countPendingRegistrations(),
            'division_progress' => $this->getDivisionProgress(),
            'items' => [
                'pending_results' => $this->listPendingResults(),
                'pending_registrations' => $this->listPendingRegistrations(),
            ],
        ];
    }

    /**
     * Liste des matchs en statut pending_result.
     *
     * @return list<array<string, mixed>>
     */
    private function listPendingResults(): array
    {
        $games = $this->entityManager->createQueryBuilder()
            ->select('g', 'd', 't1', 't2')
            ->from(Game::class, 'g')
            ->join('g.status', 's')
            ->leftJoin('g.division', 'd')
            ->leftJoin('g.team1', 't1')
            ->leftJoin('g.team2', 't2')
            ->where('s.name = :status')
            ->setParameter('status', self::STATUS_PENDING_RESULT)
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (Game $g): array => [
            'id' => $g->getId(),
            'division_id' => $g->getDivision()?->getId(),
            'division_name' => $g->getDivision()?->getName(),
            'date' => $g->getDate()?->format(\DATE_ATOM),
            'team1' => ['id' => $g->getTeam1()?->getId(), 'name' => $g->getTeam1()?->getName()],
            'team2' => ['id' => $g->getTeam2()?->getId(), 'name' => $g->getTeam2()?->getName()],
        ], $games);
    }

    /**
     * Liste des inscriptions en attente.
     *
     * @return list<array<string, mixed>>
     */
    private function listPendingRegistrations(): array
    {
        $registrations = $this->entityManager->createQueryBuilder()
            ->select('r', 'se', 't')
            ->from(Registration::class, 'r')
            ->leftJoin('r.season', 'se')
            ->leftJoin('r.team', 't')
            ->where('r.status = :status')
            ->setParameter('status', Registration::STATUS_PENDING)
            ->orderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (Registration $r): array => [
            'id' => $r->getId(),
            'season_id' => $r->getSeason()?->getId(),
            'season_name' => $r->getSeason()?->getName(),
            'team' => ['id' => $r->getTeam()?->getId(), 'name' => $r->getTeam()?->getName()],
        ], $registrations);
    }

    /**
     * Matchs ayant le statut donné, groupés par division.
     * Si $unscheduledOnly, seuls les matchs sans date sont comptés (à reprogrammer).
     *
     * @return array{total: int, by_division: list<array{division_id: int, division_name: ?string, count: int}>}
     */
    private function countGamesByStatus(string $statusName, bool $unscheduledOnly = false): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('d.id AS division_id', 'd.name AS division_name', 'COUNT(g.id) AS cnt')
            ->from(Game::class, 'g')
            ->join('g.status', 's')
            ->join('g.division', 'd')
            ->where('s.name = :status')
            ->setParameter('status', $statusName)
            ->groupBy('d.id', 'd.name')
            ->orderBy('d.id', 'ASC');

        if ($unscheduledOnly) {
            $qb->andWhere('g.date IS NULL');
        }

        return $this->formatGrouped($qb->getQuery()->getArrayResult());
    }

    /**
     * Résultats contestés (MatchResult), groupés par division.
     */
    private function countContestedResults(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('d.id AS division_id', 'd.name AS division_name', 'COUNT(r.id) AS cnt')
            ->from(MatchResult::class, 'r')
            ->join('r.game', 'g')
            ->join('g.division', 'd')
            ->where('r.status = :status')
            ->setParameter('status', MatchResult::STATUS_CONTESTED)
            ->groupBy('d.id', 'd.name')
            ->orderBy('d.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $this->formatGrouped($rows);
    }

    /**
     * Inscriptions en attente, groupées par saison.
     *
     * @return array{total: int, by_season: list<array{season_id: int, season_name: ?string, count: int}>}
     */
    private function countPendingRegistrations(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('se.id AS season_id', 'se.name AS season_name', 'COUNT(r.id) AS cnt')
            ->from(Registration::class, 'r')
            ->join('r.season', 'se')
            ->where('r.status = :status')
            ->setParameter('status', Registration::STATUS_PENDING)
            ->groupBy('se.id', 'se.name')
            ->orderBy('se.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $total = 0;
        $bySeason = [];
        foreach ($rows as $row) {
            $count = (int) $row['cnt'];
            $total += $count;
            $bySeason[] = [
                'season_id' => (int) $row['season_id'],
                'season_name' => $row['season_name'],
                'count' => $count,
            ];
        }

        return ['total' => $total, 'by_season' => $bySeason];
    }

    /**
     * Avancement par division : matchs joués / total.
     *
     * @return list<array{division_id: int, division_name: ?string, played: int, total: int}>
     */
    private function getDivisionProgress(): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select(
                'd.id AS division_id',
                'd.name AS division_name',
                'COUNT(g.id) AS total',
                'SUM(CASE WHEN s.name = :played THEN 1 ELSE 0 END) AS played'
            )
            ->from(Division::class, 'd')
            ->leftJoin(Game::class, 'g', 'WITH', 'g.division = d')
            ->leftJoin('g.status', 's')
            ->setParameter('played', self::STATUS_PLAYED)
            ->groupBy('d.id', 'd.name')
            ->orderBy('d.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'division_id' => (int) $row['division_id'],
            'division_name' => $row['division_name'],
            'played' => (int) $row['played'],
            'total' => (int) $row['total'],
        ], $rows);
    }

    private function formatGrouped(array $rows): array
    {
        $total = 0;
        $byDivision = [];
        foreach ($rows as $row) {
            $count = (int) $row['cnt'];
            $total += $count;
            $byDivision[] = [
                'division_id' => (int) $row['division_id'],
                'division_name' => $row['division_name'],
                'count' => $count,
            ];
        }

        return ['total' => $total, 'by_division' => $byDivision];
    }
}
