<?php

namespace App\Tests\Unit\Service;

use App\Entity\Division;
use App\Entity\Team;
use App\Entity\TeamStat;
use App\Repository\TeamStatRepository;
use App\Service\BracketSeedingService;
use PHPUnit\Framework\TestCase;

class BracketSeedingServiceTest extends TestCase
{
    private function stat(string $name, int $points, int $win, int $loose, int $wins): TeamStat
    {
        $team = new Team();
        $team->setName($name);
        $stat = new TeamStat();
        $stat->setTeam($team);
        $stat->setPoints($points);
        $stat->setWinRounds($win);
        $stat->setLooseRounds($loose);
        $stat->setWins($wins);
        $stat->setLosses(0);
        $stat->setTies(0);
        return $stat;
    }

    public function testSeedsOrderedByPointsThenRoundDiff(): void
    {
        $division = new Division();
        $stats = [
            $this->stat('B', 6, 10, 8, 2),   // 6 pts, diff +2
            $this->stat('A', 9, 12, 4, 3),   // 9 pts
            $this->stat('C', 6, 12, 2, 2),   // 6 pts, diff +10 -> devant B
        ];

        $repo = $this->createMock(TeamStatRepository::class);
        $repo->method('findByDivision')->willReturn($stats);

        $service = new BracketSeedingService($repo);
        $teams = $service->computeSeeds($division, 3);

        $this->assertSame(['A', 'C', 'B'], array_map(fn (Team $t) => $t->getName(), $teams));
    }

    public function testRespectsQualifiedCount(): void
    {
        $division = new Division();
        $stats = [
            $this->stat('A', 9, 12, 4, 3),
            $this->stat('B', 6, 10, 8, 2),
            $this->stat('C', 3, 5, 9, 1),
        ];
        $repo = $this->createMock(TeamStatRepository::class);
        $repo->method('findByDivision')->willReturn($stats);

        $service = new BracketSeedingService($repo);
        $teams = $service->computeSeeds($division, 2);

        $this->assertCount(2, $teams);
        $this->assertSame(['A', 'B'], array_map(fn (Team $t) => $t->getName(), $teams));
    }
}
