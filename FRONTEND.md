# FRONTEND — Twig & CSS

À lire avant toute modif dans `templates/` ou `assets/styles/`.

---

## Principes transverses

- **HTML sémantique** : `<nav>`, `<main>`, `<aside>`, `<ul>/<li>`, `<details>/<summary>`. `<div>` réservé au layout non-sémantique.
- **CSS avant JS** : pour hover/focus, transitions, `:has(input:checked)`, `:focus-within`, `<details>`. JS uniquement si vraiment nécessaire (localStorage, async, interactions sans équivalent CSS).
- **Aucun style inline dans Twig.** `style="..."` est interdit dans les templates ; le CSS va dans des fichiers dédiés (`assets/styles/app.css` ou `templates/{module}/_styles.html.twig`) avec des noms de classes propres, cohérents et réutilisables. Toujours privilégier une classe existante avant d'en créer une nouvelle.
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

`symfony/ux-twig-component` est installé et c'est l'outil par défaut pour toute unité réutilisable avec props (`templates/components/` : `Button`, `Badge`, `EmptyState`, `Flash`, `Notice`, `PageHeader`, `SpaceTypeIcon`, `Form/Field`, `Form/CheckboxField`). Les macros ne sont utilisées nulle part dans le projet — ne pas en introduire pour un cas que le Twig Component couvre déjà plus proprement (props typées, namespace, pas de `{% import %}` à gérer).

### Conventions de nommage

- Components en PascalCase, un par fichier : `templates/components/Button.html.twig`.
- Components namespacés par dossier : `templates/components/Form/Field.html.twig` → `<twig:Form:Field />`.
- Class-based components : `src/Twig/Components/{Name}.php` matche le template 1-pour-1.

### Catalogue de patterns à factoriser dès la 2e occurrence

| Pattern | Forme cible | Variantes |
|---|---|---|
| `<a/button class="btn btn--{variant}">` + icon Lucide | `Button` | `primary`, `secondary`, `ghost`, `danger` |
| `<div class="flash flash--{level}">` | `Flash` | — |
| Form field (label + input + error) | `Form:Field` (checkbox : `Form:CheckboxField`) | — |
| `panel-empty` (icon + texte + hint + CTA) | `EmptyState` (slots) | — |
| Page header (titre + sous-titre + actions) | `PageHeader` (slot actions) | — |
| Montant `1 234,56 €` (JetBrains Mono, tiret cadratin sur négatif) | filtre `\|money` (`MoneyExtension`) — **pas** un component, c'est un formatage, pas une unité de layout | devise non-EUR : voir [`DESIGN_SYSTEM.md`](DESIGN_SYSTEM.md) → *Montants* |
| Status / type badge | `Badge` | variantes par enum value |
| Card shell (icon + body + actions) | `Card` (`embed` en attendant) | — |
| Skeleton/spinner de chargement (fetch Turbo, formulaire en cours) | pas encore factorisé — factoriser à la 2e occurrence | voir *États de page* ci-dessous |

### Anti-patterns Twig

- Partial copié-collé > 5 lignes — extraire avant la 3e occurrence.
- Component avec > ~6 props — sous-composants ou slots.
- Logique de formatage/affichage dupliquée entre un component et un filtre Twig — une seule source de vérité (ex. le formatage monétaire vit dans `MoneyExtension`, pas recopié dans un template).

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

## Accessibilité

Non négociable — pas une passe de polish en fin de feature, à tenir dès l'écriture du template.

- **Contraste** : AA minimum (4,5:1 texte normal, 3:1 grand texte/icônes). `--fg-2`/`--fg-3` sur `--bg-elevated`/`--bg-overlay` sont proches de la limite — vérifier au cas par cas plutôt que de les empiler sans contrôle (voir [`DESIGN_SYSTEM.md`](DESIGN_SYSTEM.md) → *Accessibilité des couleurs*).
- **La couleur n'est jamais le seul vecteur d'information.** Un montant positif/négatif, un statut de facture : la couleur accompagne une icône ou un texte, elle ne les remplace pas (daltonisme).
- **Focus visible** sur tout élément interactif (lien, bouton, input, `<summary>`) — le glow sauge déjà utilisé pour les inputs (`DESIGN_SYSTEM.md` → *Ombres*) est la base à réutiliser, jamais de `outline: none` sans remplacement.
- **Label/input toujours liés** (`<label for>` ou wrapping), jamais un placeholder en guise de label.
- **Icônes Lucide décoratives** : `aria-hidden="true"`. Icône seule porteuse de sens (bouton icon-only) : `aria-label` explicite sur l'élément interactif.
- **`app.flashes`** rendu dans une région `aria-live="polite"` — un flash qui apparaît après une action doit être annoncé sans que l'utilisateur ait à le chercher.
- **Navigation clavier** : rien qui dépende exclusivement du survol (`:hover`) sans équivalent `:focus`/`:focus-within`. Les menus/dropdowns en `<details>` sont déjà clavier-friendly nativement — préférer ce pattern à un dropdown JS avant d'en écrire un.

---

## États de page

Toute vue qui liste ou soumet quelque chose a trois états à prévoir explicitement, pas seulement le happy path :

- **Vide** : `EmptyState` (icône + texte + hint + CTA), jamais un tableau avec un header vide ou un message brut non stylé.
- **Chargement** : les navigations Turbo ont leur barre de progression native (à styler via les tokens, pas un spinner JS additionnel) ; un formulaire en soumission désactive son bouton — voir *Double-soumission* ci-dessous.
- **Erreur** : pages 403/404/500 dédiées (`templates/bundles/TwigBundle/Exception/`), même habillage que le reste de l'app — jamais la page d'erreur par défaut de Symfony en prod.

**Listes longues** : au-delà d'une vingtaine de lignes (transactions, factures), paginer plutôt que tout charger — même convention de pagination partout (taille de page, contrôle en bas de liste), pas une solution par module.

**Double-soumission** : toute action qui écrit (confirmer une échéance, créer une transaction, soumettre un formulaire financier) doit désactiver son bouton pendant la requête pour qu'un double-clic n'envoie pas deux fois le `POST`. Turbo le fait nativement sur un `<form>` standard — ne pas le contourner avec une soumission AJAX maison qui perdrait ce comportement. Le serveur reste la dernière ligne de défense (voir [`.claude/rules.md`](.claude/rules.md) → *Sécurité* #9, idempotence) : le frontend réduit la fréquence du problème, il ne le résout pas seul.

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