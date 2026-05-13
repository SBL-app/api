<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\TeamStat;
use App\Entity\User;
use App\Service\SeasonClosureService;
use App\Tests\Functional\ApiTestCase;

class SeasonClosureTest extends ApiTestCase
{
    private function createBaseContext(int $gameCount = 1): array
    {
        $season = new Season();
        $season->setName('Test Season');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));
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

        $pendingResultStatus = new GameStatus();
        $pendingResultStatus->setName('pending_result');
        $this->entityManager->persist($pendingResultStatus);

        $team1 = new Team();
        $team1->setName('Team Alpha');
        $this->entityManager->persist($team1);

        $team2 = new Team();
        $team2->setName('Team Beta');
        $this->entityManager->persist($team2);

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
        $stat1->setWins(0)->setLosses(0)->setTies(0)->setPoints(0)->setWinRounds(0)->setLooseRounds(0);
        $this->entityManager->persist($stat1);

        $stat2 = new TeamStat();
        $stat2->setTeam($team2);
        $stat2->setDivision($division);
        $stat2->setWins(0)->setLosses(0)->setTies(0)->setPoints(0)->setWinRounds(0)->setLooseRounds(0);
        $this->entityManager->persist($stat2);

        $games = [];
        for ($i = 0; $i < $gameCount; $i++) {
            $game = new Game();
            $game->setTeam1($team1);
            $game->setTeam2($team2);
            $game->setStatus($scheduledStatus);
            $game->setDivision($division);
            $game->setWeek($i + 1);
            $game->setScore1(0);
            $game->setScore2(0);
            $game->setDate(new \DateTime('+7 days'));
            $this->entityManager->persist($game);
            $games[] = $game;
        }

        $this->entityManager->flush();

        return [
            'season' => $season,
            'division' => $division,
            'team1' => $team1,
            'team2' => $team2,
            'captain1' => $captain1,
            'captain2' => $captain2,
            'admin' => $admin,
            'games' => $games,
            'game' => $games[0],
            'playedStatus' => $playedStatus,
            'scheduledStatus' => $scheduledStatus,
        ];
    }

    private function getSeasonClosureService(): SeasonClosureService
    {
        return static::getContainer()->get(SeasonClosureService::class);
    }

    // ========================================================================
    // Auto-finalisation de division
    // ========================================================================

    public function testDivisionAutoFinalizedWhenAllGamesPlayed(): void
    {
        $ctx = $this->createBaseContext(1);

        $ctx['game']->setStatus($ctx['playedStatus']);
        $this->entityManager->flush();

        $this->getSeasonClosureService()->onGamePlayed($ctx['game']);

        $this->entityManager->refresh($ctx['division']);
        $this->assertTrue($ctx['division']->isFinalized());
    }

    public function testDivisionNotFinalizedWhenSomeGamesNotPlayed(): void
    {
        $ctx = $this->createBaseContext(2);

        $ctx['games'][0]->setStatus($ctx['playedStatus']);
        $this->entityManager->flush();

        $this->getSeasonClosureService()->onGamePlayed($ctx['games'][0]);

        $this->entityManager->refresh($ctx['division']);
        $this->assertFalse($ctx['division']->isFinalized());
    }

    public function testDivisionNotFinalizedWhenGameReported(): void
    {
        $ctx = $this->createBaseContext(1);

        $reportedStatus = new GameStatus();
        $reportedStatus->setName('reported');
        $this->entityManager->persist($reportedStatus);

        $ctx['game']->setStatus($reportedStatus);
        $this->entityManager->flush();

        $this->getSeasonClosureService()->onGamePlayed($ctx['game']);

        $this->entityManager->refresh($ctx['division']);
        $this->assertFalse($ctx['division']->isFinalized());
    }

    public function testDivisionNotFinalizedTwice(): void
    {
        $ctx = $this->createBaseContext(1);

        $ctx['game']->setStatus($ctx['playedStatus']);
        $ctx['division']->setIsFinalized(true);
        $this->entityManager->flush();

        $service = $this->getSeasonClosureService();
        $service->onGamePlayed($ctx['game']);

        $this->entityManager->refresh($ctx['division']);
        $this->assertTrue($ctx['division']->isFinalized());
    }

    // ========================================================================
    // Auto-finalisation de saison
    // ========================================================================

    public function testSeasonAutoFinalizedWhenAllDivisionsFinalized(): void
    {
        $ctx = $this->createBaseContext(1);

        $ctx['game']->setStatus($ctx['playedStatus']);
        $this->entityManager->flush();

        $this->getSeasonClosureService()->onGamePlayed($ctx['game']);

        $this->entityManager->refresh($ctx['season']);
        $this->assertTrue($ctx['season']->isFinalized());
    }

    public function testSeasonNotFinalizedWhenOneDivisionStillPending(): void
    {
        $ctx = $this->createBaseContext(1);

        $division2 = new Division();
        $division2->setName('Division B');
        $division2->setSeason($ctx['season']);
        $this->entityManager->persist($division2);

        $game2 = new Game();
        $game2->setTeam1($ctx['team1']);
        $game2->setTeam2($ctx['team2']);
        $game2->setStatus($ctx['scheduledStatus']);
        $game2->setDivision($division2);
        $game2->setWeek(1);
        $game2->setScore1(0);
        $game2->setScore2(0);
        $game2->setDate(new \DateTime('+7 days'));
        $this->entityManager->persist($game2);

        $ctx['game']->setStatus($ctx['playedStatus']);
        $this->entityManager->flush();

        $this->getSeasonClosureService()->onGamePlayed($ctx['game']);

        $this->entityManager->refresh($ctx['season']);
        $this->assertFalse($ctx['season']->isFinalized());
    }

    // ========================================================================
    // Validation préventive manuelle (DivisionController PATCH)
    // ========================================================================

    public function testManualFinalizationBlockedWhenGamesNotPlayed(): void
    {
        $ctx = $this->createBaseContext(1);

        $this->client->loginUser($ctx['admin'], 'api');

        $response = $this->jsonRequest('PATCH', '/api/divisions/' . $ctx['division']->getId(), [
            'is_finalized' => true,
        ]);

        $this->assertResponseStatusCode(409);
        $this->assertStringContainsString('Cannot finalize division', $response['detail']);
    }

    public function testManualFinalizationAllowedWhenAllGamesPlayed(): void
    {
        $ctx = $this->createBaseContext(1);

        $ctx['game']->setStatus($ctx['playedStatus']);
        $this->entityManager->flush();

        $this->client->loginUser($ctx['admin'], 'api');

        $response = $this->jsonRequest('PATCH', '/api/divisions/' . $ctx['division']->getId(), [
            'is_finalized' => true,
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertTrue($response['is_finalized']);
    }

    // ========================================================================
    // is_finalized exposé dans les réponses API
    // ========================================================================

    public function testSeasonResponseIncludesIsFinalized(): void
    {
        $ctx = $this->createBaseContext(1);

        $response = $this->jsonRequest('GET', '/api/seasons/' . $ctx['season']->getId());

        $this->assertResponseStatusCode(200);
        $this->assertArrayHasKey('is_finalized', $response);
        $this->assertFalse($response['is_finalized']);
    }
}
