# Modules — sf-flooze

Specifications for all 6 functional modules.

See also: [ARCHITECTURE.md](ARCHITECTURE.md) · [CLAUDE.md](CLAUDE.md)

---

## Module 1: Finance

### Entities

**Account**
```
id, space_id, name, type (bank|cash|crypto|saving), balance (decimal), currency (EUR), 
created_at, updated_at, deleted_at
```

**Transaction**
```
id, account_id (FK), destination_account_id (FK nullable, transfers), category_id (FK),
type (income|expense|transfer), amount (decimal), date, description, metadata (JSON),
created_at, updated_at, deleted_at
```

**Category**
```
id, space_id, parent_id (FK self-referential), name, is_deductible (bool), 
is_declarable (bool), created_at, updated_at
```

**Asset**
```
id, space_id, ticker, name, quantity (decimal), avg_price (decimal), currency,
type (stock|crypto|etf|bond), created_at, updated_at
```

### Use Cases

- Track all financial movements (income, expense, transfer) across accounts
- Categorize transactions automatically via OCR receipt → Ollama category hint
- Identify deductible expenses (`is_deductible=true`) and declarable income (`is_declarable=true`)
- Transfer between accounts (Transaction with `destination_account_id`)
- Track crypto/stocks portfolio with average acquisition price

### Key Relationships

```
Space (1) → (N) Account
Space (1) → (N) Category
Account (1) → (N) Transaction
Category (1) → (N) Transaction
Category (0..1) → (N) Category  [parent_id hierarchy]
Transaction (1) → (0..1) Transaction  [destination for transfers]
```

### Workflows

**Workflow: Manual Transaction Entry**
```
User fills TransactionFormType (amount, date, category, account)
    ↓
AutoCategoryListener.prePersist → Ollama neural-chat → category hint (if no category selected)
    ↓
Transaction persisted → Account.balance updated (via TransactionService)
    ↓
Transaction appears in account view and category reports
```

**Workflow: OCR Receipt → Transaction**
```
User uploads receipt image
    ↓ POST /receipts/upload
ReceiptOcrService.extractFromImage($path)
    ↓ OllamaClient.generateWithImage(prompt, imagePath, model: 'llava')
Structured extraction: {amount, vendor, date, category_hint, vat, confidence}
    ↓
Return preview to user (editable form)
    ↓ POST /receipts/confirm
Create Transaction(type=expense, amount=-X)
    ↓
Attach Document (image file)
    ↓
Set category.is_deductible flag if applicable
```

---

## Module 2: Real Estate

### Entities

**Property**
```
id, space_id, name, address, type (primary|rental|secondary), purchase_price,
purchase_date, created_at, updated_at, deleted_at
```

**Tenant**
```
id, space_id, first_name, last_name, email, phone, monthly_income (decimal),
guarantor_name, guarantor_income, created_at, updated_at
```

**Lease**
```
id, property_id (FK), rent (decimal), charges (decimal), type (meuble|nu|colocation),
security_deposit (decimal), start_date, end_date, is_active, created_at, updated_at, deleted_at
```

**LeaseTenant** (junction)
```
id, lease_id (FK), tenant_id (FK)
```

**RentPayment**
```
id, lease_id (FK), transaction_id (FK nullable), amount, due_date, paid_date, 
status (pending|paid|late), created_at
```

**Loan**
```
id, property_id (FK), bank_name, amount (decimal), rate (decimal), insurance_rate (decimal),
start_date, duration_months, created_at
```

**LoanPayment**
```
id, loan_id (FK), transaction_id (FK nullable), month_number, due_date,
capital_part (decimal), interest_part (decimal), insurance_part (decimal),
remaining_capital (decimal), paid_date, created_at
```

### Use Cases

- Track all rental properties (primary, investment, secondary)
- Manage multiple tenants per property via Lease + LeaseTenant
- Auto-generate monthly RentPayment records (console command)
- Link rent payments to Transaction (income) for unified finance view
- Calculate loan amortization (capital/interest/insurance breakdown per month)
- Identify tax-deductible expenses: interest payments, insurance, property charges
- Generate LMNP/micro-BIC annual summary for tax declaration

### Key Relationships

```
Space (1) → (N) Property
Property (1) → (N) Lease
Property (1) → (N) Loan
Lease (1) → (N) LeaseTenant ← (N) Tenant  [many-to-many junction]
Lease (1) → (N) RentPayment
Loan (1) → (N) LoanPayment
RentPayment (0..1) → (1) Transaction  [auto-created income]
LoanPayment (0..1) → (1) Transaction  [auto-created expense]
```

### Workflows

**Workflow: Monthly Rent Generation**
```
Cron: php bin/console app:generate-rent-payments --month=2025-01
    ↓
GenerateRentPaymentsCommand → finds all active Leases
    ↓
For each Lease: create RentPayment(amount=rent+charges, due_date=1st of month)
    ↓
LinkedTransactionListener.postPersist(RentPayment)
    ↓
Auto-create Transaction(type=income, account=owner's account, amount=rent)
    ↓
RentPayment.status = 'pending' until confirmed paid
```

**Workflow: Loan Amortization**
```
LoanService.generateAmortizationTable(Loan $loan): array
    ↓
For each month 1..duration_months:
    interest = remaining_capital × (rate / 12)
    capital = monthly_payment - interest - insurance
    remaining -= capital
    → LoanPayment(capital_part, interest_part, insurance_part, remaining_capital)
    ↓
PDF export via LoanAmortizationPdfGenerator (table: month, capital, interest, insurance, remaining)
    ↓
Tax note: interest_part + insurance_part are deductible (rental income)
```

---

## Module 3: Invoicing (ERP)

### Entities

**Client**
```
id, space_id, name, siret, vat_number, email, phone, address, city, postal_code,
country, created_at, updated_at
```

**Quote**
```
id, client_id (FK), number (unique per space), status (draft|sent|accepted|rejected),
valid_until, note, created_at, updated_at, deleted_at
```

**QuoteLine**
```
id, quote_id (FK), description, quantity (decimal), unit_price (decimal), vat_rate (decimal),
sort_order, created_at
```

**Invoice**
```
id, client_id (FK), quote_id (FK nullable), number (FAC-YYYY-NNN), 
status (draft|sent|paid|overdue), total_ht (decimal), total_ttc (decimal),
issued_at, due_date, paid_at, note, created_at, updated_at, deleted_at
```

**InvoiceLine**
```
id, invoice_id (FK), description, quantity (decimal), unit_price (decimal),
vat_rate (decimal), total_ht (decimal), total_ttc (decimal), sort_order, created_at
```

### Use Cases

- Create quotes with line items (description, qty, unit price, VAT rate)
- Manage quote status flow: draft → sent → accepted/rejected
- Convert accepted quote to invoice (copy lines, generate number)
- Auto-generate sequential invoice number: `FAC-YYYY-NNN` (per space per year)
- Track invoice payment status: draft → sent → paid/overdue
- Generate PDF for quotes and invoices (branding, SIRET, CGV)
- Link paid invoice to Transaction (income recording)

### Key Relationships

```
Space (1) → (N) Client
Client (1) → (N) Quote
Client (1) → (N) Invoice
Quote (1) → (N) QuoteLine
Quote (0..1) → (1) Invoice  [conversion]
Invoice (1) → (N) InvoiceLine
```

### Workflows

**Workflow: Quote → Invoice → PDF**
```
User creates Quote (client + line items via QuoteFormType)
    ↓
Save Quote: status=draft, number=auto-generated
    ↓
User clicks "Preview PDF"
    ↓ GET /quotes/{id}/pdf
QuotePdfGenerator.generate($quote)
    → twig->render('pdf/quote.html.twig', [quote, lines, client])
    → dompdf->loadHtml($html)->render()
    → return PDF bytes as StreamedResponse
    ↓
User updates status: draft → sent (email PDF to client)
    ↓
Client accepts: Quote.status = 'accepted'
    ↓ POST /quotes/{id}/convert
QuoteService.convertToInvoice($quote)
    → new Invoice(client, lines copied, status=draft)
    → number = InvoiceService.generateNumber(space, year) → 'FAC-2025-003'
    ↓
Invoice PDF generated and sent to client
    ↓
Payment received: Invoice.status = 'paid', paid_at = now()
    ↓
Create Transaction(type=income, amount=total_ttc, category=client revenue)
```

**Invoice Numbering Algorithm**
```php
public function generateNumber(Space $space, int $year): string {
    $lastNumber = $this->invoiceRepo->findMaxNumberForSpaceAndYear($space, $year);
    $sequence = $lastNumber ? (int)substr($lastNumber, -3) + 1 : 1;
    return sprintf('FAC-%d-%03d', $year, $sequence);
}
```

---

## Module 4: Tax

### Entities

**TaxYear**
```
id, space_id (FK), year (int), status (draft|filed|paid), note, created_at, updated_at
```

**TaxItem**
```
id, tax_year_id (FK), transaction_id (FK nullable), property_id (FK nullable),
kind (to_declare|to_deduct|to_pay), label, amount (decimal nullable), note, done (bool),
created_at, updated_at
```

### Use Cases

- Create a fiscal year record per year (draft by default)
- Add items: income to declare, charges to deduct, taxes to pay
- Link TaxItems to source Transactions or Properties for traceability
- Calculate estimated tax: `(sum to_declare) - (sum to_deduct)` × rate
- Track what has been filed (`done=true`) and what remains
- Generate fiscal summary PDF for 2042, 2042-C-Pro declaration forms
- Support fiscal regimes: micro-BIC, réel, micro-foncier, LMNP, PEA

### Key Relationships

```
Space (1) → (N) TaxYear
TaxYear (1) → (N) TaxItem
TaxItem (N) → (0..1) Transaction  [source transaction, nullable]
TaxItem (N) → (0..1) Property     [source property, nullable]
```

### Workflows

**Workflow: Annual Tax Summary**
```
php bin/console app:generate-tax-summary --year=2024 --space=1
    ↓
GenerateTaxSummaryCommand → TaxYearService.aggregateForYear($space, 2024)
    ↓
Query: all Transaction WHERE category.is_declarable=true AND YEAR(date)=2024
    → create TaxItem(kind=to_declare, label=category.name, amount=sum)
Query: all Transaction WHERE category.is_deductible=true AND YEAR(date)=2024
    → create TaxItem(kind=to_deduct, label=category.name, amount=sum)
Query: all LoanPayment.interest_part WHERE year=2024
    → create TaxItem(kind=to_deduct, label='Intérêts emprunt', amount=sum)
    ↓
TaxYear.status = 'draft' → ready for review
    ↓
User reviews items, marks done=true one by one
    ↓ GET /tax/export/{year}
TaxSummaryPdfGenerator.generate($taxYear)
    → Render: revenues, charges, net taxable, estimated tax
    → PDF download for declaration
```

---

## Module 5: Documents & Notes de Frais

### Entities

**Document**
```
id, space_id (FK), name, file_url (S3 path), mime_type, file_hash (SHA256, for dedup),
file_size, original_name, created_at, updated_at
```

**DocumentLink** (polymorphic)
```
id, document_id (FK), entity_id (int), entity_type (FQCN string), created_at
```

### Use Cases

- Upload files: receipts (JPG/PNG), payslips (PDF), invoices (PDF), contracts
- De-duplicate files by `file_hash` (SHA256) — same file won't be stored twice
- Attach a Document to any entity (Transaction, Property, Lease, Invoice, TaxItem)
- View all documents in a unified library with search and tags
- Delete: soft-delete Document + cascade DocumentLinks
- OCR pipeline: uploaded image → Ollama llava → extract data → propose Transaction

### Key Relationships

```
Space (1) → (N) Document
Document (1) → (N) DocumentLink
DocumentLink → {Transaction | Property | Lease | Invoice | TaxItem | any entity}
```

### Polymorphic Attach Pattern

```php
// Attach receipt to transaction
$link = new DocumentLink();
$link->setDocument($document)
     ->setEntityId($transaction->getId())
     ->setEntityType(Transaction::class);
$em->persist($link);

// Retrieve all documents for an entity
$links = $docLinkRepo->findBy([
    'entityId' => $transaction->getId(),
    'entityType' => Transaction::class,
]);
$documents = array_map(fn($l) => $l->getDocument(), $links);
```

### Workflow: Receipt Upload → Transaction

```
User drags image onto receipt_upload.html.twig
    ↓ POST /receipts/upload (multipart)
ReceiptUploadController → DocumentService.store($uploadedFile, $space)
    → Compute file_hash, check dedup
    → Upload to S3/local → Document persisted
    ↓
ReceiptOcrService.extractFromImage($document->getFileUrl())
    → OllamaClient.generateWithImage($prompt, $imagePath, model: 'llava')
    → Parse JSON: {amount, vendor, date, category_hint, vat_rate, confidence}
    ↓
Return ReceiptExtractionDto to view (editable preview)
    ↓ POST /receipts/confirm
Create Transaction(type=expense, amount, date, category)
    ↓
DocumentLink(document, Transaction) persisted
    ↓
Redirect to Transaction show
```

---

## Module 6: Reminders & Obligations

### Entities

**Reminder**
```
id, space_id (FK), title, description, due_date, status (pending|done|dismissed),
priority (low|medium|high), created_at, updated_at
```

**ReminderLink** (polymorphic)
```
id, reminder_id (FK), entity_id (int), entity_type (FQCN string), created_at
```

### Use Cases

- Track administrative deadlines: tax filing (15 mai), tax payment (15 sept), insurance renewals
- Link reminders to specific entities (Property, Lease, TaxYear)
- Send email notifications before deadline (configurable: 30/15/7 days before)
- Dashboard timeline view of upcoming obligations
- Mark reminders as done or dismissed

### Key Relationships

```
Space (1) → (N) Reminder
Reminder (1) → (N) ReminderLink
ReminderLink → {Property | Lease | TaxYear | any entity}
```

### Workflows

**Workflow: Automated Deadline Notifications**
```
Cron (daily): php bin/console app:process-reminders
    ↓
ProcessRemindersCommand → ReminderService.findUpcoming()
    → SELECT * FROM reminder WHERE status='pending' AND due_date BETWEEN now AND now+30days
    ↓
For each Reminder:
    if due_date - today <= 30 days AND no email sent 30d: send "30 day reminder" email
    if due_date - today <= 7 days AND no email sent 7d: send "urgent" email
    ↓
Email via Symfony Mailer (Mailpit dev / SendGrid prod)
    ↓
Log notification sent (prevents duplicate sends)
```

**Workflow: Tax Calendar (Standard FR)**
```
On TaxYear creation: ReminderService.createStandardTaxReminders($taxYear)
    → Reminder("Déclaration revenus", due_date=May 15, priority=high)
    → Reminder("Paiement acompte 1", due_date=Feb 15, priority=medium)
    → Reminder("Paiement solde impôts", due_date=Sep 15, priority=high)
    ↓
Each Reminder linked to TaxYear via ReminderLink
    ↓
Dashboard shows timeline of upcoming obligations
```

---

## Cross-Module Connections

| From | To | Via | Purpose |
|------|----|-----|---------|
| RentPayment | Transaction | LinkedTransactionListener | Rental income auto-recorded |
| LoanPayment | Transaction | LinkedTransactionListener | Mortgage payment auto-recorded |
| Invoice paid | Transaction | InvoiceController | Client revenue recorded |
| TaxItem | Transaction | FK nullable | Declarable transaction traceable |
| TaxItem | Property | FK nullable | Property-linked deduction traceable |
| Document | Any entity | DocumentLink polymorphic | Receipt/contract attached anywhere |
| Reminder | Any entity | ReminderLink polymorphic | Deadline linked to any context |
