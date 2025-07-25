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
    /**
     * Calcule les statistiques des matchs pour une saison donnée
     */
    private function calculateSeasonGameStats(Season $season, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): array
    {
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
        
        return [
            'total_games' => $totalGames,
            'finished_games' => $finishedGames,
            'percentage' => $percentage
        ];
    }

    /**
     * Formate les données d'une saison avec ou sans statistiques
     */
    private function formatSeasonData(Season $season, ?DivisionRepository $divisionRepository = null, ?GameRepository $gameRepository = null, ?GameStatusRepository $gameStatusRepository = null): array
    {
        $data = [
            'id' => $season->getId(),
            'name' => $season->getName(),
            'start_date' => $season->getStartDate()->format('d-m-Y'),
            'end_date' => $season->getEndDate()->format('d-m-Y')
        ];

        // Si les repositories sont fournis, ajouter les statistiques
        if ($divisionRepository && $gameRepository && $gameStatusRepository) {
            $stats = $this->calculateSeasonGameStats($season, $divisionRepository, $gameRepository, $gameStatusRepository);
            $data = array_merge($data, [
                'total_games' => $stats['total_games'],
                'finished_games' => $stats['finished_games'],
                'percentage' => number_format($stats['percentage'], 2)
            ]);
        }

        return $data;
    }

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

            return $this->json($this->formatSeasonData($season, $divisionRepository, $gameRepository, $gameStatusRepository));
        }

        // Sinon, retourner toutes les saisons avec leurs statistiques
        $seasons = $seasonRepository->findAll();
        $data = [];
        foreach ($seasons as $season) {
            $data[] = $this->formatSeasonData($season, $divisionRepository, $gameRepository, $gameStatusRepository);
        }
        return $this->json($data);
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
        
        $seasonData = $this->formatSeasonData($season);
        $data = array_merge($seasonData, [
            'teams' => $teamsData
        ]);

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

        $stats = $this->calculateSeasonGameStats($season, $divisionRepository, $gameRepository, $gameStatusRepository);
        
        return $this->json([
            'total' => $stats['total_games'],
            'finished' => $stats['finished_games'],
            'pourcent' => number_format($stats['percentage'], (int)$decimal)
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
