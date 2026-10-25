# PocketCat V16.4 — CMS Editor Responsive & Functional Fix

## Screenshot-driven QA findings

- **Meta-box layout** → headings and controls were being squeezed into a horizontal flex row → fixed the drag/collapse header creation so only the header is flex; Categories, Tags, Featured Image, Settings, Discussion and Author retain normal block layout.
- **Mobile/narrow editor layout** → the CMS editor could retain two columns at narrow desktop-site/mobile CSS widths → added a stronger V16.4 responsive breakpoint so the editor becomes a single column before controls become cramped.
- **Taxonomy tools** → search/add controls could overlap when the side panel became narrow → constrained inputs/buttons and added a stable two-column tool row.
- **Meta-box headings** → long headings could wrap one character per line → normalized heading wrapping and prevented pathological word breaking.
- **Meta-box collapse** → collapse button was attached to the entire card for cards without `.meta-head`, which caused the visible screenshot corruption → now a dedicated header wrapper is created around the `<h3>`.
- **Meta-box persistence** → collapse state used positional localStorage keys, which became unreliable after drag reordering → V16.4 stores collapse state by stable section name.
- **Move to Trash** → visible button had no client-side submit action → wired confirmation + `save_action=trash` submission.
- **Saved settings** → Visibility, Layout, Content Style and Vertical Padding did not restore their saved values in the editor UI → added correct selected-state rendering.
- **Asset caching** → CMS CSS could remain on an older cached version → bumped shared CSS cache references to `16.4`.

## Validation

- PHP syntax: **177/177 files passed**.
- `cms-editor.js`: **passed `node --check`**.
- `code-editor.js`: **passed `node --check`**.
- Deployment ZIP integrity: **passed `unzip -t`**.

## Backwards compatibility

No database schema changes were introduced. Existing CMS post fields, taxonomy storage, revisions and editor endpoints remain unchanged.
