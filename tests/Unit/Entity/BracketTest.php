<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Bracket;
use App\Entity\Division;
use PHPUnit\Framework\TestCase;

class BracketTest extends TestCase
{
    public function testDefaults(): void
    {
        $bracket = new Bracket();

        $this->assertSame(Bracket::FORMAT_SINGLE_ELIMINATION, $bracket->getFormat());
        $this->assertSame(Bracket::STATUS_DRAFT, $bracket->getStatus());
        $this->assertFalse($bracket->hasThirdPlaceMatch());
        $this->assertNull($bracket->getDivision());
    }

    public function testSetters(): void
    {
        $division = new Division();
        $bracket = new Bracket();

        $bracket->setName('Playoff D1')
            ->setQualifiedCount(8)
            ->setHasThirdPlaceMatch(true)
            ->setDivision($division)
            ->setStatus(Bracket::STATUS_READY);

        $this->assertSame('Playoff D1', $bracket->getName());
        $this->assertSame(8, $bracket->getQualifiedCount());
        $this->assertTrue($bracket->hasThirdPlaceMatch());
        $this->assertSame($division, $bracket->getDivision());
        $this->assertSame(Bracket::STATUS_READY, $bracket->getStatus());
    }
}
