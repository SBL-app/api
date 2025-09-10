<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthControllerTest extends ApiTestCase
{
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    public function testLoginSuccess(): void
    {
        // Créer un utilisateur de test
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setIsActive(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $response = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'testuser',
            'password' => 'password123'
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertArrayHasKey('expires_in', $response);
        $this->assertEquals(3600, $response['expires_in']);
    }

    public function testLoginMissingCredentials(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'testuser'
            // password manquant
        ]);

        $this->assertResponseStatusCodeSame(400);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Username and password are required', $response['error']);
    }

    public function testLoginInvalidCredentials(): void
    {
        // Créer un utilisateur de test
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setIsActive(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'testuser',
            'password' => 'wrongpassword'
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginInactiveUser(): void
    {
        // Créer un utilisateur inactif
        $user = new User();
        $user->setUsername('inactiveuser');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setIsActive(false);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'inactiveuser',
            'password' => 'password123'
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginNonExistentUser(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'nonexistent',
            'password' => 'password123'
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testVerifyTokenMissingToken(): void
    {
        $this->jsonRequest('POST', '/api/auth/verify');

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Missing or invalid Authorization header', $response['error']);
    }

    public function testRefreshTokenMissingToken(): void
    {
        $this->jsonRequest('POST', '/api/auth/refresh');

        $this->assertResponseStatusCodeSame(401);
        $response = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Missing or invalid Authorization header', $response['error']);
    }

    public function testLoginAndVerifyTokenWorkflow(): void
    {
        // Créer un utilisateur de test
        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $user->setIsActive(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Se connecter pour obtenir un token
        $loginResponse = $this->jsonRequest('POST', '/api/auth/login', [
            'username' => 'testuser',
            'password' => 'password123'
        ]);

        $this->assertResponseIsSuccessful();
        $token = $loginResponse['token'];

        // Vérifier le token
        $this->client->request('POST', '/api/auth/verify', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testEmptyRequestBody(): void
    {
        $this->client->request('POST', '/api/auth/login', [], [], [], '');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testInvalidJsonRequestBody(): void
    {
        $this->client->request('POST', '/api/auth/login', [], [], [], '{invalid json}');

        $this->assertResponseStatusCodeSame(400);
    }
}
