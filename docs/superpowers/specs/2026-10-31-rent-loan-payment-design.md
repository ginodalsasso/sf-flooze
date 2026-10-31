# RentPayment & LoanPayment — design

Date: 2026-10-31
Status: approved for planning

## Contexte

Le module Immobilier (Module 2) a `Property`, `Tenant`, `Lease` (avec reconduction
tacite via `LeaseTermService`) et `Loan`/`LoanLine` (avec échéancier calculé en
lecture via `LoanAmortizationService`). Il lui manque les deux entités qui
matérialisent un encaissement de loyer et un remboursement de prêt :
`RentPayment` et `LoanPayment`. Sans elles, aucun flux immobilier n'entre
jamais dans `Transaction`, qui est la source de vérité unique du module
Finance (voir `CLAUDE.md` → *Décisions clés*).

Le pattern à suivre existe déjà dans le code pour `RecurringTransaction` :
échéances calculées en lecture, aucune génération automatique, confirmation
explicite qui crée la `Transaction` via `TransactionService::save()`. Ce spec
applique ce même pattern à RentPayment et LoanPayment plutôt que le
`LinkedTransactionListener` mentionné dans `ARCHITECTURE.md`/`CLAUDE.md`, qui
n'a jamais été écrit et qui introduirait un effet de bord caché (listener)
inexistant ailleurs dans le code.

## Objectifs

- Permettre de confirmer un loyer encaissé ou impayé pour chaque échéance due
  d'un `Lease` actif, en créant la `Transaction` de revenu correspondante.
- Permettre de confirmer une mensualité de prêt payée pour chaque échéance due
  d'un `Loan`, en figeant la répartition capital/intérêts/assurance/reste-dû
  et en créant la `Transaction` de dépense correspondante.
- Corriger `LoanChargeService` (utilisé par `/tax/declaration`) pour qu'il
  utilise les montants figés des `LoanPayment` déjà confirmés plutôt que de
  toujours recalculer depuis l'échéancier théorique — sans quoi l'invariant
  "les intérêts déjà déclarés ne bougent pas si le taux est corrigé" reste
  faux en pratique.
- Mettre à jour `ARCHITECTURE.md`, `CLAUDE.md` et `MODULES.md` pour que la
  documentation reflète le pattern réellement implémenté.

## Non-objectifs

- Pas de génération automatique, de cron ou de listener Doctrine créant une
  `RentPayment`/`LoanPayment`/`Transaction` sans action explicite de
  l'utilisateur.
- Pas de notion d'impayé pour `LoanPayment` (pas dans l'ERD — hors périmètre).
- Pas d'export PDF de l'échéancier (`LoanAmortizationPdfGenerator`, déjà
  identifié comme *à faire* ailleurs).
- Pas de tests PHPUnit écrits dans le cadre de cette feature (à la demande
  explicite de l'utilisateur uniquement, cf. mémoire projet).

## Entités & migrations

### `RentPayment`

```
id, space_id (FK), lease_id (FK), transaction_id (FK nullable),
amount (decimal 15,2), due_date (date_immutable), paid_date (date_immutable nullable),
status (enum: paid|late), created_at
```

- `RentPaymentStatusEnum` : `PAID`, `LATE`. Aucune ligne `pending` n'est
  jamais persistée — c'est un état calculé en lecture (comme pour
  `RecurringTransaction`), pas une valeur stockée.
- `amount` = `lease.rent + lease.charges` au moment de la confirmation (la
  quittance couvre loyer + charges).
- Pas de soft delete : une fois confirmée, une échéance est un fait, au même
  titre qu'une `Transaction` déjà matérialisée.
- `#[ORM\ManyToOne(targetEntity: Lease::class)]`, `#[ORM\ManyToOne(targetEntity: Transaction::class, nullable: true)]`.

### `LoanPayment`

```
id, space_id (FK), loan_id (FK), transaction_id (FK nullable),
month_number (int), due_date (date_immutable),
capital_part (decimal 15,2), interest_part (decimal 15,2), insurance_part (decimal 15,2),
remaining_capital (decimal 15,2), paid_date (date_immutable), created_at
```

- Pas de colonne statut : une ligne n'existe qu'une fois payée.
- Les quatre montants sont copiés tels quels depuis la ligne calculée de
  `LoanAmortizationService::schedule($loan)->combined->rows` au moment de la
  confirmation, et ne sont plus jamais recalculés — même principe que
  `transaction.fx_rate`.
- Pas de soft delete, même raison que `RentPayment`.

Les deux entités suivent les conventions existantes : `SpaceScopeTrait`,
`TimestampTrait` (created_at seul ici, pas d'updated_at — rien n'est modifié
après création), migration générée via
`php bin/console doctrine:migrations:diff`.

## Repositories

- `RentPaymentRepositoryInterface` / `RentPaymentRepository` :
  - `findBySpace(Space $space): array`
  - `findByLease(Lease $lease): array` (historique, tri par `due_date` desc)
  - `findByLeaseAndDueDate(Lease $lease, \DateTimeImmutable $date): ?RentPayment`
    (teste si une échéance est déjà couverte)
- `LoanPaymentRepositoryInterface` / `LoanPaymentRepository` :
  - `findBySpace(Space $space): array`
  - `findByLoan(Loan $loan): array` (historique, tri par `month_number` asc)
  - `findMaxMonthNumber(Loan $loan): ?int` (curseur : prochain mois = max + 1,
    ou 1 si aucune ligne)
  - `findByLoanAndMonth(Loan $loan, int $monthNumber): ?LoanPayment` (utilisé
    par `LoanChargeService`)

## Calcul des échéances dues (lecture)

### `LeaseRentScheduleServiceInterface` / `LeaseRentScheduleService` (nouveau, `Service/RealEstate/`)

Miroir de `RecurrenceScheduleService`, mais pour un bail : mensuel, ancré sur
le jour de `lease.start_date`, jamais de décalage (`DateShifterInterface::addMonths`),
borné par `LeaseTermService::currentTerm($lease)->endDate` et par
l'inactivité du bail.

```php
interface LeaseRentScheduleServiceInterface
{
    /** La prochaine échéance due (la plus ancienne non couverte), ou null si aucune. */
    public function nextDue(Lease $lease, \DateTimeImmutable $today): ?RentDueDto;
}
```

`RentDueDto { Lease $lease; \DateTimeImmutable $dueDate; string $amount; int $backlog; }`
— même forme que `DueOccurrenceDto` (date la plus ancienne + compteur de
retard). Les échéances déjà couvertes (un `RentPayment` existe pour ce
`due_date`) sont exclues de la série avant de prendre la plus ancienne non
couverte.

### Résolution LoanPayment

Pas de service séparé : `LoanPaymentService` résout lui-même le mois suivant
via `LoanPaymentRepositoryInterface::findMaxMonthNumber()` + 1, puis prend la
ligne correspondante dans
`LoanAmortizationServiceInterface::schedule($loan)->combined->rows`. Une
échéance n'est actionnable que si sa `dueDate <= aujourd'hui` et si le mois
existe encore dans l'échéancier (sinon : prêt intégralement remboursé).

## Confirmation (écriture)

### `RentPaymentServiceInterface` / `RentPaymentService`

```php
interface RentPaymentServiceInterface
{
    public function confirm(Lease $lease, \DateTimeImmutable $dueDate, Account $account, ?Category $category): RentPayment;
    public function markLate(Lease $lease, \DateTimeImmutable $dueDate): RentPayment;
}
```

- Guards (même esprit que `RecurringTransactionService`) :
  - `$dueDate` doit être exactement l'échéance la plus ancienne due
    (`LeaseRentScheduleService::nextDue()`), sinon `InvalidArgumentException`
    — empêche de confirmer décembre avant novembre.
  - `$account` et `$category` doivent appartenir au `space` du bail (un id
    posté ne prouve rien sur son propriétaire).
  - Le bail doit être actif (`lease.isActive()`), sinon aucune échéance n'est
    due.
- `confirm()` : dans `$em->wrapInTransaction(function () { ... })` —
  crée `RentPayment(status=PAID, paidDate=$dueDate)`, appelle
  `TransactionService::save()` (type=income, amount=`rent+charges`,
  date=`$dueDate`), lie `transaction_id`, persiste. Si `TransactionService::save()`
  lève (fonds — non applicable ici car income, mais guard quand même
  présent dans `TransactionService`), toute la transaction DB est annulée :
  aucune `RentPayment` à moitié écrite.
- `markLate()` : crée `RentPayment(status=LATE)` sans `Transaction`, dans la
  même logique de guard sur `$dueDate`.

### `LoanPaymentServiceInterface` / `LoanPaymentService`

```php
interface LoanPaymentServiceInterface
{
    public function confirm(Loan $loan, Account $account, ?Category $category): LoanPayment;
}
```

- Résout le mois suivant (voir plus haut), guard qu'il est actionnable
  (`dueDate <= aujourd'hui`, mois existant dans l'échéancier).
- `$account`/`$category` revalidés dans le `space` du prêt.
- Dans `$em->wrapInTransaction()` : crée `LoanPayment` avec les 4 montants
  figés + `monthNumber`/`dueDate` copiés de la ligne calculée, appelle
  `TransactionService::save()` (type=expense, amount=`totalPayment` de la
  ligne), lie `transaction_id`.

## Correction de `LoanChargeService`

Actuellement `chargesForYear()` lit uniquement
`LoanAmortizationService::schedule($loan)->combined->years`, donc un taux de
ligne corrigé après coup changerait rétroactivement des intérêts déjà
déclarés — ce que `CLAUDE.md` exclut explicitement.

Nouvelle logique, par prêt et par année demandée :

1. Prendre les lignes théoriques de l'année (`combined->rows` filtrées par
   année) comme avant.
2. Pour chaque ligne, chercher un `LoanPayment` confirmé via
   `findByLoanAndMonth($loan, $row->monthNumber)`.
3. Si trouvé : utiliser les 4 montants figés du `LoanPayment` à la place de
   la ligne théorique pour ce mois. Sinon : garder la ligne théorique.
4. Sommer mois par mois (plus par année directement) pour obtenir le
   `AmortizationYearDto` final consommé par `TaxSummaryService`/`DeclarationController`.

Un prêt avec quelques mois confirmés et le reste de l'année projeté reste
exact : les mois passés sont figés, les mois futurs restent une estimation
jusqu'à leur confirmation.

## Controllers & routes

### `RentPaymentController` (`src/Controller/RealEstate/`, `/real-estate/leases/{lease}/rent-payments`)

- `POST /confirm` (`app_rent_payment_confirm`) : form `RentPaymentConfirmFormType`
  (compte + catégorie) + un champ cache `due_date`, rempli par la vue avec la
  date retournée par `nextDue()` — même défense en profondeur que
  `RecurringTransactionController::postedDate()` : la vue l'affiche, le
  service revalide que c'est bien l'échéance la plus ancienne due, un id/date
  posté ne prouve jamais rien par lui-même. Appelle `RentPaymentService::confirm()`.
- `POST /late` (`app_rent_payment_late`) : même champ caché `due_date`, CSRF
  via le form. Appelle `RentPaymentService::markLate()`.
- `denyAccessUnlessGranted('EDIT', $lease->getSpace())` sur les deux.

### Nouvelle page `app_lease_show`

`LeaseController` n'a aujourd'hui pas de `show()` — seulement
`index`/`new`/`edit`/`toggle`/`delete`. On l'ajoute :
`GET /real-estate/leases/{id}` → affiche l'échéance due (si `isActive()` et
`nextDue()` non nul) avec un formulaire de confirmation inline, et
l'historique des `RentPayment` (`findByLease()`). Les liens "voir" de
`lease/index.html.twig` et `lease/_card.html.twig` sont mis à jour pour
pointer vers cette page.

### `LoanPaymentController` (`src/Controller/RealEstate/`, `/real-estate/loans/{loan}/payments`)

- `POST /confirm` (`app_loan_payment_confirm`) : form `LoanPaymentConfirmFormType`
  (compte + catégorie optionnelle), appelle `LoanPaymentService::confirm()`.
- `denyAccessUnlessGranted('EDIT', $loan->getSpace())`.

### `loan/show.html.twig` existant

Section ajoutée : prochaine mensualité due (si actionnable) avec formulaire
de confirmation inline, et historique des `LoanPayment` confirmés
(`findByLoan()`), affiché à côté de l'échéancier théorique déjà présent.

## Formulaires

- `RentPaymentConfirmFormType` : `account` (`EntityType`, `query_builder`
  scopé au space, exclut les comptes crypto/stock comme
  `RecurringTransactionFormType`), `category` (`EntityType`, `required=false`,
  `query_builder` scopé au space — pas de filtre sur `fiscal_bucket` pour ne
  pas bloquer un choix légitime, mais le `group_by` existant par type
  applicable suffit à guider l'utilisateur vers une catégorie de revenu),
  `dueDate` (`HiddenType`, non mappé à l'entité — lu par le controller comme
  `RecurringTransactionController::postedDate()`).
- `LoanPaymentConfirmFormType` : `account` (même pattern), `category`
  (`required=false` — n'affecte pas la déclaration, qui lit `LoanPayment`
  directement via `LoanChargeService`).
- CSRF géré par le form Symfony standard (token caché automatique), pas de
  jeton manuel comme sur `toggle`/`delete`.

## Sécurité

- `denyAccessUnlessGranted('EDIT', ...->getSpace())` sur chaque action
  d'écriture (`confirm`, `late`), `VIEW` sur les pages de lecture
  (`app_lease_show`, `app_loan_show`).
- Les deux services revalident que `$account`/`$category` postés
  appartiennent au `space` du bail/prêt avant toute écriture — un formulaire
  n'est pas une frontière de sécurité (cf. `CLAUDE.md` → garde-fou 5).
- Actions destructives : aucune (pas de delete sur RentPayment/LoanPayment
  dans ce spec).

## Mise à jour de la documentation

- `ARCHITECTURE.md` : retirer `LinkedTransactionListener` du tableau des
  listeners et `GenerateRentPaymentsCommand` du tableau des commandes ;
  remplacer le diagramme `RentPayment created → LinkedTransactionListener → …`
  par la confirmation explicite via service (même forme que le
  `RecurringTransaction` déjà documenté juste au-dessus) ; ajouter
  `RentPaymentService`/`LoanPaymentService`/`LeaseRentScheduleService` à la
  section `RealEstate/` du *Service Layer*, et les controllers/routes
  correspondants à *Controller Structure*.
- `CLAUDE.md` : la ligne de *Décisions clés* qui dit que `RentPayment`/`LoanPayment`
  "portent leur Transaction via LinkedTransactionListener" est corrigée pour
  dire qu'ils la portent via confirmation explicite en service, comme
  `RecurringTransaction`.
- `MODULES.md` : retirer la mention *"pas encore implémenté"* sur
  `LoanPayment`, et documenter le workflow de confirmation réel (déjà en
  grande partie décrit dans le texte existant du module).

## Plan d'implémentation (phases)

1. **RentPayment** : entité, migration, repository, `LeaseRentScheduleService`,
   `RentPaymentService`, `RentPaymentController`, formulaire, page
   `app_lease_show`, liens depuis l'index des baux.
2. **LoanPayment** : entité, migration, repository, `LoanPaymentService`,
   `LoanPaymentController`, formulaire, section ajoutée à `loan/show.html.twig`.
3. **Correction `LoanChargeService`** (dépend de la phase 2 — a besoin de
   `LoanPaymentRepository::findByLoanAndMonth()`).
4. **Documentation** : `ARCHITECTURE.md`, `CLAUDE.md`, `MODULES.md`.

Chaque phase se termine par `doctrine:schema:validate`, `doctrine:migrations:diff`
(aucune migration en attente), `lint:twig templates/`.
