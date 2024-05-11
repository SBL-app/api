<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class GameSatus extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // create 3 game statuses
        for ($i = 1; $i <= 3; $i++) {
            $gameStatus = new \App\Entity\GameStatus();
            $gameStatus->setName('Game Status ' . $i);
            $manager->persist($gameStatus);
        }

        $manager->flush();
    }
}
