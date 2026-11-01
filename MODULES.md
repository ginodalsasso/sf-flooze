# Modules — sf-flooze

Specifications for all 6 functional modules.

See also: [ARCHITECTURE.md](ARCHITECTURE.md) · [CLAUDE.md](CLAUDE.md)

> Toutes les entités, **y compris les pivots-entités et les tables polymorphiques**, ont un `space_id` (FK → space) pour la défense en profondeur multi-tenant. Cela évite les bugs de jointure cross-space.
> Exception : les pivots **purs** (`transaction_tag`, `recurring_transaction_tag`, `lease_tenant`) sont de simples `JoinTable` Doctrine, sans colonne propre — leur scope `space` est assuré par le `query_builder` du formulaire et revalidé en service avant persist.

---

## Module 1: Finance

### Entities

**Account**
```
id, space_id, name, type (bank|cash|crypto|saving), opening_balance (decimal), currency,
created_at, updated_at, deleted_at
```
Pas de colonne `balance` : le solde courant se calcule (`opening_balance` + Σ des transactions actives), il ne se stocke jamais. Voir [ARCHITECTURE.md](ARCHITECTURE.md) → *Solde d'un compte — calculé, jamais stocké*.

**Transaction**
```
id, space_id, account_id (FK), destination_account_id (FK nullable, transfers), category_id (FK),
type (income|expense|transfer), amount (decimal), date, description, metadata (JSON),
created_at, updated_at, deleted_at
```

**RecurringTransaction**
```
id, space_id, account_id (FK), destination_account_id (FK nullable, transfers), category_id (FK nullable),
type (income|expense|transfer), amount (decimal), label, frequency (weekly|monthly|quarterly|yearly),
interval_count (smallint), start_date, end_date (nullable), next_occurrence_date, is_active (bool),
created_at, updated_at, deleted_at
```
Gabarit + règle de répétition. Ne crée jamais de `Transaction` seule : les échéances dues sont calculées
en lecture et matérialisées par confirmation explicite. Voir [CLAUDE.md](CLAUDE.md) → *Décisions clés*.

**Category**
```
id, space_id, parent_id (FK self-referential), name, applicable_types (JSON),
fiscal_bucket (enum nullable), fiscal_role (enum nullable), created_at, updated_at
```
La **portée fiscale** a deux faces, posées ensemble ou pas du tout :
`fiscal_bucket` = la catégorie de revenus de rattachement (`revenus_fonciers`, `bic_lmnp`,
`bnc`, `traitements_salaires`, `rcm`), `fiscal_role` = `revenu` ou `charge`.

C'est ce qui permet le cloisonnement : des travaux rattachés aux revenus fonciers en charge
s'imputent sur les loyers, et sur rien d'autre. Les deux anciens booléens
`is_declarable` / `is_deductible` disaient *si*, jamais *de quoi*. Un rôle sans bucket (ou
l'inverse) n'est compté nulle part et l'UI le signale.

**Tag**
```
id, space_id, name (unique par space), created_at, updated_at
```
Étiquette transverse (projet, événement) posée sur une transaction, sans portée fiscale.
Voir [ARCHITECTURE.md](ARCHITECTURE.md) → *Tag ≠ Category*.

**Asset**
```
id, space_id, ticker, name, currency, type (stock|crypto|etf|bond), created_at, updated_at
```
Ni quantité ni prix moyen sur l'entité : ils se dérivent du registre `AssetEntry` (achats/ventes/dividendes) via `AssetMetricsService`, même principe que le solde d'un compte — rien n'est stocké tant que ça peut se calculer depuis le ledger.

### Use Cases

- Track all financial movements (income, expense, transfer) across accounts.
- Categorize transactions automatically via OCR receipt → Ollama category hint.
- Rattacher une catégorie à une catégorie de revenus (`fiscal_bucket`) et lui donner un sens (`fiscal_role`) — c'est ce que le module Tax lit.
- Transfer between accounts (Transaction with `destination_account_id`).
- Déclarer des flux récurrents (loyer, abonnement, salaire) via `RecurringTransaction` : l'app liste les échéances dues, l'utilisateur confirme ou passe. **Aucune génération automatique.**
- Suivi du portefeuille (actions, crypto, ETF) via `Asset` + le registre `AssetEntry` — quantité et prix moyen dérivés, jamais stockés. Cours crypto suivi en direct via `CryptoPriceApiClient` (CoinGecko) ; les autres types retombent sur le prix de la dernière opération enregistrée. Voir [ARCHITECTURE.md](ARCHITECTURE.md) → *Cours des actifs — jamais bloquants, toujours datés*.

### Key Relationships

```
Space (1) → (N) Account
Space (1) → (N) Category
Space (1) → (N) Asset
Account (1) → (N) Transaction
Category (1) → (N) Transaction
Category (0..1) → (N) Category  [parent_id hierarchy]
Transaction (N) → (N) Tag        [pivot transaction_tag]
Transaction (1) → (0..1) Transaction  [destination for transfers]
Space (1) → (N) RecurringTransaction
Account (1) → (N) RecurringTransaction  [account_id, + destination_account_id nullable]
Category (0..1) → (N) RecurringTransaction
RecurringTransaction (N) → (N) Tag      [pivot recurring_transaction_tag]
RecurringTransaction (1) → (N) Transaction  [recurring_transaction_id nullable, SET NULL]
```

### Workflows

**Workflow: Manual Transaction Entry**
```
User fills TransactionFormType (amount, date, category, account)
    ↓
AutoCategoryListener.prePersist → Ollama gemma3 → category hint (if no category selected)
    ↓
Transaction persisted (via TransactionService) → account balance recomputed on read
    ↓
Transaction appears in account view and category reports
```

**Workflow: OCR Receipt → Transaction**
```
User uploads receipt image
    ↓ POST /receipts/upload
ReceiptOcrService.extractFromImage($path)
    ↓ OllamaClient.generateWithImage(prompt, imagePath, model: 'llama3.2-vision:11b')
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
id, space_id, property_id (FK), rent (decimal), charges (decimal), type (meuble|nu|colocation),
security_deposit (decimal), start_date, end_date, is_active, created_at, updated_at, deleted_at
```
`end_date` est le **terme signé**, jamais réécrit. Le terme réellement en cours se calcule en lecture
via `LeaseTermService::currentTerm()` → `LeaseTermDto` : passé le terme signé, le bail est reconduit
tacitement par tranches d'un an tant que `is_active` est vrai. Aucune écriture, aucune tâche planifiée —
ne rien faire est précisément ce qui reconduit un bail. Clôturer le bail (`is_active = false`) est le
seul moyen d'arrêter la reconduction ; le terme affiché redevient alors le terme signé.

**lease_tenant** (pivot pur, pas d'entité)
```
lease_id (FK, ON DELETE CASCADE), tenant_id (FK, ON DELETE CASCADE)
```
`ManyToMany` porté par `Lease.tenants`. Une colocation lie plusieurs locataires au même loyer.
Tout comptage de baux par locataire s'écrit en `MEMBER OF` — une jointure ManyToMany duplique les lignes.

**RentPayment**
```
id, space_id, lease_id (FK), transaction_id (FK nullable), amount, due_date, paid_date,
status (pending|paid|late), created_at
```

**Loan** — le *dossier* de prêt
```
id, space_id, property_id (FK), bank_name, start_date, created_at
```
Ni capital, ni taux, ni durée : ils appartiennent aux **lignes**. Un dossier réunissant un prêt
principal, un PTZ et un prêt employeur n'a pas de taux unique — un seul taux sur le dossier ne
pourrait être qu'une approximation. Pas de soft delete (ERD).

**LoanLine** — une ligne du dossier
```
id, space_id, loan_id (FK, ON DELETE CASCADE), label, amount (decimal),
rate (decimal 6,4 = pourcentage), insurance_rate (decimal 6,4 = pourcentage),
duration_months, negotiated_monthly_payment (decimal, nullable), created_at
```
Chaque ligne s'amortit sur ses propres termes. `LoanAmortizationService` produit un échéancier par
ligne, puis les **additionne mois par mois** pour l'échéancier du dossier — celui que le compte voit
réellement. Tout est calculé en lecture, rien n'est stocké.

Conséquence importante : si les lignes n'ont pas la même durée, **la mensualité du dossier n'est pas
constante** — elle chute à chaque ligne qui s'achève. L'UI l'annonce explicitement.

**Lissage négocié** — `negotiated_monthly_payment` (capital + intérêts, hors assurance) remplace
l'annuité calculée quand la banque a lissé cette ligne avec les autres lignes du dossier : une mensualité
réelle ne correspond alors plus à `capital/taux/durée` pris isolément. Les intérêts restent dérivés du
capital restant dû au taux de la ligne ; seule la mensualité elle-même devient une donnée fixe plutôt que
calculée. `duration_months` devient une borne maximale : une mensualité négociée plus élevée que
l'annuité éteint la ligne avant son terme, ce que la logique d'arrondi existante gère déjà sans
changement. L'assurance continue de se calculer comme pour toute ligne (`insurance_rate` × capital de la
ligne) — ce champ ne couvre que la part capital + intérêts.

**LoanPayment**
```
id, space_id, loan_id (FK), transaction_id (FK nullable), month_number, due_date,
capital_part (decimal), interest_part (decimal), insurance_part (decimal),
remaining_capital (decimal), paid_date, created_at
```
Une ligne naît à la **confirmation d'une mensualité payée**, pas à la génération de l'échéancier —
même règle que `RentPayment`. Les quatre parts y sont **figées** au moment du paiement : les intérêts
déduits d'une année fiscale ne doivent pas bouger si le taux du prêt est corrigé plus tard
(même principe que `transaction.fx_rate`).

```
Échéancier du dossier (calculé en lecture, rien stocké)
    ↓
Prochaine mensualité due : « Mensualité n°12 — 1 024 € — due le 05/11 »
    ↓
Aucune ligne loan_payment n'existe tant que l'utilisateur n'a pas confirmé le paiement
    ↓
Décision « payée » (compte débité choisi à la confirmation)
    ↓
LoanPayment(4 parts figées) + Transaction(type=expense)
    ↓
Les lignes loan_payment persistées SONT le curseur :
mensualité avec ligne = payée, mensualité sans ligne = due.
```

### Use Cases

- Track all rental properties (primary, investment, secondary).
- Manage multiple tenants per property via Lease + pivot `lease_tenant`.
- Reconduction tacite : un bail laissé en place au-delà de son terme signé court un an de plus, autant de fois que nécessaire. Terme calculé, jamais stocké.
- Calculer les échéances de loyer dues depuis le bail, et les matérialiser par confirmation explicite. **Aucune génération automatique, aucune tâche planifiée.**
- Link rent payments to Transaction (income) for unified finance view — à l'encaissement seulement.
- Calculate loan amortization (capital/interest/insurance breakdown per month).
- Identify tax-deductible expenses : interest payments, insurance, property charges.
- Generate LMNP/micro-BIC annual summary for tax declaration.

### Key Relationships

```
Space (1) → (N) Property
Property (1) → (N) Lease
Property (1) → (N) Loan
Loan (1) → (N) LoanLine  [cascade persist, orphanRemoval]
Lease (N) → (N) Tenant  [pivot lease_tenant, owning side Lease]
Lease (1) → (N) RentPayment
Loan (1) → (N) LoanPayment
RentPayment (0..1) → (1) Transaction  [auto-created income]
LoanPayment (0..1) → (1) Transaction  [auto-created expense]
```

### Workflows

**Workflow: Rent Due Dates → Payment**
```
Lease (actif) → échéances mensuelles calculées EN LECTURE de start_date à end_date
    ↓
L'écran liste les échéances dues : « Loyer de novembre — 850 € — dû depuis le 01/11 »
    ↓
Aucune ligne rent_payment n'existe tant que l'utilisateur n'a rien décidé (état `pending` calculé)
    ↓
Décision « encaissé »                        Décision « impayé »
    ↓                                            ↓
RentPayment(status=paid, paid_date)          RentPayment(status=late)
    + Transaction(type=income, compte         sans Transaction
      choisi à la confirmation)
    ↓
Les lignes rent_payment persistées SONT le curseur :
échéance avec ligne = traitée, échéance sans ligne = due.
```

> **Pas de commande `app:generate-rent-payments`, pas de `postPersist` sur `RentPayment`.**
> Un cron qui écrirait la `Transaction` le 1er du mois rendrait le solde faux pendant tout le délai
> de paiement, et faux en permanence sur un impayé — or `account.opening_balance + Σ transactions`
> est la seule source de vérité du module Finance. Voir [CLAUDE.md](CLAUDE.md) → *Décisions clés*.

**Workflow: Loan Amortization** — *implémenté (lecture seule)*
```
LoanAmortizationService::schedule(Loan) → LoanScheduleDto { combined, lines[], borrowedCapital }
    ↓
POUR CHAQUE LIGNE, sur ses propres termes :
annuité = C × i × (1+i)^n / ((1+i)^n − 1)       i = taux annuel de la ligne / 100 / 12
          C / n  si i = 0                       (prêt à taux zéro)
assurance = capital de la ligne × taux assurance / 100 / 12   → prime constante
    ↓
Pour chaque mois 1..duration_months :
    intérêts = arrondi(capital restant × i)
    capital  = annuité − intérêts
    échéance = date de départ + N mois, jour borné au mois cible (DateShifter)
    → AmortizationRowDto (calculé, rien de persisté)
    ↓
La DERNIÈRE échéance absorbe le résidu d'arrondi : le capital restant finit exactement à 0,00,
jamais à ±0,01. C'est la mensualité finale qui varie de quelques centimes, pas le solde.
    ↓
PUIS FUSION DES LIGNES, mois par mois → échéancier combiné
    Une ligne terminée ne contribue plus aux mois suivants : la mensualité du dossier baisse.
    ↓
Regroupement par année civile → AmortizationYearDto, pour le combiné et pour chaque ligne
    → intérêts + assurance de l'année = la part déductible des revenus fonciers (régime réel)
```

> **Aucune `Transaction` n'est créée par l'échéancier.** Il décrit ce qui est dû, pas ce qui est payé.
> Les mensualités réellement réglées entrent dans le registre via `LoanPayment`, confirmées une à
> une, et seule la part intérêts + assurance est déductible — jamais le capital remboursé.
>
> Hypothèses du calcul, à vérifier contre l'offre de prêt : **taux fixe**, mensualité constante,
> première échéance un mois après le déblocage, et **assurance sur le capital emprunté** (prime
> constante). Certains contrats l'assoient sur le capital restant dû : la prime décroît alors.
>
> Export PDF via `LoanAmortizationPdfGenerator` : à faire (dompdf pas encore installé).

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
id, space_id, client_id (FK), number, status (draft|sent|accepted|rejected),
valid_until, note, created_at, updated_at, deleted_at
```

**QuoteLine**
```
id, space_id, quote_id (FK), description, quantity (decimal), unit_price (decimal),
vat_rate (decimal), sort_order, created_at
```

**Invoice**
```
id, space_id, client_id (FK), quote_id (FK nullable), number (FAC-YYYY-NNN),
status (draft|sent|paid|overdue), total_ht (decimal), total_ttc (decimal),
issued_at, due_date, paid_at, note, created_at, updated_at, deleted_at
```

**InvoiceLine**
```
id, space_id, invoice_id (FK), description, quantity (decimal), unit_price (decimal),
vat_rate (decimal), total_ht (decimal), total_ttc (decimal), sort_order, created_at
```

### Use Cases

- Create quotes with line items (description, qty, unit price, VAT rate).
- Manage quote status flow : draft → sent → accepted/rejected.
- Convert accepted quote to invoice (copy lines, generate number).
- Auto-generate invoice number `FAC-YYYY-NNN` — **séquentiel par space et par année** (le compteur repart à 1 chaque 1er janvier, par space).
- Track invoice payment status : draft → sent → paid/overdue.
- Generate PDF for quotes and invoices (branding, SIRET, CGV).
- Link paid invoice to Transaction (income recording).

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
    $sequence = $lastNumber ? (int) substr($lastNumber, -3) + 1 : 1;
    return sprintf('FAC-%04d-%03d', $year, $sequence);
}
```

---

## Module 4: Tax

> **Déjà en place : la page Déclaration** (`/tax/declaration`, `TaxSummaryService`) — vue annuelle
> **calculée et en lecture seule**, construite sur `Transaction` × `Category.fiscal_bucket` /
> `fiscal_role`. Elle ne persiste rien : ni `TaxYear`, ni `TaxItem`, qui restent à implémenter
> pour la revue cochable et l'export 2042.
>
> **Une assiette par catégorie de revenus, jamais de solde global.** `TaxSummaryDto` n'expose
> volontairement aucun « résultat imposable » unique : la loi cloisonne, et soustraire des
> charges d'une catégorie aux revenus d'une autre n'a aucun sens. Pour chaque catégorie, deux
> assiettes sont affichées côte à côte :
> - **au réel** : revenus − charges de cette catégorie ;
> - **au micro** : revenus − abattement forfaitaire, **charges ignorées** (l'abattement les remplace).
>
> Le plus avantageux est signalé. Les taux sont dans `FiscalBucketEnum` et nulle part ailleurs ;
> les **plafonds d'éligibilité ne sont pas vérifiés** et l'UI le dit.
>
> Règles de correction structurantes :
> - **Les virements sont exclus** — déplacer de l'argent entre ses propres comptes n'est ni un revenu ni une charge ; les compter gonflerait la déclaration des deux côtés.
> - **Chaque total vient d'un `GROUP BY`**, et les lignes affichées sont additionnées telles quelles : ce qui est à l'écran fait toujours la somme affichée à côté.
> - **Les mouvements hors périmètre sont affichés**, pas masqués (sans catégorie, ou rattachement incomplet). Sur une déclaration, le chiffre dangereux est celui qui manque.
> - **Une catégorie à charges seules apparaît quand même** — un déficit est un résultat réel ; le masquer ferait perdre une déduction. Son imputation suit ses propres règles, que Flooze ne calcule pas.
> - **Les charges d'emprunt sont hors assiettes** — calculées depuis l'échéancier (`LoanChargeService`, côté RealEstate), affichées dans leur propre tableau avec l'avertissement de double comptage. Le domaine Tax ne lit que le registre : la composition des deux se fait dans le controller, pas dans `TaxSummaryService` (cf. [`rules.md`](.claude/rules.md) → un service par domaine).

### Entities

**TaxYear**
```
id, space_id (FK), year (int), status (draft|filed|paid), note, created_at, updated_at
```

**TaxItem**
```
id, space_id, tax_year_id (FK), transaction_id (FK nullable), property_id (FK nullable),
kind (to_declare|to_deduct|to_pay), label, amount (decimal nullable), note, done (bool),
created_at, updated_at
```

### Use Cases

- Create a fiscal year record per year (draft by default).
- Add items : income to declare, charges to deduct, taxes to pay.
- Link TaxItems to source Transactions or Properties for traceability.
- Calculate estimated tax : `(sum to_declare) - (sum to_deduct)` × rate.
- Track what has been filed (`done=true`) and what remains.
- Generate fiscal summary PDF for 2042, 2042-C-Pro declaration forms.
- Support fiscal regimes : micro-BIC, réel, micro-foncier, LMNP, PEA.

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
id, space_id, document_id (FK), entity_id (int), entity_type (FQCN string), created_at
```

### Use Cases

- Upload files : receipts (JPG/PNG), payslips (PDF), invoices (PDF), contracts.
- De-duplicate files by `file_hash` (SHA256) — same file won't be stored twice.
- Attach a Document to any entity (Transaction, Property, Lease, Invoice, TaxItem).
- View all documents in a unified library with search and tags.
- Delete : soft-delete Document + cascade DocumentLinks.
- OCR pipeline : uploaded image → Ollama vision → extract data → propose Transaction.

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
$link->setSpace($transaction->getSpace())
     ->setDocument($document)
     ->setEntityId($transaction->getId())
     ->setEntityType(Transaction::class);
$em->persist($link);

// Retrieve all documents for an entity (scoped by space for safety)
$links = $docLinkRepo->findBy([
    'space' => $activeSpace,
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
    → OllamaClient.generateWithImage($prompt, $imagePath, model: 'llama3.2-vision:11b')
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
id, space_id, reminder_id (FK), entity_id (int), entity_type (FQCN string), created_at
```

### Use Cases

- Track administrative deadlines : tax filing (15 mai), tax payment (15 sept), insurance renewals.
- Link reminders to specific entities (Property, Lease, TaxYear).
- Send email notifications before deadline (configurable : 30/15/7 days before).
- Dashboard timeline view of upcoming obligations.
- Mark reminders as done or dismissed.

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
| RentPayment | Transaction | RentPaymentService::confirm() | Rental income auto-recorded |
| LoanPayment | Transaction | LoanPaymentService::confirm() | Mortgage payment auto-recorded |
| Invoice paid | Transaction | InvoiceController | Client revenue recorded |
| TaxItem | Transaction | FK nullable | Declarable transaction traceable |
| TaxItem | Property | FK nullable | Property-linked deduction traceable |
| Document | Any entity | DocumentLink polymorphic | Receipt/contract attached anywhere |
| Reminder | Any entity | ReminderLink polymorphic | Deadline linked to any context |