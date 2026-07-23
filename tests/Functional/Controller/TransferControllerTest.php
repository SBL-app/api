<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;
use App\Entity\Division;
use App\Entity\Player;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\TeamStat;
use App\Entity\Transfer;
use App\Entity\User;

/**
 * Tests du flux de transfert de joueurs (issue app#28).
 */
class TransferControllerTest extends ApiTestCase
{
    private function makeSeason(): Season
    {
        $season = new Season();
        $season->setName('Saison Transfert');
        $this->entityManager->persist($season);
        return $season;
    }

    private function makeTeam(string $name): Team
    {
        $team = new Team();
        $team->setName($name);
        $this->entityManager->persist($team);
        return $team;
    }

    /**
     * Crée un capitaine (ou membre) authentifié pour l'équipe donnée.
     */
    private function authenticateAs(Team $team, string $role = TeamMember::ROLE_CAPTAIN): User
    {
        $user = new User();
        $user->setUsername('u_' . uniqid());
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        $user->setDiscordId('d_' . uniqid());
        $this->entityManager->persist($user);

        $member = new TeamMember();
        $member->setUser($user);
        $member->setRole($role);
        $team->addMember($member);
        $this->entityManager->persist($member);

        $this->entityManager->flush();
        $this->client->loginUser($user, 'api');

        return $user;
    }

    private function putTeamInDivision(Team $team, Season $season, int $level): void
    {
        $division = new Division();
        $division->setName('Division N' . $level);
        $division->setSeason($season);
        $division->setLevel($level);
        $this->entityManager->persist($division);

        $stat = new TeamStat();
        $stat->setTeam($team);
        $stat->setDivision($division);
        $stat->setWins(0);
        $stat->setLosses(0);
        $stat->setTies(0);
        $stat->setPoints(0);
        $stat->setWinRounds(0);
        $stat->setLooseRounds(0);
        $this->entityManager->persist($stat);
    }

    private function makePlayer(string $name, ?Team $team): Player
    {
        $player = new Player();
        $player->setName($name);
        $player->setTeam($team);
        $this->entityManager->persist($player);
        return $player;
    }

    public function testCaptainCanTransferFreeAgent(): void
    {
        $season = $this->makeSeason();
        $toTeam = $this->makeTeam('Accueil');
        $player = $this->makePlayer('Agent Libre', null);
        $this->entityManager->flush();

        $this->authenticateAs($toTeam);

        $response = $this->jsonRequest('POST', '/api/teams/' . $toTeam->getId() . '/transfers', [
            'player_id' => $player->getId(),
            'season_id' => $season->getId(),
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertEquals($player->getId(), $response['player_id']);
        $this->assertEquals($toTeam->getId(), $response['to_team_id']);
    }

    public function testTransferRejectedByDivisionRule(): void
    {
        $season = $this->makeSeason();
        $toTeam = $this->makeTeam('Elite');
        $fromTeam = $this->makeTeam('Amateurs');
        // Accueil en division 1 (haut), joueur en division 3 : 3 > 1 + 1 → refus.
        $this->putTeamInDivision($toTeam, $season, 1);
        $this->putTeamInDivision($fromTeam, $season, 3);
        $player = $this->makePlayer('Joueur Bas', $fromTeam);
        $this->entityManager->flush();

        $this->authenticateAs($toTeam);

        $this->jsonRequest('POST', '/api/teams/' . $toTeam->getId() . '/transfers', [
            'player_id' => $player->getId(),
            'season_id' => $season->getId(),
        ]);

        $this->assertResponseStatusCode(400);
    }

    public function testTransferAllowedWithinDivisionRule(): void
    {
        $season = $this->makeSeason();
        $toTeam = $this->makeTeam('Elite');
        $fromTeam = $this->makeTeam('Challengers');
        // Accueil en division 1, joueur en division 2 : 2 <= 1 + 1 → autorisé.
        $this->putTeamInDivision($toTeam, $season, 1);
        $this->putTeamInDivision($fromTeam, $season, 2);
        $player = $this->makePlayer('Joueur Proche', $fromTeam);
        $this->entityManager->flush();

        $this->authenticateAs($toTeam);

        $response = $this->jsonRequest('POST', '/api/teams/' . $toTeam->getId() . '/transfers', [
            'player_id' => $player->getId(),
            'season_id' => $season->getId(),
        ]);

        $this->assertResponseStatusCode(201);
        $this->assertEquals($fromTeam->getId(), $response['from_team_id']);
    }

    public function testMaxTransfersPerSeason(): void
    {
        $season = $this->makeSeason();
        $toTeam = $this->makeTeam('Recruteur');

        // 2 transferts déjà enregistrés cette saison.
        for ($i = 0; $i < 2; $i++) {
            $p = $this->makePlayer('Déjà ' . $i, null);
            $t = new Transfer();
            $t->setPlayer($p);
            $t->setToTeam($toTeam);
            $t->setSeason($season);
            $this->entityManager->persist($t);
        }

        $player = $this->makePlayer('Troisième', null);
        $this->entityManager->flush();

        $this->authenticateAs($toTeam);

        $this->jsonRequest('POST', '/api/teams/' . $toTeam->getId() . '/transfers', [
            'player_id' => $player->getId(),
            'season_id' => $season->getId(),
        ]);

        $this->assertResponseStatusCode(400);
    }

    public function testNonCaptainCannotTransfer(): void
    {
        $season = $this->makeSeason();
        $toTeam = $this->makeTeam('Accueil');
        $player = $this->makePlayer('Agent Libre', null);
        $this->entityManager->flush();

        $this->authenticateAs($toTeam, TeamMember::ROLE_MEMBER);

        $this->jsonRequest('POST', '/api/teams/' . $toTeam->getId() . '/transfers', [
            'player_id' => $player->getId(),
            'season_id' => $season->getId(),
        ]);

        $this->assertResponseStatusCode(403);
    }

    public function testPlayerIdRequired(): void
    {
        $season = $this->makeSeason();
        $toTeam = $this->makeTeam('Accueil');
        $this->entityManager->flush();

        $this->authenticateAs($toTeam);

        $this->jsonRequest('POST', '/api/teams/' . $toTeam->getId() . '/transfers', [
            'season_id' => $season->getId(),
        ]);

        $this->assertResponseStatusCode(400);
    }

    public function testListTransfersBySeason(): void
    {
        $season = $this->makeSeason();
        $toTeam = $this->makeTeam('Accueil');
        $player = $this->makePlayer('Transféré', null);
        $transfer = new Transfer();
        $transfer->setPlayer($player);
        $transfer->setToTeam($toTeam);
        $transfer->setSeason($season);
        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $response = $this->jsonRequest('GET', '/api/teams/' . $toTeam->getId() . '/transfers?season_id=' . $season->getId());

        $this->assertResponseStatusCode(200);
        $this->assertCount(1, $response);
        $this->assertEquals($player->getId(), $response[0]['player_id']);
    }
}
