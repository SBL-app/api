<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
use App\Service\AuthenticationService;

#[Route('/api')]
class SeasonController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Season) {
            throw new \InvalidArgumentException('Entity must be an instance of Season');
        }

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'start_date' => $entity->getStartDate()->format('d-m-Y'),
            'end_date' => $entity->getEndDate()->format('d-m-Y')
        ];
    }
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

        // Utiliser le formateur standard avec statistiques
        $seasonData = $this->formatSeasonData($season, $divisionRepository, $gameRepository, $gameStatusRepository);

        // Remplacer le formatage du pourcentage par celui demandé
        $seasonData['percentage'] = number_format($seasonData['percentage'], (int)$decimal);

        // Renommer les clés pour correspondre au format attendu de cette route
        $seasonData['total'] = $seasonData['total_games'];
        $seasonData['finished'] = $seasonData['finished_games'];
        $seasonData['pourcent'] = $seasonData['percentage'];

        // Supprimer les anciennes clés
        unset($seasonData['total_games'], $seasonData['finished_games'], $seasonData['percentage']);

        return $this->json($seasonData);
    }

    #[Route('/season', name: 'app_season_create', methods: ['POST'])]
    public function createSeason(Request $request): JsonResponse
    {
        try {
            $data = $this->getRequestData($request);
            $season = new Season();

            $season->setName($data['name']);
            $season->setStartDate(new \DateTime($data['start_date']));
            $season->setEndDate(new \DateTime($data['end_date']));

            return $this->securedCreateEntity($season);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/season', name: 'app_season_update', methods: ['PUT'])]
    public function updateSeason(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $season = $this->findEntityOrFail('App\Entity\Season', $id, 'Season');
            $data = $this->getRequestData($request);

            $season->setName($data['name']);
            $season->setStartDate(new \DateTime($data['start_date']));
            $season->setEndDate(new \DateTime($data['end_date']));

            return $this->securedUpdateEntity($season);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/season', name: 'app_season_patch', methods: ['PATCH'])]
    public function patchSeason(Request $request): JsonResponse
    {
        try {
            $this->checkModificationPermissions();

            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $season = $this->findEntityOrFail('App\Entity\Season', $id, 'Season');
            $data = $this->getRequestData($request);

            if (isset($data['name'])) {
                $season->setName($data['name']);
            }
            if (isset($data['start_date'])) {
                $season->setStartDate(new \DateTime($data['start_date']));
            }
            if (isset($data['end_date'])) {
                $season->setEndDate(new \DateTime($data['end_date']));
            }

            $this->saveEntity($season);

            return $this->json($this->formatEntityData($season));
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            return $this->permissionDeniedResponse($e->getMessage());
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/season', name: 'app_season_delete', methods: ['DELETE'])]
    public function deleteSeason(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $season = $this->findEntityOrFail('App\Entity\Season', $id, 'Season');

            return $this->securedDeleteEntity($season, 'Season');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
