<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\Loader;

/**
 * Classe de base pour les tests fonctionnels
 * Fournit des utilitaires communs pour tester l'API
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->setServerParameter('CONTENT_TYPE', 'application/json');

        $this->entityManager = static::getContainer()->get('doctrine')->getManager();

        // Nettoyer la base de données de test
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Nettoie la base de données de test
     */
    protected function cleanDatabase(): void
    {
        $purger = new ORMPurger($this->entityManager);
        $executor = new ORMExecutor($this->entityManager, $purger);
        $executor->purge();
    }

    /**
     * Charge des fixtures spécifiques
     */
    protected function loadFixtures(array $fixtures): void
    {
        $loader = new Loader();
        foreach ($fixtures as $fixture) {
            $loader->addFixture($fixture);
        }

        $purger = new ORMPurger($this->entityManager);
        $executor = new ORMExecutor($this->entityManager, $purger);
        $executor->execute($loader->getFixtures());
    }

    /**
     * Effectue une requête JSON et retourne la réponse décodée
     */
    protected function jsonRequest(string $method, string $uri, array $data = [], array $headers = []): ?array
    {
        $defaultHeaders = ['CONTENT_TYPE' => 'application/json'];
        $headers = array_merge($defaultHeaders, $headers);

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $headers,
            json_encode($data)
        );

        $response = $this->client->getResponse();
        $content = $response->getContent();

        if (empty($content)) {
            return null;
        }

        $decoded = json_decode($content, true);
        return $decoded ?? [];
    }

    /**
     * Asserte qu'une réponse JSON contient les champs attendus
     */
    protected function assertJsonResponseStructure(array $expectedStructure, array $actualData, string $path = ''): void
    {
        foreach ($expectedStructure as $key => $value) {
            $currentPath = $path ? "$path.$key" : $key;

            if (is_int($key)) {
                // Pour les listes, on vérifie que la clé existe
                $this->assertArrayHasKey($value, $actualData, "Missing key '$value' at path '$currentPath'");
            } elseif (is_array($value)) {
                // Pour les objets imbriqués
                $this->assertArrayHasKey($key, $actualData, "Missing key '$key' at path '$currentPath'");
                $this->assertJsonResponseStructure($value, $actualData[$key], $currentPath);
            } else {
                // Pour les valeurs simples
                $this->assertArrayHasKey($key, $actualData, "Missing key '$key' at path '$currentPath'");
                if ($value !== null) {
                    $this->assertEquals($value, $actualData[$key], "Value mismatch at path '$currentPath'");
                }
            }
        }
    }

    /**
     * Génère un token JWT de test (si nécessaire)
     */
    protected function getAuthHeaders(): array
    {
        // Pour l'instant, retourne un tableau vide
        // À implémenter si l'authentification JWT est nécessaire pour les tests
        return [];
    }

    /**
     * Asserte qu'une réponse a le code de statut attendu
     */
    protected function assertResponseStatusCode(int $expectedStatusCode): void
    {
        $this->assertEquals(
            $expectedStatusCode,
            $this->client->getResponse()->getStatusCode(),
            sprintf(
                'Expected status code %d, got %d. Response content: %s',
                $expectedStatusCode,
                $this->client->getResponse()->getStatusCode(),
                $this->client->getResponse()->getContent()
            )
        );
    }
}
