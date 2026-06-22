<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Division;
use App\Entity\GameStatus;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\TeamStat;
use App\Entity\User;
use App\Tests\Functional\ApiTestCase;

class BracketControllerTest extends ApiTestCase
{
    /**
     * Crée une division avec 4 équipes classées + statuts + un admin + des capitaines.
     */
    private function createDivisionContext(): array
    {
        $season = new Season();
        $season->setName('S');
        $season->setStartDate(new \DateTime('2026-01-01'));
        $season->setEndDate(new \DateTime('2026-12-31'));
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Division A');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        foreach (['scheduled', 'played'] as $name) {
            $status = new GameStatus();
            $status->setName($name);
            $this->entityManager->persist($status);
        }

        $admin = new User();
        $admin->setUsername('admin');
        $admin->setPassword('x');
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_API']);
        $admin->setIsActive(true);
        $this->entityManager->persist($admin);

        // 4 équipes avec points décroissants -> seeds 1..4
        $teams = [];
        $captains = [];
        $points = [9, 6, 3, 0];
        foreach ($points as $i => $pts) {
            $team = new Team();
            $team->setName('Team ' . ($i + 1));
            $this->entityManager->persist($team);

            $captain = new User();
            $captain->setUsername('cap' . $i);
            $captain->setPassword('x');
            $captain->setRoles(['ROLE_USER', 'ROLE_API']);
            $captain->setIsActive(true);
            $this->entityManager->persist($captain);

            $member = new TeamMember();
            $member->setRole(TeamMember::ROLE_CAPTAIN);
            $member->setJoinedAt(new \DateTimeImmutable());
            $team->addMember($member);
            $member->setUser($captain);
            $team->setCaptainUser($captain);
            $this->entityManager->persist($member);

            $stat = new TeamStat();
            $stat->setTeam($team);
            $stat->setDivision($division);
            $stat->setWins(0);
            $stat->setLosses(0);
            $stat->setTies(0);
            $stat->setPoints($pts);
            $stat->setWinRounds($pts);
            $stat->setLooseRounds(0);
            $this->entityManager->persist($stat);

            $teams[] = $team;
            $captains[] = $captain;
        }

        $this->entityManager->flush();

        return compact('division', 'admin', 'teams', 'captains');
    }

    public function testCreateSeedGenerateFlow(): void
    {
        $ctx = $this->createDivisionContext();
        $this->loginAs($ctx['admin']);

        // Créer le bracket
        $bracket = $this->jsonRequest('POST', '/api/brackets', [
            'name' => 'Playoff D1',
            'qualified_count' => 4,
            'has_third_place_match' => true,
            'division_id' => $ctx['division']->getId(),
        ]);
        $this->assertResponseStatusCode(201);
        $bracketId = $bracket['id'];

        // Auto-seed depuis le classement
        $seed = $this->jsonRequest('POST', "/api/brackets/{$bracketId}/seed");
        $this->assertResponseStatusCode(200);
        $this->assertSame(4, $seed['seeded']);

        // Générer l'arbre
        $tree = $this->jsonRequest('POST', "/api/brackets/{$bracketId}/generate");
        $this->assertResponseStatusCode(201);
        $this->assertSame('ready', $tree['status']);
        $this->assertCount(4, $tree['seeds']);

        // seed 1 = Team 1 (9 pts)
        $this->assertSame('Team 1', $tree['seeds'][0]['team_name']);

        // 2 rounds : round 1 = 2 demi-finales ; round 2 = finale + petite finale
        $this->assertCount(2, $tree['rounds']);
        $round1 = $tree['rounds'][0]['matches'];
        $round2 = $tree['rounds'][1]['matches'];
        $this->assertCount(2, $round1);
        $this->assertCount(2, $round2); // finale + petite finale

        $thirdPlace = array_values(array_filter($round2, fn ($m) => $m['is_third_place_match']));
        $this->assertCount(1, $thirdPlace);
    }

    public function testProgressionAdvancesWinner(): void
    {
        $ctx = $this->createDivisionContext();
        $this->loginAs($ctx['admin']);

        $bracket = $this->jsonRequest('POST', '/api/brackets', [
            'name' => 'Playoff',
            'qualified_count' => 4,
            'division_id' => $ctx['division']->getId(),
        ]);
        $bracketId = $bracket['id'];
        $this->jsonRequest('POST', "/api/brackets/{$bracketId}/seed");
        $tree = $this->jsonRequest('POST', "/api/brackets/{$bracketId}/generate");

        // Première demi-finale (round 1, position 0) : seed1 vs seed4
        $semi = $tree['rounds'][0]['matches'][0];
        $semiId = $semi['id'];
        $team1Id = $semi['team1_id'];

        // Le capitaine de team1 soumet 4-0, l'adversaire confirme
        $cap1 = $this->captainOfTeam($ctx, $team1Id);
        $cap2 = $this->captainOfTeam($ctx, $semi['team2_id']);

        $this->loginAs($cap1);
        $this->jsonRequest('POST', "/api/games/{$semiId}/result", ['score1' => 4, 'score2' => 0]);
        $this->assertResponseStatusCode(201);

        $this->loginAs($cap2);
        $this->jsonRequest('PUT', "/api/games/{$semiId}/result/confirm");
        $this->assertResponseStatusCode(200);

        // Recharger l'arbre : le vainqueur de la demi doit être dans la finale (round 2 position 0)
        $this->loginAs($ctx['admin']);
        $tree2 = $this->jsonRequest('GET', "/api/brackets/{$bracketId}");
        $final = array_values(array_filter(
            $tree2['rounds'][1]['matches'],
            fn ($m) => !$m['is_third_place_match']
        ))[0];

        $this->assertSame($team1Id, $final['team1_id']);
    }

    public function testDetachedBracketWithManualEntries(): void
    {
        $ctx = $this->createDivisionContext();
        $this->loginAs($ctx['admin']);

        // Bracket sans division
        $bracket = $this->jsonRequest('POST', '/api/brackets', [
            'name' => 'Tournoi détaché',
            'qualified_count' => 2,
        ]);
        $this->assertResponseStatusCode(201);
        $this->assertNull($bracket['division_id']);
        $bracketId = $bracket['id'];

        // Seeds manuels
        $this->jsonRequest('PUT', "/api/brackets/{$bracketId}/entries", [
            'entries' => [
                ['seed' => 1, 'team_id' => $ctx['teams'][0]->getId()],
                ['seed' => 2, 'team_id' => $ctx['teams'][1]->getId()],
            ],
        ]);
        $this->assertResponseStatusCode(200);

        $tree = $this->jsonRequest('POST', "/api/brackets/{$bracketId}/generate");
        $this->assertResponseStatusCode(201);
        // 2 équipes -> 1 seul match (la finale)
        $this->assertCount(1, $tree['rounds']);
        $this->assertCount(1, $tree['rounds'][0]['matches']);
    }

    private function captainOfTeam(array $ctx, int $teamId): User
    {
        foreach ($ctx['teams'] as $i => $team) {
            if ($team->getId() === $teamId) {
                return $ctx['captains'][$i];
            }
        }
        throw new \RuntimeException('Captain not found for team ' . $teamId);
    }

    /**
     * Authentifie l'utilisateur pour les requêtes suivantes.
     *
     * En environnement de test le firewall `api` est stateful (override
     * when@test dans security.yaml), de sorte que loginUser() persiste
     * entre plusieurs requêtes successives du même test.
     */
    private function loginAs(User $user): void
    {
        $this->client->loginUser($user, 'api');
    }
}
