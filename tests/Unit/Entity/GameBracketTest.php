<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Bracket;
use App\Entity\Game;
use PHPUnit\Framework\TestCase;

class GameBracketTest extends TestCase
{
    public function testBracketFieldsDefaultToNullOrFalse(): void
    {
        $game = new Game();

        $this->assertNull($game->getBracket());
        $this->assertNull($game->getRound());
        $this->assertNull($game->getBracketPosition());
        $this->assertFalse($game->isThirdPlaceMatch());
        $this->assertNull($game->getWinnerToGame());
        $this->assertNull($game->getWinnerToSlot());
        $this->assertNull($game->getLoserToGame());
        $this->assertNull($game->getLoserToSlot());
    }

    public function testBracketProgressionWiringSetters(): void
    {
        $game = new Game();
        $next = new Game();

        $game->setRound(1)
            ->setBracketPosition(0)
            ->setIsThirdPlaceMatch(true)
            ->setWinnerToGame($next)
            ->setWinnerToSlot(1)
            ->setLoserToGame($next)
            ->setLoserToSlot(2);

        $this->assertSame(1, $game->getRound());
        $this->assertSame(0, $game->getBracketPosition());
        $this->assertTrue($game->isThirdPlaceMatch());
        $this->assertSame($next, $game->getWinnerToGame());
        $this->assertSame(1, $game->getWinnerToSlot());
        $this->assertSame($next, $game->getLoserToGame());
        $this->assertSame(2, $game->getLoserToSlot());
    }
}
