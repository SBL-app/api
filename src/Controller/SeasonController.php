<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Season;
use App\Repository\DivisionRepository;
use App\Repository\SeasonRepository;
use App\Repository\TeamRepository;
use App\Repository\TeamStatRepository;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class SeasonController extends AbstractController
{
    #[Route('/seasons', name: 'app_season', methods: ['GET'])]
    public function getSeasons(SeasonRepository $seasonRepository): JsonResponse
    {
        $seasons= $seasonRepository->findAll();
        $data = array_map(function ($season) {
            return [
                'id' => $season->getId(),
                'name' => $season->getName(),
                'start_date' => $season->getStartDate()->format('d-m-Y'),
                'end_date' => $season->getEndDate()->format('d-m-Y')
            ];
        }, $seasons);
        return $this->json($data);
    }

    #[Route('/season/{id}', name: 'app_season_show',methods: ['GET'])]
    public function getSeason(Season $season): JsonResponse
    {
        return $this->json([
            'id' => $season->getId(),
            'name' => $season->getName(),
            'start_date' => $season->getStartDate()->format('d-m-Y'),
            'end_date' => $season->getEndDate()->format('d-m-Y')
        ]);
    }


    // TODO: need to be test with fake data
    #[Route('/season/{id}/games', name: 'app_season_games', methods: ['GET'])]
    public function getSeasonGames(Season $season, DivisionRepository $divisionRepository, TeamStatRepository $teamStatRepository): JsonResponse
    {
        $games = [];
        $divisions = $divisionRepository->findBy(['season' => $season]);
        foreach ($divisions as $division) {
            $teamsId = $teamStatRepository->findBy(['division' => $division]);
            foreach ($teamsId as $teamId) {
                $games[] = $teamId->getGames();
            }
        }    
        return $this->json($games); 
    }

    // TODO: need to be test with fake data, maybe it's useless
    #[Route('/season/{id}/games/{status}', name: 'app_season_games_by_status', methods: ['GET'])]
    public function getSeasonGamesByStatus(Season $season, DivisionRepository $divisionRepository, TeamStatRepository $teamStatRepository, string $status): JsonResponse
    {
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
    #[Route('/season/{id}/teams', name: 'app_season_teams', methods: ['GET'])]
    public function getSeasonTeams(Season $season, DivisionRepository $divisionRepository, TeamStatRepository $teamStatRepository, TeamRepository $teamRepository): JsonResponse
    {
        $teams = [];
        $divisions = $divisionRepository->findBy(['season' => $season]);
        foreach ($divisions as $division) {
            $teamsId = $teamStatRepository->findBy(['division' => $division]);
            foreach ($teamsId as $teamId) {
                $team = $teamRepository->find($teamId->getTeam());
                $teams[] = [
                    'id' => $team->getId(),
                    'name' => $team->getName()
                ];
            }
        }
        return $this->json($teams);
    }

    #[Route('/season', name: 'app_season_create', methods: ['POST'])]
    public function createSeason(Request $request, Season $season, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $season->setName($data['name']);
        $season->setStartDate(new \DateTime($data['start_date']));
        $season->setEndDate(new \DateTime($data['end_date']));
        $em->persist($season);
        $em->flush();
        return $this->json([
            'id' => $season->getId(),
            'name' => $season->getName(),
            'start_date' => $season->getStartDate()->format('d-m-Y'),
            'end_date' => $season->getEndDate()->format('d-m-Y')
        ]);
    }

    #[Route('/season/{id}', name: 'app_season_update', methods: ['PUT'])]
    public function updateSeason(Request $request, Season $season, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $season->setName($data['name']);
        $season->setStartDate(new \DateTime($data['start_date']));
        $season->setEndDate(new \DateTime($data['end_date']));
        $em->flush();
        return $this->json([
            'id' => $season->getId(),
            'name' => $season->getName(),
            'start_date' => $season->getStartDate()->format('d-m-Y'),
            'end_date' => $season->getEndDate()->format('d-m-Y')
        ]);
    }

    #[Route('/season/{id}', name: 'app_season_patch', methods: ['PATCH'])]
    public function patchSeason(Request $request, Season $season, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) {
            $season->setName($data['name']);
        }
        if (isset($data['start_date'])) {
            $season->setStartDate(new \DateTime($data['start_date']));
        }
        if (isset($data['end_date'])) {
            $season->setEndDate(new \DateTime($data['end_date']));
        }
        $em->flush();
        return $this->json([
            'id' => $season->getId(),
            'name' => $season->getName(),
            'start_date' => $season->getStartDate()->format('d-m-Y'),
            'end_date' => $season->getEndDate()->format('d-m-Y')
        ]);
    }

    #[Route('/season/{id}', name: 'app_season_delete', methods: ['DELETE'])]
    public function deleteSeason(Season $season, EntityManager $em): JsonResponse
    {
        $em->remove($season);
        $em->flush();
        return new JsonResponse(['message' => 'Season deleted successfully'], 200);
    }
}
