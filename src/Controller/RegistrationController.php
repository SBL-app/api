<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Registration;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class RegistrationController extends AbstractController
{
    #[Route('/registrations', name: 'app_registration', methods: ['GET'])]
    public function getRegistrations(RegistrationRepository $registrationRepository): JsonResponse
    {
        $registrations = $registrationRepository->findAll();
        foreach ($registrations as $registration) {
            $data[] = [
                'id' => $registration->getId(),
                'season' => $registration->getSeason()->getName(),
                'team' => $registration->getTeam()->getName()
            ];
        }
        return $this->json($data);
    }

    #[Route('/registration/season/{id}', name: 'app_registration_season', methods: ['GET'])]
    public function getRegistrationsBySeason(RegistrationRepository $registrationRepository, int $id): JsonResponse
    {
        $registrations = $registrationRepository->findBy(['season' => $id]);
        foreach ($registrations as $registration) {
            $data[] = [
                'id' => $registration->getId(),
                'season' => $registration->getSeason()->getName(),
                'team' => $registration->getTeam()->getName()
            ];
        }
        return $this->json($data);
    }

    #[Route('/registration/team/{id}', name: 'app_registration_team', methods: ['GET'])]
    public function getRegistrationsByTeam(RegistrationRepository $registrationRepository, int $id): JsonResponse
    {
        $registrations = $registrationRepository->findBy(['team' => $id]);
        foreach ($registrations as $registration) {
            $data[] = [
                'id' => $registration->getId(),
                'season' => $registration->getSeason()->getName(),
                'team' => $registration->getTeam()->getName()
            ];
        }
        return $this->json($data);
    }

    #[Route('/registration/{id}', name: 'app_registration_show', methods: ['GET'])]
    public function getRegistration(Registration $registration): JsonResponse
    {
        return $this->json([
            'id' => $registration->getId(),
            'season' => $registration->getSeason()->getName(),
            'team' => $registration->getTeam()->getName()
        ]);
    }

    #[Route('/registration', name: 'app_registration_create', methods: ['POST'])]
    public function createRegistration(Request $request, EntityManager $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $registration = new Registration();
        $registration->setSeason($data['season']);
        $registration->setTeam($data['team']);
        $entityManager->persist($registration);
        $entityManager->flush();
        return $this->json(['id' => $registration->getId()]);
    }

    #[Route('/registration/{id}', name: 'app_registration_update', methods: ['PUT'])]
    public function updateRegistration(Registration $registration, Request $request, EntityManager $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $registration->setSeason($data['season']);
        $registration->setTeam($data['team']);
        $entityManager->flush();
        return $this->json([
            'id' => $registration->getId(),
            'season' => $registration->getSeason()->getName(),
            'team' => $registration->getTeam()->getName()
        ]);
    }

    #[Route('/registration/{id}', name: 'app_registration_patch', methods: ['PATCH'])]
    public function patchRegistration(Registration $registration, Request $request, EntityManager $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (isset($data['season'])) {
            $registration->setSeason($data['season']);
        }
        if (isset($data['team'])) {
            $registration->setTeam($data['team']);
        }
        $entityManager->flush();
        return $this->json([
            'id' => $registration->getId(),
            'season' => $registration->getSeason()->getName(),
            'team' => $registration->getTeam()->getName()
        ]);
    }

    #[Route('/registration/{id}', name: 'app_registration_delete', methods: ['DELETE'])]
    public function deleteRegistration(Registration $registration, EntityManager $entityManager): JsonResponse
    {
        $entityManager->remove($registration);
        $entityManager->flush();
        return $this->json(null, 204);
    }
}
