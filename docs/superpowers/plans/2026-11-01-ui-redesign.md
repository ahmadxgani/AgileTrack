# UI Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Laravel starter-kit look (welcome page, logo, grey theme) with an AgileTrack brand and a modern, consistent interface across the landing, auth and app surfaces.

**Architecture:** Keep the existing Inertia + React + Tailwind 4 + shadcn-style component stack. Brand assets are one inline SVG component plus static favicons; the theme is a token change in `resources/css/app.css`; page work is confined to `resources/js` (layouts, shell components, welcome and dashboard pages). No backend behaviour changes except the default application name.

**Tech Stack:** Laravel 13, Inertia 3, React 19, TypeScript, Tailwind 4, lucide-react, Plus Jakarta Sans via Bunny Fonts (laravel-vite-plugin).

**Spec:** Brainstorming was waived by the user ("make your own calls, modern, compelling, big UI rewrite"). The calls below stand in for a spec.

## Global Constraints

- Application name is "AgileTrack" everywhere a default is used (`config/app.php`, `.env.example`, `resources/js/app.tsx`, `resources/views/app.blade.php`).
- The mark is original (no Laravel artwork remains in the repository: `welcome.tsx`, `app-logo-icon.tsx`, `public/favicon.svg`, `public/favicon.ico`, `public/apple-touch-icon.png`).
- No links to Laravel docs, Laracasts, Laravel Cloud or the starter-kit repository remain in the UI.
- Light and dark mode both supported; theme tokens stay in oklch.
- All existing feature tests, PHPStan, Pint, `npm run check` and `tsc` keep passing (`composer ci:check`).
- One commit per task, following the repository's commit rules.

## Brand decisions

- Mark: an "A" whose crossbar extends into a forward arrow. Stroke-based, 32x32 viewBox, `currentColor` with an optional accent class on the arrow.
- Palette: teal primary (`oklch(0.52 0.11 185)` light / lighter in dark), amber accent for highlights, near-black slate foreground, soft off-white background.
- Type: Plus Jakarta Sans 400/500/600/700/800.

## Review Focus

- Dark mode: every new surface (landing, auth panel, sidebar tile, stat cards) must remain readable with the `.dark` tokens; check with the appearance setting.
- Narrow viewports: landing hero and feature grid stack; auth split layout collapses to a single column; dashboard cards wrap.
- Signed-in visitor on the landing page sees "Open dashboard" instead of "Log in".
- A user with zero projects still gets a sensible dashboard (empty state, zero totals).
- Collapsed sidebar: the logo tile stays visible and the wordmark hides.

---

### Task 1: Brand assets and application name

**Files:**
- Modify: `resources/js/components/app-logo-icon.tsx`, `resources/js/components/app-logo.tsx`
- Modify: `config/app.php`, `.env.example`, `resources/js/app.tsx`, `resources/views/app.blade.php`
- Replace: `public/favicon.svg`, `public/favicon.ico`, `public/apple-touch-icon.png`

- [ ] Write the new `AppLogoIcon` (stroke mark) and `AppLogo` (tile + wordmark).
- [ ] Render favicons from the tile SVG with `rsvg-convert` and ImageMagick.
- [ ] Change the default app name to AgileTrack.
- [ ] Run `composer ci:check`; commit.

### Task 2: Theme and typography

**Files:**
- Modify: `resources/css/app.css`, `vite.config.ts`, `resources/views/app.blade.php`, `resources/js/app.tsx`

- [ ] Replace the grey token set with the teal/amber palette (light and dark), add `--font-display`.
- [ ] Load Plus Jakarta Sans through the Bunny fonts plugin; drop Instrument Sans.
- [ ] Match the inline html background colours and the Inertia progress colour.
- [ ] Run `composer ci:check`; commit.

### Task 3: Landing page

**Files:**
- Rewrite: `resources/js/pages/welcome.tsx`
- Create: `resources/js/components/marketing/*.tsx` as needed

- [ ] Hero with brand, headline, CTA (Log in / Open dashboard), product preview.
- [ ] Feature grid (projects, backlog, sprints & Kanban, reviews), workflow strip, footer.
- [ ] Run `composer ci:check`; commit.

### Task 4: Auth layout

**Files:**
- Modify: `resources/js/layouts/auth-layout.tsx`, `resources/js/layouts/auth/auth-split-layout.tsx`
- Remove: `resources/js/layouts/auth/auth-card-layout.tsx`, `resources/js/layouts/auth/auth-simple-layout.tsx` if unused

- [ ] Split layout: brand panel with value proposition on the left, form on the right.
- [ ] Run `composer ci:check`; commit.

### Task 5: App shell

**Files:**
- Modify: `resources/js/components/app-sidebar.tsx`, `nav-main.tsx`, `app-sidebar-header.tsx`, `heading.tsx`, `nav-footer.tsx` (or remove), `resources/js/layouts/settings/layout.tsx`

- [ ] Sidebar: brand header, "Workspace" group label, settings link in the footer area, no external starter-kit links.
- [ ] Header: compact top bar with breadcrumbs and user menu.
- [ ] `Heading`: display font, consistent spacing.
- [ ] Run `composer ci:check`; commit.

### Task 6: Dashboard

**Files:**
- Rewrite: `resources/js/pages/dashboard.tsx`
- Create: `resources/js/components/dashboard/*.tsx`

- [ ] Summary cards (projects, open work, in progress, done) computed from the existing props.
- [ ] Project cards with status, active sprint, work distribution bar and next review.
- [ ] Run `composer ci:check`; commit.
