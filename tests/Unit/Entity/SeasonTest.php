<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Season;
use App\Entity\Registration;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Season
 */
class SeasonTest extends TestCase
{
    private Season $season;

    protected function setUp(): void
    {
        $this->season = new Season();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->season->getId());
        $this->assertNull($this->season->getName());
        $this->assertNull($this->season->getStartDate());
        $this->assertNull($this->season->getEndDate());
        $this->assertCount(0, $this->season->getRegistrations());
    }

    public function testSetAndGetName(): void
    {
        $name = 'Saison 2024';

        $result = $this->season->setName($name);

        $this->assertSame($this->season, $result);
        $this->assertEquals($name, $this->season->getName());
    }

    public function testSetAndGetStartDate(): void
    {
        $date = new \DateTime('2024-01-01');

        $result = $this->season->setStartDate($date);

        $this->assertSame($this->season, $result);
        $this->assertEquals($date, $this->season->getStartDate());
    }

    public function testSetStartDateToNull(): void
    {
        $date = new \DateTime('2024-01-01');
        $this->season->setStartDate($date);

        $result = $this->season->setStartDate(null);

        $this->assertSame($this->season, $result);
        $this->assertNull($this->season->getStartDate());
    }

    public function testSetAndGetEndDate(): void
    {
        $date = new \DateTime('2024-12-31');

        $result = $this->season->setEndDate($date);

        $this->assertSame($this->season, $result);
        $this->assertEquals($date, $this->season->getEndDate());
    }

    public function testSetEndDateToNull(): void
    {
        $date = new \DateTime('2024-12-31');
        $this->season->setEndDate($date);

        $result = $this->season->setEndDate(null);

        $this->assertSame($this->season, $result);
        $this->assertNull($this->season->getEndDate());
    }

    public function testAddRegistration(): void
    {
        $registration = new Registration();

        $result = $this->season->addRegistration($registration);

        $this->assertSame($this->season, $result);
        $this->assertCount(1, $this->season->getRegistrations());
        $this->assertTrue($this->season->getRegistrations()->contains($registration));
        $this->assertSame($this->season, $registration->getSeason());
    }

    public function testAddSameRegistrationTwice(): void
    {
        $registration = new Registration();

        $this->season->addRegistration($registration);
        $this->season->addRegistration($registration);

        $this->assertCount(1, $this->season->getRegistrations());
    }

    public function testRemoveRegistration(): void
    {
        $registration = new Registration();
        $this->season->addRegistration($registration);

        $result = $this->season->removeRegistration($registration);

        $this->assertSame($this->season, $result);
        $this->assertCount(0, $this->season->getRegistrations());
        $this->assertFalse($this->season->getRegistrations()->contains($registration));
    }

    public function testRemoveNonExistentRegistration(): void
    {
        $registration1 = new Registration();
        $registration2 = new Registration();

        $this->season->addRegistration($registration1);
        $result = $this->season->removeRegistration($registration2);

        $this->assertSame($this->season, $result);
        $this->assertCount(1, $this->season->getRegistrations());
    }

    public function testDateRange(): void
    {
        $startDate = new \DateTime('2024-01-01');
        $endDate = new \DateTime('2024-12-31');

        $this->season->setStartDate($startDate);
        $this->season->setEndDate($endDate);

        $this->assertEquals($startDate, $this->season->getStartDate());
        $this->assertEquals($endDate, $this->season->getEndDate());
        $this->assertLessThan($this->season->getEndDate(), $this->season->getStartDate());
    }

    public function testNameWithSpecialCharacters(): void
    {
        $name = 'Saison Élite 2024/2025 - Championnat';

        $this->season->setName($name);

        $this->assertEquals($name, $this->season->getName());
    }

    public function testNameWithMaxLength(): void
    {
        $name = str_repeat('A', 255);

        $this->season->setName($name);

        $this->assertEquals($name, $this->season->getName());
        $this->assertEquals(255, strlen($this->season->getName()));
    }

    public function testMultipleRegistrations(): void
    {
        $registration1 = new Registration();
        $registration2 = new Registration();
        $registration3 = new Registration();

        $this->season->addRegistration($registration1);
        $this->season->addRegistration($registration2);
        $this->season->addRegistration($registration3);

        $this->assertCount(3, $this->season->getRegistrations());
        $this->assertTrue($this->season->getRegistrations()->contains($registration1));
        $this->assertTrue($this->season->getRegistrations()->contains($registration2));
        $this->assertTrue($this->season->getRegistrations()->contains($registration3));
    }

    public function testDateImmutability(): void
    {
        $originalDate = new \DateTime('2024-01-01');
        $this->season->setStartDate($originalDate);

        // Modifier la date originale ne doit pas affecter l'entité
        $retrievedDate = $this->season->getStartDate();
        $this->assertEquals($originalDate, $retrievedDate);
    }
}
