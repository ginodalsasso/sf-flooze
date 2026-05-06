# sf-flooze

Personal and professional financial management platform built with Symfony 8.0. Centralizes tax obligations, expense tracking, invoicing, rental properties, and investment portfolios — with AI-powered receipt OCR via a local Ollama instance.

**Stack** : PHP 8.4 · Symfony 8.0 · Doctrine 3.x · MySQL 8.0 · FrankenPHP · Ollama · dompdf · Stimulus · Turbo

---

## Features

### Finance
Track bank, cash, crypto, and saving accounts. Log income, expenses, and transfers with hierarchical categories. Categories carry `is_deductible` / `is_declarable` flags that feed directly into the Tax module.

### Real Estate
Manage rental properties, leases (including multi-tenant), and mortgages. Monthly rent payments are auto-generated via cron and linked to a `Transaction` record. Loan amortization tables are computed with capital/interest/insurance breakdown and exportable as PDF.

### Invoicing (ERP)
Full quote-to-invoice workflow: create a devis, send it, convert it to a facture on acceptance. Sequential numbering (`FAC-YYYY-NNN`) per space and year. PDF export with branding and legal mentions. Payment triggers an income `Transaction`.

### Tax
Annual fiscal summaries aggregated from flagged transactions and property data. Track items to declare, deduct, or pay. Export a PDF recap compatible with French 2042 / 2042-C-Pro forms. Standard tax calendar reminders auto-created on TaxYear creation.

### AI / OCR
Upload a receipt image → Ollama (`llama3.2-vision`) extracts amount, vendor, date, VAT, and category hint → editable preview → confirmed as a `Transaction`. Payslips and supplier invoices follow the same pipeline. On manual entry, `AutoCategoryListener` queries Ollama to suggest a category.

### Documents
File library with SHA-256 deduplication. `DocumentLink` polymorphically attaches any file (receipt, contract, PDF) to any entity without N join tables.

### Reminders & Deadlines
Deadline tracker linked to any entity via `ReminderLink`. Daily cron sends email notifications 30 and 7 days before due date (Symfony Mailer).

### Multi-Tenant (Space)
One user can have several independent spaces (personal, professional, EIRL). Every entity is scoped by `space_id`. Access enforced by `SpaceScopeVoter`.

---

## Architecture highlights

- **Transaction as financial backbone** — all money flows (rent, loan payments, invoices) converge into a single `Transaction` record, giving a unified cash-flow view regardless of origin.
- **Polymorphic links** — `DocumentLink` and `ReminderLink` attach to any entity via `(entity_id, entity_type)`, avoiding N junction tables.
- **Doctrine event listeners** — timestamp, soft-delete, auto-category (Ollama), and linked-transaction creation are all handled transparently without controller coupling.
- **Soft delete** — `deleted_at` on all deletable entities; hard deletes never happen in application code.

---

## Quick Start

**Prerequisites** : PHP 8.4, MySQL 8.0, Symfony CLI, Ollama

```bash
# 1. Install
git clone <repo-url> sf-flooze && cd sf-flooze
composer install
cp .env .env.local   # edit DATABASE_URL and OLLAMA_API_URL

# 2. Initialize
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction   # optional test data

# 3. Start
ollama serve &           # AI service on :11434
symfony serve            # App on http://localhost:8000
```

Full installation guide: [SETUP.md](SETUP.md)

---

## Project Structure

```
sf-flooze/
├── src/
│   ├── Entity/          # 24 Doctrine entities (attribute mapping)
│   ├── Service/         # Business logic by domain (AI/, Finance/, PDF/, ...)
│   ├── Controller/      # HTTP layer by domain
│   ├── Repository/      # Custom Doctrine queries
│   ├── Form/            # Symfony form types
│   ├── EventListener/   # Doctrine lifecycle hooks
│   ├── Command/         # Console commands (cron jobs)
│   ├── Enum/            # PHP backed enums
│   └── Trait/           # SpaceScopeTrait, TimestampTrait, SoftDeleteTrait
├── templates/           # Twig (+ pdf/ for dompdf rendering)
├── assets/              # Stimulus controllers, CSS
├── migrations/          # Doctrine migrations
└── tests/               # Unit, Integration, Functional
```

Full structure and ERD: [ARCHITECTURE.md](ARCHITECTURE.md) · Detailed module specs: [MODULES.md](MODULES.md)

---

## Built with

| Tool | Role |
|------|------|
| [Symfony 8.0](https://symfony.com) | MVC framework, DI, security, forms, mailer |
| [Doctrine ORM 3.x](https://www.doctrine-project.org) | Entity mapping, migrations, lifecycle listeners |
| [FrankenPHP](https://frankenphp.dev) | Production app server (replaces nginx + php-fpm) |
| [Ollama](https://ollama.ai) | Local AI for OCR (vision) and category hints — privacy-first, zero cost |
| [dompdf](https://github.com/dompdf/dompdf) | PDF generation from Twig templates — no system deps |
| [Stimulus](https://stimulus.hotwired.dev) | JS controllers (minimal, sprinkled over Twig) |
| [Turbo](https://turbo.hotwired.dev) | SPA-like navigation and form submissions |
| [Claude Code](https://claude.ai/claude-code) | AI coding assistant used throughout development |

---

## Resources

- [Symfony 8.0 Docs](https://symfony.com/doc/8.0)
- [Doctrine ORM 3.x](https://www.doctrine-project.org/projects/doctrine-orm/en/3.x/)
- [Ollama API](https://github.com/ollama/ollama/blob/main/docs/api.md)
- [dompdf](https://github.com/dompdf/dompdf)
