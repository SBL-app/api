<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\Team;
use App\Entity\Player;
use App\Entity\Division;
use App\Entity\Season;
use App\Entity\TeamStat;

/**
 * Tests fonctionnels pour le TeamController
 */
class TeamControllerTest extends ApiTestCase
{
    public function testGetTeamsEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/teams');

        $this->assertResponseStatusCode(200);
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetTeamsWithData(): void
    {
        // Créer des données de test
        $team1 = new Team();
        $team1->setName('Équipe Alpha');
        $this->entityManager->persist($team1);

        $team2 = new Team();
        $team2->setName('Équipe Beta');
        $this->entityManager->persist($team2);

        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/teams');

        $this->assertResponseStatusCode(200);
        $this->assertIsArray($response);
        $this->assertCount(2, $response);

        // Vérifier la structure de la première équipe
        $this->assertArrayHasKey('id', $response[0]);
        $this->assertArrayHasKey('name', $response[0]);
        $this->assertArrayHasKey('captain', $response[0]);
        $this->assertArrayHasKey('captain_id', $response[0]);

        $this->assertEquals('Équipe Alpha', $response[0]['name']);
        $this->assertNull($response[0]['captain']);
        $this->assertNull($response[0]['captain_id']);
    }

    public function testGetTeamById(): void
    {
        // Créer une équipe avec un capitaine
        $captain = new Player();
        $captain->setName('Capitaine Test');
        $captain->setDiscord('captain#1234');
        $this->entityManager->persist($captain);

        $team = new Team();
        $team->setName('Équipe Test');
        $team->setCaptain($captain);
        $this->entityManager->persist($team);

        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/teams/' . $team->getId());

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('name', $response);
        $this->assertArrayHasKey('captain', $response);
        $this->assertArrayHasKey('captain_id', $response);

        $this->assertEquals($team->getId(), $response['id']);
        $this->assertEquals('Équipe Test', $response['name']);
        $this->assertEquals('Capitaine Test', $response['captain']);
        $this->assertEquals($captain->getId(), $response['captain_id']);
    }

    public function testGetTeamByIdNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/teams/999');

        $this->assertResponseStatusCode(404);
        $this->assertNotNull($response);
        $this->assertArrayHasKey('detail', $response);
        $this->assertEquals('Team with id 999 not found', $response['detail']);
    }

    public function testGetTeamWithDetails(): void
    {
        // Créer des données de test complètes
        $season = new Season();
        $season->setName('Saison 2024');
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Division A');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $captain = new Player();
        $captain->setName('Capitaine Alpha');
        $captain->setDiscord('cap#1234');
        $this->entityManager->persist($captain);

        $player1 = new Player();
        $player1->setName('Joueur 1');
        $player1->setDiscord('player1#1234');
        $this->entityManager->persist($player1);

        $team = new Team();
        $team->setName('Équipe Complète');
        $team->setCaptain($captain);
        $this->entityManager->persist($team);

        // Associer les joueurs à l'équipe
        $captain->setTeam($team);
        $player1->setTeam($team);

        $teamStat = new TeamStat();
        $teamStat->setTeam($team);
        $teamStat->setDivision($division);
        $teamStat->setWins(8);
        $teamStat->setLosses(2);
        $teamStat->setPoints(16);
        $teamStat->setWinRounds(24);
        $teamStat->setLooseRounds(12);
        $this->entityManager->persist($teamStat);

        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/teams/' . $team->getId() . '?expand=players,stats');

        $this->assertResponseStatusCode(200);
        $this->assertNotNull($response);

        // Vérifier la structure de la réponse
        $this->assertArrayHasKey('team', $response);
        $this->assertArrayHasKey('players', $response);
        $this->assertArrayHasKey('stats', $response);
        $this->assertArrayHasKey('players_count', $response);
        $this->assertArrayHasKey('divisions_count', $response);

        // Vérifier les données de l'équipe
        $teamData = $response['team'];
        $this->assertEquals('Équipe Complète', $teamData['name']);
        $this->assertEquals('Capitaine Alpha', $teamData['captain']);

        // Vérifier les joueurs
        $this->assertCount(2, $response['players']);
        $this->assertEquals(2, $response['players_count']);

        // Vérifier les statistiques
        $this->assertCount(1, $response['stats']);
        $this->assertEquals(1, $response['divisions_count']);

        $stats = $response['stats'][0];
        $this->assertEquals(8, $stats['wins']);
        $this->assertEquals(2, $stats['losses']);
        $this->assertEquals(16, $stats['points']);
        $this->assertEquals(1, $stats['position']); // Première position car seule équipe
        $this->assertEquals(1, $stats['total_teams']);
    }

    public function testGetTeamDetailsNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/teams/999?expand=players,stats');

        $this->assertResponseStatusCode(404);
        $this->assertNotNull($response);
        $this->assertArrayHasKey('detail', $response);
        $this->assertEquals('Team with id 999 not found', $response['detail']);
    }

    public function testTeamWithoutCaptain(): void
    {
        $team = new Team();
        $team->setName('Équipe Sans Capitaine');
        $this->entityManager->persist($team);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/teams/' . $team->getId());

        $this->assertResponseStatusCode(200);
        $this->assertEquals('Équipe Sans Capitaine', $response['name']);
        $this->assertNull($response['captain']);
        $this->assertNull($response['captain_id']);
    }
}
