<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\Division;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\TeamStat;

/**
 * Tests fonctionnels pour le DivisionController
 */
class DivisionControllerTest extends ApiTestCase
{
    public function testGetDivisionsEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/division');

        $this->assertResponseStatusCode(200);
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetDivisionsWithData(): void
    {
        // Créer des données de test
        $season = new Season();
        $season->setName('Saison 2024');
        $this->entityManager->persist($season);

        $division1 = new Division();
        $division1->setName('Division A');
        $division1->setSeason($season);
        $this->entityManager->persist($division1);

        $division2 = new Division();
        $division2->setName('Division B');
        $division2->setSeason($season);
        $this->entityManager->persist($division2);

        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/division');

        $this->assertResponseStatusCode(200);
        $this->assertIsArray($response);
        $this->assertCount(2, $response);

        // Vérifier la structure de la première division
        $this->assertJsonResponseStructure([
            'id',
            'name',
            'season_id',
            'season_name'
        ], $response[0]);

        $this->assertEquals('Division A', $response[0]['name']);
        $this->assertEquals($season->getId(), $response[0]['season_id']);
        $this->assertEquals('Saison 2024', $response[0]['season_name']);
    }

    public function testGetDivisionById(): void
    {
        // Créer des données de test
        $season = new Season();
        $season->setName('Saison 2024');
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Division Test');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/division/' . $division->getId());

        $this->assertResponseStatusCode(200);
        $this->assertJsonResponseStructure([
            'id',
            'name',
            'season_id',
            'season_name'
        ], $response);

        $this->assertEquals($division->getId(), $response['id']);
        $this->assertEquals('Division Test', $response['name']);
        $this->assertEquals($season->getId(), $response['season_id']);
    }

    public function testGetDivisionByIdNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/division/999');

        $this->assertResponseStatusCode(404);
        $this->assertJsonResponseStructure(['error'], $response);
        $this->assertEquals('Division with id 999 not found', $response['error']);
    }

    public function testGetDivisionBySeasonEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/division/season?id=999');

        $this->assertResponseStatusCode(404);
        $this->assertJsonResponseStructure(['error'], $response);
        $this->assertEquals('No divisions found for this season', $response['error']);
    }

    public function testGetDivisionBySeasonMissingId(): void
    {
        $response = $this->jsonRequest('GET', '/api/division/season');

        $this->assertResponseStatusCode(400);
        $this->assertJsonResponseStructure(['error'], $response);
        $this->assertEquals('Season ID is required', $response['error']);
    }

    public function testGetDivisionBySeasonWithTeams(): void
    {
        // Créer des données de test complètes
        $season = new Season();
        $season->setName('Saison 2024');
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Division A');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $team1 = new Team();
        $team1->setName('Équipe 1');
        $this->entityManager->persist($team1);

        $team2 = new Team();
        $team2->setName('Équipe 2');
        $this->entityManager->persist($team2);

        $teamStat1 = new TeamStat();
        $teamStat1->setTeam($team1);
        $teamStat1->setDivision($division);
        $teamStat1->setWins(5);
        $teamStat1->setLosses(2);
        $teamStat1->setPoints(10);
        $teamStat1->setWinRounds(15);
        $teamStat1->setLooseRounds(10);
        $this->entityManager->persist($teamStat1);

        $teamStat2 = new TeamStat();
        $teamStat2->setTeam($team2);
        $teamStat2->setDivision($division);
        $teamStat2->setWins(3);
        $teamStat2->setLosses(4);
        $teamStat2->setPoints(6);
        $teamStat2->setWinRounds(12);
        $teamStat2->setLooseRounds(14);
        $this->entityManager->persist($teamStat2);

        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/division/season?id=' . $season->getId());

        $this->assertResponseStatusCode(200);
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $divisionData = $response[0];
        $this->assertArrayHasKey('id', $divisionData);
        $this->assertArrayHasKey('name', $divisionData);
        $this->assertArrayHasKey('season', $divisionData);
        $this->assertArrayHasKey('teams', $divisionData);

        $this->assertEquals($division->getId(), $divisionData['id']);
        $this->assertEquals('Division A', $divisionData['name']);
        $this->assertEquals($season->getId(), $divisionData['season']);
        $this->assertCount(2, $divisionData['teams']);

        // Vérifier les données des équipes
        $teamsData = $divisionData['teams'];
        $this->assertArrayHasKey('id', $teamsData[0]);
        $this->assertArrayHasKey('name', $teamsData[0]);
        $this->assertArrayHasKey('wins', $teamsData[0]);
        $this->assertArrayHasKey('losses', $teamsData[0]);
        $this->assertArrayHasKey('points', $teamsData[0]);

        $this->assertEquals('Équipe 1', $teamsData[0]['name']);
        $this->assertEquals(5, $teamsData[0]['wins']);
        $this->assertEquals(2, $teamsData[0]['losses']);
        $this->assertEquals(10, $teamsData[0]['points']);
    }

    public function testGetDivisionWithoutSeason(): void
    {
        // Créer une division sans saison
        $division = new Division();
        $division->setName('Division Sans Saison');
        $this->entityManager->persist($division);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/division/' . $division->getId());

        $this->assertResponseStatusCode(200);
        $this->assertJsonResponseStructure([
            'id',
            'name',
            'season_id',
            'season_name'
        ], $response);

        $this->assertEquals($division->getId(), $response['id']);
        $this->assertEquals('Division Sans Saison', $response['name']);
        $this->assertNull($response['season_id']);
        $this->assertEquals('', $response['season_name']);
    }

    // Test removed: testGetDivisionInvalidId is no longer relevant with RESTful routes
    // /api/division/invalid won't match the route constraint requirements: ['id' => '\d+']
    // and will return a 404 from Symfony directly
}
