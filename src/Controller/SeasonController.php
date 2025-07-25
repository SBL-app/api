<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Season;
use App\Repository\DivisionRepository;
use App\Repository\GameRepository;
use App\Repository\GameStatusRepository;
use App\Repository\SeasonRepository;
use App\Repository\TeamRepository;
use App\Repository\TeamStatRepository;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class SeasonController extends AbstractController
{
    #[Route('/season', name: 'app_seasons', methods: ['GET'])]
    public function getSeasons(Request $request, SeasonRepository $seasonRepository, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $id = $request->query->get('id');
        
        // Si un ID est fourni, retourner une seule saison
        if ($id) {
            $season = $seasonRepository->find($id);
            if (!$season) {
                return $this->json(['error' => 'Season not found'], 404);
            }

            // Calculer les statistiques pour cette saison
            $totalGames = 0;
            $finishedGames = 0;
            $finishedStatus = $gameStatusRepository->findOneBy(['name' => 'joué']);
            $divisions = $divisionRepository->findBy(['season' => $season]);
            foreach ($divisions as $division) {
                $games = $gameRepository->findBy(['division' => $division]);
                foreach ($games as $game) {
                    $totalGames++;
                    if ($game->getStatus() === $finishedStatus) {
                        $finishedGames++;
                    }
                }
            }
            $percentage = $totalGames > 0 ? ($finishedGames / $totalGames) * 100 : 0;
            $percentage = number_format($percentage, 2);

            return $this->json([
                'id' => $season->getId(),
                'name' => $season->getName(),
                'start_date' => $season->getStartDate()->format('d-m-Y'),
                'end_date' => $season->getEndDate()->format('d-m-Y'),
                'total_games' => $totalGames,
                'finished_games' => $finishedGames,
                'percentage' => $percentage
            ]);
        }

        // Sinon, retourner toutes les saisons avec leurs statistiques
        $seasons = $seasonRepository->findAll();
        $data = [];
        foreach ($seasons as $season) {
            $totalGames = 0;
            $finishedGames = 0;
            $finishedStatus = $gameStatusRepository->findOneBy(['name' => 'joué']);
            $divisions = $divisionRepository->findBy(['season' => $season]);
            foreach ($divisions as $division) {
                $games = $gameRepository->findBy(['division' => $division]);
                foreach ($games as $game) {
                    $totalGames++;
                    if ($game->getStatus() === $finishedStatus) {
                        $finishedGames++;
                    }
                }
            }
            $percentage = $totalGames > 0 ? ($finishedGames / $totalGames) * 100 : 0;
            $percentage = number_format($percentage, 2);
            $data[] = [
                'id' => $season->getId(),
                'name' => $season->getName(),
                'start_date' => $season->getStartDate()->format('d-m-Y'),
                'end_date' => $season->getEndDate()->format('d-m-Y'),
                'total_games' => $totalGames,
                'finished_games' => $finishedGames,
                'percentage' => $percentage
            ];
        }
        return $this->json($data);
    }


    #[Route('/season/games', name: 'app_season_games', methods: ['GET'])]
    public function getSeasonGames(Request $request, SeasonRepository $seasonRepository, DivisionRepository $divisionRepository, GameRepository $gameRepository): JsonResponse
    {
        $id = $request->query->get('id');
        if (!$id) {
            return $this->json(['error' => 'Season ID is required'], 400);
        }

        $season = $seasonRepository->find($id);
        if (!$season) {
            return $this->json(['error' => 'Season not found'], 404);
        }

        $rep = [];
        $divisions = $divisionRepository->findBy(['season' => $season]);
        foreach ($divisions as $division) {
            $games = $gameRepository->findBy(['division' => $division]);
            foreach ($games as $game) {
                $rep[] = [
                    'id' => $game->getId(),
                    'date' => $game->getDate()->format('d-m-Y'),
                    'week' => $game->getWeek(),
                    'team1' => $game->getTeam1()->getName(),
                    'team2' => $game->getTeam2()->getName(),
                    'score1' => $game->getScore1(),
                    'score2' => $game->getScore2(),
                    'winner' => $game->getWinner(),
                    'status' => $game->getStatus()->getName()
                ];
            }
        }
        return $this->json($rep);
    }

    // TODO: need to be test with fake data, maybe it's useless
    #[Route('/season/games/status', name: 'app_season_games_by_status', methods: ['GET'])]
    public function getSeasonGamesByStatus(Request $request, SeasonRepository $seasonRepository, DivisionRepository $divisionRepository, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $id = $request->query->get('id');
        $status = $request->query->get('status');
        
        if (!$id) {
            return $this->json(['error' => 'Season ID is required'], 400);
        }
        if (!$status) {
            return $this->json(['error' => 'Status is required'], 400);
        }

        $season = $seasonRepository->find($id);
        if (!$season) {
            return $this->json(['error' => 'Season not found'], 404);
        }

        $games = [];
        $divisions = $divisionRepository->findBy(['season' => $season]);
        foreach ($divisions as $division) {
            $teamsId = $teamStatRepository->findBy(['division' => $division]);
            foreach ($teamsId as $teamId) {
                if ($teamId->getGames() === $status) {
                    $games[] = $teamId->getGames();
                }
            }
        }
        return $this->json($games);
    }

    // TODO: need to be test with fake data
    #[Route('/season/teams', name: 'app_season_teams', methods: ['GET'])]
    public function getSeasonTeams(Request $request, SeasonRepository $seasonRepository, RegistrationRepository $registrationRepository): JsonResponse
    {
        $id = $request->query->get('id');
        if (!$id) {
            return $this->json(['error' => 'Season ID is required'], 400);
        }

        $season = $seasonRepository->find($id);
        if (!$season) {
            return $this->json(['error' => 'Season not found'], 404);
        }

        $teams = $registrationRepository->findBy(['season' => $season]);
        $teamsData = array_map(function ($team) {
            return [
                'id' => $team->getTeam()->getId(),
                'name' => $team->getTeam()->getName()
            ];
        }, $teams);
        $data = [
            'id' => $season->getId(),
            'name' => $season->getName(),
            'start_date' => $season->getStartDate()->format('d-m-Y'),
            'end_date' => $season->getEndDate()->format('d-m-Y'),
            'teams' => $teamsData
        ];

        return $this->json($data);
    }

    //TODO: need to be tested
    #[Route('/season/pourcent', name: 'app_season_pourcent', methods: ['GET'])]
    public function getFinishedMatchPourcent(Request $request, SeasonRepository $seasonRepository, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $id = $request->query->get('id');
        $decimal = $request->query->get('decimal', 2); // valeur par défaut : 2
        
        if (!$id) {
            return $this->json(['error' => 'Season ID is required'], 400);
        }

        $season = $seasonRepository->find($id);
        if (!$season) {
            return $this->json(['error' => 'Season not found'], 404);
        }

        $nbTotalGames = 0;
        $nbFinishedGames = 0;
        $divisions = $divisionRepository->findBy(['season' => $season]);
        foreach ($divisions as $division) {
            $games = $gameRepository->findBy(['division' => $division]);
            foreach ($games as $game) {
                $nbTotalGames++;
                if ($game->getStatus() === $gameStatusRepository->findOneBy(['name' => 'joué'])) {
                    $nbFinishedGames++;
                }
            }
        }
        $pourcent = $nbTotalGames > 0 ? ($nbFinishedGames / $nbTotalGames) * 100 : 0;
        $pourcent = number_format($pourcent, (int)$decimal);
        return $this->json([
            'total' => $nbTotalGames,
            'finished' => $nbFinishedGames,
            'pourcent' => $pourcent
        ]);
    }

    // #[Route('/season', name: 'app_season_create', methods: ['POST'])]
    // public function createSeason(Request $request, Season $season, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $season->setName($data['name']);
    //     $season->setStartDate(new \DateTime($data['start_date']));
    //     $season->setEndDate(new \DateTime($data['end_date']));
    //     $em->persist($season);
    //     $em->flush();
    //     return $this->json([
    //         'id' => $season->getId(),
    //         'name' => $season->getName(),
    //         'start_date' => $season->getStartDate()->format('d-m-Y'),
    //         'end_date' => $season->getEndDate()->format('d-m-Y')
    //     ]);
    // }

    // #[Route('/season/{id}', name: 'app_season_update', methods: ['PUT'])]
    // public function updateSeason(Request $request, Season $season, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $season->setName($data['name']);
    //     $season->setStartDate(new \DateTime($data['start_date']));
    //     $season->setEndDate(new \DateTime($data['end_date']));
    //     $em->flush();
    //     return $this->json([
    //         'id' => $season->getId(),
    //         'name' => $season->getName(),
    //         'start_date' => $season->getStartDate()->format('d-m-Y'),
    //         'end_date' => $season->getEndDate()->format('d-m-Y')
    //     ]);
    // }

    // #[Route('/season/{id}', name: 'app_season_patch', methods: ['PATCH'])]
    // public function patchSeason(Request $request, Season $season, EntityManager $em): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     if (isset($data['name'])) {
    //         $season->setName($data['name']);
    //     }
    //     if (isset($data['start_date'])) {
    //         $season->setStartDate(new \DateTime($data['start_date']));
    //     }
    //     if (isset($data['end_date'])) {
    //         $season->setEndDate(new \DateTime($data['end_date']));
    //     }
    //     $em->flush();
    //     return $this->json([
    //         'id' => $season->getId(),
    //         'name' => $season->getName(),
    //         'start_date' => $season->getStartDate()->format('d-m-Y'),
    //         'end_date' => $season->getEndDate()->format('d-m-Y')
    //     ]);
    // }

    // #[Route('/season/{id}', name: 'app_season_delete', methods: ['DELETE'])]
    // public function deleteSeason(Season $season, EntityManager $em): JsonResponse
    // {
    //     $em->remove($season);
    //     $em->flush();
    //     return new JsonResponse(['message' => 'Season deleted successfully'], 200);
    // }
}
