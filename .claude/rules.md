# Development Rules — sf-flooze

Conventions et patterns pour ce projet Symfony 8.0. Règles prescriptives — exemples uniquement quand non-évident.

---

## SOLID — application stricte

- **SRP** : Controllers = HTTP only (validation + délégation + réponse). Services = business logic. Repositories = queries DB.
- **OCP** : étendre via nouveau service, ne pas modifier les entités/services existants.
- **LSP** : utiliser des interfaces pour les composants swappables (AI client, PDF generator).
- **ISP** : traits petits et focalisés (`SpaceScopeTrait` ajoute uniquement `space_id`).
- **DIP** : injection via DI container uniquement. **Jamais `new Service()` dans un autre service.**

```php
// GOOD
public function __construct(private readonly OllamaClient $ollama) {}
```

---

## Naming Conventions

### Classes PHP

| Type | Pattern | Exemple |
|------|---------|---------|
| Entity | `CamelCase` singulier | `User`, `Property`, `Transaction` |
| Repository | `{Entity}Repository` | `TransactionRepository` |
| Service | `{Verb}{Noun}Service` | `ReceiptOcrService` |
| Controller | `{Noun}Controller` | `QuoteController` |
| Form | `{Noun}FormType` | `TransactionFormType` |
| Enum | `{Noun}{Adj}Enum` | `InvoiceStatusEnum` |
| Trait | `{Noun}Trait` | `TimestampTrait` |
| Event Listener | `{Trigger}Listener` | `AutoCategoryListener` |
| Command | `{Verb}{Noun}Command` | `GenerateRentPaymentsCommand` |
| DTO | `{Action}{Noun}Dto` | `CreateTransactionDto` |
| PDF Generator | `{Noun}PdfGenerator` | `QuotePdfGenerator` |
| Voter | `{Noun}Voter` | `SpaceScopeVoter` |

### Méthodes

- **Repositories** : descriptifs — `findBySpaceAndDateRange()`, `findOverdueInvoices()`, `sumExpensesByCategory()`
- **Services** : verbe d'abord — `createTransaction()`, `reconcileWithBankStatement()`
- **Controllers** : noms de routes en `{noun}_{action}` — `transaction_index`, `transaction_new`, `transaction_edit`, `transaction_delete`

---

## Database Conventions

### Tables
- **Singulier, snake_case** : `user`, `property`, `tax_year`, `rent_payment`
- **Pivots** : `parent_child` → `lease_tenant`, `document_link`

### Colonnes
- PK : `id` (int, auto-increment)
- FK : `{entity}_id` → `space_id`, `account_id`
- Booléens : `is_{adj}` → `is_deductible`, `is_active`
- **Soft delete** : `deleted_at` (TIMESTAMP nullable) — **jamais** `is_deleted`
- Audit : `created_at`, `updated_at` (auto via `TimestampListener`)
- **Multi-tenant** : toutes les entités ont `space_id` FK

### Doctrine
- Mapping via **attributes uniquement** (pas de YAML/XML)
- Décimal : `precision: 15, scale: 2`
- Enum : `#[ORM\Column(type: Types::STRING, enumType: XxxEnum::class)]`
- Toujours filtrer `deletedAt IS NULL` dans les queries (ou utiliser `SoftDeleteListener`)

---

## File Organization

### Quand créer un service
- Domaine métier distinct (Finance / Tax / RealEstate)
- Logique utilisée par plusieurs controllers
- Complexité > ~30 lignes en controller
- Interaction API externe (Ollama, S3, email)

### Controllers
- **Max ~50 lignes par action**
- Aucune business logic — délégation totale aux services
- Type-hinting d'entité pour les paramètres de route (ParamConverter implicite)

### Repositories
- Queries uniquement, **pas de business logic**
- QueryBuilder pour filtres complexes, DQL pour joins complexes
- Retourner des arrays typés ou entités

### Services
- Si > 300 lignes → split par use case

---

## Testing

### Hiérarchie

```
tests/
├── Unit/          → Logique isolée (mocks)
├── Integration/   → DB réelle + fixtures minimales
└── Functional/    → HTTP, forms, workflows
```

### Règles
- **Unit** : mock toutes les dépendances externes. Une méthode = un test. Nommage `test{Method}{Scenario}`.
- **Integration** : DB de test (`.env.test`), rollback par transaction, fixtures minimales.
- **Functional** : happy path + 1 cas d'erreur par endpoint. `WebTestCase`. Ne pas tester les bytes PDF — juste le `content-type`.

---

## ERD = autorité

**Toutes les entités doivent matcher l'ERD de `ARCHITECTURE.md` exactement.**
Avant de créer une entité, relation ou pivot → vérifier dans `ARCHITECTURE.md → "Entity Map"`. Si la relation n'y est pas → ne pas la créer.

Exemples interdits :
- Ajouter un pivot `SpaceMembership` quand l'ERD dit `User (1) ── (N) Space` avec FK simple
- Inventer des colonnes hors-schéma

---

## Context7 pour la documentation

Toujours fetch les docs à jour via context7 avant d'écrire du code utilisant une lib externe. Le training peut être obsolète.

| Use context7 | Don't use |
|---|---|
| Symfony component API | Refactoring de business logic |
| Doctrine syntax / mapping | Services from scratch sans API externe |
| dompdf, FrankenPHP, Caddy | Code review |
| Ollama REST API | Patterns PHP généraux |
| AssetMapper / Stimulus / Turbo | Décisions ERD / entity design |
| Twig, Twig Components | Debug pure business logic |

### Commandes
```bash
npx ctx7@latest library "<name>" "<question>"
npx ctx7@latest docs <id> "<question>"
npx ctx7@latest docs <id> "<question>" --research   # si insatisfaisant
```

### IDs courants

| Library | ctx7 ID |
|---|---|
| Symfony | `/symfony/symfony` |
| Doctrine ORM | `/doctrine/orm` |
| Twig | `/twigphp/twig` |
| UX Twig Component | `/symfony/ux-twig-component` |
| UX Live Component | `/symfony/ux-live-component` |
| dompdf | `/dompdf/dompdf` |
| Stimulus | `/hotwired/stimulus` |
| Turbo | `/hotwired/turbo` |

---

## HTML / CSS / JS

- **HTML sémantique** : `<nav>`, `<main>`, `<aside>`, `<ul>/<li>`, `<details>/<summary>`. `<div>` réservé au layout non-sémantique.
- **CSS avant JS** : pour hover/focus, transitions, `:has(input:checked)`, `:focus-within`, `<details>`. JS uniquement si vraiment nécessaire (localStorage, async, interactions sans équivalent CSS).
- **Jamais `style="..."` inline** — utiliser des classes CSS.

---

## Twig — règle de 2

**Tout bloc HTML/CSS apparaissant ≥ 2 fois doit être factorisé.** Pas de pré-factorisation spéculative — attendre la 2e occurrence puis extraire.

### Matrice de décision

| Situation | Outil | Emplacement |
|---|---|---|
| Fragment inline ≤ 10 lignes, pas de slots | **Macro** | `templates/macros/{topic}.html.twig` |
| Partial statique réutilisé | **`{% include %}`** | `templates/{module}/_{name}.html.twig` |
| Partial avec blocks/slots overridables | **`{% embed %}`** | `templates/{module}/_{name}.html.twig` |
| Unité réutilisable avec props, defaults, attrs pass-through | **Twig Component (anonymous)** | `templates/components/{Name}.html.twig` |
| Component avec logique PHP, computed props | **Twig Component (class-based)** | `src/Twig/Components/{Name}.php` + template |

> ⚠️ `symfony/ux-twig-component` **n'est pas installé**. Avant le premier composant : `composer require symfony/ux-twig-component` et confirmation utilisateur. En attendant : macros et `embed` uniquement.

### Conventions de nommage

```
templates/macros/{topic}.html.twig          → groupé par sujet (forms, money, buttons)
templates/components/{Name}.html.twig       → PascalCase, un composant par fichier
templates/components/{Group}/{Name}.html.twig → namespacé par dossier → <twig:Group:Name />
src/Twig/Components/{Name}.php              → matche le template 1-pour-1
```

### Catalogue de patterns à factoriser dès la 2e occurrence

| Pattern | Forme | Variantes |
|---|---|---|
| `<a/button class="btn btn--{variant}">` + icon Lucide | macro `buttons` → `Button` | `primary`, `secondary`, `ghost`, `danger` |
| `<div class="flash flash--{level}">` | macro `flashes` (loop sur `app.flashes`) | — |
| Form field (label + input + error) | macro `forms` → `Form:Field` | — |
| `panel-empty` (icon + texte + hint + CTA) | `EmptyState` (slots) | — |
| Page header (titre + sous-titre + actions) | `PageHeader` (slot actions) | — |
| Money `1 234,56 €` (JetBrains Mono, em-dash sur négatif) | macro `money` → `Money` | — |
| Status / type badge | `Badge` | variantes par enum value |
| Card shell (icon + body + actions) | `Card` (`embed` en attendant) | — |

### Anti-patterns
- **Computation en template** — pas d'arithmétique/aggregation/filtrage business dans `{% set %}`. Calcul en controller, exposition via DTO/ViewModel.
- Partial copié-collé > 5 lignes — extraire avant la 3e occurrence.
- Macro qui fait 3+ choses non-liées — split.
- Component avec > ~6 props — sous-composants ou slots.
- Logique dupliquée macro ↔ component — une seule source de vérité.

---

## CSS — règle de 2 + composition > override

### Hiérarchie de réutilisation (cheap → cher)

1. **Design tokens** (`--color-*`, `--space-*`, `--radius-*`, `--font-*`) — définis dans `app.css`. Valeurs hardcodées interdites si un token existe.
2. **Modifiers BEM** (`btn btn--primary`, `space-dot space-dot--sm`) — préférés aux nouveaux composants avec overrides.
3. **Classe partagée** dans `app.css` — uniquement si pattern utilisé par **3+ modules** non-liés.
4. **Classe scopée module** dans `templates/{module}/_styles.html.twig`.

### Emplacements

```
assets/styles/app.css
├── tokens (root vars + [data-theme="dark"])
├── reset / base
├── layout shell (sidebar, topbar, main, page-header)
└── shared components (.btn, .flash, .badge, .card, .panel-empty)
   → uniquement si utilisés dans ≥ 3 modules

templates/{module}/_styles.html.twig
└── classes préfixées par module (.space-full-card, .quote-line-row)
```

### Triggers de refactor

- Même couleur/spacing/radius typée 2× → introduire/utiliser un token
- 2 sélecteurs avec body identique → merge ou base + modifier
- Classe d'un module utilisée par un 2e module → promouvoir vers `app.css` (sans préfixe)
- Classe dans `app.css` utilisée par 1 seul module → demouvoir vers `_styles.html.twig`

### Anti-patterns
- Couleurs hardcodées si un token existe (sauf dans la définition des tokens)
- `!important` — quasi toujours un ordre de cascade incorrect
- Sélecteurs profonds (> 3 niveaux) — fuite de scope, BEM
- Classes module-specific dans `app.css`
- Blocs dark-theme dupliqués — toggle via `[data-theme="dark"] .x { ... }`, pas de `.x-dark` parallèle
- `<style>` inline dans templates — `{% block stylesheets %}` + `_styles.html.twig`

---

## Anti-Patterns transverses

- **Inventer des entités** non présentes dans l'ERD
- **Pivot inutile** quand une FK simple suffit
- **Fat controllers** — business logic en service
- **`new` dans un service** — toujours injecter
- **Oublier `space_id`** sur une entité multi-tenant
- **Boolean soft-delete** (`is_deleted`) — utiliser `deleted_at`
- **Abstraction prématurée** — 3 lignes similaires < classe abstraite pour 1 cas
- **Microservices reflex** — monolithe jusqu'à preuve de bottleneck
- **Mock DB en integration test** — DB réelle ou test sans valeur
- **SQL inline** — QueryBuilder/DQL
- **Arrays non-typés** retournés par services — DTOs ou collections typées
- **God service** > 300 lignes — split

---

## Symfony — spécificités

### Routes
PHP attributes, pas YAML (sauf API endpoints dans `config/routes/api.yaml`).

### Security
Toujours vérifier l'ownership via `SpaceScopeVoter` :
```php
$this->denyAccessUnlessGranted('VIEW', $entity->getSpace());
$this->denyAccessUnlessGranted('EDIT', $entity->getSpace());
```

### Forms
FormType classes uniquement, jamais en controller. CSRF auto via `AbstractType`.

### Events
- `EventListener` (pas `EventSubscriber`) pour Doctrine lifecycle
- `Symfony\Component\EventDispatcher` pour domain events

### Enums
PHP 8.1+ backed enums. Toujours `enumType` dans la column Doctrine.

### Traits
**Propriétés + getters/setters uniquement.** Pas de business logic. Exemple `SoftDeleteTrait` : `deletedAt`, `getDeletedAt()`, `setDeletedAt()`, `isDeleted()`, `softDelete()`.# rules.md — sf-flooze

Conventions de code PHP/Symfony. Les règles UI/CSS sont dans [`FRONTEND.md`](FRONTEND.md), les règles de test dans [`TESTING.md`](TESTING.md).

Les **garde-fous critiques** (ERD, multi-tenant, soft-delete, DI, security) sont listés dans [`CLAUDE.md`](CLAUDE.md). Ils sont supposés acquis ici.

---

## Naming

### Classes

| Type | Pattern | Exemple |
|---|---|---|
| Entity | `CamelCase` singulier | `Property`, `Transaction` |
| Repository | `{Entity}Repository` | `TransactionRepository` |
| Service | `{Verb}{Noun}Service` | `ReceiptOcrService` |
| Controller | `{Noun}Controller` | `QuoteController` |
| Form | `{Noun}FormType` | `TransactionFormType` |
| Enum | `{Noun}{Adj}Enum` | `InvoiceStatusEnum` |
| Trait | `{Noun}Trait` | `TimestampTrait` |
| Listener | `{Trigger}Listener` | `AutoCategoryListener` |
| Command | `{Verb}{Noun}Command` | `GenerateRentPaymentsCommand` |
| DTO | `{Action}{Noun}Dto` | `CreateTransactionDto` |
| PDF Generator | `{Noun}PdfGenerator` | `QuotePdfGenerator` |
| Voter | `{Noun}Voter` | `SpaceScopeVoter` |

### Méthodes

- **Repositories** : descriptifs — `findBySpaceAndDateRange()`, `findOverdueInvoices()`, `sumExpensesByCategory()`.
- **Services** : verbe d'abord — `createTransaction()`, `reconcileWithBankStatement()`.
- **Routes** : `{noun}_{action}` — `transaction_index`, `transaction_new`, `transaction_edit`, `transaction_delete`.

### Base de données

- Tables : **singulier, snake_case** — `user`, `tax_year`, `rent_payment`.
- Pivots : `parent_child` — `lease_tenant`, `document_link`.
- PK : `id` (int auto-increment). FK : `{entity}_id`.
- Booléens : `is_{adj}` — `is_deductible`, `is_active`.
- Audit : `created_at`, `updated_at` (auto via `TimestampListener`).

---

## Architecture

### Controllers

- Max ~50 lignes par action.
- HTTP only : valider la requête, déléguer au service, retourner la réponse.
- Type-hinting d'entité pour les paramètres de route (ParamConverter implicite).
- Routes via PHP attributes, pas YAML (sauf API dans `config/routes/api.yaml`).

### Services

- Créer un service quand : domaine métier distinct, logique partagée par plusieurs controllers, complexité > ~30 lignes en controller, ou interaction API externe.
- > 300 lignes → split par use case.
- Retourner DTOs ou entités typées, jamais d'arrays anonymes.

### Repositories

- Queries uniquement, pas de business logic.
- QueryBuilder pour filtres complexes, DQL pour joins complexes. **Jamais de SQL inline.**
- Toujours filtrer `space` + `deletedAt IS NULL`.
- **Calculs mathématiques côté DB :** si une opération (somme, moyenne, agrégation, etc.) peut s'exprimer en DQL ou SQL, l'y faire plutôt que de ramener des entités en PHP pour les calculer.

### Forms

- FormType classes uniquement, jamais de form construit en controller.
- CSRF auto via `AbstractType`.

### Events

- `EventListener` (pas `EventSubscriber`) pour les hooks Doctrine.
- `Symfony\Component\EventDispatcher` pour les events domaine.

### Traits

- Propriétés + getters/setters uniquement. **Aucune business logic.**
- `SpaceScopeTrait` n'ajoute que `space`. `TimestampTrait` n'ajoute que `created_at`/`updated_at`.

---

## Doctrine

- Mapping via **attributes uniquement**.
- Décimal monétaire : `precision: 15, scale: 2`.
- Enums : PHP 8.1+ backed enums avec `#[ORM\Column(type: Types::STRING, enumType: XxxEnum::class)]`.
- Lifecycle callbacks via `#[ORM\HasLifecycleCallbacks]` ou listeners dédiés (préférés).

---

## Anti-patterns

- Inventer une entité/relation hors ERD.
- Pivot quand une FK simple suffit.
- Business logic en controller.
- `new` au lieu d'injection.
- Oubli de `space_id` ou de `denyAccessUnlessGranted`.
- `is_deleted` au lieu de `deleted_at`.
- Abstraction prématurée (3 lignes similaires ne justifient pas une classe abstraite).
- SQL inline.
- Array non typé en retour de service.
- Service > 300 lignes sans découpage.