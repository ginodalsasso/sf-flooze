# CLAUDE.md — sf-flooze

**sf-flooze** : Application Symfony 8.0 hybride ERP + gestion bancaire + fiscalité personnelle/professionnelle. Multi-tenant via `Space`. OCR via Ollama local. PDF via dompdf.

**Stack** : PHP 8.4 · Symfony 8.0 · Doctrine 3.x · MySQL 8.0 · FrankenPHP · Ollama · dompdf · Stimulus/Turbo

**Docs** : [Architecture](ARCHITECTURE.md) · [Modules](MODULES.md) · [Setup](SETUP.md) · [Rules](.claude/rules.md) · [Memory](.claude/memory.md)

---

## Quick Dev Workflow

```bash
# 1. Start services
symfony serve           # PHP dev server (port 8000)
ollama serve            # Ollama IA (port 11434)
# MySQL via Laragon or Docker

# 2. Make changes + run migrations
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate

# 3. Clear cache if needed
php bin/console cache:clear

# 4. Run tests
php bin/phpunit tests/

# 5. Commit (see Before Committing below)
```

---

## Code Patterns

### Where to Put Code

| Type | Location |
|------|----------|
| Business logic | `src/Service/{Domain}/` |
| HTTP layer | `src/Controller/{Domain}/` |
| DB entities | `src/Entity/` |
| Custom queries | `src/Repository/` |
| Console tasks | `src/Command/` |
| Forms | `src/Form/` |
| Event hooks | `src/EventListener/` |
| Reusable mixins | `src/Trait/` |
| Strict types | `src/Enum/` |
| Input validation | `src/Dto/` |

### Naming Quick Reference

```
Entity:      CamelCase singular       → User, Property, Transaction
Service:     VerbNounService          → TransactionService, ReceiptOcrService
Controller:  NounController           → QuoteController, DashboardController
Repository:  CamelCaseRepository      → TransactionRepository
Enum:        NounStatusEnum           → InvoiceStatusEnum, TransactionTypeEnum
Trait:       NounTrait                → TimestampTrait, SpaceScopeTrait
DB table:    snake_case singular       → user, property, transaction
DB FK:       entity_id                → space_id, account_id, user_id
DB pivot:    parent_child             → lease_tenant, document_link
```

### Multi-tenant Rule

Every entity **must** have `space_id`. Use `SpaceScopeTrait` + `SpaceScopeVoter`. Never query without space filter.

### Service Domains

```
src/Service/
├── AI/           → OllamaClient, ReceiptOcrService, PayslipParsingService
├── Finance/      → TransactionService, CategoryService, AssetService
├── RealEstate/   → PropertyService, LeaseService, LoanService
├── Invoicing/    → QuoteService, InvoiceService
├── Tax/          → TaxItemService, TaxYearService
├── PDF/          → QuotePdfGenerator, InvoicePdfGenerator, TaxSummaryPdfGenerator
├── Document/     → DocumentService
├── Notification/ → ReminderService
└── Security/     → SpaceAuthorizationService, EncryptionService
```

### Doctrine Entity Pattern

```php
#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transaction')]
class Transaction {
    use TimestampTrait, SpaceScopeTrait, SoftDeleteTrait;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, enumType: TransactionTypeEnum::class)]
    private TransactionTypeEnum $type;
    // ...
}
```

### PDF Generation Pattern

```php
// Service generates PDF bytes from Twig template
$html = $this->twig->render('pdf/quote.html.twig', ['quote' => $quote]);
$dompdf = new Dompdf(new Options());
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
return $dompdf->output(); // bytes
```

### Ollama Integration Pattern

```php
// Text extraction
$result = $this->ollama->generate($prompt, model: 'neural-chat');
// Vision OCR
$result = $this->ollama->generateWithImage($prompt, $imagePath, model: 'llava');
```

---

## Before Committing

```bash
php bin/phpunit tests/                          # All tests green
php bin/console doctrine:schema:validate        # Schema valid
php bin/console doctrine:migrations:diff        # No pending migrations
php bin/console lint:twig templates/            # Templates valid
php bin/console debug:container --unused        # No unused services
```

---

## Key Commands

```bash
php bin/console doctrine:migrations:diff        # Generate migration
php bin/console doctrine:migrations:migrate     # Apply migrations
php bin/console doctrine:fixtures:load          # Load test data
php bin/console cache:clear                     # Clear cache
php bin/console debug:router                    # List routes
php bin/console debug:container                 # List services
```

---

## Documentation Index

- [README.md](README.md) — Project overview & quick start
- [ARCHITECTURE.md](ARCHITECTURE.md) — Directory structure, entity relationships, design patterns
- [MODULES.md](MODULES.md) — Detailed specs for all 6 modules
- [SETUP.md](SETUP.md) — Installation guide (Laragon + Docker)
- [.claude/rules.md](.claude/rules.md) — Code guidelines, SOLID, naming, testing
- [.claude/memory.md](.claude/memory.md) — Persistent context for Claude Code
