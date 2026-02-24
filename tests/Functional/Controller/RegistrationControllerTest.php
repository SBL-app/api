<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\Registration;
use App\Entity\Team;
use App\Entity\Season;

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
        $response = $this->jsonRequest('GET', '/api/registrations?id=999');

        $this->assertResponseStatusCodeSame(404);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('error', $response);
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
}
