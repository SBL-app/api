<?php

namespace App\Tests\Controller;

use App\Controller\GameController;
use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\Player;
use App\Entity\Team;
use App\Repository\GameRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class GameControllerTest extends TestCase
{
    private function makeController(): GameController
    {
        $controller = new GameController();
        $controller->setContainer(new Container());

        return $controller;
    }

    private function decode(JsonResponse $response): mixed
    {
        return json_decode((string) $response->getContent(), true);
    }

    private function makeTeam(string $name, ?string $captainDiscord): Team
    {
        $team = (new Team())->setName($name);
        if ($captainDiscord !== null) {
            $team->setCapitain((new Player())->setName('Cap ' . $name)->setDiscord($captainDiscord));
        }

        return $team;
    }

    public function testReturnsUnscheduledGamesForWeekAndSeason(): void
    {
        $division = (new Division())->setName('D1');
        $status = (new GameStatus())->setName('à planifier');

        $game = (new Game())
            ->setWeek(3)
            ->setDivision($division)
            ->setStatus($status)
            ->setTeam1($this->makeTeam('Alpha', '111'))
            ->setTeam2($this->makeTeam('Beta', '222'));

        $gameRepository = $this->createMock(GameRepository::class);
        $gameRepository->expects(self::once())
            ->method('findUnscheduled')
            ->with(3, 2)
            ->willReturn([$game]);

        $request = new Request(['week' => '3', 'season_id' => '2']);
        $data = $this->decode($this->makeController()->getUnscheduledGames($request, $gameRepository));

        self::assertCount(1, $data);
        self::assertSame(3, $data[0]['week']);
        self::assertSame('D1', $data[0]['division']);
        self::assertSame('Alpha', $data[0]['team1']);
        self::assertSame('Beta', $data[0]['team2']);
        self::assertSame('111', $data[0]['team1_captain_discord']);
        self::assertSame('222', $data[0]['team2_captain_discord']);
        self::assertSame('à planifier', $data[0]['status']);
    }

    public function testCaptainDiscordIsNullWhenNoCaptain(): void
    {
        $game = (new Game())
            ->setWeek(1)
            ->setDivision((new Division())->setName('D2'))
            ->setStatus((new GameStatus())->setName('à planifier'))
            ->setTeam1($this->makeTeam('Gamma', null))
            ->setTeam2($this->makeTeam('Delta', null));

        $gameRepository = $this->createMock(GameRepository::class);
        $gameRepository->method('findUnscheduled')->willReturn([$game]);

        $request = new Request(['week' => '1', 'season_id' => '1']);
        $data = $this->decode($this->makeController()->getUnscheduledGames($request, $gameRepository));

        self::assertNull($data[0]['team1_captain_discord']);
        self::assertNull($data[0]['team2_captain_discord']);
    }

    public function testMissingWeekReturns400(): void
    {
        $gameRepository = $this->createMock(GameRepository::class);
        $gameRepository->expects(self::never())->method('findUnscheduled');

        $request = new Request(['season_id' => '2']);
        $response = $this->makeController()->getUnscheduledGames($request, $gameRepository);

        self::assertSame(400, $response->getStatusCode());
        self::assertArrayHasKey('error', $this->decode($response));
    }

    public function testNonNumericSeasonIdReturns400(): void
    {
        $gameRepository = $this->createMock(GameRepository::class);
        $gameRepository->expects(self::never())->method('findUnscheduled');

        $request = new Request(['week' => '3', 'season_id' => 'abc']);
        $response = $this->makeController()->getUnscheduledGames($request, $gameRepository);

        self::assertSame(400, $response->getStatusCode());
    }
}
