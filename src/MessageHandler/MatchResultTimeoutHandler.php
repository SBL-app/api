<?php

namespace App\MessageHandler;

use App\Entity\GameStatus;
use App\Message\MatchResultTimeoutMessage;
use App\Repository\MatchResultRepository;
use App\Repository\UserRepository;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class MatchResultTimeoutHandler
{
    public function __construct(
        private MatchResultRepository $resultRepository,
        private UserRepository $userRepository,
        private PushNotificationService $pushNotificationService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ClockInterface $clock,
        private string $matchResultReminderDay,
        private int $matchResultReminderHour,
        private string $matchResultDeadlineDay,
        private int $matchResultDeadlineHour,
    ) {}

    public function __invoke(MatchResultTimeoutMessage $message): void
    {
        $now = $this->clock->now();
        $currentDay = strtolower($now->format('l'));
        $currentHour = (int) $now->format('G');

        $this->logger->info('Match result timeout check', [
            'current_day' => $currentDay,
            'current_hour' => $currentHour,
        ]);

        // Deadline has priority over reminder
        if ($currentDay === strtolower($this->matchResultDeadlineDay) && $currentHour >= $this->matchResultDeadlineHour) {
            $this->processDeadline();
        } elseif ($currentDay === strtolower($this->matchResultReminderDay) && $currentHour >= $this->matchResultReminderHour) {
            $this->processReminder();
        }
    }

    private function processReminder(): void
    {
        $pendingResults = $this->resultRepository->findPendingWithoutReminder();

        $this->logger->info('Processing reminders', ['count' => count($pendingResults)]);

        foreach ($pendingResults as $result) {
            $game = $result->getGame();
            $submitterTeam = $result->getTeam();
            $team1 = $game?->getTeam1();
            $team2 = $game?->getTeam2();

            $opposingTeam = null;
            if ($submitterTeam && $team1 && $submitterTeam->getId() === $team1->getId()) {
                $opposingTeam = $team2;
            } elseif ($submitterTeam && $team2 && $submitterTeam->getId() === $team2->getId()) {
                $opposingTeam = $team1;
            }

            $opposingCaptain = $opposingTeam?->getCaptainUser();

            if ($opposingCaptain) {
                try {
                    $this->pushNotificationService->sendToUser(
                        $opposingCaptain,
                        'Rappel — Résultat en attente',
                        sprintf(
                            'Le résultat du match %s vs %s attend votre validation',
                            $team1?->getName() ?? '???',
                            $team2?->getName() ?? '???',
                        ),
                        '/matches',
                    );
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to send reminder notification', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $result->setReminderSentAt($this->clock->now());
            $this->entityManager->persist($result);
        }

        $this->entityManager->flush();
    }

    private function processDeadline(): void
    {
        $pendingResults = $this->resultRepository->findPendingResults();

        $this->logger->info('Processing deadline', ['count' => count($pendingResults)]);

        if (empty($pendingResults)) {
            return;
        }

        $contestedStatus = $this->entityManager->getRepository(GameStatus::class)
            ->findOneBy(['name' => 'contested']);

        foreach ($pendingResults as $result) {
            $result->contest('Délai de validation expiré');

            $game = $result->getGame();
            if ($game && $contestedStatus) {
                $game->setStatus($contestedStatus);
                $this->entityManager->persist($game);
            }

            $this->entityManager->persist($result);
        }

        $this->entityManager->flush();

        // Notify admins
        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        if (!empty($admins)) {
            $count = count($pendingResults);
            try {
                $this->pushNotificationService->sendToUsers(
                    $admins,
                    'Résultats expirés',
                    sprintf('%d résultat(s) non validé(s) — intervention admin requise', $count),
                    '/admin/matches',
                );
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send admin notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
