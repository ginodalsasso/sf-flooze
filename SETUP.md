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
| Node.js (optional, AssetMapper) | 18+ | `node -v` |

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
ollama --version
ollama serve              # start API server
```

### 4. Download AI Models

```bash
# Required: text extraction (categories, recommendations)
ollama pull gemma3                 # ~3.3 GB

# Required: vision OCR (receipts, invoices, payslips)
ollama pull llama3.2-vision:11b    # ~7.9 GB — needs ~8GB RAM/VRAM

# Optional: lightweight text fallback
ollama pull mistral                # ~4.4 GB

# Verify
ollama list
```

> Si la machine n'a pas 8 GB de VRAM/RAM disponibles pour llama3.2-vision, fallback : utiliser `gemma3` aussi pour les images (moins précis sur l'OCR mais fonctionnel) ou désactiver les features vision.

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
OLLAMA_DEFAULT_MODEL=gemma3
OLLAMA_VISION_MODEL=llama3.2-vision:11b

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
ollama pull gemma3
ollama pull llama3.2-vision:11b
ollama pull mistral   # optional fallback
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
OLLAMA_DEFAULT_MODEL=gemma3
OLLAMA_VISION_MODEL=llama3.2-vision:11b

MAILER_DSN=smtp://mailpit:1025
```

### 5. Start Services

```bash
docker compose up -d
```

Services :
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
            $visionModel: '%env(OLLAMA_VISION_MODEL)%'

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

# Test text generation
curl http://localhost:11434/api/generate \
  -d '{"model":"gemma3","prompt":"Say hello","stream":false}'
```

### Test Symfony

```bash
php bin/console about
php bin/console debug:router | head -20
```

### Run Tests

```bash
php bin/phpunit tests/Unit/
php bin/phpunit tests/Integration/
php bin/phpunit tests/
```

---

## Test Database Setup

```bash
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

# Terminal 3: MySQL (Laragon auto-démarre, sinon :)
docker compose up -d database
```

---

## Common Commands Reference

```bash
# Database
php bin/console doctrine:database:create
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
php bin/console doctrine:migrations:migrate prev    # rollback last
php bin/console doctrine:fixtures:load
php bin/console doctrine:schema:validate

# Dev
symfony serve
php bin/console cache:clear
php bin/console debug:router
php bin/console debug:container
php bin/console lint:twig templates/

# Tests
php bin/phpunit tests/
php bin/phpunit tests/Unit/
php bin/phpunit --filter testMethodName
php bin/phpunit --coverage-html var/coverage

# Ollama
ollama list
ollama pull gemma3
ollama serve
```

---

## Troubleshooting

### "Connection refused" on MySQL

```bash
# Laragon: ensure MySQL service is started in Laragon tray
# Docker:
docker compose ps
docker compose up -d database
```

### "Ollama model not found"

```bash
ollama list
ollama pull gemma3
ollama pull llama3.2-vision:11b
```

### "Class not found" after adding entity

```bash
composer dump-autoload
php bin/console cache:clear
```

### "No pending migrations" but schema out of sync

```bash
php bin/console doctrine:migrations:diff
# Si diff vide mais schéma faux :
php bin/console doctrine:schema:update --force --dump-sql
```

### Permissions error on var/ directory

```bash
chmod -R 777 var/    # Linux/Mac
# Windows: laragon user must have write access to var/
```

### dompdf: blank PDF or images not loading

- Check `isRemoteEnabled` = true in dompdf Options.
- Image URLs must be absolute paths or base64-encoded.
- Avoid flexbox in PDF templates — use tables.

### llama3.2-vision: "out of memory" or slow

- `llama3.2-vision:11b` requires ~8 GB VRAM (or RAM in CPU mode, much slower).
- Fallback : use `gemma3` for everything (lower OCR accuracy on images).
- Or pull a lighter vision model when available.