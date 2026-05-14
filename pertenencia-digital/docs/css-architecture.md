# CSS Architecture Notes

This theme currently ships with a large `style.css`, but the intended direction is a layered structure that stays compatible with WordPress block themes and `theme.json`.

## Target layer order

1. `assets/css/fonts.css`
   Shared font loading for frontend and editor.
2. `assets/css/tokens.css`
   Theme-level custom properties only.
3. `assets/css/layout-shell.css`
   Constrained widths, full-width behavior, editorial shells and shared layout wrappers.
4. `assets/css/components-base.css`
   Small reusable pieces such as legal links, editorial listing cards and eyebrow text.
5. `assets/css/service-components.css`
   Reusable service cards, comparison panels and editorial route clusters.
6. `assets/css/navigation.css`
   Site navigation, account access and music subnavigation.
7. `assets/css/header.css`
   Header shells and masthead variants.
8. `style.css`
   Remaining legacy/theme-specific rules while the refactor is in progress.
9. Future split files
   Continue extracting in this order:
   - `base.css`
   - `components/*.css`
   - `blocks/*.css`
   - `features/*.css`
   - `utilities.css`
   - `overrides.css`

## Naming direction

- Keep the `pd-` namespace for theme-owned classes.
- Prefer English for system-level primitives and components:
  - `pd-shell`
  - `pd-surface`
  - `pd-stack`
  - `pd-cluster`
  - `pd-card`
- Keep Spanish only when the class is tightly tied to editorial/content vocabulary already visible to editors.
- Reserve modifiers for real variants with CSS support:
  - `pd-editorial-shell--music`
  - `pd-card--feature`
- Reserve state classes for behavior:
  - `is-open`
  - `is-active`
  - `is-current`
  - `is-restricted`

## Token hierarchy

Use this precedence:

1. `theme.json`
   Source of truth for palette, spacing scale, typography scale, radii and shared surfaces.
2. `assets/css/tokens.css`
   Maps `theme.json` values into theme-friendly custom properties.
3. Block or instance-level custom properties
   Only when a dynamic block truly needs per-instance overrides.

## What should live where

- `theme.json`
  - palette
  - spacing scale
  - typography scale
  - radii
  - shadows
  - shared surface tokens
  - base element styles
- `tokens.css`
  - `--pd-*` aliases and resolved tokens
- `style.css` or future split CSS files
  - layout objects
  - reusable components
  - component states
  - block wrappers
- current extracted files
  - `layout-shell.css` owns `pd-editorial-*` and shared constrained/full-width layout rules
  - `service-components.css` owns `pd-service-*`
  - `navigation.css` owns `pd-site-navigation*`, `pd-account-*` and `pd-music-subnav*`
  - `header.css` owns `pd-header-*`
  - `components-base.css` owns `pd-legal-links`, `pd-editorial-card*`, `pd-editorial-empty`, `pd-eyebrow`
- template-specific CSS
  - only when a template has unique structure that should not become a reusable component

## First migration rules

- Move tokens first, not components.
- Extract the most reused primitives before page-specific styling.
- Do not rename template classes and dynamic block wrappers in the same pass.
- Avoid mixing editor-only styling with frontend component styling in the same file.

## Interactive Dynamic Blocks

Template shells must stay passive around dynamic, interactive blocks. A template
wrapper can set width, margin, padding or theme tokens for its direct child, but
it must not rewrite deep descendants owned by the block.

For the rehearsal planner specifically, `.pd-rehearsal-page-content` exists only
to place `wp-song-study/current-rehearsals` inside `templates/ensayos.html`.
The previous `.pd-rehearsal-page-shell` wrapper must not be reintroduced. Do not
add broad descendant selectors such as `.pd-rehearsal-page-content *`, layout
overrides for `.pd-rehearsal-grid`, or scroll/visibility rules under the page
container. Responsive exceptions are not exempt: mobile stacking and local
overflow rules must target the specific planner component that owns that
behavior.
Avoid paint/load shortcuts in this planner. Do not use `content-visibility` on
rehearsal cards or calendar containers, and do not stop document loading from
frontend JavaScript. Those optimizations are fragile with hidden panels and
collaborator-specific content.

See `docs/rehearsal-planner-integration.md` for the incident notes and the
diagnostic matrix used to verify this boundary.
