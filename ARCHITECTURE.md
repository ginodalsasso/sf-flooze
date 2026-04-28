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
│   ├── Service/                   # Business logic (by domain)
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
├── frankenphp/
│   ├── Caddyfile
│   ├── compose.yaml
│   ├── compose.override.yaml
│   └── compose.prod.yaml
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
├── Category.php              # Finance: hierarchical with fiscal flags
├── Asset.php                 # Finance: stocks, crypto, ETF
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
Space (1) ──── (N) Property
Space (1) ──── (N) Client
Space (1) ──── (N) TaxYear
Space (1) ──── (N) Document
Space (1) ──── (N) Reminder

Account (1) ──── (N) Transaction
Category (1) ──── (N) Transaction
Category (1) ──── (N) Category [parent_id self-referential]
Transaction (1) ──── (1) Transaction [destination, nullable, for transfers]

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
├── TransactionService.php        # CRUD + reconciliation
├── CategoryService.php           # Hierarchy + flag management
└── AssetService.php              # Price tracking

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

### 6. Invoice Sequential Numbering

`InvoiceService::generateNumber(Space $space, int $year)` queries the max existing number for the space+year, then increments. Format: `FAC-2025-001`.

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

### Core Tables

| Table | Key Columns |
|-------|-------------|
| `user` | id, email, password, roles (JSON) |
| `space` | id, user_id, name, type |
| `account` | id, space_id, name, type, balance, currency, deleted_at |
| `transaction` | id, account_id, dest_account_id, category_id, type, amount, date, metadata (JSON) |
| `category` | id, space_id, parent_id, name, is_deductible, is_declarable |
| `property` | id, space_id, name, address, type, purchase_price, deleted_at |
| `lease` | id, property_id, rent, charges, type, security_deposit, is_active, deleted_at |
| `loan` | id, property_id, amount, rate, insurance_rate, start_date, duration_months |
| `client` | id, space_id, name, siret, email, address |
| `invoice` | id, client_id, number, status, total_ht, total_ttc, issued_at, due_date, quote_id |
| `tax_year` | id, space_id, year, status, note |
| `tax_item` | id, tax_year_id, transaction_id, property_id, kind, label, amount, done |
| `document` | id, space_id, name, file_url, mime_type, file_hash |
| `document_link` | id, document_id, entity_id, entity_type |
| `reminder` | id, space_id, title, due_date, status |

### All Tables Have

- `id` (int PK auto-increment)
- `space_id` (FK → space.id)
- `created_at`, `updated_at` (TIMESTAMP)
- `deleted_at` (nullable TIMESTAMP, soft delete where applicable)
