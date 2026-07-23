<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\Season;
use App\Entity\Team;
use App\Repository\GameRepository;
use App\Tests\Functional\ApiTestCase;

class GameRepositoryTest extends ApiTestCase
{
    private GameRepository $gameRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gameRepository = static::getContainer()->get(GameRepository::class);
    }

    private function createGame(\DateTime $date, ?\DateTime $reminderSentAt = null): Game
    {
        $season = new Season();
        $season->setName('Test Season');
        $season->setStartDate(new \DateTime());
        $season->setEndDate(new \DateTime('+3 months'));
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Test Division');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $status = new GameStatus();
        $status->setName('scheduled');
        $this->entityManager->persist($status);

        $teamA = new Team();
        $teamA->setName('Team A');
        $this->entityManager->persist($teamA);

        $teamB = new Team();
        $teamB->setName('Team B');
        $this->entityManager->persist($teamB);

        $game = new Game();
        $game->setTeam1($teamA);
        $game->setTeam2($teamB);
        $game->setDivision($division);
        $game->setStatus($status);
        $game->setDate($date);
        $game->setWeek(1);
        $game->setScore1(0);
        $game->setScore2(0);

        if ($reminderSentAt !== null) {
            $game->setReminderSentAt($reminderSentAt);
        }

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $game;
    }

    public function testFindGamesForReminderReturnsUpcomingGames(): void
    {
        $gameDate = new \DateTime('+12 hours');
        $this->createGame($gameDate);

        $now = new \DateTime();
        $in24h = (clone $now)->modify('+24 hours');

        $results = $this->gameRepository->findGamesForReminder($now, $in24h);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(Game::class, $results[0]);
    }

    public function testFindGamesForReminderExcludesAlreadyReminded(): void
    {
        $gameDate = new \DateTime('+12 hours');
        $this->createGame($gameDate, new \DateTime());

        $now = new \DateTime();
        $in24h = (clone $now)->modify('+24 hours');

        $results = $this->gameRepository->findGamesForReminder($now, $in24h);

        $this->assertCount(0, $results);
    }

    public function testFindGamesForReminderExcludesOutOfRange(): void
    {
        $gameDate = new \DateTime('+48 hours');
        $this->createGame($gameDate);

        $now = new \DateTime();
        $in24h = (clone $now)->modify('+24 hours');

        $results = $this->gameRepository->findGamesForReminder($now, $in24h);

        $this->assertCount(0, $results);
    }
}
