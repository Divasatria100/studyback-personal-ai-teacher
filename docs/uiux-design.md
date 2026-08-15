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


# Studyback UI/UX Design Specification (Final)

Source of truth:
- **WHAT & WHERE** → `01-product-specification.md`
- **HOW IT LOOKS** → `design-rules.md`

This document translates the Studyback product structure into an implementation-ready UI/UX specification for AI IDE and developer use. It does not redefine information architecture, invent pages, or move major sections. Any detail not explicitly defined by either source file is logged in **Section 15 — Design Decisions**.

---

## 1. UI/UX Design Overview

### Design Direction
Studyback is a clean, modern, educational SaaS interface with a restrained glassmorphism treatment applied selectively to communicate hierarchy — not as a decorative theme. The interface should feel calm, focused, and study-friendly: generous whitespace, clear typographic hierarchy, and subtle motion that never competes with the learning content.

### Visual Identity
- Structured, grid-based SaaS layout with soft glass surfaces layered over a consistent background.
- Three-typeface system creates intentional hierarchy: Inter for impact (headings), Playfair Display for readable body copy, JetBrains Mono for metadata/technical labels (progress %, status tags, timestamps).
- Color system is closed and fixed (Section 2 of `design-rules.md`); glassmorphism only modulates transparency/blur of these existing tokens — it never introduces new hues.
- Depth comes primarily from translucency and blur, not shadow weight.

### UX Principles
1. **Preserve the established IA.** Home, My Materials, Material Detail, Study Session Configuration, and Studyback Workspace remain distinct, single-purpose pages/surfaces exactly as named in the product specification.
2. **Workspace is the learning core.** Teach Me, Quiz Me, Review Weak Topics, and Guided Study Session live *inside* the Studyback Workspace as modes of the Main Learning Area — never as standalone pages.
3. **Material Library ≠ Study Workspace.** My Materials only lists/manages materials; it never renders learning content.
4. **Material Detail ≠ Workspace.** It shows information *about* a material and is the launch point into a Study Session — it does not host learning interactions itself.
5. **Two distinct interaction grammars inside the Workspace:** Conversational UI (Teach Me, Review Weak Topics) vs. Structured UI (Quiz Me). These are never merged into one pattern.
6. **Glass is selective, not universal.** Applied to elevate primary content (profile panel, material cards, workspace panels, learning map, modals) while base page chrome stays plain per the design system.

### Relationship Between Product Specification and Design Rules
The product specification's embedded structural instructions define **what exists and where it lives** (page list, Workspace composition: Learning Map + Main Learning Area with its four modes, the Learn → Test → Evaluate → Review flow, the Conversational vs. Structured UI split). `design-rules.md` defines **how everything is rendered** (tokens, spacing, radius, glass rules, motion, responsive rules). This document is the translation layer between the two — it does not add product decisions, and it does not add visual decisions that contradict the design system.

---

## 2. Global Layout

### Application Shell
```
AppShell
├── Sticky Navigation (top, z-index 100)
├── Page Container
│   └── Page Content (per-route)
└── Toast Layer (z-index 500)
```
- Background uses the `Background` token (`#CBD5E1`) applied to the full viewport (`min-h-[100dvh]`, never `h-screen`).
- Navigation is a sticky, minimal top bar (see Section 3) — not a persistent side rail at the app-shell level. (The Workspace introduces its own internal sidebar — Section 8 — scoped to that route only.)

### Page Container
- Max content width: **1280px**, centered, with **24px** horizontal padding on smaller viewports (per design-rules 16.1).
- Section padding: **80px** vertical between major page sections on desktop; reduced proportionally on mobile (see Section 10).
- Standard gap between related elements: **16px**. Card internal padding: **24px**.

### Desktop vs Mobile Shell Behavior
- **Desktop (≥1024px):** full-width grid layouts, asymmetric compositions where the product spec calls for them (e.g., Hero + Profile Panel side-by-side on Home).
- **Tablet (768–1023px):** grids reduce column count; secondary panels may move below primary content.
- **Mobile (<768px):** single-column stack; all panels/cards full-width; sticky nav collapses to a compact bar.

### Background & Glass Surface Usage
- Page background stays flat (`Background` token) — it is the base layer glass surfaces sit above.
- Glass is reserved for: profile panel, material cards, workspace learning map + main panel, modals, floating controls, and the active navigation indicator — per design-rules 6.4. Plain (non-glass) surfaces use the `Surface` token directly with a thin `Border`-token outline.

---

## 3. Global Components

### Navigation
- **Purpose:** Primary wayfinding between Home and My Materials (the two top-level destinations defined by the product structure).
- **Structure:** Logo/wordmark (left) · nav links (center/left-aligned) · profile/account affordance (right).
- **Variants:** Desktop horizontal bar; mobile compact bar with a condensed menu.
- **States:** Default, hover (subtle background/text shift), active/current-page (accent-colored indicator — underline or subtle glass pill per design-rules 12), focus (visible outline for keyboard users).
- **Interaction:** Instant route change; active indicator transitions with a subtle 200ms ease-out shift.
- **Responsive:** Collapses to icon/label compact bar under 768px; touch targets ≥44px.

### Buttons
- **Purpose:** Primary and secondary actions across all pages.
- **Structure:** Label (+ optional leading/trailing icon).
- **Variants:** Primary (filled, `Primary`/`Accent` token background), Secondary/Ghost (glass or transparent surface, thin border).
- **States:** Default, hover, active, focus, disabled, loading (per design-rules 9.3).
- **Interaction:** Hover = subtle elevation/color shift; loading = label replaced or accompanied by a restrained inline spinner or pulsing state, button disabled during load.
- **Responsive:** Full-width on mobile inside forms/modals; inline-width in toolbars/cards.

### Cards
- **Purpose:** Primary content-grouping unit (materials, topics, summary blocks).
- **Structure:** Optional media/icon → title → metadata (JetBrains Mono) → body/description → actions.
- **Variants:** Standard card (Surface token, thin border, restrained radius) and Glass Card (selected instances only, e.g., material cards, workspace panels).
- **States:** Default, hover (subtle elevation/border shift), focus (for keyboard-navigable cards), selected (for configuration contexts), loading (skeleton), empty.
- **Responsive:** Grid reflows from multi-column (desktop) to 1–2 columns (tablet) to single column (mobile).

### Glass Cards
- **Purpose:** Elevate primary/selected content above the base background per design-rules Section 7.
- **Structure:** Translucent surface + backdrop blur + thin border + restrained shadow + content, exactly per design-rules 6–8.
- **Variants:** Static (profile panel, learning map) and interactive (material card, selectable workspace tiles).
- **States:** Default, hover (slightly increased opacity/elevation), selected (accent-colored border), focus.
- **Responsive:** Blur/transparency values remain constant across breakpoints; layout reflows, visual treatment does not change.

### Inputs
- **Purpose:** Search, filters, session configuration fields.
- **Structure:** Visible label → input field → helper/error text (no floating labels, per design-rules 11).
- **States:** Default, focus (visible restrained ring using `Primary`/`Accent`), error (semantic error color + explanatory text, never color-only), disabled.
- **Responsive:** Full-width on mobile; fixed/max-width within desktop forms.

### Search
- **Purpose:** Locate materials within My Materials.
- **Structure:** Icon-prefixed text input, placeholder text, clear (×) affordance once populated.
- **States:** Default, focus, active (has query), empty-result (see Empty State).
- **Interaction:** Debounced filtering of the material grid/list; no full page reload.
- **Responsive:** Collapses to full-width row above the grid on mobile.

### Filters
- **Purpose:** Narrow the material list (e.g., by status/topic — exact filter fields per product data model; if unspecified, treated as Design Decision, Section 15).
- **Structure:** Filter trigger (button/chip row) → filter panel or inline chip toggles.
- **States:** Default, active/applied (accent-colored chip), disabled (no matching data).
- **Responsive:** Desktop: inline chip row or side panel. Mobile: collapses into a filter sheet/modal to preserve content width.

### Modal / Dialog
- **Purpose:** Focused, blocking tasks — primarily Study Session Configuration.
- **Structure:** Glass surface (per design-rules 6.4) → header/title → body/content → primary + secondary actions (footer).
- **States:** Entering (fade + translateY per design-rules 15.2), open, loading (primary action shows loading state), disabled (primary action disabled until validation passes), closing.
- **Interaction:** Backdrop dismiss + explicit close control; focus is trapped within the modal while open; z-index 300 per design-rules 17.
- **Responsive:** Desktop: centered, max-width panel. Mobile: full-width sheet anchored to viewport, scrollable body, sticky action footer.

### Toast / Notification
- **Purpose:** Non-blocking confirmations and errors (e.g., upload success, save failure).
- **Structure:** Icon (semantic) → short message → optional action/dismiss.
- **States:** Success, error, info — semantic color plus icon, never color-only (design-rules 18).
- **Interaction:** Auto-dismiss after a few seconds (persist for errors until dismissed); stacks vertically for multiple toasts; z-index 500.
- **Responsive:** Desktop: bottom-right or top-right stack. Mobile: full-width, bottom-anchored.

### Loading / Skeleton
- **Purpose:** Communicate in-progress content fetches without layout shift.
- **Structure:** Shape-matched placeholder blocks mirroring the eventual content (card skeletons, list-row skeletons, text-line skeletons).
- **Rule:** Prefer skeletons over spinners wherever the content shape is known (design-rules 14.1); reserve spinners for indeterminate actions (e.g., button loading state, AI-generated content streaming).

### Empty State
- **Purpose:** Communicate absence of content and the next action.
- **Structure:** Icon → short explanation → primary action, per design-rules 14.2.
- **Occurrences:** No materials uploaded yet (My Materials/Home Recent Materials), no search results, no weak topics to review, no quiz history.

### Error State
- **Purpose:** Communicate failed operations (fetch failure, upload failure, session generation failure).
- **Structure:** Icon → short explanation of what failed → retry action (where applicable).
- **Rule:** Errors are never communicated by color alone; always paired with icon + text.

### Progress Indicators
- **Purpose:** Communicate mastery/progress at material, topic, and session level.
- **Variants:** Linear progress bar (topic/subtopic mastery), circular/radial indicator (Overall Mastery in the Learning Map), stepped indicator (Learn → Test → Evaluate → Review).
- **States:** In-progress, complete, not-started — each with a non-color-only cue (icon or label), per design-rules 18.

### Badges / Status
- **Purpose:** Compact status communication (e.g., topic mastery level, material processing status).
- **Structure:** JetBrains Mono label, optionally icon-prefixed, restrained pill shape (`Pill` radius token).
- **Variants:** Neutral, success, warning, error/attention — using semantic colors introduced only as needed (design-rules 2, "Color Rules").

### Icons
- **System:** Lucide or Heroicons only — no emoji (design-rules 13).
- **Rule:** Consistent stroke width, aligned to adjacent text baseline, paired with text labels wherever meaning would otherwise be ambiguous (e.g., icon-only nav or button actions get an `aria-label`).

---

## 4. Home Page

### Page Structure (as fixed by the product specification)
```
HomePage
├── Navigation (global)
├── Hero (incl. Upload Material)
├── Profile Panel
└── Recent Materials (with View All / See More)
```
This ordering is preserved exactly; no sections are added, removed, or reordered.

### Hero
- Large `Display Large` (Inter, 64px) headline stating the core value of Studyback in plain language (no AI-copywriting clichés — design-rules 19.2).
- Supporting `Body Medium` (Playfair Display) subtext.
- **Upload Material** is the primary interaction embedded in the Hero: a prominent primary button and/or drag-and-drop glass surface. On desktop this may sit as an asymmetric two-column composition (headline/copy + upload surface); on mobile it stacks vertically, upload surface below the headline.

### Upload Material Interaction
- **Default:** dashed/bordered glass drop zone with icon + "drag a file or click to upload" label + primary button fallback.
- **Drag-over:** border/background shifts to `Primary`/`Accent` accent to confirm a valid drop target.
- **Uploading:** progress indicator (linear) replaces the drop-zone content; upload can be cancelled.
- **Success:** brief success toast + the new material appears at the top of Recent Materials.
- **Error:** inline error message in the drop zone (unsupported file type, size limit, etc.) plus an error toast; drop zone returns to default state for retry.

### Profile Panel
- Rendered as a Glass Card (per design-rules 6.4) positioned adjacent to or below the Hero depending on breakpoint.
- Contains user identity and summary study stats (exact metrics depend on the underlying data model — if not specified elsewhere, individual stat fields are logged as Design Decisions in Section 15).

### Recent Materials
- Section heading + grid/row of Material Cards (glass treatment, per design-rules 6.4) showing the most recently uploaded/accessed materials.
- **View All / See More:** a secondary/ghost button or text link at the section's end that routes to My Materials — it does not duplicate My Materials' functionality inline.

### States
- **Loading:** skeleton cards in place of Recent Materials grid; Profile Panel shows a skeleton glass block.
- **Empty:** if no materials exist yet, Recent Materials renders the Empty State pattern (icon + "no materials yet" + Upload Material as the primary action), reinforcing the Hero's own upload affordance rather than duplicating a second uploader.
- **Error:** inline Error State in the Recent Materials section with a retry action; Hero/Upload remain functional independently.

### Responsive Behavior
- **Desktop:** Hero and Profile Panel may sit in an asymmetric two-column grid; Recent Materials as a multi-column card grid below.
- **Tablet:** Profile Panel moves below the Hero; Recent Materials reduces to 2 columns.
- **Mobile:** Full vertical stack — Hero → Upload → Profile Panel → Recent Materials (single column); touch targets sized ≥44px throughout.

---

## 5. My Materials / Material Library

### Page Structure
```
MyMaterialsPage
├── Navigation (global)
├── Page Header (title + Upload Material action)
├── Search
├── Filters
└── Material Grid / List
```

### Page Header
- Page title (`Inter`, heading scale) + primary **Upload Material** button aligned to the header (same upload interaction pattern as Home's Hero — drop zone may open as a modal or inline expandable region here rather than a full Hero treatment, since this page's primary purpose is the library, not the upload moment).

### Search
- As defined in Section 3 (Global Components → Search); filters the grid/list in place.

### Filtering
- As defined in Section 3 (Global Components → Filters); combinable with search.

### Material Grid / List
- Default: card grid (glass Material Card) mirroring Home's Recent Materials visual treatment for consistency, but this page is the authoritative, complete, filterable list — Home only ever shows a recent subset.
- Optional list/grid view toggle is **not assumed** unless confirmed elsewhere — logged as a Design Decision if desired later.

### Material Card
- Structure: thumbnail/icon → title → metadata (upload date, topic count — JetBrains Mono) → status badge → primary action (Open/View).
- States: default, hover (elevation/border shift), focus, loading (skeleton), and a processing/error status badge if a material failed to process.

### Empty State
- No materials at all: icon + explanation + Upload Material as primary action (per design-rules 14.2).
- No results for current search/filter: icon + "no materials match" + a "clear filters" secondary action.

### Loading State
- Skeleton grid matching the eventual Material Card shape; header/search/filters remain interactive during load.

### Responsive Behavior
- **Desktop:** multi-column grid (3–4 columns depending on width), search + filters inline in the header row.
- **Tablet:** 2-column grid; filters may collapse into a single "Filters" trigger.
- **Mobile:** single-column list/stack; search full-width; filters open as a bottom sheet/modal (per Section 3 Filters).

This page's sole responsibility is discovery and management of materials — it never renders topics, learning content, or session UI inline (that belongs to Material Detail and the Workspace respectively).

---

## 6. Material Detail

### Page Structure
```
MaterialDetailPage
├── Navigation (global)
├── Material Information
├── Topics (overview list)
├── Learning Progress (summary)
└── Actions: Download Material · Start Study Session
```

### Material Information
- Title, metadata (upload date, file type/size — JetBrains Mono labels), and any description/summary content (Playfair Display body).

### Topics
- A read-only overview list of the material's topics/subtopics (names + at-a-glance mastery badge per topic). This is a **summary view**, not an interactive learning surface — clicking a topic does not open Teach Me/Quiz Me here; deep engagement happens only after **Start Study Session** → Workspace.

### Learning Progress
- Summary progress indicator (e.g., overall mastery bar/ring for this material), consistent visual language with the Workspace's Overall Mastery indicator (Section 8) so users recognize the metric across pages, but rendered here as a static summary, not the interactive Learning Map.

### Actions
- **Download Material:** secondary/ghost button; triggers a file download; shows a brief loading state on the button while preparing the file if needed.
- **Start Study Session:** primary button; opens the **Study Session Configuration modal** (Section 7) — it does not navigate directly into the Workspace without configuration.

### States
- **Loading:** skeleton for Material Information, Topics list, and Progress summary.
- **Error:** Error State if the material fails to load, with retry.

### Responsive Layout
- **Desktop:** two-column layout — Material Information + Actions in one column, Topics + Learning Progress in the other (or stacked in a single wide column if content is light — exact split logged as Design Decision if not further specified).
- **Tablet/Mobile:** single-column stack in the order: Material Information → Learning Progress → Topics → Actions (actions remain easily reachable, e.g., sticky action bar on mobile).

Material Detail strictly remains an informational + launch page. It never becomes a learning workspace.

---

## 7. Study Session Configuration

Rendered as a **Modal/Dialog** (per Section 3 Global Components → Modal/Dialog and product spec Section 7), launched from Material Detail's "Start Study Session."

### Structure
```
StudySessionConfigModal
├── Header (title + close control)
├── Topics Selection
├── Learning Mode Selection
├── Difficulty Selection
├── Validation / helper text
└── Footer: Secondary (Cancel) · Primary (Start Session)
```

### Topics Selection
- Multi-select list/grid of the material's topics (from Material Detail), each togglable.
- **Selected state:** accent-colored border/fill + check indicator (not color-only — icon confirms selection).
- **Unselected state:** default card/chip styling.
- At least one topic must be selected before the primary action activates.

### Learning Mode
- Selection control (radio/segmented control or selectable cards) among the modes defined by the product specification's Workspace structure: **Teach Me, Quiz Me, Review Weak Topics, Guided Study Session**.
- Only one mode is active per session start; selected state mirrors Topics Selection's visual treatment for consistency.

### Difficulty
- Selection control (e.g., segmented control) for difficulty level. Exact levels/labels are not defined in the source files — treated as a Design Decision (Section 15) if the underlying product data doesn't specify them elsewhere.

### Validation
- Primary action ("Start Session") stays disabled until minimum required selections are made (≥1 topic, 1 mode, 1 difficulty).
- Inline helper/error text explains what's missing, next to the relevant control — never color-only.

### Primary / Secondary Actions
- Primary: "Start Session" — filled `Primary` button; on submit shows the button loading state while the Workspace is prepared, then the modal closes and the user is routed into the Studyback Workspace.
- Secondary: "Cancel" — ghost/secondary button, closes the modal without action.

### Loading
- Button-level loading state on "Start Session" (per design-rules 9.3); modal remains open and non-dismissable during this brief transition to avoid a broken navigation state.

### Responsive Behavior
- **Desktop:** centered modal, fixed max-width, content scrolls internally if topic count is large.
- **Mobile:** full-width bottom sheet; footer actions become a sticky bar at the bottom of the viewport so they remain reachable while scrolling topic selection.

---

## 8. Studyback Workspace

This is the primary learning surface and the most important part of this specification.

### Workspace Shell
```
StudybackWorkspace
├── Learning Map / Sidebar
│   ├── Overall Mastery
│   ├── Topics
│   ├── Subtopics
│   ├── Progress
│   └── Learning Status
│
└── Main Learning Area
    ├── Teach Me
    ├── Quiz Me
    ├── Review Weak Topics
    └── Guided Study Session
```
This two-region composition is fixed by the product specification and is not altered.

### Sidebar / Learning Map
- Rendered as a persistent Glass panel (per design-rules 6.4) — desktop: fixed-width left column; docked and scrollable independently of the Main Learning Area.
- **Overall Mastery:** a radial/circular progress indicator at the top of the sidebar, summarizing mastery across the whole material.
- **Topics / Subtopics:** a nested, collapsible list — topics expand to reveal subtopics. Each item shows a compact per-item progress indicator (small linear bar or dot/badge) and a **Learning Status** badge (e.g., not-started / in-progress / mastered — using icon + label, not color alone).
- **Navigation within the map:** selecting a topic/subtopic highlights it (accent border/background) and can scope the Main Learning Area's active mode to that topic where applicable (e.g., focus Teach Me on a specific subtopic).

### Topic/Subtopic Navigation
- Default: current in-focus topic/subtopic is visually indicated (selected state) in the map.
- Hover: subtle background shift on hoverable rows (desktop only).
- Keyboard: fully navigable via arrow keys/tab, with visible focus rings.

### Mastery / Progress Visualization
- Consistent visual language across: Overall Mastery (radial), per-topic/subtopic progress (linear/compact), and session-level progress (stepped indicator — see Learn → Test → Evaluate → Review below). Progress states always pair color with a label or icon.

### Main Learning Area
The Main Learning Area renders exactly one active mode at a time (the mode selected during Study Session Configuration, or switched by the user if the product allows in-session mode switching — if not explicitly defined, switching-mid-session is logged as a Design Decision).

#### Teach Me UI — Conversational
- Structure: chat-style vertical thread — system/AI explanation bubbles interleaved with optional user prompts/questions, growing downward with the newest content in view.
- Bubbles use `Body Medium` (Playfair Display) for readability; AI turns may include structured inline elements (e.g., short lists, key-term badges) without breaking the conversational flow.
- Input area docked at the bottom (question/response field) — consistent with a chat pattern, not a form pattern.
- Loading: a typing/generating indicator (restrained, non-spinner where possible — e.g., animated ellipsis) while content streams in.

#### Quiz Me UI — Structured
- Structure: one question at a time (or a paginated set), each in a clearly bounded Card: question stem → answer options (multiple choice / structured input) → submit action.
- This is explicitly **not** a chat thread — options render as distinct selectable Cards/rows with clear default/selected/correct/incorrect states.
- Feedback: on submit, correct/incorrect states are shown via icon + color + brief explanatory text (never color-only), followed by a "Next" action.
- Progress within the quiz is shown as a stepped/linear indicator (e.g., "Question 3 of 10") at the top of the panel.

#### Review Weak Topics UI — Conversational
- Same conversational pattern as Teach Me (chat thread, bottom input), but scoped specifically to topics/subtopics flagged as weak in the Learning Map, and framed around targeted review rather than first-pass teaching. Entry point may be a short summary card ("You're reviewing: [topics]") above the thread.

#### Guided Study Session UI
- Structure: an orchestrated sequence that moves the user through the **Learn → Test → Evaluate → Review** flow within the Main Learning Area, using the Teach Me (conversational) and Quiz Me (structured) patterns as its building blocks at each relevant stage, plus an Evaluate step summarizing results before a final Review step.
- A stepped progress indicator at the top of the Main Learning Area shows current stage: `Learn → Test → Evaluate → Review`, each stage's icon/label updating to a completed state as the user advances.

### Learn → Test → Evaluate → Review Flow
```
Learn        Test         Evaluate         Review
(Teach Me)   (Quiz Me)    (results/        (Review Weak
             structured    scoring          Topics /
             UI)           summary)         summary)
```
- Rendered as a horizontal stepped indicator (desktop) / condensed stepped indicator (mobile) pinned above the Main Learning Area during a Guided Study Session.
- Each stage transition uses the standard state-transition motion (subtle fade/slide, design-rules 15) — never an abrupt cut.

### Active / Selected States
- Learning Map items: selected (accent border/background), default, hover, focus, disabled (e.g., locked/future topics if the product gates progression — logged as Design Decision if not defined).
- Main Learning Area mode indicator (if mode is switchable): active mode clearly marked distinct from inactive modes.

### Loading
- Learning Map: skeleton rows on initial workspace load.
- Main Learning Area: skeleton or conversational "generating…" indicator depending on mode (structured skeleton for Quiz Me question cards; typing indicator for Teach Me/Review Weak Topics).

### Empty
- A topic with no subtopics yet, or a material with no weak topics to review ("Review Weak Topics" entry state: icon + "no weak topics — nice work" + suggestion to try Quiz Me instead).

### Error
- Failed content generation (e.g., AI response fails): inline Error State within the Main Learning Area with a retry action; Learning Map remains usable independently.

### Completion State
- On finishing a mode or the full Guided flow: a completion summary Card (glass) — mastery delta, topics covered, and clear next actions (e.g., "Review Weak Topics," "Back to Material," "Continue to next topic").

### Responsive / Mobile Behavior
- **Desktop:** Learning Map as a persistent left sidebar (fixed width) + Main Learning Area filling remaining width.
- **Tablet:** Learning Map may collapse to a collapsible drawer (toggled via a visible control) to give the Main Learning Area more width, reopening on demand.
- **Mobile:** Learning Map becomes a top collapsible panel or a slide-over drawer accessed via a dedicated control (e.g., "Map" button); Main Learning Area takes full width by default so the active learning mode (chat thread or quiz card) is never cramped. Stepped progress indicator condenses to a compact horizontal bar with the current stage label only.

---

## 9. User Interaction & State Design

Only relevant states are documented per component — not every component uses every state.

| Component | Default | Hover | Focus | Active | Selected | Disabled | Loading | Success | Error | Empty | Completed |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Navigation item | ✓ | ✓ | ✓ | ✓ | ✓ (current page) | — | — | — | — | — | — |
| Button (Primary/Secondary) | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ | — | — | — | — |
| Input | ✓ | — | ✓ | — | — | ✓ | — | — | ✓ | — | — |
| Material Card | ✓ | ✓ | ✓ | — | — | — | ✓ | — | ✓ | — | — |
| Upload Drop Zone | ✓ | ✓ (drag-over) | ✓ | — | — | — | ✓ | ✓ | ✓ | — | — |
| Topic Selection (config modal) | ✓ | ✓ | ✓ | — | ✓ | ✓ | — | — | — | — | — |
| Learning Map item | ✓ | ✓ | ✓ | — | ✓ | ✓ | ✓ | — | — | — | ✓ |
| Quiz Answer Option | ✓ | ✓ | ✓ | — | ✓ | ✓ | — | ✓ (correct) | ✓ (incorrect) | — | — |
| Modal Primary Action | ✓ | ✓ | ✓ | ✓ | — | ✓ | ✓ | — | — | — | — |
| Recent Materials / Grid | — | — | — | — | — | — | ✓ | — | ✓ | ✓ | — |
| Guided Session Stepper | ✓ | — | — | ✓ (current stage) | — | ✓ (future stage) | — | — | — | — | ✓ (past stages) |
| Toast | — | — | — | — | — | — | — | ✓ | ✓ | — | — |

---

## 10. Responsive Design

### Desktop (≥1024px)
- CSS Grid layouts; asymmetric compositions where the page structure calls for it (Home Hero/Profile, Material Detail's two-column split).
- Studyback Workspace sidebar is persistent and fixed-width.
- Content max-width 1280px, 24px side padding, 80px section padding.

### Tablet (768–1023px)
- Grids reduce to 2 columns (Material grids); asymmetric layouts (Home, Material Detail) collapse to a single primary column with secondary content following beneath.
- Workspace sidebar becomes a collapsible drawer, default open but toggleable.
- Filters may collapse to a single trigger + panel.

### Mobile (<768px)
- Full vertical stacking across all pages; no horizontal overflow (design-rules 16.2).
- Navigation collapses to a compact bar; primary actions (Upload, Start Study Session) remain immediately visible/reachable, not buried in menus.
- Workspace: Learning Map becomes an on-demand drawer/panel; Main Learning Area takes full width.
- Modals become full-width bottom sheets with sticky action footers.
- Touch targets ≥44px throughout (nav items, buttons, quiz answer options, map rows).
- Content priority on mobile: primary action/content first, secondary metadata and supplementary panels follow below or are tucked into drawers.

No new breakpoints are introduced beyond the 768px / 1024px pattern implied by design-rules Section 16.

---

## 11. Motion & Micro-interactions

All motion follows design-rules Section 15: `ease-out`, 200–300ms for interactions, 420ms for entry, ~80ms stagger for lists. Animate `transform`/`opacity` only.

- **Page entry:** content fades/translates in (opacity 0→1, translateY 16px→0) on route load — applied to Home sections, My Materials grid (staggered ~80ms per card), Material Detail blocks.
- **Card hover:** subtle elevation/border shift on Material Cards and Learning Map items (200ms).
- **Modal opening:** Study Session Configuration modal uses the standard entry animation plus backdrop fade; closing reverses it.
- **Loading:** skeleton shimmer (subtle, restrained) instead of spinners wherever content shape is known; button loading uses a small inline indicator.
- **Progress:** mastery bars/rings animate their fill on value change (short duration, ease-out) rather than jumping instantly.
- **State transition:** Quiz Me answer feedback (correct/incorrect) uses a quick, restrained color/icon transition — no bounce or exaggerated motion, to avoid disrupting focus.
- **Workspace transitions:** switching Learning Map selection or Main Learning Area mode uses a simple cross-fade (per design-rules 15.4 page-transition pattern) rather than a hard cut; Guided Session stepper advances with a subtle highlight transition on the newly active stage.

Motion is intentionally restrained throughout — nothing exceeds ~420ms, and nothing distracts from reading/answering content.

---

## 12. Accessibility

- **Color contrast:** all text/background pairings maintain sufficient contrast per the fixed color tokens; glass surfaces are checked to ensure blur/transparency never drops text below readable contrast (design-rules 18, 6.3).
- **Keyboard navigation:** all interactive elements (nav, buttons, cards, Learning Map rows, quiz options, modal controls) are reachable and operable via keyboard, in logical tab order.
- **Focus states:** every focusable element has a visible, restrained focus indicator (per design-rules 9.3, 11.1) — never suppressed.
- **Semantic structure:** proper heading hierarchy per page (single h1 per page, nested headings for sections), semantic list markup for Topics/Subtopics and grids, `<button>`/`<a>` used per their actual behavior.
- **Form labels:** all inputs (search, filters, configuration fields) have visible, associated labels — no placeholder-only labeling.
- **Button labels:** icon-only buttons (e.g., close, drawer toggle) include descriptive `aria-label`s.
- **Screen-reader considerations:** live regions for streaming Teach Me/Review content and toast notifications; progress indicators expose current value/state via ARIA (e.g., `aria-valuenow` on mastery bars).
- **Non-color-only status:** every status communication (mastery level, quiz correctness, upload success/error, filter active state) pairs color with an icon and/or text label.
- **Touch targets:** minimum 44px on all interactive elements at mobile breakpoints.
- Glassmorphism is applied only where contrast and legibility are validated to remain intact, per design-rules Section 18's explicit requirement.

---

## 13. Component Hierarchy

### Home
```
HomePage
├── Navigation
├── HeroSection
│   └── UploadMaterial
├── ProfilePanel
└── RecentMaterials
    ├── MaterialCard (×n)
    └── ViewAllLink
```

### My Materials
```
MyMaterialsPage
├── Navigation
├── PageHeader
│   └── UploadMaterialAction
├── SearchBar
├── FilterBar
└── MaterialGrid
    └── MaterialCard (×n)
```

### Material Detail
```
MaterialDetailPage
├── Navigation
├── MaterialInformation
├── TopicsOverview
│   └── TopicSummaryRow (×n)
├── LearningProgressSummary
└── ActionsBar
    ├── DownloadMaterialButton
    └── StartStudySessionButton
```

### Study Session Configuration
```
StudySessionConfigModal
├── ModalHeader
├── TopicsSelection
│   └── TopicSelectableCard (×n)
├── LearningModeSelection
│   └── ModeOption (×4: TeachMe, QuizMe, ReviewWeakTopics, GuidedStudySession)
├── DifficultySelection
├── ValidationMessage
└── ModalFooter
    ├── CancelButton
    └── StartSessionButton
```

### Studyback Workspace
```
StudybackWorkspace
├── WorkspaceShell
├── LearningMap (Sidebar)
│   ├── OverallMasteryIndicator
│   ├── TopicList
│   │   └── SubtopicList
│   │       └── SubtopicRow (progress + status)
├── MainLearningArea
│   ├── GuidedSessionStepper (conditional: Guided Study Session)
│   ├── TeachMePanel (ConversationalUI)
│   ├── QuizMePanel (StructuredUI)
│   ├── ReviewWeakTopicsPanel (ConversationalUI)
│   └── CompletionSummaryCard
```

---

## 14. UI → Product Flow Mapping

| User Flow | UI Components | Result |
|---|---|---|
| Upload Material | Hero UploadMaterial (Home) / PageHeader UploadMaterialAction (My Materials), Drop Zone, Progress Indicator, Toast | Material appears in Recent Materials and My Materials grid |
| Open Material | Material Card (Recent Materials or My Materials grid) | Navigates to Material Detail |
| View Material Detail | MaterialInformation, TopicsOverview, LearningProgressSummary | User reviews material context before studying |
| Start Study Session | StartStudySessionButton on Material Detail | Opens Study Session Configuration modal |
| Configure Session | TopicsSelection, LearningModeSelection, DifficultySelection, ValidationMessage | Produces a validated session configuration |
| Enter Workspace | StartSessionButton (modal, loading state) | Routes into Studyback Workspace with Learning Map + selected Main Learning Area mode |
| Teach Me | TeachMePanel (chat thread + input) | Conversational explanation of selected topics; mastery/progress updates in Learning Map |
| Quiz Me | QuizMePanel (question cards + options + feedback) | Structured assessment; scores update mastery/progress in Learning Map |
| Review Weak Topics | ReviewWeakTopicsPanel (chat thread scoped to weak topics) | Targeted reinforcement of flagged subtopics |
| Complete Study Session | CompletionSummaryCard, Guided Session Stepper (Review stage) | Session summary shown; Learning Map mastery/status updated; user routed to next action (review, next topic, or back to Material Detail) |

---

## 15. Design Decisions

Items below are not explicitly defined in either `01-product-specification.md` (which is structural/instructional in nature and does not specify granular product data) or `design-rules.md`. They are treated as open implementation decisions, not product requirements, and should be confirmed against the actual product data model when available.

| Decision | Reason |
|---|---|
| Exact Profile Panel stat fields (e.g., streak, total materials, total study time) | Not specified in either source file; a reasonable glass-panel summary is assumed pending confirmation of the real data model |
| Exact filter fields on My Materials (e.g., by topic, by status, by date) | Filtering is required by the product structure's section list, but the filterable attributes themselves are not enumerated in the source files |
| Grid vs. list view toggle on My Materials | Not mentioned in either source file; default is a card grid consistent with Home's Recent Materials |
| Difficulty level labels/count in Study Session Configuration | Product spec instructs a "Difficulty" control must exist but does not enumerate levels |
| Whether Main Learning Area mode is switchable mid-session vs. fixed at configuration time | Not addressed in the source files; documented both as configured-at-start (default assumption) and as a possible future toggle |
| Whether Learning Map topics can be "locked" until prerequisites are met | Not specified; disabled state is documented as available but not confirmed as required product behavior |
| Desktop two-column split ratio on Material Detail | Not specified; a content-driven split is assumed, to be finalized against real content volume |
| Semantic color hex values for success/warning/error | design-rules explicitly permits introducing these "only when required by UI state," but does not provide exact values — implementers should derive restrained, accessible values consistent with the existing token contrast rules rather than importing external palette colors |

No existing product decision from the source files has been altered, removed, or reinterpreted to produce this list — these are exclusively additions needed to make the specification implementation-ready.

---

## 16. Final UI/UX Checklist

- [x] All pages from the Product Specification are covered (Home, My Materials, Material Detail, Study Session Configuration, Studyback Workspace).
- [x] Layout structure is unchanged from the source file's instructions.
- [x] Home layout (Hero → Profile Panel → Recent Materials) is preserved.
- [x] Material Library (My Materials) stays focused on discovery/management only.
- [x] Material Detail remains separate from the Workspace.
- [x] Study Session Configuration uses a modal/dialog.
- [x] Studyback Workspace is documented as the central learning experience.
- [x] Learning Map / Sidebar is fully documented (Overall Mastery, Topics, Subtopics, Progress, Learning Status).
- [x] Teach Me uses Conversational UI.
- [x] Quiz Me uses Structured UI.
- [x] Learn → Test → Evaluate → Review flow is documented.
- [x] design-rules.md is used as the visual source of truth throughout.
- [x] Glassmorphism does not introduce a new palette — only design-system tokens are used.
- [x] Responsive behavior is documented for desktop, tablet, and mobile.
- [x] Accessibility is documented.
- [x] Component hierarchy is provided for every page.
- [x] No feature or page was added without a basis in the product structure; all undefined details are isolated in Section 15.
