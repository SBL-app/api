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
    /**
     * Formate les données de base d'une inscription
     */
    private function formatRegistrationData(Registration $registration): array
    {
        return [
            'id' => $registration->getId(),
            'season' => $registration->getSeason()->getName(),
            'team' => $registration->getTeam()->getName()
        ];
    }

    #[Route('/registrations', name: 'app_registration', methods: ['GET'])]
    public function getRegistrations(Request $request, RegistrationRepository $registrationRepository): JsonResponse
    {
        $id = $request->query->get('id');
        $seasonId = $request->query->get('season_id');
        $teamId = $request->query->get('team_id');
        
        // Si un ID est fourni, retourner l'inscription spécifique
        if ($id) {
            $registration = $registrationRepository->find($id);
            if (!$registration) {
                return $this->json(['error' => 'Registration not found'], 404);
            }
            return $this->json($this->formatRegistrationData($registration));
        }
        
        // Si season_id ET team_id sont fournis, retourner l'inscription spécifique
        if ($seasonId && $teamId) {
            $registration = $registrationRepository->findOneBy(['season' => $seasonId, 'team' => $teamId]);
            if (!$registration) {
                return $this->json(['error' => 'Registration not found for this team and season'], 404);
            }
            return $this->json($this->formatRegistrationData($registration));
        }
        
        // Si seulement season_id est fourni, retourner les inscriptions de cette saison
        if ($seasonId) {
            $registrations = $registrationRepository->findBy(['season' => $seasonId]);
            if (empty($registrations)) {
                return $this->json(['error' => 'No registrations found for this season'], 404);
            }
            $data = array_map(function ($registration) {
                return $this->formatRegistrationData($registration);
            }, $registrations);
            return $this->json($data);
        }
        
        // Si seulement team_id est fourni, retourner les inscriptions de cette équipe
        if ($teamId) {
            $registrations = $registrationRepository->findBy(['team' => $teamId]);
            if (empty($registrations)) {
                return $this->json(['error' => 'No registrations found for this team'], 404);
            }
            $data = array_map(function ($registration) {
                return $this->formatRegistrationData($registration);
            }, $registrations);
            return $this->json($data);
        }
        
        // Sinon, retourner toutes les inscriptions
        $registrations = $registrationRepository->findAll();
        $data = array_map(function ($registration) {
            return $this->formatRegistrationData($registration);
        }, $registrations);
        return $this->json($data);
    }

    // #[Route('/registration', name: 'app_registration_create', methods: ['POST'])]
    // public function createRegistration(Request $request, EntityManager $entityManager): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $registration = new Registration();
    //     if (isset($data['season'])) {
    //         $season = $entityManager->getRepository(Season::class)->find($data['season']);
    //         if (!$season) {
    //             return $this->json(['error' => 'Season not found'], 400);
    //         }
    //         $registration->setSeason($season);
    //     }
    //     else {
    //         $registration->setSeason(null);
    //     }
    //     if (isset($data['team'])) {
    //         $team = $entityManager->getRepository(Team::class)->find($data['team']);
    //         if (!$team) {
    //             return $this->json(['error' => 'Team not found'], 400);
    //         }
    //         $registration->setTeam($team);
    //     }
    //     else {
    //         $registration->setTeam(null);
    //     }
    //     $entityManager->persist($registration);
    //     $entityManager->flush();
    //     return $this->json([
    //         'id' => $registration->getId(),
    //         'season' => $registration->getSeason()->getName(),
    //         'team' => $registration->getTeam()->getName()
    //     ]);
    // }

    // #[Route('/registration/{id}', name: 'app_registration_update', methods: ['PUT'])]
    // public function updateRegistration(Registration $registration, Request $request, EntityManager $entityManager): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     $registration->setSeason($data['season']);
    //     $registration->setTeam($data['team']);
    //     $entityManager->flush();
    //     return $this->json([
    //         'id' => $registration->getId(),
    //         'season' => $registration->getSeason()->getName(),
    //         'team' => $registration->getTeam()->getName()
    //     ]);
    // }

    // #[Route('/registration/{id}', name: 'app_registration_patch', methods: ['PATCH'])]
    // public function patchRegistration(Registration $registration, Request $request, EntityManager $entityManager): JsonResponse
    // {
    //     $data = json_decode($request->getContent(), true);
    //     if (isset($data['season'])) {
    //         $registration->setSeason($data['season']);
    //     }
    //     if (isset($data['team'])) {
    //         $registration->setTeam($data['team']);
    //     }
    //     $entityManager->flush();
    //     return $this->json([
    //         'id' => $registration->getId(),
    //         'season' => $registration->getSeason()->getName(),
    //         'team' => $registration->getTeam()->getName()
    //     ]);
    // }

    // #[Route('/registration/{id}', name: 'app_registration_delete', methods: ['DELETE'])]
    // public function deleteRegistration(Registration $registration, EntityManager $entityManager): JsonResponse
    // {
    //     $entityManager->remove($registration);
    //     $entityManager->flush();
    //     return $this->json(null, 204);
    // }
}
