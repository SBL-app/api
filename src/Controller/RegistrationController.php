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

    #[Route('/registrations/{id}', name: 'app_registration_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getRegistration(int $id): JsonResponse
    {
        return $this->getEntityById('App\Entity\Registration', $id, 'Registration');
    }

    #[Route('/registrations', name: 'app_registration', methods: ['GET'])]
    public function getRegistrations(Request $request, RegistrationRepository $registrationRepository): JsonResponse
    {
        $id = $request->query->get('id');
        $seasonId = $request->query->get('season_id');
        $teamId = $request->query->get('team_id');

        // Backward compatibility - deprecated
        if ($id) {
            $this->logger->warning('Deprecated: Using ?id parameter for registration. Use /registrations/{id} instead', ['id' => $id]);
            return $this->getEntityById('App\Entity\Registration', $id, 'Registration');
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

            return $this->securedCreateEntity($registration);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/registrations/{id}', name: 'app_registration_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateRegistration(int $id, Request $request): JsonResponse
    {
        try {
            $registration = $this->findEntityOrFail('App\Entity\Registration', $id, 'Registration');
            $data = $this->getRequestData($request);

            $season = $this->findEntityOrFail('App\Entity\Season', $data['season'], 'Season');
            $team = $this->findEntityOrFail('App\Entity\Team', $data['team'], 'Team');

            $registration->setSeason($season);
            $registration->setTeam($team);

            return $this->securedUpdateEntity($registration);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/registrations/{id}', name: 'app_registration_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patchRegistration(int $id, Request $request): JsonResponse
    {
        try {
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

            return $this->securedUpdateEntity($registration);
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }

    #[Route('/registrations/{id}', name: 'app_registration_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteRegistration(int $id): JsonResponse
    {
        try {
            $registration = $this->findEntityOrFail('App\Entity\Registration', $id, 'Registration');

            return $this->securedDeleteEntity($registration, 'Registration');
        } catch (\Exception $e) {
            $code = $e->getCode() === 404 ? 404 : 400;
            return $this->json(['error' => $e->getMessage()], $code);
        }
    }
}
