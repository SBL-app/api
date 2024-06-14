<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Player extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create();

        for ($i = 0; $i < 10; $i++) {
            $player = new \App\Entity\Player();
            $player->setName($faker->name);
            $player->setDiscord($faker->mail);
            $player->setTeam($this->getReference('team_' . $faker->numberBetween(0, 9)));
            $manager->persist($player);
        }

        $manager->flush();
    }
}
