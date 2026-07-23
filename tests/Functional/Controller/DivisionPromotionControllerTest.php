<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\Season;
use App\Entity\Division;
use App\Entity\Team;
use App\Entity\TeamStat;
use App\Entity\User;

/**
 * Tests du flux de promotion/relégation (issue api#36).
 */
class DivisionPromotionControllerTest extends ApiTestCase
{
    private function authenticateAsAdmin(): void
    {
        $admin = new User();
        $admin->setUsername('admin_' . uniqid());
        $admin->setPassword('hashed');
        $admin->setRoles(['ROLE_USER', 'ROLE_API', 'ROLE_ADMIN']);
        $admin->setIsActive(true);
        $this->entityManager->persist($admin);
        $this->entityManager->flush();
        $this->client->loginUser($admin, 'api');
    }

    private function makeDivision(Season $season, string $name, int $level, int $promo, int $releg): Division
    {
        $division = new Division();
        $division->setName($name);
        $division->setSeason($season);
        $division->setLevel($level);
        $division->setPromotionCount($promo);
        $division->setRelegationCount($releg);
        $this->entityManager->persist($division);
        return $division;
    }

    private function makeTeamStat(Division $division, string $teamName, int $points): Team
    {
        $team = new Team();
        $team->setName($teamName);
        $this->entityManager->persist($team);

        $stat = new TeamStat();
        $stat->setTeam($team);
        $stat->setDivision($division);
        $stat->setWins($points > 0 ? $points : 0);
        $stat->setLosses(0);
        $stat->setTies(0);
        $stat->setPoints($points);
        $stat->setWinRounds($points);
        $stat->setLooseRounds(0);
        $this->entityManager->persist($stat);

        return $team;
    }

    public function testPromotionPreviewComputesMovements(): void
    {
        $season = new Season();
        $season->setName('Saison Source');
        $this->entityManager->persist($season);

        $d1 = $this->makeDivision($season, 'Division 1', 1, 0, 1);
        $d2 = $this->makeDivision($season, 'Division 2', 2, 1, 0);

        $teamA = $this->makeTeamStat($d1, 'Team A', 6);
        $teamB = $this->makeTeamStat($d1, 'Team B', 3);
        $teamC = $this->makeTeamStat($d2, 'Team C', 6);
        $teamD = $this->makeTeamStat($d2, 'Team D', 3);

        $this->entityManager->flush();

        $this->authenticateAsAdmin();

        $response = $this->jsonRequest('GET', '/api/seasons/' . $season->getId() . '/promotion-preview');
        $this->assertResponseStatusCode(200);

        $byLevel = [];
        foreach ($response['divisions'] as $div) {
            $byLevel[$div['level']] = $div;
        }

        // Division niveau 1 (la plus haute) : aucune promotion, Team B (dernier) relégué.
        $this->assertCount(0, $byLevel[1]['promoted']);
        $this->assertCount(1, $byLevel[1]['relegated']);
        $this->assertEquals($teamB->getId(), $byLevel[1]['relegated'][0]['team_id']);
        $this->assertEquals(2, $byLevel[1]['relegated'][0]['to_level']);

        // Division niveau 2 : Team C (premier) promu, Team D maintenu.
        $this->assertCount(1, $byLevel[2]['promoted']);
        $this->assertEquals($teamC->getId(), $byLevel[2]['promoted'][0]['team_id']);
        $this->assertEquals(1, $byLevel[2]['promoted'][0]['to_level']);
        $this->assertCount(0, $byLevel[2]['relegated']);
    }

    public function testPromotionPreviewRequiresAdmin(): void
    {
        $season = new Season();
        $season->setName('Saison X');
        $this->entityManager->persist($season);
        $this->entityManager->flush();

        // Non authentifié → refus.
        $this->jsonRequest('GET', '/api/seasons/' . $season->getId() . '/promotion-preview');
        $this->assertContains($this->client->getResponse()->getStatusCode(), [401, 403]);
    }

    public function testApplyPromotionsToTargetSeason(): void
    {
        $source = new Season();
        $source->setName('Saison Source');
        $this->entityManager->persist($source);

        $d1 = $this->makeDivision($source, 'Division 1', 1, 0, 1);
        $d2 = $this->makeDivision($source, 'Division 2', 2, 1, 0);

        $teamA = $this->makeTeamStat($d1, 'Team A', 6);
        $teamB = $this->makeTeamStat($d1, 'Team B', 3);
        $teamC = $this->makeTeamStat($d2, 'Team C', 6);
        $teamD = $this->makeTeamStat($d2, 'Team D', 3);

        // Saison cible avec les mêmes niveaux.
        $target = new Season();
        $target->setName('Saison Cible');
        $this->entityManager->persist($target);
        $t1 = $this->makeDivision($target, 'Division 1 (N+1)', 1, 0, 1);
        $t2 = $this->makeDivision($target, 'Division 2 (N+1)', 2, 1, 0);

        $this->entityManager->flush();

        $this->authenticateAsAdmin();

        $response = $this->jsonRequest('POST', '/api/seasons/' . $source->getId() . '/apply-promotions', [
            'target_season_id' => $target->getId(),
        ]);

        $this->assertResponseStatusCode(200);
        // 4 équipes placées.
        $this->assertCount(4, $response['applied']);
        $this->assertCount(0, $response['skipped']);

        // Team B (relégué) doit se retrouver au niveau 2 de la saison cible.
        $stat = $this->entityManager->getRepository(TeamStat::class)->findOneBy(['team' => $teamB, 'division' => $t2]);
        $this->assertNotNull($stat);
        $this->assertEquals(0, $stat->getPoints());

        // Team C (promu) doit se retrouver au niveau 1 de la saison cible.
        $statC = $this->entityManager->getRepository(TeamStat::class)->findOneBy(['team' => $teamC, 'division' => $t1]);
        $this->assertNotNull($statC);
    }

    public function testApplyPromotionsRequiresTargetSeason(): void
    {
        $source = new Season();
        $source->setName('Saison Source');
        $this->entityManager->persist($source);
        $this->entityManager->flush();

        $this->authenticateAsAdmin();

        $this->jsonRequest('POST', '/api/seasons/' . $source->getId() . '/apply-promotions', []);
        $this->assertResponseStatusCode(400);
    }
}
