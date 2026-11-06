# Contribuer

## Workflow Git
- `main` : production (déployée par `cd.yml`). Jamais de PR de feature directe.
- `dev` : branche d'intégration. Toutes les branches `feat/*` et `fix/*` partent de `dev`.
- **PR vers `dev` uniquement.** La CI (`ci.yml`) s'exécute sur les PR vers `main` et `dev`.
- Release : branche `release/x.y` depuis `dev`, puis PR vers `main` (tag + release via `release.yml`).
- Hotfix urgent : PR vers `main`, puis report dans `dev`.
