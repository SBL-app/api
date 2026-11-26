<?php

namespace App\Service;

use App\Entity\User;

/**
 * Point d'entrée unique des notifications : choisit les canaux (discord, push)
 * selon les préférences de chaque utilisateur.
 */
class NotificationService
{
    public function __construct(
        private PushNotificationService $push,
        private DiscordBotNotifier $discord,
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
        }

        if ($pushUsers !== []) {
            $this->push->sendToUsers($pushUsers, $title, $body, $url);
        }
    }
}
