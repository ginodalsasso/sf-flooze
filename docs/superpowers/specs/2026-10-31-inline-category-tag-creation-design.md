# Création inline de Category / Tag depuis n'importe quel formulaire

## Contexte et objectif

Aujourd'hui, choisir une `Category` ou un `Tag` dans un formulaire (Transaction, RecurringTransaction, confirmation de paiement de prêt/loyer, ou même le formulaire de `Category` lui-même via son champ `parent`) oblige à quitter le formulaire en cours, créer l'entité sur sa propre page, puis revenir. L'objectif est de permettre la création à la volée, sans perdre la saisie en cours.

**Périmètre** (validé avec l'utilisateur) :
- Entités concernées : **Category** et **Tag** uniquement (pas Account, Property, Tenant — hors périmètre).
- Formulaires concernés : tout formulaire de **saisie** ayant un champ `category`/`tags`/`parent` — **pas** les formulaires de **filtre** (`TransactionFilterFormType` exclu, filtrer n'implique pas de créer).
- Le champ `parent` de `CategoryFormType` est **inclus** : ouvrir la modale "créer une catégorie parente" depuis la modale "créer une catégorie" est supporté (empilement natif de `<dialog>`, aucune garde anti-récursion nécessaire).

## Mécanisme retenu

Turbo Frame chargée en lazy-loading dans une `<dialog>` HTML native, pilotée par un petit contrôleur Stimulus, avec réponse Turbo Stream côté serveur pour insérer la nouvelle option dans le `<select>` d'origine.

**Pourquoi ce choix plutôt qu'une Live Component** (plus "réactive") : `symfony/ux-live-component` n'est pas installé aujourd'hui, et l'ajouter pour ce seul besoin serait disproportionné (CLAUDE.md — *pas de sur-engineering*). Turbo Frame + Stream couvre le besoin avec les dépendances déjà présentes (`ux-twig-component`, `stimulus-bundle`, Turbo).

Vérifié via la doc à jour (context7, `/hotwired/turbo` et `/hotwired/stimulus`) :
- `turbo:submit-end` est l'événement fiable pour détecter la fin d'une soumission (succès/échec), y compris pour une soumission ciblant une frame.
- Les Turbo Streams sont le moyen standard de mettre à jour un élément **hors** de la frame (ici le `<select>` d'origine, qui est un frère de la `<dialog>`, pas un enfant de la frame).
- Stimulus supporte l'écoute d'événements hors du scope du contrôleur via le suffixe `@window`.

## Composants

### 1. `templates/components/Form/Field.html.twig` (modifié)

Ajout d'une prop optionnelle `createRoute = null`. Sans cette prop, le composant est strictement inchangé (zéro impact sur les ~20 usages existants qui ne la passent pas).

Quand `createRoute` est fourni, le composant rend en plus :
- un bouton `+ Créer` à côté du label ;
- une `<dialog>` cachée contenant une `<turbo-frame>` vide, `src` posé seulement à l'ouverture (lazy load) ;
- `data-controller="inline-create"` sur le groupe, avec l'id du `<select>` cible et l'URL de `createRoute` passés en values Stimulus.

L'id de la frame et le paramètre `target` transmis en query string à la route de création sont dérivés de `field.vars.id` (id Symfony du widget, déterministe), pour que le flux reste générique à n'importe quel champ `category`/`tags`/`parent`.

### 2. `assets/controllers/inline_create_controller.js` (nouveau)

```
targets: dialog, frame
values: createPath (String)

open()      → pose frame.src = createPath si pas déjà fait, puis dialog.showModal()
submitEnd(event) [turbo:submit-end@window] →
    si event cible une soumission dans this.frameTarget :
        si succès → dialog.close()
        sinon     → ne rien faire (la frame a déjà réaffiché les erreurs, dialog reste ouverte)
```

Fermeture par Échap / clic hors modale : comportement natif de `<dialog>`, rien à coder.

### 3. Backend — une action `newInline` par entité

`CategoryController::newInline` et `TagController::newInline` (nouvelles actions, routes `app_category_new_inline` et `app_tag_new_inline`, GET+POST).

Aucune logique métier dupliquée : même `CategoryFormType`/`TagFormType`, même `CategoryServiceInterface::create()`/`TagServiceInterface::create()`, mêmes gardes que l'action `new` existante :
- `denyAccessUnlessGranted('EDIT', $space)` ;
- entité scopée via `setSpace($space)` avant persist ;
- CSRF géré nativement par le FormType (`handleRequest`).

Comportement :
- **GET**, ou **POST invalide** → rend un template `_inline_new.html.twig` contenant `<turbo-frame id="inline-create-{target}">...</turbo-frame>` avec le formulaire (erreurs affichées automatiquement par Turbo si invalide — aucun JS requis).
- **POST valide** → répond avec `Content-Type: text/vnd.turbo-stream.html`, action `append` ciblant l'id transmis dans `target`, insérant `<option value="{id}" selected>{name}</option>` dans le `<select>` d'origine. Fonctionne pour un select simple (`category`, `parent`) comme pour un select multiple (`tags` — les sélections existantes ne sont pas affectées par un `append`).

Le paramètre `target` n'est qu'un id DOM pour l'affichage ; il n'est jamais utilisé pour une recherche ou une décision d'autorisation.

## Templates impactés

Ajout de `createRoute="app_category_new_inline"` / `createRoute="app_tag_new_inline"` sur les usages existants de `<twig:Form:Field>` :

| Template | Champ(s) |
|---|---|
| `templates/finance/transaction/forms/_asset_form.html.twig` | `category`, `tags` |
| `templates/finance/transaction/forms/_classic_form.html.twig` | `category`, `tags` |
| `templates/finance/recurring/forms/_form.html.twig` | `category`, `tags` |
| `templates/real_estate/loan/forms/_confirm_payment.html.twig` | `category` |
| `templates/real_estate/lease/forms/_confirm_rent.html.twig` | `category` |
| `templates/finance/category/forms/_form.html.twig` | `parent` |

Nouveaux templates : `templates/finance/category/_inline_new.html.twig`, `templates/finance/category/_inline_new_stream.html.twig`, `templates/finance/tag/_inline_new.html.twig`, `templates/finance/tag/_inline_new_stream.html.twig`.

## Sécurité

Aucune nouvelle surface d'attaque : les actions `newInline` répliquent exactement les gardes de `new` (EDIT sur le Space actif, entité scopée, CSRF). Le `target` en query string est une chaîne d'affichage, jamais interprétée côté serveur au-delà de l'échappement Twig standard.

## Gestion d'erreurs

- Validation métier (ex. `Tag` dupliqué dans le Space, `Category` sans `applicableTypes`) → la frame réaffiche le formulaire avec les erreurs, la `<dialog>` reste ouverte. Comportement Turbo natif, pas de code dédié.
- Erreur serveur (500) → la frame ne trouve pas de correspondance dans la réponse, Turbo journalise une erreur en console ; cas limite accepté sans traitement spécifique (pas de sur-engineering pour un cas qui signale un bug ailleurs).

## Tests (Functional, suivant TESTING.md)

Pour `CategoryController::newInline` et `TagController::newInline` :
- GET → 200, fragment contenant la turbo-frame.
- POST valide → 200, `Content-Type: text/vnd.turbo-stream.html`, entité persistée et scopée au Space actif.
- POST sans droit EDIT sur le Space → 403, rien persisté.
- POST invalide (règles métier violées) → 200, formulaire réaffiché avec erreurs, rien persisté.

## Hors périmètre (explicite)

- Account, Property, Tenant : non couverts par cette feature.
- `TransactionFilterFormType` : non couvert (formulaire de filtre, pas de saisie).
