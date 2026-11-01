# Flooze — Design System Guidelines
> Ce fichier est destiné à Claude Code et aux développeurs. Il définit les règles visuelles et de code à respecter lors de tout développement sur le projet Flooze.

---

## Palette de couleurs

### Mode sombre (défaut)
```css
--bg-base:      #151515;   /* fond racine */
--bg-surface:   #1E1E1E;   /* cartes, panneaux */
--bg-elevated:  #272727;   /* hover, inputs */
--bg-overlay:   #303030;   /* modales, dropdowns */
--border:       #2E2E2E;   /* séparateurs subtils */
--border-strong:#3D3D3D;   /* bordures cartes, inputs */

--fg-1:         #EDEEED;   /* texte principal */
--fg-2:         #9A9E99;   /* texte secondaire */
--fg-3:         #5A5E59;   /* texte désactivé / muted */
```

### Mode clair
```css
--bg-base:      #EEF0EB;
--bg-surface:   #FFFFFF;
--bg-elevated:  #E4E8DF;
--border:       #D0D8CA;
--border-strong:#B0BCA8;

--fg-1:         #1A1F18;
--fg-2:         #4A5248;
--fg-3:         #8A9288;
```

### Accent — Vert sauge (couleur primaire)
```css
--sage-700: #3A5235;
--sage-600: #4A6741;   /* accent dark sur fond clair */
--sage-500: #5C7D53;
--sage-400: #C8D5C2;   /* accent principal (dark mode) */
--sage-300: #D4DDD0;
```

### Couleurs sémantiques
```
Positif / revenus  : #A8C4A0  bg: rgba(168,196,160,0.12)
Négatif / dépenses : #C2A89A  bg: rgba(194,168,154,0.12)
Informatif         : #9AAFC2  bg: rgba(154,175,194,0.12)
Fiscal / impôts    : #A89AC2  bg: rgba(168,154,194,0.12)
Alerte / échéance  : #C2B89A  bg: rgba(194,184,154,0.12)
```

### Règles d'usage des couleurs
- Ne jamais utiliser du bleu vif, rouge vif ou vert vif — toujours des tons désaturés
- Pas de dégradés sauf pour les avatars/illustrations (linéaire 135deg, deux tons sage)
- L'accent sauge `#C8D5C2` remplace tout usage de gold/amber/orange
- Les montants positifs → `#A8C4A0`, négatifs → `#C2A89A`
- Les boutons primaires : fond `#C8D5C2`, texte `#1A1F18`

### Accessibilité des couleurs

- **Contraste texte/fond : AA minimum** (4,5:1 texte courant, 3:1 à partir de 18px/gras). `--fg-1` sur `--bg-base`/`--bg-surface` est large ; `--fg-2` et surtout `--fg-3` se rapprochent de la limite sur `--bg-elevated`/`--bg-overlay` — vérifier au cas par cas avant de les combiner (ex. un label `--fg-2` sur un badge `--bg-elevated`), pas seulement en isolation sur `--bg-base`.
- **La couleur seule ne porte jamais une information** : un montant positif/négatif, un statut de facture, une variante de badge — toujours accompagnés d'un signe, d'une icône ou d'un texte, pour les utilisateurs daltoniens.
- **Focus visible systématique** : le glow sauge (`box-shadow: 0 0 20px rgba(200,213,194,0.10)`, déjà utilisé sur les inputs) est le style de référence pour l'état `:focus-visible` de tout élément interactif — jamais de `outline: none` sans cet équivalent.

---

## Typographie

### Polices
```
Display / Titres : Syne (Google Fonts) — weights 600, 700
Corps de texte   : DM Sans (Google Fonts) — weights 400, 500, 600
Nombres / Monos  : JetBrains Mono (Google Fonts) — weights 400, 500, 600
```

Import Google Fonts :
```html
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
```

### Échelle typographique
| Rôle | Police | Taille | Poids | Usage |
|---|---|---|---|---|
| Display | Syne | 48px | 700 | Montants héros, titres de page majeurs |
| H1 | Syne | 28px | 600 | Titre de page (`<h1>`) |
| H2 | Syne | 22px | 600 | Titre de section |
| H3 | Syne | 18px | 600 | Titre de carte |
| H4 | DM Sans | 15px | 500 | Sous-titre, en-tête de groupe |
| Body | DM Sans | 14px | 400 | Texte courant |
| Small | DM Sans | 12px | 400 | Labels, captions |
| Label | DM Sans | 11px | 500 | Uppercase + letter-spacing 0.10em |
| Mono | JetBrains Mono | 13–36px | 400–600 | **Tous les montants, dates, codes** |

### Règles typographiques
- Tous les montants financiers → `font-family: 'JetBrains Mono'`
- Format monétaire français : `1 234,56 €` (espace fine, virgule, suffixe €)
- Les titres de page utilisent `letter-spacing: -0.03em`
- Sentence case pour les titres : "Mes comptes" pas "Mes Comptes"
- Tutoiement : s'adresser à l'utilisateur en "tu"
- Aucun emoji dans l'UI — icônes Lucide uniquement

---

## Espacement

Base : **8px**. Utiliser exclusivement les multiples suivants :

```
4px   8px   12px   16px   20px   24px   32px   40px   48px   64px   80px
```

**Règle absolue** : utiliser `gap` avec flex/grid, jamais de `margin` entre éléments frères.

---

## Border Radius
```
sm   : 6px   — badges, tags
md   : 10px  — boutons, inputs
lg   : 14px  — cartes, panels
xl   : 20px  — modales, sidesheets
full : 9999px — pills, avatars
```

---

## Ombres
```css
/* Carte normale */
box-shadow: 0 1px 3px rgba(0,0,0,0.5), 0 0 0 1px rgba(255,255,255,0.03);

/* Élément élevé (dropdown, modal) */
box-shadow: 0 4px 24px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);

/* Glow sauge (focus, sélection active) */
box-shadow: 0 0 20px rgba(200,213,194,0.10);
```

---

## Composants

### Boutons
```css
/* Primaire */
background: #C8D5C2;
color: #1A1F18;
font-family: 'DM Sans', sans-serif;
font-size: 13px;
font-weight: 500;
padding: 8px 16px;
border-radius: 10px;
border: none;

/* Secondaire */
background: #272727;           /* light: #E4E8DF */
color: #EDEEED;                /* light: #1A1F18 */
border: 1px solid #3D3D3D;    /* light: #B0BCA8 */
border-radius: 10px;
padding: 8px 16px;

/* Ghost / lien */
background: transparent;
color: #C8D5C2;
border: none;
```

### Cartes
```css
background: #1E1E1E;           /* light: #FFFFFF */
border: 1px solid #3D3D3D;    /* light: #B0BCA8 */
border-radius: 14px;
padding: 18px 20px;
box-shadow: 0 1px 3px rgba(0,0,0,0.5);
```

### Inputs
```css
background: #272727;
border: 1px solid #3D3D3D;
border-radius: 10px;
padding: 9px 12px;
font-family: 'DM Sans', sans-serif;
font-size: 14px;
color: #EDEEED;

/* Focus */
border-color: #C8D5C2;
box-shadow: 0 0 0 2px rgba(200,213,194,0.15);
```

### Badges / Pills
```css
display: inline-flex;
align-items: center;
gap: 4px;
padding: 2px 8px;
border-radius: 9999px;
font-family: 'DM Sans', sans-serif;
font-size: 12px;
font-weight: 500;
/* couleur selon contexte sémantique — voir palette */
```

### Progress bars
```css
height: 5–8px;
background (track): #272727;
border-radius: 99px;
/* fill color selon contexte : sage, terracotta, bleu selon seuil */
```

### Tags de catégorie
```css
background: #272727;
color: #9A9E99;
font-size: 11px;
font-weight: 500;
text-transform: uppercase;
letter-spacing: 0.06em;
padding: 2px 8px;
border-radius: 6px;
```

### Tag pill (`.tag-pill`)
Étiquette transverse d'une transaction, cliquable pour filtrer la liste. Contour et non aplat,
pour ne pas se confondre avec un badge sémantique ni avec la catégorie.
```css
border: 1px solid var(--border-strong);
border-radius: 9999px;
padding: 1px 8px;
font-size: 11px;
color: var(--fg-2);
/* hover : bordure + texte sage-400 */
```

---

## Navigation / Sidebar

- Fond sidebar : **toujours `#1E1E1E`** (dark), même en mode clair
- Largeur : 228px fixe
- Élément actif : `background: rgba(200,213,194,0.10)`, texte + icône `#C8D5C2`
- Hover : `background: #272727`
- Sélecteur d'espace (Personnel / Pro) en haut de sidebar
- Toggle thème (soleil/lune) en haut à droite de la sidebar

---

## Icônes

Source : **Lucide Icons** — fichier local `public/vendor/lucide/lucide.min.js` (hébergé sans dépendance CDN).

- Taille : 14px ou 16px — jamais autre chose
- `stroke-width: 1.5` (défaut Lucide)
- Couleur hérite du texte parent
- **Jamais d'icônes filled**

### Correspondances clés
| Section | Icône Lucide |
|---|---|
| Tableau de bord | `layout-dashboard` |
| Comptes | `landmark` |
| Transactions | `arrow-left-right` |
| Immobilier | `building-2` |
| Facturation | `file-text` |
| Impôts | `receipt` |
| Rappels | `bell` |
| Documents | `folder` |
| Revenus (flèche) | `arrow-down-left` |
| Dépenses (flèche) | `arrow-up-right` |
| Virement | `arrow-left-right` |
| Ajouter | `plus` |
| Exporter | `download` |
| Paramètres | `settings` |

---

## Layout

- **Sidebar** : fixe gauche, 228px
- **Contenu principal** : flex 1, scroll vertical
- **Grille dashboard** : `grid-template-columns: 1fr 360px` (content + aside)
- **Grille KPIs** : `repeat(4, 1fr)` avec `gap: 14px`
- **Max-width** : 1400px centré sur grands écrans
- Pas de scroll horizontal

### Responsive

Aucun breakpoint n'était défini avant cette version — ce qui suit est la référence pour tout nouveau template, à affiner au premier écran mobile réellement construit plutôt qu'à deviner plus loin que nécessaire.

```
≥ 1100px  : layout desktop décrit ci-dessus (sidebar fixe 228px, grille KPIs à 4 colonnes)
769–1099px: sidebar repliable en icônes seules (largeur ~64px, labels en tooltip)
≤ 768px   : sidebar devient une barre de navigation basse (icônes + label court) ;
            grille KPIs passe à 2 colonnes puis 1 sous 420px ; grille dashboard
            (`1fr 360px`) repasse en une seule colonne, l'aside après le contenu
```

- **Cible tactile minimale : 44×44px** sur mobile/tablette (boutons, items de nav, lignes de tableau cliquables) — les 14–16px des icônes Lucide restent inchangés, c'est la zone cliquable autour qui grandit (`padding`).
- Toujours vérifier au zoom navigateur 200% autant qu'aux breakpoints : pas de troncature de texte, pas de scroll horizontal (règle absolue, voir *Ce qu'il ne faut JAMAIS faire*).

---

## Données & format

### Montants
```
Format FR : 1 234,56 €   (toLocaleString('fr-FR', { minimumFractionDigits: 2 }))
Positif   : +1 250 €     (toujours préfixé du signe)
Négatif   : −842 €       (tiret cadratin − pas le tiret -)
Police    : JetBrains Mono obligatoire
```

**Multi-devise** : le `€` suffixe n'est que le cas `EUR`. Un compte ou un actif dans une autre devise (`Account.currency`, `Space.currency`) affiche son propre symbole/code à la place — toujours celui de la devise de la **ligne affichée**, jamais celui de la devise de référence du space. Le formatage (groupement de milliers, virgule décimale, signe, tiret cadratin) ne change pas ; seul le suffixe varie. Ce formatage vit dans `MoneyExtension` — jamais recalculé dans un template ou en JS.

---

## Thème clair / sombre

Le thème est géré via une classe ou data-attribute sur `<html>` ou `<body>` :
```html
<body data-theme="dark">   <!-- défaut -->
<body data-theme="light">
```

Persister en `localStorage` sous la clé `flooze-theme`.

---

## Ce qu'il ne faut JAMAIS faire

- ❌ Utiliser `Inter`, `Roboto`, `Arial` ou des polices système
- ❌ Border-radius > 20px (sauf `border-radius: 9999px` pour les pills)
- ❌ Couleurs vives non désaturées (bleu `#0070f3`, vert `#00c853`, rouge `#f44336`)
- ❌ Dégradés en arrière-plan de page
- ❌ Emoji dans l'UI
- ❌ `margin` entre éléments frères — utiliser `gap`
- ❌ Montants en police non-mono
- ❌ Texte en ALL CAPS sauf les labels (`.type-label`)
- ❌ Scroll horizontal
- ❌ Images ou photos dans l'UI — données et icônes seulement
- ❌ Gold/amber/orange — remplacé par le sauge `#C8D5C2`

---
