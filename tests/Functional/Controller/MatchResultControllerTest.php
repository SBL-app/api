<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\MatchResult;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Tests\Functional\ApiTestCase;

class MatchResultControllerTest extends ApiTestCase
{
    private function createMatchContext(): array
    {
        $season = new Season();
        $season->setName('Test Season');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Test Division');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $scheduledStatus = new GameStatus();
        $scheduledStatus->setName('scheduled');
        $this->entityManager->persist($scheduledStatus);

        $playedStatus = new GameStatus();
        $playedStatus->setName('played');
        $this->entityManager->persist($playedStatus);

        $pendingResultStatus = new GameStatus();
        $pendingResultStatus->setName('pending_result');
        $this->entityManager->persist($pendingResultStatus);

        $contestedStatus = new GameStatus();
        $contestedStatus->setName('contested');
        $this->entityManager->persist($contestedStatus);

        $team1 = new Team();
        $team1->setName('Team Alpha');
        $this->entityManager->persist($team1);

        $team2 = new Team();
        $team2->setName('Team Beta');
        $this->entityManager->persist($team2);

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

        // Admin (ROLE_API necessaire pour passer l'access_control sur POST/PATCH /api/*)
        $admin = new User();
        $admin->setUsername('admin');
        $admin->setPassword('hashed');
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_API']);
        $admin->setIsActive(true);
        $this->entityManager->persist($admin);

        // Non-captain user
        $nonCaptain = new User();
        $nonCaptain->setUsername('noncaptain');
        $nonCaptain->setPassword('hashed');
        $nonCaptain->setRoles(['ROLE_USER', 'ROLE_API']);
        $nonCaptain->setIsActive(true);
        $this->entityManager->persist($nonCaptain);

        $game = new Game();
        $game->setTeam1($team1);
        $game->setTeam2($team2);
        $game->setStatus($scheduledStatus);
        $game->setDivision($division);
        $game->setWeek(1);
        $game->setScore1(0);
        $game->setScore2(0);
        $game->setDate(new \DateTime('+7 days'));
        $this->entityManager->persist($game);

        $this->entityManager->flush();

        return [
            'game' => $game,
            'team1' => $team1,
            'team2' => $team2,
            'captain1' => $captain1,
            'captain2' => $captain2,
            'admin' => $admin,
            'nonCaptain' => $nonCaptain,
        ];
    }

    private function createPendingResult(array $ctx): MatchResult
    {
        $result = new MatchResult();
        $result->setGame($ctx['game']);
        $result->setSubmittedBy($ctx['captain1']);
        $result->setTeam($ctx['team1']);
        $result->setScore1(3);
        $result->setScore2(1);

        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return $result;
    }

    private function createValidatedResult(array $ctx): MatchResult
    {
        $result = $this->createPendingResult($ctx);
        $result->validate();
        $this->entityManager->flush();

        return $result;
    }

    // ========================================================================
    // Submit Result
    // ========================================================================

    public function testSubmitResultSuccess(): void
    {
        $ctx = $this->createMatchContext();

        $this->client->loginUser($ctx['captain1'], 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/submit-result', [
            'score1' => 3,
            'score2' => 1,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals(3, $response['score1']);
        $this->assertEquals(1, $response['score2']);
        $this->assertEquals('pending', $response['status']);
        $this->assertEquals($ctx['game']->getId(), $response['game_id']);
        $this->assertEquals($ctx['captain1']->getId(), $response['submitted_by']);
        $this->assertEquals($ctx['team1']->getId(), $response['team_id']);
    }

    public function testSubmitResultForbiddenNonCaptain(): void
    {
        $ctx = $this->createMatchContext();

        $this->client->loginUser($ctx['nonCaptain'], 'api');

        $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/submit-result', [
            'score1' => 3,
            'score2' => 1,
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testSubmitResultForbiddenUnauthenticated(): void
    {
        $ctx = $this->createMatchContext();

        $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/submit-result', [
            'score1' => 3,
            'score2' => 1,
        ]);

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertGreaterThanOrEqual(400, $statusCode);
        $this->assertNotEquals(201, $statusCode);
    }

    public function testSubmitResultBadRequestMissingScores(): void
    {
        $ctx = $this->createMatchContext();

        $this->client->loginUser($ctx['captain1'], 'api');

        $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/submit-result', []);

        $this->assertResponseStatusCode(400);
    }

    public function testSubmitResultConflictPendingExists(): void
    {
        $ctx = $this->createMatchContext();
        $this->createPendingResult($ctx);

        $this->client->loginUser($ctx['captain1'], 'api');

        $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/submit-result', [
            'score1' => 2,
            'score2' => 2,
        ]);
        $this->assertResponseStatusCode(409);
    }

    // ========================================================================
    // Validate Result
    // ========================================================================

    public function testValidateResultSuccess(): void
    {
        $ctx = $this->createMatchContext();
        $result = $this->createPendingResult($ctx);

        $this->client->loginUser($ctx['captain2'], 'api');

        $response = $this->jsonRequest('PATCH', '/api/games/' . $ctx['game']->getId() . '/results/' . $result->getId() . '/validate');

        $this->assertResponseStatusCode(200);
        $this->assertEquals('validated', $response['status']);
        $this->assertNotNull($response['validated_at']);

        // Verifier que le game a ete mis a jour
        $this->entityManager->clear();
        $game = $this->entityManager->find(Game::class, $ctx['game']->getId());
        $this->assertEquals(3, $game->getScore1());
        $this->assertEquals(1, $game->getScore2());
        $this->assertEquals(1, $game->getWinner());
        $this->assertEquals('played', $game->getStatus()->getName());
    }

    public function testValidateResultForbiddenSameTeam(): void
    {
        $ctx = $this->createMatchContext();
        $result = $this->createPendingResult($ctx);

        // Captain1 essaie de valider son propre resultat -> interdit
        $this->client->loginUser($ctx['captain1'], 'api');

        $this->jsonRequest('PATCH', '/api/games/' . $ctx['game']->getId() . '/results/' . $result->getId() . '/validate');

        $this->assertResponseStatusCode(403);
    }

    public function testValidateResultNotPending(): void
    {
        $ctx = $this->createMatchContext();
        $result = $this->createValidatedResult($ctx);

        $this->client->loginUser($ctx['captain2'], 'api');

        $this->jsonRequest('PATCH', '/api/games/' . $ctx['game']->getId() . '/results/' . $result->getId() . '/validate');

        $this->assertResponseStatusCode(409);
    }

    // ========================================================================
    // Contest Result
    // ========================================================================

    public function testContestResultSuccess(): void
    {
        $ctx = $this->createMatchContext();
        $result = $this->createPendingResult($ctx);

        $this->client->loginUser($ctx['captain2'], 'api');

        $response = $this->jsonRequest('PATCH', '/api/games/' . $ctx['game']->getId() . '/results/' . $result->getId() . '/contest', [
            'reason' => 'The score was actually 2-2',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertEquals('contested', $response['status']);
        $this->assertEquals('The score was actually 2-2', $response['contest_reason']);

        // Verifier que le statut du game est passe a 'contested'
        $this->entityManager->clear();
        $game = $this->entityManager->find(Game::class, $ctx['game']->getId());
        $this->assertEquals('contested', $game->getStatus()->getName());
    }

    public function testContestResultRequiresReason(): void
    {
        $ctx = $this->createMatchContext();
        $result = $this->createPendingResult($ctx);

        $this->client->loginUser($ctx['captain2'], 'api');

        $this->jsonRequest('PATCH', '/api/games/' . $ctx['game']->getId() . '/results/' . $result->getId() . '/contest', []);

        $this->assertResponseStatusCode(400);
    }

    // ========================================================================
    // Admin Validate
    // ========================================================================

    public function testAdminValidateSuccess(): void
    {
        $ctx = $this->createMatchContext();
        $result = $this->createPendingResult($ctx);

        $this->client->loginUser($ctx['admin'], 'api');

        $response = $this->jsonRequest('PATCH', '/api/games/' . $ctx['game']->getId() . '/results/' . $result->getId() . '/admin-validate', [
            'score1' => 2,
            'score2' => 2,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertEquals('validated', $response['status']);
        $this->assertEquals(2, $response['score1']);
        $this->assertEquals(2, $response['score2']);
        $this->assertNotNull($response['validated_at']);

        // Verifier que le game a ete mis a jour avec les scores corriges
        $this->entityManager->clear();
        $game = $this->entityManager->find(Game::class, $ctx['game']->getId());
        $this->assertEquals(2, $game->getScore1());
        $this->assertEquals(2, $game->getScore2());
        $this->assertNull($game->getWinner()); // egalite
        $this->assertEquals('played', $game->getStatus()->getName());
    }

    // ========================================================================
    // Get Game Results
    // ========================================================================

    public function testGetGameResults(): void
    {
        $ctx = $this->createMatchContext();

        // Creer 2 resultats manuellement en BDD
        $result1 = new MatchResult();
        $result1->setGame($ctx['game']);
        $result1->setSubmittedBy($ctx['captain1']);
        $result1->setTeam($ctx['team1']);
        $result1->setScore1(3);
        $result1->setScore2(1);
        $this->entityManager->persist($result1);

        $result2 = new MatchResult();
        $result2->setGame($ctx['game']);
        $result2->setSubmittedBy($ctx['captain2']);
        $result2->setTeam($ctx['team2']);
        $result2->setScore1(2);
        $result2->setScore2(2);
        $result2->setStatus(MatchResult::STATUS_CONTESTED);
        $this->entityManager->persist($result2);

        $this->entityManager->flush();

        // GET est public (pas besoin d'auth)
        $response = $this->jsonRequest('GET', '/api/games/' . $ctx['game']->getId() . '/results');

        $this->assertResponseStatusCode(200);
        $this->assertIsArray($response);
        $this->assertCount(2, $response);
        $this->assertArrayHasKey('id', $response[0]);
        $this->assertArrayHasKey('game_id', $response[0]);
        $this->assertArrayHasKey('submitted_by', $response[0]);
        $this->assertArrayHasKey('team_id', $response[0]);
        $this->assertArrayHasKey('score1', $response[0]);
        $this->assertArrayHasKey('score2', $response[0]);
        $this->assertArrayHasKey('status', $response[0]);
        $this->assertArrayHasKey('contest_reason', $response[0]);
        $this->assertArrayHasKey('created_at', $response[0]);
    }
}
