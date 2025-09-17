<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Game;
use App\Repository\GameRepository;
use App\Repository\TeamRepository;
use App\Repository\GameStatusRepository;
use App\Repository\DivisionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api')]
class GameController extends BaseController
{
    /**
     * Formate les données de base d'un match
     */
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Game) {
            throw new \InvalidArgumentException('Entity must be an instance of Game');
        }
        return $this->formatGameData($entity);
    }

    /**
     * Formate les données d'un match avec vérification des valeurs nulles
     */
    private function formatGameData(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'date' => $game->getDate() ? $game->getDate()->format('Y-m-d H:i:s') : null,
            'week' => $game->getWeek(),
            'team1' => $game->getTeam1() ? $game->getTeam1()->getName() : null,
            'team2' => $game->getTeam2() ? $game->getTeam2()->getName() : null,
            'score1' => $game->getScore1(),
            'score2' => $game->getScore2(),
            'winner' => $game->getWinner(),
            'status' => $game->getStatus() ? $game->getStatus()->getName() : null,
            'division' => $game->getDivision() ? $game->getDivision()->getName() : null,
            'is_forfeit' => $game->isForfeit(),
            'forfeit_team' => $game->getForfeitTeam(),
            'forfeit_reason' => $game->getForfeitReason()
        ];
    }

    /**
     * Récupère les matchs d'une équipe avec filtrage optionnel par division
     */
    private function getGamesByTeam(GameRepository $gameRepository, int $teamId, ?int $divisionId = null): array
    {
        $criteria1 = ['team1' => $teamId];
        $criteria2 = ['team2' => $teamId];

        if ($divisionId) {
            $criteria1['division'] = $divisionId;
            $criteria2['division'] = $divisionId;
        }

        $games1 = $gameRepository->findBy($criteria1);
        $games2 = $gameRepository->findBy($criteria2);

        return array_merge($games1, $games2);
    }

    /**
     * Valide et associe les entités liées à un match
     */
    private function setGameRelations(Game $game, array $data): ?JsonResponse
    {
        // Validation et association des équipes
        if (isset($data['team1'])) {
            $team1 = $this->entityManager->getRepository('App\Entity\Team')->find($data['team1']);
            if ($data['team1'] && !$team1) {
                return $this->json(['error' => 'Invalid team1 id'], 400);
            }
            $game->setTeam1($team1);
        }

        if (isset($data['team2'])) {
            $team2 = $this->entityManager->getRepository('App\Entity\Team')->find($data['team2']);
            if ($data['team2'] && !$team2) {
                return $this->json(['error' => 'Invalid team2 id'], 400);
            }
            $game->setTeam2($team2);
        }

        // Validation et association du statut
        if (isset($data['status'])) {
            $status = $this->entityManager->getRepository('App\Entity\GameStatus')->find($data['status']);
            if ($data['status'] && !$status) {
                return $this->json(['error' => 'Invalid status id'], 400);
            }
            $game->setStatus($status);
        }

        // Validation et association de la division
        if (isset($data['division'])) {
            $division = $this->entityManager->getRepository('App\Entity\Division')->find($data['division']);
            if ($data['division'] && !$division) {
                return $this->json(['error' => 'Invalid division id'], 400);
            }
            $game->setDivision($division);
        }

        return null; // Pas d'erreur
    }

    #[Route('/games', name: 'app_game', methods: ['GET'])]
    public function getGames(Request $request, GameRepository $gameRepository): JsonResponse
    {
        $id = $request->query->get('id');
        $divisionId = $request->query->get('division_id');
        $teamId = $request->query->get('team_id');

        // Si un ID est fourni, retourner le match spécifique
        if ($id) {
            $game = $gameRepository->find($id);
            if (!$game) {
                return $this->json(['error' => 'Game not found'], 404);
            }
            return $this->json($this->formatGameData($game));
        }

        // Si team_id est fourni (avec ou sans division_id)
        if ($teamId) {
            $games = $this->getGamesByTeam($gameRepository, (int)$teamId, $divisionId ? (int)$divisionId : null);

            if (empty($games)) {
                $errorMessage = $divisionId
                    ? 'No games found for this team in this division'
                    : 'No games found for this team';
                return $this->json(['error' => $errorMessage], 404);
            }

            $data = array_map(function ($game) {
                return $this->formatGameData($game);
            }, $games);
            return $this->json($data);
        }

        // Si seulement division_id est fourni, retourner les matchs de cette division
        if ($divisionId) {
            $games = $gameRepository->findBy(['division' => $divisionId]);
            if (empty($games)) {
                return $this->json(['error' => 'No games found for this division'], 404);
            }
            $data = array_map(function ($game) {
                return $this->formatGameData($game);
            }, $games);
            return $this->json($data);
        }

        // Sinon, retourner tous les matchs
        $games = $gameRepository->findAll();
        $data = array_map(function ($game) {
            return $this->formatGameData($game);
        }, $games);
        return $this->json($data);
    }

    #[Route('/games', name: 'app_game_create', methods: ['POST'])]
    public function createGame(Request $request): JsonResponse
    {
        try {
            $data = $this->getRequestData($request);
            $game = new Game();

            // Définition des propriétés de base
            $game->setDate(isset($data['date']) ? new \DateTime($data['date']) : null);
            $game->setWeek($data['week'] ?? null);

            // Gestion des forfaits
            if (isset($data['is_forfeit']) && $data['is_forfeit']) {
                $game->setIsForfeit(true);
                if (isset($data['forfeit_team'])) {
                    $forfeitTeam = (int)$data['forfeit_team'];
                    if ($forfeitTeam !== 1 && $forfeitTeam !== 2) {
                        return $this->json(['error' => 'forfeit_team must be 1 or 2'], 400);
                    }
                    $game->setForfeitTeam($forfeitTeam);
                }
                if (isset($data['forfeit_reason'])) {
                    $game->setForfeitReason($data['forfeit_reason']);
                }
            } else {
                // Scores normaux seulement si ce n'est pas un forfait
                $game->setScore1($data['score1'] ?? null);
                $game->setScore2($data['score2'] ?? null);
                $game->setWinner($data['winner'] ?? null);
            }

            // Validation et association des entités liées
            $error = $this->setGameRelations($game, $data);
            if ($error) {
                return $error;
            }

            $this->saveEntity($game);

            return $this->json($this->formatGameData($game));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/games', name: 'app_game_update', methods: ['PUT'])]
    public function updateGame(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');
            $data = $this->getRequestData($request);

            // Mise à jour des propriétés de base
            $game->setDate(new \DateTime($data['date']));
            $game->setWeek($data['week']);

            // Gestion des forfaits
            if (isset($data['is_forfeit']) && $data['is_forfeit']) {
                $game->setIsForfeit(true);
                if (isset($data['forfeit_team'])) {
                    $forfeitTeam = (int)$data['forfeit_team'];
                    if ($forfeitTeam !== 1 && $forfeitTeam !== 2) {
                        return $this->json(['error' => 'forfeit_team must be 1 or 2'], 400);
                    }
                    $game->setForfeitTeam($forfeitTeam);
                }
                if (isset($data['forfeit_reason'])) {
                    $game->setForfeitReason($data['forfeit_reason']);
                }
            } else {
                // Réinitialiser les données de forfait et utiliser les scores normaux
                $game->setIsForfeit(false);
                $game->setForfeitTeam(null);
                $game->setForfeitReason(null);
                $game->setScore1($data['score1']);
                $game->setScore2($data['score2']);
                $game->setWinner($data['winner']);
            }

            // Validation et association des entités liées (mode strict pour PUT)
            $team1 = $this->findEntityOrFail('App\Entity\Team', $data['team1'], 'Team1');
            $team2 = $this->findEntityOrFail('App\Entity\Team', $data['team2'], 'Team2');
            $status = $this->findEntityOrFail('App\Entity\GameStatus', $data['status'], 'GameStatus');
            $division = $this->findEntityOrFail('App\Entity\Division', $data['division'], 'Division');

            $game->setTeam1($team1);
            $game->setTeam2($team2);
            $game->setStatus($status);
            $game->setDivision($division);

            $this->saveEntity($game);

            return $this->json($this->formatGameData($game));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/games', name: 'app_game_patch', methods: ['PATCH'])]
    public function patchGame(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');
            $data = $this->getRequestData($request);

            // Mise à jour conditionnelle des propriétés
            if (isset($data['date'])) {
                $game->setDate(new \DateTime($data['date']));
            }
            if (isset($data['week'])) {
                $game->setWeek($data['week']);
            }

            // Gestion des forfaits
            if (isset($data['is_forfeit'])) {
                $game->setIsForfeit((bool)$data['is_forfeit']);
            }
            if (isset($data['forfeit_team'])) {
                $forfeitTeam = (int)$data['forfeit_team'];
                if ($forfeitTeam !== 1 && $forfeitTeam !== 2 && $forfeitTeam !== null) {
                    return $this->json(['error' => 'forfeit_team must be 1, 2, or null'], 400);
                }
                $game->setForfeitTeam($forfeitTeam);
            }
            if (isset($data['forfeit_reason'])) {
                $game->setForfeitReason($data['forfeit_reason']);
            }

            // Mise à jour des scores seulement si ce n'est pas un forfait ou si on désactive le forfait
            if (!$game->isForfeit()) {
                if (isset($data['score1'])) {
                    $game->setScore1($data['score1']);
                }
                if (isset($data['score2'])) {
                    $game->setScore2($data['score2']);
                }
                if (isset($data['winner'])) {
                    $game->setWinner($data['winner']);
                }
            }

            // Validation et association des entités liées
            $error = $this->setGameRelations($game, $data);
            if ($error) {
                return $error;
            }

            $this->saveEntity($game);

            return $this->json($this->formatGameData($game));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/games', name: 'app_game_delete', methods: ['DELETE'])]
    public function deleteGame(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');
            $this->deleteEntity($game);

            return $this->deleteSuccessResponse('Game');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
