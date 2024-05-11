<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TeamStat extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // create 3 team stats
        for ($i = 1; $i <= 3; $i++) {
            $teamStat = new \App\Entity\TeamStat();
            $teamStat->setWin($i);
            $teamStat->setLoose($i);
            $teamStat->setTeamId($this->getReference('team_1'));
            $teamStat->setDivisionId($this->getReference('division_1'));
            $manager->persist($teamStat);
        }

        $manager->flush();
    }
}
