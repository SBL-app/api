<?php

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Game;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Message\MatchReminderMessage;
use App\MessageHandler\MatchReminderHandler;
use App\Repository\GameRepository;
use App\Service\PushNotificationService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MatchReminderHandlerTest extends TestCase
{
    private GameRepository $gameRepository;
    private PushNotificationService $pushNotificationService;
    private EntityManagerInterface $entityManager;
    private MatchReminderHandler $handler;

    protected function setUp(): void
    {
        $this->gameRepository = $this->createMock(GameRepository::class);
        $this->pushNotificationService = $this->createMock(PushNotificationService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->handler = new MatchReminderHandler(
            $this->gameRepository,
            $this->pushNotificationService,
            $this->entityManager,
            new NullLogger(),
        );
    }

    public function testInvokeWithNoGames(): void
    {
        $this->gameRepository
            ->expects($this->once())
            ->method('findGamesForReminder')
            ->willReturn([]);

        $this->pushNotificationService
            ->expects($this->never())
            ->method('sendToUsers');

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        ($this->handler)(new MatchReminderMessage());
    }

    public function testInvokeWithGames(): void
    {
        $user1 = new User();
        $user2 = new User();

        $member1 = $this->createMock(TeamMember::class);
        $member1->method('getUser')->willReturn($user1);

        $member2 = $this->createMock(TeamMember::class);
        $member2->method('getUser')->willReturn($user2);

        $team1 = $this->createMock(Team::class);
        $team1->method('getMembers')->willReturn(new ArrayCollection([$member1]));
        $team1->method('getName')->willReturn('Team A');

        $team2 = $this->createMock(Team::class);
        $team2->method('getMembers')->willReturn(new ArrayCollection([$member2]));
        $team2->method('getName')->willReturn('Team B');

        $game = $this->createMock(Game::class);
        $game->method('getTeam1')->willReturn($team1);
        $game->method('getTeam2')->willReturn($team2);
        $game->method('getDate')->willReturn(new \DateTime('+12 hours'));
        $game->method('getId')->willReturn(1);
        $game->expects($this->once())->method('setReminderSentAt');

        $this->gameRepository
            ->expects($this->once())
            ->method('findGamesForReminder')
            ->willReturn([$game]);

        $this->pushNotificationService
            ->expects($this->exactly(2))
            ->method('sendToUsers');

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($game);

        ($this->handler)(new MatchReminderMessage());
    }

    public function testInvokeSetsReminderSentAt(): void
    {
        $user1 = new User();

        $member1 = $this->createMock(TeamMember::class);
        $member1->method('getUser')->willReturn($user1);

        $team1 = $this->createMock(Team::class);
        $team1->method('getMembers')->willReturn(new ArrayCollection([$member1]));
        $team1->method('getName')->willReturn('Team A');

        $team2 = $this->createMock(Team::class);
        $team2->method('getMembers')->willReturn(new ArrayCollection());
        $team2->method('getName')->willReturn('Team B');

        $game = $this->createMock(Game::class);
        $game->method('getTeam1')->willReturn($team1);
        $game->method('getTeam2')->willReturn($team2);
        $game->method('getDate')->willReturn(new \DateTime('+12 hours'));
        $game->method('getId')->willReturn(1);
        $game->expects($this->once())
            ->method('setReminderSentAt')
            ->with($this->isInstanceOf(\DateTime::class));

        $this->gameRepository
            ->method('findGamesForReminder')
            ->willReturn([$game]);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        ($this->handler)(new MatchReminderMessage());
    }
}
