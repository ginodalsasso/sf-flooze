# RentPayment & LoanPayment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish the Real Estate module by letting a user confirm a rent payment (encaissé/impayé) and a loan instalment payment, each creating its linked `Transaction`, and make `/tax/declaration`'s loan charges read the frozen confirmed amounts instead of always recomputing the theoretical schedule.

**Architecture:** Two new entities (`RentPayment`, `LoanPayment`) follow the existing `RecurringTransaction` pattern exactly: due occurrences are calculated in a read-only schedule service, nothing is persisted until an explicit service call creates the payment row and its linked `Transaction` inside `$em->wrapInTransaction()`. No Doctrine listener, no cron command — both exist elsewhere in the docs but were never implemented and are being removed from the docs as part of this plan.

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine ORM 3.x, Twig, MySQL 8.0.

**Spec:** [docs/superpowers/specs/2026-10-31-rent-loan-payment-design.md](../specs/2026-10-31-rent-loan-payment-design.md)

## Global Constraints

- Every entity carries `space_id` via `SpaceScopeTrait`; every repository query filters by space (`CLAUDE.md` garde-fou 2).
- Multi-entity writes (`RentPayment`/`LoanPayment` + `Transaction`) happen inside `$this->em->wrapInTransaction(fn () => ...)` — no two separate `flush()` calls for one logical operation (garde-fou 6).
- Every injectable service/repository is typed by its `{Classe}Interface` in the module's `Contract/` subfolder; implementations are `final` (`.claude/rules.md` → *Interfaces*).
- No `new XxxService()` — constructor injection only, `private readonly`, typed on the interface.
- Forms are not a security boundary: every service revalidates that posted `Account`/`Category` belong to the same `space` as the `Lease`/`Loan`, exactly like `RecurringTransactionService::guardInSpace()`.
- No soft delete on `RentPayment`/`LoanPayment` — once confirmed, a payment is a fact, same as a materialised `RecurringTransaction` occurrence.
- **No PHPUnit tests are written in this plan** (standing project preference: `tests/` currently has no test classes at all, and `lint:twig` + `doctrine:schema:validate` are the project's accepted verification tools). Each task's verification step uses `doctrine:schema:validate`, `doctrine:migrations:diff` (expect no pending diff after migrating), `lint:twig templates/`, and for business logic a one-off manual check via `php bin/console doctrine:query:sql` or the actual UI — never a committed test file.
- Every commit is proposed, not run automatically — wait for the user's explicit go-ahead before `git commit` (standing instruction, applies to every task below, including docs-only ones).

## Review Focus

1. **Lease not yet started** (`lease.startDate` in the future): `LeaseRentScheduleService::nextDue()` must return `null`, not a negative-backlog occurrence — the loop bound `$start > $end` guards this.
2. **Lease closed mid-cycle** (`isActive() === false`) with a due occurrence never confirmed: `nextDue()` returns `null` immediately — a closed lease stops producing due rent, it does not retroactively erase the unconfirmed month's trace (it simply stops asking).
3. **Loan fully amortised** (`findMaxMonthNumber($loan)` already equals the schedule's last `monthNumber`): `LoanPaymentService::confirm()` must throw `\InvalidArgumentException`, not call `TransactionService::save()` with a non-existent row.
4. **Posted `due_date`/account/category from another space**: both `RentPaymentService` and `LoanPaymentService` must reject before any persist — covered by the `guardInSpace` tasks below, verified by one manual cross-space attempt per service during the phase's end-to-end check.
5. **A year with some months confirmed and some only projected** (`LoanChargeService::chargesForYear()`): the blended total must equal confirmed months' frozen amounts + still-projected months' theoretical amounts, not one or the other for the whole year — verified manually in Task 14 by confirming one month of a multi-line loan and reloading `/tax/declaration`.

---

## Phase 1 — RentPayment

### Task 1: `RentPaymentStatusEnum`, `RentPayment` entity, migration, repository

**Files:**
- Create: `src/Enum/RentPaymentStatusEnum.php`
- Create: `src/Entity/RentPayment.php`
- Create: `src/Repository/Contract/RentPaymentRepositoryInterface.php`
- Create: `src/Repository/RentPaymentRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (generated)

**Interfaces:**
- Produces: `RentPaymentStatusEnum::{PAID,LATE}` with `label(): string`; `RentPayment` with fluent setters `setSpace(Space)`, `setLease(Lease)`, `setTransaction(?Transaction)`, `setAmount(string)`, `setDueDate(\DateTimeImmutable)`, `setPaidDate(?\DateTimeImmutable)`, `setStatus(RentPaymentStatusEnum)` and matching getters; `RentPaymentRepositoryInterface::findBySpace(Space): array`, `::findByLease(Lease): array`, `::findByLeaseAndDueDate(Lease, \DateTimeImmutable): ?RentPayment`.

- [ ] **Step 1: Write `RentPaymentStatusEnum`**

`string`-backed enum, cases `PAID = 'paid'`, `LATE = 'late'`, a `label(): string` method (`'Encaissé'` / `'Impayé'`) — same shape as `App\Enum\AccountTypeEnum::label()`.

- [ ] **Step 2: Write the `RentPayment` entity**

`#[ORM\Entity(repositoryClass: RentPaymentRepository::class)]`, `#[ORM\Table(name: 'rent_payment')]`, `#[ORM\HasLifecycleCallbacks]`. Uses `TimestampTrait` + `SpaceScopeTrait` (no `SoftDeleteTrait`). Fields: `id` (identity), `lease` (`ManyToOne` to `Lease`, `lease_id`, not nullable), `transaction` (`ManyToOne` to `Transaction`, `transaction_id`, nullable), `amount` (`decimal(15,2)`), `dueDate` (`date_immutable`, column `due_date`), `paidDate` (`date_immutable`, nullable, column `paid_date`), `status` (`string`, `enumType: RentPaymentStatusEnum::class`). Follow `src/Entity/Lease.php`'s style exactly (fluent setters returning `static`).

- [ ] **Step 3: Write `RentPaymentRepositoryInterface` and `RentPaymentRepository`**

Mirror `src/Repository/Contract/LeaseRepositoryInterface.php` / `src/Repository/LeaseRepository.php`. `findBySpace(Space $space): array` — `WHERE rp.space = :space ORDER BY rp.dueDate DESC`. `findByLease(Lease $lease): array` — `WHERE rp.lease = :lease AND rp.space = :space ORDER BY rp.dueDate DESC`. `findByLeaseAndDueDate(Lease $lease, \DateTimeImmutable $dueDate): ?RentPayment` — `WHERE rp.lease = :lease AND rp.space = :space AND rp.dueDate = :dueDate`, `getOneOrNullResult()`.

- [ ] **Step 4: Generate and review the migration**

Run: `php bin/console doctrine:migrations:diff`
Expected: one new migration creating table `rent_payment` with the columns above, FKs to `lease`, `transaction`, `space`.

- [ ] **Step 5: Migrate and verify**

Run: `php bin/console doctrine:migrations:migrate --no-interaction` then `php bin/console doctrine:schema:validate`
Expected: `[OK] The mapping files are correct.` and `[OK] The database schema is in sync with the mapping files.`

- [ ] **Step 6: Commit (propose only — wait for the user's go-ahead)**

```bash
git add src/Enum/RentPaymentStatusEnum.php src/Entity/RentPayment.php src/Repository/Contract/RentPaymentRepositoryInterface.php src/Repository/RentPaymentRepository.php migrations/
git commit -m "feat: add RentPayment entity and repository"
```

---

### Task 2: `LeaseRentScheduleService`

**Files:**
- Create: `src/Dto/RealEstate/RentDueDto.php`
- Create: `src/Service/RealEstate/Contract/LeaseRentScheduleServiceInterface.php`
- Create: `src/Service/RealEstate/LeaseRentScheduleService.php`

**Interfaces:**
- Consumes: `DateShifterInterface::addMonths(\DateTimeImmutable, int): \DateTimeImmutable` (Task done, existing); `LeaseTermServiceInterface::currentTerm(Lease): LeaseTermDto` (existing, `LeaseTermDto` has public `endDate`); `RentPaymentRepositoryInterface::findByLeaseAndDueDate()` (Task 1).
- Produces: `RentDueDto { Lease $lease; \DateTimeImmutable $dueDate; string $amount; int $backlog; }`; `LeaseRentScheduleServiceInterface::nextDue(Lease $lease, \DateTimeImmutable $today): ?RentDueDto`.

- [ ] **Step 1: Write `RentDueDto`**

`final readonly class` with the four public properties above, constructor-promoted — same shape as `App\Dto\Finance\DueOccurrenceDto`.

- [ ] **Step 2: Implement `LeaseRentScheduleService::nextDue()`**

```php
public function nextDue(Lease $lease, \DateTimeImmutable $today): ?RentDueDto
{
    if (!$lease->isActive()) {
        return null;
    }

    $today = $today->setTime(0, 0);
    $start = $lease->getStartDate()->setTime(0, 0);
    $end = min($today, $this->leaseTermService->currentTerm($lease)->endDate);

    if ($start > $end) {
        return null;
    }

    $amount = bcadd($lease->getRent(), $lease->getCharges(), 2);
    $dueDates = [];
    $month = 0;

    while (($candidate = $this->dateShifter->addMonths($start, $month)) <= $end) {
        if ($this->rentPaymentRepository->findByLeaseAndDueDate($lease, $candidate) === null) {
            $dueDates[] = $candidate;
        }
        ++$month;
    }

    if ($dueDates === []) {
        return null;
    }

    return new RentDueDto($lease, $dueDates[0], $amount, count($dueDates) - 1);
}
```

Month index `0` is the lease's own `start_date` — the first rent is due at move-in, unlike a loan's first instalment which falls one month after the funds are released. Constructor takes `DateShifterInterface $dateShifter`, `LeaseTermServiceInterface $leaseTermService`, `RentPaymentRepositoryInterface $rentPaymentRepository`.

- [ ] **Step 3: Verify manually**

Run: `php bin/console doctrine:query:sql "SELECT 1"` is not useful here — instead, once Task 6 (controller) exists, exercise this through the lease show page (Phase 1 end-to-end check, see Task 7). No standalone check for this task beyond `php -l` on the new files and the Phase 1 manual walkthrough.

Run: `php -l src/Service/RealEstate/LeaseRentScheduleService.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Commit (propose only)**

```bash
git add src/Dto/RealEstate/RentDueDto.php src/Service/RealEstate/Contract/LeaseRentScheduleServiceInterface.php src/Service/RealEstate/LeaseRentScheduleService.php
git commit -m "feat: calculate the next due rent occurrence of a lease"
```

---

### Task 3: `RentPaymentService`

**Files:**
- Create: `src/Service/RealEstate/Contract/RentPaymentServiceInterface.php`
- Create: `src/Service/RealEstate/RentPaymentService.php`

**Interfaces:**
- Consumes: `LeaseRentScheduleServiceInterface::nextDue()` (Task 2); `TransactionServiceInterface::save(TransactionInputDto): Transaction` (existing, `src/Service/Finance/TransactionService.php`); `RentPaymentRepositoryInterface` is not needed directly here (service only persists via `EntityManagerInterface`).
- Produces: `RentPaymentServiceInterface::confirm(Lease $lease, \DateTimeImmutable $dueDate, Account $account, ?Category $category): RentPayment`; `::markLate(Lease $lease, \DateTimeImmutable $dueDate): RentPayment`.

- [ ] **Step 1: Implement guards**

`guardDueDate(Lease $lease, \DateTimeImmutable $dueDate): void` — calls `$this->schedule->nextDue($lease, $this->clock->now())`; throws `\InvalidArgumentException('Cette échéance ne peut pas être traitée : il faut d\'abord régler la plus ancienne.')` when the result is `null` or its `dueDate` does not equal `$dueDate->setTime(0, 0)`.

`guardInSpace(Lease $lease, Account $account, ?Category $category): void` — same shape as `RecurringTransactionService::guardInSpace()`: throws `\InvalidArgumentException(sprintf('Élément hors de cet espace : %s.', $label))` when `$account->getSpace()` or `$category?->getSpace()` differ from `$lease->getSpace()`.

- [ ] **Step 2: Implement `confirm()`**

Calls both guards, then `$this->em->wrapInTransaction(function () use (...) { ... })`: builds `RentPayment` (`status = PAID`, `amount = bcadd(rent, charges, 2)`, `paidDate = $dueDate`), builds a `TransactionInputDto` (`type = TransactionTypeEnum::INCOME`, `amount` = the same, `date = $dueDate`, `description = 'Loyer — ' . $lease->getProperty()->getName()`, `account`/`category` from the arguments), calls `$this->transactionService->save($input)`, sets the result on `RentPayment::setTransaction()`, `persist()` + `flush()` inside the closure, returns the `RentPayment`.

- [ ] **Step 3: Implement `markLate()`**

Calls `guardDueDate()` only (no account/category to validate — no `Transaction` created). Builds `RentPayment` (`status = LATE`, same `amount`, `paidDate = null`), `persist()` + `flush()`, returns it.

- [ ] **Step 4: Verify manually**

Run: `php -l src/Service/RealEstate/RentPaymentService.php`
Expected: `No syntax errors detected`

Full behavioural check happens in Task 7's end-to-end walkthrough (Review Focus items 1, 2, 4 apply here).

- [ ] **Step 5: Commit (propose only)**

```bash
git add src/Service/RealEstate/Contract/RentPaymentServiceInterface.php src/Service/RealEstate/RentPaymentService.php
git commit -m "feat: confirm or mark late a lease's due rent occurrence"
```

---

### Task 4: `RentPaymentConfirmFormType` and `RentPaymentController`

**Files:**
- Create: `src/Form/RealEstate/RentPaymentConfirmFormType.php`
- Create: `src/Controller/RealEstate/RentPaymentController.php`

**Interfaces:**
- Consumes: `RentPaymentServiceInterface::confirm()` / `::markLate()` (Task 3).
- Produces: routes `app_rent_payment_confirm` (`POST /real-estate/leases/{lease}/rent-payments/confirm`), `app_rent_payment_late` (`POST /real-estate/leases/{lease}/rent-payments/late`), both redirecting to `app_lease_show`.

- [ ] **Step 1: Write `RentPaymentConfirmFormType`**

Options: `space` (required, `Space`), same pattern as `RecurringTransactionFormType::configureOptions()`. Fields: `account` (`EntityType`, `class: Account::class`, `query_builder` excluding `AccountTypeEnum::CRYPTO`/`STOCK` like `RecurringTransactionFormType`, `placeholder: 'Choisir un compte…'`, `NotNull` constraint), `category` (`EntityType`, `class: Category::class`, `required: false`, `query_builder: fn (CategoryRepository $repo) => $repo->createSpaceScopedQb($space)`, `choice_label: 'name'`, `placeholder: 'Sans catégorie'`), `dueDate` (`HiddenType`, not mapped — `'mapped' => false`). No `data_class` (this form is not bound to an entity; the controller reads `account`/`category`/`dueDate` off the form data directly, like a DTO-less form).

- [ ] **Step 2: Write `RentPaymentController`**

`#[Route('/real-estate/leases/{lease}/rent-payments', name: 'app_rent_payment_')]`, constructor takes `RentPaymentServiceInterface $rentPaymentService`.

`confirm(Request $request, Lease $lease): Response` — `#[Route('/confirm', name: 'confirm', methods: ['POST'])]`. `denyAccessUnlessGranted('EDIT', $lease->getSpace())`. Build the form with `['space' => $lease->getSpace()]`, `handleRequest($request)`. On valid: parse `dueDate` with `\DateTimeImmutable::createFromFormat('!Y-m-d', $form->get('dueDate')->getData())` (throw `\InvalidArgumentException('Date d\'échéance invalide.')` if `false`, same as `RecurringTransactionController::postedDate()`), call `$this->rentPaymentService->confirm($lease, $dueDate, $form->get('account')->getData(), $form->get('category')->getData())` inside a `try`/`catch (\InvalidArgumentException $e)` that adds a flash error; on success add a success flash. Redirect to `app_lease_show` with `['id' => $lease->getId()]` either way.

`late(Request $request, Lease $lease): Response` — `#[Route('/late', name: 'late', methods: ['POST'])]`. `denyAccessUnlessGranted('EDIT', $lease->getSpace())`, manual CSRF check (`'rent_payment_late_' . $lease->getId()`, same style as `LeaseController::toggle()`), reads `due_date` from `$request->request->get('due_date')` the same way, calls `markLate()`, flash, redirect to `app_lease_show`.

- [ ] **Step 3: Verify**

Run: `php bin/console debug:router app_rent_payment_confirm` and `php bin/console debug:router app_rent_payment_late`
Expected: both routes listed with `POST` method and the `{lease}` placeholder.

- [ ] **Step 4: Commit (propose only)**

```bash
git add src/Form/RealEstate/RentPaymentConfirmFormType.php src/Controller/RealEstate/RentPaymentController.php
git commit -m "feat: add rent payment confirmation routes"
```

---

### Task 5: `app_lease_show` page

**Files:**
- Modify: `src/Controller/RealEstate/LeaseController.php` (add `show()`)
- Create: `templates/real_estate/lease/show.html.twig`
- Create: `templates/real_estate/lease/forms/_confirm_rent.html.twig`
- Create: `templates/real_estate/lease/forms/_late_rent.html.twig`
- Modify: `templates/real_estate/lease/_card.html.twig` (link name + "Détail" button to the new page, same pattern as `loan/_card.html.twig`)

**Interfaces:**
- Consumes: `LeaseRentScheduleServiceInterface::nextDue()` (Task 2), `RentPaymentRepositoryInterface::findByLease()` (Task 1), `RentPaymentConfirmFormType` (Task 4), routes `app_rent_payment_confirm`/`app_rent_payment_late` (Task 4).

- [ ] **Step 1: Add `LeaseController::show()`**

```php
#[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
public function show(Lease $lease): Response
{
    $this->denyAccessUnlessGranted('VIEW', $lease->getSpace());

    return $this->render('real_estate/lease/show.html.twig', [
        'lease' => $lease,
        'nextDue' => $this->rentScheduleService->nextDue($lease, $this->clock->now()),
        'confirmForm' => $this->createForm(RentPaymentConfirmFormType::class, null, ['space' => $lease->getSpace()])->createView(),
        'payments' => $this->rentPaymentRepository->findByLease($lease),
    ]);
}
```

Add `LeaseRentScheduleServiceInterface $rentScheduleService`, `RentPaymentRepositoryInterface $rentPaymentRepository`, `ClockInterface $clock` to the constructor.

- [ ] **Step 2: Write `templates/real_estate/lease/show.html.twig`**

Follow `templates/real_estate/loan/show.html.twig`'s structure: `PageHeader` (title = property name, subtitle = lease type + tenants), a `re-figures` block with the lease's rent/charges/term facts (reuse `lease_term()` Twig function already used in `_card.html.twig`), a due-rent block (if `nextDue` is not null: amount, due date, `backlog` warning via `twig:Notice` when `> 0`, and the two forms below; if null and lease active: "à jour" state; if inactive: nothing), a `data-table` history of `payments` (due date, amount, status badge, paid date, link to the linked `Transaction` when present), and the existing bottom actions (`app_lease_index`, `app_property_show`, `app_lease_edit`, delete form).

- [ ] **Step 3: Write `_confirm_rent.html.twig`**

`{{ form_start(confirmForm, {action: path('app_rent_payment_confirm', {lease: lease.id})}) }}`, sets the hidden `dueDate` field's data via `{{ form_widget(confirmForm.dueDate, {value: nextDue.dueDate|date('Y-m-d')}) }}`, renders `account`/`category`, a `twig:Button` submit labelled "Encaissé", `{{ form_end(confirmForm) }}`.

- [ ] **Step 4: Write `_late_rent.html.twig`**

Plain `<form method="post" action="{{ path('app_rent_payment_late', {lease: lease.id}) }}">` with a hidden `due_date` input (`value="{{ nextDue.dueDate|date('Y-m-d') }}"`), the CSRF hidden field (`{{ csrf_token('rent_payment_late_' ~ lease.id) }}` as `_token`), a `twig:Button` labelled "Impayé" `variant="ghost"` — same shape as `real_estate/lease/forms/_toggle.html.twig`.

- [ ] **Step 5: Update `_card.html.twig`**

Wrap the property-name link (or add a "Détail" `twig:Button` in `re-card-footer`, same as `loan/_card.html.twig`) pointing to `path('app_lease_show', {id: lease.id})`.

- [ ] **Step 6: Verify**

Run: `php bin/console lint:twig templates/real_estate/lease/`
Expected: `[OK] All 7 Twig files contain valid syntax.` (count will match however many lease templates now exist)

Run: `php bin/console debug:router app_lease_show`
Expected: route listed, `GET`, `/real-estate/leases/{id}`

- [ ] **Step 7: Manual end-to-end check (Phase 1)**

Via the browser (`symfony serve`): create a property + lease with `start_date` a few months in the past, open `app_lease_show`, confirm the oldest due rent with an account from the same space → see the flash, the history row, and the matching `Transaction` on the account. Attempt (via browser devtools or a second space) posting an account from another space → expect the flash error, no row created (Review Focus #4). Confirm two months in a row to see the backlog counter decrease. Mark one impayé and confirm it does not block the next due date from one's own space.

- [ ] **Step 8: Commit (propose only)**

```bash
git add src/Controller/RealEstate/LeaseController.php templates/real_estate/lease/
git commit -m "feat: add lease detail page with rent payment confirmation"
```

---

## Phase 2 — LoanPayment

### Task 6: `LoanPayment` entity, migration, repository

**Files:**
- Create: `src/Entity/LoanPayment.php`
- Create: `src/Repository/Contract/LoanPaymentRepositoryInterface.php`
- Create: `src/Repository/LoanPaymentRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (generated)

**Interfaces:**
- Produces: `LoanPayment` with fluent setters `setSpace`, `setLoan`, `setTransaction`, `setMonthNumber(int)`, `setDueDate`, `setCapitalPart(string)`, `setInterestPart(string)`, `setInsurancePart(string)`, `setRemainingCapital(string)`, `setPaidDate`, matching getters; `LoanPaymentRepositoryInterface::findBySpace(Space): array`, `::findByLoan(Loan): array`, `::findMaxMonthNumber(Loan): ?int`, `::findByLoanAndMonth(Loan, int): ?LoanPayment`.

- [ ] **Step 1: Write the `LoanPayment` entity**

`#[ORM\Entity(repositoryClass: LoanPaymentRepository::class)]`, `#[ORM\Table(name: 'loan_payment')]`, `#[ORM\HasLifecycleCallbacks]`, `TimestampTrait` + `SpaceScopeTrait` (no soft delete, no status). Fields: `id`, `loan` (`ManyToOne` to `Loan`, `loan_id`, not nullable), `transaction` (`ManyToOne` to `Transaction`, `transaction_id`, nullable), `monthNumber` (`integer`, column `month_number`), `dueDate` (`date_immutable`, column `due_date`), `capitalPart`/`interestPart`/`insurancePart`/`remainingCapital` (`decimal(15,2)`, columns `capital_part`/`interest_part`/`insurance_part`/`remaining_capital`), `paidDate` (`date_immutable`, column `paid_date`, not nullable — a `LoanPayment` only ever exists once paid).

- [ ] **Step 2: Write `LoanPaymentRepositoryInterface` and `LoanPaymentRepository`**

`findBySpace(Space $space): array` — `WHERE lp.space = :space ORDER BY lp.dueDate DESC`. `findByLoan(Loan $loan): array` — `WHERE lp.loan = :loan AND lp.space = :space ORDER BY lp.monthNumber ASC`. `findMaxMonthNumber(Loan $loan): ?int` — `SELECT MAX(lp.monthNumber) WHERE lp.loan = :loan AND lp.space = :space`, cast `(int)` the scalar result but return `null` when it is `null` (use `getOneOrNullResult()` or check before casting). `findByLoanAndMonth(Loan $loan, int $monthNumber): ?LoanPayment` — `WHERE lp.loan = :loan AND lp.space = :space AND lp.monthNumber = :monthNumber`, `getOneOrNullResult()`.

- [ ] **Step 3: Generate, migrate, verify**

Run: `php bin/console doctrine:migrations:diff`
Expected: one new migration creating table `loan_payment`.

Run: `php bin/console doctrine:migrations:migrate --no-interaction` then `php bin/console doctrine:schema:validate`
Expected: both `[OK]` lines as in Task 1.

- [ ] **Step 4: Commit (propose only)**

```bash
git add src/Entity/LoanPayment.php src/Repository/Contract/LoanPaymentRepositoryInterface.php src/Repository/LoanPaymentRepository.php migrations/
git commit -m "feat: add LoanPayment entity and repository"
```

---

### Task 7: `LoanPaymentService`

**Files:**
- Create: `src/Service/RealEstate/Contract/LoanPaymentServiceInterface.php`
- Create: `src/Service/RealEstate/LoanPaymentService.php`

**Interfaces:**
- Consumes: `LoanAmortizationServiceInterface::schedule(Loan): LoanScheduleDto` (existing — `->combined->rows` is `AmortizationRowDto[]`); `LoanPaymentRepositoryInterface::findMaxMonthNumber()` (Task 6); `TransactionServiceInterface::save()` (existing).
- Produces: `LoanPaymentServiceInterface::nextDueRow(Loan $loan): ?AmortizationRowDto` (public — also used read-only by `LoanController::show()` in Task 9, so the lookup is written once); `::confirm(Loan $loan, Account $account, ?Category $category): LoanPayment`.

- [ ] **Step 1: Implement `nextDueRow()` and the write guards**

Public `nextDueRow(Loan $loan): ?AmortizationRowDto` — `$nextMonth = ($this->loanPaymentRepository->findMaxMonthNumber($loan) ?? 0) + 1`; search `$this->amortizationService->schedule($loan)->combined->rows` for the row whose `monthNumber === $nextMonth` (`array_find`-style loop, or `array_filter` + first); return `null` when not found (loan fully amortised). This method does not check whether the row is actually due yet (`dueDate <= today`) — callers that only display it (Task 9) decide for themselves whether to show a confirm action; `confirm()` below is the one that enforces it.

`guardDue(AmortizationRowDto $row): void` — throws `\InvalidArgumentException('Cette mensualité n\'est pas encore due.')` when `$row->dueDate > $this->clock->now()->setTime(0, 0)`.

`guardInSpace(Loan $loan, Account $account, ?Category $category): void` — same shape as `RentPaymentService::guardInSpace()`, against `$loan->getSpace()`.

- [ ] **Step 2: Implement `confirm()`**

```php
public function confirm(Loan $loan, Account $account, ?Category $category): LoanPayment
{
    $row = $this->nextDueRow($loan);

    if ($row === null) {
        throw new \InvalidArgumentException('Ce prêt est déjà intégralement remboursé.');
    }

    $this->guardDue($row);
    $this->guardInSpace($loan, $account, $category);

    return $this->em->wrapInTransaction(function () use ($loan, $row, $account, $category) {
        $payment = (new LoanPayment())
            ->setSpace($loan->getSpace())
            ->setLoan($loan)
            ->setMonthNumber($row->monthNumber)
            ->setDueDate($row->dueDate)
            ->setCapitalPart($row->capitalPart)
            ->setInterestPart($row->interestPart)
            ->setInsurancePart($row->insurancePart)
            ->setRemainingCapital($row->remainingCapital)
            ->setPaidDate($row->dueDate);

        $input = new TransactionInputDto();
        $input->space = $loan->getSpace();
        $input->account = $account;
        $input->type = TransactionTypeEnum::EXPENSE;
        $input->amount = $row->totalPayment;
        $input->date = $row->dueDate;
        $input->description = 'Mensualité emprunt — ' . $loan->getBankName();
        $input->category = $category;

        $transaction = $this->transactionService->save($input);
        $payment->setTransaction($transaction);

        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    });
}
```

Constructor: `EntityManagerInterface $em`, `TransactionServiceInterface $transactionService`, `LoanAmortizationServiceInterface $amortizationService`, `LoanPaymentRepositoryInterface $loanPaymentRepository`, `ClockInterface $clock`.

- [ ] **Step 3: Verify**

Run: `php -l src/Service/RealEstate/LoanPaymentService.php`
Expected: `No syntax errors detected`

Behavioural check in Task 9's walkthrough (Review Focus #3 and #4 apply here).

- [ ] **Step 4: Commit (propose only)**

```bash
git add src/Service/RealEstate/Contract/LoanPaymentServiceInterface.php src/Service/RealEstate/LoanPaymentService.php
git commit -m "feat: confirm a loan's next due instalment"
```

---

### Task 8: `LoanPaymentConfirmFormType` and `LoanPaymentController`

**Files:**
- Create: `src/Form/RealEstate/LoanPaymentConfirmFormType.php`
- Create: `src/Controller/RealEstate/LoanPaymentController.php`

**Interfaces:**
- Consumes: `LoanPaymentServiceInterface::confirm()` (Task 7).
- Produces: route `app_loan_payment_confirm` (`POST /real-estate/loans/{loan}/payments/confirm`), redirects to `app_loan_show`.

- [ ] **Step 1: Write `LoanPaymentConfirmFormType`**

Same shape as `RentPaymentConfirmFormType` minus the hidden `dueDate` field (the service resolves the due month itself — no date to post or guard against staleness, since there is exactly one next month per loan).

- [ ] **Step 2: Write `LoanPaymentController`**

`#[Route('/real-estate/loans/{loan}/payments', name: 'app_loan_payment_')]`. `confirm(Request $request, Loan $loan): Response` — `#[Route('/confirm', name: 'confirm', methods: ['POST'])]`, `denyAccessUnlessGranted('EDIT', $loan->getSpace())`, build/handle the form, call `$this->loanPaymentService->confirm($loan, $account, $category)` in a `try`/`catch (\InvalidArgumentException $e)`, flash, redirect to `app_loan_show` with `['id' => $loan->getId()]`.

- [ ] **Step 3: Verify**

Run: `php bin/console debug:router app_loan_payment_confirm`
Expected: route listed, `POST`, `/real-estate/loans/{loan}/payments/confirm`

- [ ] **Step 4: Commit (propose only)**

```bash
git add src/Form/RealEstate/LoanPaymentConfirmFormType.php src/Controller/RealEstate/LoanPaymentController.php
git commit -m "feat: add loan payment confirmation route"
```

---

### Task 9: `loan/show.html.twig` additions

**Files:**
- Modify: `src/Controller/RealEstate/LoanController.php` (`show()` gains due row + history + confirm form)
- Modify: `templates/real_estate/loan/show.html.twig`
- Create: `templates/real_estate/loan/forms/_confirm_payment.html.twig`

**Interfaces:**
- Consumes: `LoanPaymentServiceInterface::nextDueRow(Loan): ?AmortizationRowDto` (Task 7), `LoanPaymentRepositoryInterface::findByLoan()` (Task 6), `LoanPaymentConfirmFormType` (Task 8), route `app_loan_payment_confirm` (Task 8).

- [ ] **Step 1: Extend `LoanController::show()`**

Add to the render array: `'nextRow' => $this->loanPaymentService->nextDueRow($loan)`, `'confirmForm' => $this->createForm(LoanPaymentConfirmFormType::class, null, ['space' => $loan->getSpace()])->createView()`, `'payments' => $this->loanPaymentRepository->findByLoan($loan)`. Add `LoanPaymentServiceInterface $loanPaymentService` and `LoanPaymentRepositoryInterface $loanPaymentRepository` to the constructor.

- [ ] **Step 2: Add a due-instalment block and a history table to `show.html.twig`**

Right after the existing `re-figures` block: if `nextRow` is not null and its `dueDate <= today`, render the confirm form (`_confirm_payment.html.twig`) with the row's amount/date; otherwise nothing (no "impayé" concept for loans). Below the existing "Échéancier mensuel combiné" `<details>`, add a `data-table` of `payments` (month number, due date, capital/interest/insurance/total, linked `Transaction`) — this is the *confirmed* history, distinct from the theoretical combined schedule already shown.

- [ ] **Step 3: Write `_confirm_payment.html.twig`**

Same shape as `lease/forms/_confirm_rent.html.twig` minus the hidden date field, posting to `app_loan_payment_confirm`.

- [ ] **Step 4: Verify**

Run: `php bin/console lint:twig templates/real_estate/loan/`
Expected: `[OK]` with the updated file count.

- [ ] **Step 5: Manual end-to-end check (Phase 2)**

Via the browser: on a loan whose first instalment is already due, confirm it, see the flash, the new row in the confirmed history, and the `Transaction` on the chosen account. Confirm a second month and check `month_number` incremented. Attempt to confirm before the computed due date arrives (loan with a future `start_date`) and confirm the form does not appear (or the service rejects it). Fully amortise a short 1-line test loan (small `durationMonths`) and confirm the last month, then attempt one more confirmation and expect the "déjà intégralement remboursé" error (Review Focus #3).

- [ ] **Step 6: Commit (propose only)**

```bash
git add src/Controller/RealEstate/LoanController.php templates/real_estate/loan/
git commit -m "feat: add loan payment confirmation to the loan detail page"
```

---

## Phase 3 — LoanChargeService correction

### Task 10: Blend confirmed `LoanPayment` amounts into `LoanChargeService`

**Files:**
- Modify: `src/Service/RealEstate/LoanChargeService.php`
- Modify: `src/Dto/RealEstate/LoanChargesDto.php` (docblock only — it now blends ledger and projection, no longer "never from the ledger")

**Interfaces:**
- Consumes: `LoanPaymentRepositoryInterface::findByLoanAndMonth(Loan, int): ?LoanPayment` (Task 6).
- Produces: `LoanChargeServiceInterface::chargesForYear()` keeps its existing signature; its *output* for a year with confirmed months now reflects the frozen amounts.

- [ ] **Step 1: Replace `yearOf()` with a row-level blend**

Remove the private `yearOf(array $years, int $year): ?AmortizationYearDto` method (it operated on the pre-aggregated `AmortizationYearDto[]`, which cannot distinguish confirmed months from projected ones). Add:

```php
/** @param AmortizationRowDto[] $rows */
private function rowsOfYear(array $rows, int $year): array
{
    return array_values(array_filter(
        $rows,
        static fn (AmortizationRowDto $row) => (int) $row->dueDate->format('Y') === $year,
    ));
}

/**
 * A confirmed LoanPayment freezes its four amounts at payment time — the theoretical row
 * for that month must not be used once a frozen one exists, or a later rate correction on
 * the LoanLine would silently change interest already declared for a past year.
 *
 * @param AmortizationRowDto[] $rows
 *
 * @return array{interest: string, insurance: string, count: int}
 */
private function blendWithConfirmed(Loan $loan, array $rows): array
{
    $interest = '0.00';
    $insurance = '0.00';

    foreach ($rows as $row) {
        $confirmed = $this->loanPaymentRepository->findByLoanAndMonth($loan, $row->monthNumber);
        $interest = bcadd($interest, $confirmed?->getInterestPart() ?? $row->interestPart, 2);
        $insurance = bcadd($insurance, $confirmed?->getInsurancePart() ?? $row->insurancePart, 2);
    }

    return ['interest' => $interest, 'insurance' => $insurance, 'count' => count($rows)];
}
```

- [ ] **Step 2: Update `chargesForYear()` to use the blend**

Replace the body's use of `$this->yearOf($this->amortizationService->schedule($loan)->combined->years, $year)` with `$rows = $this->rowsOfYear($this->amortizationService->schedule($loan)->combined->rows, $year); if ($rows === []) { continue; } $blend = $this->blendWithConfirmed($loan, $rows);` and build `LoanYearChargeDto($loan, $blend['interest'], $blend['insurance'], $blend['count'])` — same assembly as before, just fed by `$blend` instead of `$yearTotals`.

- [ ] **Step 3: Add the new constructor dependency**

Add `LoanPaymentRepositoryInterface $loanPaymentRepository` to `LoanChargeService`'s constructor (alongside the existing `LoanRepositoryInterface`, `LoanAmortizationServiceInterface`).

- [ ] **Step 4: Update `LoanChargesDto`'s docblock**

Replace "Derived from the schedules, not from the ledger: these amounts are what the loans *will* cost that year, not what has been recorded as paid." with a line saying the opposite is now true for already-confirmed months: those come from the frozen `LoanPayment` rows and do not move if a `LoanLine`'s rate is corrected afterwards; only still-unconfirmed months remain a projection.

- [ ] **Step 5: Verify**

Run: `php -l src/Service/RealEstate/LoanChargeService.php`
Expected: `No syntax errors detected`

Run: `php bin/console doctrine:schema:validate`
Expected: both `[OK]` lines (no entity change here, just confirming nothing broke).

Manual check (Review Focus #5): on a loan with at least 13 months of history, confirm one `LoanPayment` for a month in year Y, then edit that `LoanLine`'s `rate`. Reload `/tax/declaration` for year Y: the confirmed month's interest must stay the pre-edit amount; any other month of year Y (not yet confirmed) must reflect the new rate.

- [ ] **Step 6: Commit (propose only)**

```bash
git add src/Service/RealEstate/LoanChargeService.php src/Dto/RealEstate/LoanChargesDto.php
git commit -m "fix: LoanChargeService uses frozen LoanPayment amounts for confirmed months"
```

---

## Phase 4 — Documentation

### Task 11: Correct `ARCHITECTURE.md`, `CLAUDE.md`, `MODULES.md`

**Files:**
- Modify: `ARCHITECTURE.md`
- Modify: `CLAUDE.md`
- Modify: `MODULES.md`

- [ ] **Step 1: `ARCHITECTURE.md`**

Remove the `LinkedTransactionListener` row from the *Event Listeners* table (`| LinkedTransactionListener | postPersist RentPayment/LoanPayment | Auto-create linked Transaction |`). Remove the `GenerateRentPaymentsCommand` row from the *Console Commands* table. Replace the *Transaction as Financial Backbone* code block's two `LinkedTransactionListener` lines with `RentPayment confirmed → RentPaymentService::confirm() → Transaction(income) created` / `LoanPayment confirmed → LoanPaymentService::confirm() → Transaction(expense) created`. Add `RentPaymentService.php`, `LoanPaymentService.php`, `LeaseRentScheduleService.php` to the `RealEstate/` block of *Service Layer*, and `RentPaymentController.php`, `LoanPaymentController.php` to *Controller Structure*'s `RealEstate/` block.

- [ ] **Step 2: `CLAUDE.md`**

In *Décisions clés*, correct the bullet "`RentPayment` et `LoanPayment` portent leur `Transaction` via `LinkedTransactionListener`, créée au passage à `paid`, jamais à la création de l'échéance." to describe the explicit-service pattern instead (parallel to the already-existing bullet about `RecurringTransaction`/`TransactionService::save()` just below it in the same file).

- [ ] **Step 3: `MODULES.md`**

In Module 2 → `LoanPayment`, remove "*pas encore implémenté*" and describe the confirmation workflow actually built (mirroring the `RentPayment` workflow text already present just above it).

- [ ] **Step 4: Verify**

Run: `php bin/console lint:twig templates/` (no template changes here, but confirms nothing else broke across the session) — optional given no twig touched; skip if Task 9 already ran it clean.

Read the three diffs once to confirm no other stale `LinkedTransactionListener`/`GenerateRentPaymentsCommand` mention remains:
Run: `grep -rn "LinkedTransactionListener\|GenerateRentPaymentsCommand" ARCHITECTURE.md CLAUDE.md MODULES.md`
Expected: no output.

- [ ] **Step 5: Commit (propose only)**

```bash
git add ARCHITECTURE.md CLAUDE.md MODULES.md
git commit -m "docs: replace LinkedTransactionListener/cron with the implemented confirm() pattern"
```
