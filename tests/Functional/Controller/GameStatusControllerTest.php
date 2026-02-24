<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\GameStatus;

class GameStatusControllerTest extends ApiTestCase
{
    public function testGetGameStatusesEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/gameStatus');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetGameStatusesWithData(): void
    {
        // Créer des statuts de jeu
        $gameStatus1 = new GameStatus();
        $gameStatus1->setName('En attente');

        $gameStatus2 = new GameStatus();
        $gameStatus2->setName('En cours');

        $this->entityManager->persist($gameStatus1);
        $this->entityManager->persist($gameStatus2);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/gameStatus');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(2, $response);

        $statusData = $response[0];
        $this->assertArrayHasKey('id', $statusData);
        $this->assertArrayHasKey('name', $statusData);
    }

    public function testGetGameStatusById(): void
    {
        // Créer un statut de jeu
        $gameStatus = new GameStatus();
        $gameStatus->setName('Programmé');

        $this->entityManager->persist($gameStatus);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/gameStatus/' . $gameStatus->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertEquals('Programmé', $response['name']);
    }

    public function testGetGameStatusByIdNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/gameStatus?id=999');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
    }

    public function testGetAllGameStatuses(): void
    {
        // Créer des statuts de jeu
        $gameStatus1 = new GameStatus();
        $gameStatus1->setName('Joué');

        $gameStatus2 = new GameStatus();
        $gameStatus2->setName('Annulé');

        $this->entityManager->persist($gameStatus1);
        $this->entityManager->persist($gameStatus2);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/gameStatus');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(2, $response);

        // Vérifier que les deux statuts sont présents
        $names = array_column($response, 'name');
        $this->assertContains('Joué', $names);
        $this->assertContains('Annulé', $names);
    }

    public function testGetGameStatusWithNonExistentParameter(): void
    {
        $response = $this->jsonRequest('GET', '/api/gameStatus?name=Inexistant');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        // Le contrôleur ignore les paramètres non reconnus et renvoie tous les statuts
        $this->assertEmpty($response); // Car aucun statut n'a été créé
    }
}
