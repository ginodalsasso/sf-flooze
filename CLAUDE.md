# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**sf-flooze** is a personal finance and real-estate management SaaS built with Symfony 8.0 / PHP 8.4. It handles budgeting, property management, invoicing, and tax declarations, all scoped to multi-tenant *Spaces*.

## Development Environment

The stack runs in Docker via FrankenPHP (Caddy + PHP-FPM in one binary). Local dev uses Laragon with a direct MySQL connection (no Docker required).

| Context | Database URL |
|---------|-------------|
| Laragon (local) | `mysql://root@127.0.0.1:3306/sf_flooze` (set in `.env`) |
| Docker dev | `mysql://app:!ChangeMe!@database:3306/app` (injected by compose) |

### Common Commands

```bash
# Start Docker dev stack (builds frankenphp_dev image, mounts source)
docker compose up -d

# Symfony console (local)
php bin/console <command>

# Symfony console (Docker)
docker compose exec php bin/console <command>

# Run database migrations
php bin/console doctrine:migrations:migrate

# Generate a migration after entity changes
php bin/console doctrine:migrations:diff

# Clear cache
php bin/console cache:clear

# Run tests
php bin/phpunit

# Run a single test file
php bin/phpunit tests/path/to/SomeTest.php

# Run a single test method
php bin/phpunit --filter testMethodName

# Install JS assets
php bin/console importmap:install
```

### Docker Compose Files

- `compose.yaml` — base services (php, database)
- `compose.override.yaml` — dev overrides: mounts local source, adds Mailpit (port 8025), enables Xdebug toggle via `XDEBUG_MODE`
- `compose.prod.yaml` — production overrides

## Architecture

### Multi-tenant Data Model

Everything is scoped to a **Space**. A `User` owns one or more `Space` records. All domain entities (Account, Category, Property, Client, etc.) carry a `space_id` FK. The ERD lives in `erd/erdfinal.txt` and `erd/erd final.svg`.

### Domain Modules (planned entities, not yet implemented)

| Module | Key entities |
|--------|-------------|
| **Finance** | Account, Transaction, Category (hierarchical), Asset |
| **Real Estate** | Property, Lease, LeaseTenant, Tenant, RentPayment, Loan, LoanPayment |
| **Invoicing** | Client, Quote, QuoteLine, Invoice, InvoiceLine |
| **Tax** | TaxYear, TaxItem |
| **Generic** | Document + DocumentLink, Reminder + ReminderLink |

`Transaction` is the financial backbone — RentPayment, LoanPayment, and TaxItem all optionally link back to a Transaction.

### Symfony Layer

- **Entities** → `src/Entity/` (Doctrine ORM, attribute mapping)
- **Repositories** → `src/Repository/`
- **Controllers** → `src/Controller/`
- **Templates** → `templates/` (Twig)
- **Config** → `config/packages/` (one YAML per bundle)
- **Migrations** → `frankenphp/migrations/` (Doctrine migrations)

Doctrine uses `underscore` naming strategy and attribute-based mapping. Test databases are suffixed `_test`.

### Frontend

Assets are managed via Symfony AssetMapper (`importmap.php`) — no Node/Webpack build step. Stimulus (via `symfony/stimulus-bundle`) and Turbo (`symfony/ux-turbo`) are included for interactivity.

### Runtime

FrankenPHP serves the app. The Caddyfile (`frankenphp/Caddyfile`) rewrites all non-static, non-Mercure requests to `index.php`. The dev image runs with `--watch` for auto-reload.
