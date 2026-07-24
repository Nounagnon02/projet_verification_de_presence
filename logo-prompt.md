# Logo Prompt — UAC Presence Tracker

**Projet :** Système de Gestion des Présences — Université d'Abomey-Calavi (Bénin)
**Date :** 2026-07-24
**Contexte :** Application Laravel + React (admin) + React Native/Expo (mobile étudiant)

---

## 🎯 Identité Visuelle : "The Digital Chancellor"

> **Vision :** Pont entre le prestige académique traditionnel et le SaaS haute performance moderne.
> **Mots-clés :** Autorité institutionnelle moderne, SaaS éditorial, asymétrie intentionnelle, superposition tonale, glassmorphisme, indicateurs de précision.

---

## 🎨 Palette de Couleurs (Stricte)

| Rôle | Nom | Hex | RGB | HSL | Usage |
|------|-----|-----|-----|-----|-------|
| **Primary (Anchor)** | Navy | `#011549` | `rgb(1,21,73)` | `hsl(220, 97%, 14%)` | Fond principal, texte fort, app icon bg |
| **Primary Container** | Navy Light | `#1a2b5e` | `rgb(26,43,94)` | `hsl(222, 57%, 24%)` | Dégradés CTA (135°), hover states |
| **Accent (Precision)** | Emerald | `#006d42` | `rgb(0,109,66)` | `hsl(156, 100%, 21%)` | Validation, succès, accent unique |
| **Emerald Container** | Emerald Light | `#a7f3d0` | `rgb(167,243,208)` | `hsl(152, 77%, 80%)` | Chips "Présent", backgrounds subtils |
| **Surface** | Surface Base | `#f7f9fd` | `rgb(247,249,253)` | `hsl(220, 33%, 96%)` | Fond application (light mode) |
| **Surface Container Lowest** | White | `#ffffff` | `rgb(255,255,255)` | — | Cards, inputs, surfaces élevées |
| **On Surface** | Navy Text | `#191c1f` | `rgb(25,28,31)` | `hsl(210, 11%, 11%)` | Texte principal (jamais #000 pur) |
| **Outline Variant** | Border Subtle | `#73777e @ 15%` | — | — | Frontières accessibilité uniquement |

**Mode Sombre :** Inversion Navy ↔ White, Emerald inchangé.

---

## 🔤 Typographie

| Échelle | Police | Poids | Taille | Tracking | Line-height |
|---------|--------|-------|--------|----------|-------------|
| **Display / Logo** | **Sora** | Bold | 2rem (32px) | `-0.02em` | 1.2 |
| **Headline** | Sora | Bold | 1.5rem (24px) | `-0.02em` | 1.3 |
| **Title** | Sora | SemiBold | 1.25rem (20px) | `-0.01em` | 1.4 |
| **Body (workhorse)** | **Inter** | Regular | 0.875rem (14px) | `0` | 1.5 |
| **Label / Button** | Inter | Medium | 0.75rem (12px) | `+0.02em` | 1.4 |
| **Code / ID / Data** | **JetBrains Mono** | Regular | 0.8125rem (13px) | `0` | 1.5 |

> **Règle :** JetBrains Mono pour TOUS les identifiants (matricules, codes UE, tokens QR, timestamps).

---

## 📐 Principes de Design

### Règle "No-Line" (Pas de bordures 1px)
- Les frontières structurelles = **changements de ton de surface uniquement**
- Sidebar `surface-container-low` sur fond `surface` — pas de trait
- Si accessibilité exige un trait : `outline_variant` à **15% opacité** max

### Superposition Tonale (Tonal Layering)
- Hiérarchie par différences de valeur hex, pas par ombres
- Carte `surface-container-lowest` sur section `surface-container-low` = lift naturel
- Ombres ambiantes seulement : `0 12px 32px rgba(25,28,31,0.06)` (à peine visibles)

### Glassmorphism
- Overlays flottants (modals, popovers) : `surface` semi-transparent + `backdrop-blur: 12px`

### Rayons d'Angle
- Échelle unique : `md` = **12px** (ni 4px, ni 8px, ni 16px+)
- App icon iOS : 12px radius | Android : squircles adaptatifs

### Dégradés
- **Autorisé :** 2 stops max, 135°, `#011549` → `#1a2b5e` (CTA primaires uniquement)
- **Interdit :** >2 stops, dégradés sur texte, dégradés d'arrière-plan

### Asymétrie Intentionnelle
- Largeurs de conteneurs variables, whitespace généreux
- Pas de grilles rigides symétriques — feeling "document typographié vivant" (style Notion)

---

## 🧩 Concepts de Symbole (Choisir UNE direction)

### Option A — Monogramme Abstrait "UAC"
- **U** : Arc ascendant (présence qui monte/réussite)
- **A** : Forme checkmark/geste de validation
- **C** : Cercle partiel (cycle complet de présence)
- Accent Emerald **uniquement** sur le trait du checkmark

### Option B — Symbole Abstrait Minimaliste
- Checkmark ✓ formant un "P" subtil (Presence)
- Intégré dans rounded square / rounded rectangle (app-ready)
- Fond Navy, trait checkmark Emerald
- Highlight glassmorphism subtil sur bord supérieur

### Option C — Wordmark Typographique
- **"UAC"** : Sora Bold, Navy `#011549`, tracking `-0.02em`
- **"Presence"** : Sora Medium, Emerald `#006d42`, tracking `-0.01em`
- **"Tracker"** : Sora Regular, Navy `#191c1f`, tracking normal
- Underscore Emerald subtil sous "Presence" (indicateur précision)
- Variante app icon : monogramme "UAC" seul dans rounded square

### Option D — Écu Académique Réinterprété
- Shield outline minimaliste Navy (autorité institutionnelle)
- Intérieur : checkmark abstrait = graphique présence (barres montantes)
- Accent Emerald sur la tendance haussière
- Pas de bordures — séparation tonale uniquement

---

## 📱 Exigences Techniques

### Formats de Sortie Requis

| Variant | Format | Dimensions | Usage |
|---------|--------|------------|-------|
| **Horizontal Lockup** | SVG + PNG | 2048×1024 (2:1) | Header web, docs |
| **Stacked Lockup** | SVG + PNG | 1024×1024 (1:1) | Signatures, social |
| **Icon Only / App Icon** | SVG + PNG | 1024×1024, 512×512 | App stores, PWA |
| **Favicon** | PNG + ICO | 32×32, 16×16 | Browser tab |
| **Single-Color Navy** | SVG + PNG | All sizes | Impression 1 couleur |
| **Single-Color White** | SVG + PNG | All sizes | Sur fond Navy (dark mode) |
| **Brand Guidelines** | PDF / Figma | 1 page | Handoff dev/design |

### Zone de Sécurité App Icon
- **512×512** avec **marge de sécurité 72px** (contenu centré dans 368×368)
- Test lisibilité à **32×32** (favicon) et **16×16**

### Variantes Couleur
| Mode | Fond | Marque | Accent |
|------|------|--------|--------|
| Light | `#f7f9fd` | `#011549` | `#006d42` |
| Dark | `#011549` | `#ffffff` | `#a7f3d0` |
| App Icon | `#011549` | `#ffffff` | `#006d42` (stroke unique) |
| Print 1C | White | `#011549` | — |

---

## ❌ Negative Prompt (À Éviter)

```
Pure black #000000, pure white borders, thick borders (1px+), drop shadows, generic corporate blue, generic green checkmarks, generic graduation caps, generic checkboxes, clipart, gradients with more than 2 stops, busy patterns, drop shadows > 20% opacity, rounded corners < 8px or > 16px, symmetrical rigid grids, template feel, generic SaaS illustrations, stock photo feel, 3D renders, photorealism, gradients with >2 colors, outlines, strokes on text, bevel/emboss, outer glow, inner shadow, busy backgrounds, watermarks, text effects
```

---

## 🎯 Prompts Prêts à l'Emploi

### 1. Prompt Principal (Midjourney / DALL-E 3 / Stable Diffusion / Firefly)

```
Professional logo for "UAC Presence Tracker" - Academic attendance tracking system for Université d'Abomey-Calavi (Benin).

**Visual Identity:** "The Digital Chancellor" - Modern institutional authority meets high-performance SaaS. Bridge between traditional academic prestige and modern technology.

**Color Palette (Strict):**
- Primary Navy (Anchor): #011549 (Deep academic navy)
- Primary Container: #1a2b5e (Lighter navy for gradients)
- Emerald Accent (Precision): #006d42 (Academic green/emerald)
- Secondary Container (Emerald Light): #a7f3d0 (Success/present states)
- Surface: #f7f9fd (Clean background)
- Surface Container Lowest: #ffffff (Cards/surfaces)
- On-Surface Text: #191c1f (Navy-tinted black, never pure #000)

**Typography Identity:**
- Display/Logo: Sora Bold, tight tracking (-0.02em) - authoritative, geometric
- Technical/Monospace: JetBrains Mono for any alphanumeric codes
- Body: Inter (UI reference only)

**Visual Language - "Intentional Asymmetry":**
- No rigid boxes or rigid symmetry - intentional asymmetry, generous whitespace
- "No-Line Rule": No 1px borders. Boundaries defined by tonal surface shifts
- Glassmorphism for depth: semi-transparent surfaces with 12px backdrop-blur
- Gradient CTAs: 135° linear gradient from #011549 to #1a2b5e
- Rounded corners: 12px (md radius) - modern institutional feel
- Ambient shadows only: 0 12px 32px rgba(25,28,31,0.06) - barely visible

**Symbol Concepts (choose ONE direction):**

**Option A - Abstract Monogram:** Stylized "UAC" monogram where:
  - "U" forms an upward arc (attendance rising/success)
  - "A" forms a checkmark/check-in gesture (validation)
  - "C" forms a partial circle (completion/cycle of attendance)
  - Emerald accent on the checkmark stroke only

**Option B - Abstract Symbol:** Minimalist geometric mark combining:
  - A checkmark ✓ forming a subtle "P" (for Presence)
  - Integrated into a rounded square/rounded rectangle (mobile app icon ready)
  - Navy base with emerald accent stroke on the checkmark
  - Subtle glassmorphism highlight on top edge

**Option C - Typographic Logo:** Wordmark "UAC Presence Tracker" in Sora Bold:
  - "UAC" in Navy #011549, tight tracking
  - "Presence" in Emerald #006d42, slightly lighter weight
  - "Tracker" in Navy #191c1f, regular weight
  - Subtle emerald underscore under "Presence" (precision indicator)
  - App icon variant: just "UAC" monogram in rounded square

**Option D - Symbolic Academic:** Modern shield/crest reinterpretation:
  - Minimalist shield outline in Navy (institutional authority)
  - Inside: abstract checkmark forming attendance chart (upward bars)
  - Emerald accent on the upward trend
  - No borders - tonal separation only

**Technical Requirements:**
- Vector format (SVG) + PNG exports (512x512, 1024x1024, 2048x2048)
- App icon safe area: 512x512 with 72px safe margin
- Works in single-color (Navy #011549) and single-color white (reversed)
  - Light mode: Navy on Surface #f7f9fd
  - Dark mode: White on Navy #011549
  - App icon: Navy background with white/emerald mark
- Favicon: 32x32 simplified monogram
- Aspect ratios: 1:1 (app icon), 1:2 (horizontal lockup), 1:1 (icon only)

**Style Keywords:** Modern institutional, editorial SaaS, academic SaaS, digital chancellor, intentional asymmetry, tonal layering, glassmorphism, precision indicators, jetbrains mono accents, sora typography, emerald precision, navy authority, no borders tonal separation, generous whitespace, asymmetric layout, notion-like spaciousness

**Negative Prompt (avoid):**
Pure black #000000, pure white borders, thick borders, drop shadows, generic corporate blue, generic green checkmarks, generic graduation caps, generic checkboxes, clipart, gradients with more than 2 stops, busy patterns, drop shadows > 20% opacity, rounded corners < 8px or > 16px, symmetrical rigid grids, template feel, generic SaaS illustrations, stock photo feel, 3D renders, photorealism, gradients with >2 colors

**Output Formats Required:**
1. Full horizontal lockup (SVG + PNG 2048x1024)
2. Stacked lockup (SVG + PNG 1024x1024)
3. Icon only / App icon (SVG + PNG 1024x1024 + 512x512)
4. Favicon (32x32, 16x16 PNG + ICO)
5. Single-color variants (Navy only, White only)
6. Brand guideline one-pager (colors, clear space, minimum size, don'ts)
```

---

### 2. Prompt Court (Test Rapide — Midjourney/DALL-E 3)

```
Minimalist professional logo for "UAC Presence Tracker" - Benin university attendance app. Modern academic SaaS brand. Navy #011549 primary, Emerald #006d42 accent. Sora Bold typography. "Digital Chancellor" aesthetic: institutional authority meets modern tech. No borders, tonal layering only. Glassmorphism subtle. 12px rounded corners. Abstract monogram: stylized UAC forming checkmark/attendance cycle. Emerald accent on validation stroke. App icon ready (512x512 safe area). Vector clean. No gradients >2 stops. No pure black. No drop shadows. Editorial asymmetry. --no borders, shadows, gradients, 3d, photorealism, generic icons, clipart, pure black, thick strokes --ar 1:1 --stylize 250
```

---

### 3. Prompt Wordmark Typographique Pur

```
Professional typographic logo "UAC Presence Tracker". Three-weight wordmark in Sora font family.
- "UAC" : Sora Bold, #011549, tracking -0.02em
- "Presence" : Sora Medium, #006d42, tracking -0.01em
- "Tracker" : Sora Regular, #191c1f, normal tracking
Subtle emerald #006d42 underline under "Presence" only (precision indicator).
Clean, editorial, academic SaaS. Horizontal lockup 4:1 ratio. Stacked variant 1:1.
White background. Vector SVG. No effects, no shadows, no gradients on text.
Minimal, authoritative, modern institutional.
```

---

### 4. Prompt Icône App Uniquement (512×512 / 1024×1024)

```
iOS/Android app icon 1024x1024 for "UAC Presence Tracker". Rounded square 12px radius (iOS) / squircles (Android adaptive).
Background: Solid Navy #011549.
Center mark: White abstract monogram - stylized "UAC" forming a checkmark/attendance validation symbol.
Single emerald #006d42 accent stroke on the checkmark validation line.
Subtle glassmorphism highlight: top 20% white 8% opacity overlay.
No border, no shadow, no gradient background. Clean, scalable to 32x32.
Safe area: 72px padding from edges. Centered optically.
Vector style, flat design, modern institutional.
```

---

### 5. Prompt Brand Guidelines One-Pager (Notion/Figma/PDF)

```
Generate a one-page brand guideline document for "UAC Presence Tracker" including:

1. LOGO USAGE
   - Primary horizontal lockup (min width 200px)
   - Stacked variant (min width 120px)
   - Icon only (min 32px)
   - Clear space: 1x "U" height on all sides
   - Forbidden: rotation, recoloring, stretching, drop shadows, outlines

2. COLOR PALETTE (with hex, rgb, hsl, css variables)
   - Primary Navy: #011549 / rgb(1,21,73) / hsl(220, 97%, 14%)
   - Primary Container: #1a2b5e
   - Emerald Accent: #006d42 / rgb(0,109,66) / hsl(156, 100%, 21%)
   - Emerald Container: #a7f3d0
   - Surface: #f7f9fd
   - Surface Container Lowest: #ffffff
   - On Surface: #191c1f
   - Outline Variant: #73777e @ 15% opacity
   - Dark mode inversions

3. TYPOGRAPHY SCALE
   - Display: Sora Bold / 2rem / -0.02em
   - Headline: Sora Bold / 1.5rem / -0.02em
   - Title: Sora SemiBold / 1.25rem / -0.01em
   - Body: Inter Regular / 0.875rem / 1.5 line-height
   - Label: Inter Medium / 0.75rem / 0.02em
   - Code/ID: JetBrains Mono / 0.8125rem

4. SPACING & RADIUS
   - Base unit: 4px
   - Radius: xs 4px, sm 8px, md 12px, lg 16px, xl 24px
   - Card padding: xl (24px)
   - Layout max-width: 1280px

5. ELEVATION/SHADOWS
   - Level 1 (cards): none (tonal separation)
   - Level 2 (floating): 0 12px 32px rgba(25,28,31,0.06)
   - Level 3 (modals): 0 24px 48px rgba(25,28,31,0.08) + backdrop-blur 12px

6. COMPONENT STYLES
   - Buttons: Primary (gradient), Secondary (surface-container-high), Tertiary (text only)
   - Inputs: surface-container-lowest, ghost border, 2px primary bottom stroke on focus
   - Chips: Present=emerald container, Absent=coral container
   - Cards: no borders, xl padding, surface-container-lowest on surface

7. DO'S AND DON'TS (visual examples)
   - Do: tonal layers, asymmetry, emerald precision, jetbrains mono for IDs
   - Don't: pure black, borders, heavy shadows, rigid grids, >2-stop gradients

Format: Clean PDF/Figma frame, academic editorial layout, Sora/Inter typography.
```

---

## 📋 Checklist de Validation Logo

- [ ] Lisible à 32×32 (favicon)
- [ ] Lisible à 16×16 (browser tab)
- [ ] Fonctionne en 1 couleur (Navy seul)
- [ ] Fonctionne en blanc sur Navy (dark mode / app icon)
- [ ] Zone de sécurité respectée (72px padding app icon)
- [ ] Accent Emerald utilisé **une seule fois** (precision indicator)
- [ ] Pas de bordures 1px visibles
- [ ] Rayons 12px cohérents
- [ ] Typographie Sora/Inter/JetBrains Mono respectée
- [ ] Espace clair = 1x hauteur "U" minimum
- [ ] Exports SVG + PNG aux bonnes tailles
- [ ] Brand guidelines 1 page générée

---

## 🔗 Ressources Projet (Référence)

- **Design System Source :** `maquette/stitch_uac_pr_sence_tracker/stitch_uac_pr_sence_tracker/uac_academic_slate/DESIGN.md`
- **Dashboard Maquette :** `maquette/stitch_uac_pr_sence_tracker/stitch_uac_pr_sence_tracker/tableau_de_bord/DESIGN.md`
- **Audit Final :** `docs/RAPPORT_AUDIT_FINAL.md` (conformité 92%, prêt soutenance)
- **Stack :** Laravel 12 + React 19 (admin) + Expo/React Native 0.86 (mobile)

---

*Généré le 2026-07-24 pour le projet UAC Presence Tracker — Université d'Abomey-Calavi*