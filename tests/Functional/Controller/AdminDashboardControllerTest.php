<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\MatchResult;
use App\Entity\Registration;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\User;
use App\Tests\Functional\ApiTestCase;

class AdminDashboardControllerTest extends ApiTestCase
{
    private Season $season;
    private Division $division;
    private array $statuses = [];
    private Team $team1;
    private Team $team2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = (new Season())
            ->setName('S1')
            ->setStartDate(new \DateTime('2024-01-01'))
            ->setEndDate(new \DateTime('2024-12-31'));
        $this->division = (new Division())->setName('D1')->setSeason($this->season);
        $this->team1 = (new Team())->setName('T1');
        $this->team2 = (new Team())->setName('T2');

        foreach ([$this->season, $this->division, $this->team1, $this->team2] as $e) {
            $this->entityManager->persist($e);
        }
        foreach (['pending_result', 'contested', 'reported', 'played'] as $name) {
            $status = (new GameStatus())->setName($name);
            $this->entityManager->persist($status);
            $this->statuses[$name] = $status;
        }
        $this->entityManager->flush();
    }

    private function makeGame(string $status, ?\DateTime $date = null): Game
    {
        $game = (new Game())
            ->setDate($date)
            ->setWeek(1)
            ->setScore1(0)
            ->setScore2(0)
            ->setDivision($this->division)
            ->setTeam1($this->team1)
            ->setTeam2($this->team2)
            ->setStatus($this->statuses[$status]);
        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $game;
    }

    public function testRequiresAuthentication(): void
    {
        $this->jsonRequest('GET', '/api/admin/dashboard');
        $this->assertResponseStatusCode(401);
    }

    public function testForbiddenForNonAdmin(): void
    {
        $this->authenticateAsApi(['ROLE_USER', 'ROLE_API']);
        $this->jsonRequest('GET', '/api/admin/dashboard');
        $this->assertResponseStatusCode(403);
    }

    public function testEmptyDashboard(): void
    {
        $this->authenticateAsApi(['ROLE_USER', 'ROLE_ADMIN']);
        $data = $this->jsonRequest('GET', '/api/admin/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $data['pending_results']['total']);
        $this->assertSame(0, $data['contested_results']['total']);
        $this->assertSame(0, $data['reported_games']['total']);
        $this->assertSame(0, $data['pending_registrations']['total']);
        $this->assertSame(0, $data['division_progress'][0]['total']);
        $this->assertSame([], $data['items']['pending_results']);
        $this->assertSame([], $data['items']['pending_registrations']);
    }

    public function testAggregates(): void
    {
        $pending1 = $this->makeGame('pending_result', new \DateTime('2024-03-01'));
        $pending2 = $this->makeGame('pending_result', new \DateTime('2024-03-02'));
        $this->makeGame('reported', null);
        $this->makeGame('reported', new \DateTime('2024-03-05')); // déjà reprogrammé : exclu
        $this->makeGame('played', new \DateTime('2024-03-03'));
        $contested = $this->makeGame('contested', new \DateTime('2024-03-04'));

        $user = $this->authenticateAsApi(['ROLE_USER', 'ROLE_ADMIN']);
        $result = (new MatchResult())
            ->setGame($contested)
            ->setSubmittedBy($user)
            ->setTeam($this->team1)
            ->setScore1(4)
            ->setScore2(2)
            ->setStatus(MatchResult::STATUS_CONTESTED);
        $this->entityManager->persist($result);

        foreach ([Registration::STATUS_PENDING, Registration::STATUS_PENDING, Registration::STATUS_APPROVED] as $st) {
            $team = (new Team())->setName('R' . uniqid());
            $this->entityManager->persist($team);
            $reg = (new Registration())->setSeason($this->season)->setTeam($team)->setStatus($st);
            $this->entityManager->persist($reg);
        }
        $this->entityManager->flush();

        $data = $this->jsonRequest('GET', '/api/admin/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSame(2, $data['pending_results']['total']);
        $this->assertSame($this->division->getId(), $data['pending_results']['by_division'][0]['division_id']);
        $this->assertSame(2, $data['pending_results']['by_division'][0]['count']);
        $this->assertSame(1, $data['contested_results']['total']);
        $this->assertSame(1, $data['reported_games']['total']);
        $this->assertSame(2, $data['pending_registrations']['total']);
        $this->assertSame($this->season->getId(), $data['pending_registrations']['by_season'][0]['season_id']);
        $this->assertSame(1, $data['division_progress'][0]['played']);
        $this->assertSame(6, $data['division_progress'][0]['total']);

        $this->assertSame(
            [$pending1->getId(), $pending2->getId()],
            array_column($data['items']['pending_results'], 'id')
        );
        $this->assertSame($this->division->getId(), $data['items']['pending_results'][0]['division_id']);
        $this->assertSame($this->team1->getId(), $data['items']['pending_results'][0]['team1']['id']);
        $this->assertCount(2, $data['items']['pending_registrations']);
        $this->assertSame($this->season->getId(), $data['items']['pending_registrations'][0]['season_id']);
    }
}
