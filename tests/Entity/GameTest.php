<?php

namespace App\Tests\Entity;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\Team;
use PHPUnit\Framework\TestCase;

final class GameTest extends TestCase
{
    public function testScoreAndWeekAccessors(): void
    {
        $game = new Game();
        $game->setWeek(3)->setScore1(2)->setScore2(1)->setWinner(1);

        self::assertSame(3, $game->getWeek());
        self::assertSame(2, $game->getScore1());
        self::assertSame(1, $game->getScore2());
        self::assertSame(1, $game->getWinner());
    }

    public function testDateAccessor(): void
    {
        $game = new Game();
        $date = new \DateTimeImmutable('2025-08-26 21:00:00');
        $game->setDate($date);
        self::assertSame($date, $game->getDate());
    }

    public function testRelations(): void
    {
        $game = new Game();
        $team1 = (new Team())->setName('A');
        $team2 = (new Team())->setName('B');
        $status = (new GameStatus())->setName('joué');
        $division = (new Division())->setName('D1');

        $game->setTeam1($team1)->setTeam2($team2)->setStatus($status)->setDivision($division);

        self::assertSame($team1, $game->getTeam1());
        self::assertSame($team2, $game->getTeam2());
        self::assertSame($status, $game->getStatus());
        self::assertSame($division, $game->getDivision());
    }

    public function testNullableFieldsDefaultToNull(): void
    {
        $game = new Game();
        self::assertNull($game->getId());
        self::assertNull($game->getDate());
        self::assertNull($game->getWinner());
    }
}
