<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\DiscordBotNotifier;
use App\Service\NotificationService;
use App\Service\PushNotificationService;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    private function user(array $channels, ?string $discordId = '123'): User
    {
        return (new User())->setNotificationChannels($channels)->setDiscordId($discordId);
    }

    private function service($push, $discord): NotificationService
    {
        return new NotificationService($push, $discord);
    }

    public function testDefaultChannelIsDiscordOnly(): void
    {
        $push = $this->createMock(PushNotificationService::class);
        $discord = $this->createMock(DiscordBotNotifier::class);

        $push->expects($this->never())->method('sendToUsers');
        $discord->expects($this->once())->method('send')->with('123', 'T', 'B', '/x');

        $this->service($push, $discord)->sendToUser(new User(), 'T', 'B', '/x');
        $user = new User();
        $user->setDiscordId('123');
        $this->service($push, $discord)->sendToUser($user, 'T', 'B', '/x');
    }

    public function testAllChannelsEnabled(): void
    {
        $push = $this->createMock(PushNotificationService::class);
        $discord = $this->createMock(DiscordBotNotifier::class);
        $user = $this->user(['discord', 'push']);

        $push->expects($this->once())->method('sendToUsers')->with([$user], 'T', 'B', '/');
        $discord->expects($this->once())->method('send');

        $this->service($push, $discord)->sendToUser($user, 'T', 'B');
    }

    public function testNoChannelMeansNothingSent(): void
    {
        $push = $this->createMock(PushNotificationService::class);
        $discord = $this->createMock(DiscordBotNotifier::class);

        $push->expects($this->never())->method('sendToUsers');
        $discord->expects($this->never())->method('send');

        $service = $this->service($push, $discord);
        $service->sendToUser($this->user(['mail']), 'T', 'B');
        $service->sendToUser($this->user([]), 'T', 'B');
    }
}
