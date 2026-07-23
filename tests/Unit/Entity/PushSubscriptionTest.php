<?php

namespace App\Tests\Unit\Entity;

use App\Entity\PushSubscription;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PushSubscriptionTest extends TestCase
{
    public function testIdIsNullByDefault(): void
    {
        $subscription = new PushSubscription();

        $this->assertNull($subscription->getId());
    }

    public function testSetAndGetUser(): void
    {
        $subscription = new PushSubscription();
        $user = new User();

        $result = $subscription->setUser($user);

        $this->assertSame($user, $subscription->getUser());
        $this->assertSame($subscription, $result);
    }

    public function testSetAndGetEndpoint(): void
    {
        $subscription = new PushSubscription();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/example-endpoint';

        $result = $subscription->setEndpoint($endpoint);

        $this->assertSame($endpoint, $subscription->getEndpoint());
        $this->assertSame($subscription, $result);
    }

    public function testSetAndGetP256dhKey(): void
    {
        $subscription = new PushSubscription();
        $key = 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8p8REfWI8=';

        $result = $subscription->setP256dhKey($key);

        $this->assertSame($key, $subscription->getP256dhKey());
        $this->assertSame($subscription, $result);
    }

    public function testSetAndGetAuthToken(): void
    {
        $subscription = new PushSubscription();
        $token = 'tBHItJI5svbpC7yG6H_TnA==';

        $result = $subscription->setAuthToken($token);

        $this->assertSame($token, $subscription->getAuthToken());
        $this->assertSame($subscription, $result);
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $subscription = new PushSubscription();
        $after = new \DateTimeImmutable();

        $createdAt = $subscription->getCreatedAt();

        $this->assertInstanceOf(\DateTimeImmutable::class, $createdAt);
        $this->assertGreaterThanOrEqual($before, $createdAt);
        $this->assertLessThanOrEqual($after, $createdAt);

        // Vérifie que createdAt est récent (< 2 secondes)
        $diff = (new \DateTimeImmutable())->getTimestamp() - $createdAt->getTimestamp();
        $this->assertLessThan(2, $diff);
    }
}
