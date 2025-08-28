<?php

namespace App\Controller;

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

#[Route('/api')]
class RegistrationController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Registration) {
            throw new \InvalidArgumentException('Entity must be an instance of Registration');
        }

        return [
            'id' => $entity->getId(),
            'season' => $entity->getSeason()->getName(),
            'team' => $entity->getTeam()->getName()
        ];
    }

    /**
     * Formate les données de base d'une inscription
     */
    private function formatRegistrationData(Registration $registration): array
    {
        return $this->formatEntityData($registration);
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

    #[Route('/registrations', name: 'app_registration_create', methods: ['POST'])]
    public function createRegistration(Request $request): JsonResponse
    {
        try {
            $data = $this->getRequestData($request);
            $registration = new Registration();

            if (isset($data['season'])) {
                $season = $this->findEntityOrFail('App\Entity\Season', $data['season'], 'Season');
                $registration->setSeason($season);
            } else {
                $registration->setSeason(null);
            }

            if (isset($data['team'])) {
                $team = $this->findEntityOrFail('App\Entity\Team', $data['team'], 'Team');
                $registration->setTeam($team);
            } else {
                $registration->setTeam(null);
            }

            $this->saveEntity($registration);

            return $this->json($this->formatEntityData($registration));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/registrations', name: 'app_registration_update', methods: ['PUT'])]
    public function updateRegistration(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $registration = $this->findEntityOrFail('App\Entity\Registration', $id, 'Registration');
            $data = $this->getRequestData($request);

            $season = $this->findEntityOrFail('App\Entity\Season', $data['season'], 'Season');
            $team = $this->findEntityOrFail('App\Entity\Team', $data['team'], 'Team');

            $registration->setSeason($season);
            $registration->setTeam($team);

            $this->saveEntity($registration);

            return $this->json($this->formatEntityData($registration));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/registrations', name: 'app_registration_patch', methods: ['PATCH'])]
    public function patchRegistration(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $registration = $this->findEntityOrFail('App\Entity\Registration', $id, 'Registration');
            $data = $this->getRequestData($request);

            if (isset($data['season'])) {
                $season = $this->findEntityOrFail('App\Entity\Season', $data['season'], 'Season');
                $registration->setSeason($season);
            }
            if (isset($data['team'])) {
                $team = $this->findEntityOrFail('App\Entity\Team', $data['team'], 'Team');
                $registration->setTeam($team);
            }

            $this->saveEntity($registration);

            return $this->json($this->formatEntityData($registration));
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/registrations', name: 'app_registration_delete', methods: ['DELETE'])]
    public function deleteRegistration(Request $request): JsonResponse
    {
        try {
            $id = $request->query->get('id');
            if (!$id) {
                return $this->missingParameterError('id');
            }

            $registration = $this->findEntityOrFail('App\Entity\Registration', $id, 'Registration');
            $this->deleteEntity($registration);

            return $this->deleteSuccessResponse('Registration');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
