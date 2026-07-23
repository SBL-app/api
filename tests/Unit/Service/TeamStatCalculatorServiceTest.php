<?php

namespace App\Tests\Unit\Service;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\Team;
use App\Entity\TeamStat;
use App\Repository\GameRepository;
use App\Repository\TeamStatRepository;
use App\Service\TeamStatCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TeamStatCalculatorServiceTest extends TestCase
{
    private TeamStatRepository $teamStatRepository;
    private GameRepository $gameRepository;
    private EntityManagerInterface $entityManager;
    private TeamStatCalculatorService $service;

    protected function setUp(): void
    {
        $this->teamStatRepository = $this->createMock(TeamStatRepository::class);
        $this->gameRepository = $this->createMock(GameRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new TeamStatCalculatorService(
            $this->teamStatRepository,
            $this->gameRepository,
            $this->entityManager,
        );
    }

    // =========================================================================
    // updateStatsAfterGame — victoire team1
    // =========================================================================

    public function testTeam1WinIncreasesTeam1WinsAndDeductsLoserPoints(): void
    {
        $division = new Division();
        $division->setName('Div A');

        $team1 = new Team();
        $team1->setName('Team Alpha');

        $team2 = new Team();
        $team2->setName('Team Beta');

        $game = $this->buildPlayedGame($division, $team1, $team2, score1: 3, score2: 1, winner: 1);

        $stat1 = $this->buildZeroStat($team1, $division);
        $stat2 = $this->buildZeroStat($team2, $division);

        $this->teamStatRepository->method('findByTeamAndDivision')
            ->willReturnMap([
                [$team1, $division, $stat1],
                [$team2, $division, $stat2],
            ]);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->updateStatsAfterGame($game);

        $this->assertEquals(1, $stat1->getWins());
        $this->assertEquals(0, $stat1->getLosses());
        $this->assertEquals(0, $stat1->getTies());
        $this->assertEquals(3, $stat1->getPoints());

        $this->assertEquals(0, $stat2->getWins());
        $this->assertEquals(1, $stat2->getLosses());
        $this->assertEquals(0, $stat2->getTies());
        $this->assertEquals(0, $stat2->getPoints());
    }

    public function testTeam2WinIncreasesTeam2WinsAndPoints(): void
    {
        $division = new Division();
        $division->setName('Div B');

        $team1 = new Team();
        $team1->setName('Team Alpha');

        $team2 = new Team();
        $team2->setName('Team Beta');

        $game = $this->buildPlayedGame($division, $team1, $team2, score1: 1, score2: 3, winner: 2);

        $stat1 = $this->buildZeroStat($team1, $division);
        $stat2 = $this->buildZeroStat($team2, $division);

        $this->teamStatRepository->method('findByTeamAndDivision')
            ->willReturnMap([
                [$team1, $division, $stat1],
                [$team2, $division, $stat2],
            ]);

        $this->service->updateStatsAfterGame($game);

        $this->assertEquals(0, $stat1->getWins());
        $this->assertEquals(1, $stat1->getLosses());
        $this->assertEquals(0, $stat1->getPoints());

        $this->assertEquals(1, $stat2->getWins());
        $this->assertEquals(0, $stat2->getLosses());
        $this->assertEquals(3, $stat2->getPoints());
    }

    // =========================================================================
    // updateStatsAfterGame — rounds gagnés/perdus
    // =========================================================================

    public function testWinRoundsAndLooseRoundsAreUpdatedCorrectly(): void
    {
        $division = new Division();
        $division->setName('Div D');

        $team1 = new Team();
        $team1->setName('Team Alpha');

        $team2 = new Team();
        $team2->setName('Team Beta');

        $game = $this->buildPlayedGame($division, $team1, $team2, score1: 3, score2: 1, winner: 1);

        $stat1 = $this->buildZeroStat($team1, $division);
        $stat2 = $this->buildZeroStat($team2, $division);

        $this->teamStatRepository->method('findByTeamAndDivision')
            ->willReturnMap([
                [$team1, $division, $stat1],
                [$team2, $division, $stat2],
            ]);

        $this->service->updateStatsAfterGame($game);

        // team1 a gagné 3 rounds, perdu 1
        $this->assertEquals(3, $stat1->getWinRounds());
        $this->assertEquals(1, $stat1->getLooseRounds());

        // team2 a gagné 1 round, perdu 3
        $this->assertEquals(1, $stat2->getWinRounds());
        $this->assertEquals(3, $stat2->getLooseRounds());
    }

    public function testRoundsAccumulateOverMultipleCalls(): void
    {
        $division = new Division();
        $division->setName('Div E');

        $team1 = new Team();
        $team1->setName('Team Alpha');

        $team2 = new Team();
        $team2->setName('Team Beta');

        $game1 = $this->buildPlayedGame($division, $team1, $team2, score1: 3, score2: 1, winner: 1);
        $game2 = $this->buildPlayedGame($division, $team1, $team2, score1: 1, score2: 3, winner: 2);

        $stat1 = $this->buildZeroStat($team1, $division);
        $stat2 = $this->buildZeroStat($team2, $division);

        $this->teamStatRepository->method('findByTeamAndDivision')
            ->willReturnMap([
                [$team1, $division, $stat1],
                [$team2, $division, $stat2],
            ]);

        $this->service->updateStatsAfterGame($game1);
        $this->service->updateStatsAfterGame($game2);

        // After 2 games: team1 → 3+1=4 winRounds, 1+3=4 looseRounds
        $this->assertEquals(4, $stat1->getWinRounds());
        $this->assertEquals(4, $stat1->getLooseRounds());

        // After 2 games: team2 → 1+3=4 winRounds, 3+1=4 looseRounds
        $this->assertEquals(4, $stat2->getWinRounds());
        $this->assertEquals(4, $stat2->getLooseRounds());
    }

    // =========================================================================
    // updateStatsAfterGame — forfait
    // =========================================================================

    public function testForfeitTeam1LosesTeam2Wins(): void
    {
        $division = new Division();
        $division->setName('Div F');

        $team1 = new Team();
        $team1->setName('Team Alpha');

        $team2 = new Team();
        $team2->setName('Team Beta');

        // team1 forfait → score 0-4, team2 wins
        $game = $this->buildPlayedGame($division, $team1, $team2, score1: 0, score2: 4, winner: 2);
        $game->setIsForfeit(true);
        $game->setForfeitTeam(1);

        $stat1 = $this->buildZeroStat($team1, $division);
        $stat2 = $this->buildZeroStat($team2, $division);

        $this->teamStatRepository->method('findByTeamAndDivision')
            ->willReturnMap([
                [$team1, $division, $stat1],
                [$team2, $division, $stat2],
            ]);

        $this->service->updateStatsAfterGame($game);

        $this->assertEquals(0, $stat1->getWins());
        $this->assertEquals(1, $stat1->getLosses());
        $this->assertEquals(0, $stat1->getPoints());

        $this->assertEquals(1, $stat2->getWins());
        $this->assertEquals(0, $stat2->getLosses());
        $this->assertEquals(3, $stat2->getPoints());
    }

    public function testForfeitTeam2LosesTeam1Wins(): void
    {
        $division = new Division();
        $division->setName('Div G');

        $team1 = new Team();
        $team1->setName('Team Alpha');

        $team2 = new Team();
        $team2->setName('Team Beta');

        // team2 forfait → score 4-0, team1 wins
        $game = $this->buildPlayedGame($division, $team1, $team2, score1: 4, score2: 0, winner: 1);
        $game->setIsForfeit(true);
        $game->setForfeitTeam(2);

        $stat1 = $this->buildZeroStat($team1, $division);
        $stat2 = $this->buildZeroStat($team2, $division);

        $this->teamStatRepository->method('findByTeamAndDivision')
            ->willReturnMap([
                [$team1, $division, $stat1],
                [$team2, $division, $stat2],
            ]);

        $this->service->updateStatsAfterGame($game);

        $this->assertEquals(1, $stat1->getWins());
        $this->assertEquals(0, $stat1->getLosses());
        $this->assertEquals(3, $stat1->getPoints());

        $this->assertEquals(0, $stat2->getWins());
        $this->assertEquals(1, $stat2->getLosses());
        $this->assertEquals(0, $stat2->getPoints());
    }

    // =========================================================================
    // updateStatsAfterGame — création TeamStat manquante
    // =========================================================================

    public function testCreatesTeamStatWhenMissing(): void
    {
        $division = new Division();
        $division->setName('Div H');

        $team1 = new Team();
        $team1->setName('Team Alpha');

        $team2 = new Team();
        $team2->setName('Team Beta');

        $game = $this->buildPlayedGame($division, $team1, $team2, score1: 3, score2: 1, winner: 1);

        // Pas de TeamStat existantes → le service doit en créer
        $this->teamStatRepository->method('findByTeamAndDivision')
            ->willReturn(null);

        $persisted = [];
        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persisted) {
                $persisted[] = $entity;
            });

        $this->service->updateStatsAfterGame($game);

        $this->assertCount(2, $persisted);
        $this->assertContainsOnlyInstancesOf(TeamStat::class, $persisted);
    }

    public function testUpdatesExistingTeamStatWithoutCreatingNew(): void
    {
        $division = new Division();
        $division->setName('Div I');

        $team1 = new Team();
        $team1->setName('Team Alpha');

        $team2 = new Team();
        $team2->setName('Team Beta');

        $game = $this->buildPlayedGame($division, $team1, $team2, score1: 3, score2: 1, winner: 1);

        $stat1 = $this->buildZeroStat($team1, $division);
        $stat2 = $this->buildZeroStat($team2, $division);

        $this->teamStatRepository->method('findByTeamAndDivision')
            ->willReturnMap([
                [$team1, $division, $stat1],
                [$team2, $division, $stat2],
            ]);

        // Stats existantes → persist jamais appelé (pas de nouvelles entités)
        $this->entityManager->expects($this->never())->method('persist');

        $this->service->updateStatsAfterGame($game);
    }

    // =========================================================================
    // recalculateDivisionStats
    // =========================================================================

    public function testRecalculateDivisionStatsResetsAndRebuildsFromScratch(): void
    {
        $division = new Division();
        $division->setName('Div Recap');

        $team1 = new Team();
        $team1->setName('Team Alpha');

        $team2 = new Team();
        $team2->setName('Team Beta');

        // Stats existantes avec de vieilles données
        $stat1 = $this->buildStat($team1, $division, wins: 5, losses: 2, ties: 1, points: 16, winRounds: 20, looseRounds: 8);
        $stat2 = $this->buildStat($team2, $division, wins: 2, losses: 5, ties: 1, points: 7, winRounds: 8, looseRounds: 20);

        $playedStatus = new GameStatus();
        $playedStatus->setName('played');

        $game1 = $this->buildPlayedGame($division, $team1, $team2, score1: 3, score2: 1, winner: 1);
        $game2 = $this->buildPlayedGame($division, $team1, $team2, score1: 1, score2: 3, winner: 2);

        $this->gameRepository->expects($this->once())
            ->method('findPlayedByDivision')
            ->with($division)
            ->willReturn([$game1, $game2]);

        $this->teamStatRepository->expects($this->once())
            ->method('findByDivision')
            ->with($division)
            ->willReturn([$stat1, $stat2]);

        $this->service->recalculateDivisionStats($division);

        // Après recalcul : 1 victoire chacun, 1 défaite chacun
        // game1: team1 gagne 3-1 → stat1.winRounds+=3, looseRounds+=1
        // game2: team2 gagne 1-3 → stat1.winRounds+=1, looseRounds+=3
        $this->assertEquals(1, $stat1->getWins());
        $this->assertEquals(1, $stat1->getLosses());
        $this->assertEquals(0, $stat1->getTies());
        $this->assertEquals(3, $stat1->getPoints());
        $this->assertEquals(4, $stat1->getWinRounds());
        $this->assertEquals(4, $stat1->getLooseRounds());

        $this->assertEquals(1, $stat2->getWins());
        $this->assertEquals(1, $stat2->getLosses());
        $this->assertEquals(0, $stat2->getTies());
        $this->assertEquals(3, $stat2->getPoints());
        $this->assertEquals(4, $stat2->getWinRounds());
        $this->assertEquals(4, $stat2->getLooseRounds());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildPlayedGame(
        Division $division,
        Team $team1,
        Team $team2,
        int $score1,
        int $score2,
        ?int $winner,
    ): Game {
        $status = new GameStatus();
        $status->setName('played');

        $game = new Game();
        $game->setDivision($division);
        $game->setTeam1($team1);
        $game->setTeam2($team2);
        $game->setScore1($score1);
        $game->setScore2($score2);
        $game->setWinner($winner);
        $game->setStatus($status);
        $game->setWeek(1);

        return $game;
    }

    private function buildZeroStat(Team $team, Division $division): TeamStat
    {
        return $this->buildStat($team, $division, 0, 0, 0, 0, 0, 0);
    }

    private function buildStat(
        Team $team,
        Division $division,
        int $wins,
        int $losses,
        int $ties,
        int $points,
        int $winRounds,
        int $looseRounds,
    ): TeamStat {
        $stat = new TeamStat();
        $stat->setTeam($team);
        $stat->setDivision($division);
        $stat->setWins($wins);
        $stat->setLosses($losses);
        $stat->setTies($ties);
        $stat->setPoints($points);
        $stat->setWinRounds($winRounds);
        $stat->setLooseRounds($looseRounds);

        return $stat;
    }
}
