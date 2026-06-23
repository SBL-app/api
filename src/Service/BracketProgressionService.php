<?php

namespace App\Service;

use App\Entity\Bracket;
use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class BracketProgressionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Propage le résultat d'un match de bracket joué vers les matchs suivants.
     */
    public function advance(Game $game): void
    {
        $bracket = $game->getBracket();
        if ($bracket === null) {
            return;
        }

        $winner = $game->getWinner();
        $winnerTeam = match ($winner) {
            1 => $game->getTeam1(),
            2 => $game->getTeam2(),
            default => null,
        };
        $loserTeam = match ($winner) {
            1 => $game->getTeam2(),
            2 => $game->getTeam1(),
            default => null,
        };

        if ($bracket->getStatus() === Bracket::STATUS_READY) {
            $bracket->setStatus(Bracket::STATUS_IN_PROGRESS);
        }

        if ($winnerTeam !== null && $game->getWinnerToGame() !== null) {
            $this->placeTeam($game->getWinnerToGame(), $game->getWinnerToSlot(), $winnerTeam);
        }

        if ($loserTeam !== null && $game->getLoserToGame() !== null) {
            $this->placeTeam($game->getLoserToGame(), $game->getLoserToSlot(), $loserTeam);
        }

        // Finale jouée (pas de match suivant et pas la petite finale) -> bracket terminé
        if ($game->getWinnerToGame() === null && !$game->isThirdPlaceMatch()) {
            $bracket->setStatus(Bracket::STATUS_COMPLETED);
        }

        $this->entityManager->persist($bracket);
        $this->entityManager->flush();
    }

    private function placeTeam(Game $target, ?int $slot, $team): void
    {
        if ($target->getStatus()?->getName() === 'played') {
            $this->logger->warning('Bracket progression skipped: downstream game already played', [
                'target_game_id' => $target->getId(),
            ]);
            return;
        }

        if ($slot === 1) {
            $target->setTeam1($team);
        } elseif ($slot === 2) {
            $target->setTeam2($team);
        }
        $this->entityManager->persist($target);
    }
}
