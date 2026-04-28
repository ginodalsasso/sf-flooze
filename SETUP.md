# Setup Guide — sf-flooze

Step-by-step installation for local development.

See also: [CLAUDE.md](CLAUDE.md) · [ARCHITECTURE.md](ARCHITECTURE.md)

Two paths: **Laragon** (Windows, simple) or **Docker** (full stack, cross-platform).

---

## Prerequisites

| Requirement | Version | Check |
|-------------|---------|-------|
| PHP | 8.4+ | `php -v` |
| MySQL | 8.0+ | `mysql --version` |
| Composer | 2.x | `composer --version` |
| Git | any | `git --version` |
| Symfony CLI | latest | `symfony version` |
| Ollama | latest | `ollama --version` |
| Node.js (optional) | 18+ | `node -v` |

---

## Option A: Laragon (Windows — Recommended for Dev)

### 1. Install Laragon

Download from https://laragon.org/download/ → Full (includes PHP 8.4, MySQL 8.0, Apache/Nginx).

Start Laragon → ensure PHP 8.4 and MySQL are running.

### 2. Install Symfony CLI

```powershell
# Windows (Scoop)
scoop install symfony-cli

# Or download from https://symfony.com/download
```

### 3. Install Ollama

Download from https://ollama.com/download/windows → run installer.

```bash
# Verify
ollama --version

# Start Ollama service
ollama serve
```

### 4. Download AI Models

```bash
# Required: text extraction, category suggestions
ollama pull neural-chat    # ~5GB — wait for full download

# Optional: vision OCR (requires GPU with 8GB VRAM)
ollama pull llava          # ~47GB

# Optional: fast fallback
ollama pull mistral        # ~5GB

# Verify
ollama list
```

### 5. Clone the Project

```bash
cd C:/laragon/www
git clone <repo-url> sf-flooze
cd sf-flooze
```

### 6. Install PHP Dependencies

```bash
composer install
```

### 7. Configure Environment

```bash
cp .env .env.local
```

Edit `.env.local`:

```dotenv
APP_ENV=dev
APP_SECRET=your-secret-here-change-me

DATABASE_URL=mysql://root@127.0.0.1:3306/sf_flooze

OLLAMA_API_URL=http://localhost:11434
OLLAMA_DEFAULT_MODEL=neural-chat
OLLAMA_VISION_MODEL=llava

MAILER_DSN=smtp://localhost:1025    # Mailpit if installed

# Optional: S3 for document storage
# AWS_S3_BUCKET=sf-flooze-dev
# AWS_ACCESS_KEY_ID=...
# AWS_SECRET_ACCESS_KEY=...
```

### 8. Create Database

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
```

### 9. Load Test Fixtures (optional)

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

### 10. Start Dev Server

```bash
symfony serve
# Access: http://localhost:8000
```

---

## Option B: Docker (Cross-Platform)

### 1. Install Docker Desktop

Download from https://docs.docker.com/desktop/ → install for your OS.

### 2. Install Ollama on Host

Ollama runs on the host machine (not in Docker) and is accessed by the container via `host.docker.internal`.

```bash
# macOS
brew install ollama

# Linux
curl -fsSL https://ollama.com/install.sh | sh

# Windows: download installer from https://ollama.com/download
```

### 3. Download Models

```bash
ollama serve &     # start in background
ollama pull neural-chat
ollama pull mistral   # lighter fallback
# Optional: ollama pull llava (47GB, GPU required)
```

### 4. Clone & Configure

```bash
git clone <repo-url> sf-flooze
cd sf-flooze
cp .env .env.local
```

Edit `.env.local`:

```dotenv
APP_ENV=dev
APP_SECRET=change-me-in-production

DATABASE_URL=mysql://app:app@database:3306/sf_flooze

OLLAMA_API_URL=http://host.docker.internal:11434
OLLAMA_DEFAULT_MODEL=neural-chat

MAILER_DSN=smtp://mailpit:1025
```

### 5. Start Services

```bash
docker compose up -d
```

Services started:
- `php` : FrankenPHP + Symfony app → http://localhost
- `database` : MySQL 8.0 → localhost:3306
- `mailpit` : Email testing → http://localhost:8025

### 6. Run Migrations

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 7. Load Test Fixtures (optional)

```bash
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

### 8. Access

- App: http://localhost
- Mailpit: http://localhost:8025
- MySQL: localhost:3306 (user: `app`, password: `app`, db: `sf_flooze`)

---

## Services Configuration

### doctrine.yaml

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        charset: utf8mb4
    orm:
        auto_generate_proxy_classes: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                type: attribute
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'

doctrine_migrations:
    migrations_paths:
        'DoctrineMigrations': '%kernel.project_dir%/migrations'
```

### services.yaml (key bindings)

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\Service\AI\OllamaClient:
        arguments:
            $baseUrl: '%env(OLLAMA_API_URL)%'
            $defaultModel: '%env(OLLAMA_DEFAULT_MODEL)%'

    App\Service\PDF\QuotePdfGenerator: ~
    App\Service\PDF\InvoicePdfGenerator: ~
    App\Service\PDF\TaxSummaryPdfGenerator: ~
    App\Service\PDF\LoanAmortizationPdfGenerator: ~
```

### security.yaml (key rules)

```yaml
# config/packages/security.yaml
security:
    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email
    firewalls:
        main:
            pattern: ^/
            form_login:
                login_path: app_login
                check_path: app_login
            logout:
                path: app_logout
    access_control:
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/register, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: ROLE_USER }
```

---

## Verify Installation

### Test Database Connection

```bash
php bin/console doctrine:schema:validate
# Expected: [OK] The mapping files are correct. [OK] The database schema is in sync.
```

### Test Ollama Connection

```bash
curl http://localhost:11434/api/tags
# Expected: JSON list of installed models

# Test generation
curl http://localhost:11434/api/generate \
  -d '{"model":"neural-chat","prompt":"Say hello","stream":false}'
```

### Test Symfony

```bash
php bin/console about
# Expected: project info without errors

php bin/console debug:router | head -20
# Expected: list of routes
```

### Run Tests

```bash
# Unit tests
php bin/phpunit tests/Unit/

# Integration tests (requires test DB)
php bin/phpunit tests/Integration/

# All tests
php bin/phpunit tests/
```

---

## Test Database Setup

```bash
# Create test database
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
```

`.env.test`:
```dotenv
DATABASE_URL=mysql://root@127.0.0.1:3306/sf_flooze_test
OLLAMA_API_URL=http://localhost:11434
```

---

## Three-Terminal Dev Setup

```bash
# Terminal 1: Ollama
ollama serve

# Terminal 2: Symfony
symfony serve
# or: php bin/console server:start

# Terminal 3: MySQL (Laragon auto-starts, or:)
# docker compose up -d database
```

---

## Common Commands Reference

```bash
# Database
php bin/console doctrine:database:create           # Create DB
php bin/console doctrine:migrations:diff           # Generate migration
php bin/console doctrine:migrations:migrate        # Apply migrations
php bin/console doctrine:migrations:migrate --down # Rollback last
php bin/console doctrine:fixtures:load             # Load test data
php bin/console doctrine:schema:validate           # Validate schema

# Dev
symfony serve                                      # Start server
php bin/console cache:clear                        # Clear cache
php bin/console debug:router                       # List routes
php bin/console debug:container                    # List services
php bin/console lint:twig templates/               # Validate templates

# Tests
php bin/phpunit tests/                             # All tests
php bin/phpunit tests/Unit/                        # Unit only
php bin/phpunit --filter testMethodName            # Single test
php bin/phpunit --coverage-html var/coverage       # With coverage

# Ollama
ollama list                                        # Installed models
ollama pull neural-chat                            # Download model
ollama serve                                       # Start API server
```

---

## Troubleshooting

### "Connection refused" on MySQL

```bash
# Laragon: ensure MySQL service is started in Laragon tray
# Docker: ensure database container is running
docker compose ps
docker compose up -d database
```

### "Ollama model not found"

```bash
ollama list               # check installed models
ollama pull neural-chat   # download if missing
```

### "Class not found" after adding entity

```bash
composer dump-autoload
php bin/console cache:clear
```

### "No pending migrations" but schema out of sync

```bash
php bin/console doctrine:migrations:diff
# If diff is empty but schema is wrong:
php bin/console doctrine:schema:update --force --dump-sql
```

### Permissions error on var/ directory

```bash
chmod -R 777 var/    # Linux/Mac
# Windows: ensure laragon user has write access to var/
```

### dompdf: blank PDF or images not loading

- Check `isRemoteEnabled` = true in dompdf Options
- Image URLs must be absolute paths or base64-encoded
- Avoid flexbox in PDF templates — use tables

### Ollama llava: "CUDA out of memory"

llava requires ~47GB GPU VRAM. Use `neural-chat` (text-only) as fallback for non-image receipts.

```bash
# Use neural-chat instead of llava for text-based extraction
ollama pull neural-chat
# Set OLLAMA_DEFAULT_MODEL=neural-chat in .env.local
```
