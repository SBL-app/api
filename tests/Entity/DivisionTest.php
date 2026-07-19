<?php

namespace App\Tests\Entity;

use App\Entity\Division;
use App\Entity\Season;
use PHPUnit\Framework\TestCase;

final class DivisionTest extends TestCase
{
    public function testNameAccessors(): void
    {
        $division = new Division();
        self::assertNull($division->getName());
        self::assertSame($division, $division->setName('Division 1'));
        self::assertSame('Division 1', $division->getName());
    }

    public function testSeasonRelation(): void
    {
        $division = new Division();
        $season = (new Season())->setName('Saison II');

        self::assertNull($division->getSeason());
        $division->setSeason($season);
        self::assertSame($season, $division->getSeason());

        $division->setSeason(null);
        self::assertNull($division->getSeason());
    }

    public function testIdIsNullBeforePersistence(): void
    {
        self::assertNull((new Division())->getId());
    }
}
