# CLAUDE.md — sf-flooze

Application Symfony 8.0 hybride : ERP + gestion bancaire + fiscalité personnelle/professionnelle.
Multi-tenant via `Space`. OCR Ollama local. PDF dompdf.

**Stack** : PHP 8.4 · Symfony 8.0 · Doctrine 3.x · MySQL 8.0 · FrankenPHP · Ollama · dompdf · Stimulus/Turbo.

---

## Documents — toujours consulter avant d'agir

| Avant de... | Lire |
|---|---|
| Créer/modifier une entité, relation, FK, colonne | [`ARCHITECTURE.md`](ARCHITECTURE.md) — **ERD = autorité** |
| Écrire ou refactorer du code PHP | [`.claude/rules.md`](.claude/rules.md) — principes, conventions, anti-patterns |
| Toucher `templates/` ou `assets/styles/` | [`FRONTEND.md`](FRONTEND.md) + [`DESIGN_SYSTEM.md`](DESIGN_SYSTEM.md) |
| Écrire ou modifier des tests | [`TESTING.md`](TESTING.md) |
| Toucher à l'authz, aux voters, aux formulaires sensibles | [`SECURITY_PLAN.md`](SECURITY_PLAN.md) |
| Installer le projet, comprendre les services tournants | [`SETUP.md`](SETUP.md) |
| Comprendre le périmètre d'un module métier | [`MODULES.md`](MODULES.md) |

---

## Principes de code (priment sur tout le reste)

1. **Simplicité — pas de sur-engineering.** La solution la plus simple qui marche est la bonne. Code lisible avant code malin. Une abstraction se crée sur un besoin réel et présent, jamais anticipé.
2. **SOLID.** SRP (controller = HTTP, service = métier, repository = queries, entité = état) · OCP · LSP · ISP · DIP (injection uniquement).
3. **Changements isolés.** Modifier un module ne doit pas obliger à modifier les autres. Ne pas renommer ni changer une signature publique sans recenser les appelants. Attention particulière aux points partagés : `app.css`, traits, listeners Doctrine, macros.
4. **Documentation courte et précise.** Le commentaire dit *pourquoi*, jamais *quoi*. 1 ligne max dans une méthode. PHPDoc seulement si le type-hint ne suffit pas.
5. **Nommage conservé.** Suivre les conventions existantes pour le nouveau code, ne pas renommer l'existant.
6. **Interfaces = contrats.** Tout composant injectable (service, repository, client externe, générateur PDF, resolver) expose une interface `{Classe}Interface` dans le sous-dossier `Contract/` de son module ; l'implémentation est `final` et on type-hint **toujours** l'interface. Hors périmètre : entités, DTO, enums, traits, FormTypes, controllers, listeners, voters, commandes.

Détail et exemples : [`.claude/rules.md`](.claude/rules.md) → *Interfaces*.

---

## Garde-fous critiques (cassent le projet si violés)

1. **ERD = autorité.** Toute entité, relation, pivot, colonne doit exister dans `ARCHITECTURE.md → Entity Map`. Sinon, ne pas le créer.
2. **Multi-tenant.** Toute entité métier a `space_id` + filtre par `space` dans toute query.
3. **Soft delete.** `deleted_at` (TIMESTAMP nullable), jamais `is_deleted`. Filtre `deletedAt IS NULL` dans les queries actives.
4. **DI uniquement.** Jamais `new XxxService()` dans un autre service. Constructor injection avec `private readonly`, type-hint sur l'**interface** du composant — jamais sur son implémentation.
5. **La sécurité se vérifie côté backend — l'UI ne sécurise rien.** Un bouton masqué, un `disabled`, un `readonly` ou un `{% if %}` en Twig sont contournables : la requête HTTP est forgeable. Tout contrôle affiché côté UI doit exister côté serveur.
   - `denyAccessUnlessGranted('VIEW'|'EDIT', $entity->getSpace())` via `SpaceScopeVoter` sur **chaque** action, y compris `new`, `delete`, exports et endpoints JSON.
   - Query filtrée par `space` — un ID en URL ne prouve rien sur son propriétaire.
   - Entités choisies en formulaire scopées par `space` (`query_builder`) **et** revalidées avant persist.
   - Champ non modifiable = champ non mappé, pas un champ `disabled`.
   - Actions destructives en `POST`/`DELETE` avec CSRF, jamais en `GET`.
   - Jamais de redirection vers une URL issue de la requête sans validation locale.

   Checklist complète : [`.claude/rules.md`](.claude/rules.md) → *Sécurité*. Plan de durcissement : [`SECURITY_PLAN.md`](SECURITY_PLAN.md).

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
- **Multi-devise : `Space.currency` de référence, solde du compte dans la devise du compte** — seuls les agrégats convertissent. Taux historique figé sur la ligne (`transaction.fx_rate`), taux spot via `ExchangeRateService` (seul détenteur des taux, point de branchement d'une API).
- **Le solde d'un compte se calcule, il ne se stocke pas** — `account.opening_balance` + Σ des transactions, via `AccountBalanceService::getCurrentBalance()`. Aucun service n'écrit un solde : pas d'accumulateur, donc pas d'écart possible. Ne jamais réintroduire de colonne `balance`.
- **`Tag` ≠ `Category`** — la catégorie dit la *nature* du flux (une seule, hiérarchique, portée fiscale) ; le tag dit le *contexte* (0..N, plat, aucune portée fiscale, jamais lu par le module Tax). Filtre mono-tag écrit en `MEMBER OF`, jamais en `JOIN` — une jointure ManyToMany duplique les lignes et fausse les totaux.
- **Les dates ont quatre points d'ancrage, jamais contournés** — fuseau (`app.timezone`), instant présent (`ClockInterface` / `now()`), période métier (`PeriodEnum` → `DateRangeDto`), affichage (`DateFormatterInterface`, exposé à Twig par `DateExtension` et au JS via une chaîne déjà rendue). `new \DateTimeImmutable()` et tout format en dur sont des anti-patterns. Détail : [`.claude/rules.md`](.claude/rules.md) → *Dates*.
- **Virement = 1 `Transaction`, 2 comptes** (`account_id` + `destination_account_id`), pas de double-entry. Toute query filtrant par compte doit matcher **les deux jambes**.
- **Une récurrence ne génère rien automatiquement** — `RecurringTransaction` est un gabarit + une règle de dates. Les échéances dues sont calculées à l'affichage et matérialisées par confirmation explicite, via `TransactionService::save()`. Aucune tâche planifiée, aucune écriture non confirmée dans le ledger.

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