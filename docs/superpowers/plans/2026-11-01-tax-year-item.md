# TaxYear & TaxItem Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a persisted annual tax review on top of the existing read-only `/tax/declaration`: a `TaxYear` per space/year with a status (`draft|filed|paid`), a regime choice per fiscal bucket, and `TaxItem` checkboxes (one per bucket × role) marking a declaration line as reported.

**Architecture:** `TaxYear`/`TaxItem` store nothing `TaxSummaryService` can already compute — `TaxItem` is a bare checkbox keyed `(tax_year, fiscal_bucket, fiscal_role)`, created on first toggle, never pre-generated. The declaration's bucket table (`templates/tax/declaration/_bucket.html.twig`) is extended with an `editable` mode and reused, unmodified in its read-only path, by the new review page. One `TaxYearController` owns five routes; no separate `TaxItemController`.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine ORM 3.x, Twig, MySQL 8.0.

**Spec:** [docs/superpowers/specs/2026-11-01-tax-year-item-design.md](../specs/2026-11-01-tax-year-item-design.md)

## Global Constraints

- Every entity carries `space_id` via `SpaceScopeTrait`; every repository query filters by space (`CLAUDE.md` garde-fou 2).
- No soft delete on `TaxYear`/`TaxItem` — confirmed by `ARCHITECTURE.md` (short lifecycle / kept for audit).
- `TaxItem` is never pre-generated for every bucket — a row exists only once the user has toggled it at least once.
- `regime_choices` stores the literal strings `'reel'`/`'micro'`, the same values `TaxBucketDto::cheaperRegime()` already returns — no new PHP enum for this.
- Every injectable service/repository is typed by its `{Classe}Interface` in the module's `Contract/` subfolder; implementations are `final` (`.claude/rules.md` → *Interfaces*). No `new XxxService()` — constructor injection only, `private readonly`, typed on the interface.
- Forms are not a security boundary: every write action revalidates ownership and re-checks `denyAccessUnlessGranted` against the resolved `Space` — a `{year}` in the URL proves nothing about its owner.
- Status transitions and the toggle are plain `POST` forms with manual CSRF (`isCsrfTokenValid()`), not Symfony `FormType`s — matches `LeaseController::toggle()`/`delete()`.
- **No PHPUnit tests are written in this plan** (standing project preference). Each task's verification step uses `doctrine:schema:validate`, `doctrine:migrations:diff` (expect no pending diff after migrating), `lint:twig templates/`, `php bin/console debug:router`, and manual UI/`php -l` checks — never a committed test file.
- Every commit is proposed, not run automatically — wait for the user's explicit go-ahead before `git commit` (standing instruction, applies to every task below, including docs-only ones).

## Review Focus

1. **Toggling a bucket/role pair that doesn't exist yet** (`TaxItemService::toggle()` on a fresh `TaxYear`): must create the row `done=true` on the first call, not throw on a missing `TaxItem` — covered by Task 4's upsert logic and Task 9's manual walkthrough.
2. **`CHARGE` toggled on a bucket that doesn't accept charges** (`RCM`, `FiscalBucketEnum::acceptsCharges() === false`): `TaxItemService::toggle()` must throw `\InvalidArgumentException`, not silently create a meaningless row — Task 4.
3. **`'micro'` regime chosen on a bucket with no micro regime** (`RCM`, `allowsMicro() === false`): `TaxYearService::setRegimeChoice()` must throw, not store an impossible choice that the UI would then render inconsistently — Task 2.
4. **Status transition skipping a step** (`draft` → `paid` directly, or `paid` → `draft` directly): `TaxYearService::transition()` must throw — only adjacent steps are allowed — Task 2.
5. **Two spaces each with their own `TaxYear` for the same calendar year**: `findBySpaceAndYear()` must only ever return the caller's own space's row — the unique constraint is `(space_id, year)`, not `(year)` alone; verified manually in Task 9 by switching the active space.

---

## Phase 1 — `TaxYear`

### Task 1: `TaxYearStatusEnum`, `TaxYear` entity, migration, repository

**Files:**
- Create: `src/Enum/TaxYearStatusEnum.php`
- Create: `src/Entity/TaxYear.php`
- Create: `src/Repository/Contract/TaxYearRepositoryInterface.php`
- Create: `src/Repository/TaxYearRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (generated)

**Interfaces:**
- Produces: `TaxYearStatusEnum::{DRAFT,FILED,PAID}` with `label(): string`; `TaxYear` with fluent setters `setSpace(Space)`, `setYear(int)`, `setStatus(TaxYearStatusEnum)`, `setRegimeChoices(?array)`, `setNote(?string)` and matching getters (`getRegimeChoices(): ?array`); `TaxYearRepositoryInterface::findBySpaceAndYear(Space, int): ?TaxYear`, `::findBySpace(Space): array`.

- [ ] **Step 1: Write `TaxYearStatusEnum`**

`string`-backed enum, cases `DRAFT = 'draft'`, `FILED = 'filed'`, `PAID = 'paid'`, a `label(): string` method (`'Brouillon'` / `'Déposée'` / `'Payée'`) — same shape as `App\Enum\RentPaymentStatusEnum`.

- [ ] **Step 2: Write the `TaxYear` entity**

`#[ORM\Entity(repositoryClass: TaxYearRepository::class)]`, `#[ORM\Table(name: 'tax_year', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_tax_year_space_year', columns: ['space_id', 'year'])])]`, `#[ORM\HasLifecycleCallbacks]`. Uses `TimestampTrait` + `SpaceScopeTrait` (no soft delete). Fields: `id` (identity), `year` (`smallint`), `status` (`string`, `enumType: TaxYearStatusEnum::class`, default via constructor to `TaxYearStatusEnum::DRAFT`), `regimeChoices` (`json`, nullable, column `regime_choices`, PHP type `?array`), `note` (`text`, nullable). Follow `src/Entity/Loan.php`'s style (fluent setters returning `static`). Constructor sets `$this->status = TaxYearStatusEnum::DRAFT;`.

- [ ] **Step 3: Write `TaxYearRepositoryInterface` and `TaxYearRepository`**

Mirror `src/Repository/Contract/LoanPaymentRepositoryInterface.php` / `src/Repository/LoanPaymentRepository.php`. `findBySpaceAndYear(Space $space, int $year): ?TaxYear` — `WHERE ty.space = :space AND ty.year = :year`, `getOneOrNullResult()`. `findBySpace(Space $space): array` — `WHERE ty.space = :space ORDER BY ty.year DESC`.

- [ ] **Step 4: Generate and review the migration**

Run: `php bin/console doctrine:migrations:diff`
Expected: one new migration creating table `tax_year` with the columns above, the unique index `uniq_tax_year_space_year`, and the FK to `space`.

- [ ] **Step 5: Migrate and verify**

Run: `php bin/console doctrine:migrations:migrate --no-interaction` then `php bin/console doctrine:schema:validate`
Expected: `[OK] The mapping files are correct.` and `[OK] The database schema is in sync with the mapping files.`

- [ ] **Step 6: Commit (propose only — wait for the user's go-ahead)**

```bash
git add src/Enum/TaxYearStatusEnum.php src/Entity/TaxYear.php src/Repository/Contract/TaxYearRepositoryInterface.php src/Repository/TaxYearRepository.php migrations/
git commit -m "feat: add TaxYear entity and repository"
```

---

### Task 2: `TaxYearService`

**Files:**
- Create: `src/Service/Tax/Contract/TaxYearServiceInterface.php`
- Create: `src/Service/Tax/TaxYearService.php`

**Interfaces:**
- Consumes: `TaxYearRepositoryInterface::findBySpaceAndYear()` (Task 1); `App\Enum\FiscalBucketEnum::allowsMicro(): bool` (existing).
- Produces: `TaxYearServiceInterface::getOrCreate(Space $space, int $year): TaxYear`; `::transition(TaxYear $taxYear, TaxYearStatusEnum $to): void`; `::setRegimeChoice(TaxYear $taxYear, FiscalBucketEnum $bucket, ?string $regime): void`; `::setNote(TaxYear $taxYear, ?string $note): void`.

- [ ] **Step 1: Implement `getOrCreate()`**

```php
public function getOrCreate(Space $space, int $year): TaxYear
{
    $taxYear = $this->taxYearRepository->findBySpaceAndYear($space, $year);

    if ($taxYear !== null) {
        return $taxYear;
    }

    $taxYear = (new TaxYear())->setSpace($space)->setYear($year);
    $this->em->persist($taxYear);
    $this->em->flush();

    return $taxYear;
}
```

- [ ] **Step 2: Implement `transition()`**

Order array `[TaxYearStatusEnum::DRAFT, TaxYearStatusEnum::FILED, TaxYearStatusEnum::PAID]`. Compute `$from = array_search($taxYear->getStatus(), $order, true)` and `$toIndex = array_search($to, $order, true)`; throw `\InvalidArgumentException('Cette transition de statut n\'est pas autorisée.')` when `abs($from - $toIndex) !== 1`. Otherwise `$taxYear->setStatus($to)`, `flush()`.

- [ ] **Step 3: Implement `setRegimeChoice()`**

Throw `\InvalidArgumentException('Valeur de régime invalide.')` when `$regime !== null && !in_array($regime, ['reel', 'micro'], true)`. Throw `\InvalidArgumentException('Cette catégorie n\'admet pas de régime micro.')` when `$regime === 'micro' && !$bucket->allowsMicro()`. Otherwise read `$choices = $taxYear->getRegimeChoices() ?? [];`, set or `unset($choices[$bucket->value])` depending on whether `$regime` is `null`, `$taxYear->setRegimeChoices($choices === [] ? null : $choices)`, `flush()`.

- [ ] **Step 4: Implement `setNote()`**

`$taxYear->setNote($note === '' ? null : $note); flush();` — constructor: `EntityManagerInterface $em`, `TaxYearRepositoryInterface $taxYearRepository`.

- [ ] **Step 5: Verify**

Run: `php -l src/Service/Tax/TaxYearService.php`
Expected: `No syntax errors detected`

Behavioural check (Review Focus #3, #4) happens in Task 9's end-to-end walkthrough.

- [ ] **Step 6: Commit (propose only)**

```bash
git add src/Service/Tax/Contract/TaxYearServiceInterface.php src/Service/Tax/TaxYearService.php
git commit -m "feat: add TaxYear status transitions and regime choice"
```

---

## Phase 2 — `TaxItem`

### Task 3: `TaxItem` entity, migration, repository

**Files:**
- Create: `src/Entity/TaxItem.php`
- Create: `src/Repository/Contract/TaxItemRepositoryInterface.php`
- Create: `src/Repository/TaxItemRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (generated)

**Interfaces:**
- Consumes: `TaxYear` (Task 1).
- Produces: `TaxItem` with fluent setters `setSpace(Space)`, `setTaxYear(TaxYear)`, `setFiscalBucket(FiscalBucketEnum)`, `setFiscalRole(FiscalRoleEnum)`, `setDone(bool)` and matching getters; `TaxItemRepositoryInterface::findOne(TaxYear, FiscalBucketEnum, FiscalRoleEnum): ?TaxItem`, `::findByTaxYear(TaxYear): array`.

- [ ] **Step 1: Write the `TaxItem` entity**

`#[ORM\Entity(repositoryClass: TaxItemRepository::class)]`, `#[ORM\Table(name: 'tax_item', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_tax_item_year_bucket_role', columns: ['tax_year_id', 'fiscal_bucket', 'fiscal_role'])])]`, `#[ORM\HasLifecycleCallbacks]`. Uses `TimestampTrait` + `SpaceScopeTrait` (no soft delete). Fields: `id`, `taxYear` (`ManyToOne` to `TaxYear`, `#[ORM\JoinColumn(name: 'tax_year_id', nullable: false, onDelete: 'CASCADE')]`), `fiscalBucket` (`string`, `enumType: FiscalBucketEnum::class`, column `fiscal_bucket`), `fiscalRole` (`string`, `enumType: FiscalRoleEnum::class`, column `fiscal_role`), `done` (`boolean`, default `false`). The boolean getter is named `isDone(): bool` (not `getDone()`), matching the project's existing convention (`Lease::isActive()`); the setter stays `setDone(bool $done): static`.

- [ ] **Step 2: Write `TaxItemRepositoryInterface` and `TaxItemRepository`**

`findOne(TaxYear $taxYear, FiscalBucketEnum $bucket, FiscalRoleEnum $role): ?TaxItem` — `WHERE ti.taxYear = :taxYear AND ti.fiscalBucket = :bucket AND ti.fiscalRole = :role`, `getOneOrNullResult()`. `findByTaxYear(TaxYear $taxYear): array` — `WHERE ti.taxYear = :taxYear`, no particular order needed (the controller indexes the result itself, see Task 5).

- [ ] **Step 3: Generate, migrate, verify**

Run: `php bin/console doctrine:migrations:diff`
Expected: one new migration creating table `tax_item` with the unique index `uniq_tax_item_year_bucket_role` and the FK to `tax_year` (`ON DELETE CASCADE`).

Run: `php bin/console doctrine:migrations:migrate --no-interaction` then `php bin/console doctrine:schema:validate`
Expected: both `[OK]` lines as in Task 1.

- [ ] **Step 4: Commit (propose only)**

```bash
git add src/Entity/TaxItem.php src/Repository/Contract/TaxItemRepositoryInterface.php src/Repository/TaxItemRepository.php migrations/
git commit -m "feat: add TaxItem entity and repository"
```

---

### Task 4: `TaxItemService`

**Files:**
- Create: `src/Service/Tax/Contract/TaxItemServiceInterface.php`
- Create: `src/Service/Tax/TaxItemService.php`

**Interfaces:**
- Consumes: `TaxItemRepositoryInterface::findOne()` (Task 3); `FiscalBucketEnum::acceptsCharges(): bool` (existing).
- Produces: `TaxItemServiceInterface::toggle(TaxYear $taxYear, FiscalBucketEnum $bucket, FiscalRoleEnum $role): TaxItem`.

- [ ] **Step 1: Implement `toggle()`**

```php
public function toggle(TaxYear $taxYear, FiscalBucketEnum $bucket, FiscalRoleEnum $role): TaxItem
{
    if ($role === FiscalRoleEnum::CHARGE && !$bucket->acceptsCharges()) {
        throw new \InvalidArgumentException('Cette catégorie n\'admet pas de charges déductibles.');
    }

    $item = $this->taxItemRepository->findOne($taxYear, $bucket, $role);

    if ($item === null) {
        $item = (new TaxItem())
            ->setSpace($taxYear->getSpace())
            ->setTaxYear($taxYear)
            ->setFiscalBucket($bucket)
            ->setFiscalRole($role)
            ->setDone(true);
        $this->em->persist($item);
    } else {
        $item->setDone(!$item->isDone());
    }

    $this->em->flush();

    return $item;
}
```

Constructor: `EntityManagerInterface $em`, `TaxItemRepositoryInterface $taxItemRepository`.

- [ ] **Step 2: Verify**

Run: `php -l src/Service/Tax/TaxItemService.php`
Expected: `No syntax errors detected`

Behavioural check (Review Focus #1, #2) happens in Task 9's end-to-end walkthrough.

- [ ] **Step 3: Commit (propose only)**

```bash
git add src/Service/Tax/Contract/TaxItemServiceInterface.php src/Service/Tax/TaxItemService.php
git commit -m "feat: toggle a TaxItem reviewed checkbox"
```

---

## Phase 3 — `TaxYearController`

### Task 5: Routes — index, show, status, regime, note, item toggle

**Files:**
- Create: `src/Controller/Tax/TaxYearController.php`

**Interfaces:**
- Consumes: `TaxYearServiceInterface` (Task 2), `TaxItemServiceInterface` (Task 4), `TaxYearRepositoryInterface::findBySpace()` (Task 1), `TaxItemRepositoryInterface::findByTaxYear()` (Task 3), `TaxSummaryServiceInterface::summarize()` (existing), `LoanChargeServiceInterface::chargesForYear()` (existing), `ActiveSpaceControllerTrait::resolveActiveSpace()` (existing).
- Produces: routes `app_tax_year_index` (`GET /tax/years`), `app_tax_year_show` (`GET /tax/years/{year}`), `app_tax_year_status` (`POST /tax/years/{year}/status`), `app_tax_year_regime` (`POST /tax/years/{year}/regime`), `app_tax_year_note` (`POST /tax/years/{year}/note`), `app_tax_year_item_toggle` (`POST /tax/years/{year}/items/toggle`).

- [ ] **Step 1: Scaffold the controller**

`#[Route('/tax/years', name: 'app_tax_year_')]`, `use ActiveSpaceControllerTrait;`. Constructor: `TaxYearServiceInterface $taxYearService`, `TaxItemServiceInterface $taxItemService`, `TaxYearRepositoryInterface $taxYearRepository`, `TaxItemRepositoryInterface $taxItemRepository`, `TaxSummaryServiceInterface $taxSummaryService`, `LoanChargeServiceInterface $loanChargeService`, `SpaceResolverInterface $spaceResolver` (matches `DeclarationController`'s dependency, required by the trait).

- [ ] **Step 2: `index()`**

`#[Route('', name: 'index')]`. `$space = $this->resolveActiveSpace('VIEW'); if ($space instanceof RedirectResponse) return $space;`. Render `tax/years/index.html.twig` with `'taxYears' => $this->taxYearRepository->findBySpace($space)`, `'currentYear' => (int) $this->clock->now()->format('Y')` (add `ClockInterface $clock` to the constructor for this).

- [ ] **Step 3: `show()`**

```php
#[Route('/{year}', name: 'show', requirements: ['year' => '\d{4}'])]
public function show(int $year): Response
{
    $space = $this->resolveActiveSpace('VIEW');
    if ($space instanceof RedirectResponse) {
        return $space;
    }

    $taxYear = $this->taxYearService->getOrCreate($space, $year);
    $summary = $this->taxSummaryService->summarize($space, $year);

    $itemsByKey = [];
    foreach ($this->taxItemRepository->findByTaxYear($taxYear) as $item) {
        $itemsByKey[$item->getFiscalBucket()->value . '-' . $item->getFiscalRole()->value] = $item->isDone();
    }

    return $this->render('tax/years/show.html.twig', [
        'taxYear' => $taxYear,
        'summary' => $summary,
        'loanCharges' => $this->loanChargeService->chargesForYear($space, $summary->year),
        'itemsByKey' => $itemsByKey,
        'regimeChoices' => $taxYear->getRegimeChoices() ?? [],
    ]);
}
```

- [ ] **Step 4: `status()`**

`#[Route('/{year}/status', name: 'status', methods: ['POST'], requirements: ['year' => '\d{4}'])]`. Resolve space with `'EDIT'`, `getOrCreate()`, manual CSRF `'tax_year_status_' . $taxYear->getId()`. Read `$to = $request->request->get('to')`; wrap in `try`: `TaxYearStatusEnum::from($to)` then `$this->taxYearService->transition($taxYear, ...)`; `catch (\ValueError|\InvalidArgumentException $e)` → flash error with `$e->getMessage()` (default message for `\ValueError`: `'Statut invalide.'`). On success, success flash. Redirect to `app_tax_year_show` with `['year' => $year]` either way.

- [ ] **Step 5: `regime()`**

`#[Route('/{year}/regime', name: 'regime', methods: ['POST'], requirements: ['year' => '\d{4}'])]`. Same resolve/CSRF shape (`'tax_year_regime_' . $taxYear->getId()`). Read `$bucketValue = $request->request->get('bucket')`, `$regime = $request->request->get('regime') ?: null`; `try`: `FiscalBucketEnum::from($bucketValue)` then `setRegimeChoice()`; `catch (\ValueError|\InvalidArgumentException $e)` → flash error. Redirect to `app_tax_year_show`.

- [ ] **Step 6: `note()`**

`#[Route('/{year}/note', name: 'note', methods: ['POST'], requirements: ['year' => '\d{4}'])]`. Same resolve/CSRF shape (`'tax_year_note_' . $taxYear->getId()`). `$this->taxYearService->setNote($taxYear, $request->request->get('note'))`, success flash, redirect to `app_tax_year_show`.

- [ ] **Step 7: `itemToggle()`**

`#[Route('/{year}/items/toggle', name: 'item_toggle', methods: ['POST'], requirements: ['year' => '\d{4}'])]`. Same resolve/CSRF shape (`'tax_year_item_toggle_' . $taxYear->getId()`). Read `bucket`/`role` from the request; `try`: `FiscalBucketEnum::from(...)`, `FiscalRoleEnum::from(...)`, then `$this->taxItemService->toggle(...)`; `catch (\ValueError|\InvalidArgumentException $e)` → flash error. Redirect to `app_tax_year_show`.

- [ ] **Step 8: Verify**

Run: `php bin/console debug:router app_tax_year_index` ... (repeat for `app_tax_year_show`, `app_tax_year_status`, `app_tax_year_regime`, `app_tax_year_note`, `app_tax_year_item_toggle`)
Expected: all six routes listed with the methods/paths above.

- [ ] **Step 9: Commit (propose only)**

```bash
git add src/Controller/Tax/TaxYearController.php
git commit -m "feat: add TaxYear review routes"
```

---

## Phase 4 — Frontend

### Task 6: Extract `_summary.html.twig` and `_year_switcher.html.twig` from the declaration page

Pure refactor — the declaration page's rendered output must be byte-identical afterwards. This is what makes Task 7/8 able to reuse it without duplicating the bucket/loan-charges/notices markup (project's factorisation rule: a block used by 2+ pages is shared).

**Files:**
- Create: `templates/tax/_summary.html.twig`
- Create: `templates/tax/_year_switcher.html.twig`
- Modify: `templates/tax/declaration/index.html.twig`

- [ ] **Step 1: Write `templates/tax/_year_switcher.html.twig`**

```twig
{# routeName = route to link each year to. currentYear = int. availableYears = int[]. #}
<div class="dec-years">
    {% for year in availableYears %}
        <a href="{{ path(routeName, {year: year}) }}"
           class="dec-year{{ year == currentYear ? ' is-active' : '' }}">{{ year }}</a>
    {% endfor %}
</div>
```

- [ ] **Step 2: Write `templates/tax/_summary.html.twig`**

Move the entire body currently between `{% if summary.hasNothing %}` and the matching `{% endif %}` in `templates/tax/declaration/index.html.twig` (lines 26–208 as read during planning: the empty state, `dec-totals`, the buckets loop, the loan-charges table + warning notice, the outside-scope notice, and the "ce que cette page ne calcule pas" notice) into this file unchanged, with one addition: the bucket include gains three new optional variables, defaulted so the declaration page's behaviour does not change:

```twig
{% include 'tax/declaration/_bucket.html.twig' with {
    bucket: bucket,
    currency: summary.currency,
    editable: editable|default(false),
    taxYear: taxYear|default(null),
    itemsByKey: itemsByKey|default({}),
    regimeChoices: regimeChoices|default({}),
} %}
```

Keep every other line (the `summary`, `loanCharges` variables, the `app_category_index`/`app_transaction_index`/`app_loan_index` links) exactly as they read today.

- [ ] **Step 3: Update `templates/tax/declaration/index.html.twig`**

Replace the `dec-years` block with `{% include 'tax/_year_switcher.html.twig' with {routeName: 'app_declaration_index', currentYear: summary.year, availableYears: summary.availableYears} %}`. Replace everything from `{% if summary.hasNothing %}` to its matching `{% endif %}` with `{% include 'tax/_summary.html.twig' with {summary: summary, loanCharges: loanCharges} %}` (no `editable`/`taxYear`/`itemsByKey`/`regimeChoices` passed — they default as written in Step 2).

- [ ] **Step 4: Verify**

Run: `php bin/console lint:twig templates/tax/`
Expected: `[OK] All ... Twig files contain valid syntax.`

Manual check: reload `/tax/declaration` in the browser, compare against the page before this task (git stash the change temporarily if needed, or simply confirm visually that totals/buckets/notices/loan-charges table are unchanged).

- [ ] **Step 5: Commit (propose only)**

```bash
git add templates/tax/_summary.html.twig templates/tax/_year_switcher.html.twig templates/tax/declaration/index.html.twig
git commit -m "refactor: extract shared tax summary and year-switcher partials"
```

---

### Task 7: Editable mode on `_bucket.html.twig` — item toggle and regime select

**Files:**
- Modify: `templates/tax/declaration/_bucket.html.twig`
- Create: `templates/tax/declaration/_item_toggle.html.twig`
- Create: `templates/tax/declaration/_regime_select.html.twig`

**Interfaces:**
- Consumes: routes `app_tax_year_item_toggle`, `app_tax_year_regime` (Task 5).

- [ ] **Step 1: Write `_item_toggle.html.twig`**

```twig
{# taxYear = TaxYear. bucket = FiscalBucketEnum. role = string ('revenu'|'charge'). done = bool. #}
<form method="post" action="{{ path('app_tax_year_item_toggle', {year: taxYear.year}) }}">
    <input type="hidden" name="_token" value="{{ csrf_token('tax_year_item_toggle_' ~ taxYear.id) }}">
    <input type="hidden" name="bucket" value="{{ bucket.value }}">
    <input type="hidden" name="role" value="{{ role }}">
    <twig:Button
        type="submit"
        icon="{{ done ? 'check-square' : 'square' }}"
        label="{{ done ? 'Reporté sur la déclaration' : 'Marquer reporté' }}"
        variant="ghost"
    />
</form>
```

Same shape as `templates/real_estate/lease/forms/_toggle.html.twig`.

- [ ] **Step 2: Write `_regime_select.html.twig`**

```twig
{# taxYear = TaxYear. bucket = FiscalBucketEnum. current = 'reel'|'micro'|null. #}
<form method="post" action="{{ path('app_tax_year_regime', {year: taxYear.year}) }}" class="dec-regime-form">
    <input type="hidden" name="_token" value="{{ csrf_token('tax_year_regime_' ~ taxYear.id) }}">
    <input type="hidden" name="bucket" value="{{ bucket.value }}">
    <label>
        Régime retenu
        <select name="regime" onchange="this.form.requestSubmit()">
            <option value=""{{ current is null ? ' selected' : '' }}>— à choisir —</option>
            <option value="reel"{{ current == 'reel' ? ' selected' : '' }}>{{ bucket.reelLabel() }}</option>
            <option value="micro"{{ current == 'micro' ? ' selected' : '' }}>{{ bucket.microLabel() }}</option>
        </select>
    </label>
</form>
```

`onchange="this.form.requestSubmit()"` is a plain HTML form auto-submit, not a Stimulus controller — consistent with the project's preference to avoid JS when a native form behaviour suffices.

- [ ] **Step 3: Wire both into `_bucket.html.twig`**

Add the four new Twig variables at the top of the file's doc comment (`editable`, `taxYear`, `itemsByKey`, `regimeChoices`, all with safe defaults via `|default(...)` at each use site so the declaration page's existing call sites keep working unchanged). In the revenu side (`dec-bucket-side` for revenus), right after the side's "Total" foot block (only when `bucket.revenus|length > 0`), add, when `editable`:

```twig
{% if editable %}
    {% include 'tax/declaration/_item_toggle.html.twig' with {
        taxYear: taxYear,
        bucket: bucket.bucket,
        role: 'revenu',
        done: itemsByKey[bucket.bucket.value ~ '-revenu'] ?? false,
    } %}
{% endif %}
```

Mirror this in the charges side, guarded by the existing `bucket.charges|length > 0` branch, with `role: 'charge'` and key `bucket.bucket.value ~ '-charge'` (these string literals match `FiscalRoleEnum::REVENU`/`CHARGE`'s backing values, and the controller's `item_toggle` action turns them back into the enum via `FiscalRoleEnum::from()` — see Task 5 Step 7). In the `dec-regimes` block, right after the opening `<div class="dec-regimes">`, add, when `editable and micro is not null`:

```twig
{% if editable and micro is not null %}
    {% include 'tax/declaration/_regime_select.html.twig' with {
        taxYear: taxYear,
        bucket: bucket.bucket,
        current: regimeChoices[bucket.bucket.value] ?? null,
    } %}
{% endif %}
```

- [ ] **Step 4: Verify**

Run: `php bin/console lint:twig templates/tax/declaration/`
Expected: `[OK]` with the updated file count.

Manual check: reload `/tax/declaration` (still `editable=false` by default) and confirm no checkbox/select appears — the read-only page is untouched.

- [ ] **Step 5: Commit (propose only)**

```bash
git add templates/tax/declaration/_bucket.html.twig templates/tax/declaration/_item_toggle.html.twig templates/tax/declaration/_regime_select.html.twig
git commit -m "feat: add editable item-toggle and regime-select to the bucket partial"
```

---

### Task 8: `tax/years/index.html.twig` and `tax/years/show.html.twig`

**Files:**
- Create: `templates/tax/years/index.html.twig`
- Create: `templates/tax/years/show.html.twig`
- Create: `templates/tax/years/forms/_status.html.twig`
- Create: `templates/tax/years/forms/_note.html.twig`

**Interfaces:**
- Consumes: `taxYears`/`currentYear` (Task 5's `index()`); `taxYear`/`summary`/`loanCharges`/`itemsByKey`/`regimeChoices` (Task 5's `show()`); `tax/_year_switcher.html.twig`, `tax/_summary.html.twig` (Task 6); routes `app_tax_year_status`, `app_tax_year_note` (Task 5).

- [ ] **Step 1: Write `templates/tax/years/index.html.twig`**

`{% extends 'layout/app.html.twig' %}`, `<twig:PageHeader title="Années fiscales" subtitle="Statut de revue, par année" />`. If `taxYears` is empty: `<twig:EmptyState icon="calendar-range" text="Aucune revue commencée" hint="Ouvre l'année en cours pour démarrer la revue de ta déclaration." ctaLabel="Revoir {{ currentYear }}" ctaHref="{{ path('app_tax_year_show', {year: currentYear}) }}" />`. Otherwise a `data-table` (one row per `TaxYear`: year, status badge via `<twig:Badge label="{{ taxYear.status.label() }}" variant="..." />` — `info` for draft, `warn` for filed, `positive` for paid — and a "Revoir" link to `app_tax_year_show`), plus, above or below the table, a permanent link to the current year's review (`path('app_tax_year_show', {year: currentYear})`) so a year not yet in the list is still reachable.

- [ ] **Step 2: Write `templates/tax/years/forms/_status.html.twig`**

```twig
{# taxYear = TaxYear. #}
{% set order = ['draft', 'filed', 'paid'] %}
{% set index = {'draft': 0, 'filed': 1, 'paid': 2}[taxYear.status.value] %}
<div class="dec-status-actions">
    {% if index > 0 %}
        {{ _self.button(taxYear, order[index - 1], 'Revenir en ' ~ order[index - 1]) }}
    {% endif %}
    {% if index < 2 %}
        {{ _self.button(taxYear, order[index + 1], 'Marquer ' ~ order[index + 1]) }}
    {% endif %}
</div>

{% macro button(taxYear, to, label) %}
    <form method="post" action="{{ path('app_tax_year_status', {year: taxYear.year}) }}">
        <input type="hidden" name="_token" value="{{ csrf_token('tax_year_status_' ~ taxYear.id) }}">
        <input type="hidden" name="to" value="{{ to }}">
        <twig:Button type="submit" label="{{ label }}" variant="secondary" />
    </form>
{% endmacro %}
```

Two buttons at most (previous/next adjacent status), matching `TaxYearService::transition()`'s one-step-at-a-time guard. Labels are a first draft — replace `'Revenir en ' ~ order[index - 1]` / `'Marquer ' ~ order[index + 1]` with the French labels from `TaxYearStatusEnum::label()` if the raw enum values read awkwardly in the UI (e.g. `"Marquer déposée"`, `"Marquer payée"`, `"Revenir en brouillon"`).

- [ ] **Step 3: Write `templates/tax/years/forms/_note.html.twig`**

```twig
{# taxYear = TaxYear. #}
<form method="post" action="{{ path('app_tax_year_note', {year: taxYear.year}) }}" class="dec-note-form">
    <input type="hidden" name="_token" value="{{ csrf_token('tax_year_note_' ~ taxYear.id) }}">
    <label>
        Note
        <textarea name="note" rows="2">{{ taxYear.note }}</textarea>
    </label>
    <twig:Button type="submit" label="Enregistrer" variant="secondary" />
</form>
```

- [ ] **Step 4: Write `templates/tax/years/show.html.twig`**

`{% extends 'layout/app.html.twig' %}`, same `stylesheets` block as `tax/declaration/index.html.twig` (reuses `tax/declaration/_styles.html.twig`). `<twig:PageHeader title="Revue {{ taxYear.year }}" subtitle="Coche ce qui est reporté sur la déclaration officielle" />`. Inside `.page-content.dec-stack`: include `tax/_year_switcher.html.twig` with `routeName: 'app_tax_year_show'`; a status badge + `forms/_status.html.twig`; `forms/_note.html.twig`; then `{% include 'tax/_summary.html.twig' with {summary: summary, loanCharges: loanCharges, editable: true, taxYear: taxYear, itemsByKey: itemsByKey, regimeChoices: regimeChoices} %}`.

- [ ] **Step 5: Verify**

Run: `php bin/console lint:twig templates/tax/`
Expected: `[OK]` with the updated file count.

- [ ] **Step 6: Manual end-to-end check (Phase 4)**

Via the browser: open `/tax/years`, follow the link to the current year, confirm it lands on `/tax/years/{year}` with the same buckets as `/tax/declaration` plus checkboxes and (where applicable) a regime select. Toggle a revenu checkbox on and off (Review Focus #1), attempt the charge checkbox on a bucket with no charges accepted if one is visible (RCM — Review Focus #2, or confirm the UI simply never renders it there since `charges|length == 0` for RCM). Select `micro` on a bucket that allows it, confirm the choice persists on reload. Click "Marquer déposée" then confirm the "Revenir en brouillon"/"Marquer payée" buttons are the only two now shown (Review Focus #4); attempting the old draft→paid button should no longer be on screen at all (the UI only ever offers adjacent transitions). Switch the active space (if more than one exists) and confirm `/tax/years` shows an independent, empty list (Review Focus #5).

- [ ] **Step 7: Commit (propose only)**

```bash
git add templates/tax/years/
git commit -m "feat: add tax year review pages"
```

---

### Task 9: Wire the sidebar navigation

**Files:**
- Modify: `templates/layout/sidebar.html.twig`

- [ ] **Step 1: Point "Années fiscales" at the new index**

Replace the placeholder `<a href="#" class="nav-item nav-item--sub">...Années fiscales</a>` with `<a href="{{ path('app_tax_year_index') }}" class="nav-item nav-item--sub{{ _route starts with 'app_tax_year_' ? ' is-active' : '' }}">`, keeping its existing icon/label.

- [ ] **Step 2: Remove the "Éléments fiscaux" placeholder**

Delete the `<a href="#" class="nav-item nav-item--sub">...Éléments fiscaux</a>` block — this plan merges `TaxItem` management into the year review page, there is no standalone list for it (see spec's *Controllers & routes* section: one `TaxYearController`, no `TaxItemController`).

Also update the `<details class="nav-group">` opening condition on this group (currently `_route starts with 'app_declaration_'`) to `_route starts with 'app_declaration_' or _route starts with 'app_tax_year_'`, so the group stays open while browsing the new pages.

- [ ] **Step 3: Verify**

Run: `php bin/console lint:twig templates/layout/`
Expected: `[OK]`

Manual check: navigate to `/tax/years` and confirm the "Impôts" nav group is open and "Années fiscales" is highlighted.

- [ ] **Step 4: Commit (propose only)**

```bash
git add templates/layout/sidebar.html.twig
git commit -m "feat: wire tax year review into the sidebar"
```

---

## Phase 5 — Documentation

### Task 10: Correct `ARCHITECTURE.md` and `MODULES.md`

**Files:**
- Modify: `ARCHITECTURE.md`
- Modify: `MODULES.md`

- [ ] **Step 1: `ARCHITECTURE.md`**

In the ERD text, remove `TaxItem (N) ──── (0..1) Transaction [nullable FK]` and `TaxItem (N) ──── (0..1) Property [nullable FK]` (no longer part of the entity — see Task 3). In *Service Layer*'s `Tax/` block, correct `TaxYearService.php`'s description from `# Aggregate, calculate, export` to something reflecting the actual implementation (status transitions, regime choice, note — not aggregation or export, which stay out of scope per the spec's non-objectifs), and add `TaxItemService.php` with a one-line description (`# Toggle a reviewed checkbox per bucket × role`). In *Controller Structure*'s `Tax/` block, remove the separate `TaxItemController.php` row and correct `TaxYearController.php`'s description to list its five routes' purpose instead of the original one-line placeholder.

- [ ] **Step 2: `MODULES.md`**

In Module 4 (Tax), replace the `TaxItem` entity code block (currently `id, space_id, tax_year_id (FK), transaction_id (FK nullable), property_id (FK nullable), kind (to_declare|to_deduct|to_pay), label, amount (decimal nullable), note, done (bool), created_at, updated_at`) with the bucket × role schema from the spec (`id, space_id, tax_year_id (FK), fiscal_bucket (enum), fiscal_role (enum), done (bool), created_at, updated_at`). Update the `TaxYear` entity block to add `regime_choices (JSON, nullable)`. Replace the *Workflow: Annual Tax Summary* section's `GenerateTaxSummaryCommand`-based flow (which was never implemented and this plan does not implement either) with a short description of the real flow: open `/tax/years/{year}` (auto-creates a `draft` `TaxYear`), toggle reviewed lines, choose a regime per bucket where applicable, move the status forward as the declaration progresses.

- [ ] **Step 3: Verify**

Run: `grep -rn "to_declare\|to_deduct\|to_pay\|GenerateTaxSummaryCommand" ARCHITECTURE.md MODULES.md`
Expected: no output (every stale mention of the old `TaxItem.kind` values and the never-built command is gone).

- [ ] **Step 4: Commit (propose only)**

```bash
git add ARCHITECTURE.md MODULES.md
git commit -m "docs: replace TaxItem per-transaction sketch with the implemented bucket x role review"
```
