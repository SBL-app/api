<?php

namespace App\Controller;

use App\Entity\GameResult;
use App\Exception\ApiProblemException;
use App\Repository\GameResultRepository;
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
}
