<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Season;
use App\Repository\SeasonRepository;
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

    #[Route('/season/{id}', name: 'app_season_update', methods: ['PATCH'])]
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
        return new JsonResponse(null, 204);
    }
}
