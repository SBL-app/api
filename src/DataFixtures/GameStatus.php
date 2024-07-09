<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class GameStatus extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 3; $i++) {
            $gameStatus = new \App\Entity\GameStatus();
            $gameStatus->setName($faker->name);
            $manager->persist($gameStatus);
        }

        $manager->flush();
    }
}
