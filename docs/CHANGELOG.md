# Changelog

## r2.2.8 - 2026-10-30

- Added saved Page Generation options to preserve original Flip Box back titles and expand partial repeat rows to full width (two cards at 50% each, one at 100%). Existing behavior remains the default; jobs retain the choices made when queued. Added full-generation and shared-settings regressions.

- Updated the plugin header, runtime version, and current documentation to r2.2.8.

See [`RELEASE-r2.2.8.md`](RELEASE-r2.2.8.md) for defaults, upgrade behavior, and verification.

## r2.2.7 - 2026-10-30

- Fixed collapsed/overlapping repeat columns at limits such as 3, 6, and 7. Generated `_column_size` values now stay integer-valued for Elementor's column classes, while `_inline_size` retains the precise percentage. Fixed legacy partial-row centering discarding its updated width settings. Added regressions against the supplied repeat-column template, preserving card order, images/styles, and centered partial rows.
- Added **Cancel Queue** beside Process All Queue Now. It clears waiting jobs, stops the current dashboard's manual batch loop, and lets an already-running job finish without deleting generated pages. Cancellation is capability- and nonce-protected, logged, and covered by worker/UI regression checks. The worker refreshes its option cache between jobs to observe cancellations made by another request.
- Replaced repeated manual queue clicks with **Process All Queue Now**. The dashboard requests bounded batches until the queue is empty, shows handled/remaining/failed counts, waits for an active worker, and allows retry after request failures. Jobs added during generation are retained. Keep the dashboard open; leaving it ends the manual loop while ordinary WP-Cron remains available.
- Updated the plugin header, runtime version, and current documentation to r2.2.7. Admin JavaScript cache invalidation uses the asset modification time.

See [`RELEASE-r2.2.7.md`](RELEASE-r2.2.7.md) for release details and verification.

## r2.2.6 - 2026-10-30

- added the installed version and GitHub version status below the dashboard title. Checks the plugin header on `ArdsGold/w2e-wf`'s `main` branch and shows a clickable repository link only when that version is newer. Results cache for six hours; failed checks retry after 15 minutes and show an unavailable status. No automatic download or installation is performed.
- newly generated phone links inherit their surrounding text color, including on hover, using an inline color override. Phone destinations and displayed numbers remain unchanged; existing anchors retain their original styling. Regenerate pages to update automatically linked numbers. Added standalone phone-link regression checks.
- Updated the plugin header, runtime version, and current documentation to r2.2.6. Automatic installation remains unimplemented; GitHub checks only provide status and a repository link.

See [`RELEASE-r2.2.6.md`](RELEASE-r2.2.6.md) for release details and checks.

## r2.2.5 - 2026-10-21

- Fixed the final ElementsKit Heading in the Sud Slingers template: `h2` now fills `ekit_heading_title`, and `p` fills the rich-text Title Description (`ekit_heading_extra_title`). Focused title, subtitle, visibility, and styling remain unchanged. Preview and compatibility tests include this widget; all 12 marked supported widgets in the template pass.
- Added marked JKit Heading (`sg_title_text`/`sg_description`), JKit Icon Box (`sg_icon_text`/`sg_icon_description`), and Elementor Flip Box support. Flip Boxes receive the same heading on both faces and paragraph content only in the back description. Extra JKit heading text, badges, buttons, front descriptions, and unmarked widgets remain unchanged. Added template-preview fields and normal/repeat regression checks, including all 11 compatible marked widgets in the supplied Sud Slingers template.
- Updated the plugin header, runtime version, and current documentation to r2.2.5. The r2.2.2 release notes retain their previous scope.

See [`RELEASE-r2.2.5.md`](RELEASE-r2.2.5.md) for mapping rules and verification.

## r2.2.2 — 2026-10-20

- Consolidated the current fixes and documentation under r2.2.2. Documented supported text destinations and section-heading-first behavior; retained the existing mapper without introducing universal widget-control discovery.
- Fixed `data-customID|p` on Heading widgets by writing their `title` field. Combined heading/paragraph markers keep their heading when no separate body field exists.
- Fixed FAQ Accordion/Toggle scope selection when the widget precedes its section's H2 in Elementor's JSON. Mapping visits a section's sole marked H1/H2 branch first without rearranging the saved layout. Generation and Validator traces use the same traversal order.
- Internal FAQ repeaters now populate even when the page has no external card repeat groups.
- Fixed structural groups consuming later scopes after row expansion by processing structural collections in reverse order. Mixed structural and widget repeat groups now both populate.
- Expanded regressions to cover multiple numeric structural groups, mixed repeat styles, paragraph Heading widgets, FAQ column order, Validator traces, and the supplied Services template/DOCX through in-memory generation checks.
- Added **No limit** beside **Widgets per section**. Checking it grays out and disables the numeric field; unchecking restores its previous value.
- Unlimited mode keeps all widgets for each repeatable group in one section, supports more than 100 records, and keeps separate groups independent. Both structural and legacy/widget repeat generation paths support it; numeric limits still work when unchecked.
- Added automatic, site-wide settings shared across administrators: repeat controls, parent page, overwrite, image-pool enablement and images, zip-free fallback, saved template, image mappings, active tab, and log filters.
- Existing per-administrator template, image, and fallback preferences seed missing shared settings on first use. False and empty selections persist; file uploads must be selected again after refresh.
- Added authenticated, nonce-protected preference autosave with validation, ordered patches, and visible save/error status. Replaced browser session tab memory with shared saved state and updated the URL when switching tabs.
- Fixed saved image IDs re-enabling a disabled image pool. Disabled pools retain selections without attaching them to new jobs. Generation submissions also preserve the numeric limit while No limit disables its input.
- Set the plugin header and runtime version to r2.2.2 and updated the README, dashboard, installation, marker, and architecture documentation. Added runnable generation and preference checks.

See [`RELEASE-r2.2.2.md`](RELEASE-r2.2.2.md) for behavior, upgrade details, and checks.

## r2.2.0

- Added structural `data-customID|repeat` markers for complete Elementor items.
- Structural repeat items are created or removed to match the DOCX records in scope. Generated rows follow the **Widgets per section** limit, preserve all template prototypes, use consistent column widths, and center partial rows.
- Kept heading/widget repeat markers and legacy marker spellings compatible.
- Added Accordion support alongside Toggle for internal `h3|p|repeat` items.
- Added automatic template structure previews in Page Generation and Template Validator. The Validator previews and validates with one action and can check an optional DOCX.
- Added PHP `ZipArchive` status and an account-level zip-free DOCX fallback, automatically enabled when `ZipArchive` is unavailable.
- Kept existing `WFEBPG_*` storage names, queue data, and WordPress integration identifiers.

See [`RELEASE-r2.2.0.md`](RELEASE-r2.2.0.md) for release behavior and checks. Earlier history is kept separately in [`CHANGELOG-LEGACY.md`](CHANGELOG-LEGACY.md), [`RELEASE-r2.1.0.md`](RELEASE-r2.1.0.md), and [`CODE-AUDIT-r2.1.0.md`](CODE-AUDIT-r2.1.0.md).
