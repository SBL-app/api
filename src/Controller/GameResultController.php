<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\GameResult;
use App\Exception\ApiProblemException;
use App\Repository\GameResultRepository;
use App\Repository\GameStatusRepository;
use App\Repository\TeamStatRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class GameResultController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof GameResult) {
            throw new \InvalidArgumentException('Entity must be an instance of GameResult');
        }

        return [
            'id' => $entity->getId(),
            'game_id' => $entity->getGame()?->getId(),
            'submitted_by_team_id' => $entity->getSubmittedByTeam()?->getId(),
            'submitted_by_team_name' => $entity->getSubmittedByTeam()?->getName(),
            'submitted_by_id' => $entity->getSubmittedBy()?->getId(),
            'score1' => $entity->getScore1(),
            'score2' => $entity->getScore2(),
            'status' => $entity->getStatus(),
            'responded_by_id' => $entity->getRespondedBy()?->getId(),
            'responded_at' => $entity->getRespondedAt()?->format('Y-m-d H:i:s'),
            'created_at' => $entity->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('/games/{id}/result', name: 'app_game_result_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getResult(int $id, GameResultRepository $resultRepository): JsonResponse
    {
        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        $result = $resultRepository->findLatestByGame($game);
        if (!$result) {
            throw ApiProblemException::notFound('No result found for this game');
        }

        return $this->json($this->formatEntityData($result));
    }

    #[Route('/games/{id}/result', name: 'app_game_result_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function submitResult(
        int $id,
        Request $request,
        GameResultRepository $resultRepository,
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

        if ($resultRepository->findPendingByGame($game)) {
            throw ApiProblemException::conflict('A result is already pending validation for this game');
        }

        $data = $this->getRequestData($request);

        if (!isset($data['score1']) || !isset($data['score2'])) {
            throw ApiProblemException::badRequest('score1 and score2 are required');
        }

        if ((int) $data['score1'] < 0 || (int) $data['score2'] < 0) {
            throw ApiProblemException::badRequest('score1 and score2 must be non-negative');
        }

        $result = new GameResult();
        $result->setGame($game);
        $result->setSubmittedByTeam($team);
        $result->setSubmittedBy($user);
        $result->setScore1((int) $data['score1']);
        $result->setScore2((int) $data['score2']);

        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return $this->json($this->formatEntityData($result), 201);
    }

    #[Route('/games/{id}/result/confirm', name: 'app_game_result_confirm', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function confirmResult(
        int $id,
        GameResultRepository $resultRepository,
        GameStatusRepository $gameStatusRepository,
        TeamStatRepository $teamStatRepository,
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        $result = $resultRepository->findPendingByGame($game);
        if (!$result) {
            throw ApiProblemException::notFound('No pending result found for this game');
        }

        if ($game->getStatus()?->getName() === 'played') {
            throw ApiProblemException::conflict('This game result has already been confirmed');
        }

        if ($result->getSubmittedByTeam()->isCaptain($user)) {
            throw ApiProblemException::forbidden('You cannot confirm your own submitted result');
        }

        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();

        if (!$team1 || !$team2) {
            throw ApiProblemException::badRequest('Game must have two teams');
        }

        $opposingTeam = $result->getSubmittedByTeam() === $team1 ? $team2 : $team1;
        if (!$opposingTeam->isCaptain($user)) {
            throw ApiProblemException::forbidden('You must be a captain of the opposing team to confirm');
        }

        $result->confirm($user);

        $score1 = $result->getScore1();
        $score2 = $result->getScore2();
        $winner = match(true) {
            $score1 > $score2 => 1,
            $score2 > $score1 => 2,
            default => null,
        };

        $game->setScore1($score1);
        $game->setScore2($score2);
        $game->setWinner($winner);

        $playedStatus = $gameStatusRepository->findOneBy(['name' => 'played']);
        if (!$playedStatus) {
            throw ApiProblemException::badRequest('Game status "played" not found in database');
        }
        $game->setStatus($playedStatus);

        $this->applyStatsUpdate($teamStatRepository, $game, $score1, $score2, $winner);

        $this->entityManager->persist($result);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $this->json($this->formatEntityData($result));
    }

    #[Route('/games/{id}/result/dispute', name: 'app_game_result_dispute', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function disputeResult(
        int $id,
        GameResultRepository $resultRepository,
    ): JsonResponse {
        $user = $this->getAuthenticatedUser();
        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        $result = $resultRepository->findPendingByGame($game);
        if (!$result) {
            throw ApiProblemException::notFound('No pending result found for this game');
        }

        if ($result->getSubmittedByTeam()->isCaptain($user)) {
            throw ApiProblemException::forbidden('You cannot dispute your own submitted result');
        }

        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();

        if (!$team1 || !$team2) {
            throw ApiProblemException::badRequest('Game must have two teams');
        }

        $opposingTeam = $result->getSubmittedByTeam() === $team1 ? $team2 : $team1;

        if (!$opposingTeam->isCaptain($user)) {
            throw ApiProblemException::forbidden('You must be a captain of the opposing team to dispute');
        }

        $result->dispute($user);

        $this->entityManager->persist($result);
        $this->entityManager->flush();

        return $this->json($this->formatEntityData($result));
    }

    #[Route('/games/{id}/result/admin-resolve', name: 'app_game_result_admin_resolve', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function adminResolveResult(
        int $id,
        Request $request,
        GameResultRepository $resultRepository,
        GameStatusRepository $gameStatusRepository,
        TeamStatRepository $teamStatRepository,
    ): JsonResponse {
        $this->checkUserRole('ROLE_ADMIN');

        $user = $this->getAuthenticatedUser();
        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        $result = $resultRepository->findDisputedByGame($game);
        if (!$result) {
            throw ApiProblemException::notFound('No disputed result found for this game');
        }

        $data = $this->getRequestData($request);

        if (!isset($data['score1']) || !isset($data['score2'])) {
            throw ApiProblemException::badRequest('score1 and score2 are required');
        }

        if ((int) $data['score1'] < 0 || (int) $data['score2'] < 0) {
            throw ApiProblemException::badRequest('score1 and score2 must be non-negative');
        }

        $score1 = (int) $data['score1'];
        $score2 = (int) $data['score2'];

        $result->setScore1($score1);
        $result->setScore2($score2);
        $result->confirm($user);

        $winner = match(true) {
            $score1 > $score2 => 1,
            $score2 > $score1 => 2,
            default => null,
        };

        $game->setScore1($score1);
        $game->setScore2($score2);
        $game->setWinner($winner);

        $playedStatus = $gameStatusRepository->findOneBy(['name' => 'played']);
        if (!$playedStatus) {
            throw ApiProblemException::badRequest('Game status "played" not found in database');
        }
        $game->setStatus($playedStatus);

        $this->applyStatsUpdate($teamStatRepository, $game, $score1, $score2, $winner);

        $this->entityManager->persist($result);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $this->json($this->formatEntityData($result));
    }

    private function applyStatsUpdate(
        TeamStatRepository $teamStatRepository,
        Game $game,
        int $score1,
        int $score2,
        ?int $winner,
    ): void {
        $division = $game->getDivision();
        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();

        $stat1 = $teamStatRepository->findByTeamAndDivision($team1, $division);
        $stat2 = $teamStatRepository->findByTeamAndDivision($team2, $division);

        if (!$stat1 || !$stat2) {
            throw ApiProblemException::badRequest('TeamStat not found for one or both teams in this division');
        }

        if ($winner === 1) {
            $stat1->setWins(($stat1->getWins() ?? 0) + 1);
            $stat1->setPoints(($stat1->getPoints() ?? 0) + 3);
            $stat2->setLosses(($stat2->getLosses() ?? 0) + 1);
        } elseif ($winner === 2) {
            $stat2->setWins(($stat2->getWins() ?? 0) + 1);
            $stat2->setPoints(($stat2->getPoints() ?? 0) + 3);
            $stat1->setLosses(($stat1->getLosses() ?? 0) + 1);
        } else {
            $stat1->setTies(($stat1->getTies() ?? 0) + 1);
            $stat1->setPoints(($stat1->getPoints() ?? 0) + 1);
            $stat2->setTies(($stat2->getTies() ?? 0) + 1);
            $stat2->setPoints(($stat2->getPoints() ?? 0) + 1);
        }

        $stat1->setWinRounds(($stat1->getWinRounds() ?? 0) + $score1);
        $stat1->setLooseRounds(($stat1->getLooseRounds() ?? 0) + $score2);
        $stat2->setWinRounds(($stat2->getWinRounds() ?? 0) + $score2);
        $stat2->setLooseRounds(($stat2->getLooseRounds() ?? 0) + $score1);

        $this->entityManager->persist($stat1);
        $this->entityManager->persist($stat2);
    }
}
