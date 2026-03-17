<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Game;
use App\Entity\MatchResult;
use App\Entity\Team;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class MatchResultTest extends TestCase
{
    public function testIdIsNullByDefault(): void
    {
        $result = new MatchResult();

        $this->assertNull($result->getId());
    }

    public function testSetAndGetGame(): void
    {
        $result = new MatchResult();
        $game = new Game();

        $returnValue = $result->setGame($game);

        $this->assertSame($game, $result->getGame());
        $this->assertSame($result, $returnValue, 'setGame should return fluent interface');
    }

    public function testSetAndGetSubmittedBy(): void
    {
        $result = new MatchResult();
        $user = new User();
        $user->setUsername('testuser');

        $returnValue = $result->setSubmittedBy($user);

        $this->assertSame($user, $result->getSubmittedBy());
        $this->assertSame($result, $returnValue, 'setSubmittedBy should return fluent interface');
    }

    public function testSetAndGetTeam(): void
    {
        $result = new MatchResult();
        $team = new Team();
        $team->setName('Test Team');

        $returnValue = $result->setTeam($team);

        $this->assertSame($team, $result->getTeam());
        $this->assertSame($result, $returnValue, 'setTeam should return fluent interface');
    }

    public function testSetAndGetScore1(): void
    {
        $result = new MatchResult();

        $returnValue = $result->setScore1(3);

        $this->assertEquals(3, $result->getScore1());
        $this->assertSame($result, $returnValue, 'setScore1 should return fluent interface');
    }

    public function testSetAndGetScore2(): void
    {
        $result = new MatchResult();

        $returnValue = $result->setScore2(2);

        $this->assertEquals(2, $result->getScore2());
        $this->assertSame($result, $returnValue, 'setScore2 should return fluent interface');
    }

    public function testStatusDefaultsToPending(): void
    {
        $result = new MatchResult();

        $this->assertSame('pending', $result->getStatus());
        $this->assertTrue($result->isPending());
    }

    public function testSetAndGetStatus(): void
    {
        $result = new MatchResult();

        $returnValue = $result->setStatus('validated');

        $this->assertSame('validated', $result->getStatus());
        $this->assertSame($result, $returnValue, 'setStatus should return fluent interface');
    }

    public function testContestReasonIsNullByDefault(): void
    {
        $result = new MatchResult();

        $this->assertNull($result->getContestReason());
    }

    public function testSetAndGetContestReason(): void
    {
        $result = new MatchResult();

        $returnValue = $result->setContestReason('Score is incorrect');

        $this->assertEquals('Score is incorrect', $result->getContestReason());
        $this->assertSame($result, $returnValue, 'setContestReason should return fluent interface');
    }

    public function testValidatedAtIsNullByDefault(): void
    {
        $result = new MatchResult();

        $this->assertNull($result->getValidatedAt());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $result = new MatchResult();
        $after = new \DateTimeImmutable();

        $createdAt = $result->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertGreaterThanOrEqual($before, $createdAt);
        $this->assertLessThanOrEqual($after, $createdAt);
    }

    public function testIsPending(): void
    {
        $result = new MatchResult();

        $result->setStatus(MatchResult::STATUS_PENDING);
        $this->assertTrue($result->isPending());

        $result->setStatus(MatchResult::STATUS_VALIDATED);
        $this->assertFalse($result->isPending());
    }

    public function testIsValidated(): void
    {
        $result = new MatchResult();

        $result->setStatus(MatchResult::STATUS_VALIDATED);
        $this->assertTrue($result->isValidated());

        $result->setStatus(MatchResult::STATUS_PENDING);
        $this->assertFalse($result->isValidated());
    }

    public function testIsContested(): void
    {
        $result = new MatchResult();

        $result->setStatus(MatchResult::STATUS_CONTESTED);
        $this->assertTrue($result->isContested());

        $result->setStatus(MatchResult::STATUS_PENDING);
        $this->assertFalse($result->isContested());
    }

    public function testValidateMethod(): void
    {
        $result = new MatchResult();

        $before = new \DateTimeImmutable();
        $returnValue = $result->validate();
        $after = new \DateTimeImmutable();

        $this->assertSame(MatchResult::STATUS_VALIDATED, $result->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->getValidatedAt());
        $this->assertGreaterThanOrEqual($before, $result->getValidatedAt());
        $this->assertLessThanOrEqual($after, $result->getValidatedAt());
        $this->assertSame($result, $returnValue, 'validate should return fluent interface');
    }

    public function testContestMethod(): void
    {
        $result = new MatchResult();

        $returnValue = $result->contest('Score is wrong');

        $this->assertSame(MatchResult::STATUS_CONTESTED, $result->getStatus());
        $this->assertSame('Score is wrong', $result->getContestReason());
        $this->assertSame($result, $returnValue, 'contest should return fluent interface');
    }
}
