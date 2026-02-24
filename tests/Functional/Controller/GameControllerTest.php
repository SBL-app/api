<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\Game;
use App\Entity\Division;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\GameStatus;

class GameControllerTest extends ApiTestCase
{
    public function testGetGamesEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/games');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetGamesWithData(): void
    {
        // Créer les entités nécessaires
        $season = new Season();
        $season->setName('Season 2024');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $division = new Division();
        $division->setName('Division Test');
        $division->setSeason($season);

        $team1 = new Team();
        $team1->setName('Team 1');

        $team2 = new Team();
        $team2->setName('Team 2');

        $gameStatus = new GameStatus();
        $gameStatus->setName('en attente');

        $game = new Game();
        $game->setDate(new \DateTime('2024-03-15'));
        $game->setWeek(1);
        $game->setScore1(10);
        $game->setScore2(8);
        $game->setDivision($division);
        $game->setTeam1($team1);
        $game->setTeam2($team2);
        $game->setStatus($gameStatus);

        $this->entityManager->persist($season);
        $this->entityManager->persist($division);
        $this->entityManager->persist($team1);
        $this->entityManager->persist($team2);
        $this->entityManager->persist($gameStatus);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/games');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $gameData = $response[0];
        $this->assertArrayHasKey('id', $gameData);
        $this->assertArrayHasKey('date', $gameData);
        $this->assertArrayHasKey('division', $gameData);
        $this->assertArrayHasKey('team1', $gameData);
        $this->assertArrayHasKey('team2', $gameData);
        $this->assertArrayHasKey('status', $gameData);
    }

    public function testGetGameById(): void
    {
        // Créer les entités nécessaires
        $season = new Season();
        $season->setName('Season 2024');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $division = new Division();
        $division->setName('Division Test');
        $division->setSeason($season);

        $team1 = new Team();
        $team1->setName('Team 1');

        $team2 = new Team();
        $team2->setName('Team 2');

        $gameStatus = new GameStatus();
        $gameStatus->setName('programmé');

        $game = new Game();
        $game->setDate(new \DateTime('2024-04-20'));
        $game->setWeek(2);
        $game->setScore1(12);
        $game->setScore2(9);
        $game->setDivision($division);
        $game->setTeam1($team1);
        $game->setTeam2($team2);
        $game->setStatus($gameStatus);

        $this->entityManager->persist($season);
        $this->entityManager->persist($division);
        $this->entityManager->persist($team1);
        $this->entityManager->persist($team2);
        $this->entityManager->persist($gameStatus);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/games/' . $game->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('date', $response);
        $this->assertArrayHasKey('division', $response);
        $this->assertArrayHasKey('team1', $response);
        $this->assertArrayHasKey('team2', $response);
        $this->assertArrayHasKey('status', $response);
    }

    public function testGetGameByIdNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/games/999');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('Game with id 999 not found', $response['error']);
    }

    public function testGetGamesByDivision(): void
    {
        // Créer les entités nécessaires
        $season = new Season();
        $season->setName('Season 2024');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $division = new Division();
        $division->setName('Division Filter Test');
        $division->setSeason($season);

        $team1 = new Team();
        $team1->setName('Team A');

        $team2 = new Team();
        $team2->setName('Team B');

        $gameStatus = new GameStatus();
        $gameStatus->setName('en cours');

        $game = new Game();
        $game->setDate(new \DateTime('2024-05-10'));
        $game->setWeek(3);
        $game->setScore1(14);
        $game->setScore2(11);
        $game->setDivision($division);
        $game->setTeam1($team1);
        $game->setTeam2($team2);
        $game->setStatus($gameStatus);

        $this->entityManager->persist($season);
        $this->entityManager->persist($division);
        $this->entityManager->persist($team1);
        $this->entityManager->persist($team2);
        $this->entityManager->persist($gameStatus);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/games?division=' . $division->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $gameData = $response[0];
        $this->assertEquals($division->getName(), $gameData['division']);
    }

    public function testGetGamesByTeam(): void
    {
        // Créer les entités nécessaires  
        $season = new Season();
        $season->setName('Season 2024');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $division = new Division();
        $division->setName('Division Test');
        $division->setSeason($season);

        $team1 = new Team();
        $team1->setName('Team Filter');

        $team2 = new Team();
        $team2->setName('Team Other');

        $gameStatus = new GameStatus();
        $gameStatus->setName('joué');

        $game = new Game();
        $game->setDate(new \DateTime('2024-06-15'));
        $game->setWeek(4);
        $game->setScore1(16);
        $game->setScore2(13);
        $game->setDivision($division);
        $game->setTeam1($team1);
        $game->setTeam2($team2);
        $game->setStatus($gameStatus);

        $this->entityManager->persist($season);
        $this->entityManager->persist($division);
        $this->entityManager->persist($team1);
        $this->entityManager->persist($team2);
        $this->entityManager->persist($gameStatus);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/games?team=' . $team1->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $gameData = $response[0];
        $this->assertTrue($gameData['team1'] == $team1->getName() || $gameData['team2'] == $team1->getName());
    }
}
