<?php

namespace App\MessageHandler;

use App\Message\GameResultTimeoutMessage;
use App\Repository\GameResultRepository;
use App\Repository\UserRepository;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GameResultTimeoutHandler
{
    public function __construct(
        private GameResultRepository $gameResultRepository,
        private UserRepository $userRepository,
        private PushNotificationService $pushNotificationService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private int $scoreValidationTimeoutDays,
    ) {}

    public function __invoke(GameResultTimeoutMessage $message): void
    {
        $expired = $this->gameResultRepository->findExpiredPending($this->scoreValidationTimeoutDays);

        $this->logger->info('Game result auto-dispute check', ['expired_count' => count($expired)]);

        if (empty($expired)) {
            return;
        }

        foreach ($expired as $result) {
            $result->autoDispute();
            $this->entityManager->persist($result);
        }

        $this->entityManager->flush();

        $admins = $this->userRepository->findByRole('ROLE_ADMIN');
        if (empty($admins)) {
            return;
        }

        $count = count($expired);
        try {
            $this->pushNotificationService->sendToUsers(
                $admins,
                'Résultats en litige automatique',
                sprintf('%d résultat(s) non confirmé(s) après %d jours — intervention admin requise', $count, $this->scoreValidationTimeoutDays),
                '/admin/matches',
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send auto-dispute admin notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
