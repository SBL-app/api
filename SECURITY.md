# Sécurité — API SBL

Revue de sécurité alignée sur le **OWASP Top 10 (2021)**.

## Mesures en place

| Risque OWASP | Mesure |
| --- | --- |
| **A01 — Contrôle d'accès** | L'API est en **lecture seule** : tous les endpoints d'écriture (POST/PUT/PATCH/DELETE) sont désactivés. Voir la section « Endpoints d'écriture » ci-dessous avant toute réactivation. |
| **A02 — Défaillances cryptographiques** | Le `APP_SECRET` réel n'est plus versionné : la valeur du `.env` est un placeholder de développement ; en production il provient d'une variable d'environnement (`docker-compose`). **L'ancien secret exposé doit être régénéré.** |
| **A03 — Injection** | Accès aux données exclusivement via l'ORM Doctrine (requêtes paramétrées) ; aucun SQL concaténé. Les identifiants de route sont typés (`int $id`). |
| **A05 — Mauvaise configuration** | En-têtes de sécurité ajoutés à toutes les réponses via `SecurityHeadersSubscriber` (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP restrictive). `server_tokens off` côté Nginx. Conteneur PHP-FPM non-root. |
| **A05 — CORS** | `nelmio/cors-bundle` restreint les origines via une regex (`CORS_ALLOW_ORIGIN`), `allow_credentials: false`. |
| **A06 — Composants vulnérables** | Dépendances gérées par Composer ; à auditer régulièrement (`composer audit`). |
| **A08 — Intégrité** | Build Docker reproductible, autoloader optimisé, image de production sans dépendances de dev. |

## Endpoints d'écriture (à sécuriser avant réactivation)

Les routes de modification sont actuellement commentées. **Avant de les
réactiver**, il est impératif d'ajouter :

1. une **authentification** (l'infrastructure provisionne déjà des clés JWT et
   un flux OAuth Discord — voir `docker-compose`),
2. un **contrôle d'accès** par rôle (`#[IsGranted(...)]`),
3. une **validation des entrées** (composant `symfony/validator`),
4. une protection **CSRF** si des sessions sont utilisées.

## Vérification

```bash
composer audit          # vulnérabilités des dépendances
vendor/bin/phpstan      # analyse statique
vendor/bin/phpunit      # tests (dont SecurityHeadersSubscriberTest)
```

## Signaler une vulnérabilité

Ouvrir une issue privée ou contacter un mainteneur. Ne pas divulguer
publiquement une faille avant correction.
