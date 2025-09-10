<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\TeamStat;
use App\Entity\Team;
use App\Entity\Season;
use App\Entity\Division;

class TeamStatControllerTest extends ApiTestCase
{
    public function testGetTeamStatsEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/teamStats');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetTeamStatsWithData(): void
    {
        // Créer les entités nécessaires
        $season = new Season();
        $season->setName('Season 2024');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $division = new Division();
        $division->setName('Division Test');
        $division->setSeason($season);

        $team = new Team();
        $team->setName('Team Test');

        $teamStat = new TeamStat();
        $teamStat->setTeam($team);
        $teamStat->setDivision($division);
        $teamStat->setWins(5);
        $teamStat->setLosses(3);
        $teamStat->setPoints(15);
        $teamStat->setWinRounds(120);
        $teamStat->setLooseRounds(95);

        $this->entityManager->persist($season);
        $this->entityManager->persist($division);
        $this->entityManager->persist($team);
        $this->entityManager->persist($teamStat);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/teamStats');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $teamStatData = $response[0];
        $this->assertArrayHasKey('id', $teamStatData);
        $this->assertArrayHasKey('team_id', $teamStatData);
        $this->assertArrayHasKey('team_name', $teamStatData);
        $this->assertArrayHasKey('division_id', $teamStatData);
        $this->assertArrayHasKey('division_name', $teamStatData);
        $this->assertArrayHasKey('wins', $teamStatData);
        $this->assertArrayHasKey('losses', $teamStatData);
        $this->assertArrayHasKey('points', $teamStatData);
        $this->assertArrayHasKey('winRounds', $teamStatData);
    }

    public function testGetTeamStatByNonExistentTeam(): void
    {
        $response = $this->jsonRequest('GET', '/api/teamStats?team_id=999');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
    }

    public function testGetTeamStatsByTeam(): void
    {
        // Créer les entités nécessaires
        $season = new Season();
        $season->setName('Season Filter');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $division = new Division();
        $division->setName('Division Filter');
        $division->setSeason($season);

        $team = new Team();
        $team->setName('Team Filter');

        $teamStat = new TeamStat();
        $teamStat->setTeam($team);
        $teamStat->setDivision($division);
        $teamStat->setWins(6);
        $teamStat->setLosses(4);
        $teamStat->setPoints(18);
        $teamStat->setWinRounds(100);
        $teamStat->setLooseRounds(85);

        $this->entityManager->persist($season);
        $this->entityManager->persist($division);
        $this->entityManager->persist($team);
        $this->entityManager->persist($teamStat);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/teamStats?team_id=' . $team->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $teamStatData = $response[0];
        $this->assertEquals($team->getName(), $teamStatData['team_name']);
    }

    public function testGetTeamStatsByDivision(): void
    {
        // Créer les entités nécessaires
        $season = new Season();
        $season->setName('Season Division');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $division = new Division();
        $division->setName('Division Special');
        $division->setSeason($season);

        $team = new Team();
        $team->setName('Team Division');

        $teamStat = new TeamStat();
        $teamStat->setTeam($team);
        $teamStat->setDivision($division);
        $teamStat->setWins(7);
        $teamStat->setLosses(1);
        $teamStat->setPoints(21);
        $teamStat->setWinRounds(140);
        $teamStat->setLooseRounds(70);

        $this->entityManager->persist($season);
        $this->entityManager->persist($division);
        $this->entityManager->persist($team);
        $this->entityManager->persist($teamStat);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/teamStats?division_id=' . $division->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $teamStatData = $response[0];
        $this->assertEquals($division->getName(), $teamStatData['division_name']);
    }
}
