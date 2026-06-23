<?php

namespace App\Tests\Unit\Service;

use App\Entity\Bracket;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\Team;
use App\Service\BracketProgressionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class BracketProgressionServiceTest extends TestCase
{
    private function em(): EntityManagerInterface
    {
        return $this->createStub(EntityManagerInterface::class);
    }

    private function logger(): LoggerInterface
    {
        return $this->createStub(LoggerInterface::class);
    }

    public function testWinnerAndLoserPropagated(): void
    {
        $bracket = new Bracket();
        $bracket->setName('B')->setQualifiedCount(4)->setStatus(Bracket::STATUS_READY);

        $teamA = (new Team())->setName('A');
        $teamB = (new Team())->setName('B');

        $final = new Game();
        $final->setBracket($bracket);
        $thirdPlace = new Game();
        $thirdPlace->setBracket($bracket)->setIsThirdPlaceMatch(true);

        $semi = new Game();
        $semi->setBracket($bracket)
            ->setTeam1($teamA)->setTeam2($teamB)
            ->setWinner(1)
            ->setWinnerToGame($final)->setWinnerToSlot(1)
            ->setLoserToGame($thirdPlace)->setLoserToSlot(2);

        $service = new BracketProgressionService($this->em(), $this->logger());
        $service->advance($semi);

        $this->assertSame($teamA, $final->getTeam1());
        $this->assertSame($teamB, $thirdPlace->getTeam2());
        $this->assertSame(Bracket::STATUS_IN_PROGRESS, $bracket->getStatus());
    }

    public function testFinalCompletesBracket(): void
    {
        $bracket = new Bracket();
        $bracket->setName('B')->setQualifiedCount(2)->setStatus(Bracket::STATUS_IN_PROGRESS);

        $teamA = (new Team())->setName('A');
        $teamB = (new Team())->setName('B');

        $final = new Game();
        $final->setBracket($bracket)
            ->setTeam1($teamA)->setTeam2($teamB)
            ->setWinner(2); // pas de winnerToGame, pas de petite finale

        $service = new BracketProgressionService($this->em(), $this->logger());
        $service->advance($final);

        $this->assertSame(Bracket::STATUS_COMPLETED, $bracket->getStatus());
    }

    public function testThirdPlaceMatchDoesNotCompleteBracket(): void
    {
        $bracket = new Bracket();
        $bracket->setName('B')->setQualifiedCount(4)->setStatus(Bracket::STATUS_IN_PROGRESS);

        $thirdPlace = new Game();
        $thirdPlace->setBracket($bracket)->setIsThirdPlaceMatch(true)
            ->setTeam1((new Team())->setName('A'))
            ->setTeam2((new Team())->setName('B'))
            ->setWinner(1);

        $service = new BracketProgressionService($this->em(), $this->logger());
        $service->advance($thirdPlace);

        $this->assertSame(Bracket::STATUS_IN_PROGRESS, $bracket->getStatus());
    }

    public function testSkipsPropagationWhenTargetAlreadyPlayed(): void
    {
        $bracket = new Bracket();
        $bracket->setName('B')->setQualifiedCount(4)->setStatus(Bracket::STATUS_IN_PROGRESS);

        $teamA = (new Team())->setName('A');
        $teamB = (new Team())->setName('B');

        $playedStatus = (new \App\Entity\GameStatus())->setName('played');

        $finalGame = new Game();
        $finalGame->setBracket($bracket);
        $finalGame->setStatus($playedStatus);

        $semi = new Game();
        $semi->setBracket($bracket)
            ->setTeam1($teamA)->setTeam2($teamB)
            ->setWinner(1)
            ->setWinnerToGame($finalGame)->setWinnerToSlot(1);

        $service = new BracketProgressionService($this->em(), $this->logger());
        $service->advance($semi);

        $this->assertNull($finalGame->getTeam1(), 'team1 of played final must not be overwritten');
        $this->assertNull($finalGame->getTeam2(), 'team2 of played final must not be overwritten');
    }
}
