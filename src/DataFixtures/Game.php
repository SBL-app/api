<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Game extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 10; $i++) {
            $game = new \App\Entity\Game();
            $game->setDate($faker->dateTimeThisYear);
            $game->setWeek($faker->numberBetween(1, 52));
            $game->setTeam1($this->getReference('team_' . $faker->numberBetween(0, 9)));
            $game->setTeam2($this->getReference('team_' . $faker->numberBetween(0, 9)));
            $game->setScore1($faker->numberBetween(0, 4));
            $game->setScore2($faker->numberBetween(0, 4));
            $game->setWinner($faker->numberBetween(1, 2));
            $game->setStatus($this->getReference('game_status_' . $faker->numberBetween(0, 2)));
            $game->setDivision($this->getReference('division_' . $faker->numberBetween(0, 9)));
            $manager->persist($game);
        }

        $manager->flush();
    }
}
