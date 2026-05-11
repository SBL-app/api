<?php

namespace App\Service;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\TeamStat;
use App\Repository\GameRepository;
use App\Repository\TeamStatRepository;
use Doctrine\ORM\EntityManagerInterface;

class TeamStatCalculatorService
{
    private const POINTS_WIN = 3;
    private const POINTS_LOSS = 0;

    public function __construct(
        private readonly TeamStatRepository $teamStatRepository,
        private readonly GameRepository $gameRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function updateStatsAfterGame(Game $game): void
    {
        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();
        $division = $game->getDivision();

        if (!$team1 || !$team2 || !$division) {
            return;
        }

        $stat1 = $this->getOrCreateStat($team1, $division);
        $stat2 = $this->getOrCreateStat($team2, $division);

        $score1 = $game->getScore1() ?? 0;
        $score2 = $game->getScore2() ?? 0;
        $winner = $game->getWinner();

        $stat1->setWinRounds($stat1->getWinRounds() + $score1);
        $stat1->setLooseRounds($stat1->getLooseRounds() + $score2);

        $stat2->setWinRounds($stat2->getWinRounds() + $score2);
        $stat2->setLooseRounds($stat2->getLooseRounds() + $score1);

        if ($winner === 1) {
            $stat1->setWins($stat1->getWins() + 1);
            $stat1->setPoints($stat1->getPoints() + self::POINTS_WIN);
            $stat2->setLosses($stat2->getLosses() + 1);
            $stat2->setPoints($stat2->getPoints() + self::POINTS_LOSS);
        } elseif ($winner === 2) {
            $stat2->setWins($stat2->getWins() + 1);
            $stat2->setPoints($stat2->getPoints() + self::POINTS_WIN);
            $stat1->setLosses($stat1->getLosses() + 1);
            $stat1->setPoints($stat1->getPoints() + self::POINTS_LOSS);
        }

        $this->entityManager->flush();
    }

    public function recalculateDivisionStats(Division $division): void
    {
        $existingStats = $this->teamStatRepository->findByDivision($division);
        foreach ($existingStats as $stat) {
            $stat->setWins(0);
            $stat->setLosses(0);
            $stat->setTies(0);
            $stat->setPoints(0);
            $stat->setWinRounds(0);
            $stat->setLooseRounds(0);
        }

        $games = $this->gameRepository->findPlayedByDivision($division);
        foreach ($games as $game) {
            $this->applyGameToStats($game, $existingStats);
        }

        $this->entityManager->flush();
    }

    private function getOrCreateStat($team, Division $division): TeamStat
    {
        $stat = $this->teamStatRepository->findByTeamAndDivision($team, $division);

        if ($stat === null) {
            $stat = new TeamStat();
            $stat->setTeam($team);
            $stat->setDivision($division);
            $stat->setWins(0);
            $stat->setLosses(0);
            $stat->setTies(0);
            $stat->setPoints(0);
            $stat->setWinRounds(0);
            $stat->setLooseRounds(0);
            $this->entityManager->persist($stat);
        }

        return $stat;
    }

    /**
     * @param TeamStat[] $statsIndex
     */
    private function applyGameToStats(Game $game, array $statsIndex): void
    {
        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();

        if (!$team1 || !$team2) {
            return;
        }

        $stat1 = $this->findStatForTeam($statsIndex, $team1);
        $stat2 = $this->findStatForTeam($statsIndex, $team2);

        if (!$stat1 || !$stat2) {
            return;
        }

        $score1 = $game->getScore1() ?? 0;
        $score2 = $game->getScore2() ?? 0;
        $winner = $game->getWinner();

        $stat1->setWinRounds($stat1->getWinRounds() + $score1);
        $stat1->setLooseRounds($stat1->getLooseRounds() + $score2);

        $stat2->setWinRounds($stat2->getWinRounds() + $score2);
        $stat2->setLooseRounds($stat2->getLooseRounds() + $score1);

        if ($winner === 1) {
            $stat1->setWins($stat1->getWins() + 1);
            $stat1->setPoints($stat1->getPoints() + self::POINTS_WIN);
            $stat2->setLosses($stat2->getLosses() + 1);
        } elseif ($winner === 2) {
            $stat2->setWins($stat2->getWins() + 1);
            $stat2->setPoints($stat2->getPoints() + self::POINTS_WIN);
            $stat1->setLosses($stat1->getLosses() + 1);
        }
    }

    /**
     * @param TeamStat[] $stats
     */
    private function findStatForTeam(array $stats, $team): ?TeamStat
    {
        foreach ($stats as $stat) {
            if ($stat->getTeam() === $team) {
                return $stat;
            }
        }

        return null;
    }
}
