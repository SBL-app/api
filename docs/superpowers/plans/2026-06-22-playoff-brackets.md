# Brackets de playoff — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre de créer des arbres de playoff (simple élimination + petite finale) pour les divisions, seedés sur le classement régulier, et réutilisables pour des tournois détachés des saisons.

**Architecture:** Nouveau conteneur `Bracket` + table de seeds `BracketEntry`, découplés de `Season`. L'entité `Game` existante est étendue (division/week nullables + champs bracket + pointeurs de progression) et réutilisée pour les matchs. Trois services : seeding (depuis le classement), génération de l'arbre, progression des vainqueurs/perdants. Le flux de résultat existant (`GameResult` confirm/dispute/admin-resolve) déclenche la progression.

**Tech Stack:** Symfony 7.3, PHP 8.3, Doctrine ORM, PostgreSQL (prod) / SQLite (tests), PHPUnit.

---

## Référence : faits vérifiés dans le code

- `Game` (`src/Entity/Game.php`) : `division` est `ManyToOne` `JoinColumn(nullable: false)`, `week` est `Column` non-null. Les rendre nullables.
- Le calcul du vainqueur + passage au statut `played` + mise à jour stats se fait dans `GameResultController::confirmResult()` et `::adminResolveResult()` (`src/Controller/GameResultController.php`), méthode privée `applyStatsUpdate()`.
- `applyStatsUpdate()` appelle `$game->getDivision()` et `TeamStatRepository::findByTeamAndDivision()` → **plante si division null**. Doit court-circuiter quand division null.
- `SeasonClosureService::onGamePlayed()` (`src/Service/SeasonClosureService.php:26`) gère déjà `division === null` (return immédiat). Aucune modif nécessaire.
- `GameController::patchGame()` (`src/Controller/GameController.php:254`) a déjà un hook `becomesPlayed` qui appelle `seasonClosureService->onGamePlayed()`. C'est le point d'entrée pour la progression sur forfait (PATCH status→played).
- Statuts de jeu `scheduled` et `played` ne sont **pas** seedés par migration ; ils sont créés à la volée (présents en prod, créés dans chaque test). `GameStatus` a juste `id` + `name`.
- Le classement est trié par `points` décroissants (`DivisionController.php:218`). `TeamStatRepository::findByDivision(Division)` existe.
- Pattern contrôleur : `BaseController` fournit `getRequestData()`, `findEntityOrFail()`, `saveEntity()`, `securedCreateEntity()`, `securedDeleteEntity()`, `checkUserRole('ROLE_ADMIN')`, `getAuthenticatedUser()`. Chaque contrôleur a `#[Route('/api')]` au niveau classe et implémente `formatEntityData()`.
- Pattern test fonctionnel : `ApiTestCase` recrée le schéma SQLite via `SchemaTool` à chaque test (donc **les tests n'exécutent pas les migrations** — la migration prod est non testée par PHPUnit, validée à part). `loginUser($user, 'api')`, `jsonRequest()`, `assertResponseStatusCode()`.

## Structure des fichiers

**À créer :**
- `src/Entity/Bracket.php` — conteneur d'arbre
- `src/Entity/BracketEntry.php` — seed → équipe (snapshot)
- `src/Repository/BracketRepository.php`
- `src/Repository/BracketEntryRepository.php`
- `src/Service/BracketSeedingService.php` — classement division → équipes ordonnées
- `src/Service/BracketGeneratorService.php` — construit Games + câblage (seed order, byes, petite finale)
- `src/Service/BracketProgressionService.php` — propage vainqueur/perdant
- `src/Controller/BracketController.php` — endpoints REST
- `migrations/VersionYYYYMMDDHHMMSS.php` — schéma (division/week nullables, colonnes Game, tables bracket)
- `tests/Unit/Service/BracketGeneratorServiceTest.php`
- `tests/Unit/Service/BracketProgressionServiceTest.php`
- `tests/Functional/Controller/BracketControllerTest.php`

**À modifier :**
- `src/Entity/Game.php` — division/week nullables + nouveaux champs et accesseurs
- `src/Repository/GameRepository.php` — `findByBracket()`
- `src/Controller/GameResultController.php` — guard stats null division + hook progression dans `confirmResult` et `adminResolveResult`
- `src/Controller/GameController.php` — hook progression dans `patchGame` (forfait)

---

## Task 1 : Étendre l'entité Game (division/week nullables + champs bracket)

**Files:**
- Modify: `src/Entity/Game.php`
- Test: `tests/Unit/Entity/GameBracketTest.php` (Create)

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Entity/GameBracketTest.php` :

```php
<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Bracket;
use App\Entity\Game;
use PHPUnit\Framework\TestCase;

class GameBracketTest extends TestCase
{
    public function testBracketFieldsDefaultToNullOrFalse(): void
    {
        $game = new Game();

        $this->assertNull($game->getBracket());
        $this->assertNull($game->getRound());
        $this->assertNull($game->getBracketPosition());
        $this->assertFalse($game->isThirdPlaceMatch());
        $this->assertNull($game->getWinnerToGame());
        $this->assertNull($game->getWinnerToSlot());
        $this->assertNull($game->getLoserToGame());
        $this->assertNull($game->getLoserToSlot());
    }

    public function testBracketProgressionWiringSetters(): void
    {
        $game = new Game();
        $next = new Game();

        $game->setRound(1)
            ->setBracketPosition(0)
            ->setIsThirdPlaceMatch(true)
            ->setWinnerToGame($next)
            ->setWinnerToSlot(1)
            ->setLoserToGame($next)
            ->setLoserToSlot(2);

        $this->assertSame(1, $game->getRound());
        $this->assertSame(0, $game->getBracketPosition());
        $this->assertTrue($game->isThirdPlaceMatch());
        $this->assertSame($next, $game->getWinnerToGame());
        $this->assertSame(1, $game->getWinnerToSlot());
        $this->assertSame($next, $game->getLoserToGame());
        $this->assertSame(2, $game->getLoserToSlot());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Unit/Entity/GameBracketTest.php`
Expected: FAIL ("Call to undefined method App\Entity\Game::getBracket()" ou erreur de classe `Bracket` introuvable — la classe Bracket est créée en Task 2 ; si l'autoload échoue ici, créer d'abord Task 2 puis revenir. Sinon le test échoue sur les méthodes manquantes de Game).

- [ ] **Step 3: Modifier l'entité Game**

Dans `src/Entity/Game.php`, rendre `division` et `week` nullables et ajouter les champs bracket.

Remplacer la déclaration de `$week` :

```php
    #[ORM\Column(nullable: true)]
    private ?int $week = null;
```

Remplacer la déclaration de `$division` :

```php
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Division $division = null;
```

Ajouter, après le champ `$reminderSentAt` :

```php
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Bracket $bracket = null;

    #[ORM\Column(nullable: true)]
    private ?int $round = null;

    #[ORM\Column(nullable: true)]
    private ?int $bracketPosition = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isThirdPlaceMatch = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Game $winnerToGame = null;

    #[ORM\Column(nullable: true)]
    private ?int $winnerToSlot = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Game $loserToGame = null;

    #[ORM\Column(nullable: true)]
    private ?int $loserToSlot = null;
```

Ajouter les accesseurs avant la fin de la classe :

```php
    public function getBracket(): ?Bracket
    {
        return $this->bracket;
    }

    public function setBracket(?Bracket $bracket): static
    {
        $this->bracket = $bracket;

        return $this;
    }

    public function getRound(): ?int
    {
        return $this->round;
    }

    public function setRound(?int $round): static
    {
        $this->round = $round;

        return $this;
    }

    public function getBracketPosition(): ?int
    {
        return $this->bracketPosition;
    }

    public function setBracketPosition(?int $bracketPosition): static
    {
        $this->bracketPosition = $bracketPosition;

        return $this;
    }

    public function isThirdPlaceMatch(): bool
    {
        return $this->isThirdPlaceMatch;
    }

    public function setIsThirdPlaceMatch(bool $isThirdPlaceMatch): static
    {
        $this->isThirdPlaceMatch = $isThirdPlaceMatch;

        return $this;
    }

    public function getWinnerToGame(): ?Game
    {
        return $this->winnerToGame;
    }

    public function setWinnerToGame(?Game $winnerToGame): static
    {
        $this->winnerToGame = $winnerToGame;

        return $this;
    }

    public function getWinnerToSlot(): ?int
    {
        return $this->winnerToSlot;
    }

    public function setWinnerToSlot(?int $winnerToSlot): static
    {
        $this->winnerToSlot = $winnerToSlot;

        return $this;
    }

    public function getLoserToGame(): ?Game
    {
        return $this->loserToGame;
    }

    public function setLoserToGame(?Game $loserToGame): static
    {
        $this->loserToGame = $loserToGame;

        return $this;
    }

    public function getLoserToSlot(): ?int
    {
        return $this->loserToSlot;
    }

    public function setLoserToSlot(?int $loserToSlot): static
    {
        $this->loserToSlot = $loserToSlot;

        return $this;
    }
```

> Note : `use App\Entity\Bracket;` n'est pas nécessaire (même namespace `App\Entity`).

- [ ] **Step 4: Run test to verify it passes** (après avoir créé `Bracket` en Task 2)

Run: `php bin/phpunit tests/Unit/Entity/GameBracketTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Game.php tests/Unit/Entity/GameBracketTest.php
git commit -m "feat(game): champs bracket + division/week nullables"
```

---

## Task 2 : Entité Bracket + repository

**Files:**
- Create: `src/Entity/Bracket.php`
- Create: `src/Repository/BracketRepository.php`
- Test: `tests/Unit/Entity/BracketTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Entity/BracketTest.php` :

```php
<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Bracket;
use App\Entity\Division;
use PHPUnit\Framework\TestCase;

class BracketTest extends TestCase
{
    public function testDefaults(): void
    {
        $bracket = new Bracket();

        $this->assertSame(Bracket::FORMAT_SINGLE_ELIMINATION, $bracket->getFormat());
        $this->assertSame(Bracket::STATUS_DRAFT, $bracket->getStatus());
        $this->assertFalse($bracket->hasThirdPlaceMatch());
        $this->assertNull($bracket->getDivision());
    }

    public function testSetters(): void
    {
        $division = new Division();
        $bracket = new Bracket();

        $bracket->setName('Playoff D1')
            ->setQualifiedCount(8)
            ->setHasThirdPlaceMatch(true)
            ->setDivision($division)
            ->setStatus(Bracket::STATUS_READY);

        $this->assertSame('Playoff D1', $bracket->getName());
        $this->assertSame(8, $bracket->getQualifiedCount());
        $this->assertTrue($bracket->hasThirdPlaceMatch());
        $this->assertSame($division, $bracket->getDivision());
        $this->assertSame(Bracket::STATUS_READY, $bracket->getStatus());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Unit/Entity/BracketTest.php`
Expected: FAIL ("Class App\Entity\Bracket not found")

- [ ] **Step 3: Créer l'entité et le repository**

Create `src/Entity/Bracket.php` :

```php
<?php

namespace App\Entity;

use App\Repository\BracketRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BracketRepository::class)]
class Bracket
{
    public const FORMAT_SINGLE_ELIMINATION = 'single_elimination';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50, options: ['default' => self::FORMAT_SINGLE_ELIMINATION])]
    private string $format = self::FORMAT_SINGLE_ELIMINATION;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $hasThirdPlaceMatch = false;

    #[ORM\Column]
    private ?int $qualifiedCount = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Division $division = null;

    #[ORM\Column(length: 20, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function hasThirdPlaceMatch(): bool
    {
        return $this->hasThirdPlaceMatch;
    }

    public function setHasThirdPlaceMatch(bool $hasThirdPlaceMatch): static
    {
        $this->hasThirdPlaceMatch = $hasThirdPlaceMatch;

        return $this;
    }

    public function getQualifiedCount(): ?int
    {
        return $this->qualifiedCount;
    }

    public function setQualifiedCount(int $qualifiedCount): static
    {
        $this->qualifiedCount = $qualifiedCount;

        return $this;
    }

    public function getDivision(): ?Division
    {
        return $this->division;
    }

    public function setDivision(?Division $division): static
    {
        $this->division = $division;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }
}
```

Create `src/Repository/BracketRepository.php` :

```php
<?php

namespace App\Repository;

use App\Entity\Bracket;
use App\Entity\Division;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bracket>
 */
class BracketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bracket::class);
    }

    public function findOneByDivision(Division $division): ?Bracket
    {
        return $this->findOneBy(['division' => $division]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Unit/Entity/BracketTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Bracket.php src/Repository/BracketRepository.php tests/Unit/Entity/BracketTest.php
git commit -m "feat(bracket): entité Bracket + repository"
```

---

## Task 3 : Entité BracketEntry + repository

**Files:**
- Create: `src/Entity/BracketEntry.php`
- Create: `src/Repository/BracketEntryRepository.php`
- Test: `tests/Unit/Entity/BracketEntryTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Entity/BracketEntryTest.php` :

```php
<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Bracket;
use App\Entity\BracketEntry;
use App\Entity\Team;
use PHPUnit\Framework\TestCase;

class BracketEntryTest extends TestCase
{
    public function testSetters(): void
    {
        $bracket = new Bracket();
        $team = new Team();
        $entry = new BracketEntry();

        $entry->setBracket($bracket)
            ->setSeed(3)
            ->setTeam($team);

        $this->assertSame($bracket, $entry->getBracket());
        $this->assertSame(3, $entry->getSeed());
        $this->assertSame($team, $entry->getTeam());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Unit/Entity/BracketEntryTest.php`
Expected: FAIL ("Class App\Entity\BracketEntry not found")

- [ ] **Step 3: Créer l'entité et le repository**

Create `src/Entity/BracketEntry.php` :

```php
<?php

namespace App\Entity;

use App\Repository\BracketEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BracketEntryRepository::class)]
class BracketEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Bracket $bracket = null;

    #[ORM\Column]
    private ?int $seed = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBracket(): ?Bracket
    {
        return $this->bracket;
    }

    public function setBracket(?Bracket $bracket): static
    {
        $this->bracket = $bracket;

        return $this;
    }

    public function getSeed(): ?int
    {
        return $this->seed;
    }

    public function setSeed(int $seed): static
    {
        $this->seed = $seed;

        return $this;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }
}
```

Create `src/Repository/BracketEntryRepository.php` :

```php
<?php

namespace App\Repository;

use App\Entity\Bracket;
use App\Entity\BracketEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BracketEntry>
 */
class BracketEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BracketEntry::class);
    }

    /**
     * @return BracketEntry[]
     */
    public function findByBracketOrderedBySeed(Bracket $bracket): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.bracket = :bracket')
            ->setParameter('bracket', $bracket)
            ->orderBy('e.seed', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Unit/Entity/BracketEntryTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Entity/BracketEntry.php src/Repository/BracketEntryRepository.php tests/Unit/Entity/BracketEntryTest.php
git commit -m "feat(bracket): entité BracketEntry + repository"
```

---

## Task 4 : GameRepository::findByBracket

**Files:**
- Modify: `src/Repository/GameRepository.php`
- Test: `tests/Integration/Repository/GameRepositoryTest.php` (ajouter une méthode si le fichier existe ; sinon créer)

- [ ] **Step 1: Write the failing test**

Ajouter dans `tests/Integration/Repository/GameRepositoryTest.php` (si la classe existe déjà, ajouter la méthode ; respecter le `setUp` existant qui fournit `$this->entityManager`). Si le fichier n'existe pas, le créer avec la structure d'un test d'intégration (étend `KernelTestCase`, boot kernel, recrée schéma SQLite). Test :

```php
    public function testFindByBracketReturnsGamesOrderedByRoundThenPosition(): void
    {
        $bracket = new \App\Entity\Bracket();
        $bracket->setName('B')->setQualifiedCount(2);
        $this->entityManager->persist($bracket);

        $status = new \App\Entity\GameStatus();
        $status->setName('scheduled');
        $this->entityManager->persist($status);

        $final = new \App\Entity\Game();
        $final->setBracket($bracket)->setRound(2)->setBracketPosition(0)
            ->setStatus($status)->setScore1(0)->setScore2(0);
        $this->entityManager->persist($final);

        $semi = new \App\Entity\Game();
        $semi->setBracket($bracket)->setRound(1)->setBracketPosition(1)
            ->setStatus($status)->setScore1(0)->setScore2(0);
        $this->entityManager->persist($semi);

        $this->entityManager->flush();

        $games = $this->entityManager->getRepository(\App\Entity\Game::class)
            ->findByBracket($bracket);

        $this->assertCount(2, $games);
        $this->assertSame(1, $games[0]->getRound());
        $this->assertSame(2, $games[1]->getRound());
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php bin/phpunit --filter testFindByBracketReturnsGamesOrderedByRoundThenPosition`
Expected: FAIL ("Call to undefined method ...::findByBracket()")

- [ ] **Step 3: Ajouter la méthode au repository**

Dans `src/Repository/GameRepository.php`, ajouter `use App\Entity\Bracket;` en haut, puis :

```php
    /**
     * @return Game[]
     */
    public function findByBracket(Bracket $bracket): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.bracket = :bracket')
            ->setParameter('bracket', $bracket)
            ->orderBy('g.round', 'ASC')
            ->addOrderBy('g.bracketPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php bin/phpunit --filter testFindByBracketReturnsGamesOrderedByRoundThenPosition`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Repository/GameRepository.php tests/Integration/Repository/GameRepositoryTest.php
git commit -m "feat(bracket): GameRepository::findByBracket"
```

---

## Task 5 : BracketSeedingService (classement → équipes seedées)

**Files:**
- Create: `src/Service/BracketSeedingService.php`
- Test: `tests/Unit/Service/BracketSeedingServiceTest.php`

Tri de seeding : `points` décroissants, puis différence `winRounds - looseRounds` décroissante, puis `wins` décroissants. Renvoie au plus `qualifiedCount` équipes.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Service/BracketSeedingServiceTest.php` :

```php
<?php

namespace App\Tests\Unit\Service;

use App\Entity\Division;
use App\Entity\Team;
use App\Entity\TeamStat;
use App\Repository\TeamStatRepository;
use App\Service\BracketSeedingService;
use PHPUnit\Framework\TestCase;

class BracketSeedingServiceTest extends TestCase
{
    private function stat(string $name, int $points, int $win, int $loose, int $wins): TeamStat
    {
        $team = new Team();
        $team->setName($name);
        $stat = new TeamStat();
        $stat->setTeam($team);
        $stat->setPoints($points);
        $stat->setWinRounds($win);
        $stat->setLooseRounds($loose);
        $stat->setWins($wins);
        $stat->setLosses(0);
        $stat->setTies(0);
        return $stat;
    }

    public function testSeedsOrderedByPointsThenRoundDiff(): void
    {
        $division = new Division();
        $stats = [
            $this->stat('B', 6, 10, 8, 2),   // 6 pts, diff +2
            $this->stat('A', 9, 12, 4, 3),   // 9 pts
            $this->stat('C', 6, 12, 2, 2),   // 6 pts, diff +10 -> devant B
        ];

        $repo = $this->createMock(TeamStatRepository::class);
        $repo->method('findByDivision')->willReturn($stats);

        $service = new BracketSeedingService($repo);
        $teams = $service->computeSeeds($division, 3);

        $this->assertSame(['A', 'C', 'B'], array_map(fn (Team $t) => $t->getName(), $teams));
    }

    public function testRespectsQualifiedCount(): void
    {
        $division = new Division();
        $stats = [
            $this->stat('A', 9, 12, 4, 3),
            $this->stat('B', 6, 10, 8, 2),
            $this->stat('C', 3, 5, 9, 1),
        ];
        $repo = $this->createMock(TeamStatRepository::class);
        $repo->method('findByDivision')->willReturn($stats);

        $service = new BracketSeedingService($repo);
        $teams = $service->computeSeeds($division, 2);

        $this->assertCount(2, $teams);
        $this->assertSame(['A', 'B'], array_map(fn (Team $t) => $t->getName(), $teams));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Unit/Service/BracketSeedingServiceTest.php`
Expected: FAIL ("Class App\Service\BracketSeedingService not found")

- [ ] **Step 3: Créer le service**

Create `src/Service/BracketSeedingService.php` :

```php
<?php

namespace App\Service;

use App\Entity\Division;
use App\Entity\Team;
use App\Repository\TeamStatRepository;

class BracketSeedingService
{
    public function __construct(
        private TeamStatRepository $teamStatRepository,
    ) {
    }

    /**
     * Calcule l'ordre de seeding d'une division depuis le classement régulier.
     *
     * @return Team[] équipes ordonnées du seed 1 au seed N (au plus $qualifiedCount)
     */
    public function computeSeeds(Division $division, int $qualifiedCount): array
    {
        $stats = $this->teamStatRepository->findByDivision($division);

        usort($stats, function ($a, $b) {
            if ($a->getPoints() !== $b->getPoints()) {
                return $b->getPoints() - $a->getPoints();
            }
            $diffA = ($a->getWinRounds() ?? 0) - ($a->getLooseRounds() ?? 0);
            $diffB = ($b->getWinRounds() ?? 0) - ($b->getLooseRounds() ?? 0);
            if ($diffA !== $diffB) {
                return $diffB - $diffA;
            }
            return ($b->getWins() ?? 0) - ($a->getWins() ?? 0);
        });

        $teams = array_map(fn ($stat) => $stat->getTeam(), $stats);

        return array_slice($teams, 0, $qualifiedCount);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Unit/Service/BracketSeedingServiceTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Service/BracketSeedingService.php tests/Unit/Service/BracketSeedingServiceTest.php
git commit -m "feat(bracket): BracketSeedingService depuis le classement"
```

---

## Task 6 : BracketGeneratorService (construction de l'arbre)

**Files:**
- Create: `src/Service/BracketGeneratorService.php`
- Test: `tests/Unit/Service/BracketGeneratorServiceTest.php`

Algorithme :
1. N = nombre de `BracketEntry` (ordonnés par seed). Exiger N ≥ 2.
2. `size` = plus petite puissance de 2 ≥ N. `rounds` = log2(size).
3. `seedOrder(size)` = ordre standard des seeds par position (récursif : `[1]` → à chaque tour, pour chaque s : `s` puis `len*2+1 - s`).
4. Round 1 : pour chaque paire de positions, créer un `Game` (round=1, position=index). team = entry du seed si seed ≤ N, sinon `null` (bye).
5. Rounds 2..rounds : créer les `Game` vides (round r, positions 0..size/2^r - 1).
6. Câbler `winnerToGame`/`winnerToSlot` : game(r, p) → game(r+1, floor(p/2)), slot = (p pair ? 1 : 2).
7. Si `hasThirdPlaceMatch` et rounds ≥ 2 : créer un `Game` 3e place (round = rounds, `isThirdPlaceMatch=true`, position = 1), et câbler `loserToGame`/`loserToSlot` des deux demi-finales (round = rounds-1) vers lui (slots 1 et 2). La finale garde position 0 au round `rounds`.
8. Byes : pour chaque game round 1 avec exactement une équipe, résoudre immédiatement (winner = côté présent, scores 0-0, statut `played`) et propager l'équipe au game suivant via le slot câblé.
9. `bracket.status` = `ready`.

Le service ne flpush pas lui-même les Games un par un pour la propagation ; il construit en mémoire, persiste tout, puis flush une fois. Pour les byes, il appelle une méthode interne de propagation simple (pas le ProgressionService, pour éviter une dépendance circulaire).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Service/BracketGeneratorServiceTest.php`. On teste l'algorithme pur via une méthode statique `seedOrder()` et la structure générée avec un EntityManager mocké qui collecte les `persist()`.

```php
<?php

namespace App\Tests\Unit\Service;

use App\Entity\Bracket;
use App\Entity\BracketEntry;
use App\Entity\Game;
use App\Entity\GameStatus;
use App\Entity\Team;
use App\Repository\BracketEntryRepository;
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

    private function makeBracket(int $n, bool $thirdPlace): Bracket
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
        // attache les entries via un repo mocké appelé par le service
        $bracket->__testEntries = $entries;

        return $bracket;
    }

    public function testGenerateFourTeamsNoByes(): void
    {
        $persisted = [];
        $service = $this->makeService($persisted);
        $bracket = $this->makeBracket(4, false);

        $service->generateFromEntries($bracket, $bracket->__testEntries);

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
        $bracket = $this->makeBracket(4, true);

        $service->generateFromEntries($bracket, $bracket->__testEntries);

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
        $bracket = $this->makeBracket(6, false);

        $service->generateFromEntries($bracket, $bracket->__testEntries);

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
```

> Note : la propriété dynamique `__testEntries` est un raccourci de test ; le service expose `generateFromEntries(Bracket, BracketEntry[])` pour rester testable sans base. La méthode publique `generate(Bracket)` (Task 8) chargera les entries via le repository puis appellera `generateFromEntries`.

- [ ] **Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Unit/Service/BracketGeneratorServiceTest.php`
Expected: FAIL ("Class App\Service\BracketGeneratorService not found")

- [ ] **Step 3: Créer le service**

Create `src/Service/BracketGeneratorService.php` :

```php
<?php

namespace App\Service;

use App\Entity\Bracket;
use App\Entity\BracketEntry;
use App\Entity\Game;
use App\Exception\ApiProblemException;
use App\Repository\BracketEntryRepository;
use App\Repository\GameStatusRepository;
use Doctrine\ORM\EntityManagerInterface;

class BracketGeneratorService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameStatusRepository $gameStatusRepository,
    ) {
    }

    /**
     * Charge les entries et génère l'arbre. Point d'entrée appelé par le contrôleur.
     */
    public function generate(Bracket $bracket, BracketEntryRepository $entryRepository): void
    {
        $entries = $entryRepository->findByBracketOrderedBySeed($bracket);
        $this->generateFromEntries($bracket, $entries);
    }

    /**
     * Ordre standard des seeds par position pour une taille puissance de 2.
     *
     * @return int[]
     */
    public static function seedOrder(int $size): array
    {
        $seeds = [1];
        while (count($seeds) < $size) {
            $length = count($seeds) * 2 + 1;
            $next = [];
            foreach ($seeds as $s) {
                $next[] = $s;
                $next[] = $length - $s;
            }
            $seeds = $next;
        }

        return $seeds;
    }

    /**
     * @param BracketEntry[] $entries ordonnés par seed croissant
     */
    public function generateFromEntries(Bracket $bracket, array $entries): void
    {
        $n = count($entries);
        if ($n < 2) {
            throw ApiProblemException::badRequest('A bracket needs at least 2 entries');
        }

        // seed (1-based) -> Team
        $teamBySeed = [];
        foreach ($entries as $entry) {
            $teamBySeed[$entry->getSeed()] = $entry->getTeam();
        }

        $size = 1;
        while ($size < $n) {
            $size *= 2;
        }
        $rounds = (int) log($size, 2);

        $scheduledStatus = $this->gameStatusRepository->findOneBy(['name' => 'scheduled']);
        if (!$scheduledStatus) {
            throw ApiProblemException::badRequest('Game status "scheduled" not found in database');
        }

        // games[round][position] = Game
        $games = [];

        // Round 1
        $order = self::seedOrder($size);
        $matchCountRound1 = $size / 2;
        for ($p = 0; $p < $matchCountRound1; $p++) {
            $seedA = $order[$p * 2];
            $seedB = $order[$p * 2 + 1];

            $game = new Game();
            $game->setBracket($bracket);
            $game->setRound(1);
            $game->setBracketPosition($p);
            $game->setStatus($scheduledStatus);
            $game->setScore1(0);
            $game->setScore2(0);
            $game->setTeam1($seedA <= $n ? $teamBySeed[$seedA] : null);
            $game->setTeam2($seedB <= $n ? $teamBySeed[$seedB] : null);

            $this->entityManager->persist($game);
            $games[1][$p] = $game;
        }

        // Rounds 2..rounds (vides). La finale est round=rounds, position 0.
        for ($r = 2; $r <= $rounds; $r++) {
            $matchCount = $size / (2 ** $r);
            for ($p = 0; $p < $matchCount; $p++) {
                $game = new Game();
                $game->setBracket($bracket);
                $game->setRound($r);
                $game->setBracketPosition($p);
                $game->setStatus($scheduledStatus);
                $game->setScore1(0);
                $game->setScore2(0);
                $this->entityManager->persist($game);
                $games[$r][$p] = $game;
            }
        }

        // Câblage winnerTo
        for ($r = 1; $r < $rounds; $r++) {
            foreach ($games[$r] as $p => $game) {
                $nextGame = $games[$r + 1][intdiv($p, 2)];
                $game->setWinnerToGame($nextGame);
                $game->setWinnerToSlot($p % 2 === 0 ? 1 : 2);
            }
        }

        // Petite finale
        if ($bracket->hasThirdPlaceMatch() && $rounds >= 2) {
            $thirdPlace = new Game();
            $thirdPlace->setBracket($bracket);
            $thirdPlace->setRound($rounds);
            $thirdPlace->setBracketPosition(1);
            $thirdPlace->setIsThirdPlaceMatch(true);
            $thirdPlace->setStatus($scheduledStatus);
            $thirdPlace->setScore1(0);
            $thirdPlace->setScore2(0);
            $this->entityManager->persist($thirdPlace);

            $semis = $games[$rounds - 1];
            $slot = 1;
            foreach ($semis as $semi) {
                $semi->setLoserToGame($thirdPlace);
                $semi->setLoserToSlot($slot);
                $slot++;
            }
        }

        // Byes : round 1 avec une seule équipe -> résolu et propagé
        foreach ($games[1] as $game) {
            $hasTeam1 = $game->getTeam1() !== null;
            $hasTeam2 = $game->getTeam2() !== null;
            if ($hasTeam1 === $hasTeam2) {
                continue; // deux équipes (match réel) ou aucune (impossible ici)
            }

            $winnerSlot = $hasTeam1 ? 1 : 2;
            $winnerTeam = $hasTeam1 ? $game->getTeam1() : $game->getTeam2();

            $playedStatus = $this->gameStatusRepository->findOneBy(['name' => 'played']);
            if (!$playedStatus) {
                throw ApiProblemException::badRequest('Game status "played" not found in database');
            }
            $game->setWinner($winnerSlot);
            $game->setStatus($playedStatus);

            $nextGame = $game->getWinnerToGame();
            if ($nextGame !== null) {
                if ($game->getWinnerToSlot() === 1) {
                    $nextGame->setTeam1($winnerTeam);
                } else {
                    $nextGame->setTeam2($winnerTeam);
                }
            }
        }

        $bracket->setStatus(Bracket::STATUS_READY);
        $this->entityManager->persist($bracket);
        $this->entityManager->flush();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Unit/Service/BracketGeneratorServiceTest.php`
Expected: PASS (4 méthodes vertes ; `seedOrder(8)` = `[1,8,4,5,2,7,3,6]`)

- [ ] **Step 5: Commit**

```bash
git add src/Service/BracketGeneratorService.php tests/Unit/Service/BracketGeneratorServiceTest.php
git commit -m "feat(bracket): BracketGeneratorService (simple élim + byes + petite finale)"
```

---

## Task 7 : BracketProgressionService (propagation vainqueur/perdant)

**Files:**
- Create: `src/Service/BracketProgressionService.php`
- Test: `tests/Unit/Service/BracketProgressionServiceTest.php`

Règle : à partir d'un `Game` de bracket joué (`winner` = 1 ou 2), placer l'équipe gagnante dans `winnerToGame[winnerToSlot]`, et la perdante dans `loserToGame[loserToSlot]` si défini. Si le game est la finale (`winnerToGame === null` et `!isThirdPlaceMatch`), passer le bracket à `completed`. Si le bracket est `ready`, passer à `in_progress`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Service/BracketProgressionServiceTest.php` :

```php
<?php

namespace App\Tests\Unit\Service;

use App\Entity\Bracket;
use App\Entity\Game;
use App\Entity\Team;
use App\Service\BracketProgressionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BracketProgressionServiceTest extends TestCase
{
    private function em(): EntityManagerInterface
    {
        return $this->createMock(EntityManagerInterface::class);
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

        $service = new BracketProgressionService($this->em());
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

        $service = new BracketProgressionService($this->em());
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

        $service = new BracketProgressionService($this->em());
        $service->advance($thirdPlace);

        $this->assertSame(Bracket::STATUS_IN_PROGRESS, $bracket->getStatus());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Unit/Service/BracketProgressionServiceTest.php`
Expected: FAIL ("Class App\Service\BracketProgressionService not found")

- [ ] **Step 3: Créer le service**

Create `src/Service/BracketProgressionService.php` :

```php
<?php

namespace App\Service;

use App\Entity\Bracket;
use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;

class BracketProgressionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Propage le résultat d'un match de bracket joué vers les matchs suivants.
     */
    public function advance(Game $game): void
    {
        $bracket = $game->getBracket();
        if ($bracket === null) {
            return;
        }

        $winner = $game->getWinner();
        $winnerTeam = match ($winner) {
            1 => $game->getTeam1(),
            2 => $game->getTeam2(),
            default => null,
        };
        $loserTeam = match ($winner) {
            1 => $game->getTeam2(),
            2 => $game->getTeam1(),
            default => null,
        };

        if ($bracket->getStatus() === Bracket::STATUS_READY) {
            $bracket->setStatus(Bracket::STATUS_IN_PROGRESS);
        }

        if ($winnerTeam !== null && $game->getWinnerToGame() !== null) {
            $this->placeTeam($game->getWinnerToGame(), $game->getWinnerToSlot(), $winnerTeam);
        }

        if ($loserTeam !== null && $game->getLoserToGame() !== null) {
            $this->placeTeam($game->getLoserToGame(), $game->getLoserToSlot(), $loserTeam);
        }

        // Finale jouée (pas de match suivant et pas la petite finale) -> bracket terminé
        if ($game->getWinnerToGame() === null && !$game->isThirdPlaceMatch()) {
            $bracket->setStatus(Bracket::STATUS_COMPLETED);
        }

        $this->entityManager->persist($bracket);
        $this->entityManager->flush();
    }

    private function placeTeam(Game $target, ?int $slot, $team): void
    {
        if ($slot === 1) {
            $target->setTeam1($team);
        } elseif ($slot === 2) {
            $target->setTeam2($team);
        }
        $this->entityManager->persist($target);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Unit/Service/BracketProgressionServiceTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Service/BracketProgressionService.php tests/Unit/Service/BracketProgressionServiceTest.php
git commit -m "feat(bracket): BracketProgressionService"
```

---

## Task 8 : Brancher la progression dans le flux de résultat

**Files:**
- Modify: `src/Controller/GameResultController.php`
- Modify: `src/Controller/GameController.php`
- Test: couvert par le test fonctionnel de Task 10 (progression de bout en bout)

- [ ] **Step 1: Guard stats null division**

Dans `src/Controller/GameResultController.php`, au début de `applyStatsUpdate()` (juste après l'ouverture de la méthode), court-circuiter les matchs de bracket (division null) :

```php
        $division = $game->getDivision();
        if ($division === null) {
            // Match de bracket : aucune stat de division à mettre à jour
            return;
        }
        $team1 = $game->getTeam1();
        $team2 = $game->getTeam2();
```

(remplace les 3 premières lignes existantes `$division = ...; $team1 = ...; $team2 = ...;`).

- [ ] **Step 2: Injecter et appeler le ProgressionService dans confirmResult**

Dans la signature de `confirmResult()`, ajouter le paramètre `BracketProgressionService $bracketProgressionService` (Symfony autowire les arguments d'action). Ajouter l'import `use App\Service\BracketProgressionService;` en haut.

Après le bloc `try { $seasonClosureService->onGamePlayed($game); } catch (...) {...}` existant, ajouter :

```php
        if ($game->getBracket() !== null) {
            try {
                $bracketProgressionService->advance($game);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to advance bracket after result confirmation', [
                    'game_id' => $game->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
```

- [ ] **Step 3: Idem dans adminResolveResult**

Ajouter `BracketProgressionService $bracketProgressionService` à la signature de `adminResolveResult()` et le même bloc `if ($game->getBracket() !== null) { ... }` après le `try/catch` de `onGamePlayed`.

- [ ] **Step 4: Brancher la progression sur le forfait (GameController::patchGame)**

Dans `src/Controller/GameController.php`, ajouter l'import `use App\Service\BracketProgressionService;`. Ajouter `BracketProgressionService $bracketProgressionService` à la signature de `patchGame()`. Dans le bloc existant `if ($becomesPlayed && $game->getStatus()?->getName() === 'played') { ... }`, après le `try/catch` de `onGamePlayed`, ajouter :

```php
            if ($game->getBracket() !== null) {
                try {
                    $bracketProgressionService->advance($game);
                } catch (\Throwable $e) {
                    $this->logger->error('Failed to advance bracket after game patch', [
                        'game_id' => $game->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
```

- [ ] **Step 5: Run existing tests to verify no regression**

Run: `php bin/phpunit tests/Functional/Controller/GameResultControllerTest.php`
Expected: PASS (le guard null division ne change rien aux matchs de division ; les signatures enrichies sont autowirées)

- [ ] **Step 6: Commit**

```bash
git add src/Controller/GameResultController.php src/Controller/GameController.php
git commit -m "feat(bracket): déclenche la progression depuis le flux de résultat et le forfait"
```

---

## Task 9 : BracketController (endpoints REST)

**Files:**
- Create: `src/Controller/BracketController.php`
- Test: couvert par Task 10

Endpoints :
- `POST /api/brackets` — créer (admin). Body : `name`, `qualified_count`, `has_third_place_match` (opt), `division_id` (opt), `format` (opt).
- `GET /api/brackets/{id}` — arbre complet.
- `GET /api/divisions/{id}/bracket` — bracket d'une division.
- `POST /api/brackets/{id}/seed` — auto-seed depuis le classement de la division liée (admin). Crée les `BracketEntry` (efface les existants).
- `PUT /api/brackets/{id}/entries` — seeds manuels (admin). Body : `entries: [{seed, team_id}, ...]`.
- `POST /api/brackets/{id}/generate` — construit l'arbre (admin).
- `DELETE /api/brackets/{id}` — supprime bracket + games + entries (admin).

- [ ] **Step 1: Créer le contrôleur**

Create `src/Controller/BracketController.php` :

```php
<?php

namespace App\Controller;

use App\Entity\Bracket;
use App\Entity\BracketEntry;
use App\Entity\Game;
use App\Exception\ApiProblemException;
use App\Repository\BracketEntryRepository;
use App\Repository\BracketRepository;
use App\Repository\GameRepository;
use App\Service\BracketGeneratorService;
use App\Service\BracketSeedingService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class BracketController extends BaseController
{
    protected function formatEntityData($entity): array
    {
        if (!$entity instanceof Bracket) {
            throw new \InvalidArgumentException('Entity must be an instance of Bracket');
        }

        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'format' => $entity->getFormat(),
            'has_third_place_match' => $entity->hasThirdPlaceMatch(),
            'qualified_count' => $entity->getQualifiedCount(),
            'division_id' => $entity->getDivision()?->getId(),
            'status' => $entity->getStatus(),
        ];
    }

    private function formatGame(Game $game): array
    {
        return [
            'id' => $game->getId(),
            'round' => $game->getRound(),
            'position' => $game->getBracketPosition(),
            'is_third_place_match' => $game->isThirdPlaceMatch(),
            'team1_id' => $game->getTeam1()?->getId(),
            'team1_name' => $game->getTeam1()?->getName(),
            'team2_id' => $game->getTeam2()?->getId(),
            'team2_name' => $game->getTeam2()?->getName(),
            'score1' => $game->getScore1(),
            'score2' => $game->getScore2(),
            'winner' => $game->getWinner(),
            'status' => $game->getStatus()?->getName(),
            'date' => $game->getDate()?->format('Y-m-d H:i:s'),
            'winner_to_game_id' => $game->getWinnerToGame()?->getId(),
            'winner_to_slot' => $game->getWinnerToSlot(),
            'loser_to_game_id' => $game->getLoserToGame()?->getId(),
            'loser_to_slot' => $game->getLoserToSlot(),
        ];
    }

    private function formatTree(Bracket $bracket, GameRepository $gameRepository, BracketEntryRepository $entryRepository): array
    {
        $data = $this->formatEntityData($bracket);

        $entries = $entryRepository->findByBracketOrderedBySeed($bracket);
        $data['seeds'] = array_map(fn (BracketEntry $e) => [
            'seed' => $e->getSeed(),
            'team_id' => $e->getTeam()?->getId(),
            'team_name' => $e->getTeam()?->getName(),
        ], $entries);

        $games = $gameRepository->findByBracket($bracket);
        $rounds = [];
        foreach ($games as $game) {
            $rounds[$game->getRound()][] = $this->formatGame($game);
        }
        ksort($rounds);
        $data['rounds'] = array_map(fn ($round, $matches) => [
            'round' => $round,
            'matches' => $matches,
        ], array_keys($rounds), $rounds);

        return $data;
    }

    #[Route('/brackets', name: 'app_bracket_create', methods: ['POST'])]
    public function createBracket(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);

        if (!isset($data['name'])) {
            return $this->missingParameterError('name');
        }
        if (!isset($data['qualified_count'])) {
            return $this->missingParameterError('qualified_count');
        }
        if ((int) $data['qualified_count'] < 2) {
            throw ApiProblemException::badRequest('qualified_count must be at least 2');
        }

        $bracket = new Bracket();
        $bracket->setName($data['name']);
        $bracket->setQualifiedCount((int) $data['qualified_count']);
        $bracket->setHasThirdPlaceMatch((bool) ($data['has_third_place_match'] ?? false));
        if (isset($data['format'])) {
            $bracket->setFormat($data['format']);
        }
        if (isset($data['division_id'])) {
            $division = $this->findEntityOrFail('App\Entity\Division', $data['division_id'], 'Division');
            $bracket->setDivision($division);
        }

        return $this->securedCreateEntity($bracket, $request);
    }

    #[Route('/brackets/{id}', name: 'app_bracket_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getBracket(int $id, GameRepository $gameRepository, BracketEntryRepository $entryRepository): JsonResponse
    {
        $bracket = $this->findEntityOrFail('App\Entity\Bracket', $id, 'Bracket');

        return $this->json($this->formatTree($bracket, $gameRepository, $entryRepository));
    }

    #[Route('/divisions/{id}/bracket', name: 'app_division_bracket', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getDivisionBracket(int $id, BracketRepository $bracketRepository, GameRepository $gameRepository, BracketEntryRepository $entryRepository): JsonResponse
    {
        $division = $this->findEntityOrFail('App\Entity\Division', $id, 'Division');
        $bracket = $bracketRepository->findOneByDivision($division);
        if (!$bracket) {
            throw ApiProblemException::notFound('No bracket for this division');
        }

        return $this->json($this->formatTree($bracket, $gameRepository, $entryRepository));
    }

    #[Route('/brackets/{id}/seed', name: 'app_bracket_seed', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function seedBracket(int $id, BracketSeedingService $seedingService, BracketEntryRepository $entryRepository): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        $bracket = $this->findEntityOrFail('App\Entity\Bracket', $id, 'Bracket');
        $division = $bracket->getDivision();
        if ($division === null) {
            throw ApiProblemException::badRequest('This bracket is not linked to a division; use PUT /entries for manual seeding');
        }

        foreach ($entryRepository->findBy(['bracket' => $bracket]) as $existing) {
            $this->entityManager->remove($existing);
        }
        $this->entityManager->flush();

        $teams = $seedingService->computeSeeds($division, $bracket->getQualifiedCount());
        if (count($teams) < 2) {
            throw ApiProblemException::badRequest('Not enough ranked teams in the division to seed');
        }

        $seed = 1;
        foreach ($teams as $team) {
            $entry = new BracketEntry();
            $entry->setBracket($bracket);
            $entry->setSeed($seed);
            $entry->setTeam($team);
            $this->entityManager->persist($entry);
            $seed++;
        }
        $this->entityManager->flush();

        return $this->json(['seeded' => count($teams)]);
    }

    #[Route('/brackets/{id}/entries', name: 'app_bracket_entries', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function setEntries(int $id, Request $request, BracketEntryRepository $entryRepository): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        $bracket = $this->findEntityOrFail('App\Entity\Bracket', $id, 'Bracket');
        $data = $this->getRequestData($request);

        if (!isset($data['entries']) || !is_array($data['entries']) || count($data['entries']) < 2) {
            throw ApiProblemException::badRequest('entries must be an array of at least 2 {seed, team_id}');
        }

        foreach ($entryRepository->findBy(['bracket' => $bracket]) as $existing) {
            $this->entityManager->remove($existing);
        }
        $this->entityManager->flush();

        foreach ($data['entries'] as $row) {
            if (!isset($row['seed'], $row['team_id'])) {
                throw ApiProblemException::badRequest('Each entry needs seed and team_id');
            }
            $team = $this->findEntityOrFail('App\Entity\Team', $row['team_id'], 'Team');
            $entry = new BracketEntry();
            $entry->setBracket($bracket);
            $entry->setSeed((int) $row['seed']);
            $entry->setTeam($team);
            $this->entityManager->persist($entry);
        }
        $this->entityManager->flush();

        return $this->json(['entries' => count($data['entries'])]);
    }

    #[Route('/brackets/{id}/generate', name: 'app_bracket_generate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function generateBracket(int $id, BracketGeneratorService $generatorService, BracketEntryRepository $entryRepository, GameRepository $gameRepository): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        $bracket = $this->findEntityOrFail('App\Entity\Bracket', $id, 'Bracket');

        if (!empty($gameRepository->findByBracket($bracket))) {
            throw ApiProblemException::conflict('Bracket already generated');
        }

        $generatorService->generate($bracket, $entryRepository);

        return $this->json($this->formatTree($bracket, $gameRepository, $entryRepository), 201);
    }

    #[Route('/brackets/{id}', name: 'app_bracket_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteBracket(int $id, GameRepository $gameRepository, BracketEntryRepository $entryRepository): JsonResponse
    {
        $this->checkUserRole('ROLE_ADMIN');

        $bracket = $this->findEntityOrFail('App\Entity\Bracket', $id, 'Bracket');

        foreach ($gameRepository->findByBracket($bracket) as $game) {
            $this->entityManager->remove($game);
        }
        $this->entityManager->flush();

        foreach ($entryRepository->findBy(['bracket' => $bracket]) as $entry) {
            $this->entityManager->remove($entry);
        }
        $this->entityManager->flush();

        return $this->securedDeleteEntity($bracket, 'Bracket');
    }
}
```

> Note progression/suppression : les `Game` ont des self-FK `winnerToGame`/`loserToGame` avec `onDelete: 'SET NULL'`, donc supprimer les games d'un bracket ne provoque pas d'erreur de contrainte. On supprime games puis entries puis bracket.

- [ ] **Step 2: Vérifier la syntaxe**

Run: `php -l src/Controller/BracketController.php`
Expected: "No syntax errors detected"

- [ ] **Step 3: Commit**

```bash
git add src/Controller/BracketController.php
git commit -m "feat(bracket): BracketController (CRUD + seed + generate)"
```

---

## Task 10 : Test fonctionnel de bout en bout

**Files:**
- Create: `tests/Functional/Controller/BracketControllerTest.php`

Couvre : création, auto-seed depuis classement, génération (4 équipes + petite finale), progression d'une demi-finale via le flux de résultat, et scénario détaché (entries manuelles).

- [ ] **Step 1: Write the test**

Create `tests/Functional/Controller/BracketControllerTest.php` :

```php
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
        $this->client->loginUser($ctx['admin'], 'api');

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
        $this->client->loginUser($ctx['admin'], 'api');

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

        $this->client->loginUser($cap1, 'api');
        $this->jsonRequest('POST', "/api/games/{$semiId}/result", ['score1' => 4, 'score2' => 0]);
        $this->assertResponseStatusCode(201);

        $this->client->loginUser($cap2, 'api');
        $this->jsonRequest('PUT', "/api/games/{$semiId}/result/confirm");
        $this->assertResponseStatusCode(200);

        // Recharger l'arbre : le vainqueur de la demi doit être dans la finale (round 2 position 0)
        $this->client->loginUser($ctx['admin'], 'api');
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
        $this->client->loginUser($ctx['admin'], 'api');

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
}
```

- [ ] **Step 2: Run the test**

Run: `php bin/phpunit tests/Functional/Controller/BracketControllerTest.php`
Expected: PASS (4 tests). Si `testProgressionAdvancesWinner` échoue sur le statut `played` introuvable, vérifier que le contexte crée bien le statut `played` (il le fait).

- [ ] **Step 3: Run the full suite**

Run: `php bin/phpunit`
Expected: PASS (aucune régression)

- [ ] **Step 4: Commit**

```bash
git add tests/Functional/Controller/BracketControllerTest.php
git commit -m "test(bracket): flux fonctionnel de bout en bout"
```

---

## Task 11 : Migration de schéma (prod PostgreSQL)

**Files:**
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (timestamp réel à la création)

Les tests utilisent SQLite via SchemaTool et n'exécutent pas cette migration. Elle est validée par `doctrine:schema:validate` (cohérence mapping ↔ schéma) et relue à la main.

- [ ] **Step 1: Générer la migration depuis le diff**

Run: `php bin/console doctrine:migrations:diff`
Cela génère un fichier `migrations/VersionXXXX.php` reflétant : `game.division_id` nullable, `game.week` nullable, nouvelles colonnes `game` (`bracket_id`, `round`, `bracket_position`, `is_third_place_match`, `winner_to_game_id`, `winner_to_slot`, `loser_to_game_id`, `loser_to_slot`), tables `bracket` et `bracket_entry`, FKs associées.

- [ ] **Step 2: Relire la migration générée**

Ouvrir le fichier généré. Vérifier la présence de :
- `ALTER TABLE game ALTER division_id DROP NOT NULL;`
- `ALTER TABLE game ALTER week DROP NOT NULL;`
- `CREATE TABLE bracket (...)` et `CREATE TABLE bracket_entry (...)`
- les `ADD CONSTRAINT ... FOREIGN KEY` pour `bracket_id`, `winner_to_game_id`, `loser_to_game_id`, `bracket.division_id`, `bracket_entry.bracket_id`, `bracket_entry.team_id`
- une méthode `down()` cohérente

Si `doctrine:migrations:diff` n'est pas disponible/configuré, créer la migration à la main sur le modèle de `migrations/Version20260317100000.php` avec les `addSql()` ci-dessus (syntaxe PostgreSQL).

- [ ] **Step 3: Valider le mapping**

Run: `php bin/console doctrine:schema:validate --skip-sync`
Expected: "The mapping files are correct." (la partie mapping). Un éventuel avertissement "database not in sync" est normal tant que la migration n'est pas appliquée.

- [ ] **Step 4: Commit**

```bash
git add migrations/
git commit -m "feat(bracket): migration schéma (game nullable + tables bracket)"
```

---

## Task 12 : Mettre à jour la documentation

**Files:**
- Modify: `CLAUDE.md` (section Features)

- [ ] **Step 1: Ajouter une section Features**

Dans `CLAUDE.md`, ajouter après la feature 16 (Logging) une entrée :

```markdown
### 17. Playoff Brackets (`BracketController`)
- `POST /api/brackets` — créer un bracket (admin) : name, qualified_count, has_third_place_match?, division_id?, format?
- `GET /api/brackets/{id}` — arbre complet (seeds + rounds + matchs)
- `GET /api/divisions/{id}/bracket` — bracket d'une division
- `POST /api/brackets/{id}/seed` — auto-seed depuis le classement de la division (admin)
- `PUT /api/brackets/{id}/entries` — seeds manuels pour tournoi détaché (admin)
- `POST /api/brackets/{id}/generate` — génère l'arbre (simple élim + byes + petite finale) (admin)
- `DELETE /api/brackets/{id}` — supprime bracket, games et entries (admin)
- Réutilise `Game` (division/week nullables + champs bracket) et tout le flux `GameResult`
- Progression automatique : à la confirmation d'un résultat de match de bracket, le vainqueur (et le perdant pour la petite finale) avance via `BracketProgressionService`
- Tournoi détaché : bracket sans division + entries manuelles
```

Mettre à jour la section "Entity Relationships" pour ajouter :

```
Bracket → BracketEntry → Team
Bracket → Division (nullable)
Game → Bracket (nullable) ; Game.division nullable
```

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: documenter les playoff brackets"
```

---

## Self-Review (effectué à l'écriture du plan)

**Couverture de la spec :**
- Format simple élim + petite finale → Task 6 (génération) + Task 7 (progression perdant→petite finale). ✓
- Match unique réutilisant Game → Task 1. ✓
- Seeding depuis classement, figé → Task 5 + Task 9 (`/seed`). ✓
- Top N configurable → `qualifiedCount` (Task 2, Task 9). ✓
- 1 bracket ↔ 1 division → `Bracket.division` + `findOneByDivision` (Task 2). ✓
- Tournoi détaché → division null + `/entries` manuels (Task 9, Task 10). ✓
- Règles de victoire identiques (réutilisation flux résultat) → Task 8. ✓
- Byes pour non-puissances de 2 → Task 6 + test 6-équipes. ✓
- Exclusion stats/clôture régulières → guard null division Task 8 + `SeasonClosureService` déjà null-safe. ✓
- Migration compatible données S3 → Task 11 (colonnes deviennent nullables). ✓
- Tests Unit + Functional → Tasks 1-7, 10. ✓

**Cohérence des types :** signatures de service identiques entre définition et usage (`computeSeeds(Division, int): Team[]`, `generateFromEntries(Bracket, array): void`, `generate(Bracket, BracketEntryRepository): void`, `advance(Game): void`). ✓

**Pas de placeholder :** tout le code est fourni. ✓
