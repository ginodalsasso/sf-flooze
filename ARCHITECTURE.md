# Architecture — sf-flooze

Full structural reference for the sf-flooze Symfony 8.0 codebase.

See also: [CLAUDE.md](CLAUDE.md) · [MODULES.md](MODULES.md) · [SETUP.md](SETUP.md)

---

## Directory Tree

```
sf-flooze/
├── src/
│   ├── Entity/                    # Doctrine ORM entities (attribute mapping)
│   ├── Repository/                # Custom Doctrine queries
│   │   └── Contract/              # {Entity}RepositoryInterface (extends ObjectRepository)
│   ├── Service/                   # Business logic (by domain)
│   │   └── <Domain>/Contract/     # {X}ServiceInterface — one Contract/ per domain folder
│   │   ├── AI/
│   │   ├── Finance/
│   │   ├── RealEstate/
│   │   ├── Invoicing/
│   │   ├── Tax/
│   │   ├── PDF/
│   │   ├── Document/
│   │   ├── Notification/
│   │   ├── Export/
│   │   └── Security/
│   ├── Controller/                # HTTP layer (by domain)
│   │   ├── Dashboard/
│   │   ├── Finance/
│   │   ├── RealEstate/
│   │   ├── Invoicing/
│   │   ├── Tax/
│   │   ├── Document/
│   │   ├── AI/
│   │   └── Auth/
│   ├── Form/                      # Symfony form types
│   ├── EventListener/             # Doctrine + Symfony event listeners
│   ├── Command/                   # Console commands (cron jobs)
│   ├── Trait/                     # Reusable entity mixins
│   ├── Enum/                      # PHP backed enums
│   ├── Security/
│   │   └── Voter/
│   └── Dto/                       # Data transfer objects
│
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml
│   │   ├── security.yaml
│   │   ├── messenger.yaml
│   │   ├── mailer.yaml
│   │   ├── twig.yaml
│   │   └── services.yaml
│   └── routes/
│       ├── attributes.yaml
│       └── api.yaml
│
├── templates/
│   ├── base.html.twig
│   ├── layout/
│   ├── dashboard/
│   ├── finance/
│   ├── real_estate/
│   ├── invoicing/
│   ├── tax/
│   ├── ai/
│   ├── document/
│   └── pdf/                       # dompdf Twig templates
│
├── public/
│   └── assets/
│
├── assets/                        # Stimulus controllers, CSS
│   ├── controllers/
│   ├── styles/
│   └── app.js
│
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Functional/
│
├── migrations/                    # Doctrine migrations
│
├── frankenphp/                    # Docker app server config (dev + prod)
│   ├── Caddyfile
│   ├── conf.d/
│   └── docker-entrypoint.sh
│
├── desktop/                       # Tauri desktop app (see desktop/README.md)
│   ├── Caddyfile                  # FrankenPHP worker mode on :8765
│   ├── start / start.ps1          # Bootstrap + launch FrankenPHP
│   ├── bin/                       # Local FrankenPHP binary (gitignored)
│   └── src-tauri/                 # Tauri shell (WebView over localhost:8765)
│
├── .claude/
│   ├── rules.md
│   └── memory.md
│
├── CLAUDE.md
├── ARCHITECTURE.md
├── MODULES.md
├── SETUP.md
├── README.md
├── .env
└── composer.json
```

---

## Entity Map

### Full Entity List

```
src/Entity/
├── User.php                  # Auth (email, password, roles)
├── Space.php                 # Multi-tenant unit
│
├── Account.php               # Finance: bank/cash/crypto/saving account
├── Transaction.php           # Finance: income/expense/transfer
├── RecurringTransaction.php  # Finance: gabarit + règle de répétition (→ Transaction à la confirmation)
├── Category.php              # Finance: hierarchical, fiscal flags, applicable transaction types
├── Tag.php                   # Finance: free cross-cutting label on transactions (no fiscal meaning)
├── Asset.php                 # Finance: stocks, crypto, ETF (ticker, name, currency, type)
├── AssetEntry.php            # Finance: ledger row for a buy/sell/dividend operation
│
├── Property.php              # Real estate: residential/rental/secondary
├── Tenant.php                # Real estate: tenant with income verification
├── Lease.php                 # Real estate: rental contract
├── LeaseTenant.php           # Real estate: junction (multi-tenant per lease)
├── RentPayment.php           # Real estate: monthly rent (→ Transaction)
├── Loan.php                  # Real estate: mortgage with amortization
├── LoanPayment.php           # Real estate: monthly payment (capital+interest)
│
├── Client.php                # Invoicing: client with SIRET, email, address
├── Quote.php                 # Invoicing: devis with status flow
├── QuoteLine.php             # Invoicing: line items (qty, unit price, VAT)
├── Invoice.php               # Invoicing: facture with sequential number
├── InvoiceLine.php           # Invoicing: line items (HT/TTC breakdown)
│
├── TaxYear.php               # Tax: fiscal year (draft/filed/paid)
├── TaxItem.php               # Tax: item (to_declare/to_deduct/to_pay)
│
├── Document.php              # Generic: stored file (PDF, image)
├── DocumentLink.php          # Generic: polymorphic relation Document → any entity
├── Reminder.php              # Generic: task/deadline
└── ReminderLink.php          # Generic: polymorphic Reminder → any entity
```

### Entity Relationships (ERD Text)

```
User (1) ──── (N) Space
Space (1) ──── (N) Account
Space (1) ──── (N) Asset
Space (1) ──── (N) Category
Space (1) ──── (N) Tag
Space (1) ──── (N) Property
Space (1) ──── (N) Client
Space (1) ──── (N) TaxYear
Space (1) ──── (N) Document
Space (1) ──── (N) Reminder

Account (1) ──── (N) Transaction
Account (1) ──── (N) AssetEntry [holding account, nullable]
Account (1) ──── (N) AssetEntry [funding account, nullable]
Category (1) ──── (N) Transaction
Category (1) ──── (N) Category [parent_id self-referential]
Transaction (N) ──── (N) Tag [pivot transaction_tag, owning side Transaction]
Account (1) ──── (N) Transaction [destination_account_id, nullable, for transfers]
Transaction (N) ──── (0..1) AssetEntry [asset_entry_id, SET NULL on hard delete]
Asset (1) ──── (N) AssetEntry

Space (1) ──── (N) RecurringTransaction
Account (1) ──── (N) RecurringTransaction [account_id]
Account (1) ──── (N) RecurringTransaction [destination_account_id, nullable, virements]
Category (1) ──── (N) RecurringTransaction [category_id, nullable]
RecurringTransaction (N) ──── (N) Tag [pivot recurring_transaction_tag, owning side RecurringTransaction]
RecurringTransaction (1) ──── (N) Transaction [recurring_transaction_id, nullable, SET NULL]

Property (1) ──── (N) Lease
Property (1) ──── (N) Loan
Lease (1) ──── (N) LeaseTenant
Lease (1) ──── (N) RentPayment
Tenant (1) ──── (N) LeaseTenant
Loan (1) ──── (N) LoanPayment
RentPayment (1) ──── (1) Transaction [linked income]
LoanPayment (1) ──── (1) Transaction [linked expense]

Client (1) ──── (N) Quote
Client (1) ──── (N) Invoice
Quote (1) ──── (N) QuoteLine
Quote (1) ──── (0..1) Invoice [conversion]
Invoice (1) ──── (N) InvoiceLine

TaxYear (1) ──── (N) TaxItem
TaxItem (N) ──── (0..1) Transaction [nullable FK]
TaxItem (N) ──── (0..1) Property [nullable FK]

Document (1) ──── (N) DocumentLink
DocumentLink (N) ──── (1) {any entity} [polymorphic: entity_id + entity_type]

Reminder (1) ──── (N) ReminderLink
ReminderLink (N) ──── (1) {any entity} [polymorphic]
```

---

## Service Layer

Chaque service listé ci-dessous est accompagné de son interface `{Classe}Interface`, rangée dans le sous-dossier `Contract/` de son domaine, et n'est injecté que par elle (voir [`.claude/rules.md`](.claude/rules.md) → *Interfaces*). Même règle pour les repositories et les générateurs PDF.

```
src/Service/

AI/
├── OllamaClient.php              # HTTP wrapper for Ollama API
├── ReceiptOcrService.php         # Vision OCR (llava) → structured extraction
├── PayslipParsingService.php     # Payslip text extraction
├── InvoiceParsingService.php     # Supplier invoice parsing
├── FiscalRecommendationService.php # Tax optimization suggestions
└── AIMetricsService.php          # OCR confidence logging

Finance/
├── RecurringTransactionService.php  # CRUD des récurrences + échéances dues + matérialisation
├── RecurrenceScheduleService.php    # Calcul pur des dates d'occurrence (aucune dépendance Doctrine)
├── TransactionService.php        # CRUD + guards (manual transactions)
├── CategoryService.php           # Hierarchy + flag management
├── AssetService.php              # Asset CRUD
├── AssetEntryService.php         # Buy/sell/dividend ledger + P&L (FIFO)
├── AssetEntryTransactionService.php # Keeps Transaction rows in sync with AssetEntry
├── AssetMetricsService.php       # Aggregated metrics (qty, avg price, cost basis)
├── AssetPriceService.php         # Single source of asset unit prices (crypto = marché, sinon dernière opération)
├── CryptoPriceApiClient.php      # Cours crypto spot + recherche du catalogue (CoinGecko), cache offline
├── AccountService.php            # Account CRUD + soft-delete
├── AccountBalanceService.php     # current balance + invested vs available split
├── ExchangeRateService.php       # Single source of FX rates (space currency conversions)
└── AccountDetailService.php      # Per-account detail DTO (monthly stats)

RealEstate/
├── PropertyService.php
├── LeaseService.php              # Auto-generate rent payments
└── LoanService.php               # Amortization calculations

Invoicing/
├── QuoteService.php              # Quote → Invoice conversion
└── InvoiceService.php            # Numbering (FAC-YYYY-NNN), payment tracking

Tax/
├── TaxItemService.php            # CRUD + linking
└── TaxYearService.php            # Aggregate, calculate, export

PDF/
├── QuotePdfGenerator.php
├── InvoicePdfGenerator.php
├── TaxSummaryPdfGenerator.php
└── LoanAmortizationPdfGenerator.php

Document/
└── DocumentService.php           # S3 upload, polymorphic links, dedup (file_hash)

Notification/
└── ReminderService.php           # Email notifications, deadline tracking

Export/
└── TaxExportService.php          # 2042/2042-C-Pro form export

Security/
├── SpaceAuthorizationService.php # Multi-tenant authorization
└── EncryptionService.php         # IBAN, SIRET encryption (Sodium)
```

---

## Controller Structure

```
src/Controller/

Dashboard/
└── DashboardController.php       # GET /dashboard

Auth/
├── LoginController.php           # GET|POST /login
├── RegisterController.php        # GET|POST /register
└── SpaceSwitcherController.php   # POST /space/switch (AJAX)

Finance/
├── AccountController.php         # /accounts
├── TransactionController.php     # /transactions (+ import CSV, reconcile)
└── CategoryController.php        # /categories (tree editor)

RealEstate/
├── PropertyController.php        # /properties
├── LeaseController.php           # /leases
├── TenantController.php          # /tenants
└── LoanController.php            # /loans (+ amortization PDF)

Invoicing/
├── ClientController.php          # /clients
├── QuoteController.php           # /quotes (+ PDF preview, status)
└── InvoiceController.php         # /invoices (+ PDF, payment tracking)

Tax/
├── TaxYearController.php         # /tax/years
├── TaxItemController.php         # /tax/items
└── TaxExportController.php       # /tax/export (2042 PDF)

Document/
└── DocumentController.php        # /documents (upload, list, preview)

AI/
├── ReceiptUploadController.php   # POST /receipts/upload → preview → confirm
├── PayslipImportController.php   # POST /payslips/import
└── InvoiceImportController.php   # POST /invoices/import
```

---

## Event Listeners

| Listener | Trigger | Action |
|----------|---------|--------|
| `TimestampListener` | `prePersist`, `preUpdate` | Auto-set `created_at`, `updated_at` |
| `SoftDeleteListener` | `preRemove` | Set `deleted_at`, prevent hard delete |
| `AutoCategoryListener` | `prePersist` Transaction | Call Ollama for category hint |
| `LinkedTransactionListener` | `postPersist` RentPayment/LoanPayment | Auto-create linked Transaction |
| `AuditListener` | `postPersist`, `postUpdate` | Log created_by, updated_by (future) |

---

## Console Commands

| Command | Purpose | Schedule |
|---------|---------|----------|
| `GenerateRentPaymentsCommand` | Create monthly RentPayment entries | Monthly cron |
| `ProcessRemindersCommand` | Send email before deadlines | Daily cron |
| `GenerateTaxSummaryCommand` | Aggregate TaxYear items | On demand |
| `ReconcileAccountCommand` | Match Transactions ↔ bank statement | On demand |
| `SyncCloudStorageCommand` | Backup docs to Google Drive | Weekly (future) |
| `OptimizeTaxesCommand` | Run Ollama fiscal recommendations | On demand (future) |

---

## Desktop App

The desktop app is a Tauri WebView shell over the same Symfony codebase, served
by a local **FrankenPHP binary in worker mode** on `http://localhost:8765`
(`desktop/Caddyfile`), backed by SQLite (`var/app.db`) under the `desktop`
kernel environment. Details: [desktop/README.md](desktop/README.md).

---

## Key Design Patterns

### 1. Space Scoping (Multi-Tenancy)

Every entity uses `SpaceScopeTrait` (adds `space_id`). Queries always filter by active space. `SpaceScopeVoter` enforces ownership in controllers.

```php
// Doctrine query ALWAYS includes space filter
->where('e.space = :space')
->setParameter('space', $this->getActiveSpace())
```

### 2. Transaction as Financial Backbone

All money flows — rent, loan payments, invoices — ultimately create a `Transaction` record. This gives a unified view of cash flow regardless of origin.

```
RentPayment created → LinkedTransactionListener → Transaction(income) created
LoanPayment created → LinkedTransactionListener → Transaction(expense) created
Invoice paid → InvoiceController → Transaction(income) created
```

### 3. DocumentLink Polymorphism

A single `Document` can be attached to any entity without N join tables. `DocumentLink` stores `(document_id, entity_id, entity_type)`.

```php
// Attach a receipt to a Transaction
$link = new DocumentLink();
$link->setDocument($document);
$link->setEntityId($transaction->getId());
$link->setEntityType(Transaction::class);
```

### 4. ReminderLink Polymorphism

Same pattern as DocumentLink. Reminders can be linked to Property, Lease, TaxYear, etc.

### 5. Category Hierarchy

`Category` is self-referential with `parent_id`. `is_deductible` and `is_declarable` flags propagate fiscal significance.

`applicable_types` (JSON, list of `TransactionTypeEnum` values) scopes a category to income / expense / transfer.
Empty array = tous types (legacy rows). Les selects de transaction groupent les catégories par ce scope
(`group_by`), et `AbstractTransactionFormType::validateCategoryType()` rejette une catégorie hors scope.

### 6. Tag ≠ Category

Deux axes **orthogonaux**, à ne jamais fusionner :

| | `Category` | `Tag` |
|---|---|---|
| Cardinalité | 1 par transaction | 0..N |
| Répond à | *quelle est la nature du flux ?* | *à quoi ça se rattache ?* (projet, événement, personne) |
| Portée fiscale | oui (`is_deductible`, `is_declarable`, `applicable_types`) | **aucune** |
| Structure | hiérarchique (`parent_id`) | plat, jetable |

Règles qui tiennent la séparation :

- Un tag n'a **ni hiérarchie, ni flags fiscaux, ni `applicable_types`** — sinon c'est une seconde taxonomie concurrente et deux sources de vérité pour la déductibilité.
- Le module Tax ne lit **jamais** un tag.
- Le pivot `transaction_tag` ne porte pas de `space_id` : ses deux côtés sont déjà scopés, et `TransactionService::guardTagsInSpace()` revalide l'appartenance avant persist.
- Le filtre par tag s'écrit `:tag MEMBER OF t.tags`, **jamais** en `JOIN` : le même QueryBuilder sert à `sumByFilter()`, et une jointure ManyToMany duplique les lignes donc fausse les totaux.

Filtre **mono-tag** assumé : une transaction porte rarement plus d'un projet, et croiser deux projets a peu de sens. Passer au multi-tag ne touche que `TransactionFilterDto`, son FormType et `applyFilter()`.

### 7. Invoice Sequential Numbering

`InvoiceService::generateNumber(Space $space, int $year)` queries the max existing number for the space+year, then increments. Format: `FAC-2025-001`.

### 8. Multi-devise

`Space.currency` est la **devise de référence** : tout total qui traverse plusieurs comptes s'y exprime. Elle se choisit à la création d'un space et n'est plus modifiable — la changer invaliderait tous les `fx_rate` déjà figés.

Le solde d'un compte reste **dans la devise du compte**, pour rester rapprochable avec un relevé bancaire. Seuls les agrégats convertissent.

Deux taux distincts, à ne pas confondre :

| Taux | Où | Rôle |
|---|---|---|
| **historique** | `transaction.fx_rate`, `asset_entry.fx_rate` | Figé à la date de l'opération. Un taux récupéré plus tard ne doit jamais réécrire le passé. |
| **spot** | `ExchangeRateService` | Convertit les *soldes* à l'instant présent (total du dashboard, invested vs available). |

`ExchangeRateService` est le **seul** détenteur des taux (table en `private const`). Brancher une API de taux ne touche que cette classe : aucun appelant, aucune colonne. La table devient alors le fallback offline.

### 9. Virements — une ligne, deux comptes

Un virement est **une seule `Transaction`** portant `account_id` (débité) et `destination_account_id` (crédité). Il n'y a pas de double-entry.

Conséquence à ne pas oublier : **toute query filtrant par compte doit matcher les deux jambes**, sinon le compte destinataire voit son solde bouger sans transaction visible.

```php
->andWhere('t.account = :account OR t.destinationAccount = :account')
```

Entre deux devises, la banque seule connaît le taux appliqué : `destination_amount` porte le montant **réellement crédité**, dans la devise du compte destinataire. Il est `NULL` quand les deux comptes partagent la même devise, et obligatoire sinon (`TransactionService::guardValidTransfer`).

### 10. Solde d'un compte — calculé, jamais stocké

`account.opening_balance` est le solde **avant** le suivi dans Flooze : saisi à la création, il ne bouge que si l'utilisateur le corrige. Le solde courant se dérive :

```
solde = opening_balance + Σ(mouvements actifs du compte)
```

`TransactionRepository::getBalanceDelta()` calcule la somme en une requête (jambe sortante signée par le type, jambe entrante créditée de `destination_amount`), `AccountBalanceService::getCurrentBalance()` y ajoute l'ouverture. **Aucun service n'écrit un solde** : un bug d'écriture ne peut plus laisser d'écart durable, et supprimer la transaction fautive suffit à rétablir la valeur juste.

Conséquence : ne jamais réintroduire de colonne `balance` ni de méthode qui incrémente un solde. Pour un solde à une date donnée, filtrer la même requête sur `t.date`.

### 11. Cours des actifs — jamais bloquants, toujours datés

`AssetPriceService` est le **seul** détenteur des cours, comme `ExchangeRateService` l'est des taux. Pour une crypto il interroge `CryptoPriceApiClient` (CoinGecko) ; pour tout le reste — et pour une crypto que le fournisseur ne sait pas coter — il retombe sur le prix unitaire de la dernière opération enregistrée.

`CryptoPriceApiClient` garde **deux entrées de cache par paire** : une *gate* à durée courte qui espace les appels (60 s après un échec, 5 min après un succès), et le cours lui-même, **sans expiration**. Un fournisseur injoignable dégrade donc vers le dernier cours lu, jamais vers rien : l'appli reste utilisable hors ligne. Le ticker est résolu en identifiant CoinGecko via `/search` (mapping mis en cache 30 jours) — **aucune colonne** n'est ajoutée à `asset`.

`AssetPriceDto` porte la source (`market` / `cached` / `trade`) et sa date. Deux règles côté UI :
- un cours périmé ne se présente jamais comme un cours en direct — le badge le dit ;
- seul un cours `market` préremplit le prix unitaire d'un achat ou d'une vente. Un cours périmé se ressaisit à la main, sinon il partirait en base sans que l'utilisateur l'ait voulu.

**Création d'un actif crypto.** `CryptoMarketController` expose deux endpoints JSON (`/finance/crypto/search`, `/finance/crypto/price`) qui alimentent le contrôleur Stimulus `crypto-picker`. La recherche remplit `ticker` et `name`, qui restent éditables : une crypto inconnue du fournisseur se saisit à la main, et hors ligne la recherche renvoie une liste vide plutôt qu'une erreur.

Le prix de l'**achat initial** d'une crypto n'est pas saisi : `AssetFormType` l'écrase en `PRE_SUBMIT` avec le cours du marché, donc avant validation et quoi que poste le navigateur. Le champ affiché n'est qu'un rendu — c'est bien le serveur qui impose la valeur. Exception unique : sans aucun cours connu (hors ligne, jamais relevé), la saisie manuelle reprend la main, sinon aucune crypto ne pourrait plus être enregistrée.

---

## Template Structure

```
templates/
├── base.html.twig                # Base layout (CSS, JS imports, blocks)
├── layout/
│   ├── sidebar.html.twig         # Navigation (Finance, RealEstate, Invoicing, Tax, Docs)
│   ├── topbar.html.twig          # User menu, notifications, space selector
│   ├── space_switcher.html.twig  # AJAX space switch dropdown
│   └── breadcrumb.html.twig
│
├── dashboard/                    # Main dashboard view
├── finance/                      # accounts/, transactions/, categories/
├── real_estate/                  # properties/, leases/, tenants/, loans/
├── invoicing/                    # clients/, quotes/, invoices/
├── tax/                          # tax_years/, tax_items/, exports/
├── ai/                           # receipt_upload, payslip_import, invoice_import
├── document/                     # Document library
│
└── pdf/                          # dompdf Twig templates (limited CSS!)
    ├── quote.html.twig           # Devis layout (branding, items, CGV)
    ├── invoice.html.twig         # Facture layout (+ SIRET, payment terms)
    ├── tax_summary.html.twig     # Fiscal recap (revenues vs charges)
    └── loan_amortization.html.twig # Payment schedule table
```

**PDF template constraint** : dompdf has limited CSS support. Use `<table>` for layout, avoid flexbox/grid, floats are OK.

---

## Database Schema Summary
 
> **Toutes les tables ont** : `id` (PK auto-increment), `space_id` (FK → space, **y compris les pivots et tables polymorphiques** par défense en profondeur ; sauf `user` et `space` elles-mêmes), `created_at`, `updated_at`, et `deleted_at` (nullable, sur les entités où le soft-delete s'applique — colonne "Soft" ci-dessous).
> Les colonnes ci-dessous listent uniquement les champs **spécifiques** à chaque table.
 
### Tables
 
| Table | Champs spécifiques | Soft |
|---|---|:-:|
| `user` | email, password, roles (JSON) — pas de `space_id` | — |
| `space` | user_id, name, type, currency (devise de référence) — pas de `space_id` (racine) | — |
| `account` | name, type, opening_balance, currency | ✓ |
| `transaction` | account_id, destination_account_id (nullable), category_id, type, amount, fx_rate, destination_amount (nullable), date, description, metadata (JSON) | ✓ |
| `category` | parent_id (nullable), name, is_deductible, is_declarable | — |
| `tag` | name (unique par space) | ✓ |
| `transaction_tag` | transaction_id, tag_id (pivot, ON DELETE CASCADE) | — |
| `asset` | ticker, name, currency, type | — |
| `asset_entry` | asset_id, account_id (nullable), funding_account_id (nullable), date, kind (buy\|sell\|dividend), quantity, unit_price, fx_rate, fees, note | ✓ |
| `property` | name, address, type, purchase_price, purchase_date | ✓ |
| `tenant` | first_name, last_name, email, phone, monthly_income, guarantor_name, guarantor_income | — |
| `lease` | property_id, rent, charges, type, security_deposit, start_date, end_date, is_active | ✓ |
| `lease_tenant` | lease_id, tenant_id (pivot) | — |
| `rent_payment` | lease_id, transaction_id (nullable), amount, due_date, paid_date, status | — |
| `loan` | property_id, bank_name, amount, rate, insurance_rate, start_date, duration_months | — |
| `loan_payment` | loan_id, transaction_id (nullable), month_number, due_date, capital_part, interest_part, insurance_part, remaining_capital, paid_date | — |
| `client` | name, siret, vat_number, email, phone, address, city, postal_code, country | — |
| `quote` | client_id, number, status, valid_until, note | ✓ |
| `quote_line` | quote_id, description, quantity, unit_price, vat_rate, sort_order | — |
| `invoice` | client_id, quote_id (nullable), number (FAC-YYYY-NNN), status, total_ht, total_ttc, issued_at, due_date, paid_at, note | ✓ |
| `invoice_line` | invoice_id, description, quantity, unit_price, vat_rate, total_ht, total_ttc, sort_order | — |
| `tax_year` | year, status, note | — |
| `tax_item` | tax_year_id, transaction_id (nullable), property_id (nullable), kind, label, amount (nullable), note, done | — |
| `document` | name, file_url, mime_type, file_hash, file_size, original_name | — |
| `document_link` | document_id, entity_id, entity_type (polymorphic) | — |
| `reminder` | title, description, due_date, status, priority | — |
| `reminder_link` | reminder_id, entity_id, entity_type (polymorphic) | — |
 
### Notes
 
- `user` et `space` n'ont pas de `space_id` — `user` est l'entité racine, `space` appartient à un `user` directement.
- `category`, `tenant`, `client`, `tax_year`, `tax_item`, `document`, `reminder` n'ont pas de `deleted_at` car leur cycle de vie est court ou ils sont conservés pour audit.
- Les pivots (`lease_tenant`, `document_link`, `reminder_link`) ont `space_id` **par défense en profondeur** — cela évite des bugs de jointure cross-space où un user pourrait accidentellement lier deux entités d'espaces différents.

### All Tables Have

- `id` (int PK auto-increment)
- `space_id` (FK → space.id)
- `created_at`, `updated_at` (TIMESTAMP)
- `deleted_at` (nullable TIMESTAMP, soft delete where applicable)
