# Design System Document: Academic Editorial

## 1. Overview & Creative North Star: "The Digital Chancellor"
The vision for this design system is to bridge the gap between traditional academic prestige and modern high-performance software. We are moving away from the "generic SaaS" look to embrace **The Digital Chancellor**—a North Star that prioritizes institutional authority through sophisticated, layered surfaces and an editorial-first approach to information density.

To break the "template" feel, we reject rigid, boxed-in layouts in favor of **Intentional Asymmetry**. By utilizing generous whitespace and varying container widths, we create a sense of bespoke craftsmanship. The interface shouldn't feel like a series of cards; it should feel like a perfectly typeset document that has come to life.

---

## 2. Colors: Tonal Architecture
We utilize a Material Design-inspired palette to create a spectrum of depth. Our primary `navy` provides the "Anchor," while our `emerald` accents serve as "Precision Indicators."

### The "No-Line" Rule
**Explicit Instruction:** 1px solid borders are prohibited for sectioning. Structural boundaries must be defined solely through background color shifts or tonal transitions. To separate the sidebar from the main content, do not draw a line; instead, place a `surface-container-low` sidebar against a `surface` background.

### Surface Hierarchy & Nesting
Treat the UI as a series of physical layers. We use surface tiers to define importance:
- **`surface-container-lowest` (#ffffff):** Reserved for the most interactive elements, like primary data cards or active inputs.
- **`surface` (#f7f9fd):** The base canvas for the entire application.
- **`surface-container-high` (#e6e8ec):** Used for "recessed" areas like search bars or inactive utility panels.

### The "Glass & Gradient" Rule
To add "soul" to the institutional gravity, use Glassmorphism for floating overlays (Modals, Popovers). Apply a semi-transparent `surface` color with a `backdrop-blur` of 12px. For high-impact CTAs, use a subtle linear gradient from `primary` (#011549) to `primary_container` (#1a2b5e) at a 135-degree angle.

---

## 3. Typography: Editorial Authority
Our typography is designed to feel like a high-end academic journal. We pair the geometric strength of **Sora** (titles) with the Swiss-style utility of **Inter** (body).

- **Display & Headlines:** Use `headline-lg` (2rem) and `display` scales to create clear entry points. These should be set in **Sora Bold** with a tighter letter-spacing (-0.02em) to feel authoritative.
- **Body & Labels:** Use **Inter** for all functional text. `body-md` (0.875rem) is our workhorse. Ensure a line-height of 1.5 to maintain readability during long grading sessions or data entry.
- **Technical/IDs:** Any student ID, course code, or timestamp must use **JetBrains Mono**. This distinguishes "system data" from "human content," adding a layer of technical sophistication.

---

## 4. Elevation & Depth: Tonal Layering
We move away from the "drop shadow on everything" approach. Hierarchy is achieved through **Tonal Layering**.

- **The Layering Principle:** Place a `surface-container-lowest` card on a `surface-container-low` section. The change in hex value creates a soft, natural lift that is cleaner than a shadow.
- **Ambient Shadows:** When an element must float (e.g., a dropdown), use a shadow tinted with our `on-surface` color: `0 12px 32px rgba(25, 28, 31, 0.06)`. This mimics natural light reflecting off a premium surface.
- **The "Ghost Border":** If accessibility requires a stroke (e.g., in high-contrast modes), use `outline_variant` at **15% opacity**. Never use 100% opaque borders.

---

## 5. Components: The Primitive Set

### Buttons
- **Primary:** Gradient fill (`primary` to `primary_container`), white text, `md` (12px) corners.
- **Secondary:** `surface-container-highest` background with `on-surface` text. No border.
- **Tertiary:** Pure text with a subtle background shift to `surface-container-low` on hover.

### Cards & Lists
**Rule:** Forbid divider lines.
Separate list items using `8px` of vertical whitespace or a subtle hover state change to `surface-container-low`. Cards should use `xl` (24px) padding to allow the content to breathe, inspired by the spaciousness of Notion.

### Inputs
Text fields should utilize `surface-container-lowest` with a "Ghost Border" that transitions to a 2px `primary` bottom-stroke on focus. This creates a "signature" look that feels more sophisticated than a standard box.

### Academic-Specific Components
- **Attendance Chips:** Use `secondary_container` (Emerald) for "Present" and `tertiary_container` (Coral) for "Absent." The text should be `on_secondary_container` and `on_tertiary_container` respectively for AA accessibility.
- **Course Timeline:** An asymmetrical vertical line using `outline_variant` at 20% opacity, with `surface_tint` nodes to mark milestones.

---

## 6. Do’s and Don’ts

### Do
- **Do** use `JetBrains Mono` for all numeric data and IDs to emphasize the SaaS's precision.
- **Do** favor "Nested Surfaces" over borders to define areas.
- **Do** lean into asymmetrical layouts for dashboards; not every column needs to be the same width.
- **Do** use `surface-container-lowest` for the main content area to make it pop against the `surface` background.

### Don’t
- **Don’t** use pure black (#000000). Use `on_surface` (#191c1f) for all dark elements to maintain the navy-tinted atmosphere.
- **Don’t** use default 4px or 8px corners. Stick to the `md` (12px) scale to maintain the "Modern Institutional" feel.
- **Don’t** use high-opacity shadows. If you can see the shadow clearly, it’s too dark.
- **Don’t** use dividers between list items. Use whitespace.