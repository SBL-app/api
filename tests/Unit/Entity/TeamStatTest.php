<?php

namespace App\Tests\Unit\Entity;

use App\Entity\TeamStat;
use App\Entity\Team;
use App\Entity\Division;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité TeamStat
 */
class TeamStatTest extends TestCase
{
    private TeamStat $teamStat;

    protected function setUp(): void
    {
        $this->teamStat = new TeamStat();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->teamStat->getId());
        $this->assertNull($this->teamStat->getWins());
        $this->assertNull($this->teamStat->getLosses());
        $this->assertNull($this->teamStat->getTeam());
        $this->assertNull($this->teamStat->getDivision());
        $this->assertNull($this->teamStat->getPoints());
        $this->assertNull($this->teamStat->getTies());
        $this->assertNull($this->teamStat->getWinRounds());
        $this->assertNull($this->teamStat->getLooseRounds());
    }

    public function testSetAndGetWins(): void
    {
        $wins = 8;

        $result = $this->teamStat->setWins($wins);

        $this->assertSame($this->teamStat, $result); // Test fluent interface
        $this->assertEquals($wins, $this->teamStat->getWins());
    }

    public function testSetAndGetLosses(): void
    {
        $losses = 3;

        $result = $this->teamStat->setLosses($losses);

        $this->assertSame($this->teamStat, $result);
        $this->assertEquals($losses, $this->teamStat->getLosses());
    }

    public function testSetAndGetTeam(): void
    {
        $team = new Team();

        $result = $this->teamStat->setTeam($team);

        $this->assertSame($this->teamStat, $result);
        $this->assertSame($team, $this->teamStat->getTeam());
    }

    public function testSetAndGetDivision(): void
    {
        $division = new Division();

        $result = $this->teamStat->setDivision($division);

        $this->assertSame($this->teamStat, $result);
        $this->assertSame($division, $this->teamStat->getDivision());
    }

    public function testSetAndGetPoints(): void
    {
        $points = 16;

        $result = $this->teamStat->setPoints($points);

        $this->assertSame($this->teamStat, $result);
        $this->assertEquals($points, $this->teamStat->getPoints());
    }

    public function testSetAndGetTies(): void
    {
        $ties = 2;

        $result = $this->teamStat->setTies($ties);

        $this->assertSame($this->teamStat, $result);
        $this->assertEquals($ties, $this->teamStat->getTies());
    }

    public function testSetTiesToNull(): void
    {
        $this->teamStat->setTies(2);

        $result = $this->teamStat->setTies(null);

        $this->assertSame($this->teamStat, $result);
        $this->assertNull($this->teamStat->getTies());
    }

    public function testSetAndGetWinRounds(): void
    {
        $winRounds = 24;

        $result = $this->teamStat->setWinRounds($winRounds);

        $this->assertSame($this->teamStat, $result);
        $this->assertEquals($winRounds, $this->teamStat->getWinRounds());
    }

    public function testSetAndGetLooseRounds(): void
    {
        $looseRounds = 12;

        $result = $this->teamStat->setLooseRounds($looseRounds);

        $this->assertSame($this->teamStat, $result);
        $this->assertEquals($looseRounds, $this->teamStat->getLooseRounds());
    }

    public function testCompleteTeamStatSetup(): void
    {
        $team = new Team();
        $division = new Division();

        $this->teamStat->setTeam($team);
        $this->teamStat->setDivision($division);
        $this->teamStat->setWins(8);
        $this->teamStat->setLosses(3);
        $this->teamStat->setTies(1);
        $this->teamStat->setPoints(17);
        $this->teamStat->setWinRounds(24);
        $this->teamStat->setLooseRounds(15);

        $this->assertSame($team, $this->teamStat->getTeam());
        $this->assertSame($division, $this->teamStat->getDivision());
        $this->assertEquals(8, $this->teamStat->getWins());
        $this->assertEquals(3, $this->teamStat->getLosses());
        $this->assertEquals(1, $this->teamStat->getTies());
        $this->assertEquals(17, $this->teamStat->getPoints());
        $this->assertEquals(24, $this->teamStat->getWinRounds());
        $this->assertEquals(15, $this->teamStat->getLooseRounds());
    }

    public function testZeroValues(): void
    {
        $this->teamStat->setWins(0);
        $this->teamStat->setLosses(0);
        $this->teamStat->setPoints(0);
        $this->teamStat->setWinRounds(0);
        $this->teamStat->setLooseRounds(0);

        $this->assertEquals(0, $this->teamStat->getWins());
        $this->assertEquals(0, $this->teamStat->getLosses());
        $this->assertEquals(0, $this->teamStat->getPoints());
        $this->assertEquals(0, $this->teamStat->getWinRounds());
        $this->assertEquals(0, $this->teamStat->getLooseRounds());
    }

    public function testStatisticsCalculation(): void
    {
        $this->teamStat->setWins(8);
        $this->teamStat->setLosses(3);
        $this->teamStat->setTies(1);

        $totalGames = $this->teamStat->getWins() + $this->teamStat->getLosses() + ($this->teamStat->getTies() ?? 0);
        $this->assertEquals(12, $totalGames);

        $winRatio = $this->teamStat->getWins() / max(1, $totalGames);
        $this->assertEquals(8 / 12, $winRatio);
    }

    public function testFluentInterface(): void
    {
        $team = new Team();
        $division = new Division();

        $result = $this->teamStat
            ->setTeam($team)
            ->setDivision($division)
            ->setWins(5)
            ->setLosses(2)
            ->setPoints(10);

        $this->assertSame($this->teamStat, $result);
        $this->assertSame($team, $this->teamStat->getTeam());
        $this->assertSame($division, $this->teamStat->getDivision());
        $this->assertEquals(5, $this->teamStat->getWins());
        $this->assertEquals(2, $this->teamStat->getLosses());
        $this->assertEquals(10, $this->teamStat->getPoints());
    }

    public function testNegativeRoundsHandling(): void
    {
        // Bien que normalement les rounds ne devraient pas être négatifs,
        // l'entité devrait pouvoir les stocker
        $this->teamStat->setWinRounds(-1);
        $this->teamStat->setLooseRounds(-1);

        $this->assertEquals(-1, $this->teamStat->getWinRounds());
        $this->assertEquals(-1, $this->teamStat->getLooseRounds());
    }
}
