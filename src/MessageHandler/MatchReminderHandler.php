<?php

namespace App\MessageHandler;

use App\Message\MatchReminderMessage;
use App\Repository\GameRepository;
use App\Service\PushNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class MatchReminderHandler
{
    public function __construct(
        private GameRepository $gameRepository,
        private PushNotificationService $pushNotificationService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(MatchReminderMessage $message): void
    {
        $now = new \DateTime();
        $in24h = (clone $now)->modify('+24 hours');

        $games = $this->gameRepository->findGamesForReminder($now, $in24h);

        $this->logger->info('Match reminder check', ['games_found' => count($games)]);

        foreach ($games as $game) {
            $team1 = $game->getTeam1();
            $team2 = $game->getTeam2();
            $dateStr = $game->getDate()?->format('d/m/Y à H:i') ?? 'date inconnue';

            if ($team1 !== null) {
                $users1 = array_values(array_filter(
                    array_map(fn($m) => $m->getUser(), $team1->getMembers()->toArray())
                ));
                $this->pushNotificationService->sendToUsers(
                    $users1,
                    'Rappel — Match dans moins de 24h',
                    sprintf('Votre match contre %s est prévu le %s', $team2?->getName() ?? '???', $dateStr),
                    '/games/' . $game->getId(),
                );
            }

            if ($team2 !== null) {
                $users2 = array_values(array_filter(
                    array_map(fn($m) => $m->getUser(), $team2->getMembers()->toArray())
                ));
                $this->pushNotificationService->sendToUsers(
                    $users2,
                    'Rappel — Match dans moins de 24h',
                    sprintf('Votre match contre %s est prévu le %s', $team1?->getName() ?? '???', $dateStr),
                    '/games/' . $game->getId(),
                );
            }

            $game->setReminderSentAt(new \DateTime());
            $this->entityManager->persist($game);
        }

        $this->entityManager->flush();
    }
}
