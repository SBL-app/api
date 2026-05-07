<?php

namespace App\Controller;

use App\Entity\GameStatus;
use App\Entity\MatchResult;
use App\Exception\ApiProblemException;
use App\Repository\MatchResultRepository;
use App\Service\PushNotificationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class MatchResultController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof MatchResult) {
            throw new \InvalidArgumentException('Entity must be an instance of MatchResult');
        }

        return [
            'id' => $entity->getId(),
            'game_id' => $entity->getGame()?->getId(),
            'submitted_by' => $entity->getSubmittedBy()?->getId(),
            'submitted_by_username' => $entity->getSubmittedBy()?->getUsername(),
            'team_id' => $entity->getTeam()?->getId(),
            'team_name' => $entity->getTeam()?->getName(),
            'score1' => $entity->getScore1(),
            'score2' => $entity->getScore2(),
            'status' => $entity->getStatus(),
            'contest_reason' => $entity->getContestReason(),
            'validated_at' => $entity->getValidatedAt()?->format('Y-m-d H:i:s'),
            'created_at' => $entity->getCreatedAt()->format('Y-m-d H:i:s'),
            'reminder_sent_at' => $entity->getReminderSentAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * POST /api/games/{id}/submit-result - Un capitaine soumet le resultat d'un match
     */
    #[Route('/games/{id}/submit-result', name: 'api_game_submit_result', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function submitResult(
        int $id,
        Request $request,
        MatchResultRepository $resultRepository,
        PushNotificationService $pushNotificationService,
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();

        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();

        $team = null;
        if ($team1 && $team1->isCaptain($user)) {
            $team = $team1;
        } elseif ($team2 && $team2->isCaptain($user)) {
            $team = $team2;
        }

        if (!$team) {
            throw ApiProblemException::forbidden('You must be a captain of one of the teams in this game');
        }

        $pendingResult = $resultRepository->findPendingByGame($game);
        if ($pendingResult) {
            throw ApiProblemException::conflict('A pending result already exists for this game');
        }

        $data = $this->getRequestData($request);

        if (!isset($data['score1']) || !isset($data['score2'])) {
            throw ApiProblemException::badRequest('score1 and score2 are required');
        }

        $score1 = $data['score1'];
        $score2 = $data['score2'];

        if (!is_int($score1) || !is_int($score2) || $score1 < 0 || $score2 < 0) {
            throw ApiProblemException::badRequest('score1 and score2 must be non-negative integers');
        }

        $result = new MatchResult();
        $result->setGame($game);
        $result->setSubmittedBy($user);
        $result->setTeam($team);
        $result->setScore1($score1);
        $result->setScore2($score2);

        $pendingResultStatus = $this->entityManager->getRepository(GameStatus::class)->findOneBy(['name' => 'pending_result']);
        if ($pendingResultStatus) {
            $game->setStatus($pendingResultStatus);
        }

        $this->entityManager->persist($result);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        // Envoyer une notification push au capitaine adverse
        $opposingTeam = ($team === $team1) ? $team2 : $team1;
        $opposingCaptain = $opposingTeam?->getCaptainUser();

        if ($opposingCaptain) {
            try {
                $pushNotificationService->sendToUser(
                    $opposingCaptain,
                    'Resultat soumis',
                    sprintf('%s a soumis le resultat du match : %d - %d', $team->getName(), $score1, $score2),
                    '/matches',
                );
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send push notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->json($this->formatEntityData($result), 201);
    }

    /**
     * PATCH /api/games/{id}/results/{resultId}/validate - Le capitaine adverse valide le resultat
     */
    #[Route('/games/{id}/results/{resultId}/validate', name: 'api_game_result_validate', methods: ['PATCH'], requirements: ['id' => '\d+', 'resultId' => '\d+'])]
    public function validateResult(
        int $id,
        int $resultId,
        PushNotificationService $pushNotificationService,
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();

        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');
        $result = $this->findEntityOrFail('App\Entity\MatchResult', $resultId, 'MatchResult');

        if ($result->getGame()?->getId() !== $game->getId()) {
            throw ApiProblemException::badRequest('This result does not belong to this game');
        }

        if (!$result->isPending()) {
            throw ApiProblemException::conflict('This result is no longer pending');
        }

        // Verifier que l'utilisateur est capitaine de l'AUTRE equipe (pas celle du soumetteur)
        $submitterTeam = $result->getTeam();
        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();

        $opposingTeam = null;
        if ($submitterTeam && $team1 && $submitterTeam->getId() === $team1->getId()) {
            $opposingTeam = $team2;
        } elseif ($submitterTeam && $team2 && $submitterTeam->getId() === $team2->getId()) {
            $opposingTeam = $team1;
        }

        if (!$opposingTeam || !$opposingTeam->isCaptain($user)) {
            throw ApiProblemException::forbidden('You must be the captain of the opposing team to validate this result');
        }

        $result->validate();

        $score1 = $result->getScore1();
        $score2 = $result->getScore2();

        $game->setScore1($score1);
        $game->setScore2($score2);
        $game->setWinner($this->calculateWinner($score1, $score2));

        $playedStatus = $this->entityManager->getRepository(GameStatus::class)->findOneBy(['name' => 'played']);
        if ($playedStatus) {
            $game->setStatus($playedStatus);
        }

        $this->entityManager->persist($result);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        // Notifier le soumetteur que le resultat a ete valide
        $submitter = $result->getSubmittedBy();
        if ($submitter) {
            try {
                $pushNotificationService->sendToUser(
                    $submitter,
                    'Resultat valide',
                    sprintf('Le resultat du match %s vs %s a ete valide : %d - %d', $team1?->getName(), $team2?->getName(), $score1, $score2),
                    '/matches',
                );
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send push notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->json($this->formatEntityData($result));
    }

    /**
     * PATCH /api/games/{id}/results/{resultId}/contest - Le capitaine adverse conteste le resultat
     */
    #[Route('/games/{id}/results/{resultId}/contest', name: 'api_game_result_contest', methods: ['PATCH'], requirements: ['id' => '\d+', 'resultId' => '\d+'])]
    public function contestResult(
        int $id,
        int $resultId,
        Request $request,
        PushNotificationService $pushNotificationService,
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();

        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');
        $result = $this->findEntityOrFail('App\Entity\MatchResult', $resultId, 'MatchResult');

        if ($result->getGame()?->getId() !== $game->getId()) {
            throw ApiProblemException::badRequest('This result does not belong to this game');
        }

        if (!$result->isPending()) {
            throw ApiProblemException::conflict('This result is no longer pending');
        }

        // Verifier que l'utilisateur est capitaine de l'AUTRE equipe
        $submitterTeam = $result->getTeam();
        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();

        $opposingTeam = null;
        if ($submitterTeam && $team1 && $submitterTeam->getId() === $team1->getId()) {
            $opposingTeam = $team2;
        } elseif ($submitterTeam && $team2 && $submitterTeam->getId() === $team2->getId()) {
            $opposingTeam = $team1;
        }

        if (!$opposingTeam || !$opposingTeam->isCaptain($user)) {
            throw ApiProblemException::forbidden('You must be the captain of the opposing team to contest this result');
        }

        $data = $this->getRequestData($request);

        if (!isset($data['reason']) || empty(trim($data['reason']))) {
            throw ApiProblemException::badRequest('reason is required');
        }

        $result->contest($data['reason']);

        $contestedStatus = $this->entityManager->getRepository(GameStatus::class)->findOneBy(['name' => 'contested']);
        if ($contestedStatus) {
            $game->setStatus($contestedStatus);
        }

        $this->entityManager->persist($result);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        // Notifier le soumetteur que le resultat a ete conteste
        $submitter = $result->getSubmittedBy();
        if ($submitter) {
            try {
                $pushNotificationService->sendToUser(
                    $submitter,
                    'Resultat conteste',
                    sprintf('Le resultat du match %s vs %s a ete conteste', $team1?->getName(), $team2?->getName()),
                    '/matches',
                );
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send push notification', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->json($this->formatEntityData($result));
    }

    /**
     * PATCH /api/games/{id}/results/{resultId}/admin-validate - Un admin valide (ou corrige) le resultat
     */
    #[Route('/games/{id}/results/{resultId}/admin-validate', name: 'api_game_result_admin_validate', methods: ['PATCH'], requirements: ['id' => '\d+', 'resultId' => '\d+'])]
    public function adminValidateResult(
        int $id,
        int $resultId,
        Request $request,
    ): JsonResponse {
        $this->checkUserRole('ROLE_ADMIN');

        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');
        $result = $this->findEntityOrFail('App\Entity\MatchResult', $resultId, 'MatchResult');

        if ($result->getGame()?->getId() !== $game->getId()) {
            throw ApiProblemException::badRequest('This result does not belong to this game');
        }

        $data = $this->getRequestData($request);

        // L'admin peut optionnellement corriger les scores
        $score1 = $data['score1'] ?? $result->getScore1();
        $score2 = $data['score2'] ?? $result->getScore2();

        if (!is_int($score1) || !is_int($score2) || $score1 < 0 || $score2 < 0) {
            throw ApiProblemException::badRequest('score1 and score2 must be non-negative integers');
        }

        $result->validate();
        $result->setScore1($score1);
        $result->setScore2($score2);

        $game->setScore1($score1);
        $game->setScore2($score2);
        $game->setWinner($this->calculateWinner($score1, $score2));

        $playedStatus = $this->entityManager->getRepository(GameStatus::class)->findOneBy(['name' => 'played']);
        if ($playedStatus) {
            $game->setStatus($playedStatus);
        }

        $this->entityManager->persist($result);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $this->json($this->formatEntityData($result));
    }

    /**
     * GET /api/games/{id}/results - Liste les resultats d'un match
     */
    #[Route('/games/{id}/results', name: 'api_game_results', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getGameResults(int $id, MatchResultRepository $resultRepository): JsonResponse
    {
        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        $results = $resultRepository->findByGame($game);
        $data = array_map(fn($result) => $this->formatEntityData($result), $results);

        return $this->json($data);
    }

    private function calculateWinner(int $score1, int $score2): ?int
    {
        if ($score1 > $score2) {
            return 1;
        }
        if ($score2 > $score1) {
            return 2;
        }

        return null; // egalite
    }
}
