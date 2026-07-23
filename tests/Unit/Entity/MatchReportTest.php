<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Game;
use App\Entity\MatchReport;
use App\Entity\Team;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class MatchReportTest extends TestCase
{
    public function testIdIsNullByDefault(): void
    {
        $report = new MatchReport();

        $this->assertNull($report->getId());
    }

    public function testSetAndGetGame(): void
    {
        $report = new MatchReport();
        $game = new Game();

        $result = $report->setGame($game);

        $this->assertSame($game, $report->getGame());
        $this->assertSame($report, $result, 'setGame should return fluent interface');
    }

    public function testSetAndGetTeam(): void
    {
        $report = new MatchReport();
        $team = new Team();
        $team->setName('Test Team');

        $result = $report->setTeam($team);

        $this->assertSame($team, $report->getTeam());
        $this->assertSame($report, $result, 'setTeam should return fluent interface');
    }

    public function testSetAndGetRequestedBy(): void
    {
        $report = new MatchReport();
        $user = new User();
        $user->setUsername('testuser');

        $result = $report->setRequestedBy($user);

        $this->assertSame($user, $report->getRequestedBy());
        $this->assertSame($report, $result, 'setRequestedBy should return fluent interface');
    }

    public function testSetAndGetReason(): void
    {
        $report = new MatchReport();

        $this->assertNull($report->getReason(), 'Reason should be null by default');

        $result = $report->setReason('Player unavailable');

        $this->assertEquals('Player unavailable', $report->getReason());
        $this->assertSame($report, $result, 'setReason should return fluent interface');

        $report->setReason(null);
        $this->assertNull($report->getReason(), 'Reason should be nullable');
    }

    public function testIsAdminForcedDefaultsFalse(): void
    {
        $report = new MatchReport();

        $this->assertFalse($report->isAdminForced());
    }

    public function testSetAndGetIsAdminForced(): void
    {
        $report = new MatchReport();

        $result = $report->setIsAdminForced(true);

        $this->assertTrue($report->isAdminForced());
        $this->assertSame($report, $result, 'setIsAdminForced should return fluent interface');

        $report->setIsAdminForced(false);
        $this->assertFalse($report->isAdminForced());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $report = new MatchReport();
        $after = new \DateTimeImmutable();

        $createdAt = $report->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertGreaterThanOrEqual($before, $createdAt);
        $this->assertLessThanOrEqual($after, $createdAt);
    }
}
