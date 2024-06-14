<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TeamStat extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 10; $i++) {
            $teamStat = new \App\Entity\TeamStat();
            $teamStat->setWin($faker->numberBetween(0, 10));
            $teamStat->setLoose($faker->numberBetween(0, 10));
            $teamStat->setTeam($this->getReference('team_' . $faker->numberBetween(0, 9)));
            $teamStat->setDivision($this->getReference('division_' . $faker->numberBetween(0, 9)));
            $manager->persist($teamStat);
        }

        $manager->flush();
    }
}
