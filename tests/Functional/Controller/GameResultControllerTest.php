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
}
