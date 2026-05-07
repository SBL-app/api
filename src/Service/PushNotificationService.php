<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

class PushNotificationService
{
    private ?WebPush $webPush = null;

    public function __construct(
        private PushSubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        string $vapidPublicKey,
        string $vapidPrivateKey,
        string $vapidSubject,
    ) {
        try {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => $vapidSubject,
                    'publicKey' => $vapidPublicKey,
                    'privateKey' => $vapidPrivateKey,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('WebPush disabled: invalid VAPID keys', ['error' => $e->getMessage()]);
        }
    }

    public function sendToUser(User $user, string $title, string $body, string $url = '/'): void
    {
        $this->sendToUsers([$user], $title, $body, $url);
    }

    public function sendToUsers(array $users, string $title, string $body, string $url = '/'): void
    {
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'icon' => '/img/sbl-logo.png',
            'badge' => '/img/sbl-logo.png',
        ]);

        if ($this->webPush === null) {
            $this->logger->warning('Push notifications disabled, skipping send');
            return;
        }

        $subscriptionMap = [];

        foreach ($users as $user) {
            foreach ($this->subscriptionRepository->findByUser($user) as $sub) {
                $subscription = Subscription::create([
                    'endpoint' => $sub->getEndpoint(),
                    'keys' => [
                        'p256dh' => $sub->getP256dhKey(),
                        'auth' => $sub->getAuthToken(),
                    ],
                ]);
                $this->webPush->queueNotification($subscription, $payload);
                $subscriptionMap[$sub->getEndpoint()] = $sub;
            }
        }

        if (empty($subscriptionMap)) {
            return;
        }

        $toRemove = [];

        foreach ($this->webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $endpoint = $report->getEndpoint();
                if ($report->isSubscriptionExpired() && isset($subscriptionMap[$endpoint])) {
                    $toRemove[] = $subscriptionMap[$endpoint];
                    $this->logger->info('Removing expired push subscription', ['endpoint' => $endpoint]);
                } else {
                    $this->logger->warning('Push notification failed', [
                        'endpoint' => $endpoint,
                        'reason' => $report->getReason(),
                    ]);
                }
            }
        }

        foreach ($toRemove as $sub) {
            $this->entityManager->remove($sub);
        }

        if (!empty($toRemove)) {
            $this->entityManager->flush();
        }
    }
}
