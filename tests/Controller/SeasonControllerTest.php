<?php

namespace App\Tests\Controller;

use App\Controller\SeasonController;
use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\Registration;
use App\Entity\Season;
use App\Entity\Team;
use App\Repository\DivisionRepository;
use App\Repository\GameRepository;
use App\Repository\GameStatusRepository;
use App\Repository\RegistrationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\JsonResponse;

final class SeasonControllerTest extends TestCase
{
    private function makeController(): SeasonController
    {
        $controller = new SeasonController();
        // Conteneur sans service "serializer" : AbstractController::json()
        // retombe alors sur une JsonResponse simple, suffisante pour les tests.
        $controller->setContainer(new Container());

        return $controller;
    }

    private function makeSeason(): Season
    {
        return (new Season())
            ->setName('Saison I')
            ->setStartDate(new \DateTimeImmutable('2025-03-01'))
            ->setEndDate(new \DateTimeImmutable('2025-05-01'));
    }

    private function decode(JsonResponse $response): mixed
    {
        return json_decode((string) $response->getContent(), true);
    }

    public function testGetSeasonTeamsReturnsSeasonWithRegisteredTeams(): void
    {
        $season = $this->makeSeason();

        $reg1 = (new Registration())->setSeason($season)->setTeam((new Team())->setName('Les Baguettes'));
        $reg2 = (new Registration())->setSeason($season)->setTeam((new Team())->setName('Les Croissants'));

        $registrationRepository = $this->createMock(RegistrationRepository::class);
        $registrationRepository->expects(self::once())
            ->method('findBy')
            ->with(['season' => $season])
            ->willReturn([$reg1, $reg2]);

        $response = $this->makeController()->getSeasonTeams($season, $registrationRepository);
        $data = $this->decode($response);

        self::assertSame('Saison I', $data['name']);
        self::assertSame('01-03-2025', $data['start_date']);
        self::assertSame('01-05-2025', $data['end_date']);
        self::assertCount(2, $data['teams']);
        self::assertSame(['Les Baguettes', 'Les Croissants'], array_column($data['teams'], 'name'));
    }

    public function testGetSeasonTeamsWithNoRegistrations(): void
    {
        $season = $this->makeSeason();

        $registrationRepository = $this->createMock(RegistrationRepository::class);
        $registrationRepository->method('findBy')->willReturn([]);

        $data = $this->decode($this->makeController()->getSeasonTeams($season, $registrationRepository));

        self::assertSame([], $data['teams']);
    }

    public function testGetFinishedMatchPourcentComputesPercentage(): void
    {
        $season = $this->makeSeason();
        $division = (new Division())->setName('D1');
        $played = (new GameStatus())->setName('joué');
        $toPlay = (new GameStatus())->setName('à jouer');

        $game1 = (new Game())->setStatus($played);
        $game2 = (new Game())->setStatus($played);
        $game3 = (new Game())->setStatus($toPlay);
        $game4 = (new Game())->setStatus($toPlay);

        $divisionRepository = $this->createMock(DivisionRepository::class);
        $divisionRepository->method('findBy')->with(['season' => $season])->willReturn([$division]);

        $gameRepository = $this->createMock(GameRepository::class);
        $gameRepository->method('findBy')->with(['division' => $division])->willReturn([$game1, $game2, $game3, $game4]);

        $gameStatusRepository = $this->createMock(GameStatusRepository::class);
        $gameStatusRepository->method('findOneBy')->with(['name' => 'joué'])->willReturn($played);

        $response = $this->makeController()->getFinishedMatchPourcent(
            $season,
            $divisionRepository,
            $gameRepository,
            $gameStatusRepository,
            2
        );
        $data = $this->decode($response);

        self::assertSame(4, $data['total']);
        self::assertSame(2, $data['finished']);
        self::assertSame('50.00', $data['pourcent']);
    }

    public function testGetFinishedMatchPourcentWithNoGamesIsZero(): void
    {
        $season = $this->makeSeason();

        $divisionRepository = $this->createMock(DivisionRepository::class);
        $divisionRepository->method('findBy')->willReturn([]);

        $gameRepository = $this->createMock(GameRepository::class);
        $gameStatusRepository = $this->createMock(GameStatusRepository::class);

        $data = $this->decode($this->makeController()->getFinishedMatchPourcent(
            $season,
            $divisionRepository,
            $gameRepository,
            $gameStatusRepository,
            0
        ));

        self::assertSame(0, $data['total']);
        self::assertSame(0, $data['finished']);
        self::assertSame('0', $data['pourcent']);
    }
}
