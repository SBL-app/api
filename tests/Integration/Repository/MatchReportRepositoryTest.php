<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\MatchReport;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\MatchReportRepository;
use App\Tests\Functional\ApiTestCase;

class MatchReportRepositoryTest extends ApiTestCase
{
    private MatchReportRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->entityManager->getRepository(MatchReport::class);
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

    public function testCountByTeamAndSeason(): void
    {
        $base = $this->createBaseEntities();
        $user1 = $this->createUser('user1');
        $user2 = $this->createUser('user2');

        $report1 = new MatchReport();
        $report1->setGame($base['game']);
        $report1->setTeam($base['team1']);
        $report1->setRequestedBy($user1);
        $this->entityManager->persist($report1);

        $report2 = new MatchReport();
        $report2->setGame($base['game']);
        $report2->setTeam($base['team1']);
        $report2->setRequestedBy($user2);
        $this->entityManager->persist($report2);

        $this->entityManager->flush();

        $count = $this->repository->countByTeamAndSeason($base['team1'], $base['season']);

        $this->assertEquals(2, $count);
    }

    public function testCountByTeamAndSeasonIgnoresOtherTeams(): void
    {
        $base = $this->createBaseEntities();
        $user = $this->createUser('user1');

        $report1 = new MatchReport();
        $report1->setGame($base['game']);
        $report1->setTeam($base['team1']);
        $report1->setRequestedBy($user);
        $this->entityManager->persist($report1);

        $report2 = new MatchReport();
        $report2->setGame($base['game']);
        $report2->setTeam($base['team2']);
        $report2->setRequestedBy($user);
        $this->entityManager->persist($report2);

        $this->entityManager->flush();

        $countTeam1 = $this->repository->countByTeamAndSeason($base['team1'], $base['season']);

        $this->assertEquals(1, $countTeam1);
    }

    public function testCountByTeamAndSeasonIgnoresOtherSeasons(): void
    {
        $base = $this->createBaseEntities();
        $user = $this->createUser('user1');

        // Créer une autre saison avec sa propre division et game
        $otherSeason = new Season();
        $otherSeason->setName('Season 2025');
        $otherSeason->setStartDate(new \DateTime('2025-01-01'));
        $otherSeason->setEndDate(new \DateTime('2025-12-31'));
        $this->entityManager->persist($otherSeason);

        $otherDivision = new Division();
        $otherDivision->setName('Division B');
        $otherDivision->setSeason($otherSeason);
        $this->entityManager->persist($otherDivision);

        $otherGame = new Game();
        $otherGame->setTeam1($base['team1']);
        $otherGame->setTeam2($base['team2']);
        $otherGame->setDivision($otherDivision);
        $otherGame->setStatus($base['status']);
        $otherGame->setWeek(1);
        $otherGame->setScore1(0);
        $otherGame->setScore2(0);
        $this->entityManager->persist($otherGame);

        $report = new MatchReport();
        $report->setGame($otherGame);
        $report->setTeam($base['team1']);
        $report->setRequestedBy($user);
        $this->entityManager->persist($report);

        $this->entityManager->flush();

        // Le report est dans otherSeason, donc count pour season d'origine = 0
        $count = $this->repository->countByTeamAndSeason($base['team1'], $base['season']);

        $this->assertEquals(0, $count);
    }

    public function testFindByGame(): void
    {
        $base = $this->createBaseEntities();
        $user1 = $this->createUser('user1');
        $user2 = $this->createUser('user2');

        $report1 = new MatchReport();
        $report1->setGame($base['game']);
        $report1->setTeam($base['team1']);
        $report1->setRequestedBy($user1);
        $this->entityManager->persist($report1);

        $report2 = new MatchReport();
        $report2->setGame($base['game']);
        $report2->setTeam($base['team2']);
        $report2->setRequestedBy($user2);
        $this->entityManager->persist($report2);

        $this->entityManager->flush();

        $reports = $this->repository->findByGame($base['game']);

        $this->assertCount(2, $reports);
        $this->assertInstanceOf(MatchReport::class, $reports[0]);
        $this->assertInstanceOf(MatchReport::class, $reports[1]);
    }

    public function testFindByTeamAndSeason(): void
    {
        $base = $this->createBaseEntities();
        $user = $this->createUser('user1');

        // Report pour team1 dans la saison
        $report1 = new MatchReport();
        $report1->setGame($base['game']);
        $report1->setTeam($base['team1']);
        $report1->setRequestedBy($user);
        $report1->setReason('Scheduling conflict');
        $this->entityManager->persist($report1);

        // Report pour team2 dans la même saison (ne doit pas apparaître pour team1)
        $report2 = new MatchReport();
        $report2->setGame($base['game']);
        $report2->setTeam($base['team2']);
        $report2->setRequestedBy($user);
        $this->entityManager->persist($report2);

        $this->entityManager->flush();

        $reports = $this->repository->findByTeamAndSeason($base['team1'], $base['season']);

        $this->assertCount(1, $reports);
        $this->assertSame($base['team1']->getId(), $reports[0]->getTeam()->getId());
        $this->assertEquals('Scheduling conflict', $reports[0]->getReason());
    }
}
