<?php

namespace App\Tests\Unit\Entity;

use App\Entity\GameStatus;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité GameStatus
 */
class GameStatusTest extends TestCase
{
    private GameStatus $gameStatus;

    protected function setUp(): void
    {
        $this->gameStatus = new GameStatus();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->gameStatus->getId());
        $this->assertNull($this->gameStatus->getName());
    }

    public function testSetAndGetName(): void
    {
        $name = 'En cours';

        $result = $this->gameStatus->setName($name);

        $this->assertSame($this->gameStatus, $result); // Test fluent interface
        $this->assertEquals($name, $this->gameStatus->getName());
    }

    public function testNameWithSpecialCharacters(): void
    {
        $name = 'Terminé - Validé';

        $this->gameStatus->setName($name);

        $this->assertEquals($name, $this->gameStatus->getName());
    }

    public function testCommonStatusNames(): void
    {
        $statusNames = [
            'Programmé',
            'En cours',
            'Terminé',
            'Annulé',
            'Reporté',
            'En attente'
        ];

        foreach ($statusNames as $name) {
            $this->gameStatus->setName($name);
            $this->assertEquals($name, $this->gameStatus->getName());
        }
    }

    public function testNameWithMaxLength(): void
    {
        $name = str_repeat('A', 255);

        $this->gameStatus->setName($name);

        $this->assertEquals($name, $this->gameStatus->getName());
        $this->assertEquals(255, strlen($this->gameStatus->getName()));
    }

    public function testNameWithEmptyString(): void
    {
        $name = '';

        $this->gameStatus->setName($name);

        $this->assertEquals($name, $this->gameStatus->getName());
    }

    public function testNameWithNumbers(): void
    {
        $name = 'Status123';

        $this->gameStatus->setName($name);

        $this->assertEquals($name, $this->gameStatus->getName());
    }

    public function testNameWithUnicodeCharacters(): void
    {
        $name = 'Statut Français Élémentaire';

        $this->gameStatus->setName($name);

        $this->assertEquals($name, $this->gameStatus->getName());
    }
}
