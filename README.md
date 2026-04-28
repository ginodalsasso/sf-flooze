# sf-flooze

Personal and professional financial management platform built with Symfony 8.0. Centralizes tax obligations, expense tracking, invoicing, rental properties, and investment portfolios — with AI-powered receipt OCR via Ollama.

**Stack** : PHP 8.4 · Symfony 8.0 · Doctrine 3.x · MySQL 8.0 · FrankenPHP · Ollama · dompdf

---

## Features

- **Finance** — Accounts, transactions, hierarchical categories with deductible/declarable flags
- **Real Estate** — Rental properties, leases, loan amortization, auto-generated rent payments
- **Invoicing** — Quotes and invoices with PDF export, sequential numbering, client management
- **Tax** — Annual fiscal summaries, tax item tracking, 2042 PDF export
- **AI/OCR** — Upload receipts → Ollama extracts amount, vendor, date, category
- **Documents** — File library with polymorphic attachment to any entity
- **Reminders** — Deadline tracking with email notifications (tax dates, insurance renewals)
- **Multi-tenant** — Multiple spaces (personal, professional, EIRL) per user

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
ollama serve &           # AI service
symfony serve            # App → http://localhost:8000
```

Full installation guide: [SETUP.md](SETUP.md)

---

## Project Structure

```
sf-flooze/
├── src/
│   ├── Entity/          # 20+ Doctrine entities
│   ├── Service/         # Business logic by domain (AI/, Finance/, PDF/, ...)
│   ├── Controller/      # HTTP layer by domain
│   ├── Repository/      # Custom Doctrine queries
│   ├── Form/            # Symfony form types
│   ├── EventListener/   # Doctrine lifecycle hooks
│   ├── Command/         # Console commands (cron jobs)
│   ├── Enum/            # PHP backed enums
│   └── Trait/           # SpaceScopeTrait, TimestampTrait, SoftDeleteTrait
├── templates/           # Twig (+ pdf/ for dompdf)
├── migrations/          # Doctrine migrations
├── tests/               # Unit, Integration, Functional
└── .claude/             # Claude Code context
```

Full structure: [ARCHITECTURE.md](ARCHITECTURE.md)

---

## Documentation

| Doc | Purpose |
|-----|---------|
| [CLAUDE.md](CLAUDE.md) | Dev workflow, code patterns, quick reference |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Directory structure, entity relationships, design patterns |
| [MODULES.md](MODULES.md) | Detailed specs for all 6 modules (entities, use cases, workflows) |
| [SETUP.md](SETUP.md) | Installation guide (Laragon + Docker), troubleshooting |
| [.claude/rules.md](.claude/rules.md) | SOLID principles, naming conventions, testing strategy |
| [.claude/memory.md](.claude/memory.md) | Persistent context for Claude Code sessions |

---

## Development Workflow

See [CLAUDE.md](CLAUDE.md) for:
- Code patterns and where to put code
- Naming conventions
- Doctrine entity pattern
- Before committing checklist

---

## Contributing

Follow guidelines in [.claude/rules.md](.claude/rules.md):
- SOLID principles (one service = one responsibility)
- All entities must have `space_id` (multi-tenant rule)
- Use `deleted_at` not boolean `is_deleted`
- Tests required for new services

---

## Resources

- [Symfony 8.0 Docs](https://symfony.com/doc/8.0)
- [Doctrine ORM 3.x](https://www.doctrine-project.org/projects/doctrine-orm/en/3.x/)
- [Ollama API](https://github.com/ollama/ollama/blob/main/docs/api.md)
- [dompdf](https://github.com/dompdf/dompdf)
- [Stimulus](https://stimulus.hotwired.dev)
- [Turbo](https://turbo.hotwired.dev)
