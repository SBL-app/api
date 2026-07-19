<?php

namespace App\Tests\Entity;

use App\Entity\Player;
use App\Entity\Team;
use PHPUnit\Framework\TestCase;

final class PlayerTest extends TestCase
{
    public function testScalarAccessors(): void
    {
        $player = new Player();
        $player->setName('Alex')->setDiscord('alex#1234');

        self::assertSame('Alex', $player->getName());
        self::assertSame('alex#1234', $player->getDiscord());
    }

    public function testDiscordIsNullable(): void
    {
        $player = new Player();
        $player->setDiscord(null);
        self::assertNull($player->getDiscord());
    }

    public function testTeamRelation(): void
    {
        $player = new Player();
        $team = (new Team())->setName('Équipe A');

        $player->setTeam($team);
        self::assertSame($team, $player->getTeam());
    }
}
