<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Player;
use App\Entity\Team;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Player
 */
class PlayerTest extends TestCase
{
    private Player $player;

    protected function setUp(): void
    {
        $this->player = new Player();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->player->getId());
        $this->assertNull($this->player->getName());
        $this->assertNull($this->player->getDiscord());
        $this->assertNull($this->player->getTeam());
    }

    public function testSetAndGetName(): void
    {
        $name = 'John Doe';

        $result = $this->player->setName($name);

        $this->assertSame($this->player, $result); // Test fluent interface
        $this->assertEquals($name, $this->player->getName());
    }

    public function testSetAndGetDiscord(): void
    {
        $discord = 'johndoe#1234';

        $result = $this->player->setDiscord($discord);

        $this->assertSame($this->player, $result);
        $this->assertEquals($discord, $this->player->getDiscord());
    }

    public function testSetDiscordToNull(): void
    {
        $discord = 'johndoe#1234';
        $this->player->setDiscord($discord);

        $result = $this->player->setDiscord(null);

        $this->assertSame($this->player, $result);
        $this->assertNull($this->player->getDiscord());
    }

    public function testSetAndGetTeam(): void
    {
        $team = new Team();

        $result = $this->player->setTeam($team);

        $this->assertSame($this->player, $result);
        $this->assertSame($team, $this->player->getTeam());
    }

    public function testSetTeamToNull(): void
    {
        $team = new Team();
        $this->player->setTeam($team);

        $result = $this->player->setTeam(null);

        $this->assertSame($this->player, $result);
        $this->assertNull($this->player->getTeam());
    }

    public function testNameWithSpecialCharacters(): void
    {
        $name = 'Jean-Claude Müller & Co.';

        $this->player->setName($name);

        $this->assertEquals($name, $this->player->getName());
    }

    public function testDiscordWithVariousFormats(): void
    {
        $discordFormats = [
            'user#1234',
            'test_user#9999',
            'Player.Name#0001',
            'français#2345'
        ];

        foreach ($discordFormats as $discord) {
            $this->player->setDiscord($discord);
            $this->assertEquals($discord, $this->player->getDiscord());
        }
    }

    public function testNameWithMaxLength(): void
    {
        $name = str_repeat('A', 255);

        $this->player->setName($name);

        $this->assertEquals($name, $this->player->getName());
        $this->assertEquals(255, strlen($this->player->getName()));
    }

    public function testDiscordWithMaxLength(): void
    {
        $discord = str_repeat('A', 250) . '#1234'; // Discord max length

        $this->player->setDiscord($discord);

        $this->assertEquals($discord, $this->player->getDiscord());
    }
}
