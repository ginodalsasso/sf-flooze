# Création inline de Category / Tag — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre de créer une `Category` ou un `Tag` sans quitter le formulaire en cours (Transaction, RecurringTransaction, confirmations de paiement prêt/loyer, et le champ `parent` de `CategoryFormType` lui-même).

**Architecture:** Un composant `Form:Field` étendu d'une prop optionnelle `createRoute` affiche un bouton "+ Créer" qui ouvre une `<dialog>` native contenant une `<turbo-frame>` chargée en lazy-loading. Le sous-formulaire (même `CategoryFormType`/`TagFormType`, même service, mêmes gardes que les pages `new` existantes) répond en Turbo Stream en cas de succès pour insérer la nouvelle option dans le `<select>` d'origine et fermer la modale ; en cas d'erreur, Turbo réaffiche nativement les erreurs dans la frame.

**Tech Stack:** Symfony 8 (FormType/EntityType), `symfony/ux-twig-component`, Turbo (Frames + Streams), Stimulus (`stimulus-bundle`), AssetMapper. Aucune nouvelle dépendance.

**Spec:** [`docs/superpowers/specs/2026-10-31-inline-category-tag-creation-design.md`](../specs/2026-10-31-inline-category-tag-creation-design.md)

## Global Constraints

- Entités concernées : **Category et Tag uniquement** (Account, Property, Tenant hors périmètre).
- Formulaires de **saisie** seulement — `TransactionFilterFormType` (formulaire de filtre) **exclu**.
- Le champ `parent` de `CategoryFormType` **inclus** — empilement de `<dialog>` supporté nativement, pas de garde anti-récursion à écrire.
- Aucune nouvelle dépendance Composer/npm (pas de `symfony/ux-live-component`).
- Les actions `newInline` répliquent exactement les gardes des actions `new` existantes : `denyAccessUnlessGranted('EDIT', $space)`, `setSpace()` avant persist, CSRF géré nativement par le FormType.
- **Pas de tests PHPUnit automatiques** (convention projet — ne pas en écrire proactivement). Chaque tâche se vérifie par `lint:twig`, `debug:router`, une vérification de syntaxe JS, ou une vérification manuelle en navigateur (Task 7, finale).

## Review Focus

- **Multi-tenant** : une catégorie/tag créé inline doit être scopé au Space actif et invisible depuis un autre Space — vérifié manuellement en Task 7 (changer de Space, confirmer l'absence de fuite).
- **CSRF** : le fragment rendu dans la turbo-frame doit contenir le token CSRF du `FormType` comme la page `new` existante — garanti structurellement par la réutilisation de `CategoryFormType`/`TagFormType` (`createForm()` l'embarque et le valide automatiquement, rien à coder) ; un oubli romprait le golden path de Task 7 (POST rejeté).
- **Round-trip d'erreur dans la frame** : une violation métier (ex. `Tag` dupliqué dans le Space, `Category` sans `applicableTypes`) doit réafficher le formulaire avec erreurs *dans la frame*, dialog toujours ouverte, valeurs saisies conservées — vérifié en Task 7.
- **Empilement de modales (catégorie parente)** : créer une catégorie parente depuis la modale de création de catégorie doit insérer la bonne option dans le bon `<select>` d'origine (le `target` de chaque niveau ne doit pas se mélanger) — vérifié en Task 7, scénario dédié.
- **`target` absent ou vide** : si `target` n'est pas fourni, le stream `<turbo-stream action="append" target="">` ne doit pas lever d'exception (no-op silencieux attendu, documenté — pas une garde applicative à ajouter, cf. *pas de sur-engineering*).

---

### Task 1: Contrôleur Stimulus `inline-create`

**Files:**
- Create: `assets/controllers/inline-create_controller.js`

**Interfaces:**
- Produces: contrôleur Stimulus `inline-create` avec :
  - targets : `dialog`, `frame`
  - values : `createPath` (String)
  - actions : `open()`, `close()`, `submitEnd(event)`
- Consumé par : `templates/components/Form/Field.html.twig` (Task 3) via `data-controller="inline-create"`.

- [ ] **Step 1: Écrire `assets/controllers/inline-create_controller.js`**

```js
import { Controller } from '@hotwired/stimulus';

/**
 * Ouvre une dialog native contenant une turbo-frame de création (Category/Tag),
 * chargée en lazy-loading, et referme la dialog quand la soumission réussit.
 * Les erreurs de validation restent affichées : la frame les réaffiche nativement,
 * la dialog ne se ferme que sur turbo:submit-end { success: true }.
 */
export default class extends Controller {
    static targets = ['dialog', 'frame'];
    static values = { createPath: String };

    open() {
        if (!this.frameTarget.getAttribute('src')) {
            this.frameTarget.setAttribute('src', this.createPathValue);
        }
        this.dialogTarget.showModal();
    }

    close() {
        this.dialogTarget.close();
    }

    submitEnd(event) {
        if (!this.frameTarget.contains(event.target)) return;
        if (event.detail.success) this.dialogTarget.close();
    }
}
```

- [ ] **Step 2: Vérifier la syntaxe**

Run: `node --check assets/controllers/inline-create_controller.js`
Expected: aucune sortie (syntaxe valide).

- [ ] **Step 3: Commit**

```bash
git add assets/controllers/inline-create_controller.js
git commit -m "feat: add inline-create Stimulus controller for modal create-in-form"
```

---

### Task 2: Styles CSS partagés (trigger + dialog)

**Files:**
- Modify: `assets/styles/app.css`

**Interfaces:**
- Produces : classes `.form-label-row`, `.inline-create-trigger`, `.inline-create-dialog` (+ `::backdrop`), consommées par `templates/components/Form/Field.html.twig` (Task 3).

- [ ] **Step 1: Ajouter les règles dans la section "shared components" de `app.css`**

Reprendre les tokens déjà documentés pour les modales (`DESIGN_SYSTEM.md` → *Border Radius* `xl: 20px`, *Ombres* élément élevé) et les variables existantes `var(--bg-overlay)`, `var(--shadow-elevated)`, `var(--fg-1)`, `var(--fg-2)` :

```css
/* ── Inline create (Form:Field "+ Créer") ──────────────────────
   Partagé par tout champ Category/Tag qui peut créer son option
   sans quitter le formulaire en cours. */
.form-label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.inline-create-trigger {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: none;
  border: none;
  padding: 0;
  font-size: 11px;
  font-weight: 500;
  color: var(--fg-2);
  cursor: pointer;
}

.inline-create-trigger:hover,
.inline-create-trigger:focus-visible { color: var(--fg-1); }

.inline-create-dialog {
  border: none;
  border-radius: 20px;
  padding: 24px;
  background: var(--bg-overlay);
  box-shadow: var(--shadow-elevated);
  max-width: 480px;
  width: 90vw;
}

.inline-create-dialog::backdrop {
  background: rgba(0, 0, 0, .5);
}
```

Placer ce bloc dans `app.css` (pas dans un `_styles.html.twig` de module) puisque `Form:Field` est un composant partagé entre modules — cohérent avec la règle *Classe dans app.css si pattern utilisé par ≥ 3 modules* (ici le composant lui-même est multi-module).

- [ ] **Step 2: Vérifier qu'aucune classe ne collisionne avec une règle existante**

Run: `grep -n "\.form-label-row\|\.inline-create-trigger\|\.inline-create-dialog" assets/styles/app.css`
Expected: seules les occurrences ajoutées à l'étape 1.

- [ ] **Step 3: Commit**

```bash
git add assets/styles/app.css
git commit -m "feat: add shared styles for inline-create trigger and dialog"
```

---

### Task 3: Prop `createRoute` sur `Form:Field`

**Files:**
- Modify: `templates/components/Form/Field.html.twig`

**Interfaces:**
- Consumes: contrôleur Stimulus `inline-create` (Task 1), classes CSS (Task 2).
- Produces: prop `createRoute = null` sur `<twig:Form:Field>` — quand fournie, rend un bouton "+ Créer" + une `<dialog>`/`<turbo-frame id="inline-create-{{ field.vars.id }}">`. Absente (valeur par défaut), le rendu est strictement identique à aujourd'hui.

- [ ] **Step 1: Modifier `templates/components/Form/Field.html.twig`**

```twig
{# Symfony form field: label + widget + errors + optional hint. field = FormView object.
   createRoute: route name for inline Category/Tag creation (adds a "+ Créer" trigger + lazy turbo-frame dialog). #}
{% props field, label, hint = null, attr = {}, createRoute = null %}

{% set groupClass = 'form-group' ~ (field.vars.disabled ? ' form-group--disabled' : '') %}
<div {{ attributes.defaults({class: groupClass}) }}
     {% if createRoute %}data-controller="inline-create" data-inline-create-create-path-value="{{ path(createRoute, {target: field.vars.id}) }}"{% endif %}>
    {% if createRoute %}
        <div class="form-label-row">
            <label for="{{ field.vars.id }}" class="form-label-app">{{ label }}</label>
            <button type="button" class="inline-create-trigger" data-action="inline-create#open">
                <i data-lucide="plus" aria-hidden="true"></i> Créer
            </button>
        </div>
    {% else %}
        <label for="{{ field.vars.id }}" class="form-label-app">{{ label }}</label>
    {% endif %}
    {{ form_widget(field, {attr: attr}) }}
    {% for error in field.vars.errors %}
        <span class="form-error">{{ error.message }}</span>
    {% endfor %}
    {% set displayHint = hint ?? field.vars.help ?? null %}
    {% if displayHint %}
        <span class="form-hint">{{ displayHint }}</span>
    {% endif %}
    {% if createRoute %}
        <dialog class="inline-create-dialog" data-inline-create-target="dialog"
                data-action="turbo:submit-end@window->inline-create#submitEnd">
            <turbo-frame id="inline-create-{{ field.vars.id }}" data-inline-create-target="frame"></turbo-frame>
        </dialog>
    {% endif %}
</div>
```

- [ ] **Step 2: Lint du template**

Run: `php bin/console lint:twig templates/components/Form/Field.html.twig`
Expected: `[OK] All 1 Twig files contain valid syntax.`

- [ ] **Step 3: Commit**

```bash
git add templates/components/Form/Field.html.twig
git commit -m "feat: add optional inline-create trigger to Form:Field component"
```

---

### Task 4: Création inline de Category

**Files:**
- Modify: `src/Controller/Finance/CategoryController.php`
- Modify: `templates/finance/category/forms/_form.html.twig`
- Create: `templates/finance/category/_inline_new.html.twig`
- Create: `templates/finance/category/_inline_new_stream.html.twig`

**Interfaces:**
- Consumes: `Form:Field` prop `createRoute` (Task 3) ; `CategoryServiceInterface::save()`, `CategoryFormType` (existants, inchangés) ; `ActiveSpaceControllerTrait::resolveActiveSpace()` (existant).
- Produces: route `app_category_new_inline` (`GET|POST /finance/categories/new/inline`).

- [ ] **Step 1: Ajouter l'action `newInline` dans `src/Controller/Finance/CategoryController.php`**

Même logique que `new()` (`src/Controller/Finance/CategoryController.php:44-64`), mais :
- lit `target` depuis la query string (id DOM du `<select>` d'origine, affichage uniquement) ;
- sur succès, répond en Turbo Stream au lieu de rediriger ;
- sur GET ou échec de validation, rend `_inline_new.html.twig` au lieu de `new.html.twig`.

```php
#[Route('/new/inline', name: 'new_inline')]
public function newInline(Request $request): Response
{
    $space = $this->resolveActiveSpace('EDIT');
    if ($space instanceof RedirectResponse) {
        return $space;
    }

    $target = (string) $request->query->get('target', '');

    $category = new Category();
    $form = $this->createForm(CategoryFormType::class, $category, ['space' => $space]);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $category->setSpace($space);
        $this->categoryService->save($category);

        return $this->render('finance/category/_inline_new_stream.html.twig', [
            'category' => $category,
            'target' => $target,
        ], new Response(null, 200, ['Content-Type' => 'text/vnd.turbo-stream.html']));
    }

    return $this->render('finance/category/_inline_new.html.twig', [
        'form' => $form,
        'target' => $target,
    ]);
}
```

- [ ] **Step 2: Rendre `_form.html.twig` réutilisable en mode inline**

Dans `templates/finance/category/forms/_form.html.twig` :
- ajouter `createRoute="app_category_new_inline"` sur le `<twig:Form:Field field="{{ form.parent }}" .../>` (ligne 8) ;
- remplacer le bouton "Annuler" (ligne 33) par une variante conditionnelle : si la variable `inline` (passée par l'appelant, défaut `false`) est vraie, un bouton qui ferme la dialog au lieu de naviguer :

```twig
{% if inline|default(false) %}
    <twig:Button type="button" label="Annuler" variant="secondary" attr="{{ {'data-action': 'inline-create#close'} }}" />
{% else %}
    <twig:Button as="a" href="{{ path('app_category_index') }}" label="Annuler" variant="secondary" />
{% endif %}
```

- [ ] **Step 3: Créer `templates/finance/category/_inline_new.html.twig`**

```twig
<turbo-frame id="inline-create-{{ target }}">
    {% include 'finance/category/forms/_form.html.twig' with {submit_label: 'Créer la catégorie', inline: true} %}
</turbo-frame>
```

- [ ] **Step 4: Créer `templates/finance/category/_inline_new_stream.html.twig`**

```twig
<turbo-stream action="append" target="{{ target }}">
    <template>
        <option value="{{ category.id }}" selected>{{ category.name }}</option>
    </template>
</turbo-stream>
```

- [ ] **Step 5: Lint des templates**

Run: `php bin/console lint:twig templates/finance/category/`
Expected: `[OK]` pour tous les fichiers.

- [ ] **Step 6: Vérifier la route**

Run: `php bin/console debug:router app_category_new_inline`
Expected: affiche `GET|POST /finance/categories/new/inline`.

- [ ] **Step 7: Commit**

```bash
git add src/Controller/Finance/CategoryController.php templates/finance/category/forms/_form.html.twig templates/finance/category/_inline_new.html.twig templates/finance/category/_inline_new_stream.html.twig
git commit -m "feat: add inline Category creation (turbo-frame + stream)"
```

---

### Task 5: Création inline de Tag

**Files:**
- Modify: `src/Controller/Finance/TagController.php`
- Modify: `templates/finance/tag/forms/_form.html.twig`
- Create: `templates/finance/tag/_inline_new.html.twig`
- Create: `templates/finance/tag/_inline_new_stream.html.twig`

**Interfaces:**
- Consumes: `Form:Field` prop `createRoute` (Task 3) ; `TagServiceInterface::save()`, `TagFormType` (existants, inchangés).
- Produces: route `app_tag_new_inline` (`GET|POST /finance/tags/new/inline`).

- [ ] **Step 1: Ajouter l'action `newInline` dans `src/Controller/Finance/TagController.php`**

Même logique que `new()` (`src/Controller/Finance/TagController.php:46-67`) — y compris `(new Tag())->setSpace($space)` **avant** `createForm` (la validation d'unicité du nom dépend du Space déjà posé) — adaptée comme Task 4 Step 1 (lecture de `target`, réponse Turbo Stream sur succès, `_inline_new.html.twig` sinon) :

```php
#[Route('/new/inline', name: 'new_inline')]
public function newInline(Request $request): Response
{
    $space = $this->resolveActiveSpace('EDIT');
    if ($space instanceof RedirectResponse) {
        return $space;
    }

    $target = (string) $request->query->get('target', '');

    $tag = (new Tag())->setSpace($space);
    $form = $this->createForm(TagFormType::class, $tag);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $this->tagService->save($tag);

        return $this->render('finance/tag/_inline_new_stream.html.twig', [
            'tag' => $tag,
            'target' => $target,
        ], new Response(null, 200, ['Content-Type' => 'text/vnd.turbo-stream.html']));
    }

    return $this->render('finance/tag/_inline_new.html.twig', [
        'form' => $form,
        'target' => $target,
    ]);
}
```

- [ ] **Step 2: Rendre `_form.html.twig` réutilisable en mode inline**

Même changement que Task 4 Step 2, appliqué à `templates/finance/tag/forms/_form.html.twig` (bouton "Annuler" ligne 9). Pas de champ `parent` pour `Tag` — rien d'autre à changer.

- [ ] **Step 3: Créer `templates/finance/tag/_inline_new.html.twig`**

```twig
<turbo-frame id="inline-create-{{ target }}">
    {% include 'finance/tag/forms/_form.html.twig' with {submit_label: 'Créer le tag', inline: true} %}
</turbo-frame>
```

- [ ] **Step 4: Créer `templates/finance/tag/_inline_new_stream.html.twig`**

```twig
<turbo-stream action="append" target="{{ target }}">
    <template>
        <option value="{{ tag.id }}" selected>{{ tag.name }}</option>
    </template>
</turbo-stream>
```

- [ ] **Step 5: Lint des templates**

Run: `php bin/console lint:twig templates/finance/tag/`
Expected: `[OK]` pour tous les fichiers.

- [ ] **Step 6: Vérifier la route**

Run: `php bin/console debug:router app_tag_new_inline`
Expected: affiche `GET|POST /finance/tags/new/inline`.

- [ ] **Step 7: Commit**

```bash
git add src/Controller/Finance/TagController.php templates/finance/tag/forms/_form.html.twig templates/finance/tag/_inline_new.html.twig templates/finance/tag/_inline_new_stream.html.twig
git commit -m "feat: add inline Tag creation (turbo-frame + stream)"
```

---

### Task 6: Activer le bouton "+ Créer" sur les formulaires existants

**Files:**
- Modify: `templates/finance/transaction/forms/_asset_form.html.twig:27-29`
- Modify: `templates/finance/transaction/forms/_classic_form.html.twig:24-26`
- Modify: `templates/finance/recurring/forms/_form.html.twig:21-22`
- Modify: `templates/real_estate/loan/forms/_confirm_payment.html.twig:5`
- Modify: `templates/real_estate/lease/forms/_confirm_rent.html.twig:7`

**Interfaces:**
- Consumes: routes `app_category_new_inline` (Task 4), `app_tag_new_inline` (Task 5).

- [ ] **Step 1: `_asset_form.html.twig`**

```twig
<twig:Form:Field field="{{ form.category }}" label="Catégorie" createRoute="app_category_new_inline" />
<twig:Form:Field field="{{ form.tags }}" label="Tags" createRoute="app_tag_new_inline"
                 hint="Projet ou événement — Ctrl/⌘ pour en choisir plusieurs" />
```

- [ ] **Step 2: `_classic_form.html.twig`**

Même changement que Step 1 (mêmes lignes `category`/`tags`).

- [ ] **Step 3: `recurring/forms/_form.html.twig`**

```twig
<twig:Form:Field field="{{ form.category }}" label="Catégorie" createRoute="app_category_new_inline" />
<twig:Form:Field field="{{ form.tags }}" label="Tags" createRoute="app_tag_new_inline" />
```

- [ ] **Step 4: `real_estate/loan/forms/_confirm_payment.html.twig`**

```twig
<twig:Form:Field field="{{ confirmForm.category }}" label="Catégorie" createRoute="app_category_new_inline" />
```

- [ ] **Step 5: `real_estate/lease/forms/_confirm_rent.html.twig`**

Même changement que Step 4 sur `confirmForm.category`.

- [ ] **Step 6: Lint de tous les templates modifiés**

Run: `php bin/console lint:twig templates/`
Expected: `[OK] All X Twig files contain valid syntax.`

- [ ] **Step 7: Commit**

```bash
git add templates/finance/transaction/forms/_asset_form.html.twig templates/finance/transaction/forms/_classic_form.html.twig templates/finance/recurring/forms/_form.html.twig templates/real_estate/loan/forms/_confirm_payment.html.twig templates/real_estate/lease/forms/_confirm_rent.html.twig
git commit -m "feat: wire inline-create trigger onto category/tag fields project-wide"
```

---

### Task 7: Vérification manuelle en navigateur (gate final)

Pas de fichiers modifiés — vérification du comportement bout-en-bout avant de considérer la feature terminée (CLAUDE.md : *tester le golden path et les cas limites dans un navigateur réel*).

- [ ] **Step 1: Lancer l'environnement de dev** (`symfony serve`, MySQL, `php bin/console cache:clear` si besoin).
- [ ] **Step 2: Golden path Category** — ouvrir un formulaire de transaction, cliquer "+ Créer" sur Catégorie, créer une catégorie valide (nom + au moins un type applicable), vérifier : la dialog se ferme, la nouvelle catégorie est sélectionnée dans le select d'origine.
- [ ] **Step 3: Golden path Tag** — même scénario sur le champ Tags (multi-select) : vérifier que les tags déjà sélectionnés restent sélectionnés après ajout du nouveau.
- [ ] **Step 4: Erreur de validation** — soumettre le sous-formulaire Category avec un nom vide : vérifier que les erreurs s'affichent dans la frame, que la dialog reste ouverte, qu'aucune catégorie n'est créée.
- [ ] **Step 5: Empilement de modales** — depuis la modale de création de catégorie (ouverte depuis un formulaire de transaction), cliquer "+ Créer" sur le champ "Catégorie parente" ; créer la catégorie parente ; vérifier qu'elle apparaît sélectionnée dans le select "parent" de la modale catégorie (pas dans le select "Catégorie" de la transaction).
- [ ] **Step 6: Formulaires de confirmation** — reproduire le golden path Category sur une confirmation de paiement de prêt et une confirmation de loyer.
- [ ] **Step 7: Multi-tenant** — changer de Space actif, vérifier que la catégorie/tag créée à l'étape 2/3 n'apparaît pas dans la liste de l'autre Space.
- [ ] **Step 8: Formulaire de filtre inchangé** — vérifier que `TransactionFilterFormType` (page de liste des transactions) n'affiche **pas** de bouton "+ Créer" sur ses champs category/tag.

Si un écart est constaté, corriger dans la tâche concernée (1 à 6) avant de clore.
