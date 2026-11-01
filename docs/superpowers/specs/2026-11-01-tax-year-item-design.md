# TaxYear & TaxItem — design

Date: 2026-11-01
Status: approved for planning

## Contexte

Le module Tax a aujourd'hui une seule page : `/tax/declaration`
(`TaxSummaryService`, `DeclarationController`), une vue annuelle **calculée en
lecture** depuis `Transaction × Category.fiscal_bucket/fiscal_role`. Elle
affiche, par catégorie de revenus (`FiscalBucketEnum`), les revenus et charges
de l'année, les deux assiettes possibles (réel/micro via `TaxBucketDto`), et
signale celle qui est la plus avantageuse (`TaxBucketDto::cheaperRegime()`).
Rien n'y est coché, rien n'y est persisté.

Il manque la **revue** : un moyen de suivre, année après année, quelles lignes
ont été reportées sur la déclaration officielle, quel régime a réellement été
retenu par bucket, et où en est le dossier (brouillon / déposé / payé).
`ARCHITECTURE.md` prévoit déjà `TaxYear`/`TaxItem` dans l'ERD cible ; ce spec
en fixe la forme définitive — plus simple que l'esquisse initiale de
`MODULES.md`, qui portait un montant et des FK `Transaction`/`Property` par
ligne.

**Principe directeur, posé explicitement pendant le brainstorming** :
`TaxItem` ne stocke jamais un montant recalculable. `CLAUDE.md` dit *« La
déclaration se lit, elle ne se stocke pas »* précisément pour qu'aucune dérive
ne soit possible entre le registre et ce qui est déclaré — `TaxItem` ne doit
pas devenir une deuxième source de vérité. Son seul rôle : mémoriser qu'une
ligne déjà calculée par `TaxSummaryService` a été traitée.

## Objectifs

- `TaxYear` : un dossier par `(space, année)`, avec un statut
  (`draft|filed|paid`) et une note libre.
- Pour chaque bucket fiscal **et** chaque rôle (revenu/charge) affiché par
  `TaxSummaryDto`, une case à cocher « reporté sur la déclaration »
  (`TaxItem`), créée à la demande — jamais pré-générée pour tous les buckets.
- Pour chaque bucket qui admet un régime micro (`FiscalBucketEnum::allowsMicro()`),
  un choix persisté du régime réellement retenu (`reel`|`micro`), indépendant
  de la recommandation affichée par `cheaperRegime()`.
- Une page `/tax/years/{year}` qui réutilise l'affichage de
  `/tax/declaration` (facturisé en composant Twig partagé) en y ajoutant les
  cases, le sélecteur de régime, le changement de statut et la note.

## Non-objectifs

- Pas d'export PDF 2042 (`TaxExportService`/`TaxExportController`) — deuxième
  feature indépendante, qui nécessite d'installer dompdf (pas encore présent
  dans le projet).
- Pas de `TaxItem` par mouvement individuel ni par propriété — granularité
  bucket × rôle uniquement, décidée explicitement.
- Pas de `kind=to_pay` / suivi d'un montant d'impôt à payer — n'a pas de sens
  à la granularité bucket × rôle retenue ; hors périmètre de cette itération.
- Pas de tests PHPUnit écrits dans le cadre de cette feature (uniquement sur
  demande explicite, cf. mémoire projet).

## Entités & migrations

### `TaxYear`

```text
id, space_id (FK), year (smallint), status (enum: draft|filed|paid),
regime_choices (JSON, nullable), note (text, nullable),
created_at, updated_at
```

- Contrainte unique `(space_id, year)` — un seul dossier par space et par
  année.
- `regime_choices` : map `{ "bic_lmnp": "reel", "revenus_fonciers": "micro" }`.
  Clés = valeurs de `FiscalBucketEnum`, valeurs = `'reel'|'micro'` — les
  **mêmes chaînes littérales** que `TaxBucketDto::cheaperRegime()` déjà dans
  le code, pas un nouvel enum PHP : un JSON borné à deux valeurs connues ne
  justifie pas une classe séparée (cf. `.claude/rules.md` → *Simplicité*).
  N'est jamais interrogé en jointure, même principe que `transaction.metadata`.
- Pas de `deleted_at` — confirmé par `ARCHITECTURE.md` (cycle de vie court /
  conservé pour audit, comme `tax_item`, `tag`, `category`).
- `TaxYearStatusEnum` : `DRAFT`, `FILED`, `PAID` (même style que
  `RentPaymentStatusEnum`).

### `TaxItem`

```text
id, space_id (FK), tax_year_id (FK), fiscal_bucket (enum FiscalBucketEnum),
fiscal_role (enum FiscalRoleEnum), done (bool, default false),
created_at, updated_at
```

- Contrainte unique `(tax_year_id, fiscal_bucket, fiscal_role)`.
- `ORM\ManyToOne` vers `TaxYear`, `JoinColumn(onDelete: 'CASCADE')` —
  supprimer un dossier d'année supprime ses coches.
- Pas de FK `transaction_id`/`property_id`, pas de champ `amount`, pas de
  `kind` à trois valeurs : écartés pendant le brainstorming, la granularité
  bucket × rôle les rend inutiles et ils dupliqueraient `TaxSummaryService`.
- Pas de `deleted_at` (même raison que `TaxYear`).
- Créée à la demande : une ligne n'existe que si l'utilisateur a coché au
  moins une fois (voir `TaxItemService::toggle()`), jamais pré-générée pour
  tous les buckets d'une année.

Les deux entités suivent les conventions existantes : `SpaceScopeTrait`,
`TimestampTrait`, migration générée via
`php bin/console doctrine:migrations:diff`.

## Repositories

- `TaxYearRepositoryInterface` / `TaxYearRepository` :
  - `findBySpaceAndYear(Space $space, int $year): ?TaxYear`
  - `findBySpace(Space $space): array` (tri par `year` desc, pour l'index)
- `TaxItemRepositoryInterface` / `TaxItemRepository` :
  - `findOne(TaxYear $taxYear, FiscalBucketEnum $bucket, FiscalRoleEnum $role): ?TaxItem`
  - `findByTaxYear(TaxYear $taxYear): array` (pour construire l'état `done`
    par bucket/rôle sans N requêtes)

## Services & règles de workflow

### `TaxYearServiceInterface` / `TaxYearService`

```php
interface TaxYearServiceInterface
{
    public function getOrCreate(Space $space, int $year): TaxYear;
    public function transition(TaxYear $taxYear, TaxYearStatusEnum $to): void;
    public function setRegimeChoice(TaxYear $taxYear, FiscalBucketEnum $bucket, ?string $regime): void;
    public function setNote(TaxYear $taxYear, ?string $note): void;
}
```

- `getOrCreate()` : visiter `/tax/years/{year}` crée le dossier en `draft`
  s'il n'existe pas — pas de formulaire « nouvelle année » séparé, même
  logique que les échéances calculées ailleurs dans l'app.
- `transition()` : guard — un seul pas à la fois, dans un sens ou l'autre
  (`draft↔filed↔paid`), jamais `draft→paid` ni `paid→draft` directs. Lève
  `\InvalidArgumentException` sinon.
- `setRegimeChoice()` : guard — `$regime` doit être `null`, `'reel'` ou
  `'micro'` ; `'micro'` rejeté si `!$bucket->allowsMicro()` (RCM).
- Pas de `$em->wrapInTransaction()` : chaque appel touche une seule ligne
  `TaxYear`, pas d'écriture multi-entités interdépendante (`CLAUDE.md` #6 ne
  s'applique pas ici).

### `TaxItemServiceInterface` / `TaxItemService`

```php
interface TaxItemServiceInterface
{
    public function toggle(TaxYear $taxYear, FiscalBucketEnum $bucket, FiscalRoleEnum $role): TaxItem;
}
```

- Upsert par la clé unique `(tax_year, bucket, role)` : si aucune ligne,
  crée `done=true` ; si une ligne existe, bascule `done`. La ligne n'est
  jamais supprimée (une coche retirée reste une trace, `done=false`).
- Guard : `$role` doit être cohérent avec `$bucket` — rejette `CHARGE` si
  `!$bucket->acceptsCharges()` (RCM n'a pas de charges déductibles, donc pas
  de case à cocher pour ce couple).

## Controllers & routes

Un seul `TaxYearController` (`src/Controller/Tax/`) — pas de
`TaxItemController` séparé, basculer une case est une action trop fine pour
justifier un second contrôleur :

```text
GET  /tax/years                      app_tax_year_index  : liste des TaxYear du space
GET  /tax/years/{year}               app_tax_year_show   : getOrCreate + fusion avec TaxSummaryDto
POST /tax/years/{year}/status        app_tax_year_status : transition (body: to)
POST /tax/years/{year}/regime        app_tax_year_regime : setRegimeChoice (body: bucket, regime)
POST /tax/years/{year}/note          app_tax_year_note   : setNote (body: note)
POST /tax/years/{year}/items/toggle  app_tax_year_item_toggle : toggle (body: bucket, role)
```

- `{year}` (int) plutôt que `{id}` : lisible, et la contrainte unique
  `(space_id, year)` en fait une clé métier valide.
- Chaque action résout le space actif
  (`ActiveSpaceControllerTrait::resolveActiveSpace()`), appelle
  `getOrCreate()`, puis `denyAccessUnlessGranted('VIEW'|'EDIT', $taxYear->getSpace())`
  — un `{year}` posté ne prouve rien sur son propriétaire, même garde-fou que
  partout ailleurs dans l'app.
- `show()` : construit la même `TaxSummaryDto` que `DeclarationController`
  (`TaxSummaryServiceInterface::summarize()`) + l'état des `TaxItem` existants
  pour l'année (`findByTaxYear()`, indexé par `bucket-role` pour un lookup
  direct côté template) + `regime_choices` du `TaxYear`.

## Formulaires

Pas de `FormType` Symfony classique pour ces actions : ce sont des toggles
et des changements d'état ponctuels (case, select, bouton de statut), pas des
formulaires métier avec validation complexe. Chaque action est un `POST` avec
jeton CSRF manuel (même pattern que `toggle`/`delete` ailleurs dans le
projet : `isCsrfTokenValid()`), pas de `FormType` dédié — cohérent avec
*Simplicité, pas de sur-engineering*.

- `app_tax_year_regime` : `<select>` natif (`reel`/`micro`/vide), affiché
  seulement si `bucket->allowsMicro()`.
- `app_tax_year_item_toggle` : `<button type="submit">` par case, pas de
  JS requis (cf. mémoire projet *feedback_no_js* — éviter JS quand un form
  Symfony suffit ; ici même un simple POST HTML suffit).
- `app_tax_year_note` : `<textarea>` dans un petit form avec jeton CSRF.

## Sécurité

- `denyAccessUnlessGranted('VIEW', ...)` sur `index`/`show`, `'EDIT'` sur les
  quatre actions d'écriture — toutes via `SpaceScopeVoter` sur
  `$taxYear->getSpace()`.
- CSRF manuel vérifié sur chaque `POST` (`status`, `regime`, `note`,
  `items/toggle`).
- Aucune action destructive (pas de delete sur `TaxYear`/`TaxItem` dans ce
  spec).
- `setRegimeChoice`/`toggle` revalidés côté service (pas seulement côté UI) :
  un bucket qui n'admet pas le micro, ou un rôle que le bucket n'accepte pas,
  reste rejeté même si la requête est forgée.

## Frontend — factorisation

`/tax/declaration` et `/tax/years/{year}` affichent la même structure bucket
→ revenus/charges → assiettes réel/micro. Le tableau par bucket devient un
composant Twig partagé (`templates/tax/_bucket_table.html.twig` ou Twig
Component selon ce qu'`FRONTEND.md` préconise pour ce cas), pris par les deux
pages, paramétré par un flag `editable: bool` :

- `editable=false` (déclaration) : affichage pur, comme aujourd'hui.
- `editable=true` (revue) : ajoute, par ligne revenu/charge, la case
  `TaxItem.done` ; par bucket, le sélecteur de régime si applicable.

Détail du markup laissé au plan d'implémentation.

## Mise à jour de la documentation

- `ARCHITECTURE.md` : retirer `TaxItem (N) → (0..1) Transaction`/`Property`
  de l'ERD texte, ajouter `TaxYearService`/`TaxItemService` à la section
  *Service Layer* (déjà listés dans `Tax/` mais avec l'ancienne description
  "Aggregate, calculate, export" pour `TaxYearService` — à corriger), et
  `TaxYearController` à *Controller Structure* (`TaxItemController` retiré,
  fusionné).
- `MODULES.md` : remplacer le champ `TaxItem` (actuellement
  `kind|label|amount|note|done` + FK Transaction/Property) par le schéma
  bucket × rôle ci-dessus, et documenter le workflow de revue.
- `CLAUDE.md` : aucune décision clé existante n'est contredite — `TaxItem`
  reste strictement une coche, la ligne *« La déclaration se lit, elle ne se
  stocke pas »* reste vraie pour les montants, seul l'état de revue est
  stocké. Pas de changement nécessaire dans ce fichier.

## Plan d'implémentation (phases)

1. **`TaxYear`** : entité, `TaxYearStatusEnum`, migration, repository,
   `TaxYearService` (`getOrCreate`, `transition`, `setRegimeChoice`,
   `setNote`).
2. **`TaxItem`** : entité, migration, repository, `TaxItemService::toggle()`.
3. **Controller & routes** : `TaxYearController` (5 actions), CSRF manuel,
   `denyAccessUnlessGranted`.
4. **Frontend** : extraction du composant Twig partagé depuis
   `tax/declaration/index.html.twig`, page `tax/years/show.html.twig`,
   `tax/years/index.html.twig`.
5. **Documentation** : `ARCHITECTURE.md`, `MODULES.md`.

Chaque phase se termine par `doctrine:schema:validate`,
`doctrine:migrations:diff` (aucune migration en attente),
`lint:twig templates/`.
