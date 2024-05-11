<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Season extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // create 3 seasons
        for ($i = 1; $i <= 3; $i++) {
            $season = new \App\Entity\Season();
            $season->setName('Season ' . $i);
            $season->setStartDate(new \DateTime('2021-0' . $i . '-01'));
            $season->setEndDate(new \DateTime('2021-0' . $i . '-31'));
            $manager->persist($season);
        }

        $manager->flush();
    }
}
