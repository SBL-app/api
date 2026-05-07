<?php

namespace App\Controller;

use App\Repository\RegistrationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    private function formatRegistration($registration): array
    {
        return [
            'id' => $registration->getId(),
            'season' => $registration->getSeason()->getName(),
            'team' => $registration->getTeam()->getName(),
        ];
    }

    #[Route('/registrations/{id}', name: 'app_registration_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getRegistration(int $id, RegistrationRepository $registrationRepository): JsonResponse
    {
        $registration = $registrationRepository->find($id);
        if (!$registration) {
            return $this->json(['error' => 'Registration not found'], 404);
        }
        return $this->json($this->formatRegistration($registration));
    }

    /**
     * GET /registrations — liste avec filtres optionnels
     *
     * ?season_id=X             inscriptions d'une saison
     * ?team_id=X               inscriptions d'une équipe
     * ?season_id=X&team_id=X  inscription unique saison+équipe
     */
    #[Route('/registrations', name: 'app_registrations', methods: ['GET'])]
    public function getRegistrations(Request $request, RegistrationRepository $registrationRepository): JsonResponse
    {
        $seasonId = $request->query->get('season_id');
        $teamId = $request->query->get('team_id');

        if ($seasonId && $teamId) {
            $registration = $registrationRepository->findOneBy(['season' => $seasonId, 'team' => $teamId]);
            if (!$registration) {
                return $this->json(['error' => 'Registration not found'], 404);
            }
            return $this->json($this->formatRegistration($registration));
        }

        $criteria = [];
        if ($seasonId) {
            $criteria['season'] = $seasonId;
        }
        if ($teamId) {
            $criteria['team'] = $teamId;
        }

        $registrations = $registrationRepository->findBy($criteria);
        return $this->json(array_map(fn($r) => $this->formatRegistration($r), $registrations));
    }
}
