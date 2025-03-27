<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Registration;
use App\Repository\RegistrationRepository;
use App\Entity\Season;
use App\Repository\SeasonRepository;
use App\Entity\Team;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface as EntityManager;
use Symfony\Component\HttpFoundation\Request;

class RegistrationController extends AbstractController
{
    #[Route('/registrations', name: 'app_registration', methods: ['GET'])]
    public function getRegistrations(RegistrationRepository $registrationRepository): JsonResponse
    {
        $registrations = $registrationRepository->findAll();
        $data = array_map(function ($registration) {
            return [
                'id' => $registration->getId(),
                'season' => $registration->getSeason()->getName(),
                'team' => $registration->getTeam()->getName()
            ];
        }, $registrations);
        return $this->json($data);
    }

    #[Route('/registration/season/{id}', name: 'app_registration_season', methods: ['GET'])]
    public function getRegistrationsBySeason(RegistrationRepository $registrationRepository, int $id): JsonResponse
    {
        $registrations = $registrationRepository->findBy(['season' => $id]);
        $data = array_map(function ($registration) {
            return [
                'id' => $registration->getId(),
                'season' => $registration->getSeason()->getName(),
                'team' => $registration->getTeam()->getName()
            ];
        }, $registrations);
        return $this->json($data);
    }

    #[Route('/registration/team/{id}', name: 'app_registration_team', methods: ['GET'])]
    public function getRegistrationsByTeam(RegistrationRepository $registrationRepository, int $id): JsonResponse
    {
        $registrations = $registrationRepository->findBy(['team' => $id]);
        $data = array_map(function ($registration) {
            return [
                'id' => $registration->getId(),
                'season' => $registration->getSeason()->getName(),
                'team' => $registration->getTeam()->getName()
            ];
        }, $registrations);
        return $this->json($data);
    }

    #[Route('/registration/{id}', name: 'app_registration_show', methods: ['GET'])]
    public function getRegistration(RegistrationRepository $registrationRepository, int $id): JsonResponse
    {
        $registration = $registrationRepository->find($id);
        if (!$registration) {
            return $this->json(['error' => 'Registration not found'], 404);
        }
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
        if (isset($data['season'])) {
            $season = $entityManager->getRepository(Season::class)->find($data['season']);
            if (!$season) {
                return $this->json(['error' => 'Season not found'], 400);
            }
            $registration->setSeason($season);
        }
        else {
            $registration->setSeason(null);
        }
        if (isset($data['team'])) {
            $team = $entityManager->getRepository(Team::class)->find($data['team']);
            if (!$team) {
                return $this->json(['error' => 'Team not found'], 400);
            }
            $registration->setTeam($team);
        }
        else {
            $registration->setTeam(null);
        }
        $entityManager->persist($registration);
        $entityManager->flush();
        return $this->json([
            'id' => $registration->getId(),
            'season' => $registration->getSeason()->getName(),
            'team' => $registration->getTeam()->getName()
        ]);
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
