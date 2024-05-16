<?php

namespace App\Controller;

use App\Repository\DivisionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Division;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;

class DivisionController extends AbstractController
{
    #[Route('/division', name: 'app_division', methods: ['GET'])]
    public function getDivisions(DivisionRepository $divisionRepository): JsonResponse
    {
        $divisions = $divisionRepository->findAll();
        $data = array_map(function ($division) {
            return [
                'id' => $division->getId(),
                'name' => $division->getName(),
                'season' => $division->getSeasonId()
            ];
        }, $divisions);
        return $this->json($data);
    }

    #[Route('/division/{id}', name: 'app_division_show', methods: ['GET'])]
    public function getDivision(Division $division): JsonResponse
    {
        return $this->json([
            'id' => $division->getId(),
            'name' => $division->getName(),
            'season' => $division->getSeason()
        ]);
    }

    #[Route('/division', name: 'app_division_create', methods: ['POST'])]
    public function createDivision(Request $request, Division $division, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $division->setName($data['name']);
        $division->setSeason($data['season']);
        $em->persist($division);
        $em->flush();
        return $this->json([
            'id' => $division->getId(),
            'name' => $division->getName(),
            'season' => $division->getSeason()
        ]);
    }

    #[Route('/division/{id}', name: 'app_division_update', methods: ['PUT'])]
    public function updateDivision(Request $request, Division $division, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $division->setName($data['name']);
        $division->setSeason($data['season']);
        $em->persist($division);
        $em->flush();
        return $this->json([
            'id' => $division->getId(),
            'name' => $division->getName(),
            'season' => $division->getSeason()
        ]);
    }

    #[Route('/division/{id}', name: 'app_division_update', methods: ['PATCH'])]
    public function patchDivision(Request $request, Division $division, EntityManager $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) {
            $division->setName($data['name']);
        }
        if (isset($data['season'])) {
            $division->setSeason($data['season']);
        }
        $em->persist($division);
        $em->flush();
        return $this->json([
            'id' => $division->getId(),
            'name' => $division->getName(),
            'season' => $division->getSeason()
        ]);
    }

    #[Route('/division/{id}', name: 'app_division_delete', methods: ['DELETE'])]
    public function deleteDivision(Division $division, EntityManager $em): JsonResponse
    {
        $em->remove($division);
        $em->flush();
        return $this->json(null, 204);
    }
}
