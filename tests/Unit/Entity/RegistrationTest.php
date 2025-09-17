<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Registration;
use App\Entity\Season;
use App\Entity\Team;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité Registration
 */
class RegistrationTest extends TestCase
{
    private Registration $registration;

    protected function setUp(): void
    {
        $this->registration = new Registration();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->registration->getId());
        $this->assertNull($this->registration->getSeason());
        $this->assertNull($this->registration->getTeam());
    }

    public function testSetAndGetSeason(): void
    {
        $season = new Season();

        $result = $this->registration->setSeason($season);

        $this->assertSame($this->registration, $result); // Test fluent interface
        $this->assertSame($season, $this->registration->getSeason());
    }

    public function testSetSeasonToNull(): void
    {
        $season = new Season();
        $this->registration->setSeason($season);

        $result = $this->registration->setSeason(null);

        $this->assertSame($this->registration, $result);
        $this->assertNull($this->registration->getSeason());
    }

    public function testSetAndGetTeam(): void
    {
        $team = new Team();

        $result = $this->registration->setTeam($team);

        $this->assertSame($this->registration, $result);
        $this->assertSame($team, $this->registration->getTeam());
    }

    public function testSetTeamToNull(): void
    {
        $team = new Team();
        $this->registration->setTeam($team);

        $result = $this->registration->setTeam(null);

        $this->assertSame($this->registration, $result);
        $this->assertNull($this->registration->getTeam());
    }

    public function testCompleteRegistration(): void
    {
        $season = new Season();
        $team = new Team();

        $this->registration->setSeason($season);
        $this->registration->setTeam($team);

        $this->assertSame($season, $this->registration->getSeason());
        $this->assertSame($team, $this->registration->getTeam());
    }

    public function testRegistrationWithDifferentSeasons(): void
    {
        $season1 = new Season();
        $season2 = new Season();

        $this->registration->setSeason($season1);
        $this->assertSame($season1, $this->registration->getSeason());

        $this->registration->setSeason($season2);
        $this->assertSame($season2, $this->registration->getSeason());
    }

    public function testRegistrationWithDifferentTeams(): void
    {
        $team1 = new Team();
        $team2 = new Team();

        $this->registration->setTeam($team1);
        $this->assertSame($team1, $this->registration->getTeam());

        $this->registration->setTeam($team2);
        $this->assertSame($team2, $this->registration->getTeam());
    }

    public function testFluentInterface(): void
    {
        $season = new Season();
        $team = new Team();

        $result = $this->registration
            ->setSeason($season)
            ->setTeam($team);

        $this->assertSame($this->registration, $result);
        $this->assertSame($season, $this->registration->getSeason());
        $this->assertSame($team, $this->registration->getTeam());
    }
}
