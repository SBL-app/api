<?php

namespace App\Service;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Point d'entrée unique des notifications : choisit les canaux (discord, push, mail)
 * selon les préférences de chaque utilisateur.
 */
class NotificationService
{
    public function __construct(
        private PushNotificationService $push,
        private DiscordBotNotifier $discord,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire(env: 'MAILER_FROM')] private string $mailFrom,
    ) {}

    public function sendToUser(User $user, string $title, string $body, string $url = '/'): void
    {
        $this->sendToUsers([$user], $title, $body, $url);
    }

    /** @param User[] $users */
    public function sendToUsers(array $users, string $title, string $body, string $url = '/'): void
    {
        $pushUsers = [];

        foreach ($users as $user) {
            if ($user->wantsNotificationChannel(User::CHANNEL_PUSH)) {
                $pushUsers[] = $user;
            }

            if ($user->wantsNotificationChannel(User::CHANNEL_DISCORD) && $user->getDiscordId() !== null) {
                $this->discord->send($user->getDiscordId(), $title, $body, $url);
            }

            if ($user->wantsNotificationChannel(User::CHANNEL_MAIL) && $user->getEmail() !== null) {
                $this->sendMail($user->getEmail(), $title, $body, $url);
            }
        }

        if ($pushUsers !== []) {
            $this->push->sendToUsers($pushUsers, $title, $body, $url);
        }
    }

    private function sendMail(string $to, string $title, string $body, string $url): void
    {
        try {
            $this->mailer->send(
                (new Email())
                    ->from($this->mailFrom)
                    ->to($to)
                    ->subject($title)
                    ->text($body . "\n\n" . $url)
            );
        } catch (\Throwable $e) {
            $this->logger->warning('Mail notification failed', ['error' => $e->getMessage()]);
        }
    }
}
