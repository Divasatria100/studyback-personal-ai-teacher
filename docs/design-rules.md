> **Implementation Language Directive**
>
> This document is written in Indonesian for documentation and planning purposes. However, all implementation based on this document must use **English** as the project's primary language.
>
> When implementing the requirements described in this document:
>
> * All user-facing UI text, labels, buttons, messages, notifications, and content must be written in **English**.
> * All source code, variable names, function names, class names, component names, and comments should use **English**.
> * All database names, table names, column names, enum values, and database-related identifiers must use **English**.
> * All API endpoints, request/response fields, validation messages, and API-related identifiers must use **English**.
> * All routes, URLs, configuration keys, and other technical identifiers must use **English**.
> * Do not directly copy Indonesian wording from this document into the application.
> * When this document contains Indonesian descriptions, interpret them as **functional and design requirements**, not as the literal language to be used in the implementation.
>
> **Important:** The language of this document does not determine the language of the application. The application must remain fully English unless another language requirement is explicitly specified.


# Studyback Design System

## 1. Design Direction

Studyback uses a clean, modern, educational interface with a subtle glassmorphism treatment.

The visual direction combines:

- Clean and structured SaaS interface
- Soft glass surfaces
- Generous whitespace
- Clear typography hierarchy
- Subtle depth and elevation
- Restrained motion
- Strong readability and contrast

Glassmorphism is used as a **component treatment**, not as the source of the application's color palette.

The Studyback color system defined in this document is the single source of truth for all colors.

---

## 2. Color System

Use the following Studyback color tokens consistently across the application.

| Token | Value | Usage |
|---|---|---|
| Primary | `#A2D2F5` | Primary actions, highlights |
| Secondary | `#CBD5E1` | Secondary UI elements |
| Accent | `#94A3B8` | Accents, secondary emphasis |
| Background | `#CBD5E1` | Main page background |
| Surface | `#94A3B8` | Component surfaces |
| Text Primary | `#111827` | Main text |
| Text Secondary | `#4B5563` | Supporting text |
| Border | `#CBD5E1` | Borders and dividers |

### Color Rules

- Keep background, surface, text, and border roles distinct.
- Maintain sufficient text/background contrast.
- Do not introduce unrelated palette colors.
- Do not replace the Studyback palette with colors from external design templates.
- Semantic colors such as success, warning, error, and info may be introduced only when required by UI state and should remain visually restrained.
- Avoid oversaturated accent colors.

---

## 3. Typography

### 3.1 Font Families

**Display / Heading**
- Font: `Inter`
- Used for hero headings, page titles, and major visual statements.

**Body**
- Font: `Playfair Display`
- Used for primary body copy and supporting content.

**UI Labels / Technical Metadata**
- Font: `JetBrains Mono`
- Used for labels, metadata, technical values, status indicators, and compact UI information.

### 3.2 Typography Tokens

**Display Large**
```css
font-family: Inter;
font-size: 64px;
font-weight: 500;
line-height: 1.04;
letter-spacing: 0;
```

**Body Medium**
```css
font-family: Playfair Display;
font-size: 16px;
font-weight: 400;
line-height: 1.6;
```

**Label Medium**
```css
font-family: JetBrains Mono;
font-size: 12px;
font-weight: 600;
line-height: 1.2;
```

### 3.3 Typography Rules

- Maintain a clear hierarchy between headings, body text, and labels.
- Use Inter for visual impact and major headings.
- Use Playfair Display for readable body content.
- Use JetBrains Mono sparingly for metadata and technical information.
- Do not use excessive font weights.
- Do not use typography as decoration at the expense of readability.

---

## 4. Spacing System

Use an 8px spacing rhythm.

| Token | Value |
|---|---:|
| Base | `8px` |
| Standard Gap | `16px` |
| Card Padding | `24px` |
| Section Padding | `80px` |

### Spacing Rules

- Prefer multiples of 8px where practical.
- Maintain consistent spacing between related elements.
- Use larger spacing to separate distinct sections.
- Avoid overly dense layouts.
- Preserve visual breathing room around major content.

---

## 5. Border Radius

The base design system uses intentionally restrained rounding.

| Token | Value |
|---|---:|
| Card | `1px` |
| Control | `1px` |
| Pill | `9999px` |

These values are the default design-system tokens.

However, glassmorphism components may use a larger radius when required by the glass surface treatment, as defined in Section 6.

Do not apply large rounded corners universally.

---

## 6. Glassmorphism

Glassmorphism is a **visual surface treatment** applied to selected components. It does not define the application's color palette.

### 6.1 Purpose

Glass surfaces provide:

- visual separation from the background;
- subtle depth;
- hierarchy between content layers;
- a modern visual identity;
- improved grouping of related information.

### 6.2 Core Properties

Glass components should use:

```css
backdrop-filter: blur(...);
-webkit-backdrop-filter: blur(...);
border: 1px solid ...;
background: ...;
box-shadow: ...;
```

The exact background color must use the **Studyback color system**, not the colors from the original glassmorphism reference.

### 6.3 Glass Surface Rules

A glass component should visually communicate:

```
Background
    ↓
Glass Surface
    ↓
Content
```

The glass layer should remain translucent/subtle enough that the underlying background contributes to the visual depth.

Do not make the glass effect so strong that it becomes visually indistinguishable from a normal opaque card.

### 6.4 Glass Components

Glassmorphism may be used for:

- primary content cards;
- profile panels;
- material cards;
- study workspace panels;
- learning map / sidebar;
- modal / dialog surfaces;
- floating controls;
- selected navigation elements.

Not every element needs to use glassmorphism. Use it selectively to establish hierarchy.

---

## 7. Glass Card Treatment

Glass cards should follow this general structure:

```
Glass Card
├── subtle translucent surface
├── backdrop blur
├── thin border
├── restrained shadow
└── content
```

Recommended visual behavior:

- subtle transparency;
- moderate backdrop blur;
- thin border;
- soft shadow;
- restrained contrast;
- clear text hierarchy.

Avoid:

- excessive blur;
- heavy shadows;
- strong glow;
- excessive transparency;
- overly bright borders.

---

## 8. Elevation & Depth

Depth should primarily come from, in order of priority:

1. glass transparency;
2. backdrop blur;
3. subtle border;
4. soft shadow;
5. restrained layering.

Cards should not rely on heavy drop shadows.

A glass surface should feel elevated without appearing detached from the page.

---

## 9. Buttons

### 9.1 Primary Button

Primary actions use the Studyback `primary` or `accent` color.

Default characteristics:

- clear visual hierarchy;
- restrained radius;
- comfortable padding;
- strong text contrast;
- subtle hover feedback.

### 9.2 Secondary / Ghost Button

Secondary actions may use:

- transparent/glass surface;
- subtle border;
- Studyback text color.

Hover:

- subtle background change;
- subtle elevation change.

### 9.3 Interaction States

Button states should include:

```
Default
Hover
Active
Focus
Disabled
Loading
```

Motion should remain subtle and fast.

---

## 10. Cards

Cards are the primary grouping mechanism for content.

Default behavior:

- use the appropriate Studyback surface token;
- subtle border;
- restrained elevation;
- consistent padding;
- clear content hierarchy.

For selected cards, glass treatment may be applied.

Cards should not become visually excessive through:

- heavy shadows;
- excessive gradients;
- excessive rounded corners;
- unnecessary decorative elements.

---

## 11. Inputs

Inputs should use:

- visible labels;
- clear borders;
- readable text;
- adequate spacing;
- obvious focus state.

Do not use floating labels unless specifically required by the interaction.

### 11.1 Focus

Focused inputs should have a visible but restrained focus indicator.

### 11.2 Error

Validation errors should:

- appear near the relevant input;
- clearly explain the problem;
- use an appropriate semantic error color;
- not rely solely on color.

---

## 12. Navigation

Navigation should be clean and minimal.

Active navigation items should have a clear visual indicator, using one of:

- typography;
- subtle background;
- accent;
- border;
- icon treatment.

Do not overload navigation with unnecessary visual decoration.

---

## 13. Icons

Use an icon system rather than emojis.

Preferred icon systems:

- Lucide
- Heroicons

Icons should:

- have consistent stroke/weight;
- align with surrounding text;
- communicate function clearly;
- not replace necessary textual labels when meaning would be unclear.

---

## 14. Loading & Empty States

### 14.1 Loading

Prefer skeleton states for content-heavy areas.

Avoid unnecessary circular spinners when the structure of the content is known.

### 14.2 Empty States

Use the following structure:

```
Icon
  ↓
Short explanation
  ↓
Primary action
```

Empty states should clearly explain what the user can do next.

---

## 15. Motion

Motion should be subtle, purposeful, and performant.

### 15.1 General Motion

Preferred easing: `ease-out`

Typical duration: `200–300ms`

### 15.2 Entry Animation

For content entering the interface:

```
opacity: 0 → 1
transform: translateY(16px) → translateY(0)
```

Typical duration: `420ms`

Lists may use restrained staggered animation: `~80ms` between items.

### 15.3 Hover

Hover transitions may use:

- subtle color shift;
- shadow adjustment;
- small elevation change.

### 15.4 Page Transition

Use a simple fade transition.

### 15.5 Performance

Prefer animating:

```
transform
opacity
```

Avoid animations that trigger layout recalculation.

---

## 16. Responsive Design

Use responsive layouts based on the application's actual information hierarchy.

The responsive system must prevent the interface from becoming excessively stretched on large screens while preserving usability, readability, accessibility, and natural browser zoom behavior.

### 16.1 Global Layout Containment

All application pages must use a constrained and centered content layout rather than allowing primary content to stretch indefinitely across the viewport.

Rules:

- Use a centered page container with a maximum content width of `1280px`.
- Apply approximately `24px` horizontal padding on desktop and smaller viewports as needed.
- Use `margin-inline: auto` or an equivalent centering mechanism for the main page container.
- Do not use unrestricted `width: 100%` as the sole constraint for primary page content.
- Content areas must not expand indefinitely simply because additional horizontal viewport space is available.
- Preserve consistent horizontal alignment across pages.
- Large desktop and ultrawide displays should result in additional surrounding whitespace rather than excessively stretched content.
- The layout must remain visually balanced when the viewport is significantly wider than the maximum content width.
- The maximum width applies to the page content container; individual components may use narrower content constraints when required for readability.

The goal is to make the application feel like a structured application interface rather than a layout that continuously stretches to fill the entire viewport.

### 16.2 Desktop

Use:

- CSS Grid;
- Flexbox where appropriate;
- asymmetric layouts where the information hierarchy requires them;
- fixed or constrained widths for persistent navigation/sidebar regions;
- generous spacing;
- clear content grouping;
- centered content containers;
- component-specific `max-width` constraints where necessary.

Maximum page content width: `1280px`, with approximately `24px` horizontal side padding.

Persistent sidebars or navigation regions that require stable sizing should use a fixed or constrained width and must not scale proportionally with the viewport.

For layouts containing a sidebar and main content area:

- the sidebar must remain visually stable;
- the main content area may expand only within the available page container;
- the main content must not force the sidebar to shrink;
- use `flex-shrink: 0` or an equivalent constraint when a fixed sidebar width is required.

### 16.3 Content Readability

Page-level width constraints must be complemented by content-level width constraints.

Do not assume that the entire `1280px` page container should be used by every content type.

Use narrower maximum widths for content that primarily contains readable text, conversations, explanations, or other sequential information.

Examples:

- Conversational/chat content: approximately `768px–800px`;
- Long-form reading content: approximately `700px–900px`;
- Standard forms/settings content: approximately `800px–1000px`;
- Data-heavy or card-grid pages may use most of the available page container.

Text-heavy content should not stretch across the full page width merely because additional viewport space is available.

### 16.4 Workspace Layout

The Studyback Workspace uses a two-region layout:

- Learning Map / Sidebar;
- Main Learning Area.

Desktop rules:

- The Learning Map uses a fixed or constrained width.
- The Learning Map must not continuously grow or shrink with the viewport.
- The Main Learning Area occupies the remaining available space within the page container.
- The Main Learning Area must contain a dedicated Conversation Column for conversational learning content.
- The Conversation Column must use a constrained `max-width`, approximately `768px–800px`.
- The Conversation Column should be centered horizontally within the available Main Learning Area.
- AI messages must remain within the Conversation Column.
- User messages may be right-aligned within the Conversation Column but must not be positioned relative to the full width of the Main Learning Area.
- Chat messages and conversational content must not stretch across the entire Main Learning Area.
- The message input/composer must align with the same Conversation Column rather than spanning the entire Main Learning Area.
- Additional horizontal space on large or ultrawide displays should remain as balanced surrounding whitespace around the Conversation Column.
- The Conversation Column must remain visually stable when the viewport becomes wider.

The conversation layout should behave as a bounded reading and interaction column rather than a full-width chat canvas.

### 16.5 Large Screens / Wide Viewports

When the viewport is wider than the maximum page content width:

- Do not scale the application content proportionally to fill the additional space.
- Keep the primary page container centered.
- Preserve the defined maximum content width.
- Allow additional horizontal space to remain as surrounding whitespace.
- Do not increase sidebar width merely because the viewport becomes wider.
- Do not increase text line length merely because the viewport becomes wider.
- Do not distribute major UI elements excessively far apart.

The application should maintain a consistent visual density and information hierarchy across standard desktop and large/ultrawide displays without proportionally scaling UI elements to fill the viewport.

### 16.6 Browser Zoom

Browser zoom must remain fully supported and must not be artificially disabled or counteracted.

Rules:

- Do not attempt to prevent browser zoom from affecting the interface.
- Do not use JavaScript or CSS techniques intended to force the UI to remain at a fixed browser-scale size.
- Treat browser zoom as a normal accessibility feature.
- Layout constraints must preserve usability when users zoom in or out.
- The purpose of `max-width`, fixed sidebar widths, and readable content widths is to prevent excessive layout stretching, not to disable or neutralize browser zoom.

### 16.7 Tablet

For `768px–1023px`:

- Reduce grid column counts appropriately.
- Secondary panels may move below primary content.
- Fixed desktop sidebars may collapse into drawers where necessary.
- Content containers remain constrained by viewport width.
- Horizontal padding may be reduced while preserving readable spacing.
- Avoid horizontal scrolling for primary page content.

### 16.8 Mobile

For widths below `768px`:

- Multi-column layouts should collapse appropriately.
- Use a single-column layout where necessary.
- No horizontal overflow.
- Preserve content hierarchy.
- Maintain readable typography.
- Stack panels vertically when necessary.
- Keep primary actions easily accessible.
- Content containers should use the available viewport width with appropriate horizontal padding.
- Fixed desktop sidebars must become collapsible drawers, sheets, or equivalent mobile navigation patterns.
- Chat and text-heavy content may use nearly the full available mobile width while preserving appropriate horizontal padding.

### 16.9 Responsive Integrity Rules

Across all breakpoints:

- Do not allow content to become excessively wide.
- Do not allow important content to become unnecessarily narrow.
- Do not rely solely on percentage-based widths for major layout regions.
- Avoid arbitrary fixed widths that cause horizontal overflow on smaller screens.
- Prefer `max-width`, `min-width`, flexible layouts, and breakpoint-specific constraints where appropriate.
- Preserve consistent alignment between related content.
- Preserve readability before maximizing the amount of content displayed per row.
- Responsive behavior must adapt the layout, not merely scale the entire interface.

The responsive system must prioritize:

1. Readability
2. Content hierarchy
3. Usability
4. Accessibility
5. Consistent visual density
6. Efficient use of available space

---

## 17. Layering & Z-Index

Use the following conceptual layering:

| Layer | Z-Index |
|---|---:|
| Base Content | 0 |
| Sticky Navigation | 100 |
| Overlay | 200 |
| Modal | 300 |
| Toast | 500 |

Use layering only when necessary.

---

## 18. Accessibility

The interface must maintain:

- readable contrast;
- visible keyboard focus;
- semantic HTML;
- accessible form labels;
- meaningful button labels;
- appropriate ARIA usage when necessary;
- non-color-only status communication.

Glassmorphism must never reduce readability or accessibility.

---

## 19. Visual Guardrails

### 19.1 Do

- Use the Studyback color system consistently.
- Use glassmorphism selectively.
- Maintain clear hierarchy.
- Preserve generous whitespace.
- Use subtle depth.
- Use restrained animation.
- Maintain consistent spacing.
- Maintain readable contrast.
- Use icons instead of emojis.
- Keep the interface visually calm and focused.

### 19.2 Do Not

- Do not use the original glassmorphism template's blue palette.
- Do not introduce unrelated brand colors.
- Do not make every element glass.
- Do not use excessive blur.
- Do not use heavy shadows.
- Do not use excessive glow.
- Do not use pure black `#000000`.
- Do not use oversaturated colors.
- Do not create unnecessary 3-column equal layouts.
- Do not use `h-screen`; prefer `min-h-[100dvh]`.
- Do not use generic SaaS sections that are unrelated to Studyback.
- Do not use AI-copywriting clichés such as "Elevate", "Seamless", "Unleash", or "Next-Gen".

---

## 20. Studyback UI Principle

The design system defines **how Studyback looks and behaves visually**.

The Product Specification defines **what Studyback contains and how users move through it**.

Therefore:

- Do not change the established page structure.
- Do not invent new pages.
- Do not move major sections without explicit product requirements.
- Do not turn learning modes into separate pages if the Product Specification defines them inside the Studyback Workspace.
- Do not let visual styling override usability or product hierarchy.

The UI/UX implementation should translate the existing Studyback product structure into a consistent visual system using the rules defined in this document.