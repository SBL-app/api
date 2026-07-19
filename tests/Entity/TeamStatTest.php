<?php

namespace App\Tests\Entity;

use App\Entity\Division;
use App\Entity\Team;
use App\Entity\TeamStat;
use PHPUnit\Framework\TestCase;

final class TeamStatTest extends TestCase
{
    public function testStatAccessors(): void
    {
        $stat = new TeamStat();
        $stat->setWins(5)
            ->setLosses(2)
            ->setTies(1)
            ->setPoints(16)
            ->setWinRounds(30)
            ->setLooseRounds(18);

        self::assertSame(5, $stat->getWins());
        self::assertSame(2, $stat->getLosses());
        self::assertSame(1, $stat->getTies());
        self::assertSame(16, $stat->getPoints());
        self::assertSame(30, $stat->getWinRounds());
        self::assertSame(18, $stat->getLooseRounds());
    }

    public function testRelations(): void
    {
        $stat = new TeamStat();
        $team = (new Team())->setName('A');
        $division = (new Division())->setName('D1');

        $stat->setTeam($team)->setDivision($division);

        self::assertSame($team, $stat->getTeam());
        self::assertSame($division, $stat->getDivision());
    }
}
