<?php

namespace App\Controller;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\MatchResult;
use App\Exception\ApiProblemException;
use App\Repository\MatchResultRepository;
use App\Service\PushNotificationService;
use App\Service\TeamStatCalculatorService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Actions rapides admin (ROLE_ADMIN). Chaque action est journalisée (qui, quoi, quand).
 */
#[Route('/api/admin')]
class AdminActionController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        throw new \LogicException('Not supported: this controller exposes actions only.');
    }

    private function requireAdmin(): void
    {
        // 401 explicite : le listener d'erreurs API convertirait une AccessDeniedException en 500.
        if (null === $this->getUser()) {
            throw ApiProblemException::unauthorized('Authentication required');
        }
        $this->checkUserRole('ROLE_ADMIN');
    }

    private function audit(string $action, string $targetType, int $targetId): void
    {
        $this->logger->info('admin_action', [
            'admin' => $this->getUser()?->getUserIdentifier(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ]);
    }

    private function findOr404(string $class, int $id, string $label): object
    {
        $entity = $this->entityManager->getRepository($class)->find($id);
        if (null === $entity) {
            throw ApiProblemException::notFound($label . ' not found');
        }

        return $entity;
    }

    /**
     * POST /api/admin/games/{id}/validate-result - Valide le résultat en attente/contesté d'un match.
     */
    #[Route('/games/{id}/validate-result', name: 'api_admin_validate_result', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function validateResult(int $id, MatchResultRepository $results, TeamStatCalculatorService $statCalculator): JsonResponse
    {
        $this->requireAdmin();
        /** @var Game $game */
        $game = $this->findOr404(Game::class, $id, 'Game');

        $target = null;
        foreach ($results->findByGame($game) as $r) { // plus récent d'abord
            if ($r->isPending() || $r->isContested()) {
                $target = $r;
                break;
            }
        }
        if (null === $target) {
            throw ApiProblemException::conflict('No pending or contested result to validate for this game');
        }

        $target->validate();
        $game->setScore1($target->getScore1());
        $game->setScore2($target->getScore2());
        $game->setWinner($target->getScore1() > $target->getScore2() ? 1 : ($target->getScore2() > $target->getScore1() ? 2 : null));
        $played = $this->entityManager->getRepository(GameStatus::class)->findOneBy(['name' => 'played']);
        if ($played) {
            $game->setStatus($played);
        }
        $this->entityManager->flush();

        try {
            $statCalculator->updateStatsAfterGame($game);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update team stats', ['error' => $e->getMessage()]);
        }
        $this->audit('validate_result', 'game', $id);

        return $this->json(['game_id' => $id, 'result_id' => $target->getId(), 'status' => $target->getStatus()]);
    }

    /**
     * POST /api/admin/games/{id}/remind - Relance les équipes d'un match sans résultat validé.
     */
    #[Route('/games/{id}/remind', name: 'api_admin_remind_game', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function remind(int $id, PushNotificationService $push, MatchResultRepository $results): JsonResponse
    {
        $this->requireAdmin();
        /** @var Game $game */
        $game = $this->findOr404(Game::class, $id, 'Game');

        foreach ($results->findByGame($game) as $r) {
            if ($r->isValidated()) {
                throw ApiProblemException::conflict('Game already has a validated result');
            }
        }

        foreach ([$game->getTeam1(), $game->getTeam2()] as $team) {
            if (null === $team) {
                continue;
            }
            $users = array_values(array_filter(array_map(fn ($m) => $m->getUser(), $team->getMembers()->toArray())));
            $push->sendToUsers($users, 'Rappel — résultat attendu', 'Merci de saisir ou valider le résultat de votre match.', '/games/' . $id);
        }
        $this->audit('remind_game', 'game', $id);

        return $this->json(['game_id' => $id, 'reminded' => true]);
    }

    /**
     * POST /api/admin/divisions/{id}/close - Clôture une division (refusée si des matchs restent sans résultat).
     */
    #[Route('/divisions/{id}/close', name: 'api_admin_close_division', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function closeDivision(int $id): JsonResponse
    {
        $this->requireAdmin();
        /** @var Division $division */
        $division = $this->findOr404(Division::class, $id, 'Division');

        if ($division->isFinalized()) {
            throw ApiProblemException::conflict('Division is already closed');
        }

        $open = [];
        foreach ($this->entityManager->getRepository(Game::class)->findBy(['division' => $division]) as $game) {
            if ('played' !== $game->getStatus()?->getName() && !$game->isForfeit()) {
                $open[] = $game->getId();
            }
        }
        if ($open) {
            throw ApiProblemException::conflict(sprintf(
                'Cannot close division: %d game(s) without result (ids: %s)',
                count($open),
                implode(', ', $open),
            ));
        }

        $division->setIsFinalized(true);
        $this->entityManager->flush();
        $this->audit('close_division', 'division', $id);

        return $this->json(['division_id' => $id, 'is_finalized' => true]);
    }
}
