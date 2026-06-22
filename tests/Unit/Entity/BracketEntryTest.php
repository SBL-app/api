<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Bracket;
use App\Entity\BracketEntry;
use App\Entity\Team;
use PHPUnit\Framework\TestCase;

class BracketEntryTest extends TestCase
{
    public function testSetters(): void
    {
        $bracket = new Bracket();
        $team = new Team();
        $entry = new BracketEntry();

        $entry->setBracket($bracket)
            ->setSeed(3)
            ->setTeam($team);

        $this->assertSame($bracket, $entry->getBracket());
        $this->assertSame(3, $entry->getSeed());
        $this->assertSame($team, $entry->getTeam());
    }
}
