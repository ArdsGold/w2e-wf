# Changelog

## r2.2.0

- Added structural `data-customID|repeat` markers for complete Elementor items.
- Structural repeat items are created or removed to match the DOCX records in scope. Generated rows follow the **Widgets per section** limit, preserve all template prototypes, use consistent column widths, and center partial rows.
- Kept heading/widget repeat markers and legacy marker spellings compatible.
- Added Accordion support alongside Toggle for internal `h3|p|repeat` items.
- Added automatic template structure previews in Page Generation and Template Validator. The Validator previews and validates with one action and can check an optional DOCX.
- Added PHP `ZipArchive` status and an account-level zip-free DOCX fallback, automatically enabled when `ZipArchive` is unavailable.
- Kept existing `WFEBPG_*` storage names, queue data, and WordPress integration identifiers.

See [`RELEASE-r2.2.0.md`](RELEASE-r2.2.0.md) for release behavior and checks. Earlier history is kept separately in [`CHANGELOG-LEGACY.md`](CHANGELOG-LEGACY.md), [`RELEASE-r2.1.0.md`](RELEASE-r2.1.0.md), and [`CODE-AUDIT-r2.1.0.md`](CODE-AUDIT-r2.1.0.md).
