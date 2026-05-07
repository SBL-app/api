# Game Result Validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre aux capitaines de soumettre et valider les scores de leurs matchs avec double validation et escalade admin en cas de désaccord.

**Architecture:** Nouvelle entité `GameResult` (pattern identique à `MatchProposal`) avec statuts `pending_validation` / `confirmed` / `disputed`. À la confirmation, le controller met à jour directement `Game` (score, status "played") et les `TeamStat` des 2 équipes. Le timeout auto-dispute (scheduler) est hors scope (#41).

**Tech Stack:** Symfony 7.3, PHP 8.3, Doctrine ORM, PHPUnit, SQLite (tests)

---

## Structure des fichiers

| Fichier | Action | Responsabilité |
|---------|--------|----------------|
| `src/Entity/GameResult.php` | Créer | Entité résultat avec statuts |
| `src/Repository/GameResultRepository.php` | Créer | Queries findPendingByGame, findDisputedByGame, findLatestByGame |
| `src/Repository/TeamStatRepository.php` | Modifier | Ajouter findByTeamAndDivision() |
| `src/Controller/GameResultController.php` | Créer | 5 endpoints submit/confirm/dispute/admin-resolve/get |
| `migrations/Version20260507120000.php` | Générer | Table game_result |
| `tests/Functional/Controller/GameResultControllerTest.php` | Créer | Tests fonctionnels complets |

---

## Task 1 : Entité `GameResult` + Repository

**Files:**
- Create: `src/Entity/GameResult.php`
- Create: `src/Repository/GameResultRepository.php`

- [ ] **Étape 1 : Créer l'entité**

```php
// src/Entity/GameResult.php
<?php

namespace App\Entity;

use App\Repository\GameResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameResultRepository::class)]
class GameResult
{
    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DISPUTED = 'disputed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $submittedByTeam = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $submittedBy = null;

    #[ORM\Column]
    private ?int $score1 = null;

    #[ORM\Column]
    private ?int $score2 = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_PENDING_VALIDATION;

    #[ORM\ManyToOne]
    private ?User $respondedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $respondedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getGame(): ?Game { return $this->game; }
    public function setGame(?Game $game): static { $this->game = $game; return $this; }

    public function getSubmittedByTeam(): ?Team { return $this->submittedByTeam; }
    public function setSubmittedByTeam(?Team $team): static { $this->submittedByTeam = $team; return $this; }

    public function getSubmittedBy(): ?User { return $this->submittedBy; }
    public function setSubmittedBy(?User $user): static { $this->submittedBy = $user; return $this; }

    public function getScore1(): ?int { return $this->score1; }
    public function setScore1(int $score1): static { $this->score1 = $score1; return $this; }

    public function getScore2(): ?int { return $this->score2; }
    public function setScore2(int $score2): static { $this->score2 = $score2; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getRespondedBy(): ?User { return $this->respondedBy; }
    public function setRespondedBy(?User $user): static { $this->respondedBy = $user; return $this; }

    public function getRespondedAt(): ?\DateTimeInterface { return $this->respondedAt; }
    public function setRespondedAt(?\DateTimeInterface $at): static { $this->respondedAt = $at; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isPendingValidation(): bool { return $this->status === self::STATUS_PENDING_VALIDATION; }
    public function isConfirmed(): bool { return $this->status === self::STATUS_CONFIRMED; }
    public function isDisputed(): bool { return $this->status === self::STATUS_DISPUTED; }

    public function confirm(User $respondedBy): static
    {
        $this->status = self::STATUS_CONFIRMED;
        $this->respondedBy = $respondedBy;
        $this->respondedAt = new \DateTime();
        return $this;
    }

    public function dispute(User $respondedBy): static
    {
        $this->status = self::STATUS_DISPUTED;
        $this->respondedBy = $respondedBy;
        $this->respondedAt = new \DateTime();
        return $this;
    }
}
```

- [ ] **Étape 2 : Créer le repository**

```php
// src/Repository/GameResultRepository.php
<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GameResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameResult>
 */
class GameResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameResult::class);
    }

    public function findPendingByGame(Game $game): ?GameResult
    {
        return $this->findOneBy([
            'game' => $game,
            'status' => GameResult::STATUS_PENDING_VALIDATION,
        ]);
    }

    public function findDisputedByGame(Game $game): ?GameResult
    {
        return $this->findOneBy([
            'game' => $game,
            'status' => GameResult::STATUS_DISPUTED,
        ]);
    }

    public function findLatestByGame(Game $game): ?GameResult
    {
        return $this->createQueryBuilder('gr')
            ->where('gr.game = :game')
            ->setParameter('game', $game)
            ->orderBy('gr.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
```

- [ ] **Étape 3 : Commit**

```bash
git add src/Entity/GameResult.php src/Repository/GameResultRepository.php
git commit -m "feat: add GameResult entity and repository"
```

---

## Task 2 : Ajouter `findByTeamAndDivision()` à `TeamStatRepository`

**Files:**
- Modify: `src/Repository/TeamStatRepository.php`

- [ ] **Étape 1 : Ajouter la méthode**

Dans `src/Repository/TeamStatRepository.php`, ajouter les imports et la méthode :

```php
// Ajouter en haut du fichier après "use App\Entity\TeamStat;"
use App\Entity\Division;
use App\Entity\Team;
```

```php
// Ajouter dans la classe TeamStatRepository
public function findByTeamAndDivision(Team $team, Division $division): ?TeamStat
{
    return $this->findOneBy([
        'team' => $team,
        'division' => $division,
    ]);
}
```

- [ ] **Étape 2 : Commit**

```bash
git add src/Repository/TeamStatRepository.php
git commit -m "feat: add findByTeamAndDivision to TeamStatRepository"
```

---

## Task 3 : Migration Doctrine

**Files:**
- Generate: `migrations/Version20260507120000.php`

- [ ] **Étape 1 : Générer la migration**

```bash
php bin/console make:migration
```

Vérifier que le fichier généré contient une table `game_result` avec les colonnes :
`id`, `game_id`, `submitted_by_team_id`, `submitted_by_id`, `score1`, `score2`, `status`, `responded_by_id`, `responded_at`, `created_at`

- [ ] **Étape 2 : Appliquer la migration**

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Expected: `[OK] Successfully executed 1 migrations`

- [ ] **Étape 3 : Commit**

```bash
git add migrations/
git commit -m "feat: add game_result migration"
```

---

## Task 4 : `GameResultController` — GET + helpers communs

**Files:**
- Create: `src/Controller/GameResultController.php`
- Create: `tests/Functional/Controller/GameResultControllerTest.php`

- [ ] **Étape 1 : Écrire le test GET qui échoue**

```php
// tests/Functional/Controller/GameResultControllerTest.php
<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Division;
use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\GameStatus;
use App\Entity\Season;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\TeamStat;
use App\Entity\User;
use App\Tests\Functional\ApiTestCase;

class GameResultControllerTest extends ApiTestCase
{
    private function createMatchContext(): array
    {
        $season = new Season();
        $season->setName('Season 2026');
        $season->setStartDate(new \DateTime('2026-01-01'));
        $season->setEndDate(new \DateTime('2026-12-31'));
        $this->entityManager->persist($season);

        $division = new Division();
        $division->setName('Division A');
        $division->setSeason($season);
        $this->entityManager->persist($division);

        $scheduledStatus = new GameStatus();
        $scheduledStatus->setName('scheduled');
        $this->entityManager->persist($scheduledStatus);

        $playedStatus = new GameStatus();
        $playedStatus->setName('played');
        $this->entityManager->persist($playedStatus);

        $team1 = new Team();
        $team1->setName('Team Alpha');
        $this->entityManager->persist($team1);

        $team2 = new Team();
        $team2->setName('Team Beta');
        $this->entityManager->persist($team2);

        $game = new Game();
        $game->setTeam1($team1);
        $game->setTeam2($team2);
        $game->setDivision($division);
        $game->setStatus($scheduledStatus);
        $game->setWeek(1);
        $game->setScore1(0);
        $game->setScore2(0);
        $game->setDate(new \DateTime('2026-03-15'));
        $this->entityManager->persist($game);

        $captain1 = new User();
        $captain1->setUsername('captain1');
        $captain1->setPassword('hashed');
        $captain1->setRoles(['ROLE_USER', 'ROLE_API']);
        $captain1->setIsActive(true);
        $this->entityManager->persist($captain1);

        $member1 = new TeamMember();
        $member1->setRole(TeamMember::ROLE_CAPTAIN);
        $member1->setJoinedAt(new \DateTimeImmutable());
        $team1->addMember($member1);
        $member1->setUser($captain1);
        $team1->setCaptainUser($captain1);
        $this->entityManager->persist($member1);

        $captain2 = new User();
        $captain2->setUsername('captain2');
        $captain2->setPassword('hashed');
        $captain2->setRoles(['ROLE_USER', 'ROLE_API']);
        $captain2->setIsActive(true);
        $this->entityManager->persist($captain2);

        $member2 = new TeamMember();
        $member2->setRole(TeamMember::ROLE_CAPTAIN);
        $member2->setJoinedAt(new \DateTimeImmutable());
        $team2->addMember($member2);
        $member2->setUser($captain2);
        $team2->setCaptainUser($captain2);
        $this->entityManager->persist($member2);

        $admin = new User();
        $admin->setUsername('admin');
        $admin->setPassword('hashed');
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN', 'ROLE_API']);
        $admin->setIsActive(true);
        $this->entityManager->persist($admin);

        $stat1 = new TeamStat();
        $stat1->setTeam($team1);
        $stat1->setDivision($division);
        $stat1->setWins(0);
        $stat1->setLosses(0);
        $stat1->setTies(0);
        $stat1->setPoints(0);
        $stat1->setWinRounds(0);
        $stat1->setLooseRounds(0);
        $this->entityManager->persist($stat1);

        $stat2 = new TeamStat();
        $stat2->setTeam($team2);
        $stat2->setDivision($division);
        $stat2->setWins(0);
        $stat2->setLosses(0);
        $stat2->setTies(0);
        $stat2->setPoints(0);
        $stat2->setWinRounds(0);
        $stat2->setLooseRounds(0);
        $this->entityManager->persist($stat2);

        $this->entityManager->flush();

        return [
            'game' => $game,
            'team1' => $team1,
            'team2' => $team2,
            'captain1' => $captain1,
            'captain2' => $captain2,
            'admin' => $admin,
            'season' => $season,
            'division' => $division,
            'stat1' => $stat1,
            'stat2' => $stat2,
        ];
    }

    public function testGetResultNotFound(): void
    {
        $ctx = $this->createMatchContext();

        $response = $this->jsonRequest('GET', '/api/games/' . $ctx['game']->getId() . '/result');

        $this->assertResponseStatusCode(404);
    }
}
```

- [ ] **Étape 2 : Lancer le test — vérifier l'échec**

```bash
php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php --filter testGetResultNotFound -v
```

Expected : FAIL `No route found` ou 404 non géré (route inexistante).

- [ ] **Étape 3 : Créer le controller avec GET + formatEntityData**

```php
// src/Controller/GameResultController.php
<?php

namespace App\Controller;

use App\Entity\GameResult;
use App\Exception\ApiProblemException;
use App\Repository\GameResultRepository;
use App\Repository\GameStatusRepository;
use App\Repository\TeamStatRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class GameResultController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof GameResult) {
            throw new \InvalidArgumentException('Entity must be an instance of GameResult');
        }

        return [
            'id' => $entity->getId(),
            'game_id' => $entity->getGame()?->getId(),
            'submitted_by_team_id' => $entity->getSubmittedByTeam()?->getId(),
            'submitted_by_team_name' => $entity->getSubmittedByTeam()?->getName(),
            'submitted_by_id' => $entity->getSubmittedBy()?->getId(),
            'score1' => $entity->getScore1(),
            'score2' => $entity->getScore2(),
            'status' => $entity->getStatus(),
            'responded_by_id' => $entity->getRespondedBy()?->getId(),
            'responded_at' => $entity->getRespondedAt()?->format('Y-m-d H:i:s'),
            'created_at' => $entity->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    #[Route('/games/{id}/result', name: 'app_game_result_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getResult(int $id, GameResultRepository $resultRepository): JsonResponse
    {
        $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

        $result = $resultRepository->findLatestByGame($game);
        if (!$result) {
            throw ApiProblemException::notFound('No result found for this game');
        }

        return $this->json($this->formatEntityData($result));
    }
}
```

- [ ] **Étape 4 : Lancer le test — vérifier le passage**

```bash
php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php --filter testGetResultNotFound -v
```

Expected : PASS

- [ ] **Étape 5 : Commit**

```bash
git add src/Controller/GameResultController.php tests/Functional/Controller/GameResultControllerTest.php
git commit -m "feat: add GameResultController with GET endpoint"
```

---

## Task 5 : POST — soumettre un score

**Files:**
- Modify: `src/Controller/GameResultController.php`
- Modify: `tests/Functional/Controller/GameResultControllerTest.php`

- [ ] **Étape 1 : Écrire les tests POST qui échouent**

Ajouter dans `GameResultControllerTest` :

```php
public function testSubmitResultSuccess(): void
{
    $ctx = $this->createMatchContext();
    $this->client->loginUser($ctx['captain1'], 'api');

    $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 1,
    ]);

    $this->assertResponseStatusCode(201);
    $this->assertNotNull($response);
    $this->assertArrayHasKey('id', $response);
    $this->assertEquals(2, $response['score1']);
    $this->assertEquals(1, $response['score2']);
    $this->assertEquals(GameResult::STATUS_PENDING_VALIDATION, $response['status']);
    $this->assertEquals($ctx['team1']->getId(), $response['submitted_by_team_id']);
}

public function testSubmitResultDuplicateFails(): void
{
    $ctx = $this->createMatchContext();
    $this->client->loginUser($ctx['captain1'], 'api');

    $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 1,
    ]);

    $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 0,
    ]);

    $this->assertResponseStatusCode(409);
}

public function testSubmitResultNotCaptainFails(): void
{
    $ctx = $this->createMatchContext();

    $outsider = new User();
    $outsider->setUsername('outsider');
    $outsider->setPassword('hashed');
    $outsider->setRoles(['ROLE_USER', 'ROLE_API']);
    $outsider->setIsActive(true);
    $this->entityManager->persist($outsider);
    $this->entityManager->flush();

    $this->client->loginUser($outsider, 'api');

    $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 1,
    ]);

    $this->assertResponseStatusCode(403);
}
```

- [ ] **Étape 2 : Lancer les tests — vérifier l'échec**

```bash
php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php --filter "testSubmit" -v
```

Expected : FAIL (route POST inexistante).

- [ ] **Étape 3 : Implémenter le endpoint POST**

Ajouter dans `GameResultController` :

```php
#[Route('/games/{id}/result', name: 'app_game_result_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
public function submitResult(
    int $id,
    Request $request,
    GameResultRepository $resultRepository,
): JsonResponse {
    $user = $this->getAuthenticatedUser();
    $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

    $team1 = $game->getTeam1();
    $team2 = $game->getTeam2();

    $team = null;
    if ($team1 && $team1->isCaptain($user)) {
        $team = $team1;
    } elseif ($team2 && $team2->isCaptain($user)) {
        $team = $team2;
    }

    if (!$team) {
        throw ApiProblemException::forbidden('You must be a captain of one of the teams in this game');
    }

    if ($resultRepository->findPendingByGame($game)) {
        throw ApiProblemException::conflict('A result is already pending validation for this game');
    }

    $data = $this->getRequestData($request);

    if (!isset($data['score1']) || !isset($data['score2'])) {
        throw ApiProblemException::badRequest('score1 and score2 are required');
    }

    $result = new GameResult();
    $result->setGame($game);
    $result->setSubmittedByTeam($team);
    $result->setSubmittedBy($user);
    $result->setScore1((int) $data['score1']);
    $result->setScore2((int) $data['score2']);

    $this->entityManager->persist($result);
    $this->entityManager->flush();

    return $this->json($this->formatEntityData($result), 201);
}
```

- [ ] **Étape 4 : Lancer les tests — vérifier le passage**

```bash
php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php --filter "testSubmit" -v
```

Expected : 3 PASS

- [ ] **Étape 5 : Commit**

```bash
git add src/Controller/GameResultController.php tests/Functional/Controller/GameResultControllerTest.php
git commit -m "feat: add POST submit result endpoint"
```

---

## Task 6 : PUT — confirmer un score (+ mise à jour stats)

**Files:**
- Modify: `src/Controller/GameResultController.php`
- Modify: `tests/Functional/Controller/GameResultControllerTest.php`

- [ ] **Étape 1 : Écrire les tests confirm qui échouent**

Ajouter dans `GameResultControllerTest` :

```php
public function testConfirmResultSuccess(): void
{
    $ctx = $this->createMatchContext();

    // Captain1 soumet
    $this->client->loginUser($ctx['captain1'], 'api');
    $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 1,
    ]);

    // Captain2 confirme
    $this->client->loginUser($ctx['captain2'], 'api');
    $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/confirm');

    $this->assertResponseStatusCode(200);
    $this->assertEquals(GameResult::STATUS_CONFIRMED, $response['status']);

    // Vérifier que le Game est mis à jour
    $this->entityManager->clear();
    $game = $this->entityManager->find('App\Entity\Game', $ctx['game']->getId());
    $this->assertEquals(2, $game->getScore1());
    $this->assertEquals(1, $game->getScore2());
    $this->assertEquals(1, $game->getWinner());
    $this->assertEquals('played', $game->getStatus()->getName());

    // Vérifier stats team1 (gagnante)
    $stat1 = $this->entityManager->find('App\Entity\TeamStat', $ctx['stat1']->getId());
    $this->assertEquals(1, $stat1->getWins());
    $this->assertEquals(0, $stat1->getLosses());
    $this->assertEquals(3, $stat1->getPoints());
    $this->assertEquals(2, $stat1->getWinRounds());
    $this->assertEquals(1, $stat1->getLooseRounds());

    // Vérifier stats team2 (perdante)
    $stat2 = $this->entityManager->find('App\Entity\TeamStat', $ctx['stat2']->getId());
    $this->assertEquals(0, $stat2->getWins());
    $this->assertEquals(1, $stat2->getLosses());
    $this->assertEquals(0, $stat2->getPoints());
    $this->assertEquals(1, $stat2->getWinRounds());
    $this->assertEquals(2, $stat2->getLooseRounds());
}

public function testConfirmResultTie(): void
{
    $ctx = $this->createMatchContext();

    $this->client->loginUser($ctx['captain1'], 'api');
    $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 1,
        'score2' => 1,
    ]);

    $this->client->loginUser($ctx['captain2'], 'api');
    $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/confirm');

    $this->assertResponseStatusCode(200);

    $this->entityManager->clear();
    $game = $this->entityManager->find('App\Entity\Game', $ctx['game']->getId());
    $this->assertNull($game->getWinner());

    $stat1 = $this->entityManager->find('App\Entity\TeamStat', $ctx['stat1']->getId());
    $this->assertEquals(1, $stat1->getTies());
    $this->assertEquals(1, $stat1->getPoints());

    $stat2 = $this->entityManager->find('App\Entity\TeamStat', $ctx['stat2']->getId());
    $this->assertEquals(1, $stat2->getTies());
    $this->assertEquals(1, $stat2->getPoints());
}

public function testConfirmBySameTeamFails(): void
{
    $ctx = $this->createMatchContext();

    $this->client->loginUser($ctx['captain1'], 'api');
    $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 1,
    ]);

    // Captain1 essaie de confirmer son propre résultat
    $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/confirm');

    $this->assertResponseStatusCode(403);
}

public function testConfirmNoPendingFails(): void
{
    $ctx = $this->createMatchContext();
    $this->client->loginUser($ctx['captain2'], 'api');

    $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/confirm');

    $this->assertResponseStatusCode(404);
}
```

- [ ] **Étape 2 : Lancer les tests — vérifier l'échec**

```bash
php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php --filter "testConfirm" -v
```

Expected : FAIL (route PUT inexistante).

- [ ] **Étape 3 : Implémenter le endpoint PUT confirm**

Ajouter dans `GameResultController` (ajouter `GameStatusRepository` et `TeamStatRepository` aux paramètres de méthode) :

```php
#[Route('/games/{id}/result/confirm', name: 'app_game_result_confirm', methods: ['PUT'], requirements: ['id' => '\d+'])]
public function confirmResult(
    int $id,
    GameResultRepository $resultRepository,
    GameStatusRepository $gameStatusRepository,
    TeamStatRepository $teamStatRepository,
): JsonResponse {
    $user = $this->getAuthenticatedUser();
    $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

    $result = $resultRepository->findPendingByGame($game);
    if (!$result) {
        throw ApiProblemException::notFound('No pending result found for this game');
    }

    if ($result->getSubmittedByTeam()->isCaptain($user)) {
        throw ApiProblemException::forbidden('You cannot confirm your own submitted result');
    }

    $team1 = $game->getTeam1();
    $team2 = $game->getTeam2();

    if (!$team1 || !$team2) {
        throw ApiProblemException::badRequest('Game must have two teams');
    }

    $opposingTeam = $result->getSubmittedByTeam() === $team1 ? $team2 : $team1;
    if (!$opposingTeam->isCaptain($user)) {
        throw ApiProblemException::forbidden('You must be a captain of the opposing team to confirm');
    }

    $result->confirm($user);

    $score1 = $result->getScore1();
    $score2 = $result->getScore2();
    $winner = match(true) {
        $score1 > $score2 => 1,
        $score2 > $score1 => 2,
        default => null,
    };

    $game->setScore1($score1);
    $game->setScore2($score2);
    $game->setWinner($winner);

    $playedStatus = $gameStatusRepository->findOneBy(['name' => 'played']);
    if ($playedStatus) {
        $game->setStatus($playedStatus);
    }

    $this->applyStatsUpdate($teamStatRepository, $game, $score1, $score2, $winner);

    $this->entityManager->persist($result);
    $this->entityManager->persist($game);
    $this->entityManager->flush();

    return $this->json($this->formatEntityData($result));
}

private function applyStatsUpdate(
    TeamStatRepository $teamStatRepository,
    \App\Entity\Game $game,
    int $score1,
    int $score2,
    ?int $winner,
): void {
    $division = $game->getDivision();
    $team1 = $game->getTeam1();
    $team2 = $game->getTeam2();

    $stat1 = $teamStatRepository->findByTeamAndDivision($team1, $division);
    $stat2 = $teamStatRepository->findByTeamAndDivision($team2, $division);

    if (!$stat1 || !$stat2) {
        throw ApiProblemException::badRequest('TeamStat not found for one or both teams in this division');
    }

    if ($winner === 1) {
        $stat1->setWins($stat1->getWins() + 1);
        $stat1->setPoints($stat1->getPoints() + 3);
        $stat2->setLosses($stat2->getLosses() + 1);
    } elseif ($winner === 2) {
        $stat2->setWins($stat2->getWins() + 1);
        $stat2->setPoints($stat2->getPoints() + 3);
        $stat1->setLosses($stat1->getLosses() + 1);
    } else {
        $stat1->setTies(($stat1->getTies() ?? 0) + 1);
        $stat1->setPoints($stat1->getPoints() + 1);
        $stat2->setTies(($stat2->getTies() ?? 0) + 1);
        $stat2->setPoints($stat2->getPoints() + 1);
    }

    $stat1->setWinRounds($stat1->getWinRounds() + $score1);
    $stat1->setLooseRounds($stat1->getLooseRounds() + $score2);
    $stat2->setWinRounds($stat2->getWinRounds() + $score2);
    $stat2->setLooseRounds($stat2->getLooseRounds() + $score1);

    $this->entityManager->persist($stat1);
    $this->entityManager->persist($stat2);
}
```

- [ ] **Étape 4 : Lancer les tests — vérifier le passage**

```bash
php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php --filter "testConfirm" -v
```

Expected : 4 PASS

- [ ] **Étape 5 : Commit**

```bash
git add src/Controller/GameResultController.php tests/Functional/Controller/GameResultControllerTest.php
git commit -m "feat: add PUT confirm result endpoint with stats update"
```

---

## Task 7 : PUT — contester un score

**Files:**
- Modify: `src/Controller/GameResultController.php`
- Modify: `tests/Functional/Controller/GameResultControllerTest.php`

- [ ] **Étape 1 : Écrire les tests dispute qui échouent**

Ajouter dans `GameResultControllerTest` :

```php
public function testDisputeResultSuccess(): void
{
    $ctx = $this->createMatchContext();

    $this->client->loginUser($ctx['captain1'], 'api');
    $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 1,
    ]);

    $this->client->loginUser($ctx['captain2'], 'api');
    $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/dispute');

    $this->assertResponseStatusCode(200);
    $this->assertEquals(GameResult::STATUS_DISPUTED, $response['status']);

    // Le Game ne doit PAS être modifié
    $this->entityManager->clear();
    $game = $this->entityManager->find('App\Entity\Game', $ctx['game']->getId());
    $this->assertEquals('scheduled', $game->getStatus()->getName());
}

public function testDisputeAllowsNewSubmission(): void
{
    $ctx = $this->createMatchContext();

    $this->client->loginUser($ctx['captain1'], 'api');
    $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 1,
    ]);

    $this->client->loginUser($ctx['captain2'], 'api');
    $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/dispute');

    // Après dispute, un nouveau résultat peut être soumis
    $this->client->loginUser($ctx['captain1'], 'api');
    $response = $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 0,
    ]);

    $this->assertResponseStatusCode(201);
}
```

- [ ] **Étape 2 : Lancer les tests — vérifier l'échec**

```bash
php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php --filter "testDispute" -v
```

Expected : FAIL (route PUT dispute inexistante).

- [ ] **Étape 3 : Implémenter le endpoint PUT dispute**

Ajouter dans `GameResultController` :

```php
#[Route('/games/{id}/result/dispute', name: 'app_game_result_dispute', methods: ['PUT'], requirements: ['id' => '\d+'])]
public function disputeResult(
    int $id,
    GameResultRepository $resultRepository,
): JsonResponse {
    $user = $this->getAuthenticatedUser();
    $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

    $result = $resultRepository->findPendingByGame($game);
    if (!$result) {
        throw ApiProblemException::notFound('No pending result found for this game');
    }

    if ($result->getSubmittedByTeam()->isCaptain($user)) {
        throw ApiProblemException::forbidden('You cannot dispute your own submitted result');
    }

    $team1 = $game->getTeam1();
    $team2 = $game->getTeam2();
    $opposingTeam = $result->getSubmittedByTeam() === $team1 ? $team2 : $team1;

    if (!$opposingTeam->isCaptain($user)) {
        throw ApiProblemException::forbidden('You must be a captain of the opposing team to dispute');
    }

    $result->dispute($user);

    $this->entityManager->persist($result);
    $this->entityManager->flush();

    return $this->json($this->formatEntityData($result));
}
```

- [ ] **Étape 4 : Lancer les tests — vérifier le passage**

```bash
php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php --filter "testDispute" -v
```

Expected : 2 PASS

- [ ] **Étape 5 : Commit**

```bash
git add src/Controller/GameResultController.php tests/Functional/Controller/GameResultControllerTest.php
git commit -m "feat: add PUT dispute result endpoint"
```

---

## Task 8 : PUT — résolution admin

**Files:**
- Modify: `src/Controller/GameResultController.php`
- Modify: `tests/Functional/Controller/GameResultControllerTest.php`

- [ ] **Étape 1 : Écrire les tests admin-resolve qui échouent**

Ajouter dans `GameResultControllerTest` :

```php
public function testAdminResolveSuccess(): void
{
    $ctx = $this->createMatchContext();

    // Créer un dispute
    $this->client->loginUser($ctx['captain1'], 'api');
    $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 1,
    ]);
    $this->client->loginUser($ctx['captain2'], 'api');
    $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/dispute');

    // Admin tranche
    $this->client->loginUser($ctx['admin'], 'api');
    $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/admin-resolve', [
        'score1' => 2,
        'score2' => 0,
    ]);

    $this->assertResponseStatusCode(200);
    $this->assertEquals(GameResult::STATUS_CONFIRMED, $response['status']);
    $this->assertEquals(2, $response['score1']);
    $this->assertEquals(0, $response['score2']);

    $this->entityManager->clear();
    $game = $this->entityManager->find('App\Entity\Game', $ctx['game']->getId());
    $this->assertEquals(2, $game->getScore1());
    $this->assertEquals(0, $game->getScore2());
    $this->assertEquals(1, $game->getWinner());
    $this->assertEquals('played', $game->getStatus()->getName());
}

public function testAdminResolveRequiresDisputedResult(): void
{
    $ctx = $this->createMatchContext();
    $this->client->loginUser($ctx['admin'], 'api');

    $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/admin-resolve', [
        'score1' => 2,
        'score2' => 0,
    ]);

    $this->assertResponseStatusCode(404);
}

public function testAdminResolveRequiresAdminRole(): void
{
    $ctx = $this->createMatchContext();

    $this->client->loginUser($ctx['captain1'], 'api');
    $this->jsonRequest('POST', '/api/games/' . $ctx['game']->getId() . '/result', [
        'score1' => 2,
        'score2' => 1,
    ]);
    $this->client->loginUser($ctx['captain2'], 'api');
    $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/dispute');

    $response = $this->jsonRequest('PUT', '/api/games/' . $ctx['game']->getId() . '/result/admin-resolve', [
        'score1' => 2,
        'score2' => 0,
    ]);

    $this->assertResponseStatusCode(403);
}
```

- [ ] **Étape 2 : Lancer les tests — vérifier l'échec**

```bash
php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php --filter "testAdminResolve" -v
```

Expected : FAIL (route inexistante).

- [ ] **Étape 3 : Implémenter le endpoint PUT admin-resolve**

Ajouter dans `GameResultController` :

```php
#[Route('/games/{id}/result/admin-resolve', name: 'app_game_result_admin_resolve', methods: ['PUT'], requirements: ['id' => '\d+'])]
public function adminResolveResult(
    int $id,
    Request $request,
    GameResultRepository $resultRepository,
    GameStatusRepository $gameStatusRepository,
    TeamStatRepository $teamStatRepository,
): JsonResponse {
    $this->checkUserRole('ROLE_ADMIN');

    $user = $this->getAuthenticatedUser();
    $game = $this->findEntityOrFail('App\Entity\Game', $id, 'Game');

    $result = $resultRepository->findDisputedByGame($game);
    if (!$result) {
        throw ApiProblemException::notFound('No disputed result found for this game');
    }

    $data = $this->getRequestData($request);

    if (!isset($data['score1']) || !isset($data['score2'])) {
        throw ApiProblemException::badRequest('score1 and score2 are required');
    }

    $score1 = (int) $data['score1'];
    $score2 = (int) $data['score2'];

    $result->setScore1($score1);
    $result->setScore2($score2);
    $result->confirm($user);

    $winner = match(true) {
        $score1 > $score2 => 1,
        $score2 > $score1 => 2,
        default => null,
    };

    $game->setScore1($score1);
    $game->setScore2($score2);
    $game->setWinner($winner);

    $playedStatus = $gameStatusRepository->findOneBy(['name' => 'played']);
    if ($playedStatus) {
        $game->setStatus($playedStatus);
    }

    $this->applyStatsUpdate($teamStatRepository, $game, $score1, $score2, $winner);

    $this->entityManager->persist($result);
    $this->entityManager->persist($game);
    $this->entityManager->flush();

    return $this->json($this->formatEntityData($result));
}
```

- [ ] **Étape 4 : Lancer les tests — vérifier le passage**

```bash
php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php --filter "testAdminResolve" -v
```

Expected : 3 PASS

- [ ] **Étape 5 : Commit**

```bash
git add src/Controller/GameResultController.php tests/Functional/Controller/GameResultControllerTest.php
git commit -m "feat: add PUT admin-resolve result endpoint"
```

---

## Task 9 : Lancer la suite complète + fermer l'issue

**Files:**
- Aucun

- [ ] **Étape 1 : Lancer tous les tests du projet**

```bash
make test
```

Expected : suite verte, aucune régression.

- [ ] **Étape 2 : Fermer l'issue GitHub**

```bash
gh issue close 34 --comment "Implémenté via GameResultController. Endpoints : GET/POST /api/games/{id}/result, PUT confirm/dispute/admin-resolve. Stats mises à jour à la confirmation. Timeout auto-dispute dans #41."
```

- [ ] **Étape 3 : Commit final si nécessaire**

```bash
git status
# Si rien à committer, passer à l'étape suivante
```
