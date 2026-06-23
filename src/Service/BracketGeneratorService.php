<?php

namespace App\Service;

use App\Entity\Bracket;
use App\Entity\BracketEntry;
use App\Entity\Game;
use App\Exception\ApiProblemException;
use App\Repository\BracketEntryRepository;
use App\Repository\GameStatusRepository;
use Doctrine\ORM\EntityManagerInterface;

class BracketGeneratorService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameStatusRepository $gameStatusRepository,
    ) {
    }

    /**
     * Charge les entries et génère l'arbre. Point d'entrée appelé par le contrôleur.
     */
    public function generate(Bracket $bracket, BracketEntryRepository $entryRepository): void
    {
        $entries = $entryRepository->findByBracketOrderedBySeed($bracket);
        $this->generateFromEntries($bracket, $entries);
    }

    /**
     * Ordre standard des seeds par position pour une taille puissance de 2.
     *
     * @return int[]
     */
    public static function seedOrder(int $size): array
    {
        $seeds = [1];
        while (count($seeds) < $size) {
            $length = count($seeds) * 2 + 1;
            $next = [];
            foreach ($seeds as $s) {
                $next[] = $s;
                $next[] = $length - $s;
            }
            $seeds = $next;
        }

        return $seeds;
    }

    /**
     * @param BracketEntry[] $entries ordonnés par seed croissant
     */
    public function generateFromEntries(Bracket $bracket, array $entries): void
    {
        $n = count($entries);
        if ($n < 2) {
            throw ApiProblemException::badRequest('A bracket needs at least 2 entries');
        }

        // seed (1-based) -> Team
        $teamBySeed = [];
        foreach ($entries as $entry) {
            $teamBySeed[$entry->getSeed()] = $entry->getTeam();
        }

        $size = 1;
        while ($size < $n) {
            $size *= 2;
        }
        $rounds = (int) log($size, 2);

        $scheduledStatus = $this->gameStatusRepository->findOneBy(['name' => 'scheduled']);
        if (!$scheduledStatus) {
            throw ApiProblemException::badRequest('Game status "scheduled" not found in database');
        }

        // games[round][position] = Game
        $games = [];

        // Round 1
        $order = self::seedOrder($size);
        $matchCountRound1 = $size / 2;
        for ($p = 0; $p < $matchCountRound1; $p++) {
            $seedA = $order[$p * 2];
            $seedB = $order[$p * 2 + 1];

            $game = new Game();
            $game->setBracket($bracket);
            $game->setRound(1);
            $game->setBracketPosition($p);
            $game->setStatus($scheduledStatus);
            $game->setScore1(0);
            $game->setScore2(0);
            $game->setTeam1($seedA <= $n ? $teamBySeed[$seedA] : null);
            $game->setTeam2($seedB <= $n ? $teamBySeed[$seedB] : null);

            $this->entityManager->persist($game);
            $games[1][$p] = $game;
        }

        // Rounds 2..rounds (vides). La finale est round=rounds, position 0.
        for ($r = 2; $r <= $rounds; $r++) {
            $matchCount = $size / (2 ** $r);
            for ($p = 0; $p < $matchCount; $p++) {
                $game = new Game();
                $game->setBracket($bracket);
                $game->setRound($r);
                $game->setBracketPosition($p);
                $game->setStatus($scheduledStatus);
                $game->setScore1(0);
                $game->setScore2(0);
                $this->entityManager->persist($game);
                $games[$r][$p] = $game;
            }
        }

        // Câblage winnerTo
        for ($r = 1; $r < $rounds; $r++) {
            foreach ($games[$r] as $p => $game) {
                $nextGame = $games[$r + 1][intdiv($p, 2)];
                $game->setWinnerToGame($nextGame);
                $game->setWinnerToSlot($p % 2 === 0 ? 1 : 2);
            }
        }

        // Petite finale
        if ($bracket->hasThirdPlaceMatch() && $rounds >= 2) {
            $thirdPlace = new Game();
            $thirdPlace->setBracket($bracket);
            $thirdPlace->setRound($rounds);
            $thirdPlace->setBracketPosition(1);
            $thirdPlace->setIsThirdPlaceMatch(true);
            $thirdPlace->setStatus($scheduledStatus);
            $thirdPlace->setScore1(0);
            $thirdPlace->setScore2(0);
            $this->entityManager->persist($thirdPlace);

            $semis = $games[$rounds - 1];
            $slot = 1;
            foreach ($semis as $semi) {
                $semi->setLoserToGame($thirdPlace);
                $semi->setLoserToSlot($slot);
                $slot++;
            }
        }

        // Byes : round 1 avec une seule équipe -> résolu et propagé
        $playedStatus = null;
        if ($n < $size) { // byes only exist when N is not a power of 2
            $playedStatus = $this->gameStatusRepository->findOneBy(['name' => 'played']);
            if (!$playedStatus) {
                throw ApiProblemException::badRequest('Game status "played" not found in database');
            }
        }

        foreach ($games[1] as $game) {
            $hasTeam1 = $game->getTeam1() !== null;
            $hasTeam2 = $game->getTeam2() !== null;
            if ($hasTeam1 === $hasTeam2) {
                continue; // deux équipes (match réel) ou aucune (impossible ici)
            }

            $winnerSlot = $hasTeam1 ? 1 : 2;
            $winnerTeam = $hasTeam1 ? $game->getTeam1() : $game->getTeam2();

            $game->setWinner($winnerSlot);
            $game->setStatus($playedStatus);

            $nextGame = $game->getWinnerToGame();
            if ($nextGame !== null) {
                if ($game->getWinnerToSlot() === 1) {
                    $nextGame->setTeam1($winnerTeam);
                } else {
                    $nextGame->setTeam2($winnerTeam);
                }
            }
        }

        $bracket->setStatus(Bracket::STATUS_READY);
        $this->entityManager->persist($bracket);
        $this->entityManager->flush();
    }
}
