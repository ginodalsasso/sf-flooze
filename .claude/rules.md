# rules.md — sf-flooze

Conventions de code PHP/Symfony. Les règles UI/CSS sont dans [`FRONTEND.md`](../FRONTEND.md), les règles de test dans [`TESTING.md`](../TESTING.md).

Les **garde-fous critiques** (ERD, multi-tenant, soft-delete, DI, security) sont listés dans [`CLAUDE.md`](../CLAUDE.md). Ils sont supposés acquis ici.

---

## Principes de base

Ces quatre principes priment sur tout le reste. En cas de conflit entre une règle ci-dessous et un principe ici, le principe gagne.

### 1. Simplicité — pas de sur-engineering

- **La solution la plus simple qui marche est la bonne.** Toujours la préférer.
- Écrire pour être **lu**, pas pour impressionner : code plat, noms explicites, early return, pas de one-liner cryptique.
- Une abstraction ne se crée que sur un besoin **réel et présent**, jamais sur un besoin anticipé.
- Classe abstraite, factory, event, couche de mapping : uniquement si ≥ 2 implémentations existent **aujourd'hui**.
- **Exception : les interfaces de composants injectables** (voir [Interfaces](#interfaces)). Elles sont un contrat de lecture et un point d'injection, pas une abstraction spéculative — elles sont donc systématiques, même à une seule implémentation.
- Pas de couche de config/paramétrage pour un cas d'usage unique.
- Si une explication de plus de 3 phrases est nécessaire pour justifier un design → c'est trop complexe, simplifier.

### 2. SOLID — application stricte

- **SRP** : Controller = HTTP (valider, déléguer, répondre). Service = business logic. Repository = queries. Entity = état + getters/setters.
- **OCP** : étendre via un nouveau service, plutôt que modifier un service existant utilisé ailleurs.
- **LSP** : l'implémentation respecte le contrat de son interface — mêmes types de retour, mêmes exceptions, pas de pré-condition ajoutée.
- **ISP** : traits et interfaces petits et focalisés — une interface expose l'API réellement appelée, pas l'intégralité des méthodes publiques « au cas où ».
- **DIP** : dépendre d'abstractions, injection par le container uniquement. Le type-hint est **l'interface**, jamais l'implémentation. **Jamais `new Service()` dans un autre service.**

```php
// GOOD
public function __construct(private readonly OllamaClientInterface $ollama) {}

// BAD — couplé à l'implémentation
public function __construct(private readonly OllamaClient $ollama) {}
```

### 3. Isolation des changements — ne pas casser l'existant

Un changement doit être **contenu**. Modifier un module ne doit pas exiger de modifier les autres.

- **Ne pas modifier une signature publique** (méthode de service, constructeur, route, nom de champ) sans recenser tous les appelants au préalable. Sinon : ajouter une nouvelle méthode et laisser l'ancienne.
- **Ne pas renommer** une classe, méthode, route, colonne ou classe CSS existante « pour faire plus propre ». Le nommage existant fait autorité (voir [Naming](#naming)).
- **Nouveau comportement = nouveau code**, pas de branche `if` ajoutée dans un service partagé pour un cas particulier.
- **Un service par domaine métier.** Le code Finance ne connaît pas Tax, RealEstate ne connaît pas Invoicing. Le point de contact autorisé est `Transaction`.
- Un changement en `app.css`, dans un trait, un listener Doctrine ou une macro partagée impacte **tous** les modules → vérifier les usages avant, préférer un modifier/une classe scopée.
- Après modification d'une entité ou d'un listener : `doctrine:schema:validate` + tests du module touché **et** des modules voisins.

### 4. Documentation — courte et précise

- Un commentaire explique **pourquoi**, jamais **quoi** (le code dit déjà quoi).
- **1 ligne maximum** dans le corps d'une méthode, et seulement si la raison n'est pas évidente.
- PHPDoc **uniquement** quand le type-hint PHP ne suffit pas : `@return list<Transaction>`, `@throws`, tableau structuré.
- Pas de docblock qui paraphrase la signature, pas de `@param` redondant, pas de bannière ASCII, pas d'exemple d'usage dans le code.
- Un service ou un listener non trivial : 1 ligne en tête de classe décrivant son rôle. C'est tout.
- Commentaire obsolète = bug. Si le code change, le commentaire change ou disparaît.

```php
// GOOD — explique une contrainte non devinable
// dompdf ne supporte pas flexbox : layout en table dans le template.

// BAD — paraphrase
// Récupère les transactions de l'espace
public function findBySpace(Space $space): array
```

---

## Sécurité — la vérification est backend, toujours

> **L'UI n'est pas une frontière de sécurité.** Un bouton caché, un champ `disabled`, un `readonly`, un `{% if %}` dans un template ou une validation Stimulus ne protègent rien : la requête HTTP est forgeable directement. Tout contrôle affiché côté UI **doit** exister côté serveur.

À chaque ajout ou modification d'un controller, d'un formulaire ou d'une route, double-checker :

1. **Authorization** — `denyAccessUnlessGranted('VIEW'|'EDIT', $entity)` sur **chaque** action, y compris `new`, `delete`, les exports et les endpoints AJAX/JSON.
   ```php
   $this->denyAccessUnlessGranted('EDIT', $transaction->getSpace());
   ```
2. **Scope multi-tenant** — la query filtre par `space` **et** `deletedAt IS NULL`. Un ID en URL ne prouve rien sur son propriétaire.
3. **Ownership des données liées** — les entités choisies dans un formulaire (catégorie, compte destination, client) appartiennent au même `space`. Contraindre via `query_builder` dans le FormType **et** revalider avant persist.
4. **Champs non modifiables** — un champ masqué ou `disabled` dans le template reste soumettable. Ne pas le mapper au formulaire, ou l'ignorer explicitement côté serveur.
5. **CSRF** — actif (auto via `AbstractType`). Toute action destructive passe par `POST`/`DELETE`, jamais par un `GET`.
6. **Redirections** — jamais une URL issue de la requête (`redirect_to`, `Referer`) sans validation locale.
7. **Entrées** — validation par contraintes Symfony sur le DTO/l'entité, pas seulement par les attributs HTML (`required`, `min`, `pattern`).

Tests attendus : pour chaque endpoint sensible, un test fonctionnel « user B accède à une entité du space de user A → 403 » (voir [`TESTING.md`](../TESTING.md)).

Le plan de durcissement en cours est dans [`SECURITY_PLAN.md`](../SECURITY_PLAN.md).

---

## Naming

Le nommage existant fait autorité : suivre les patterns ci-dessous pour le nouveau code, **ne pas renommer** l'existant.

### Classes

| Type | Pattern | Exemple |
|---|---|---|
| Entity | `CamelCase` singulier | `Property`, `Transaction` |
| Repository | `{Entity}Repository` | `TransactionRepository` |
| Service | `{Verb}{Noun}Service` | `ReceiptOcrService` |
| Controller | `{Noun}Controller` | `QuoteController` |
| Form | `{Noun}FormType` | `TransactionFormType` |
| Enum | `{Noun}{Adj}Enum` | `InvoiceStatusEnum` |
| Trait | `{Noun}Trait` | `TimestampTrait` |
| Listener | `{Trigger}Listener` | `AutoCategoryListener` |
| Command | `{Verb}{Noun}Command` | `GenerateRentPaymentsCommand` |
| DTO | `{Action}{Noun}Dto` | `CreateTransactionDto` |
| PDF Generator | `{Noun}PdfGenerator` | `QuotePdfGenerator` |
| Voter | `{Noun}Voter` | `SpaceScopeVoter` |
| Interface | `{Classe}Interface` dans `Contract/` | `App\Service\Finance\Contract\TransactionServiceInterface` |

### Méthodes

- **Repositories** : descriptifs — `findBySpaceAndDateRange()`, `findOverdueInvoices()`, `sumExpensesByCategory()`.
- **Services** : verbe d'abord — `createTransaction()`, `reconcileWithBankStatement()`.
- **Routes** : `{noun}_{action}` — `transaction_index`, `transaction_new`, `transaction_edit`, `transaction_delete`.

### Base de données

- Tables : **singulier, snake_case** — `user`, `tax_year`, `rent_payment`.
- Pivots : `parent_child` — `lease_tenant`, `document_link`.
- PK : `id` (int auto-increment). FK : `{entity}_id`.
- Booléens : `is_{adj}` — `is_deductible`, `is_active`.
- Soft delete : `deleted_at` (TIMESTAMP nullable) — **jamais** `is_deleted`.
- Audit : `created_at`, `updated_at` (auto via `TimestampListener`).

---

## Interfaces

**Tout composant injectable est déclaré par une interface et injecté par cette interface.** L'interface est le contrat lisible du composant ; la classe n'est qu'une implémentation.

### Périmètre

| Concerné — interface obligatoire | Hors périmètre — pas d'interface |
|---|---|
| Services métier (`src/Service/**`) | Entités, DTO, enums, traits |
| Repositories (`src/Repository/**`) | FormTypes, controllers |
| Clients externes (Ollama, taux de change, mailer applicatif) | Listeners Doctrine, voters, commandes, Twig extensions |
| Générateurs PDF, exports | Value objects |
| Resolvers / providers (`SpaceResolver`, `FeatureFlags`) | |

Le hors-périmètre n'est pas injecté dans du code métier : soit c'est de l'état (entité, DTO), soit c'est un point d'entrée dont le framework définit déjà le contrat (`VoterInterface`, `Command`, `AbstractType`). Une interface y serait du bruit.

### Convention

- Nom : `{Classe}Interface` (suffixe, comme Symfony — pas de préfixe `I` façon C#).
- Emplacement : sous-dossier **`Contract/`** du module qui porte l'implémentation, d'où un namespace en `\Contract`. `Interface/` est impossible : `interface` est un mot réservé PHP.

```
src/Service/Finance/
├── Contract/
│   ├── TransactionServiceInterface.php     → App\Service\Finance\Contract
│   └── ExchangeRateServiceInterface.php
├── TransactionService.php                  → App\Service\Finance
└── ExchangeRateService.php
```

- Implémentation : `final class X implements XInterface`, avec l'import du contrat.
- L'interface ne déclare **que** les méthodes appelées de l'extérieur (ISP). Une méthode publique utilisée uniquement en interne n'y figure pas.
- Types de retour explicites, DTO ou entités typées — jamais `array` nu (voir [Services](#services)).
- PHPDoc génériques (`@return list<Transaction>`) portés par l'**interface**, pas dupliqués dans l'implémentation.

```php
// src/Service/Finance/Contract/TransactionServiceInterface.php
namespace App\Service\Finance\Contract;

interface TransactionServiceInterface
{
    public function save(TransactionInputDto $input): Transaction;
}

// src/Service/Finance/TransactionService.php
namespace App\Service\Finance;

use App\Service\Finance\Contract\ExchangeRateServiceInterface;
use App\Service\Finance\Contract\TransactionServiceInterface;

final class TransactionService implements TransactionServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExchangeRateServiceInterface $exchangeRates,
    ) {}
}
```

### Cas des repositories

Le contrat vit dans `src/Repository/Contract/` et **étend `ObjectRepository`** : `find()`, `findBy()` et consorts restent disponibles sans les redéclarer. L'implémentation Doctrine reste un `ServiceEntityRepository`.

```php
namespace App\Repository\Contract;

/** @extends ObjectRepository<Transaction> */
interface TransactionRepositoryInterface extends ObjectRepository
{
    /** @return Transaction[] */
    public function findBySpace(Space $space): array;
}

final class TransactionRepository extends ServiceEntityRepository implements TransactionRepositoryInterface
```

Deux points d'accès échappent au contrat, et c'est normal :

- `$em->getRepository(Transaction::class)` retourne la classe concrète — injecter `TransactionRepositoryInterface` dans le constructeur à la place.
- Les closures `query_builder` d'un `EntityType` reçoivent le repository de Doctrine et appellent `createQueryBuilder()`, absent du contrat : y garder le **type concret**. Ce n'est pas de l'injection.

### Container

- Une seule implémentation → l'autowiring résout le type-hint tout seul (alias automatique des interfaces à implémentation unique via le chargement PSR-4 de `services.yaml`). **Rien à configurer.**
- Plusieurs implémentations → `#[AsAlias(SomethingInterface::class)]` sur celle par défaut, alias explicite dans `config/services.yaml` pour les autres.

### Ce que la règle n'autorise pas

Une interface ne justifie pas une deuxième implémentation « pour l'exemple », ni un `AbstractXxxService`, ni une factory. Le reste des règles de simplicité s'applique intégralement.

---

## Architecture

### Controllers

- Max ~50 lignes par action.
- HTTP only : valider la requête, déléguer au service, retourner la réponse.
- Type-hinting d'entité pour les paramètres de route (ParamConverter implicite).
- Routes via PHP attributes, pas YAML (sauf API dans `config/routes/api.yaml`).

### Services

- Créer un service quand : domaine métier distinct, logique partagée par plusieurs controllers, complexité > ~30 lignes en controller, ou interaction API externe.
- > 300 lignes → split par use case.
- Retourner DTOs ou entités typées, jamais d'arrays anonymes.
- Toujours livré avec son interface, et injecté par elle (voir [Interfaces](#interfaces)).

### Repositories

- Queries uniquement, pas de business logic.
- QueryBuilder pour filtres complexes, DQL pour joins complexes. **Jamais de SQL inline.**
- Toujours filtrer `space` + `deletedAt IS NULL`.
- **Calculs mathématiques côté DB :** si une opération (somme, moyenne, agrégation) peut s'exprimer en requête, la faire côté base. Ordre de priorité : **DQL d'abord**, **SQL natif si DQL insuffisant**, **PHP en dernier recours**.
- **Entités sans calcul :** une entité n'agrège pas sa propre collection. Les calculs vont dans un service ou le repository ; l'entité expose getters/setters.

### Forms

- FormType classes uniquement, jamais de form construit en controller.
- CSRF auto via `AbstractType`.
- `query_builder` scopé par `space` sur tout `EntityType`.

### Events

- `EventListener` (pas `EventSubscriber`) pour les hooks Doctrine.
- `Symfony\Component\EventDispatcher` pour les events domaine.

### Traits

- Propriétés + getters/setters uniquement. **Aucune business logic.**
- `SpaceScopeTrait` n'ajoute que `space`. `TimestampTrait` n'ajoute que `created_at`/`updated_at`.

---

## Doctrine

- Mapping via **attributes uniquement**.
- Décimal monétaire : `precision: 15, scale: 2`.
- Enums : PHP 8.1+ backed enums avec `#[ORM\Column(type: Types::STRING, enumType: XxxEnum::class)]`.
- Lifecycle callbacks via `#[ORM\HasLifecycleCallbacks]` ou listeners dédiés (préférés).

---

## Anti-patterns

**Conception**
- Abstraction prématurée — 3 lignes similaires ne justifient pas une classe abstraite.
- Classe abstraite, factory ou point d'extension pour une seule implémentation (les interfaces de composants injectables font exception, voir [Interfaces](#interfaces)).
- Interface sur une entité, un DTO, un FormType, un controller ou un voter.
- Type-hint sur l'implémentation alors qu'une interface existe.
- Interface qui recopie toutes les méthodes publiques, y compris celles appelées seulement en interne.
- Réflexe microservices — monolithe jusqu'à preuve d'un bottleneck.
- Renommer ou déplacer de l'existant sans nécessité fonctionnelle.
- `if` de cas particulier ajouté dans un service partagé.

**Sécurité**
- Se fier à un contrôle UI (bouton masqué, `disabled`, `{% if %}`) sans équivalent serveur.
- Oubli de `space_id` ou de `denyAccessUnlessGranted`.
- Redirection vers une URL issue de la requête sans validation.

**Symfony / Doctrine**
- Inventer une entité/relation hors ERD.
- Pivot quand une FK simple suffit.
- Business logic en controller.
- `new` au lieu d'injection.
- `is_deleted` au lieu de `deleted_at`.
- SQL inline.
- Array non typé en retour de service.
- Service > 300 lignes sans découpage.

**Documentation**
- Docblock qui paraphrase la signature.
- Commentaire décrivant *quoi* au lieu de *pourquoi*.
- Commentaire non mis à jour avec le code.
