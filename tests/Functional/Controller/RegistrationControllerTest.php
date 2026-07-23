<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\Registration;
use App\Entity\Team;
use App\Entity\Season;
use App\Entity\Player;
use App\Entity\TeamMember;
use App\Entity\User;

class RegistrationControllerTest extends ApiTestCase
{
    public function testGetRegistrationsEmpty(): void
    {
        $response = $this->jsonRequest('GET', '/api/registrations');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testGetRegistrationsWithData(): void
    {
        // Créer les entités nécessaires
        $season = new Season();
        $season->setName('Season 2024');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $team = new Team();
        $team->setName('Team Test');

        $registration = new Registration();
        $registration->setSeason($season);
        $registration->setTeam($team);

        $this->entityManager->persist($season);
        $this->entityManager->persist($team);
        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/registrations');

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $registrationData = $response[0];
        $this->assertArrayHasKey('id', $registrationData);
        $this->assertArrayHasKey('season', $registrationData);
        $this->assertArrayHasKey('team', $registrationData);
    }

    public function testGetRegistrationById(): void
    {
        // Créer les entités nécessaires
        $season = new Season();
        $season->setName('Season Test');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $team = new Team();
        $team->setName('Team Test');

        $registration = new Registration();
        $registration->setSeason($season);
        $registration->setTeam($team);

        $this->entityManager->persist($season);
        $this->entityManager->persist($team);
        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/registrations/' . $registration->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);

        $this->assertArrayHasKey('id', $response);
        $this->assertArrayHasKey('season', $response);
        $this->assertArrayHasKey('team', $response);
        $this->assertEquals($registration->getId(), $response['id']);
    }

    public function testGetRegistrationByIdNotFound(): void
    {
        $response = $this->jsonRequest('GET', '/api/registrations/999');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('detail', $response);
    }

    public function testGetRegistrationsByTeam(): void
    {
        // Créer les entités nécessaires
        $season = new Season();
        $season->setName('Season Filter');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $team = new Team();
        $team->setName('Team Filter');

        $registration1 = new Registration();
        $registration1->setSeason($season);
        $registration1->setTeam($team);

        $registration2 = new Registration();
        $registration2->setSeason($season);
        $registration2->setTeam($team);

        $this->entityManager->persist($season);
        $this->entityManager->persist($team);
        $this->entityManager->persist($registration1);
        $this->entityManager->persist($registration2);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/registrations?team_id=' . $team->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(2, $response);

        foreach ($response as $registrationData) {
            $this->assertEquals($team->getName(), $registrationData['team']);
        }
    }

    public function testGetRegistrationsBySeason(): void
    {
        // Créer les entités nécessaires
        $season = new Season();
        $season->setName('Season Special');
        $season->setStartDate(new \DateTime('2024-01-01'));
        $season->setEndDate(new \DateTime('2024-12-31'));

        $team = new Team();
        $team->setName('Team Season');

        $registration = new Registration();
        $registration->setSeason($season);
        $registration->setTeam($team);

        $this->entityManager->persist($season);
        $this->entityManager->persist($team);
        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/registrations?season_id=' . $season->getId());

        $this->assertResponseIsSuccessful();
        $this->assertIsArray($response);
        $this->assertCount(1, $response);

        $registrationData = $response[0];
        $this->assertEquals($season->getName(), $registrationData['season']);
    }

    // ==========================================
    // Flux d'inscription à une saison (issue api#35)
    // ==========================================

    private function makeOpenSeason(): Season
    {
        $season = new Season();
        $season->setName('Saison Ouverte');
        $season->setRegistrationOpenDate(new \DateTime('-1 week'));
        $season->setRegistrationCloseDate(new \DateTime('+1 week'));
        $this->entityManager->persist($season);
        return $season;
    }

    /**
     * Crée une équipe avec `playerCount` joueurs et un membre authentifié
     * ayant le rôle donné. Retourne l'équipe.
     */
    private function makeTeamWithCaptain(string $role, int $playerCount): Team
    {
        $user = new User();
        $user->setUsername('cap_' . uniqid());
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        $user->setDiscordId('discord_' . uniqid());
        $this->entityManager->persist($user);

        $team = new Team();
        $team->setName('Équipe Inscription');
        $this->entityManager->persist($team);

        $member = new TeamMember();
        $member->setUser($user);
        $member->setRole($role);
        $team->addMember($member);
        $this->entityManager->persist($member);

        for ($i = 0; $i < $playerCount; $i++) {
            $player = new Player();
            $player->setName('Joueur ' . $i);
            $player->setTeam($team);
            $this->entityManager->persist($player);
        }

        $this->entityManager->flush();
        $this->client->loginUser($user, 'api');

        return $team;
    }

    private function authenticateAsAdmin(): void
    {
        $admin = new User();
        $admin->setUsername('admin_' . uniqid());
        $admin->setPassword('hashed');
        $admin->setRoles(['ROLE_USER', 'ROLE_API', 'ROLE_ADMIN']);
        $admin->setIsActive(true);
        $this->entityManager->persist($admin);
        $this->entityManager->flush();
        $this->client->loginUser($admin, 'api');
    }

    public function testCaptainCanRegisterTeam(): void
    {
        $season = $this->makeOpenSeason();
        $team = $this->makeTeamWithCaptain(TeamMember::ROLE_CAPTAIN, 3);
        $this->entityManager->flush();

        $response = $this->jsonRequest('POST', '/api/seasons/' . $season->getId() . '/register', [
            'team_id' => $team->getId(),
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertEquals('pending', $response['status']);
        $this->assertEquals($team->getId(), $response['team_id']);
        $this->assertEquals($season->getId(), $response['season_id']);
    }

    public function testRegisterFailsWithoutEnoughPlayers(): void
    {
        $season = $this->makeOpenSeason();
        $team = $this->makeTeamWithCaptain(TeamMember::ROLE_CAPTAIN, 1);
        $this->entityManager->flush();

        $this->jsonRequest('POST', '/api/seasons/' . $season->getId() . '/register', [
            'team_id' => $team->getId(),
        ]);

        $this->assertResponseStatusCode(400);
    }

    public function testNonCaptainCannotRegister(): void
    {
        $season = $this->makeOpenSeason();
        $team = $this->makeTeamWithCaptain(TeamMember::ROLE_MEMBER, 3);
        $this->entityManager->flush();

        $this->jsonRequest('POST', '/api/seasons/' . $season->getId() . '/register', [
            'team_id' => $team->getId(),
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testRegisterFailsWhenWindowClosed(): void
    {
        $season = new Season();
        $season->setName('Saison Fermée');
        $season->setRegistrationOpenDate(new \DateTime('-3 weeks'));
        $season->setRegistrationCloseDate(new \DateTime('-1 week'));
        $this->entityManager->persist($season);

        $team = $this->makeTeamWithCaptain(TeamMember::ROLE_CAPTAIN, 3);
        $this->entityManager->flush();

        $this->jsonRequest('POST', '/api/seasons/' . $season->getId() . '/register', [
            'team_id' => $team->getId(),
        ]);

        $this->assertResponseStatusCode(400);
    }

    public function testDuplicateRegistrationRejected(): void
    {
        $season = $this->makeOpenSeason();
        $team = $this->makeTeamWithCaptain(TeamMember::ROLE_CAPTAIN, 3);
        $this->entityManager->flush();

        $uri = '/api/seasons/' . $season->getId() . '/register';
        $this->jsonRequest('POST', $uri, ['team_id' => $team->getId()]);
        $this->assertResponseStatusCode(201);

        $this->jsonRequest('POST', $uri, ['team_id' => $team->getId()]);
        $this->assertResponseStatusCode(409);
    }

    public function testAdminCanApproveRegistration(): void
    {
        $season = $this->makeOpenSeason();
        $team = new Team();
        $team->setName('Équipe À Valider');
        $this->entityManager->persist($team);

        $registration = new Registration();
        $registration->setSeason($season);
        $registration->setTeam($team);
        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $this->authenticateAsAdmin();

        $response = $this->jsonRequest('PATCH', '/api/registrations/' . $registration->getId() . '/review', [
            'status' => 'approved',
        ]);

        $this->assertResponseStatusCode(200);
        $this->assertEquals('approved', $response['status']);
        $this->assertNotNull($response['reviewed_at']);
    }

    public function testReviewRejectsInvalidStatus(): void
    {
        $season = $this->makeOpenSeason();
        $team = new Team();
        $team->setName('Équipe X');
        $this->entityManager->persist($team);

        $registration = new Registration();
        $registration->setSeason($season);
        $registration->setTeam($team);
        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        $this->authenticateAsAdmin();

        $this->jsonRequest('PATCH', '/api/registrations/' . $registration->getId() . '/review', [
            'status' => 'maybe',
        ]);

        $this->assertResponseStatusCode(400);
    }
}
