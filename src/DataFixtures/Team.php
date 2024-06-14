<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Team extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 10; $i++) {
            $team = new \App\Entity\Team();
            $team->setName($faker->name);
            $team->setCapitain($this->getReference('player_' . $faker->numberBetween(0, 9)));
            $manager->persist($team);
            $this->addReference('team_' . $i, $team);
        }

        $manager->flush();
    }
}
