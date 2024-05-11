<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class Game extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // create 3 games
        for ($i = 1; $i <= 3; $i++) {
            $game = new \App\Entity\Game();
            $game->setDate(new \DateTime('2021-01-0' . $i));
            $game->setWeek($i);
            $game->setTeam1($this->getReference('team_1'));
            $game->setTeam2($this->getReference('team_2'));
            $game->setScore1(0);
            $game->setScore2(0);
            $game->setWinner(null);
            $game->setStatus($this->getReference('game_status_1'));
            $game->setDivisionId($this->getReference('division_1'));
            $manager->persist($game);
        }

        $manager->flush();
    }
}
