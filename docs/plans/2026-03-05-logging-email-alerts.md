# Logging Email Alerts Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ajouter des alertes email automatiques en production via Monolog quand une erreur ERROR ou CRITICAL se produit, avec déduplication anti-spam.

**Architecture:** Utilisation exclusive de handlers Monolog natifs (`fingers_crossed` → `deduplication` → `native_mailer`) configurés uniquement dans `when@prod`. Aucun code PHP à écrire, tout se passe dans la config YAML et les variables d'environnement.

**Tech Stack:** symfony/monolog-bundle (déjà installé), NativeMailerHandler, Gmail SMTP avec App Password.

---

### Task 1 : Ajouter les variables d'environnement MAILER

**Files:**
- Modify: `.env`
- Modify: `.env.local` (valeurs réelles, non committé)

**Step 1 : Ajouter les variables dans `.env`**

Ouvrir `.env` et ajouter à la fin :

```dotenv
###> mailer ###
MAILER_FROM="SBL Alerts <noreply@sbl.app>"
MAILER_TO=admin@example.com
MAILER_HOST=smtp.gmail.com
MAILER_PORT=587
MAILER_USERNAME=ton-compte@gmail.com
MAILER_PASSWORD=
###< mailer ###
```

**Step 2 : Ajouter les valeurs réelles dans `.env.local`**

Créer ou ouvrir `.env.local` et ajouter :

```dotenv
MAILER_TO=ton-email-perso@gmail.com
MAILER_USERNAME=ton-compte@gmail.com
MAILER_PASSWORD=xxxx-xxxx-xxxx-xxxx  # App Password Gmail (16 caractères)
```

> **Note Gmail App Password :** Aller sur https://myaccount.google.com → Sécurité → Validation en deux étapes → Mots de passe des applications → Créer un mot de passe pour "Autre (nom personnalisé)" → "SBL API".

**Step 3 : Vérifier que `.env.local` est dans `.gitignore`**

```bash
grep ".env.local" .gitignore
```

Résultat attendu : `.env.local` doit apparaître. Si ce n'est pas le cas, l'ajouter.

**Step 4 : Commit**

```bash
git add .env
git commit -m "feat: add MAILER environment variables for email alerts"
```

---

### Task 2 : Configurer les handlers Monolog en production

**Files:**
- Modify: `config/packages/monolog.yaml`

**Step 1 : Lire la configuration actuelle**

```bash
cat config/packages/monolog.yaml
```

Le bloc `when@prod` actuel ressemble à :

```yaml
when@prod:
    monolog:
        handlers:
            main:
                type: rotating_file
                path: "%kernel.logs_dir%/%kernel.environment%.log"
                level: info
                max_files: 14
                channels: ["!deprecation"]
            error:
                type: rotating_file
                path: "%kernel.logs_dir%/error.log"
                level: error
                max_files: 30
            ...
```

**Step 2 : Ajouter les handlers mail dans `when@prod`**

Remplacer le bloc `when@prod` par :

```yaml
when@prod:
    monolog:
        handlers:
            main:
                type: rotating_file
                path: "%kernel.logs_dir%/%kernel.environment%.log"
                level: info
                max_files: 14
                channels: ["!deprecation"]
            error:
                type: rotating_file
                path: "%kernel.logs_dir%/error.log"
                level: error
                max_files: 30
            console:
                type: console
                process_psr_3_messages: false
                channels: ["!event", "!doctrine"]
            deprecation:
                type: stream
                channels: [deprecation]
                path: php://stderr
                formatter: monolog.formatter.json
            # --- Alertes email ---
            mail_buffer:
                type: fingers_crossed
                action_level: error
                handler: mail_dedup
                excluded_http_codes: [404, 405]
                buffer_size: 50
            mail_dedup:
                type: deduplication
                handler: mail_sender
                store: "%kernel.cache_dir%/monolog_dedup.log"
                time: 300
            mail_sender:
                type: native_mailer
                to: "%env(MAILER_TO)%"
                from: "%env(MAILER_FROM)%"
                subject: "[SBL API] Erreur en production"
                level: error
                stream: smtp://%env(MAILER_USERNAME)%:%env(MAILER_PASSWORD)%@%env(MAILER_HOST)%:%env(MAILER_PORT)%
```

**Step 3 : Vider le cache**

```bash
php bin/console cache:clear --env=prod
```

Résultat attendu : `Cache for the "prod" environment (debug=false) was successfully cleared.`

**Step 4 : Commit**

```bash
git add config/packages/monolog.yaml
git commit -m "feat: add email alert handlers in monolog prod config"
```

---

### Task 3 : Tester la configuration localement

**Files:**
- Create (temporaire, à supprimer après test) : `tests/Manual/TestEmailAlertCommand.php`

> Ce test est manuel : on crée une commande Symfony temporaire pour déclencher un log ERROR et vérifier que l'email part.

**Step 1 : Créer une commande de test temporaire**

```bash
php bin/console make:command app:test-email-alert
```

Modifier le fichier généré `src/Command/AppTestEmailAlertCommand.php` :

```php
<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-email-alert')]
class AppTestEmailAlertCommand extends Command
{
    public function __construct(private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Test info — ne déclenchera pas d\'email');
        $this->logger->warning('Test warning — ne déclenchera pas d\'email');
        $this->logger->error('Test error — DOIT déclencher un email', [
            'context' => 'test manuel depuis app:test-email-alert',
        ]);

        $output->writeln('Log ERROR envoyé. Vérifier la boîte mail : ' . $_ENV['MAILER_TO'] ?? '(MAILER_TO non défini)');

        return Command::SUCCESS;
    }
}
```

**Step 2 : Lancer la commande en environnement prod**

```bash
APP_ENV=prod php bin/console app:test-email-alert
```

Résultat attendu : message dans le terminal + email reçu dans la boîte `MAILER_TO`.

**Step 3 : Vérifier les logs**

```bash
tail -n 20 var/log/prod.log
tail -n 20 var/log/error.log
cat var/cache/prod/monolog_dedup.log
```

Le fichier `monolog_dedup.log` doit contenir un hash + timestamp du dernier message envoyé.

**Step 4 : Tester l'anti-spam**

Relancer la commande dans la fenêtre de 300 secondes :

```bash
APP_ENV=prod php bin/console app:test-email-alert
```

Résultat attendu : **pas de second email** reçu (le deduplication handler a bloqué l'envoi).

**Step 5 : Supprimer la commande de test**

```bash
trash-put src/Command/AppTestEmailAlertCommand.php
```

**Step 6 : Commit**

```bash
git add -A
git commit -m "feat: complete email alert logging system for production"
```

---

### Task 4 : Documenter les variables dans `.env`

**Files:**
- Modify: `.env`

**Step 1 : S'assurer que les variables MAILER ont des commentaires explicites**

Le bloc ajouté en Task 1 doit déjà avoir les commentaires. Vérifier :

```bash
grep -A 7 "###> mailer ###" .env
```

**Step 2 : Mettre à jour le README ou CLAUDE.md si nécessaire**

Vérifier si `CLAUDE.md` mentionne les variables d'environnement :

```bash
grep -n "MAILER\|mail" CLAUDE.md
```

Si rien → pas besoin de modification (les variables `.env` sont auto-documentées).

**Step 3 : Commit final**

```bash
git status
# Si des modifications mineures → git add + commit
# Sinon → rien à faire
```

---

## Récapitulatif des fichiers touchés

| Fichier | Action |
|---------|--------|
| `.env` | Ajout variables `MAILER_*` |
| `.env.local` | Valeurs réelles (non committé) |
| `config/packages/monolog.yaml` | Ajout handlers `mail_buffer`, `mail_dedup`, `mail_sender` |
| `src/Command/AppTestEmailAlertCommand.php` | Créé puis supprimé (test manuel) |

## Vérification finale

- [ ] `var/log/error.log` contient les erreurs ERROR+
- [ ] Email reçu lors d'un ERROR en prod
- [ ] Pas de second email dans la fenêtre de 300s (deduplication)
- [ ] `var/cache/prod/monolog_dedup.log` existe après le premier envoi
