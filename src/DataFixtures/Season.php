<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Season extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 10; $i++) {
            $season = new \App\Entity\Season();
            $season->setName("season_$i");
            $season->setStartDate($faker->dateTimeThisYear);
            $season->setEndDate($faker->dateTimeThisYear);
            $manager->persist($season);
        }

        $manager->flush();
    }
}
