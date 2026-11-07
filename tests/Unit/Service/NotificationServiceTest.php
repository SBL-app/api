<?php

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\DiscordBotNotifier;
use App\Service\NotificationService;
use App\Service\PushNotificationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Mailer\MailerInterface;

class NotificationServiceTest extends TestCase
{
    private function user(array $channels, ?string $email = null, ?string $discordId = '123'): User
    {
        return (new User())->setNotificationChannels($channels)->setEmail($email)->setDiscordId($discordId);
    }

    private function service($push, $discord, $mailer): NotificationService
    {
        return new NotificationService($push, $discord, $mailer, new NullLogger(), 'SBL <noreply@sbl.app>');
    }

    public function testDefaultChannelIsDiscordOnly(): void
    {
        $push = $this->createMock(PushNotificationService::class);
        $discord = $this->createMock(DiscordBotNotifier::class);
        $mailer = $this->createMock(MailerInterface::class);

        $push->expects($this->never())->method('sendToUsers');
        $mailer->expects($this->never())->method('send');
        $discord->expects($this->once())->method('send')->with('123', 'T', 'B', '/x');

        $this->service($push, $discord, $mailer)->sendToUser(new User(), 'T', 'B', '/x');
        $user = new User();
        $user->setDiscordId('123');
        $this->service($push, $discord, $mailer)->sendToUser($user, 'T', 'B', '/x');
    }

    public function testAllChannelsEnabled(): void
    {
        $push = $this->createMock(PushNotificationService::class);
        $discord = $this->createMock(DiscordBotNotifier::class);
        $mailer = $this->createMock(MailerInterface::class);
        $user = $this->user(['discord', 'push', 'mail'], 'a@b.fr');

        $push->expects($this->once())->method('sendToUsers')->with([$user], 'T', 'B', '/');
        $discord->expects($this->once())->method('send');
        $mailer->expects($this->once())->method('send');

        $this->service($push, $discord, $mailer)->sendToUser($user, 'T', 'B');
    }

    public function testMailSkippedWithoutEmailAndNoChannelMeansNothingSent(): void
    {
        $push = $this->createMock(PushNotificationService::class);
        $discord = $this->createMock(DiscordBotNotifier::class);
        $mailer = $this->createMock(MailerInterface::class);

        $push->expects($this->never())->method('sendToUsers');
        $discord->expects($this->never())->method('send');
        $mailer->expects($this->never())->method('send');

        $service = $this->service($push, $discord, $mailer);
        $service->sendToUser($this->user(['mail'], null), 'T', 'B');
        $service->sendToUser($this->user([], 'a@b.fr'), 'T', 'B');
    }
}
