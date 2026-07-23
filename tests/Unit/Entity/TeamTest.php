<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Team;
use App\Entity\Player;
use App\Entity\Registration;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Team
 */
class TeamTest extends TestCase
{
    private Team $team;

    protected function setUp(): void
    {
        $this->team = new Team();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->team->getId());
        $this->assertNull($this->team->getName());
        $this->assertNull($this->team->getCaptain());
        $this->assertCount(0, $this->team->getRegistrations());
    }

    public function testSetAndGetName(): void
    {
        $name = 'Les Dragons';

        $result = $this->team->setName($name);

        $this->assertSame($this->team, $result);
        $this->assertEquals($name, $this->team->getName());
    }

    public function testSetAndGetCaptain(): void
    {
        $captain = new Player();

        $result = $this->team->setCaptain($captain);

        $this->assertSame($this->team, $result);
        $this->assertSame($captain, $this->team->getCaptain());
    }

    public function testSetCaptainToNull(): void
    {
        $captain = new Player();
        $this->team->setCaptain($captain);

        $result = $this->team->setCaptain(null);

        $this->assertSame($this->team, $result);
        $this->assertNull($this->team->getCaptain());
    }

    public function testAddRegistration(): void
    {
        $registration = new Registration();

        $result = $this->team->addRegistration($registration);

        $this->assertSame($this->team, $result);
        $this->assertCount(1, $this->team->getRegistrations());
        $this->assertTrue($this->team->getRegistrations()->contains($registration));
        $this->assertSame($this->team, $registration->getTeam());
    }

    public function testAddSameRegistrationTwice(): void
    {
        $registration = new Registration();

        $this->team->addRegistration($registration);
        $this->team->addRegistration($registration); // Ajouter la même registration

        $this->assertCount(1, $this->team->getRegistrations());
    }

    public function testRemoveRegistration(): void
    {
        $registration = new Registration();
        $this->team->addRegistration($registration);

        $result = $this->team->removeRegistration($registration);

        $this->assertSame($this->team, $result);
        $this->assertCount(0, $this->team->getRegistrations());
        $this->assertFalse($this->team->getRegistrations()->contains($registration));
    }

    public function testRemoveNonExistentRegistration(): void
    {
        $registration1 = new Registration();
        $registration2 = new Registration();

        $this->team->addRegistration($registration1);
        $result = $this->team->removeRegistration($registration2);

        $this->assertSame($this->team, $result);
        $this->assertCount(1, $this->team->getRegistrations());
        $this->assertTrue($this->team->getRegistrations()->contains($registration1));
    }

    public function testMultipleRegistrations(): void
    {
        $registration1 = new Registration();
        $registration2 = new Registration();
        $registration3 = new Registration();

        $this->team->addRegistration($registration1);
        $this->team->addRegistration($registration2);
        $this->team->addRegistration($registration3);

        $this->assertCount(3, $this->team->getRegistrations());
        $this->assertTrue($this->team->getRegistrations()->contains($registration1));
        $this->assertTrue($this->team->getRegistrations()->contains($registration2));
        $this->assertTrue($this->team->getRegistrations()->contains($registration3));
    }

    public function testNameWithSpecialCharacters(): void
    {
        $name = 'Les Étoiles Noires & Co. (Équipe #1)';

        $this->team->setName($name);

        $this->assertEquals($name, $this->team->getName());
    }

    public function testNameWithMaxLength(): void
    {
        $name = str_repeat('A', 255);

        $this->team->setName($name);

        $this->assertEquals($name, $this->team->getName());
        $this->assertEquals(255, strlen($this->team->getName()));
    }
}
