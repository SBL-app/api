<?php

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Tests\Functional\ApiTestCase;

class NotificationPreferencesTest extends ApiTestCase
{
    private function login(): User
    {
        $user = new User();
        $user->setUsername('prefs_user');
        $user->setPassword('x');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->client->loginUser($user, 'api');

        return $user;
    }

    public function testDefaultsToDiscordOnly(): void
    {
        $this->login();
        $this->jsonRequest('GET', '/api/users/me/notification-preferences');

        $this->assertResponseStatusCode(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['discord' => true, 'push' => false, 'mail' => false], $data['channels']);
        $this->assertNull($data['email']);
    }

    public function testEnableAllChannelsWithEmail(): void
    {
        $this->login();
        $this->jsonRequest('PUT', '/api/users/me/notification-preferences', [
            'channels' => ['push' => true, 'mail' => true],
            'email' => 'joueur@example.com',
        ]);

        $this->assertResponseStatusCode(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['discord' => true, 'push' => true, 'mail' => true], $data['channels']);
        $this->assertSame('joueur@example.com', $data['email']);
    }

    public function testMailRequiresEmail(): void
    {
        $this->login();
        $this->jsonRequest('PUT', '/api/users/me/notification-preferences', ['channels' => ['mail' => true]]);

        $this->assertResponseStatusCode(422);
    }

    public function testRejectsUnknownChannel(): void
    {
        $this->login();
        $this->jsonRequest('PUT', '/api/users/me/notification-preferences', ['channels' => ['sms' => true]]);

        $this->assertResponseStatusCode(422);
    }
}
