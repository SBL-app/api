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
use App\Tests\Functional\ApiTestCase;

class TeamStatRecalculateControllerTest extends ApiTestCase
{
    private function createDivisionContext(): array
    {
        $season = new Season();
        $season->setName('Season Recap');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Division Recap');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $playedStatus = new GameStatus();
        $playedStatus->setName('played');
        $this->entityManager->persist($playedStatus);

        $team1 = new Team();
        $team1->setName('Team Alpha');
        $this->entityManager->persist($team1);

        $team2 = new Team();
        $team2->setName('Team Beta');
        $this->entityManager->persist($team2);

        $admin = new User();
        $admin->setUsername('admin');
        $admin->setPassword('hashed');
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_API']);
        $admin->setIsActive(true);
        $this->entityManager->persist($admin);

        $regularUser = new User();
        $regularUser->setUsername('regular');
        $regularUser->setPassword('hashed');
        $regularUser->setRoles(['ROLE_USER', 'ROLE_API']);
        $regularUser->setIsActive(true);
        $this->entityManager->persist($regularUser);

        // Captain pour team1 (pour avoir setIsForfeit accessible)
        $captain1 = new User();
        $captain1->setUsername('cap1');
        $captain1->setPassword('hashed');
        $captain1->setRoles(['ROLE_USER', 'ROLE_API']);
        $captain1->setIsActive(true);
        $this->entityManager->persist($captain1);

        $member1 = new TeamMember();
        $member1->setRole(TeamMember::ROLE_CAPTAIN);
        $member1->setJoinedAt(new \DateTimeImmutable());
        $member1->setUser($captain1);
        $team1->addMember($member1);
        $team1->setCaptainUser($captain1);
        $this->entityManager->persist($member1);

        // Un match joué
        $game = new Game();
        $game->setDivision($division);
        $game->setTeam1($team1);
        $game->setTeam2($team2);
        $game->setScore1(3);
        $game->setScore2(1);
        $game->setWinner(1);
        $game->setWeek(1);
        $game->setStatus($playedStatus);
        $this->entityManager->persist($game);

        // Stats intentionnellement fausses
        $stat1 = new TeamStat();
        $stat1->setTeam($team1);
        $stat1->setDivision($division);
        $stat1->setWins(99);
        $stat1->setLosses(99);
        $stat1->setTies(0);
        $stat1->setPoints(99);
        $stat1->setWinRounds(99);
        $stat1->setLooseRounds(99);
        $this->entityManager->persist($stat1);

        $stat2 = new TeamStat();
        $stat2->setTeam($team2);
        $stat2->setDivision($division);
        $stat2->setWins(99);
        $stat2->setLosses(99);
        $stat2->setTies(0);
        $stat2->setPoints(99);
        $stat2->setWinRounds(99);
        $stat2->setLooseRounds(99);
        $this->entityManager->persist($stat2);

        $this->entityManager->flush();

        return [
            'division' => $division,
            'team1' => $team1,
            'team2' => $team2,
            'game' => $game,
            'stat1' => $stat1,
            'stat2' => $stat2,
            'admin' => $admin,
            'regularUser' => $regularUser,
        ];
    }

    public function testAdminCanRecalculateDivisionStats(): void
    {
        $ctx = $this->createDivisionContext();

        $this->client->loginUser($ctx['admin'], 'api');

        $response = $this->jsonRequest('POST', '/api/divisions/' . $ctx['division']->getId() . '/recalculate-stats');

        $this->assertResponseStatusCode(200);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('message', $response);

        $this->entityManager->clear();

        $stat1 = $this->entityManager->find(TeamStat::class, $ctx['stat1']->getId());
        $stat2 = $this->entityManager->find(TeamStat::class, $ctx['stat2']->getId());

        // Stats recalculées à partir du vrai jeu (3-1, team1 gagne)
        $this->assertEquals(1, $stat1->getWins());
        $this->assertEquals(0, $stat1->getLosses());
        $this->assertEquals(3, $stat1->getPoints());
        $this->assertEquals(3, $stat1->getWinRounds());
        $this->assertEquals(1, $stat1->getLooseRounds());

        $this->assertEquals(0, $stat2->getWins());
        $this->assertEquals(1, $stat2->getLosses());
        $this->assertEquals(0, $stat2->getPoints());
        $this->assertEquals(1, $stat2->getWinRounds());
        $this->assertEquals(3, $stat2->getLooseRounds());
    }

    public function testRecalculateRequiresAdmin(): void
    {
        $ctx = $this->createDivisionContext();

        $this->client->loginUser($ctx['regularUser'], 'api');

        $this->jsonRequest('POST', '/api/divisions/' . $ctx['division']->getId() . '/recalculate-stats');

        $this->assertResponseStatusCode(403);
    }

    public function testRecalculateRequiresAuthentication(): void
    {
        $ctx = $this->createDivisionContext();

        $this->jsonRequest('POST', '/api/divisions/' . $ctx['division']->getId() . '/recalculate-stats');

        $statusCode = $this->client->getResponse()->getStatusCode();
        $this->assertGreaterThanOrEqual(400, $statusCode);
        $this->assertNotEquals(200, $statusCode);
    }

    public function testRecalculateUnknownDivisionReturns404(): void
    {
        $ctx = $this->createDivisionContext();

        $this->client->loginUser($ctx['admin'], 'api');

        $this->jsonRequest('POST', '/api/divisions/99999/recalculate-stats');

        $this->assertResponseStatusCode(404);
    }
}
