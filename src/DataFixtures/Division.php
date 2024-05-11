<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Division extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // create 3 divisions
        for ($i = 1; $i <= 3; $i++) {
            $division = new \App\Entity\Division();
            $division->setName('Division ' . $i);
            $manager->persist($division);
        }

        $manager->flush();
    }
}
