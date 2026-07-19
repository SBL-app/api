<?php

namespace App\Tests\Entity;

use App\Entity\Registration;
use App\Entity\Season;
use PHPUnit\Framework\TestCase;

final class SeasonTest extends TestCase
{
    public function testNameAccessors(): void
    {
        $season = new Season();
        self::assertNull($season->getName());
        self::assertSame($season, $season->setName('Saison I'));
        self::assertSame('Saison I', $season->getName());
    }

    public function testDateAccessors(): void
    {
        $season = new Season();
        $start = new \DateTimeImmutable('2022-03-21');
        $end = new \DateTimeImmutable('2022-05-02');

        $season->setStartDate($start)->setEndDate($end);

        self::assertSame($start, $season->getStartDate());
        self::assertSame($end, $season->getEndDate());
    }

    public function testRegistrationsCollectionStartsEmpty(): void
    {
        $season = new Season();
        self::assertCount(0, $season->getTeam());
    }

    public function testAddAndRemoveTeamMaintainsBothSides(): void
    {
        $season = new Season();
        $registration = new Registration();

        $season->addTeam($registration);
        self::assertCount(1, $season->getTeam());
        self::assertSame($season, $registration->getSeason());

        // Ajouter deux fois la même inscription ne crée pas de doublon.
        $season->addTeam($registration);
        self::assertCount(1, $season->getTeam());

        $season->removeTeam($registration);
        self::assertCount(0, $season->getTeam());
        self::assertNull($registration->getSeason());
    }
}
