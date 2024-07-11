<?php

namespace App\Controller;

use App\Repository\DivisionRepository;
use App\Repository\TeamStatRepository;
use App\Repository\TeamRepository;
use App\Repository\SeasonRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Division;
use App\Entity\Season;
use App\Entity\Team;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class DivisionController extends AbstractController
{
    #[Route('/divisions', name: 'app_division', methods: ['GET'])]
    public function getDivisions(DivisionRepository $divisionRepository): JsonResponse
    {
        $divisions = $divisionRepository->findAll();
        $data = array_map(function ($division) {
            return [
                'id' => $division->getId(),
                'name' => $division->getName(),
                'season' => $division->getSeason() ? $division->getSeason()->getId() : null
            ];
        }, $divisions);
        return $this->json($data);
    }

    #[Route('/division/{id}', name: 'app_division_show', methods: ['GET'])]
    public function getDivision(Division $division): JsonResponse
    {
        $seasonId = $division->getSeason() ? $division->getSeason()->getId() : null;
        return $this->json([
            'id' => $division->getId(),
            'name' => $division->getName(),
            'season' => $seasonId
        ]);
    }

    #[Route('/division/season/{id}', name: 'app_division_season', methods: ['GET'])]
    public function getDivisionBySeason(DivisionRepository $divisionRepository, int $id): JsonResponse
    {
        $divisions = $divisionRepository->findBy(['season' => $id]);
        $data = array_map(function ($division) {
            $seasonId = $division->getSeason() ? $division->getSeason()->getId() : null;
            return [
                'id' => $division->getId(),
                'name' => $division->getName(),
                'season' => $seasonId
            ];
        }, $divisions);
        return $this->json($data);
    }

    #[Route('/division/{id}/teams', name: 'app_division_teams', methods: ['GET'])]
    public function getTeamsByDivision(Division $division, TeamStatRepository $teamStatRepository, TeamRepository $teamRepository): JsonResponse
    {
        $teamStats = $teamStatRepository->findBy(['division' => $division]);
        usort($teamStats, function ($a, $b) {
            return $b->getPoints() - $a->getPoints();
        });
        $data = array_map(function ($teamStat) use ($teamRepository) {
            $team = $teamStat->getTeam();
            $teamEntity = $teamRepository->find($team->getId());
            return [
                'id' => $team->getId(),
                'name' => $teamEntity->getName(),
                'points' => $teamStat->getPoints()
            ];
        }, $teamStats);
        return $this->json($data);
    }

    #[Route('/division', name: 'app_division_create', methods: ['POST'])]
    public function createDivision(Request $request, Division $division, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $division = new Division(); // Supposons que vous créez une nouvelle instance de Division
        $division->setName($data['name']);

        // Vérifier si le champ season est renseigné
        if (isset($data['season'])) {
            // Récupérer l'instance de Season à partir de son ID
            $season = $em->getRepository(Season::class)->find($data['season']);
            if (!$season) {
                // Gérer le cas où la saison n'est pas trouvée
                return $this->json(['error' => 'Season not found'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $division->setSeason($season);
        } else {
            $division->setSeason(null);
        }

        $em->persist($division);
        $em->flush();

        return $this->json([
            'id' => $division->getId(),
            'name' => $division->getName(),
            'season' => $division->getSeason() ? $division->getSeason()->getId() : null
        ]);
    }

    #[Route('/division/{id}', name: 'app_division_update', methods: ['PUT'])]
    public function updateDivision(Request $request, Division $division, EntityManager $em, SeasonRepository $seasonRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $division->setName($data['name']);
        
        if (isset($data['season'])) {
            $season = $seasonRepository->find($data['season']);
            if (!$season) {
                return $this->json(['error' => 'Season not found'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $division->setSeason($season);
        }
        
        $em->persist($division);
        $em->flush();
        
        return $this->json([
            'id' => $division->getId(),
            'name' => $division->getName(),
            'season' => $division->getSeason() ? $division->getSeason()->getId() : null
        ]);
    }

    #[Route('/division/{id}', name: 'app_division_patch', methods: ['PATCH'])]
    public function patchDivision(Request $request, Division $division, EntityManager $em, SeasonRepository $seasonRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) {
            $division->setName($data['name']);
        }
        if (isset($data['season'])) {
            $season = $seasonRepository->find($data['season']);
            if (!$season) {
                return $this->json(['error' => 'Season not found'], JsonResponse::HTTP_BAD_REQUEST);
            }
            $division->setSeason($season);
        }
        $em->persist($division);
        $em->flush();
        return $this->json([
            'id' => $division->getId(),
            'name' => $division->getName(),
            'season' => $division->getSeason() ? $division->getSeason()->getId() : null
        ]);
    }

    #[Route('/division/{id}', name: 'app_division_delete', methods: ['DELETE'])]
    public function deleteDivision(Division $division, EntityManager $em): JsonResponse
    {
        $em->remove($division);
        $em->flush();
        return $this->json([
            'message' => 'Division deleted successfully'
        ]);
    }
}
