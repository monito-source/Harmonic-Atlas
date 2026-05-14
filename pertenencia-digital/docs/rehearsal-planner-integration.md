# Rehearsal Planner Integration

Date: 2026-05-14

This note documents the production hang on `/musica/ensayos/` after the theme
scroll/style audit.

## Symptom

- Admin users could load the page.
- Non-admin collaborators received HTTP 200 and the server render completed, but
  the browser stayed visually stuck on the full theme template.
- The isolated/block-only rehearsal view worked.

## Root Cause

The failure was a theme integration issue, not Google Drive/OAuth and not missing
`node_modules`.

The `templates/ensayos.html` template renders:

1. the music header,
2. the editorial rehearsal shell,
3. `.pd-rehearsal-page-content`,
4. `pertenencia-digital/music-access-gate`,
5. `wp-song-study/current-rehearsals`.

The recent CSS audit made `.pd-rehearsal-page-shell` too invasive. It applied
deep layout rules to the interactive planner tree, including universal descendant
box sizing and layout constraints for internal planner classes. The block-only
view did not have that template wrapper, so it kept working.

## Fix

- `pd_render_block_music_access_gate()` now returns the rehearsal planner content
  directly for authorized users instead of wrapping it in the access-gate card.
- The old `.pd-rehearsal-page-shell` wrapper was removed from the production
  template. It was replaced by `.pd-rehearsal-page-content`, a passive placement
  container that only controls its own width and the direct `.pd-rehearsal-block`
  child.
- The planner keeps ownership of its internal layout, tabs, grids, scrolling,
  visibility and responsive behavior.

## Guardrails

- Do not add `.pd-rehearsal-page-shell *` or `.pd-rehearsal-page-shell *::...`.
- Do not use `.pd-rehearsal-page-content` or `.pd-rehearsal-page-shell` to target
  internal planner layout classes such as `.pd-rehearsal-grid`,
  `.pd-rehearsal-section`, `.pd-rehearsal-panel`, `.pd-rehearsal-member-editor`
  or `.pd-rehearsal-calendar-*`.
- Do not add broad `.pd-rehearsal-block :where(...)` overrides as a wrapper
  workaround. Use the specific component class that owns the behavior.
- Keep the access gate as a permission boundary. For authorized rehearsal users,
  it should be a passthrough, not a visual wrapper.
- Mobile responsive rules must live on specific planner components, not on the
  page placement container. Wide widgets, such as the monthly calendar, must
  expose their own local horizontal scroll area.
- Do not use `content-visibility` inside the rehearsal planner. The planner has
  tabbed and dynamically hidden panels; browser paint optimizations can leave a
  collaborator view visually stuck even when the server returned HTTP 200.
- Do not use `window.stop()` as a production workaround. Load diagnostics may
  log a pending page state, but they must never abort document loading for real
  users.

## Mobile Interaction Contract

- The rehearsal planner can use native `<details>` panels for mobile/tablet
  density, but the markup and state handling must belong to
  `wp-song-study-blocks`, not to the theme template wrapper.
- The theme may style `.pd-rehearsal-collapsible*` component classes because
  those classes are owned by the planner. It must not style them through
  `.pd-rehearsal-page-content` or another page-level descendant selector.
- Suggested rehearsal windows are grouped by day in PHP before rendering. Keep
  the day grouping server-side so no collaborator depends on client-side JS to
  understand or submit a proposed time slot.

## Regression Checks

After changing rehearsal template, gate or CSS rules, test with a collaborator
account, not only an admin account:

1. `/musica/ensayos/`
2. `/musica/ensayos/?wpssb_rehearsal_diag=theme-template-no-js`
3. `/musica/ensayos/?wpssb_rehearsal_diag=theme-template-no-page-shell`
4. `/musica/ensayos/?wpssb_rehearsal_diag=block-only-page-shell-no-js`
5. `/musica/ensayos/?wpssb_rehearsal_diag=block-only-no-js`

Expected result: all modes render. If only `block-only-*` works, the issue is in
the theme template boundary. If `theme-template-no-css` works but
`theme-template-no-js` fails, the issue is CSS.
