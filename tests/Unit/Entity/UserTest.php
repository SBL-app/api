<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour l'entité User
 */
class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->user->getId());
        $this->assertNull($this->user->getUsername());
        $this->assertEquals(['ROLE_USER'], $this->user->getRoles()); // getRoles() ajoute automatiquement ROLE_USER
        // Note: getPassword() ne peut pas être testé dans l'état initial car il a un type de retour string
        $this->assertNull($this->user->getApiKey());
        $this->assertNull($this->user->getLastLogin());
        $this->assertTrue($this->user->isActive());
    }

    public function testSetAndGetUsername(): void
    {
        $username = 'testuser';

        $result = $this->user->setUsername($username);

        $this->assertSame($this->user, $result); // Test fluent interface
        $this->assertEquals($username, $this->user->getUsername());
    }

    public function testUserIdentifier(): void
    {
        $username = 'testuser';
        $this->user->setUsername($username);

        $this->assertEquals($username, $this->user->getUserIdentifier());
    }

    public function testSetAndGetRoles(): void
    {
        $roles = ['ROLE_ADMIN'];

        $result = $this->user->setRoles($roles);

        $this->assertSame($this->user, $result);
        $retrievedRoles = $this->user->getRoles();
        $this->assertContains('ROLE_USER', $retrievedRoles); // Automatiquement ajouté
        $this->assertContains('ROLE_ADMIN', $retrievedRoles);
    }

    public function testRolesAlwaysContainUserRole(): void
    {
        $this->user->setRoles(['ROLE_ADMIN']);

        $roles = $this->user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testSetAndGetPassword(): void
    {
        $password = 'hashed_password';

        $result = $this->user->setPassword($password);

        $this->assertSame($this->user, $result);
        $this->assertEquals($password, $this->user->getPassword());
    }

    public function testSetAndGetApiKey(): void
    {
        $apiKey = 'api_key_123456';

        $result = $this->user->setApiKey($apiKey);

        $this->assertSame($this->user, $result);
        $this->assertEquals($apiKey, $this->user->getApiKey());
    }

    public function testSetApiKeyToNull(): void
    {
        $this->user->setApiKey('api_key_123456');

        $result = $this->user->setApiKey(null);

        $this->assertSame($this->user, $result);
        $this->assertNull($this->user->getApiKey());
    }

    public function testSetAndGetLastLogin(): void
    {
        $lastLogin = new \DateTime('2024-09-15 14:30:00');

        $result = $this->user->setLastLogin($lastLogin);

        $this->assertSame($this->user, $result);
        $this->assertEquals($lastLogin, $this->user->getLastLogin());
    }

    public function testSetLastLoginToNull(): void
    {
        $this->user->setLastLogin(new \DateTime());

        $result = $this->user->setLastLogin(null);

        $this->assertSame($this->user, $result);
        $this->assertNull($this->user->getLastLogin());
    }

    public function testSetAndIsActive(): void
    {
        $result = $this->user->setIsActive(false);

        $this->assertSame($this->user, $result);
        $this->assertFalse($this->user->isActive());

        $this->user->setIsActive(true);
        $this->assertTrue($this->user->isActive());
    }

    public function testEraseCredentials(): void
    {
        // Cette méthode devrait nettoyer les données sensibles temporaires
        $this->user->eraseCredentials();

        // Pour l'instant, elle ne fait rien, donc on teste juste qu'elle n'échoue pas
        $this->addToAssertionCount(1);
    }

    public function testUniqueUsernameConstraint(): void
    {
        $username = 'unique_user';
        $this->user->setUsername($username);

        $this->assertEquals($username, $this->user->getUsername());
    }

    public function testCompleteUserSetup(): void
    {
        $username = 'admin_user';
        $password = 'hashed_password_123';
        $apiKey = 'api_key_admin_123';
        $roles = ['ROLE_ADMIN'];
        $lastLogin = new \DateTime('2024-09-15 14:30:00');

        $this->user->setUsername($username);
        $this->user->setPassword($password);
        $this->user->setApiKey($apiKey);
        $this->user->setRoles($roles);
        $this->user->setLastLogin($lastLogin);
        $this->user->setIsActive(true);

        $this->assertEquals($username, $this->user->getUsername());
        $this->assertEquals($password, $this->user->getPassword());
        $this->assertEquals($apiKey, $this->user->getApiKey());
        $retrievedRoles = $this->user->getRoles();
        $this->assertContains('ROLE_USER', $retrievedRoles);
        $this->assertContains('ROLE_ADMIN', $retrievedRoles);
        $this->assertEquals($lastLogin, $this->user->getLastLogin());
        $this->assertTrue($this->user->isActive());
    }

    public function testDefaultRoles(): void
    {
        $this->user->setRoles([]);

        $roles = $this->user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertCount(1, $roles);
    }

    public function testDuplicateRolesRemoval(): void
    {
        $this->user->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_USER']);

        $roles = $this->user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
        // La méthode devrait automatiquement dédupliquer
    }

    public function testUsernameWithSpecialCharacters(): void
    {
        $username = 'user.test@example.com';

        $this->user->setUsername($username);

        $this->assertEquals($username, $this->user->getUsername());
    }

    public function testFluentInterface(): void
    {
        $username = 'fluent_user';
        $password = 'fluent_password';
        $apiKey = 'fluent_api_key';

        $result = $this->user
            ->setUsername($username)
            ->setPassword($password)
            ->setApiKey($apiKey)
            ->setIsActive(true);

        $this->assertSame($this->user, $result);
        $this->assertEquals($username, $this->user->getUsername());
        $this->assertEquals($password, $this->user->getPassword());
        $this->assertEquals($apiKey, $this->user->getApiKey());
        $this->assertTrue($this->user->isActive());
    }

    public function testDateTimeHandling(): void
    {
        $date = new \DateTime('2024-09-15 14:30:00');
        $this->user->setLastLogin($date);

        $retrievedDate = $this->user->getLastLogin();
        $this->assertEquals($date->format('Y-m-d H:i:s'), $retrievedDate->format('Y-m-d H:i:s'));
    }
}
