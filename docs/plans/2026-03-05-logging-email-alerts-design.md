# Design — Système de logging avec alertes email en production

**Date :** 2026-03-05
**Statut :** Approuvé

## Contexte

L'API Symfony utilise déjà `symfony/monolog-bundle` avec une configuration prod basique (fichiers rotatifs). L'objectif est d'ajouter des alertes email automatiques en cas d'erreur en production, sans installer de nouvelle dépendance PHP.

## Décisions clés

- **Niveau déclencheur :** ERROR et CRITICAL uniquement
- **SMTP :** Gmail via `NativeMailerHandler` (pas de `symfony/mailer`)
- **Anti-spam :** Handler `deduplication` de Monolog (fenêtre de 5 minutes)
- **Contexte :** Handler `fingers_crossed` pour inclure tous les logs précédant l'erreur

## Architecture des handlers

```
Requête HTTP
    │
    ▼
[fingers_crossed] ──buffer tous les logs──► si ERROR/CRITICAL déclenché
    │                                              │
    ▼                                              ▼
[stream: prod.log]                    [deduplication] ── déjà envoyé récemment ?
(tous les logs INFO+)                       │ non         │ oui
                                            ▼             ▼
[rotating_file: error.log]          [native_mailer]    (ignoré)
(ERROR+ uniquement)                  Gmail SMTP
```

## Handlers configurés (prod uniquement)

| Handler | Type | Niveau | Rôle |
|---------|------|--------|------|
| `main` | rotating_file | INFO | Consultation manuelle, 14 jours |
| `error` | rotating_file | ERROR | Historique erreurs, 30 jours |
| `mail_buffer` | fingers_crossed | ERROR | Bufférise tout, déclenche sur ERROR+ |
| `mail_dedup` | deduplication | — | Anti-spam, fenêtre 300 secondes |
| `mail_sender` | native_mailer | — | Envoi SMTP Gmail |

## Variables d'environnement

```env
MAILER_FROM=noreply@sbl.app
MAILER_TO=admin@sbl.app
MAILER_HOST=smtp.gmail.com
MAILER_PORT=587
MAILER_USERNAME=ton-compte@gmail.com
MAILER_PASSWORD=app-password-gmail  # App Password Gmail (pas le mot de passe principal)
```

## Fichiers modifiés

- `config/packages/monolog.yaml` — ajout des handlers mail dans le bloc `when@prod`
- `.env` — ajout des variables MAILER_*
- `.env.local` — valeurs réelles (non committé)

## Ce qui n'est PAS dans ce design

- Emails HTML / templates Twig (approche B, future évolution possible)
- Intégration Sentry (approche C, si besoin de monitoring avancé)
- Modification de code PHP (zéro nouveau fichier PHP)
