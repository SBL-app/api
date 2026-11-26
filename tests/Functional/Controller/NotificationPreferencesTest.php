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
        $this->assertSame(['discord' => true, 'push' => false], $data['channels']);
        $this->assertArrayNotHasKey('email', $data);
    }

    public function testEnablePush(): void
    {
        $this->login();
        $this->jsonRequest('PUT', '/api/users/me/notification-preferences', ['channels' => ['push' => true]]);

        $this->assertResponseStatusCode(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['discord' => true, 'push' => true], $data['channels']);
    }

    public function testRejectsMailChannel(): void
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
