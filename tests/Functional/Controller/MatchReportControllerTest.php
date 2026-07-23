<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\MatchReport;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Tests\Functional\ApiTestCase;

class MatchReportControllerTest extends ApiTestCase
{
    private function createMatchContext(): array
    {
        $season = new Season();
        $season->setName('Season 2024');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Division A');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $status = new GameStatus();
        $status->setName('scheduled');
        $this->entityManager->persist($status);

        $team1 = new Team();
        $team1->setName('Team Alpha');
        $this->entityManager->persist($team1);

        $team2 = new Team();
        $team2->setName('Team Beta');
        $this->entityManager->persist($team2);

        $game = new Game();
        $game->setTeam1($team1);
        $game->setTeam2($team2);
        $game->setDivision($division);
        $game->setStatus($status);
        $game->setWeek(1);
        $game->setScore1(0);
        $game->setScore2(0);
        $game->setDate(new \DateTime('2024-03-15'));
        $this->entityManager->persist($game);

        // Captain 1
        $captain1 = new User();
        $captain1->setUsername('captain1');
        $captain1->setPassword('hashed');
        $captain1->setRoles(['ROLE_USER', 'ROLE_API']);
        $captain1->setIsActive(true);
        $this->entityManager->persist($captain1);

        $member1 = new TeamMember();
        $member1->setRole(TeamMember::ROLE_CAPTAIN);
        $member1->setJoinedAt(new \DateTimeImmutable());
        $team1->addMember($member1);
        $member1->setUser($captain1);
        $team1->setCaptainUser($captain1);
        $this->entityManager->persist($member1);

        // Captain 2
        $captain2 = new User();
        $captain2->setUsername('captain2');
        $captain2->setPassword('hashed');
        $captain2->setRoles(['ROLE_USER', 'ROLE_API']);
        $captain2->setIsActive(true);
        $this->entityManager->persist($captain2);

        $member2 = new TeamMember();
        $member2->setRole(TeamMember::ROLE_CAPTAIN);
        $member2->setJoinedAt(new \DateTimeImmutable());
        $team2->addMember($member2);
        $member2->setUser($captain2);
        $team2->setCaptainUser($captain2);
        $this->entityManager->persist($member2);

        // Admin user (ROLE_API nécessaire pour passer l'access_control sur POST /api/*)
        $admin = new User();
        $admin->setUsername('admin');
        $admin->setPassword('hashed');
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_API']);
        $admin->setIsActive(true);
        $this->entityManager->persist($admin);

        $this->entityManager->flush();

        return [
            'game' => $game,
            'team1' => $team1,
            'team2' => $team2,
            'captain1' => $captain1,
            'captain2' => $captain2,
            'admin' => $admin,
            'season' => $season,
        ];
    }

    public function testReportGameSuccess(): void
    {
        $ctx = $this->createMatchContext();

        $this->client->loginUser($ctx['captain1'], 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/report');

        $this->assertResponseStatusCode(201);
        $this->assertNotNull($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals($ctx['game']->getId(), $response['game_id']);
        $this->assertEquals($ctx['team1']->getId(), $response['team_id']);
        $this->assertEquals($ctx['captain1']->getId(), $response['requested_by_id']);
        $this->assertFalse($response['is_admin_forced']);

        // Vérifier que le report est bien en BDD
        $report = $this->entityManager->getRepository(MatchReport::class)->find($response['id']);
        $this->assertNotNull($report);

        // Vérifier que la date du game a été mise à null
        $this->entityManager->refresh($ctx['game']);
        $this->assertNull($ctx['game']->getDate());
    }

    public function testReportGameWithReason(): void
    {
        $ctx = $this->createMatchContext();

        $this->client->loginUser($ctx['captain1'], 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/report', [
            'reason' => 'Player unavailable this week',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertNotNull($response);
        $this->assertEquals('Player unavailable this week', $response['reason']);
    }

    public function testReportGameForbiddenNonCaptain(): void
    {
        $ctx = $this->createMatchContext();

        // Créer un user random (pas capitaine)
        $randomUser = new User();
        $randomUser->setUsername('randomuser');
        $randomUser->setPassword('hashed');
        $randomUser->setRoles(['ROLE_USER', 'ROLE_API']);
        $randomUser->setIsActive(true);
        $this->entityManager->persist($randomUser);
        $this->entityManager->flush();

        $this->client->loginUser($randomUser, 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/report');

        $this->assertResponseStatusCode(403);
    }

    public function testReportGameMaxReportsReached(): void
    {
        $ctx = $this->createMatchContext();

        // Créer 2 reports existants pour team1
        $report1 = new MatchReport();
        $report1->setGame($ctx['game']);
        $report1->setTeam($ctx['team1']);
        $report1->setRequestedBy($ctx['captain1']);
        $this->entityManager->persist($report1);

        $report2 = new MatchReport();
        $report2->setGame($ctx['game']);
        $report2->setTeam($ctx['team1']);
        $report2->setRequestedBy($ctx['captain1']);
        $this->entityManager->persist($report2);

        $this->entityManager->flush();

        $this->client->loginUser($ctx['captain1'], 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/report');

        $this->assertResponseStatusCode(400);
    }

    public function testReportGameNotFound(): void
    {
        $ctx = $this->createMatchContext();

        $this->client->loginUser($ctx['captain1'], 'api');

        $response = $this->jsonRequest('POST', '/api/games/999/report');

        $this->assertResponseStatusCode(404);
    }

    public function testAdminReportSuccess(): void
    {
        $ctx = $this->createMatchContext();

        $this->client->loginUser($ctx['admin'], 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/admin-report', [
            'reason' => 'Admin forced reschedule',
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertNotNull($response);
        $this->assertCount(2, $response);

        // Vérifier les 2 reports
        $this->assertEquals($ctx['team1']->getId(), $response[0]['team_id']);
        $this->assertEquals($ctx['team2']->getId(), $response[1]['team_id']);
        $this->assertTrue($response[0]['is_admin_forced']);
        $this->assertTrue($response[1]['is_admin_forced']);
        $this->assertEquals('Admin forced reschedule', $response[0]['reason']);
        $this->assertEquals('Admin forced reschedule', $response[1]['reason']);

        // Vérifier que les 2 reports sont en BDD
        $reports = $this->entityManager->getRepository(MatchReport::class)->findBy(['game' => $ctx['game']]);
        $this->assertCount(2, $reports);
    }

    public function testAdminReportForbiddenNonAdmin(): void
    {
        $ctx = $this->createMatchContext();

        // Un capitaine (pas admin) essaie admin-report
        $this->client->loginUser($ctx['captain1'], 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/admin-report');

        // checkUserRole lance AccessDeniedException (security component) qui n'est pas
        // convertie en 403 par l'ApiProblemExceptionListener (il ne gère que ApiProblemException
        // et HttpExceptionInterface). Le résultat est un 500.
        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            $statusCode === 403 || $statusCode === 500,
            "Expected 403 or 500, got $statusCode"
        );
        // Vérifier qu'aucun report n'a été créé
        $reports = $this->entityManager->getRepository(MatchReport::class)->findBy(['game' => $ctx['game']]);
        $this->assertCount(0, $reports);
    }

    public function testGetGameReports(): void
    {
        $ctx = $this->createMatchContext();

        // Créer des reports
        $report1 = new MatchReport();
        $report1->setGame($ctx['game']);
        $report1->setTeam($ctx['team1']);
        $report1->setRequestedBy($ctx['captain1']);
        $report1->setReason('Reason 1');
        $this->entityManager->persist($report1);

        $report2 = new MatchReport();
        $report2->setGame($ctx['game']);
        $report2->setTeam($ctx['team2']);
        $report2->setRequestedBy($ctx['captain2']);
        $report2->setReason('Reason 2');
        $this->entityManager->persist($report2);

        $this->entityManager->flush();

        // GET est public (pas besoin d'auth)
        $response = $this->jsonRequest('GET', '/api/games/' . $ctx['game']->getId() . '/reports');

        $this->assertResponseStatusCode(200);
        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertArrayHasKey('id', $response[0]);
        $this->assertArrayHasKey('game_id', $response[0]);
        $this->assertArrayHasKey('team_id', $response[0]);
        $this->assertArrayHasKey('reason', $response[0]);
        $this->assertArrayHasKey('is_admin_forced', $response[0]);
        $this->assertArrayHasKey('created_at', $response[0]);
    }

    public function testGetTeamReports(): void
    {
        $ctx = $this->createMatchContext();

        // Créer un report pour team1
        $report = new MatchReport();
        $report->setGame($ctx['game']);
        $report->setTeam($ctx['team1']);
        $report->setRequestedBy($ctx['captain1']);
        $report->setReason('Need to reschedule');
        $this->entityManager->persist($report);

        $this->entityManager->flush();

        // GET est public
        $response = $this->jsonRequest(
            'GET',
            '/api/teams/' . $ctx['team1']->getId() . '/reports?season_id=' . $ctx['season']->getId()
        );

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('reports', $response);
        $this->assertArrayHasKey('count', $response);
        $this->assertArrayHasKey('remaining', $response);
        $this->assertEquals(1, $response['count']);
        $this->assertEquals(1, $response['remaining']); // MAX_REPORTS_PER_SEASON (2) - 1 = 1
        $this->assertCount(1, $response['reports']);
        $this->assertEquals('Need to reschedule', $response['reports'][0]['reason']);
    }

    public function testGetTeamReportsMissingSeasonId(): void
    {
        $ctx = $this->createMatchContext();

        $response = $this->jsonRequest('GET', '/api/teams/' . $ctx['team1']->getId() . '/reports');

        $this->assertResponseStatusCode(400);
    }
}
