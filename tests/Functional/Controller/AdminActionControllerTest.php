<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\MatchResult;
use App\Entity\Season;
use App\Entity\Team;
use App\Tests\Functional\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AdminActionControllerTest extends ApiTestCase
{
    private Division $division;
    private array $statuses = [];
    private Team $t1;
    private Team $t2;

    protected function setUp(): void
    {
        parent::setUp();
        $season = (new Season())->setName('S')->setStartDate(new \DateTime('2024-01-01'))->setEndDate(new \DateTime('2024-12-31'));
        $this->division = (new Division())->setName('D')->setSeason($season);
        $this->t1 = (new Team())->setName('A');
        $this->t2 = (new Team())->setName('B');
        foreach ([$season, $this->division, $this->t1, $this->t2] as $e) {
            $this->entityManager->persist($e);
        }
        foreach (['pending_result', 'played'] as $n) {
            $s = (new GameStatus())->setName($n);
            $this->entityManager->persist($s);
            $this->statuses[$n] = $s;
        }
        $this->entityManager->flush();
    }

    private function game(string $status): Game
    {
        $g = (new Game())->setDate(new \DateTime('2024-03-01'))->setWeek(1)->setScore1(0)->setScore2(0)
            ->setDivision($this->division)->setTeam1($this->t1)->setTeam2($this->t2)->setStatus($this->statuses[$status]);
        $this->entityManager->persist($g);
        $this->entityManager->flush();

        return $g;
    }

    public static function routes(): array
    {
        return [['/api/admin/games/1/validate-result'], ['/api/admin/games/1/remind'], ['/api/admin/divisions/1/close']];
    }

    #[DataProvider('routes')]
    public function testAnonymous401(string $url): void
    {
        $this->jsonRequest('POST', $url);
        $this->assertResponseStatusCode(401);
    }

    #[DataProvider('routes')]
    public function testNonAdmin403(string $url): void
    {
        $this->authenticateAsApi(['ROLE_USER', 'ROLE_API']);
        $this->jsonRequest('POST', $url);
        $this->assertResponseStatusCode(403);
    }

    #[DataProvider('routes')]
    public function testUnknown404(string $url): void
    {
        $this->authenticateAsApi(['ROLE_USER', 'ROLE_ADMIN']);
        $this->jsonRequest('POST', str_replace('/1/', '/99999/', $url));
        $this->assertResponseStatusCode(404);
    }

    public function testValidateResult(): void
    {
        $game = $this->game('pending_result');
        $user = $this->authenticateAsApi(['ROLE_USER', 'ROLE_ADMIN']);
        $r = (new MatchResult())->setGame($game)->setSubmittedBy($user)->setTeam($this->t1)->setScore1(3)->setScore2(1);
        $this->entityManager->persist($r);
        $this->entityManager->flush();

        $this->jsonRequest('POST', '/api/admin/games/' . $game->getId() . '/validate-result');
        $this->assertResponseIsSuccessful();
        $this->entityManager->refresh($game);
        $this->assertSame(3, $game->getScore1());
        $this->assertSame('played', $game->getStatus()->getName());

        $this->client->loginUser($user, 'api'); // firewall stateless : ré-authentifier
        $this->jsonRequest('POST', '/api/admin/games/' . $game->getId() . '/validate-result');
        $this->assertResponseStatusCode(409);
    }

    public function testRemind(): void
    {
        $game = $this->game('pending_result');
        $this->authenticateAsApi(['ROLE_USER', 'ROLE_ADMIN']);
        $this->jsonRequest('POST', '/api/admin/games/' . $game->getId() . '/remind');
        $this->assertResponseIsSuccessful();
    }

    public function testRemindConflictWhenValidated(): void
    {
        $game = $this->game('played');
        $user = $this->authenticateAsApi(['ROLE_USER', 'ROLE_ADMIN']);
        $r = (new MatchResult())->setGame($game)->setSubmittedBy($user)->setTeam($this->t1)->setScore1(1)->setScore2(0)->setStatus(MatchResult::STATUS_VALIDATED);
        $this->entityManager->persist($r);
        $this->entityManager->flush();
        $this->jsonRequest('POST', '/api/admin/games/' . $game->getId() . '/remind');
        $this->assertResponseStatusCode(409);
    }

    public function testCloseDivision(): void
    {
        $open = $this->game('pending_result');
        $user = $this->authenticateAsApi(['ROLE_USER', 'ROLE_ADMIN']);
        $url = '/api/admin/divisions/' . $this->division->getId() . '/close';

        $this->jsonRequest('POST', $url);
        $this->assertResponseStatusCode(409);

        $open->setStatus($this->statuses['played']);
        $this->entityManager->flush();
        $this->client->loginUser($user, 'api');
        $this->jsonRequest('POST', $url);
        $this->assertResponseIsSuccessful();

        $this->client->loginUser($user, 'api');
        $this->jsonRequest('POST', $url);
        $this->assertResponseStatusCode(409);
    }
}
