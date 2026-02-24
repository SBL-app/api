<?php

namespace App\Controller;

use App\Repository\DivisionRepository;
use App\Repository\TeamStatRepository;
use App\Repository\TeamRepository;
use App\Repository\SeasonRepository;
use App\Repository\GameStatusRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Division;
use App\Entity\Season;
use App\Entity\Game;
use App\Repository\PlayerRepository;
use App\Repository\GameRepository;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

#[Route("/api")]
class DivisionController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Division) {
            throw new \InvalidArgumentException(
                "Entity must be an instance of Division",
            );
        }

        return [
            "id" => $entity->getId(),
            "name" => $entity->getName(),
            "season_id" => $entity->getSeason()
                ? $entity->getSeason()->getId()
                : null,
            "season_name" => $entity->getSeason()
                ? $entity->getSeason()->getName()
                : "",
        ];
    }

    /**
     * Formate les données de base d'une division
     */
    private function formatDivisionData(Division $division): array
    {
        return $this->formatEntityData($division);
    }

    #[Route("/division/{id}", name: "app_division_get", methods: ["GET"], requirements: ["id" => "\d+"])]
    public function getDivision(int $id): JsonResponse
    {
        return $this->getEntityById("App\Entity\Division", $id, "Division");
    }

    #[Route("/division", name: "app_divisions", methods: ["GET"])]
    public function getDivisions(
        Request $request,
        DivisionRepository $divisionRepository,
    ): JsonResponse {
        $id = $request->query->get("id");

        // Backward compatibility - deprecated
        if ($id) {
            $this->logger->warning("Deprecated: Using ?id parameter for division. Use /division/{id} instead", ["id" => $id]);
            return $this->getEntityById("App\Entity\Division", $id, "Division");
        }

        // Sinon, retourner toutes les divisions
        $divisions = $divisionRepository->findAll();
        $data = array_map(function ($division) {
            return $this->formatDivisionData($division);
        }, $divisions);
        return $this->json($data);
    }

    #[Route("/division/season", name: "app_divisions_season", methods: ["GET"])]
    public function getDivisionBySeason(
        Request $request,
        DivisionRepository $divisionRepository,
        TeamStatRepository $teamStatRepository,
        TeamRepository $teamRepository,
    ): JsonResponse {
        $seasonId = $request->query->get("id");
        if (!$seasonId) {
            return $this->json(["error" => "Season ID is required"], 400);
        }

        $divisions = $divisionRepository->findBy(["season" => $seasonId]);
        if (!$divisions) {
            return $this->json(
                ["error" => "No divisions found for this season"],
                404,
            );
        }

        $data = array_map(function ($division) use (
            $teamStatRepository,
            $teamRepository,
        ) {
            $seasonId = $division->getSeason()
                ? $division->getSeason()->getId()
                : null;
            $teams = $teamStatRepository->findBy(["division" => $division]);
            $teamData = array_map(function ($teamStat) use ($teamRepository) {
                $team = $teamStat->getTeam();
                $teamEntity = $teamRepository->find($team->getId());
                return [
                    "id" => $team->getId(),
                    "name" => $teamEntity->getName(),
                    "wins" => $teamStat->getWins(),
                    "losses" => $teamStat->getLosses(),
                    "points" => $teamStat->getPoints(),
                ];
            }, $teams);
            return [
                "id" => $division->getId(),
                "name" => $division->getName(),
                "season" => $seasonId,
                "teams" => $teamData,
            ];
        }, $divisions);
        return $this->json($data);
    }

    #[Route("/division/teams", name: "app_division_teams", methods: ["GET"])]
    public function getTeamsByDivision(
        Request $request,
        DivisionRepository $divisionRepository,
        TeamStatRepository $teamStatRepository,
        TeamRepository $teamRepository,
        PlayerRepository $playerRepository,
    ): JsonResponse {
        $id = $request->query->get("id");
        if (!$id) {
            return $this->json(["error" => "Division ID is required"], 400);
        }

        $division = $divisionRepository->find($id);
        if (!$division) {
            return $this->json(["error" => "Division not found"], 404);
        }

        $teamStats = $teamStatRepository->findBy(["division" => $division]);
        usort($teamStats, function ($a, $b) {
            return $b->getPoints() - $a->getPoints();
        });
        $data = array_map(function ($teamStat) use (
            $teamRepository,
            $playerRepository,
        ) {
            $team = $teamStat->getTeam();
            $teamEntity = $teamRepository->find($team->getId());
            $players = $playerRepository->findBy(["team" => $teamEntity]);

            $members = array_map(function ($player) {
                return [
                    "id" => $player->getId(),
                    "name" => $player->getName(),
                    "discord" => $player->getDiscord(),
                ];
            }, $players);

            return [
                "id" => $team->getId(),
                "name" => $teamEntity->getName(),
                "captain" => $teamEntity->getCaptain()
                    ? $teamEntity->getCaptain()->getName()
                    : null,
                "members" => $members,
            ];
        }, $teamStats);

        return $this->json($data);
    }

    #[Route("/division/games", name: "app_division_games", methods: ["GET"])]
    public function getGamesByDivision(
        Request $request,
        DivisionRepository $divisionRepository,
        GameRepository $gameRepository,
    ): JsonResponse {
        $id = $request->query->get("id");
        if (!$id) {
            return $this->json(["error" => "Division ID is required"], 400);
        }

        $division = $divisionRepository->find($id);
        if (!$division) {
            return $this->json(["error" => "Division not found"], 404);
        }

        $games = $gameRepository->findBy(["division" => $division]);
        $rep = [];

        foreach ($games as $game) {
            $week = $game->getWeek();
            if (!isset($rep[$week])) {
                $rep[$week] = [
                    "week" => $week,
                    "games" => [],
                ];
            }
            $rep[$week]["games"][] = [
                "id" => $game->getId(),
                "date" => $game->getDate()->format("d-m-Y"),
                "team1" => $game->getTeam1()->getName(),
                "team2" => $game->getTeam2()->getName(),
                "score1" => $game->getScore1(),
                "score2" => $game->getScore2(),
                "winner" => $game->getWinner(),
                "status" => $game->getStatus()->getName(),
            ];
        }
        $response = array_values($rep);
        return $this->json($response);
    }

    #[
        Route(
            "/division/details",
            name: "app_division_details",
            methods: ["GET"],
        ),
    ]
    public function getDivisionDetails(
        Request $request,
        DivisionRepository $divisionRepository,
        TeamStatRepository $teamStatRepository,
        TeamRepository $teamRepository,
        PlayerRepository $playerRepository,
        GameRepository $gameRepository,
    ): JsonResponse {
        $divisionId = $request->query->get("division_id");
        if (!$divisionId) {
            return $this->json(["error" => "Division ID is required"], 400);
        }

        try {
            // Récupération de la division
            $division = $this->findEntityOrFail(
                "App\Entity\Division",
                $divisionId,
                "Division",
            );

            // Informations de base de la division
            $divisionData = $this->formatEntityData($division);

            // Récupération des statistiques d'équipes pour le classement
            $teamStats = $teamStatRepository->findBy(["division" => $division]);

            // Tri par points décroissant pour le classement
            usort($teamStats, function ($a, $b) {
                return $b->getPoints() - $a->getPoints();
            });

            // Formation du classement simplifié (nom équipe + statistiques)
            $ranking = [];
            $teams = [];

            foreach ($teamStats as $position => $teamStat) {
                $team = $teamStat->getTeam();
                $teamEntity = $teamRepository->find($team->getId());
                $players = $playerRepository->findBy(["team" => $teamEntity]);

                $members = array_map(function ($player) {
                    return [
                        "id" => $player->getId(),
                        "name" => $player->getName(),
                        "discord" => $player->getDiscord(),
                    ];
                }, $players);

                // Classement simplifié
                $ranking[] = [
                    "position" => $position + 1,
                    "team_id" => $team->getId(),
                    "team_name" => $teamEntity->getName(),
                    "stats" => [
                        "wins" => $teamStat->getWins(),
                        "losses" => $teamStat->getLosses(),
                        "ties" => $teamStat->getTies(),
                        "winRounds" => $teamStat->getWinRounds(),
                        "looseRounds" => $teamStat->getLooseRounds(),
                        "points" => $teamStat->getPoints(),
                    ],
                ];

                // Détails complets des équipes pour la fin de réponse
                $teams[] = [
                    "id" => $team->getId(),
                    "name" => $teamEntity->getName(),
                    "captain" => $teamEntity->getCaptain()
                        ? $teamEntity->getCaptain()->getName()
                        : null,
                    "members" => $members,
                ];
            }

            // Récupération des matchs de la division organisés par semaine
            $games = $gameRepository->findBy(["division" => $division]);
            $gamesData = [];

            foreach ($games as $game) {
                $week = $game->getWeek();
                if (!isset($gamesData[$week])) {
                    $gamesData[$week] = [
                        "week" => $week,
                        "games" => [],
                    ];
                }
                $gamesData[$week]["games"][] = [
                    "id" => $game->getId(),
                    "date" => $game->getDate()
                        ? $game->getDate()->format("d-m-Y")
                        : null,
                    "team1" => $game->getTeam1()
                        ? $game->getTeam1()->getName()
                        : null,
                    "team2" => $game->getTeam2()
                        ? $game->getTeam2()->getName()
                        : null,
                    "score1" => $game->getScore1(),
                    "score2" => $game->getScore2(),
                    "winner" => $game->getWinner(),
                    "status" => $game->getStatus()
                        ? $game->getStatus()->getName()
                        : null,
                ];
            }

            // Assemblage de la réponse complète avec les détails des équipes à la fin
            $response = [
                "division" => $divisionData,
                "ranking" => $ranking,
                "teams_count" => count($teamStats),
                "games" => array_values($gamesData),
                "teams" => $teams,
            ];

            return $this->json($response);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 500;
            return $this->json(["error" => $e->getMessage()], $code);
        }
    }

    #[Route("/division", name: "app_division_create", methods: ["POST"])]
    public function createDivision(Request $request): JsonResponse
    {
        try {
            $data = $this->getRequestData($request);
            $division = new Division();

            $division->setName($data["name"]);

            if (isset($data["season"])) {
                $season = $this->findEntityOrFail(
                    "App\Entity\Season",
                    $data["season"],
                    "Season",
                );
                $division->setSeason($season);
            } else {
                $division->setSeason(null);
            }

            return $this->securedCreateEntity($division);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(["error" => $e->getMessage()], $code);
        }
    }

    #[Route("/division/{id}", name: "app_division_update", methods: ["PUT"], requirements: ["id" => "\d+"])]
    public function updateDivision(int $id, Request $request): JsonResponse
    {
        try {
            $division = $this->findEntityOrFail(
                "App\Entity\Division",
                $id,
                "Division",
            );
            $data = $this->getRequestData($request);

            $division->setName($data["name"]);

            if (isset($data["season"])) {
                $season = $this->findEntityOrFail(
                    "App\Entity\Season",
                    $data["season"],
                    "Season",
                );
                $division->setSeason($season);
            }

            return $this->securedUpdateEntity($division);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(["error" => $e->getMessage()], $code);
        }
    }

    #[Route("/division/{id}", name: "app_division_patch", methods: ["PATCH"], requirements: ["id" => "\d+"])]
    public function patchDivision(int $id, Request $request): JsonResponse
    {
        try {
            $division = $this->findEntityOrFail(
                "App\Entity\Division",
                $id,
                "Division",
            );
            $data = $this->getRequestData($request);

            if (isset($data["name"])) {
                $division->setName($data["name"]);
            }
            if (isset($data["season"])) {
                $season = $this->findEntityOrFail(
                    "App\Entity\Season",
                    $data["season"],
                    "Season",
                );
                $division->setSeason($season);
            }

            return $this->securedUpdateEntity($division);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(["error" => $e->getMessage()], $code);
        }
    }

    #[Route("/division/{id}", name: "app_division_delete", methods: ["DELETE"], requirements: ["id" => "\d+"])]
    public function deleteDivision(int $id): JsonResponse
    {
        try {
            $division = $this->findEntityOrFail(
                "App\Entity\Division",
                $id,
                "Division",
            );

            return $this->securedDeleteEntity($division, "Division");
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(["error" => $e->getMessage()], $code);
        }
    }

    /**
     * Génère un planning de matchs pour une division en Round Robin ou Double Round Robin
     *
     * Round Robin : Chaque équipe joue une fois contre chaque autre équipe
     * Double Round Robin : Chaque équipe joue deux fois contre chaque autre équipe (aller-retour)
     */
    #[
        Route(
            "/division/schedule",
            name: "app_division_generate_schedule",
            methods: ["POST"],
        ),
    ]
    public function generateSchedule(
        Request $request,
        DivisionRepository $divisionRepository,
        TeamStatRepository $teamStatRepository,
        GameStatusRepository $gameStatusRepository,
    ): JsonResponse {
        try {
            $this->checkModificationPermissions();
            $data = $this->getRequestData($request);

            // Validation des paramètres requis
            if (!isset($data["division_id"])) {
                return $this->missingParameterError("division_id");
            }
            if (!isset($data["status_id"])) {
                return $this->missingParameterError("status_id");
            }
            if (
                !isset($data["type"]) ||
                !in_array($data["type"], ["round_robin", "double_round_robin"])
            ) {
                return $this->json(
                    [
                        "error" =>
                            'type must be "round_robin" or "double_round_robin"',
                    ],
                    400,
                );
            }

            // Récupération de la division
            $division = $this->findEntityOrFail(
                "App\Entity\Division",
                $data["division_id"],
                "Division",
            );

            // Récupération du statut de match
            $status = $this->findEntityOrFail(
                "App\Entity\GameStatus",
                $data["status_id"],
                "GameStatus",
            );

            // Récupération des équipes de la division via TeamStat
            $teamStats = $teamStatRepository->findBy(["division" => $division]);
            $teams = array_map(
                fn($teamStat) => $teamStat->getTeam(),
                $teamStats,
            );

            if (count($teams) < 2) {
                return $this->json(
                    [
                        "error" =>
                            "Division must have at least 2 teams to generate a schedule",
                    ],
                    400,
                );
            }

            // Paramètres optionnels
            $startDate = isset($data["start_date"])
                ? new \DateTime($data["start_date"])
                : new \DateTime();
            $daysBetweenWeeks = $data["days_between_weeks"] ?? 7;

            // Génération du planning Round Robin
            $schedule = $this->generateRoundRobinSchedule($teams);

            // Si Double Round Robin, ajouter les matchs retour
            if ($data["type"] === "double_round_robin") {
                $returnSchedule = $this->generateReturnSchedule($schedule);
                $schedule = array_merge($schedule, $returnSchedule);
            }

            // Création des matchs dans la base de données
            $createdGames = [];
            $currentDate = clone $startDate;

            foreach ($schedule as $weekIndex => $weekGames) {
                $weekNumber = $weekIndex + 1;
                foreach ($weekGames as $match) {
                    $game = new Game();
                    $game->setTeam1($match["team1"]);
                    $game->setTeam2($match["team2"]);
                    $game->setDivision($division);
                    $game->setStatus($status);
                    $game->setWeek($weekNumber);
                    $game->setDate(clone $currentDate);
                    $game->setScore1(0);
                    $game->setScore2(0);
                    $game->setWinner(null);

                    $this->entityManager->persist($game);
                    $createdGames[] = [
                        "week" => $weekNumber,
                        "team1" => $match["team1"]->getName(),
                        "team2" => $match["team2"]->getName(),
                        "date" => $currentDate->format("Y-m-d"),
                    ];
                }
                // Avancer à la semaine suivante
                $currentDate->modify("+{$daysBetweenWeeks} days");
            }

            $this->entityManager->flush();

            return $this->json([
                "message" => "Schedule generated successfully",
                "type" => $data["type"],
                "division" => $division->getName(),
                "total_games" => count($createdGames),
                "total_weeks" => count($schedule),
                "games" => $createdGames,
            ]);
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->permissionDeniedResponse($e->getMessage());
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(["error" => $e->getMessage()], $code);
        }
    }

    /**
     * Génère un planning Round Robin en utilisant l'algorithme du cercle (circle method)
     *
     * @param array $teams Liste des équipes
     * @return array Planning organisé par semaines
     */
    private function generateRoundRobinSchedule(array $teams): array
    {
        $schedule = [];
        $teamCount = count($teams);

        // Si nombre impair d'équipes, ajouter une équipe "bye"
        if ($teamCount % 2 !== 0) {
            $teams[] = null; // null représente un "bye" (repos)
            $teamCount++;
        }

        $rounds = $teamCount - 1;
        $matchesPerRound = $teamCount / 2;

        // Fixer la première équipe et faire tourner les autres
        $fixedTeam = array_shift($teams);
        $rotatingTeams = $teams;

        for ($round = 0; $round < $rounds; $round++) {
            $weekGames = [];

            // Premier match : équipe fixe contre la première équipe rotative
            if ($rotatingTeams[0] !== null && $fixedTeam !== null) {
                $weekGames[] = [
                    "team1" => $fixedTeam,
                    "team2" => $rotatingTeams[0],
                ];
            }

            // Autres matchs : appariement symétrique
            for ($i = 1; $i < $matchesPerRound; $i++) {
                $team1 = $rotatingTeams[$i];
                $team2 = $rotatingTeams[$teamCount - 1 - $i];

                if ($team1 !== null && $team2 !== null) {
                    $weekGames[] = [
                        "team1" => $team1,
                        "team2" => $team2,
                    ];
                }
            }

            if (!empty($weekGames)) {
                $schedule[] = $weekGames;
            }

            // Rotation des équipes (la première équipe reste fixe)
            $lastTeam = array_pop($rotatingTeams);
            array_unshift($rotatingTeams, $lastTeam);
        }

        return $schedule;
    }

    /**
     * Génère les matchs retour pour un Double Round Robin
     * Inverse simplement team1 et team2 pour chaque match
     *
     * @param array $schedule Planning aller
     * @return array Planning retour
     */
    private function generateReturnSchedule(array $schedule): array
    {
        $returnSchedule = [];

        foreach ($schedule as $weekGames) {
            $returnWeekGames = [];
            foreach ($weekGames as $match) {
                $returnWeekGames[] = [
                    "team1" => $match["team2"],
                    "team2" => $match["team1"],
                ];
            }
            $returnSchedule[] = $returnWeekGames;
        }

        return $returnSchedule;
    }
}
