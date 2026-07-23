<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Division;
use App\Entity\Season;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Division
 */
class DivisionTest extends TestCase
{
    private Division $division;

    protected function setUp(): void
    {
        $this->division = new Division();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->division->getId());
        $this->assertNull($this->division->getName());
        $this->assertNull($this->division->getSeason());
    }

    public function testSetAndGetName(): void
    {
        $name = 'Division A';

        $result = $this->division->setName($name);

        $this->assertSame($this->division, $result); // Test fluent interface
        $this->assertEquals($name, $this->division->getName());
    }

    public function testSetAndGetSeason(): void
    {
        $season = new Season();

        $result = $this->division->setSeason($season);

        $this->assertSame($this->division, $result); // Test fluent interface
        $this->assertSame($season, $this->division->getSeason());
    }

    public function testSetSeasonToNull(): void
    {
        $season = new Season();
        $this->division->setSeason($season);

        $result = $this->division->setSeason(null);

        $this->assertSame($this->division, $result);
        $this->assertNull($this->division->getSeason());
    }

    public function testNameWithEmptyString(): void
    {
        $name = '';

        $this->division->setName($name);

        $this->assertEquals($name, $this->division->getName());
    }

    public function testNameWithSpecialCharacters(): void
    {
        $name = 'Division Élite - Catégorie A (2024)';

        $this->division->setName($name);

        $this->assertEquals($name, $this->division->getName());
    }

    public function testNameWithMaxLength(): void
    {
        // Test avec une chaîne de 255 caractères (longueur max définie dans l'entité)
        $name = str_repeat('A', 255);

        $this->division->setName($name);

        $this->assertEquals($name, $this->division->getName());
        $this->assertEquals(255, strlen($this->division->getName()));
    }
}
