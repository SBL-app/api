<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Team extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // create 3 teams
        for ($i = 1; $i <= 3; $i++) {
            $team = new \App\Entity\Team();
            $team->setName('Team ' . $i);
            $team->setCapitainId($this->getReference('player_1'));
            $manager->persist($team);
        }

        $manager->flush();
    }
}
