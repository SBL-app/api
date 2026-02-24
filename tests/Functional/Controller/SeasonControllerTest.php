<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\Season;

class SeasonControllerTest extends ApiTestCase
{
    public function testGetSeasonsEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/seasons');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetSeasonsWithData(): void
    {
        // Créer une saison de test
        $season = new Season();
        $season->setName('Season 2024');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $this->entityManager->persist($season);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/seasons');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $seasonData = $response[0];
        $this->assertArrayHasKey('id', $seasonData);
        $this->assertArrayHasKey('name', $seasonData);
        $this->assertArrayHasKey('start_date', $seasonData);
        $this->assertArrayHasKey('end_date', $seasonData);

        $this->assertEquals('Season 2024', $seasonData['name']);
        $this->assertEquals('01-01-2024', $seasonData['start_date']);
        $this->assertEquals('31-12-2024', $seasonData['end_date']);
    }

    public function testGetSeasonById(): void
    {
        // Créer une saison de test
        $season = new Season();
        $season->setName('Test Season');
        $season->setStartDate(new \DateTime('2023-01-01'));
        $season->setEndDate(new \DateTime('2023-12-31'));

        $this->entityManager->persist($season);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/seasons/' . $season->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('start_date', $response);
        $this->assertArrayHasKey('end_date', $response);

        $this->assertEquals('Test Season', $response['name']);
        $this->assertEquals('01-01-2023', $response['start_date']);
        $this->assertEquals('31-12-2023', $response['end_date']);
    }

    public function testGetSeasonByIdNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/seasons/999');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('detail', $response);
        $this->assertEquals('Season with id 999 not found', $response['detail']);
    }

    public function testGetSeasonTeams(): void
    {
        // Créer une saison de test
        $season = new Season();
        $season->setName('Season with Teams');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $this->entityManager->persist($season);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/seasons/' . $season->getId() . '/teams');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        // Pas d'équipes créées donc tableau vide attendu
    }

    public function testGetSeasonTeamsNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/seasons/999/teams');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('detail', $response);
    }

    public function testGetSeasonCompletion(): void
    {
        // Créer une saison de test
        $season = new Season();
        $season->setName('Season Percentage');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $this->entityManager->persist($season);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/seasons/' . $season->getId() . '/completion');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        // Le pourcentage devrait être 0% car pas de matchs
        $this->assertArrayHasKey('total', $response);
        $this->assertArrayHasKey('finished', $response);
        $this->assertArrayHasKey('pourcent', $response);
    }

    public function testGetSeasonCompletionNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/seasons/999/completion');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('detail', $response);
    }
}
