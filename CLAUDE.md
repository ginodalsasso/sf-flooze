# CLAUDE.md — sf-flooze

Application Symfony 8.0 hybride : ERP + gestion bancaire + fiscalité personnelle/professionnelle.
Multi-tenant via `Space`. OCR Ollama local. PDF dompdf.

**Stack** : PHP 8.4 · Symfony 8.0 · Doctrine 3.x · MySQL 8.0 · FrankenPHP · Ollama · dompdf · Stimulus/Turbo.

---

## Documents — toujours consulter avant d'agir

| Avant de... | Lire |
|---|---|
| Créer/modifier une entité, relation, FK, colonne | [`ARCHITECTURE.md`](ARCHITECTURE.md) — **ERD = autorité** |
| Écrire ou refactorer du code PHP | [`rules.md`](rules.md) — conventions, naming, anti-patterns |
| Toucher `templates/` ou `assets/styles/` | [`FRONTEND.md`](FRONTEND.md) + [`DESIGN_SYSTEM.md`](DESIGN_SYSTEM.md) |
| Écrire ou modifier des tests | [`TESTING.md`](TESTING.md) |
| Installer le projet, comprendre les services tournants | [`SETUP.md`](SETUP.md) |
| Comprendre le périmètre d'un module métier | [`MODULES.md`](MODULES.md) |

---

## Garde-fous critiques (cassent le projet si violés)

1. **ERD = autorité.** Toute entité, relation, pivot, colonne doit exister dans `ARCHITECTURE.md → Entity Map`. Sinon, ne pas le créer.
2. **Multi-tenant.** Toute entité métier a `space_id` + filtre par `space` dans toute query.
3. **Soft delete.** `deleted_at` (TIMESTAMP nullable), jamais `is_deleted`. Filtre `deletedAt IS NULL` dans les queries actives.
4. **DI uniquement.** Jamais `new XxxService()` dans un autre service. Constructor injection avec `private readonly`.
5. **Security par espace.** `denyAccessUnlessGranted('VIEW'|'EDIT', $entity->getSpace())` via `SpaceScopeVoter`.

---

## Quick Dev Workflow

```bash
# Démarrage (3 terminaux ou Docker)
symfony serve                   # PHP dev server :8000
ollama serve                    # IA :11434
# MySQL : Laragon (auto) ou docker compose up -d database

# Cycle de modif
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console cache:clear     # si nécessaire

# Avant tout commit
php bin/phpunit tests/                          # tous tests verts
php bin/console doctrine:schema:validate        # schéma valide
php bin/console doctrine:migrations:diff        # aucune migration en attente
php bin/console lint:twig templates/            # templates valides
```

---

## Décisions clés du projet

- **Monolithe Symfony** — pas de microservices avant un bottleneck prouvé. Symfony DI suffit.
- **dompdf > wkhtmltopdf** — zero deps système, Docker-friendly, FrankenPHP-compatible.
- **Ollama local > cloud AI** — privacy-first, coût zéro, fonctionne offline.
- **`DocumentLink` polymorphique** — un `Document` attachable à n'importe quelle entité (évite N tables de jointure).
- **`Space` = unité multi-tenant** — un user peut avoir plusieurs spaces (perso, pro, EIRL).
- **Tout flux monétaire passe par `Transaction`** — `RentPayment` et `LoanPayment` génèrent automatiquement leur `Transaction` via `LinkedTransactionListener`. Source de vérité unique pour le module Finance.

---

## Documentation libs externes — context7 obligatoire

Avant d'utiliser une lib externe (Symfony component, Doctrine, Twig, dompdf, Stimulus, Turbo, Ollama, FrankenPHP) :

```bash
npx ctx7@latest library "<name>" "<question>"
npx ctx7@latest docs <id> "<question>"
npx ctx7@latest docs <id> "<question>" --research   # si la 1re passe est insuffisante
```

IDs courants : `/symfony/symfony` · `/doctrine/orm` · `/twigphp/twig` · `/symfony/ux-twig-component` · `/symfony/ux-live-component` · `/dompdf/dompdf` · `/hotwired/stimulus` · `/hotwired/turbo`.

Liste complète des cas où context7 est attendu (et ceux où il ne l'est pas) : voir [`rules.md`](rules.md) → "Workflow Claude".

---

## Commandes fréquentes

```bash
# Doctrine
php bin/console doctrine:migrations:diff        # générer migration
php bin/console doctrine:migrations:migrate     # appliquer
php bin/console doctrine:fixtures:load          # data de test
php bin/console doctrine:schema:validate

# Debug
php bin/console debug:router                    # routes
php bin/console debug:container                 # services
php bin/console debug:container --unused        # services orphelins

# Tests
php bin/phpunit tests/                          # tout
php bin/phpunit tests/Unit/                     # unit only
php bin/phpunit --filter testMethodName         # un seul test
```