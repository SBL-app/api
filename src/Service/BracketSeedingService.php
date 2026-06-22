<?php

namespace App\Service;

use App\Entity\Division;
use App\Entity\Team;
use App\Repository\TeamStatRepository;

class BracketSeedingService
{
    public function __construct(
        private TeamStatRepository $teamStatRepository,
    ) {
    }

    /**
     * Calcule l'ordre de seeding d'une division depuis le classement régulier.
     *
     * @return Team[] équipes ordonnées du seed 1 au seed N (au plus $qualifiedCount)
     */
    public function computeSeeds(Division $division, int $qualifiedCount): array
    {
        $stats = $this->teamStatRepository->findByDivision($division);

        usort($stats, function ($a, $b) {
            if ($a->getPoints() !== $b->getPoints()) {
                return $b->getPoints() - $a->getPoints();
            }
            $diffA = ($a->getWinRounds() ?? 0) - ($a->getLooseRounds() ?? 0);
            $diffB = ($b->getWinRounds() ?? 0) - ($b->getLooseRounds() ?? 0);
            if ($diffA !== $diffB) {
                return $diffB - $diffA;
            }
            return ($b->getWins() ?? 0) - ($a->getWins() ?? 0);
        });

        $teams = array_map(fn ($stat) => $stat->getTeam(), $stats);

        return array_slice($teams, 0, $qualifiedCount);
    }
}
