# FRONTEND — Twig & CSS

À lire avant toute modif dans `templates/` ou `assets/styles/`.

---

## Principes transverses

- **HTML sémantique** : `<nav>`, `<main>`, `<aside>`, `<ul>/<li>`, `<details>/<summary>`. `<div>` réservé au layout non-sémantique.
- **CSS avant JS** : pour hover/focus, transitions, `:has(input:checked)`, `:focus-within`, `<details>`. JS uniquement si vraiment nécessaire (localStorage, async, interactions sans équivalent CSS).
- **Jamais `style="..."` inline.** Toujours classe CSS.
- **Pas de logique métier en template.** Pas d'arithmétique/aggregation/filtrage business dans `{% set %}`. Calcul en controller, exposition via DTO/ViewModel.

---

## Twig — règle de 2

**Tout bloc HTML/CSS apparaissant ≥ 2 fois doit être factorisé.** Pas de pré-factorisation spéculative — attendre la 2e occurrence puis extraire.

### Quel outil pour quelle situation

| Situation | Outil | Emplacement |
|---|---|---|
| Fragment inline ≤ 10 lignes, pas de slots | **Macro** | `templates/macros/{topic}.html.twig` |
| Partial statique réutilisé | **`{% include %}`** | `templates/{module}/_{name}.html.twig` |
| Partial avec blocks/slots overridables | **`{% embed %}`** | `templates/{module}/_{name}.html.twig` |
| Unité réutilisable avec props, defaults, attrs pass-through | **Twig Component (anonymous)** | `templates/components/{Name}.html.twig` |
| Component avec logique PHP, computed props | **Twig Component (class-based)** | `src/Twig/Components/{Name}.php` + template |

> ⚠️ `symfony/ux-twig-component` **n'est pas installé.** Avant le premier composant : `composer require symfony/ux-twig-component` et confirmation utilisateur. En attendant : macros et `embed` uniquement.

### Conventions de nommage

- Macros groupées par sujet : `templates/macros/forms.html.twig`, `buttons.html.twig`, `money.html.twig`.
- Components en PascalCase, un par fichier : `templates/components/Button.html.twig`.
- Components namespacés par dossier : `templates/components/Form/Field.html.twig` → `<twig:Form:Field />`.
- Class-based components : `src/Twig/Components/{Name}.php` matche le template 1-pour-1.

### Catalogue de patterns à factoriser dès la 2e occurrence

| Pattern | Forme cible | Variantes |
|---|---|---|
| `<a/button class="btn btn--{variant}">` + icon Lucide | macro `buttons` → `Button` | `primary`, `secondary`, `ghost`, `danger` |
| `<div class="flash flash--{level}">` | macro `flashes` (loop sur `app.flashes`) | — |
| Form field (label + input + error) | macro `forms` → `Form:Field` | — |
| `panel-empty` (icon + texte + hint + CTA) | `EmptyState` (slots) | — |
| Page header (titre + sous-titre + actions) | `PageHeader` (slot actions) | — |
| Money `1 234,56 €` (JetBrains Mono, em-dash sur négatif) | macro `money` → `Money` | — |
| Status / type badge | `Badge` | variantes par enum value |
| Card shell (icon + body + actions) | `Card` (`embed` en attendant) | — |

### Anti-patterns Twig

- Partial copié-collé > 5 lignes — extraire avant la 3e occurrence.
- Macro qui fait 3+ choses non-liées — split.
- Component avec > ~6 props — sous-composants ou slots.
- Logique dupliquée macro ↔ component — une seule source de vérité.

---

## CSS — règle de 2 + composition > override

### Hiérarchie de réutilisation (cheap → cher)

1. **Design tokens** (`--color-*`, `--space-*`, `--radius-*`, `--font-*`) — définis dans `app.css`. Valeurs hardcodées interdites si un token existe.
2. **Modifiers BEM** (`btn btn--primary`, `space-dot space-dot--sm`) — préférés aux nouveaux composants avec overrides.
3. **Classe partagée** dans `app.css` — uniquement si pattern utilisé par **3+ modules** non-liés.
4. **Classe scopée module** dans `templates/{module}/_styles.html.twig`.

### Emplacements

```
assets/styles/app.css
├── tokens (root vars + [data-theme="dark"])
├── reset / base
├── layout shell (sidebar, topbar, main, page-header)
└── shared components (.btn, .flash, .badge, .card, .panel-empty)
   → uniquement si utilisés dans ≥ 3 modules

templates/{module}/_styles.html.twig
└── classes préfixées par module (.space-full-card, .quote-line-row)
```

### Triggers de refactor

- Même couleur/spacing/radius typé 2× → introduire/utiliser un token.
- 2 sélecteurs avec body identique → merge ou base + modifier.
- Classe d'un module utilisée par un 2e module → promouvoir vers `app.css` (sans préfixe module).
- Classe dans `app.css` utilisée par 1 seul module → demouvoir vers `_styles.html.twig`.

### Anti-patterns CSS

- Couleurs hardcodées si un token existe (sauf dans la définition des tokens).
- `!important` — quasi toujours un ordre de cascade incorrect.
- Sélecteurs profonds (> 3 niveaux) — fuite de scope, BEM.
- Classes module-specific dans `app.css`.
- Blocs dark-theme dupliqués — toggle via `[data-theme="dark"] .x { ... }`, pas de `.x-dark` parallèle.
- `<style>` inline dans templates — `{% block stylesheets %}` + `_styles.html.twig`.

---

## Documentation — context7

Pour Twig, UX Components, Stimulus, Turbo : fetch la doc à jour avant d'implémenter.

| Lib | ctx7 ID |
|---|---|
| Twig | `/twigphp/twig` |
| UX Twig Component | `/symfony/ux-twig-component` |
| UX Live Component | `/symfony/ux-live-component` |
| Stimulus | `/hotwired/stimulus` |
| Turbo | `/hotwired/turbo` |

```bash
npx ctx7@latest docs /symfony/ux-twig-component "anonymous component props"
```