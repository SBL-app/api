<?php

namespace App\Tests\Unit\Service;

use App\Entity\Bracket;
use App\Entity\BracketEntry;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\Team;
use App\Repository\GameStatusRepository;
use App\Service\BracketGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BracketGeneratorServiceTest extends TestCase
{
    public function testSeedOrderForEight(): void
    {
        $this->assertSame(
            [1, 8, 4, 5, 2, 7, 3, 6],
            BracketGeneratorService::seedOrder(8)
        );
    }

    public function testSeedOrderForFour(): void
    {
        $this->assertSame([1, 4, 2, 3], BracketGeneratorService::seedOrder(4));
    }

    private function makeService(array &$persisted): BracketGeneratorService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($e) use (&$persisted) {
            $persisted[] = $e;
        });

        $statusRepo = $this->createMock(GameStatusRepository::class);
        $statusRepo->method('findOneBy')->willReturnCallback(function ($criteria) {
            $s = new GameStatus();
            $s->setName($criteria['name']);

            return $s;
        });

        return new BracketGeneratorService($em, $statusRepo);
    }

    /**
     * @return array{0: Bracket, 1: BracketEntry[]}
     */
    private function makeBracket(int $n, bool $thirdPlace): array
    {
        $bracket = new Bracket();
        $bracket->setName('B')->setQualifiedCount($n)->setHasThirdPlaceMatch($thirdPlace);

        $entries = [];
        for ($i = 1; $i <= $n; $i++) {
            $team = new Team();
            $team->setName('T' . $i);
            $entry = new BracketEntry();
            $entry->setBracket($bracket)->setSeed($i)->setTeam($team);
            $entries[] = $entry;
        }

        return [$bracket, $entries];
    }

    public function testGenerateFourTeamsNoByes(): void
    {
        $persisted = [];
        $service = $this->makeService($persisted);
        [$bracket, $entries] = $this->makeBracket(4, false);

        $service->generateFromEntries($bracket, $entries);

        $games = array_values(array_filter($persisted, fn ($e) => $e instanceof Game));
        // 2 demi-finales (round 1) + 1 finale (round 2) = 3
        $this->assertCount(3, $games);

        $round1 = array_values(array_filter($games, fn (Game $g) => $g->getRound() === 1));
        $this->assertCount(2, $round1);
        // pas de byes -> aucune équipe nulle au round 1
        foreach ($round1 as $g) {
            $this->assertNotNull($g->getTeam1());
            $this->assertNotNull($g->getTeam2());
        }
        $this->assertSame(Bracket::STATUS_READY, $bracket->getStatus());
    }

    public function testGenerateFourTeamsWithThirdPlace(): void
    {
        $persisted = [];
        $service = $this->makeService($persisted);
        [$bracket, $entries] = $this->makeBracket(4, true);

        $service->generateFromEntries($bracket, $entries);

        $games = array_values(array_filter($persisted, fn ($e) => $e instanceof Game));
        // 2 demi + finale + petite finale = 4
        $this->assertCount(4, $games);

        $thirdPlace = array_values(array_filter($games, fn (Game $g) => $g->isThirdPlaceMatch()));
        $this->assertCount(1, $thirdPlace);

        // les deux demi-finales pointent leur perdant vers la petite finale
        $semis = array_values(array_filter($games, fn (Game $g) => $g->getRound() === 1));
        foreach ($semis as $g) {
            $this->assertSame($thirdPlace[0], $g->getLoserToGame());
            $this->assertContains($g->getLoserToSlot(), [1, 2]);
        }
    }

    public function testGenerateSixTeamsCreatesByes(): void
    {
        $persisted = [];
        $service = $this->makeService($persisted);
        [$bracket, $entries] = $this->makeBracket(6, false);

        $service->generateFromEntries($bracket, $entries);

        $games = array_values(array_filter($persisted, fn ($e) => $e instanceof Game));
        $round1 = array_values(array_filter($games, fn (Game $g) => $g->getRound() === 1));
        // size = 8 -> 4 matchs round 1, 2 byes (seeds 1 et 2)
        $this->assertCount(4, $round1);

        $byeResolved = array_values(array_filter(
            $round1,
            fn (Game $g) => $g->getTeam1() === null || $g->getTeam2() === null
        ));
        $this->assertCount(2, $byeResolved);
        // chaque bye est résolu (statut played + winner) et l'équipe propagée au round 2
        foreach ($byeResolved as $g) {
            $this->assertSame('played', $g->getStatus()->getName());
            $this->assertNotNull($g->getWinner());
        }
    }
}
