<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\Player;
use App\Entity\Team;
use App\Entity\Division;
use App\Entity\Season;

class PlayerControllerTest extends ApiTestCase
{
    public function testGetPlayersEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/players');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetPlayersWithData(): void
    {
        // Créer les entités de test
        $season = new Season();
        $season->setName('Season 2024');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $division = new Division();
        $division->setName('Division Test');
        $division->setSeason($season);

        $team = new Team();
        $team->setName('Team Test');

        $player = new Player();
        $player->setName('John Doe');
        $player->setDiscord('johndoe#1234');
        $player->setTeam($team);

        $this->entityManager->persist($season);
        $this->entityManager->persist($division);
        $this->entityManager->persist($team);
        $this->entityManager->persist($player);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/players');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $playerData = $response[0];
        $this->assertArrayHasKey('id', $playerData);
        $this->assertArrayHasKey('name', $playerData);
        $this->assertArrayHasKey('discord', $playerData);
        $this->assertArrayHasKey('team_id', $playerData);
        $this->assertArrayHasKey('team_name', $playerData);

        $this->assertEquals('John Doe', $playerData['name']);
        $this->assertEquals('johndoe#1234', $playerData['discord']);
        $this->assertEquals($team->getId(), $playerData['team_id']);
        $this->assertEquals('Team Test', $playerData['team_name']);
    }

    public function testGetPlayerById(): void
    {
        // Créer un joueur de test
        $team = new Team();
        $team->setName('Team Test');

        $player = new Player();
        $player->setName('Jane Doe');
        $player->setDiscord('janedoe#5678');
        $player->setTeam($team);

        $this->entityManager->persist($team);
        $this->entityManager->persist($player);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/players/' . $player->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('discord', $response);
        $this->assertArrayHasKey('team_id', $response);
        $this->assertArrayHasKey('team_name', $response);

        $this->assertEquals('Jane Doe', $response['name']);
        $this->assertEquals('janedoe#5678', $response['discord']);
        $this->assertEquals($team->getId(), $response['team_id']);
        $this->assertEquals('Team Test', $response['team_name']);
    }

    public function testGetPlayerByIdNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/players?id=999');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Player not found', $response['error']);
    }

    public function testGetPlayerWithStats(): void
    {
        // Créer les entités de test
        $season = new Season();
        $season->setName('Season 2024');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $division = new Division();
        $division->setName('Division Test');
        $division->setSeason($season);

        $team = new Team();
        $team->setName('Team Test');

        $player = new Player();
        $player->setName('Player With Stats');
        $player->setDiscord('playerstats#1234');
        $player->setTeam($team);

        $this->entityManager->persist($season);
        $this->entityManager->persist($division);
        $this->entityManager->persist($team);
        $this->entityManager->persist($player);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/players/' . $player->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('discord', $response);
        $this->assertArrayHasKey('stats', $response);

        $this->assertEquals('Player With Stats', $response['name']);
        $this->assertEquals('playerstats#1234', $response['discord']);
        $this->assertIsArray($response['stats']);
    }

    public function testGetPlayerWithStatsNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/players?id=999');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Player not found', $response['error']);
    }

    public function testCreatePlayer(): void
    {
        $team = new Team();
        $team->setName('Team for New Player');
        $this->entityManager->persist($team);
        $this->entityManager->flush();

        $playerData = [
            'name' => 'New Player',
            'discord' => 'newplayer#9999',
            'team' => $team->getId()
        ];

        $response = $this->jsonRequest('POST', '/api/players', $playerData);

        $this->assertResponseStatusCodeSame(201);
        $this->assertIsArray($response);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('discord', $response);
        $this->assertArrayHasKey('team_id', $response);

        $this->assertEquals('New Player', $response['name']);
        $this->assertEquals('newplayer#9999', $response['discord']);
        $this->assertEquals($team->getId(), $response['team_id']);
    }

    public function testCreatePlayerWithInvalidData(): void
    {
        $playerData = [
            'discord' => 'invalid#1234'
            // name manquant
        ];

        $response = $this->jsonRequest('POST', '/api/players', $playerData);

        $this->assertResponseStatusCodeSame(400);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
    }

    public function testUpdatePlayer(): void
    {
        $team = new Team();
        $team->setName('Original Team');

        $player = new Player();
        $player->setName('Original Name');
        $player->setDiscord('original#1234');
        $player->setTeam($team);

        $this->entityManager->persist($team);
        $this->entityManager->persist($player);
        $this->entityManager->flush();

        $updateData = [
            'name' => 'Updated Name',
            'discord' => 'updated#5678'
        ];

        $response = $this->jsonRequest('PUT', '/api/players/' . $player->getId(), $updateData);

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);

        $this->assertEquals('Updated Name', $response['name']);
        $this->assertEquals('updated#5678', $response['discord']);
    }

    public function testUpdatePlayerNotFound(): void
    {
        $updateData = [
            'name' => 'Updated Name'
        ];

        $response = $this->jsonRequest('PUT', '/api/players/999', $updateData);

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Player with id 999 not found', $response['error']);
    }

    public function testDeletePlayer(): void
    {
        $player = new Player();
        $player->setName('Player to Delete');
        $player->setDiscord('delete#1234');

        $this->entityManager->persist($player);
        $this->entityManager->flush();

        $response = $this->jsonRequest('DELETE', '/api/players/' . $player->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertArrayHasKey('message', $response);
        $this->assertEquals('Player deleted successfully', $response['message']);
    }

    public function testDeletePlayerNotFound(): void
    {
        $response = $this->jsonRequest('DELETE', '/api/players/999');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Player with id 999 not found', $response['error']);
    }
}
