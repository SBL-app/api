<?php

namespace App\Tests\Entity;

use App\Entity\GameStatus;
use PHPUnit\Framework\TestCase;

final class GameStatusTest extends TestCase
{
    public function testNameAccessors(): void
    {
        $status = new GameStatus();
        self::assertNull($status->getName());
        self::assertSame($status, $status->setName('à jouer'));
        self::assertSame('à jouer', $status->getName());
    }

    public function testIdIsNullBeforePersistence(): void
    {
        self::assertNull((new GameStatus())->getId());
    }
}
