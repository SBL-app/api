<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Game;
use App\Entity\Team;
use App\Entity\GameStatus;
use App\Entity\Division;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Game
 */
class GameTest extends TestCase
{
    private Game $game;

    protected function setUp(): void
    {
        $this->game = new Game();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->game->getId());
        $this->assertNull($this->game->getDate());
        $this->assertNull($this->game->getWeek());
        $this->assertNull($this->game->getTeam1());
        $this->assertNull($this->game->getTeam2());
        $this->assertNull($this->game->getScore1());
        $this->assertNull($this->game->getScore2());
        $this->assertNull($this->game->getWinner());
        $this->assertNull($this->game->getStatus());
        $this->assertNull($this->game->getDivision());
    }

    public function testSetAndGetDate(): void
    {
        $date = new \DateTime('2024-09-15 14:30:00');

        $result = $this->game->setDate($date);

        $this->assertSame($this->game, $result);
        $this->assertEquals($date, $this->game->getDate());
    }

    public function testSetDateToNull(): void
    {
        $date = new \DateTime('2024-09-15 14:30:00');
        $this->game->setDate($date);

        $result = $this->game->setDate(null);

        $this->assertSame($this->game, $result);
        $this->assertNull($this->game->getDate());
    }

    public function testSetAndGetWeek(): void
    {
        $week = 5;

        $result = $this->game->setWeek($week);

        $this->assertSame($this->game, $result);
        $this->assertEquals($week, $this->game->getWeek());
    }

    public function testSetAndGetTeam1(): void
    {
        $team = new Team();

        $result = $this->game->setTeam1($team);

        $this->assertSame($this->game, $result);
        $this->assertSame($team, $this->game->getTeam1());
    }

    public function testSetTeam1ToNull(): void
    {
        $team = new Team();
        $this->game->setTeam1($team);

        $result = $this->game->setTeam1(null);

        $this->assertSame($this->game, $result);
        $this->assertNull($this->game->getTeam1());
    }

    public function testSetAndGetTeam2(): void
    {
        $team = new Team();

        $result = $this->game->setTeam2($team);

        $this->assertSame($this->game, $result);
        $this->assertSame($team, $this->game->getTeam2());
    }

    public function testSetAndGetScore1(): void
    {
        $score = 3;

        $result = $this->game->setScore1($score);

        $this->assertSame($this->game, $result);
        $this->assertEquals($score, $this->game->getScore1());
    }

    public function testSetAndGetScore2(): void
    {
        $score = 1;

        $result = $this->game->setScore2($score);

        $this->assertSame($this->game, $result);
        $this->assertEquals($score, $this->game->getScore2());
    }

    public function testSetAndGetWinner(): void
    {
        $winner = 1;

        $result = $this->game->setWinner($winner);

        $this->assertSame($this->game, $result);
        $this->assertEquals($winner, $this->game->getWinner());
    }

    public function testSetWinnerToNull(): void
    {
        $this->game->setWinner(1);

        $result = $this->game->setWinner(null);

        $this->assertSame($this->game, $result);
        $this->assertNull($this->game->getWinner());
    }

    public function testSetAndGetStatus(): void
    {
        $status = new GameStatus();

        $result = $this->game->setStatus($status);

        $this->assertSame($this->game, $result);
        $this->assertSame($status, $this->game->getStatus());
    }

    public function testSetAndGetDivision(): void
    {
        $division = new Division();

        $result = $this->game->setDivision($division);

        $this->assertSame($this->game, $result);
        $this->assertSame($division, $this->game->getDivision());
    }

    public function testCompleteGameScenario(): void
    {
        $team1 = new Team();
        $team2 = new Team();
        $status = new GameStatus();
        $division = new Division();
        $date = new \DateTime('2024-09-15 14:30:00');

        $this->game->setTeam1($team1);
        $this->game->setTeam2($team2);
        $this->game->setScore1(3);
        $this->game->setScore2(1);
        $this->game->setWinner(1);
        $this->game->setStatus($status);
        $this->game->setDivision($division);
        $this->game->setDate($date);
        $this->game->setWeek(5);

        $this->assertSame($team1, $this->game->getTeam1());
        $this->assertSame($team2, $this->game->getTeam2());
        $this->assertEquals(3, $this->game->getScore1());
        $this->assertEquals(1, $this->game->getScore2());
        $this->assertEquals(1, $this->game->getWinner());
        $this->assertSame($status, $this->game->getStatus());
        $this->assertSame($division, $this->game->getDivision());
        $this->assertEquals($date, $this->game->getDate());
        $this->assertEquals(5, $this->game->getWeek());
    }

    public function testScoreValidation(): void
    {
        $this->game->setScore1(0);
        $this->game->setScore2(0);

        $this->assertEquals(0, $this->game->getScore1());
        $this->assertEquals(0, $this->game->getScore2());
    }

    public function testWinnerValues(): void
    {
        $validWinners = [1, 2, null]; // null pour match nul

        foreach ($validWinners as $winner) {
            $this->game->setWinner($winner);
            $this->assertEquals($winner, $this->game->getWinner());
        }
    }

    public function testWeekValidation(): void
    {
        $weeks = [1, 10, 52];

        foreach ($weeks as $week) {
            $this->game->setWeek($week);
            $this->assertEquals($week, $this->game->getWeek());
        }
    }

    public function testDateTimeHandling(): void
    {
        $date = new \DateTime('2024-09-15 14:30:00');
        $this->game->setDate($date);

        $retrievedDate = $this->game->getDate();
        $this->assertEquals($date->format('Y-m-d H:i:s'), $retrievedDate->format('Y-m-d H:i:s'));
    }
}
