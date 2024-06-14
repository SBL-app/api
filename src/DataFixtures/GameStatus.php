<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class GameStatus extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 10; $i++) {
            $gameStatus = new \App\Entity\GameStatus();
            $gameStatus->setName($faker->word());

            $manager->persist($gameStatus);
        }

        $manager->flush();
    }
}
