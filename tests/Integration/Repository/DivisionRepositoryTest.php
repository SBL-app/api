<?php

namespace App\Tests\Integration\Repository;

use App\Entity\Division;
use App\Entity\Season;
use App\Repository\DivisionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d'intégration pour le DivisionRepository
 */
class DivisionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DivisionRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $this->repository = $this->entityManager->getRepository(Division::class);

        // Nettoyer la base de données
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    private function cleanDatabase(): void
    {
        // Supprimer toutes les divisions et saisons existantes
        $this->entityManager->createQuery('DELETE FROM App\Entity\Division')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Season')->execute();
    }

    public function testFindAll(): void
    {
        // Test avec base de données vide
        $divisions = $this->repository->findAll();
        $this->assertEmpty($divisions);

        // Ajouter des données
        $season = new Season();
        $season->setName('Test Season');
        $this->entityManager->persist($season);

        $division1 = new Division();
        $division1->setName('Division A');
        $division1->setSeason($season);
        $this->entityManager->persist($division1);

        $division2 = new Division();
        $division2->setName('Division B');
        $division2->setSeason($season);
        $this->entityManager->persist($division2);

        $this->entityManager->flush();

        // Tester avec des données
        $divisions = $this->repository->findAll();
        $this->assertCount(2, $divisions);
        $this->assertInstanceOf(Division::class, $divisions[0]);
        $this->assertInstanceOf(Division::class, $divisions[1]);
    }

    public function testFind(): void
    {
        $season = new Season();
        $season->setName('Test Season');
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Test Division');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $this->entityManager->flush();
        $divisionId = $division->getId();

        // Test find avec ID valide
        $foundDivision = $this->repository->find($divisionId);
        $this->assertNotNull($foundDivision);
        $this->assertEquals('Test Division', $foundDivision->getName());
        $this->assertEquals($season->getId(), $foundDivision->getSeason()->getId());

        // Test find avec ID invalide
        $notFoundDivision = $this->repository->find(999);
        $this->assertNull($notFoundDivision);
    }

    public function testFindBy(): void
    {
        $season1 = new Season();
        $season1->setName('Season 2023');
        $this->entityManager->persist($season1);

        $season2 = new Season();
        $season2->setName('Season 2024');
        $this->entityManager->persist($season2);

        $division1 = new Division();
        $division1->setName('Division A 2023');
        $division1->setSeason($season1);
        $this->entityManager->persist($division1);

        $division2 = new Division();
        $division2->setName('Division B 2023');
        $division2->setSeason($season1);
        $this->entityManager->persist($division2);

        $division3 = new Division();
        $division3->setName('Division A 2024');
        $division3->setSeason($season2);
        $this->entityManager->persist($division3);

        $this->entityManager->flush();

        // Test findBy season
        $divisionsForSeason1 = $this->repository->findBy(['season' => $season1]);
        $this->assertCount(2, $divisionsForSeason1);

        $divisionsForSeason2 = $this->repository->findBy(['season' => $season2]);
        $this->assertCount(1, $divisionsForSeason2);
        $this->assertEquals('Division A 2024', $divisionsForSeason2[0]->getName());

        // Test findBy name
        $divisionsByName = $this->repository->findBy(['name' => 'Division A 2023']);
        $this->assertCount(1, $divisionsByName);
        $this->assertEquals('Division A 2023', $divisionsByName[0]->getName());
    }

    public function testFindOneBy(): void
    {
        $season = new Season();
        $season->setName('Test Season');
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Unique Division');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $this->entityManager->flush();

        // Test findOneBy
        $foundDivision = $this->repository->findOneBy(['name' => 'Unique Division']);
        $this->assertNotNull($foundDivision);
        $this->assertEquals('Unique Division', $foundDivision->getName());

        // Test findOneBy avec critères non trouvés
        $notFoundDivision = $this->repository->findOneBy(['name' => 'Non-existent Division']);
        $this->assertNull($notFoundDivision);
    }

    public function testPersistAndRemove(): void
    {
        $season = new Season();
        $season->setName('Test Season');
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Test Division');
        $division->setSeason($season);

        // Test persistence
        $this->entityManager->persist($division);
        $this->entityManager->flush();

        $this->assertNotNull($division->getId());

        $divisionId = $division->getId();
        $foundDivision = $this->repository->find($divisionId);
        $this->assertNotNull($foundDivision);

        // Test suppression
        $this->entityManager->remove($division);
        $this->entityManager->flush();

        $deletedDivision = $this->repository->find($divisionId);
        $this->assertNull($deletedDivision);
    }

    public function testCountDivisions(): void
    {
        // Test count avec base vide
        $initialCount = $this->repository->count([]);
        $this->assertEquals(0, $initialCount);

        // Ajouter des divisions
        $season = new Season();
        $season->setName('Test Season');
        $this->entityManager->persist($season);

        for ($i = 1; $i <= 3; $i++) {
            $division = new Division();
            $division->setName("Division $i");
            $division->setSeason($season);
            $this->entityManager->persist($division);
        }

        $this->entityManager->flush();

        // Test count après ajout
        $finalCount = $this->repository->count([]);
        $this->assertEquals(3, $finalCount);

        // Test count avec critères
        $countBySeason = $this->repository->count(['season' => $season]);
        $this->assertEquals(3, $countBySeason);
    }
}
