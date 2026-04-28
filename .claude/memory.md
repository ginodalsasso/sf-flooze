# Memory — sf-flooze

Persistent context for Claude Code sessions. Updated: 2026-04-28.

---

## Project

**sf-flooze** : Symfony 8.0 app — ERP hybride + gestion bancaire + fiscalité personnelle/professionnelle.
Utilisateur cible : indépendant/entrepreneur/investisseur.

---

## Tech Stack

| Layer | Tech |
|-------|------|
| Language | PHP 8.4 |
| Framework | Symfony 8.0 |
| ORM | Doctrine 3.x (attribute mapping) |
| Database | MySQL 8.0 (InnoDB, UTF-8) |
| Server | FrankenPHP (Caddy + PHP-FPM) |
| Frontend | Symfony AssetMapper + Stimulus JS + Turbo |
| PDF | dompdf 2.0 (pure PHP) |
| AI/OCR | Ollama local (neural-chat + llava) |
| Email (dev) | Mailpit (localhost:8025) |
| Storage | Local uploads or S3/DigitalOcean |

---

## Entity Hierarchy

```
User
└── Space (multi-tenant unit)
    ├── Account → Transaction → Category
    ├── Asset
    ├── Property → Lease → LeaseTenant → Tenant
    │              └── RentPayment → Transaction
    ├── Loan → LoanPayment → Transaction
    ├── Client → Quote → QuoteLine
    │         └── Invoice → InvoiceLine
    ├── TaxYear → TaxItem
    ├── Document → DocumentLink (polymorphic)
    └── Reminder → ReminderLink (polymorphic)
```

---

## Key Entities & Fields

| Entity | Key Fields |
|--------|-----------|
| Space | id, user_id, name, type |
| Account | id, space_id, name, type (bank\|cash\|crypto\|saving), balance, currency |
| Transaction | id, account_id, destination_account_id, category_id, type, amount, date, metadata (JSON) |
| Category | id, space_id, parent_id, name, is_deductible, is_declarable |
| Property | id, space_id, name, address, type (primary\|rental\|secondary), purchase_price |
| Lease | id, property_id, rent, charges, type, security_deposit, is_active |
| Invoice | id, client_id, number (FAC-YYYY-NNN), status, total_ht, total_ttc, quote_id |
| TaxYear | id, space_id, year, status (draft\|filed\|paid) |
| TaxItem | id, tax_year_id, kind (to_declare\|to_deduct\|to_pay), amount, done |
| Document | id, space_id, file_url, mime_type, file_hash |
| DocumentLink | document_id, entity_id, entity_type (polymorphic) |

---

## Multi-Tenancy

- **All** entities have `space_id` FK
- Use `SpaceScopeTrait` + `SpaceScopeVoter` everywhere
- Active space stored in session, switched via `SpaceSwitcherController`
- Never query without space filter

---

## AI / Ollama

**Base URL** : `http://localhost:11434` (host) / `http://host.docker.internal:11434` (Docker)

| Model | Use | Size |
|-------|-----|------|
| `neural-chat` | Text extraction, categories, recommendations | ~5GB |
| `llava` | Vision OCR (receipt images) | ~47GB |
| `llama2` | Fallback text | ~4GB |
| `mistral` | Fast fallback | ~5GB |
| `nomic-embed-text` | Embeddings (future) | ~3GB |

**Main services** : `OllamaClient` → `ReceiptOcrService` / `PayslipParsingService` / `FiscalRecommendationService`

---

## PDF Generation

- Library : **dompdf 2.0** (pure PHP, no system deps)
- Twig template → HTML → dompdf → PDF bytes
- Templates in `templates/pdf/`
- Generators in `src/Service/PDF/`
- No flexbox in PDF templates (dompdf limitation — use floats/tables)

---

## Entity Design Rules

- **ERD is the single source of truth** for all entity design. See `ARCHITECTURE.md` → "Entity Relationships (ERD Text)".
- Never create a pivot table, junction entity, or extra relation that does not appear in the ERD.
- Never add fields (roles, flags, extra FKs) that are not listed in `ARCHITECTURE.md` → "Database Schema Summary".
- A simple `User (1) ──── (N) Space` FK means exactly that — not a `SpaceMembership` pivot.

---

## Key Architectural Decisions

1. **dompdf over wkhtmltopdf** : zero system dependencies, Docker-friendly, FrankenPHP compatible
2. **Ollama local over cloud AI** : privacy-first, no API costs, works offline
3. **DocumentLink polymorphism** : one Document entity attached to any entity type (avoids N join tables)
4. **Symfony monolith** : no microservices until proven bottleneck — Symfony DI is sufficient
5. **Space = multi-tenant unit** : one user can have multiple spaces (personal, business, EIRL)
6. **RentPayment/LoanPayment → Transaction** : all money flows go through `Transaction` (single source of truth for Finance module)

---

## Running Services (Dev)

```bash
Terminal 1: symfony serve          # http://localhost:8000
Terminal 2: ollama serve           # http://localhost:11434
Terminal 3: # MySQL via Laragon (auto-started) or docker compose up -d database
```

---

## Key Repositories (Custom Queries)

| Repository | Notable Methods |
|-----------|----------------|
| `TransactionRepository` | `findBySpaceAndDateRange`, `sumByCategory`, `findUnreconciled` |
| `TaxItemRepository` | `groupByKind`, `aggregateByCategory`, `findByTaxYear` |
| `InvoiceRepository` | `findOverdue`, `findByStatus`, `sumPaidByYear` |
| `PropertyRepository` | `findActiveRentals`, `findBySpace` |
| `LeaseRepository` | `findActiveByProperty`, `findExpiringWithin` |

---

## Invoice Numbering

Format : `FAC-YYYY-NNN` (auto-incremented per space per year)
Logic in : `src/Service/Invoicing/InvoiceService.php` → `generateNumber(Space, year)`

--- 