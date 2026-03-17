<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\MatchResult;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\MatchResultRepository;
use App\Tests\Functional\ApiTestCase;

class MatchResultRepositoryTest extends ApiTestCase
{
    private MatchResultRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->entityManager->getRepository(MatchResult::class);
    }

    private function createBaseEntities(): array
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

        $this->entityManager->flush();

        return [
            'season' => $season,
            'division' => $division,
            'game' => $game,
            'team1' => $team1,
            'team2' => $team2,
            'status' => $status,
        ];
    }

    private function createUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        $this->entityManager->persist($user);

        return $user;
    }

    public function testFindByGameReturnsResults(): void
    {
        $base = $this->createBaseEntities();
        $user = $this->createUser('user1');

        $result1 = new MatchResult();
        $result1->setGame($base['game']);
        $result1->setSubmittedBy($user);
        $result1->setTeam($base['team1']);
        $result1->setScore1(3);
        $result1->setScore2(1);
        $result1->setStatus(MatchResult::STATUS_PENDING);
        $this->entityManager->persist($result1);

        $result2 = new MatchResult();
        $result2->setGame($base['game']);
        $result2->setSubmittedBy($user);
        $result2->setTeam($base['team2']);
        $result2->setScore1(2);
        $result2->setScore2(2);
        $result2->contest('Wrong score reported');
        $this->entityManager->persist($result2);

        $this->entityManager->flush();

        $results = $this->repository->findByGame($base['game']);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(MatchResult::class, $results[0]);
        $this->assertInstanceOf(MatchResult::class, $results[1]);
    }

    public function testFindByGameReturnsEmptyForNoResults(): void
    {
        $base = $this->createBaseEntities();

        $results = $this->repository->findByGame($base['game']);

        $this->assertIsArray($results);
        $this->assertCount(0, $results);
    }

    public function testFindPendingByGameReturnsPendingResult(): void
    {
        $base = $this->createBaseEntities();
        $user = $this->createUser('user1');

        $result = new MatchResult();
        $result->setGame($base['game']);
        $result->setSubmittedBy($user);
        $result->setTeam($base['team1']);
        $result->setScore1(3);
        $result->setScore2(1);
        $this->entityManager->persist($result);

        $this->entityManager->flush();

        $pending = $this->repository->findPendingByGame($base['game']);

        $this->assertNotNull($pending);
        $this->assertEquals(MatchResult::STATUS_PENDING, $pending->getStatus());
        $this->assertTrue($pending->isPending());
    }

    public function testFindPendingByGameReturnsNullWhenNoPending(): void
    {
        $base = $this->createBaseEntities();
        $user = $this->createUser('user1');

        $result = new MatchResult();
        $result->setGame($base['game']);
        $result->setSubmittedBy($user);
        $result->setTeam($base['team1']);
        $result->setScore1(3);
        $result->setScore2(1);
        $result->validate();
        $this->entityManager->persist($result);

        $this->entityManager->flush();

        $pending = $this->repository->findPendingByGame($base['game']);

        $this->assertNull($pending);
    }

    public function testFindByGameDoesNotReturnResultsFromOtherGames(): void
    {
        $base = $this->createBaseEntities();
        $user = $this->createUser('user1');

        // Créer un deuxième game dans la même division
        $game2 = new Game();
        $game2->setTeam1($base['team1']);
        $game2->setTeam2($base['team2']);
        $game2->setDivision($base['division']);
        $game2->setStatus($base['status']);
        $game2->setWeek(2);
        $game2->setScore1(0);
        $game2->setScore2(0);
        $this->entityManager->persist($game2);

        // Résultat pour game1
        $result1 = new MatchResult();
        $result1->setGame($base['game']);
        $result1->setSubmittedBy($user);
        $result1->setTeam($base['team1']);
        $result1->setScore1(3);
        $result1->setScore2(1);
        $this->entityManager->persist($result1);

        // Résultat pour game2
        $result2 = new MatchResult();
        $result2->setGame($game2);
        $result2->setSubmittedBy($user);
        $result2->setTeam($base['team2']);
        $result2->setScore1(0);
        $result2->setScore2(4);
        $this->entityManager->persist($result2);

        $this->entityManager->flush();

        $results = $this->repository->findByGame($base['game']);

        $this->assertCount(1, $results);
        $this->assertSame($base['game']->getId(), $results[0]->getGame()->getId());
    }
}
