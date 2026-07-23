<?php

namespace App\Controller;

use App\Exception\ApiProblemException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Registration;
use App\Entity\Season;
use App\Entity\Team;
use App\Repository\RegistrationRepository;
use App\Repository\PlayerRepository;
use App\Repository\TeamMemberRepository;
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
            'season' => $entity->getSeason() ? $entity->getSeason()->getName() : null,
            'season_id' => $entity->getSeason() ? $entity->getSeason()->getId() : null,
            'team' => $entity->getTeam() ? $entity->getTeam()->getName() : null,
            'team_id' => $entity->getTeam() ? $entity->getTeam()->getId() : null,
            'status' => $entity->getStatus(),
            'created_at' => $entity->getCreatedAt()->format('Y-m-d H:i:s'),
            'reviewed_at' => $entity->getReviewedAt()?->format('Y-m-d H:i:s'),
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
        $seasonId = $request->query->get('season_id');
        $teamId = $request->query->get('team_id');

        // Si season_id ET team_id sont fournis, retourner l'inscription spécifique
        if ($seasonId && $teamId) {
            $registration = $registrationRepository->findOneBy(['season' => $seasonId, 'team' => $teamId]);
            if (!$registration) {
                throw ApiProblemException::notFound('Registration not found for this team and season');
            }
            return $this->json($this->formatRegistrationData($registration));
        }

        // Si seulement season_id est fourni, retourner les inscriptions de cette saison
        if ($seasonId) {
            $registrations = $registrationRepository->findBy(['season' => $seasonId]);
            $data = array_map(fn($r) => $this->formatRegistrationData($r), $registrations);
            return $this->json($data);
        }

        // Si seulement team_id est fourni, retourner les inscriptions de cette équipe
        if ($teamId) {
            $registrations = $registrationRepository->findBy(['team' => $teamId]);
            $data = array_map(fn($r) => $this->formatRegistrationData($r), $registrations);
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

        return $this->securedCreateEntity($registration, $request);
    }

    #[Route('/registrations/{id}', name: 'app_registration_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function updateRegistration(int $id, Request $request): JsonResponse
    {
        $registration = $this->findEntityOrFail('App\Entity\Registration', $id, 'Registration');
        $data = $this->getRequestData($request);

        $season = $this->findEntityOrFail('App\Entity\Season', $data['season'], 'Season');
        $team = $this->findEntityOrFail('App\Entity\Team', $data['team'], 'Team');

        $registration->setSeason($season);
        $registration->setTeam($team);

        return $this->securedUpdateEntity($registration);
    }

    #[Route('/registrations/{id}', name: 'app_registration_patch', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function patchRegistration(int $id, Request $request): JsonResponse
    {
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
    }

    #[Route('/registrations/{id}', name: 'app_registration_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteRegistration(int $id): JsonResponse
    {
        $registration = $this->findEntityOrFail('App\Entity\Registration', $id, 'Registration');
        return $this->securedDeleteEntity($registration, 'Registration');
    }

    // ==========================================
    // Flux d'inscription à une saison (issue api#35)
    // ==========================================

    /**
     * POST /api/seasons/{seasonId}/register
     *
     * Le capitaine d'une équipe inscrit SON équipe à une saison ouverte.
     * Body : {"team_id": <id>}. L'inscription est créée au statut "pending"
     * et devra être validée par un admin.
     */
    #[Route('/seasons/{seasonId}/register', name: 'app_season_register_team', methods: ['POST'], requirements: ['seasonId' => '\d+'])]
    public function registerTeam(
        int $seasonId,
        Request $request,
        RegistrationRepository $registrationRepository,
        TeamMemberRepository $teamMemberRepository,
        PlayerRepository $playerRepository
    ): JsonResponse {
        try {
            $currentUser = $this->getAuthenticatedUser();
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            throw ApiProblemException::unauthorized($e->getMessage());
        }

        /** @var Season $season */
        $season = $this->findEntityOrFail(Season::class, $seasonId, 'Season');

        $data = $this->getRequestData($request);
        $teamId = $data['team_id'] ?? $data['team'] ?? null;
        if (!$teamId) {
            throw ApiProblemException::validationError('team_id is required', [['field' => 'team_id', 'message' => 'This value should not be blank.']]);
        }

        /** @var Team $team */
        $team = $this->findEntityOrFail(Team::class, $teamId, 'Team');

        // Seul le capitaine de l'équipe peut l'inscrire.
        $membership = $teamMemberRepository->findByTeamAndUser($team, $currentUser);
        if (!$membership || !$membership->isCaptain()) {
            throw ApiProblemException::forbidden('Only the team captain can register the team');
        }

        // Période d'inscription.
        if (!$season->isRegistrationOpen()) {
            throw ApiProblemException::badRequest('Registration is not open for this season');
        }

        // Pas de doublon.
        $existing = $registrationRepository->findOneBy(['season' => $season, 'team' => $team]);
        if ($existing) {
            throw ApiProblemException::conflict('This team is already registered for this season');
        }

        // Prérequis : nombre minimum de joueurs.
        $minPlayers = (int) $this->getParameter('app.registration.min_players');
        $playerCount = count($playerRepository->findBy(['team' => $team]));
        if ($playerCount < $minPlayers) {
            throw ApiProblemException::badRequest(sprintf('Team must have at least %d players to register (currently %d)', $minPlayers, $playerCount));
        }

        $registration = new Registration();
        $registration->setSeason($season);
        $registration->setTeam($team);
        $registration->setStatus(Registration::STATUS_PENDING);

        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $this->logger->info('Team registered to season', ['season' => $seasonId, 'team' => $team->getId(), 'registration' => $registration->getId()]);

        $response = $this->json($this->formatEntityData($registration), 201);
        $response->headers->set('Location', '/api/registrations/' . $registration->getId());
        return $response;
    }

    /**
     * PATCH /api/registrations/{id}/review
     *
     * Un admin valide ou refuse une inscription en attente.
     * Body : {"status": "approved"|"rejected"}.
     */
    #[Route('/registrations/{id}/review', name: 'app_registration_review', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    public function reviewRegistration(int $id, Request $request): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        /** @var Registration $registration */
        $registration = $this->findEntityOrFail(Registration::class, $id, 'Registration');
        $data = $this->getRequestData($request);

        $status = $data['status'] ?? null;
        if (!in_array($status, [Registration::STATUS_APPROVED, Registration::STATUS_REJECTED], true)) {
            throw ApiProblemException::badRequest('status must be "approved" or "rejected"');
        }

        $registration->setStatus($status);
        $registration->setReviewedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->logger->info('Registration reviewed', ['registration' => $id, 'status' => $status]);

        return $this->json($this->formatEntityData($registration));
    }
}
