### r2.0.1b — Widget-level repeat scope fix

- Widget-level repeat markers inside ordinary Elementor Sections/Containers now use the outer visual section as their repeat owner instead of accidentally using a nested Container.
- `data-customID|h3|p|repeat` on a Text Editor can therefore share the same Section as its H2 marker without producing a missing DOCX scope error.
- The DOCX scope remains heading-based: the nearest preceding marked H2 establishes the H3 collection, and the collection ends at the next higher-level heading.
- Nested Elementor Containers no longer overwrite the repeat-region owner while walking the template tree.

# Legacy Changelog

### r2.0.1b — Wolf Forge scoped repeatable regions
- Rebranded the plugin as Wolf Forge Elementor Page Generator.
- `data-customID|...|repeat` remains the preferred repeat marker. The older `|repeatable` spelling is an equivalent compatibility alias.
- Modern repeatable Elementor regions now receive independent DOCX collections and independent repeat cursors.
- A repeatable H3 region is scoped to its surrounding H2 hierarchy, so separate Services, Process, FAQ, and similar sections no longer consume one another's records.
- Removed the dependency on legacy yellow DOCX headings for modern marker-driven repeatable sections; yellow-heading parsing remains only as a legacy fallback.

This file preserves the historical release notes from the pre-rebrand plugin.

### 1.19.0
- Reworked non-repeat content mapping to use hierarchical heading scope instead of global H1/H2/H3 cursors.
- A child heading is now scoped to the preceding marked heading one level above it: H2→H1, H3→H2, H4→H3, and so on.
- Prevents content from a later document section from being consumed by unused heading slots in an earlier section.
- Added a Mapping Debugger to the Template Validator showing Elementor element IDs, markers, parent scope, DOCX source, and match status.
- Repeatable heading detection now accepts arbitrary heading levels supported by the DOCX, while preserving the existing H1/H2/H3 behavior.

### 1.18.0
- Added generic internal repeater support for Elementor Toggle widgets.
- `data-customID|h3|repeat` on a Toggle now rebuilds its internal Toggle Items from the matching DOCX H3 + paragraph records instead of cloning the Toggle widget.
- Toggle repeatable content is scoped to the corresponding H2 section based on the marker's position in the Elementor template, allowing separate H3 repeatable regions such as Services and FAQs in one template.
- Existing Toggle visual settings, icons, typography, schema setting, and other widget settings are preserved; only the internal item collection is replaced.
- Repeatable parent containers continue using the generic first Heading / first Text Editor discovery model.

### 1.17.2
- Template Image Replacement now previews the plugin's top recommended Media Library image before you apply it.


### 1.16.2 — Template-aware content mapping

- Repeatable H3 cards are scoped to the corresponding DOCX H2 section instead of consuming every H3 in the document.
- Repeatable and non-repeatable Icon Box H3 markers populate both the title and the associated body paragraph.
- Toggle widgets using an H3 marker now populate their tabs from the DOCX H3/body pairs.
- Unmarked Elementor elements remain untouched.

### 1.16.0 — Marker-driven single-template generation

- Replaced the old Generic/Unique marker contract with a single, documented `data-customID|type|flags...` system.
- Added simple content markers: `h1`, `h2`, `h3`, and `p`.
- Added the `repeatable` flag, allowing one Elementor element to declare both its content type and repeatable behavior, for example `data-customID|h2|repeat`.
- Enforced the safety rule that unmarked elements are not populated by the content mapper.
- Repeatable cards can contain multiple marked widgets, such as `h2|repeat` and `p|repeat`, and those widgets are cloned together.
- Removed the need for yellow DOCX formatting in new templates. Legacy yellow-heading and `repeatableItem` templates remain supported.
- Added dedicated marker-system documentation in `docs/MARKER-SYSTEM.md`.
- Improved H1/H2/H3 mapping and repeatable heading parsing for normal DOCX Heading styles and plain paragraphs.

### 1.14.1 — Last-used templates and per-page Trash action

- Remembers the last used Unique and Generic saved templates per administrator.
- Restores those selections on later upload sessions.
- Added a per-row **Move to Trash** action to the Newly Created Pages — To-Do table.
- Moving a page to Trash removes only that page from the To-Do table; it does not permanently delete it.
- Kept **Clear Generated Pages Table** safe: it only clears the table.

### 1.12.0 — Remembered JSON template library

- Added persistent Elementor JSON template storage for convenient reuse.
- Re-uploading a JSON file with the same filename replaces the remembered copy.
- Added a separate **Clear Saved JSON Templates** control.
- Queued jobs receive their own template copy, so later library changes do not affect queued work.
- Validates uploaded JSON before saving it to the remembered library.


### 1.11.0 — Generator optimization and color algorithm cleanup

- Cached repeatable Column paths during the initial template scan.
- Removed the redundant repeatable-section pre-scan.
- Prevalidated Media Library image pools for reuse across generated sections.
- Combined repeatable image/color writes into one Column traversal.
- Cached/validated the two-color pattern once per prototype section.
- Simplified alternating color selection to a constant-time slot calculation.
- Fixed path traversal so it no longer relies on `end($path)` inside iteration.
- Preserved global-token versus literal-color storage when swapping alternating colors.
- Expanded documentation and troubleshooting guidance.

### 1.10.0 — Alternating Repeatable Card Colors

- Detects alternating two-color repeatable card patterns from the Elementor template.
- Preserves the first section's pattern and reverses it on the next generated section.
- Continues alternating section by section.
- Supports Elementor global color tokens and literal background/overlay colors.
- Keeps image-pool randomization independent from color assignment.

### 1.9.0 — Template validation and image pools

- Added Template Validator admin screen.
- Added optional DOCX compatibility validation.
- Added Media Library Image Pool selection.
- Added randomized repeatable image assignment.
- Prevented duplicate pool images within the same generated repeatable section.

### 1.8.0 — Partial repeatable sections, Elementor CSS, and reset

- Removes unused prototype Columns in partial/final repeatable sections.
- Regenerates Elementor CSS and clears Elementor's file cache after generation.
- Added Reset Logs & Generated Pages.

### 1.7.x and earlier

See the existing plugin history for queue processing, semantic DOCX mapping, section-title mapping, created-page tracking, and earlier repeatable-section improvements.


## r2.0.1b — Inline Text Editor repeat stream

- Fixed widget-level `data-customID|h3|p|repeat` Text Editor behavior.
- A marked Text Editor now remains a single Elementor widget instead of causing its containing Section/Container to be cloned.
- All matching H3/P records in the current heading scope are rendered into that one editor as semantic `<h3>` and `<p>` HTML.
- The repeat marker is removed after the stream is consumed so the later repeat-section expansion pass cannot duplicate the surrounding layout.
- `repeat` and `repeatable` remain equivalent marker aliases.
