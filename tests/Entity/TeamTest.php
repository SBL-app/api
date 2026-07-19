<?php

namespace App\Tests\Entity;

use App\Entity\Player;
use App\Entity\Registration;
use App\Entity\Team;
use PHPUnit\Framework\TestCase;

final class TeamTest extends TestCase
{
    public function testNameAccessors(): void
    {
        $team = new Team();
        self::assertSame($team, $team->setName('Les Baguettes'));
        self::assertSame('Les Baguettes', $team->getName());
    }

    public function testCaptainRelation(): void
    {
        $team = new Team();
        $player = (new Player())->setName('Capitaine');

        self::assertNull($team->getCapitain());
        $team->setCapitain($player);
        self::assertSame($player, $team->getCapitain());
    }

    public function testRegistrationsCollection(): void
    {
        $team = new Team();
        self::assertCount(0, $team->getRegistrations());

        $registration = new Registration();
        $team->addRegistration($registration);
        self::assertCount(1, $team->getRegistrations());
        self::assertTrue($team->getRegistrations()->contains($registration));
    }
}
