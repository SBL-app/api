<?php

namespace App\Tests\Unit\MessageHandler;

use App\Message\MatchResultTimeoutMessage;
use App\MessageHandler\MatchResultTimeoutHandler;
use App\Repository\MatchResultRepository;
use App\Repository\UserRepository;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;

class MatchResultTimeoutHandlerTest extends TestCase
{
    private MatchResultRepository $resultRepository;
    private UserRepository $userRepository;
    private PushNotificationService $pushNotificationService;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->resultRepository = $this->createMock(MatchResultRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->pushNotificationService = $this->createMock(PushNotificationService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
    }

    private function createHandler(
        string $reminderDay,
        int $reminderHour,
        string $deadlineDay,
        int $deadlineHour,
        \DateTimeImmutable $now,
    ): MatchResultTimeoutHandler {
        return new MatchResultTimeoutHandler(
            $this->resultRepository,
            $this->userRepository,
            $this->pushNotificationService,
            $this->entityManager,
            new NullLogger(),
            new MockClock($now),
            $reminderDay,
            $reminderHour,
            $deadlineDay,
            $deadlineHour,
        );
    }

    public function testInvokeOnNonMatchingDayDoesNothing(): void
    {
        // Monday 10:00 — reminder=wednesday, deadline=thursday
        $handler = $this->createHandler('wednesday', 20, 'thursday', 22, new \DateTimeImmutable('2026-03-23 10:00:00')); // monday

        $this->resultRepository->expects($this->never())->method('findPendingWithoutReminder');
        $this->resultRepository->expects($this->never())->method('findPendingResults');

        $handler(new MatchResultTimeoutMessage());
    }

    public function testProcessReminderOnCorrectDay(): void
    {
        // Wednesday 20:00
        $handler = $this->createHandler('wednesday', 20, 'thursday', 22, new \DateTimeImmutable('2026-03-25 20:00:00'));

        $team1 = $this->createMock(\App\Entity\Team::class);
        $team1->method('getId')->willReturn(1);
        $team1->method('getName')->willReturn('Team A');

        $captain = new \App\Entity\User();

        $team2 = $this->createMock(\App\Entity\Team::class);
        $team2->method('getId')->willReturn(2);
        $team2->method('getName')->willReturn('Team B');
        $team2->method('getCaptainUser')->willReturn($captain);

        $game = $this->createMock(\App\Entity\Game::class);
        $game->method('getTeam1')->willReturn($team1);
        $game->method('getTeam2')->willReturn($team2);

        $result = $this->createMock(\App\Entity\MatchResult::class);
        $result->method('getGame')->willReturn($game);
        $result->method('getTeam')->willReturn($team1);
        $result->expects($this->once())->method('setReminderSentAt');

        $this->resultRepository->method('findPendingWithoutReminder')->willReturn([$result]);
        $this->pushNotificationService->expects($this->once())->method('sendToUser');
        $this->entityManager->expects($this->once())->method('flush');

        $handler(new MatchResultTimeoutMessage());
    }

    public function testProcessDeadlineOnCorrectDay(): void
    {
        // Thursday 22:00
        $handler = $this->createHandler('wednesday', 20, 'thursday', 22, new \DateTimeImmutable('2026-03-26 22:00:00'));

        $game = $this->createMock(\App\Entity\Game::class);

        $result = $this->createMock(\App\Entity\MatchResult::class);
        $result->method('getGame')->willReturn($game);
        $result->expects($this->once())->method('contest')->with('Délai de validation expiré');

        $statusRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $statusRepo->method('findOneBy')->willReturn(new \App\Entity\GameStatus());

        $this->entityManager->method('getRepository')->willReturn($statusRepo);
        $this->resultRepository->method('findPendingResults')->willReturn([$result]);

        $admin = new \App\Entity\User();
        $this->userRepository->method('findByRole')->with('ROLE_ADMIN')->willReturn([$admin]);

        $this->pushNotificationService->expects($this->once())->method('sendToUsers');
        $this->entityManager->expects($this->once())->method('flush');

        $handler(new MatchResultTimeoutMessage());
    }

    public function testReminderDayButBeforeHourDoesNothing(): void
    {
        // Wednesday 15:00 — before reminder hour (20)
        $handler = $this->createHandler('wednesday', 20, 'thursday', 22, new \DateTimeImmutable('2026-03-25 15:00:00'));

        $this->resultRepository->expects($this->never())->method('findPendingWithoutReminder');
        $this->resultRepository->expects($this->never())->method('findPendingResults');

        $handler(new MatchResultTimeoutMessage());
    }

    public function testDeadlineDayButBeforeHourDoesNothing(): void
    {
        // Thursday 18:00 — before deadline hour (22)
        $handler = $this->createHandler('wednesday', 20, 'thursday', 22, new \DateTimeImmutable('2026-03-26 18:00:00'));

        $this->resultRepository->expects($this->never())->method('findPendingWithoutReminder');
        $this->resultRepository->expects($this->never())->method('findPendingResults');

        $handler(new MatchResultTimeoutMessage());
    }

    public function testNoPendingResultsOnReminderDay(): void
    {
        // Wednesday 20:00 — but no pending results
        $handler = $this->createHandler('wednesday', 20, 'thursday', 22, new \DateTimeImmutable('2026-03-25 20:00:00'));

        $this->resultRepository->method('findPendingWithoutReminder')->willReturn([]);
        $this->pushNotificationService->expects($this->never())->method('sendToUser');

        $handler(new MatchResultTimeoutMessage());
    }

    public function testNoPendingResultsOnDeadlineDay(): void
    {
        // Thursday 22:00 — but no pending results
        $handler = $this->createHandler('wednesday', 20, 'thursday', 22, new \DateTimeImmutable('2026-03-26 22:00:00'));

        $this->resultRepository->method('findPendingResults')->willReturn([]);

        $statusRepo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $this->entityManager->method('getRepository')->willReturn($statusRepo);

        $this->pushNotificationService->expects($this->never())->method('sendToUsers');

        $handler(new MatchResultTimeoutMessage());
    }
}
