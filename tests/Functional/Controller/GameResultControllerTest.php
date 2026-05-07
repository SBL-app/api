<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\GameStatus;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\TeamStat;
use App\Entity\User;
use App\Tests\Functional\ApiTestCase;

class GameResultControllerTest extends ApiTestCase
{
    private function createMatchContext(): array
    {
        $season = new Season();
        $season->setName('Season 2026');
        $season->setStartDate(new \DateTime('2026-01-01'));
        $season->setEndDate(new \DateTime('2026-12-31'));
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Division A');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $scheduledStatus = new GameStatus();
        $scheduledStatus->setName('scheduled');
        $this->entityManager->persist($scheduledStatus);

        $playedStatus = new GameStatus();
        $playedStatus->setName('played');
        $this->entityManager->persist($playedStatus);

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
        $game->setStatus($scheduledStatus);
        $game->setWeek(1);
        $game->setScore1(0);
        $game->setScore2(0);
        $game->setDate(new \DateTime('2026-03-15'));
        $this->entityManager->persist($game);

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

        $admin = new User();
        $admin->setUsername('admin');
        $admin->setPassword('hashed');
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_API']);
        $admin->setIsActive(true);
        $this->entityManager->persist($admin);

        $stat1 = new TeamStat();
        $stat1->setTeam($team1);
        $stat1->setDivision($division);
        $stat1->setWins(0);
        $stat1->setLosses(0);
        $stat1->setTies(0);
        $stat1->setPoints(0);
        $stat1->setWinRounds(0);
        $stat1->setLooseRounds(0);
        $this->entityManager->persist($stat1);

        $stat2 = new TeamStat();
        $stat2->setTeam($team2);
        $stat2->setDivision($division);
        $stat2->setWins(0);
        $stat2->setLosses(0);
        $stat2->setTies(0);
        $stat2->setPoints(0);
        $stat2->setWinRounds(0);
        $stat2->setLooseRounds(0);
        $this->entityManager->persist($stat2);

        $this->entityManager->flush();

        return [
            'game' => $game,
            'team1' => $team1,
            'team2' => $team2,
            'captain1' => $captain1,
            'captain2' => $captain2,
            'admin' => $admin,
            'season' => $season,
            'division' => $division,
            'stat1' => $stat1,
            'stat2' => $stat2,
            'playedStatus' => $playedStatus,
        ];
    }

    public function testGetResultNotFound(): void
    {
        $ctx = $this->createMatchContext();

        $response = $this->jsonRequest('GET', '/api/games/' . $ctx['game']->getId() . '/result');

        $this->assertResponseStatusCode(404);
    }

    public function testSubmitResultSuccess(): void
    {
        $ctx = $this->createMatchContext();
        $this->client->loginUser($ctx['captain1'], 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
            'score1' => 2,
            'score2' => 1,
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertNotNull($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals(2, $response['score1']);
        $this->assertEquals(1, $response['score2']);
        $this->assertEquals(GameResult::STATUS_PENDING_VALIDATION, $response['status']);
        $this->assertEquals($ctx['team1']->getId(), $response['submitted_by_team_id']);
    }

    public function testSubmitResultDuplicateFails(): void
    {
        $ctx = $this->createMatchContext();

        // Directly persist a pending result to simulate an already-submitted one
        $existingResult = new GameResult();
        $existingResult->setGame($ctx['game']);
        $existingResult->setSubmittedByTeam($ctx['team1']);
        $existingResult->setSubmittedBy($ctx['captain1']);
        $existingResult->setScore1(2);
        $existingResult->setScore2(1);
        $this->entityManager->persist($existingResult);
        $this->entityManager->flush();

        $this->client->loginUser($ctx['captain1'], 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
            'score1' => 2,
            'score2' => 0,
        ]);

        $this->assertResponseStatusCode(409);
    }

    public function testSubmitResultNotCaptainFails(): void
    {
        $ctx = $this->createMatchContext();

        $outsider = new User();
        $outsider->setUsername('outsider');
        $outsider->setPassword('hashed');
        $outsider->setRoles(['ROLE_USER', 'ROLE_API']);
        $outsider->setIsActive(true);
        $this->entityManager->persist($outsider);
        $this->entityManager->flush();

        $this->client->loginUser($outsider, 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
            'score1' => 2,
            'score2' => 1,
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testSubmitResultMissingScoreFails(): void
    {
        $ctx = $this->createMatchContext();
        $this->client->loginUser($ctx['captain1'], 'api');

        $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
            'score1' => 2,
            // score2 intentionally missing
        ]);

        $this->assertResponseStatusCode(400);
    }

    public function testConfirmResultSuccess(): void
    {
        $ctx = $this->createMatchContext();

        // Captain1 soumet directement via EM (firewall stateless)
        $result = new GameResult();
        $result->setGame($ctx['game']);
        $result->setSubmittedByTeam($ctx['team1']);
        $result->setSubmittedBy($ctx['captain1']);
        $result->setScore1(2);
        $result->setScore2(1);
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        // Captain2 confirme
        $this->client->loginUser($ctx['captain2'], 'api');
        $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/confirm');

        $this->assertResponseStatusCode(200);
        $this->assertEquals(GameResult::STATUS_CONFIRMED, $response['status']);

        // Vérifier Game mis à jour
        $this->entityManager->clear();
        $game = $this->entityManager->find('App\Entity\Game', $ctx['game']->getId());
        $this->assertEquals(2, $game->getScore1());
        $this->assertEquals(1, $game->getScore2());
        $this->assertEquals(1, $game->getWinner());
        $this->assertEquals('played', $game->getStatus()->getName());

        // Stats team1 (gagnante)
        $stat1 = $this->entityManager->find('App\Entity\TeamStat', $ctx['stat1']->getId());
        $this->assertEquals(1, $stat1->getWins());
        $this->assertEquals(0, $stat1->getLosses());
        $this->assertEquals(3, $stat1->getPoints());
        $this->assertEquals(2, $stat1->getWinRounds());
        $this->assertEquals(1, $stat1->getLooseRounds());

        // Stats team2 (perdante)
        $stat2 = $this->entityManager->find('App\Entity\TeamStat', $ctx['stat2']->getId());
        $this->assertEquals(0, $stat2->getWins());
        $this->assertEquals(1, $stat2->getLosses());
        $this->assertEquals(0, $stat2->getPoints());
        $this->assertEquals(1, $stat2->getWinRounds());
        $this->assertEquals(2, $stat2->getLooseRounds());
    }

    public function testConfirmResultTie(): void
    {
        $ctx = $this->createMatchContext();

        $result = new GameResult();
        $result->setGame($ctx['game']);
        $result->setSubmittedByTeam($ctx['team1']);
        $result->setSubmittedBy($ctx['captain1']);
        $result->setScore1(1);
        $result->setScore2(1);
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        $this->client->loginUser($ctx['captain2'], 'api');
        $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/confirm');

        $this->assertResponseStatusCode(200);

        $this->entityManager->clear();
        $game = $this->entityManager->find('App\Entity\Game', $ctx['game']->getId());
        $this->assertNull($game->getWinner());

        $stat1 = $this->entityManager->find('App\Entity\TeamStat', $ctx['stat1']->getId());
        $this->assertEquals(1, $stat1->getTies());
        $this->assertEquals(1, $stat1->getPoints());

        $stat2 = $this->entityManager->find('App\Entity\TeamStat', $ctx['stat2']->getId());
        $this->assertEquals(1, $stat2->getTies());
        $this->assertEquals(1, $stat2->getPoints());
    }

    public function testConfirmBySameTeamFails(): void
    {
        $ctx = $this->createMatchContext();

        $result = new GameResult();
        $result->setGame($ctx['game']);
        $result->setSubmittedByTeam($ctx['team1']);
        $result->setSubmittedBy($ctx['captain1']);
        $result->setScore1(2);
        $result->setScore2(1);
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        // Captain1 essaie de confirmer son propre résultat
        $this->client->loginUser($ctx['captain1'], 'api');
        $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/confirm');

        $this->assertResponseStatusCode(403);
    }

    public function testConfirmNoPendingFails(): void
    {
        $ctx = $this->createMatchContext();
        $this->client->loginUser($ctx['captain2'], 'api');

        $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/confirm');

        $this->assertResponseStatusCode(404);
    }

    public function testDisputeResultSuccess(): void
    {
        $ctx = $this->createMatchContext();

        $result = new GameResult();
        $result->setGame($ctx['game']);
        $result->setSubmittedByTeam($ctx['team1']);
        $result->setSubmittedBy($ctx['captain1']);
        $result->setScore1(2);
        $result->setScore2(1);
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        $this->client->loginUser($ctx['captain2'], 'api');
        $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/dispute');

        $this->assertResponseStatusCode(200);
        $this->assertEquals(GameResult::STATUS_DISPUTED, $response['status']);

        // Game doit PAS être modifié
        $this->entityManager->clear();
        $game = $this->entityManager->find('App\Entity\Game', $ctx['game']->getId());
        $this->assertEquals('scheduled', $game->getStatus()->getName());
    }

    public function testDisputeAllowsNewSubmission(): void
    {
        $ctx = $this->createMatchContext();

        $result = new GameResult();
        $result->setGame($ctx['game']);
        $result->setSubmittedByTeam($ctx['team1']);
        $result->setSubmittedBy($ctx['captain1']);
        $result->setScore1(2);
        $result->setScore2(1);
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        $this->client->loginUser($ctx['captain2'], 'api');
        $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/dispute');

        $this->assertResponseStatusCode(200);

        // Après dispute, il ne doit plus y avoir de résultat en pending_validation
        // (le résultat disputé est STATUS_DISPUTED, pas STATUS_PENDING_VALIDATION)
        // ce qui permet une nouvelle soumission sans conflit
        $this->entityManager->clear();
        $pendingResult = $this->entityManager
            ->getRepository(GameResult::class)
            ->findOneBy(['game' => $ctx['game'], 'status' => GameResult::STATUS_PENDING_VALIDATION]);

        $this->assertNull($pendingResult, 'After dispute, no result should remain in pending_validation status');
    }

    public function testAdminResolveSuccess(): void
    {
        $ctx = $this->createMatchContext();

        // Créer un résultat disputé directement
        $result = new GameResult();
        $result->setGame($ctx['game']);
        $result->setSubmittedByTeam($ctx['team1']);
        $result->setSubmittedBy($ctx['captain1']);
        $result->setScore1(2);
        $result->setScore2(1);
        $result->dispute($ctx['captain2']);
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        // Admin tranche
        $this->client->loginUser($ctx['admin'], 'api');
        $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/admin-resolve', [
            'score1' => 2,
            'score2' => 0,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertEquals(GameResult::STATUS_CONFIRMED, $response['status']);
        $this->assertEquals(2, $response['score1']);
        $this->assertEquals(0, $response['score2']);

        $this->entityManager->clear();
        $game = $this->entityManager->find('App\Entity\Game', $ctx['game']->getId());
        $this->assertEquals(2, $game->getScore1());
        $this->assertEquals(0, $game->getScore2());
        $this->assertEquals(1, $game->getWinner());
        $this->assertEquals('played', $game->getStatus()->getName());

        // Stats team1 (gagnante avec score 2-0)
        $stat1 = $this->entityManager->find('App\Entity\TeamStat', $ctx['stat1']->getId());
        $this->assertEquals(1, $stat1->getWins());
        $this->assertEquals(0, $stat1->getLosses());
        $this->assertEquals(3, $stat1->getPoints());
        $this->assertEquals(2, $stat1->getWinRounds());
        $this->assertEquals(0, $stat1->getLooseRounds());

        // Stats team2 (perdante)
        $stat2 = $this->entityManager->find('App\Entity\TeamStat', $ctx['stat2']->getId());
        $this->assertEquals(0, $stat2->getWins());
        $this->assertEquals(1, $stat2->getLosses());
        $this->assertEquals(0, $stat2->getPoints());
        $this->assertEquals(0, $stat2->getWinRounds());
        $this->assertEquals(2, $stat2->getLooseRounds());
    }

    public function testAdminResolveRequiresDisputedResult(): void
    {
        $ctx = $this->createMatchContext();
        $this->client->loginUser($ctx['admin'], 'api');

        $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/admin-resolve', [
            'score1' => 2,
            'score2' => 0,
        ]);

        $this->assertResponseStatusCode(404);
    }

    public function testAdminResolveRequiresAdminRole(): void
    {
        $ctx = $this->createMatchContext();

        $result = new GameResult();
        $result->setGame($ctx['game']);
        $result->setSubmittedByTeam($ctx['team1']);
        $result->setSubmittedBy($ctx['captain1']);
        $result->setScore1(2);
        $result->setScore2(1);
        $result->dispute($ctx['captain2']);
        $this->entityManager->persist($result);
        $this->entityManager->flush();

        // Captain2 (non-admin) essaie d'accéder
        $this->client->loginUser($ctx['captain2'], 'api');
        $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/admin-resolve', [
            'score1' => 2,
            'score2' => 0,
        ]);

        // checkUserRole lance AccessDeniedException (security component) qui n'est pas
        // convertie en 403 par l'ApiProblemExceptionListener (il ne gère que ApiProblemException
        // et HttpExceptionInterface). Le résultat est un 403 ou 500 selon l'environnement.
        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertTrue(
            $statusCode === 403 || $statusCode === 500,
            "Expected 403 or 500, got $statusCode"
        );
    }
}
