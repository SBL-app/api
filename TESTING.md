# Guide des Tests - API SBL

Ce document décrit la stratégie de tests mise en place pour l'API SBL, basée sur les meilleures pratiques de développement.

## 🏗️ Architecture des Tests

### Structure des Répertoires

```
tests/
├── Unit/              # Tests unitaires
│   └── Entity/        # Tests des entités
├── Integration/       # Tests d'intégration
│   └── Repository/    # Tests des repositories
├── Functional/        # Tests fonctionnels
│   └── Controller/    # Tests des contrôleurs/API
├── bootstrap.php      # Configuration des tests
└── ApiTestCase.php    # Classe de base pour les tests d'API
```

### Types de Tests

#### 1. Tests Unitaires (`tests/Unit/`)
- **Objectif** : Tester des unités de code isolées
- **Scope** : Entités, services, classes utilitaires
- **Caractéristiques** :
  - Rapides à exécuter
  - Pas de dépendances externes
  - Pas d'accès à la base de données
  - Utilisation de mocks pour les dépendances

**Exemple** : Tests des entités (getters, setters, logique métier)

#### 2. Tests d'Intégration (`tests/Integration/`)
- **Objectif** : Tester l'interaction entre composants
- **Scope** : Repositories, services avec base de données
- **Caractéristiques** :
  - Utilisation de la base de données de test
  - Test des requêtes Doctrine
  - Validation des relations entre entités

**Exemple** : Tests des repositories avec la base de données

#### 3. Tests Fonctionnels (`tests/Functional/`)
- **Objectif** : Tester les fonctionnalités complètes
- **Scope** : API endpoints, workflow complets
- **Caractéristiques** :
  - Simulation de requêtes HTTP
  - Test du comportement de bout en bout
  - Validation des réponses JSON

**Exemple** : Tests des contrôleurs via HTTP

## 🛠️ Configuration

### Environnement de Test

Le fichier `.env.test` configure l'environnement spécifiquement pour les tests :

```bash
APP_ENV=test
DATABASE_URL="sqlite:///%kernel.project_dir%/var/test.db"
JWT_PASSPHRASE=test
```

### Base de Données de Test

- **SQLite** : Base de données légère pour les tests
- **Isolation** : Chaque test nettoie la base avant exécution
- **Performance** : Base en mémoire pour des tests rapides

### Configuration PHPUnit

Le fichier `phpunit.xml.dist` organise les tests en suites :

```xml
<testsuites>
    <testsuite name="unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="integration">
        <directory>tests/Integration</directory>
    </testsuite>
    <testsuite name="functional">
        <directory>tests/Functional</directory>
    </testsuite>
</testsuites>
```

## 🚀 Exécution des Tests

### Via Makefile (Recommandé)

```bash
# Tous les tests
make test

# Tests par type
make test-unit
make test-integration
make test-functional

# Avec couverture
make test-coverage
```

### Via Script Personnalisé

```bash
# Tests avec options
./run-tests.sh unit --coverage
./run-tests.sh functional --filter=Division
./run-tests.sh --stop-on-failure
```

### Via PHPUnit Direct

```bash
# Tous les tests
vendor/bin/phpunit

# Tests spécifiques
vendor/bin/phpunit tests/Unit
vendor/bin/phpunit --filter=DivisionTest
```

## 📝 Conventions de Test

### Nomenclature

- **Classes de test** : `[NomClasse]Test.php`
- **Méthodes de test** : `test[ActionTested]()`
- **Données de test** : Noms explicites et réalistes

### Structure des Tests

```php
<?php

class ExampleTest extends TestCase
{
    private $subject;

    protected function setUp(): void
    {
        // Initialisation avant chaque test
        $this->subject = new ExampleClass();
    }

    protected function tearDown(): void
    {
        // Nettoyage après chaque test
        parent::tearDown();
    }

    public function testSomething(): void
    {
        // Arrange (Préparation)
        $input = 'test data';
        
        // Act (Action)
        $result = $this->subject->doSomething($input);
        
        // Assert (Vérification)
        $this->assertEquals('expected', $result);
    }
}
```

### Assertions Courantes

```php
// Égalité
$this->assertEquals($expected, $actual);
$this->assertSame($expected, $actual); // Identité stricte

// Tableaux
$this->assertArrayHasKey('key', $array);
$this->assertCount(2, $array);
$this->assertEmpty($array);

// Objets
$this->assertInstanceOf(ClassName::class, $object);
$this->assertNull($value);

// HTTP (tests fonctionnels)
$this->assertResponseStatusCode(200);
$this->assertJsonResponseStructure(['key'], $response);
```

## 🎯 Stratégies de Test

### Tests d'Entités

```php
public function testEntityValidation(): void
{
    $entity = new Division();
    
    // Test état initial
    $this->assertNull($entity->getId());
    
    // Test setters/getters
    $entity->setName('Test Division');
    $this->assertEquals('Test Division', $entity->getName());
    
    // Test interface fluide
    $result = $entity->setName('New Name');
    $this->assertSame($entity, $result);
}
```

### Tests de Repository

```php
public function testRepositoryFunctions(): void
{
    // Préparation des données
    $entity = new Division();
    $entity->setName('Test');
    $this->entityManager->persist($entity);
    $this->entityManager->flush();
    
    // Test de recherche
    $found = $this->repository->find($entity->getId());
    $this->assertNotNull($found);
    $this->assertEquals('Test', $found->getName());
}
```

### Tests d'API

```php
public function testApiEndpoint(): void
{
    // Préparation des données
    $this->loadTestData();
    
    // Requête API
    $response = $this->jsonRequest('GET', '/api/divisions');
    
    // Vérifications
    $this->assertResponseStatusCode(200);
    $this->assertIsArray($response);
    $this->assertCount(2, $response);
}
```

## 📊 Couverture de Code

### Génération du Rapport

```bash
make test-coverage
```

Le rapport est généré dans `var/coverage/index.html`

### Objectifs de Couverture

- **Entités** : 100% (code simple)
- **Services** : 90%+ (logique métier critique)
- **Contrôleurs** : 80%+ (principaux endpoints)
- **Global** : 85%+

## 🔧 Utilitaires de Test

### Classe `ApiTestCase`

Classe de base pour les tests fonctionnels avec utilitaires :

```php
// Requête JSON simplifiée
$response = $this->jsonRequest('POST', '/api/endpoint', $data);

// Nettoyage de base de données
$this->cleanDatabase();

// Chargement de fixtures
$this->loadFixtures([new DivisionFixture()]);

// Assertions personnalisées
$this->assertResponseStatusCode(201);
$this->assertJsonResponseStructure(['id', 'name'], $response);
```

### Données de Test

```php
// Création d'entités pour tests
private function createTestDivision(): Division
{
    $season = new Season();
    $season->setName('Test Season');
    $this->entityManager->persist($season);
    
    $division = new Division();
    $division->setName('Test Division');
    $division->setSeason($season);
    $this->entityManager->persist($division);
    
    $this->entityManager->flush();
    return $division;
}
```

## 🚨 Bonnes Pratiques

### Isolation des Tests

- Chaque test doit être indépendant
- Nettoyage de la base entre les tests
- Pas d'ordre d'exécution requis

### Performance

- Tests unitaires : < 1ms par test
- Tests d'intégration : < 100ms par test
- Tests fonctionnels : < 500ms par test

### Maintenance

- Tests simples et lisibles
- Un seul concept par test
- Messages d'erreur explicites
- Documentation des cas complexes

### Données de Test

- Données réalistes mais simples
- Pas de dépendances externes
- Fixtures pour les cas complexes
- Nettoyage automatique

## 📈 Métriques et Monitoring

### CI/CD Integration

```yaml
# Exemple GitHub Actions
- name: Run Tests
  run: |
    make ci-test
    
- name: Upload Coverage
  uses: codecov/codecov-action@v1
  with:
    file: coverage.xml
```

### Métriques Importantes

- **Temps d'exécution** : < 30s pour tous les tests
- **Couverture** : 85%+ maintenue
- **Stabilité** : 0% de tests flaky
- **Maintenance** : Tests mis à jour avec le code

## 🔍 Débogage des Tests

### Tests qui Échouent

```bash
# Mode verbeux
vendor/bin/phpunit --verbose

# Arrêt à la première erreur
vendor/bin/phpunit --stop-on-failure

# Test spécifique
vendor/bin/phpunit --filter=testMethodName
```

### Problèmes Courants

1. **Base de données** : Vérifier la configuration de test
2. **Isolation** : S'assurer du nettoyage entre tests
3. **Dépendances** : Vérifier les mocks et stubs
4. **Données** : Valider les fixtures et données de test

## 📚 Ressources

- [Documentation PHPUnit](https://phpunit.de/)
- [Tests Symfony](https://symfony.com/doc/current/testing.html)
- [Doctrine Testing](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/testing.html)

---

Cette documentation sera mise à jour au fur et à mesure de l'évolution de la suite de tests.
