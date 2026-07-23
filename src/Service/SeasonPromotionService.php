<?php

namespace App\Service;

use App\Entity\Division;
use App\Entity\Season;
use App\Entity\TeamStat;
use App\Repository\DivisionRepository;
use App\Repository\TeamStatRepository;

/**
 * Calcule les mouvements de promotion / relégation entre divisions d'une saison
 * à partir des classements finaux (issue api#36).
 *
 * Convention de niveau : 1 = division la plus haute. Une équipe promue passe au
 * niveau immédiatement inférieur en nombre (level - 1), une équipe reléguée au
 * niveau immédiatement supérieur en nombre (level + 1).
 */
class SeasonPromotionService
{
    public function __construct(
        private DivisionRepository $divisionRepository,
        private TeamStatRepository $teamStatRepository,
    ) {
    }

    /**
     * Trie un tableau de TeamStat par classement :
     * points desc, puis différence de rounds desc, puis rounds gagnés desc.
     *
     * @param TeamStat[] $teamStats
     * @return TeamStat[]
     */
    private function sortStandings(array $teamStats): array
    {
        usort($teamStats, function (TeamStat $a, TeamStat $b) {
            if ($a->getPoints() !== $b->getPoints()) {
                return $b->getPoints() <=> $a->getPoints();
            }
            $diffA = $a->getWinRounds() - $a->getLooseRounds();
            $diffB = $b->getWinRounds() - $b->getLooseRounds();
            if ($diffA !== $diffB) {
                return $diffB <=> $diffA;
            }
            return $b->getWinRounds() <=> $a->getWinRounds();
        });

        return $teamStats;
    }

    /**
     * Calcule les mouvements pour toutes les divisions d'une saison.
     *
     * @return array<int, array<string, mixed>> Une entrée par division, triée par niveau.
     */
    public function computeMovements(Season $season): array
    {
        $divisions = $this->divisionRepository->findBy(['season' => $season]);

        if (empty($divisions)) {
            return [];
        }

        $levels = array_map(fn (Division $d) => $d->getLevel(), $divisions);
        $minLevel = min($levels);
        $maxLevel = max($levels);

        usort($divisions, fn (Division $a, Division $b) => $a->getLevel() <=> $b->getLevel());

        $result = [];
        foreach ($divisions as $division) {
            $standings = $this->sortStandings(
                $this->teamStatRepository->findBy(['division' => $division])
            );
            $count = count($standings);
            $level = $division->getLevel();

            // On ne peut pas promouvoir depuis la division la plus haute,
            // ni reléguer depuis la plus basse.
            $promoteN = $level > $minLevel ? min($division->getPromotionCount(), $count) : 0;
            $relegateN = $level < $maxLevel ? min($division->getRelegationCount(), $count) : 0;

            // Si la division est trop petite pour promouvoir ET reléguer autant,
            // on rogne d'abord la relégation.
            if ($promoteN + $relegateN > $count) {
                $relegateN = max(0, $count - $promoteN);
            }

            $promoted = [];
            $relegated = [];
            $stayed = [];

            foreach ($standings as $i => $stat) {
                $team = $stat->getTeam();
                $entry = [
                    'team_id' => $team->getId(),
                    'team_name' => $team->getName(),
                    'rank' => $i + 1,
                    'points' => $stat->getPoints(),
                    'from_level' => $level,
                ];

                if ($i < $promoteN) {
                    $entry['to_level'] = $level - 1;
                    $entry['movement'] = 'promoted';
                    $promoted[] = $entry;
                } elseif ($i >= $count - $relegateN) {
                    $entry['to_level'] = $level + 1;
                    $entry['movement'] = 'relegated';
                    $relegated[] = $entry;
                } else {
                    $entry['to_level'] = $level;
                    $entry['movement'] = 'stayed';
                    $stayed[] = $entry;
                }
            }

            $result[] = [
                'division_id' => $division->getId(),
                'division_name' => $division->getName(),
                'level' => $level,
                'promotion_count' => $promoteN,
                'relegation_count' => $relegateN,
                'promoted' => $promoted,
                'relegated' => $relegated,
                'stayed' => $stayed,
            ];
        }

        return $result;
    }

    /**
     * Aplati les mouvements calculés en une liste team_id => to_level.
     *
     * @param array<int, array<string, mixed>> $movements
     * @return array<int, int> team_id => niveau cible
     */
    public function flattenTargetLevels(array $movements): array
    {
        $map = [];
        foreach ($movements as $divisionMovement) {
            foreach (['promoted', 'relegated', 'stayed'] as $bucket) {
                foreach ($divisionMovement[$bucket] as $entry) {
                    $map[$entry['team_id']] = $entry['to_level'];
                }
            }
        }

        return $map;
    }
}
