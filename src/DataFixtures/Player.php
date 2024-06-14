<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Player extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // create 3 players
        for ($i = 1; $i <= 3; $i++) {
            $player = new \App\Entity\Player();
            $player->setName('Player ' . $i);
            $player->setDiscord('player' . $i);
            $player->setTeam($this->getReference('team_1'));
            $manager->persist($player);
        }

        $manager->flush();
    }
}
