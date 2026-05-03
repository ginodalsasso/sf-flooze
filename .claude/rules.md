# Development Rules — sf-flooze

Code guidelines, conventions, and patterns for this Symfony 8.0 project.

---

## SOLID Principles

### Single Responsibility
One class = one reason to change. Services handle business logic only; Controllers handle HTTP only.

```php
// BAD: controller doing business logic
class InvoiceController {
    public function create(Request $request, EntityManagerInterface $em): Response {
        $invoice = new Invoice();
        $invoice->setNumber($this->generateNumber()); // business logic in controller
        $em->persist($invoice);
        ...
    }
}

// GOOD: delegate to service
class InvoiceController {
    public function create(Request $request, InvoiceService $service): Response {
        $invoice = $service->createFromRequest($request);
        return $this->redirectToRoute('invoice_show', ['id' => $invoice->getId()]);
    }
}
```

### Open/Closed
Extend via new services, don't modify existing entities. Add AI models via new service, not modifying `OllamaClient`.

### Liskov Substitution
Use interfaces for swappable components (AI client, PDF generator). Future `CloudAIClient` must be interchangeable with `OllamaClient`.

### Interface Segregation
Traits stay small and focused. `SpaceScopeTrait` only adds `space_id`. `TimestampTrait` only adds `created_at`/`updated_at`.

### Dependency Inversion
Always inject via Symfony DI container. Never `new Service()` inside another service.

```php
// BAD
class TransactionService {
    private OllamaClient $ollama;
    public function __construct() {
        $this->ollama = new OllamaClient(); // hard coupling
    }
}

// GOOD
class TransactionService {
    public function __construct(private readonly OllamaClient $ollama) {}
}
```

---

## Naming Conventions

### PHP Classes

| Type | Pattern | Example |
|------|---------|---------|
| Entity | `CamelCase` singular | `User`, `Property`, `Transaction` |
| Repository | `{Entity}Repository` | `TransactionRepository` |
| Service | `{Verb}{Noun}Service` | `ReceiptOcrService`, `TransactionService` |
| Controller | `{Noun}Controller` | `QuoteController`, `DashboardController` |
| Form | `{Noun}FormType` | `TransactionFormType`, `LeaseFormType` |
| Enum | `{Noun}{Adj}Enum` | `InvoiceStatusEnum`, `TransactionTypeEnum` |
| Trait | `{Noun}Trait` | `TimestampTrait`, `SpaceScopeTrait` |
| Event Listener | `{Trigger}Listener` | `AutoCategoryListener`, `TimestampListener` |
| Command | `{Verb}{Noun}Command` | `GenerateRentPaymentsCommand` |
| DTO | `{Action}{Noun}Dto` | `CreateTransactionDto`, `ReceiptExtractionDto` |
| PDF Generator | `{Noun}PdfGenerator` | `QuotePdfGenerator`, `TaxSummaryPdfGenerator` |
| Voter | `{Noun}Voter` | `SpaceScopeVoter` |

### Methods

```php
// Repositories: descriptive query names
findBySpaceAndDateRange(Space $space, \DateTimeInterface $from, \DateTimeInterface $to): array
findOverdueInvoices(Space $space): array
sumExpensesByCategory(Space $space, int $year): array

// Services: verb-first
createTransaction(CreateTransactionDto $dto): Transaction
reconcileWithBankStatement(Account $account, array $rows): ReconciliationResult
generateMonthlyRentPayments(\DateTimeInterface $month): int

// Controllers: HTTP verb + noun
#[Route('/transactions', name: 'transaction_index', methods: ['GET'])]
#[Route('/transactions/new', name: 'transaction_new', methods: ['GET', 'POST'])]
#[Route('/transactions/{id}', name: 'transaction_show', methods: ['GET'])]
#[Route('/transactions/{id}/edit', name: 'transaction_edit', methods: ['GET', 'POST'])]
#[Route('/transactions/{id}', name: 'transaction_delete', methods: ['DELETE'])]
```

---

## Database Conventions

### Tables
- **Singular** : `user`, `property`, `transaction` (NOT `users`, `properties`)
- **snake_case** : `tax_year`, `rent_payment`, `loan_payment`
- **Pivots** : `parent_child` format → `lease_tenant`, `document_link`

### Columns
- Primary key : `id` (int, auto-increment)
- Foreign keys : `{entity}_id` → `space_id`, `account_id`, `category_id`
- Booleans : `is_{adjective}` → `is_deductible`, `is_declarable`, `is_active`
- Soft delete : `deleted_at` (nullable TIMESTAMP, NOT boolean `is_deleted`)
- Audit : `created_at`, `updated_at` (auto via `TimestampListener`)
- Multi-tenant : **all entities** must have `space_id` FK

### Doctrine Mapping (attributes only)

```php
#[ORM\Entity(repositoryClass: PropertyRepository::class)]
#[ORM\Table(name: 'property')]
#[ORM\HasLifecycleCallbacks]
class Property {
    use TimestampTrait, SpaceScopeTrait, SoftDeleteTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: Types::STRING, enumType: PropertyTypeEnum::class)]
    private PropertyTypeEnum $type;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2, nullable: true)]
    private ?string $purchasePrice = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;
}
```

### Soft Delete
Always use `deleted_at` NULL check in queries. Use `SoftDeleteListener` to auto-filter.

```php
// Repository: always filter soft-deleted
public function findActiveBySpace(Space $space): array {
    return $this->createQueryBuilder('p')
        ->where('p.space = :space')
        ->andWhere('p.deletedAt IS NULL')
        ->setParameter('space', $space)
        ->getQuery()
        ->getResult();
}
```

---

## File Organization

### When to Create a New Service
- Distinct business domain (Finance vs Tax vs RealEstate)
- Logic used by multiple controllers
- Complexity > ~30 lines in controller
- External API interaction (Ollama, S3, email)

### Controller Rules
- Max ~50 lines per action method
- No business logic — delegate entirely to services
- Only: validate request, call service, return response/redirect
- Use `ParamConverter` / entity type-hinting for route params

### Repository Rules
- Only database queries here
- No business logic
- Return typed arrays or single entities
- Use QueryBuilder for complex filters, DQL for complex joins

```php
// Good repository method
public function findDeclarableByYear(Space $space, int $year): array {
    return $this->createQueryBuilder('t')
        ->join('t.category', 'c')
        ->where('t.space = :space')
        ->andWhere('YEAR(t.date) = :year')
        ->andWhere('c.isDeclarable = true')
        ->andWhere('t.deletedAt IS NULL')
        ->setParameters(['space' => $space, 'year' => $year])
        ->getQuery()
        ->getResult();
}
```

---

## Testing Strategy

### Hierarchy

```
tests/
├── Unit/          → Business logic isolated (mock deps)
│   ├── Service/   → OllamaClientTest, TransactionServiceTest
│   └── Entity/    → CategoryTest (hierarchy validation)
├── Integration/   → DB + queries (real DB, test fixtures)
│   ├── Repository/
│   └── EventListener/
└── Functional/    → HTTP routes, forms, workflows
    ├── Controller/
    └── Workflow/  → ReceiptToTransactionWorkflowTest
```

### Unit Test Rules
- Mock all external dependencies (HTTP, DB, Ollama)
- Test one method per test
- Name: `test{MethodName}{Scenario}` → `testExtractFromImageReturnsAmount`

```php
class ReceiptOcrServiceTest extends TestCase {
    public function testExtractFromImageReturnsStructuredData(): void {
        $mockResponse = new MockResponse(json_encode([
            'response' => '{"amount": 42.50, "vendor": "Carrefour", "date": "2025-01-15"}'
        ]));
        $client = new MockHttpClient([$mockResponse]);
        $ollama = new OllamaClient($client, 'http://localhost:11434');
        $service = new ReceiptOcrService($ollama);

        $result = $service->extractFromImage('/path/to/receipt.jpg');

        $this->assertEquals(42.50, $result->amount);
        $this->assertEquals('Carrefour', $result->vendor);
    }
}
```

### Integration Test Rules
- Use separate test DB (`.env.test`: `DATABASE_URL=...sf_flooze_test`)
- Load minimal fixtures (create only what the test needs)
- Rollback after each test via transactions

### Functional Test Rules
- Test happy path + 1 error case per endpoint
- Use `WebTestCase` for HTTP, assert response codes + redirects
- Don't test PDF bytes — just assert content-type header

---

## ERD is the Authority

**All entities must match the ERD in `ARCHITECTURE.md` exactly.** Before creating any entity, relation, or pivot table, verify it exists in the ERD. Do not invent tables, junction entities, or extra columns that are not in the schema.

```
# Before creating an entity: check ARCHITECTURE.md → "Entity Map" → "Entity Relationships (ERD Text)"
# If a relation is not in the ERD → do not create it.
```

Examples of forbidden over-engineering:
- Adding a `SpaceMembership` pivot when the ERD says `User (1) ──── (N) Space` with a plain FK

---

## Using Context7 for Documentation

When implementing or debugging anything involving a specific library or framework, **always fetch up-to-date docs via context7** before writing code. Training data may be outdated.

### When to use context7

| Use context7 | Don't use context7 |
|---|---|
| Symfony component API (forms, security, events…) | Refactoring existing business logic |
| Doctrine query syntax / mapping options | Writing services from scratch (no external API) |
| dompdf configuration / options | Code review |
| Ollama REST API parameters | General PHP patterns |
| FrankenPHP / Caddy config | Debugging pure business logic |
| AssetMapper / Stimulus / Turbo | ERD / entity design decisions |

### How to use

```bash
# 1. Resolve library ID
npx ctx7@latest library <name> "<question>"
# Example:
npx ctx7@latest library "Symfony" "how to create a custom voter"

# 2. Fetch docs with the returned ID
npx ctx7@latest docs /symfony/symfony "how to create a custom voter"

# 3. If result is unsatisfying, add --research flag
npx ctx7@latest docs /symfony/symfony "how to create a custom voter" --research
```

### Common library IDs for this project

| Library | ctx7 ID |
|---------|---------|
| Symfony | `/symfony/symfony` |
| Doctrine ORM | `/doctrine/orm` |
| dompdf | `/dompdf/dompdf` |
| Stimulus | `/hotwired/stimulus` |
| Turbo | `/hotwired/turbo` |

---

## HTML Semantics

- Use semantic elements: `<nav>`, `<main>`, `<aside>`, `<ul>`/`<li>` for lists, `<details>`/`<summary>` for collapsible UI, `<p>` for text blocks. Reserve `<div>` for non-semantic layout containers.
- Prefer `<details>`/`<summary>` for toggle/dropdown patterns before reaching for JS.
- Never use inline `style=""` — use CSS classes or modifiers.

## CSS over JS

- Use CSS for: hover/focus states, transitions, `:has(input:checked)` for radio/checkbox selection styling, `:focus-within` for parent highlighting.
- Only use JS when truly necessary: localStorage persistence, async data, complex interactions with no CSS equivalent.
- When you're about to write a JS class-toggle, ask first if `:has()`, `:checked`, `:focus-within`, or `<details>` solves it.

---

## Anti-Patterns to Avoid

- **Inventing entities** : if it's not in the ERD, don't create it
- **Over-engineering relations** : a simple FK > a pivot table unless the ERD explicitly has one
- **Fat controllers** : business logic belongs in services
- **New inside services** : always inject dependencies
- **Missing space_id** : every entity needs multi-tenant scope
- **Boolean soft-delete** : use `deleted_at` NOT `is_deleted`
- **Premature abstraction** : 3 similar lines > abstract class for 1 case
- **Microservices reflex** : monolith is fine until proven bottleneck
- **Mock DB in integration tests** : hit real DB or the test is meaningless
- **Inline SQL** : use Doctrine QueryBuilder/DQL
- **Untyped arrays** : return typed collections or DTOs from services
- **God services** : if service > 300 lines, split by use case

---

## Symfony-Specific Rules

### Routes
Use PHP attributes, not YAML routes (except API endpoints in `config/routes/api.yaml`).

```php
#[Route('/quotes/{id}/pdf', name: 'quote_pdf_download', methods: ['GET'])]
public function downloadPdf(Quote $quote, QuotePdfGenerator $gen): StreamedResponse {
    $this->denyAccessUnlessGranted('VIEW', $quote->getSpace());
    return $gen->downloadAsResponse($quote);
}
```

### Security
Always check space ownership via `SpaceScopeVoter` before accessing entities.

```php
$this->denyAccessUnlessGranted('VIEW', $entity->getSpace());
// or
$this->denyAccessUnlessGranted('EDIT', $entity->getSpace());
```

### Forms
Use FormType classes, never build forms in controllers. Add CSRF protection (auto with `AbstractType`).

### Events
Use `EventListener` (not `EventSubscriber`) for Doctrine lifecycle hooks. Use `Symfony\Component\EventDispatcher` for domain events.

### Enums
PHP 8.1+ backed enums. Always use `enumType` in Doctrine column.

```php
enum InvoiceStatusEnum: string {
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Overdue = 'overdue';
}
```

### Traits
Keep traits minimal — only properties + getters/setters. No business logic in traits.

```php
trait SoftDeleteTrait {
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function getDeletedAt(): ?\DateTimeImmutable { return $this->deletedAt; }
    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static {
        $this->deletedAt = $deletedAt;
        return $this;
    }
    public function isDeleted(): bool { return $this->deletedAt !== null; }
    public function softDelete(): void { $this->deletedAt = new \DateTimeImmutable(); }
}
```
