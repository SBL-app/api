<?php

namespace App\Controller;

use App\Exception\ApiProblemException;
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
            'end_date' => $entity->getEndDate()->format('d-m-Y'),
            'is_finalized' => $entity->isFinalized(),
        ];
    }
    /**
     * Calcule les statistiques des matchs pour une saison donnée
     */
    private function calculateSeasonGameStats(Season $season, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): array
    {
        $totalGames = 0;
        $finishedGames = 0;
        $finishedStatus = $gameStatusRepository->findOneBy(['name' => 'played']);
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
            'end_date' => $season->getEndDate()->format('d-m-Y'),
            'is_finalized' => $season->isFinalized(),
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

    #[Route('/seasons/current', name: 'app_season_current', methods: ['GET'])]
    public function getCurrentSeason(SeasonRepository $seasonRepository, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $now = new \DateTime();
        $seasons = $seasonRepository->findAll();

        $currentSeason = null;
        foreach ($seasons as $season) {
            if ($season->getStartDate() <= $now && $season->getEndDate() >= $now) {
                $currentSeason = $season;
                break;
            }
        }

        if (!$currentSeason) {
            // Si pas de saison en cours, prendre la prochaine saison (start_date > now)
            usort($seasons, fn($a, $b) => $a->getStartDate() <=> $b->getStartDate());
            foreach ($seasons as $season) {
                if ($season->getStartDate() > $now) {
                    $currentSeason = $season;
                    break;
                }
            }
        }

        if (!$currentSeason) {
            throw ApiProblemException::notFound('No current or upcoming season found');
        }

        return $this->json($this->formatSeasonData($currentSeason, $divisionRepository, $gameRepository, $gameStatusRepository));
    }

    #[Route('/seasons/current/week', name: 'app_season_current_week', methods: ['GET'])]
    public function getCurrentSeasonWeek(SeasonRepository $seasonRepository, DivisionRepository $divisionRepository, GameRepository $gameRepository): JsonResponse
    {
        $now = new \DateTime();
        $seasons = $seasonRepository->findAll();

        $currentSeason = null;
        foreach ($seasons as $season) {
            if ($season->getStartDate() <= $now && $season->getEndDate() >= $now) {
                $currentSeason = $season;
                break;
            }
        }

        if (!$currentSeason) {
            throw ApiProblemException::notFound('No current season found');
        }

        // Calculer le numéro de semaine depuis le début de la saison
        $startDate = $currentSeason->getStartDate();
        $interval = $startDate->diff($now);
        $weekNumber = (int) ceil(($interval->days + 1) / 7);

        // Trouver le numéro de semaine max dans les matchs
        $divisions = $divisionRepository->findBy(['season' => $currentSeason]);
        $maxWeek = 1;
        foreach ($divisions as $division) {
            $games = $gameRepository->findBy(['division' => $division]);
            foreach ($games as $game) {
                if ($game->getWeek() > $maxWeek) {
                    $maxWeek = $game->getWeek();
                }
            }
        }

        return $this->json([
            'season_id' => $currentSeason->getId(),
            'season_name' => $currentSeason->getName(),
            'current_week' => min($weekNumber, $maxWeek),
            'max_week' => $maxWeek,
            'start_date' => $currentSeason->getStartDate()->format('d-m-Y'),
            'end_date' => $currentSeason->getEndDate()->format('d-m-Y')
        ]);
    }

    #[Route('/seasons/{id}', name: 'app_season_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getSeason(int $id, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $season = $this->findEntityOrFail('App\Entity\Season', $id, 'Season');
        return $this->json($this->formatSeasonData($season, $divisionRepository, $gameRepository, $gameStatusRepository));
    }

    #[Route('/seasons', name: 'app_seasons', methods: ['GET'])]
    public function getSeasons(SeasonRepository $seasonRepository, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $seasons = $seasonRepository->findAll();
        $data = [];
        foreach ($seasons as $season) {
            $data[] = $this->formatSeasonData($season, $divisionRepository, $gameRepository, $gameStatusRepository);
        }
        return $this->json($data);
    }

    #[Route('/seasons/{seasonId}/teams', name: 'app_season_teams', methods: ['GET'], requirements: ['seasonId' => '\d+'])]
    public function getSeasonTeams(int $seasonId, SeasonRepository $seasonRepository, RegistrationRepository $registrationRepository): JsonResponse
    {
        $season = $seasonRepository->find($seasonId);
        if (!$season) {
            throw ApiProblemException::notFound('Season not found');
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

    #[Route('/seasons/{id}/completion', name: 'app_season_completion', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getSeasonCompletion(int $id, Request $request, DivisionRepository $divisionRepository, GameRepository $gameRepository, GameStatusRepository $gameStatusRepository): JsonResponse
    {
        $decimal = $request->query->get('decimal', 2);

        $season = $this->findEntityOrFail('App\Entity\Season', $id, 'Season');

        $stats = $this->calculateSeasonGameStats($season, $divisionRepository, $gameRepository, $gameStatusRepository);

        return $this->json([
            'id' => $season->getId(),
            'name' => $season->getName(),
            'start_date' => $season->getStartDate()->format('d-m-Y'),
            'end_date' => $season->getEndDate()->format('d-m-Y'),
            'total_games' => $stats['total_games'],
            'finished_games' => $stats['finished_games'],
            'percentage' => number_format($stats['percentage'], (int)$decimal)
        ]);
    }

    #[Route('/seasons', name: 'app_season_create', methods: ['POST'])]
    public function createSeason(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);
        $season = new Season();
        $season->setName($data['name']);
        $season->setStartDate(new \DateTime($data['start_date']));
        $season->setEndDate(new \DateTime($data['end_date']));

        return $this->securedCreateEntity($season, $request);
    }

    #[Route('/seasons/{id}', name: 'app_season_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateSeason(int $id, Request $request): JsonResponse
    {
        $season = $this->findEntityOrFail('App\Entity\Season', $id, 'Season');
        $data = $this->getRequestData($request);
        $season->setName($data['name']);
        $season->setStartDate(new \DateTime($data['start_date']));
        $season->setEndDate(new \DateTime($data['end_date']));

        return $this->securedUpdateEntity($season);
    }

    #[Route('/seasons/{id}', name: 'app_season_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patchSeason(int $id, Request $request): JsonResponse
    {
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

        return $this->securedUpdateEntity($season);
    }

    #[Route('/seasons/{id}', name: 'app_season_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteSeason(int $id): JsonResponse
    {
        $season = $this->findEntityOrFail('App\Entity\Season', $id, 'Season');

        return $this->securedDeleteEntity($season, 'Season');
    }
}
