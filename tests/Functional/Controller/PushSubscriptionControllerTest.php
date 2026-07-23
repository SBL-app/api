<?php

namespace App\Tests\Functional\Controller;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PushSubscriptionControllerTest extends ApiTestCase
{
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    private function createAuthenticatedUser(string $username = 'testuser'): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user, 'api');

        return $user;
    }

    public function testGetVapidPublicKey(): void
    {
        $response = $this->jsonRequest('GET', '/api/push/vapid-public-key');

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('publicKey', $response);
        $this->assertNotEmpty($response['publicKey']);
    }

    public function testSubscribeRequiresAuthentication(): void
    {
        $this->jsonRequest('POST', '/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            'keys' => [
                'p256dh' => 'test-p256dh-key-base64',
                'auth' => 'test-auth-token-base64',
            ],
        ]);

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertGreaterThanOrEqual(400, $statusCode, 'Unauthenticated request should be rejected');
        $this->assertNotEquals(201, $statusCode, 'Unauthenticated request should not create a subscription');
    }

    public function testSubscribeSuccess(): void
    {
        $user = $this->createAuthenticatedUser();

        $response = $this->jsonRequest('POST', '/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            'keys' => [
                'p256dh' => 'test-p256dh-key-base64',
                'auth' => 'test-auth-token-base64',
            ],
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('ok', $response['status']);

        // Vérifier que l'entité est en BDD
        $subscription = $this->entityManager
            ->getRepository(PushSubscription::class)
            ->findByEndpoint('https://fcm.googleapis.com/fcm/send/test-endpoint-123');

        $this->assertNotNull($subscription);
        $this->assertEquals($user->getId(), $subscription->getUser()->getId());
        $this->assertEquals('test-p256dh-key-base64', $subscription->getP256dhKey());
        $this->assertEquals('test-auth-token-base64', $subscription->getAuthToken());
    }

    public function testSubscribeUpdatesExisting(): void
    {
        $user = $this->createAuthenticatedUser();

        // Créer un abonnement existant
        $subscription = new PushSubscription();
        $subscription->setUser($user);
        $subscription->setEndpoint('https://fcm.googleapis.com/fcm/send/test-endpoint-123');
        $subscription->setP256dhKey('old-p256dh-key');
        $subscription->setAuthToken('old-auth-token');
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        // Mettre à jour avec les mêmes endpoint mais nouvelles clés
        $response = $this->jsonRequest('POST', '/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            'keys' => [
                'p256dh' => 'new-p256dh-key-base64',
                'auth' => 'new-auth-token-base64',
            ],
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('status', $response);
        $this->assertEquals('ok', $response['status']);

        // Vérifier que les clés ont été mises à jour
        $this->entityManager->clear();
        $updated = $this->entityManager
            ->getRepository(PushSubscription::class)
            ->findByEndpoint('https://fcm.googleapis.com/fcm/send/test-endpoint-123');

        $this->assertNotNull($updated);
        $this->assertEquals('new-p256dh-key-base64', $updated->getP256dhKey());
        $this->assertEquals('new-auth-token-base64', $updated->getAuthToken());
    }

    public function testSubscribeMissingFields(): void
    {
        $this->createAuthenticatedUser();

        // Requête sans les clés
        $response = $this->jsonRequest('POST', '/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
        ]);

        $this->assertResponseStatusCode(400);
    }

    public function testUnsubscribeSuccess(): void
    {
        $user = $this->createAuthenticatedUser();

        // Créer un abonnement existant
        $subscription = new PushSubscription();
        $subscription->setUser($user);
        $subscription->setEndpoint('https://fcm.googleapis.com/fcm/send/test-endpoint-123');
        $subscription->setP256dhKey('test-p256dh-key-base64');
        $subscription->setAuthToken('test-auth-token-base64');
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        $response = $this->jsonRequest('DELETE', '/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
        ]);

        $this->assertResponseStatusCode(204);
        $this->assertNull($response);

        // Vérifier que l'abonnement a été supprimé
        $this->entityManager->clear();
        $deleted = $this->entityManager
            ->getRepository(PushSubscription::class)
            ->findByEndpoint('https://fcm.googleapis.com/fcm/send/test-endpoint-123');

        $this->assertNull($deleted);
    }

    public function testUnsubscribeMissingEndpoint(): void
    {
        $this->createAuthenticatedUser();

        $this->jsonRequest('DELETE', '/api/push/subscribe', []);

        $this->assertResponseStatusCode(400);
    }

    public function testUnsubscribeNotFound(): void
    {
        $this->createAuthenticatedUser();

        $this->jsonRequest('DELETE', '/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/nonexistent-endpoint',
        ]);

        $this->assertResponseStatusCode(404);
    }

    public function testSubscribeCannotStealOtherUserSubscription(): void
    {
        // Créer le premier utilisateur avec un abonnement
        $user1 = new User();
        $user1->setUsername('user1');
        $user1->setPassword($this->passwordHasher->hashPassword($user1, 'password123'));
        $user1->setRoles(['ROLE_USER']);
        $user1->setIsActive(true);
        $this->entityManager->persist($user1);

        $subscription = new PushSubscription();
        $subscription->setUser($user1);
        $subscription->setEndpoint('https://fcm.googleapis.com/fcm/send/shared-endpoint');
        $subscription->setP256dhKey('user1-p256dh-key');
        $subscription->setAuthToken('user1-auth-token');
        $this->entityManager->persist($subscription);

        $this->entityManager->flush();

        // Créer et authentifier le second utilisateur
        $user2 = new User();
        $user2->setUsername('user2');
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'password123'));
        $user2->setRoles(['ROLE_USER']);
        $user2->setIsActive(true);
        $this->entityManager->persist($user2);
        $this->entityManager->flush();

        $this->client->loginUser($user2, 'api');

        // Tenter de s'abonner avec le même endpoint
        $this->jsonRequest('POST', '/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/shared-endpoint',
            'keys' => [
                'p256dh' => 'user2-p256dh-key',
                'auth' => 'user2-auth-token',
            ],
        ]);

        $this->assertResponseStatusCode(403);

        // Vérifier que l'abonnement appartient toujours à user1
        $this->entityManager->clear();
        $existingSub = $this->entityManager
            ->getRepository(PushSubscription::class)
            ->findByEndpoint('https://fcm.googleapis.com/fcm/send/shared-endpoint');

        $this->assertNotNull($existingSub);
        $this->assertEquals($user1->getId(), $existingSub->getUser()->getId());
        $this->assertEquals('user1-p256dh-key', $existingSub->getP256dhKey());
    }
}
