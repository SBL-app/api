<?php

namespace App\Service;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\Season;
use App\Repository\DivisionRepository;
use App\Repository\GameRepository;
use App\Repository\TeamStatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SeasonClosureService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameRepository $gameRepository,
        private DivisionRepository $divisionRepository,
        private TeamStatRepository $teamStatRepository,
        private PushNotificationService $pushNotificationService,
        private LoggerInterface $logger,
    ) {
    }

    public function onGamePlayed(Game $game): void
    {
        $division = $game->getDivision();
        if ($division === null) {
            return;
        }

        $this->tryFinalizeDivision($division);
    }

    private function tryFinalizeDivision(Division $division): void
    {
        $this->entityManager->refresh($division);

        if ($division->isFinalized()) {
            return;
        }

        if ($this->gameRepository->count(['division' => $division]) === 0) {
            return;
        }

        if ($this->gameRepository->hasNonPlayedGameInDivision($division)) {
            return;
        }

        $division->setIsFinalized(true);
        $this->entityManager->flush();

        $this->notifyDivisionFinalized($division);

        $season = $division->getSeason();
        if ($season !== null) {
            $this->tryFinalizeSeason($season);
        }
    }

    private function tryFinalizeSeason(Season $season): void
    {
        $this->entityManager->refresh($season);

        if ($season->isFinalized()) {
            return;
        }

        if ($this->divisionRepository->hasNonFinalizedDivisionInSeason($season)) {
            return;
        }

        $season->setIsFinalized(true);
        $this->entityManager->flush();

        $this->notifySeasonFinalized($season);
    }

    public function notifyDivisionFinalized(Division $division): void
    {
        $teamStats = $this->teamStatRepository->findBy(['division' => $division]);

        $users = [];
        foreach ($teamStats as $teamStat) {
            foreach ($teamStat->getTeam()->getMembers() as $member) {
                if ($member->getUser() !== null) {
                    $users[] = $member->getUser();
                }
            }
        }

        if (empty($users)) {
            return;
        }

        try {
            $this->pushNotificationService->sendToUsers(
                $users,
                'Résultats finaux — ' . $division->getName(),
                'Les résultats de la division ' . $division->getName() . ' sont disponibles',
                '/divisions/' . $division->getId(),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send push notifications for division finalization', [
                'division_id' => $division->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifySeasonFinalized(Season $season): void
    {
        $divisions = $this->divisionRepository->findBy(['season' => $season]);

        $users = [];
        $seen = [];
        foreach ($divisions as $division) {
            $teamStats = $this->teamStatRepository->findBy(['division' => $division]);

            foreach ($teamStats as $teamStat) {
                foreach ($teamStat->getTeam()->getMembers() as $member) {
                    $user = $member->getUser();
                    if ($user !== null && !in_array($user->getId(), $seen, true)) {
                        $seen[] = $user->getId();
                        $users[] = $user;
                    }
                }
            }
        }

        if (empty($users)) {
            return;
        }

        try {
            $this->pushNotificationService->sendToUsers(
                $users,
                'Saison terminée — ' . $season->getName(),
                'La saison ' . $season->getName() . ' est maintenant clôturée',
                '/seasons/' . $season->getId(),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send push notifications for season finalization', [
                'season_id' => $season->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
