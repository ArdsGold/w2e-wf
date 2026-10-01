# Wolf Forge Elementor Page Generator

**Version:** r2.0.1b  
**Authors:** Macky Villafuerte, Arden Guinto  
**WordPress:** 6.0+  
**PHP:** 7.4+  
**Requires:** Elementor

Wolf Forge Elementor Page Generator creates WordPress pages from DOCX content using an Elementor JSON template.

The generator is **marker-driven**: the Elementor template decides exactly which widgets receive dynamic content. This keeps the visual design in Elementor while the DOCX controls page-specific copy.

> **Compatibility note:** The plugin's internal `WFEBPG_*` PHP classes, WordPress option names, AJAX actions, cron hook, and CSS selectors are intentionally retained from the previous codebase. They are implementation identifiers, not the public product name. Keeping them stable protects existing saved templates, queued jobs, logs, and user settings during the rebrand.

---

## What it does

- Generates WordPress pages from one reusable Elementor JSON template.
- Reads normal DOCX Heading 1/2/3 styles and paragraphs.
- Maps content with `data-customID` markers.
- Supports fixed and repeatable content blocks.
- `|repeat` and the legacy `|repeatable` marker are equivalent; `|repeat` is preferred for new templates.
- Repeatable regions are independently scoped to their surrounding heading hierarchy, so Services, Process, FAQs, and other H3/P groups do not share one global repeat cursor.
- Supports the parent-container repeatable model.
- Supports Elementor Toggle internal repeaters.
- Remembers uploaded Elementor JSON templates.
- Lets you map template images to Media Library images.
- Supports an optional Media Library image pool for repeatable cards.
- Processes large batches through a WP-Cron queue.
- Regenerates Elementor CSS after page creation.
- Provides a Template Validator and Mapping Debugger.
- Tracks generated pages in an admin To-Do table.
- Supports safe rollback/trash workflows already present in the plugin.

---

## Installation

1. Back up the current plugin, Elementor templates, and generated pages.
2. In WordPress, open **Plugins → Add New → Upload Plugin**.
3. Upload the plugin ZIP.
4. Activate **Wolf Forge Elementor Page Generator**.
5. Open **Wolf Forge Page Generator** in the WordPress admin.
6. Upload or select an Elementor JSON template.
7. Upload one or more DOCX files.
8. Validate the template before a large production batch.
9. Run a small staging test first.

### Updating an existing installation

This release is a **rebrand of the existing WFEBPG codebase**. Internal option names and hooks were deliberately not renamed. That means the new code can continue reading the existing plugin's saved templates, queue data, logs, image-pool selections, and generated-page records.

Because the public plugin slug has been rebranded, treat this ZIP as a replacement build when installing it over an existing copy. Do not keep both copies active at the same time.

---

## Basic workflow

### 1. Build the Elementor template

Create the visual page in Elementor.

Only widgets with a `data-customID` marker are populated. Everything else remains under the designer's control.

### 2. Add markers

Use **Elementor → Advanced → Attributes / Custom Attributes**.

Basic markers:

```text
data-customID|h1
data-customID|h2
data-customID|h3
data-customID|p
```

Combined heading + paragraph marker:

```text
data-customID|h3|p
```

Repeatable marker:

```text
data-customID|h3|repeat
```

See [`docs/MARKER-SYSTEM.md`](docs/MARKER-SYSTEM.md) for the complete mapping contract.

### 3. Prepare the DOCX

Use normal Microsoft Word heading styles:

- Heading 1
- Heading 2
- Heading 3
- Normal paragraphs

For a repeatable H3 section:

```text
Heading 2
Services

Heading 3
Roof Repair
Paragraph one.
Paragraph two.

Heading 3
Roof Replacement
Paragraph one.
Paragraph two.
```

The generator associates each heading with the paragraphs that follow it until the next relevant heading.

### 4. Validate

Use **Template Validator** before generating a large batch.

It reports:

- Marker counts.
- Repeatable sections.
- Image locations.
- DOCX compatibility.
- Mapping/debug information.

### 5. Generate

Upload the DOCX files, choose the Elementor template, configure the optional image pool and parent page, then queue the pages.

The queue is processed by WP-Cron. **Process Queue Now** is available when you need to process a queued job manually.

---

## Marker safety rule

> **No `data-customID` = no content mapping.**

This is the most important rule in the plugin.

Unmarked headings, buttons, icons, images, decorative copy, and other Elementor content are not rewritten by the content mapper.

An unmarked element can still be cloned when it sits inside a marked repeatable parent. Cloning is a layout operation; it does not mean the element's text is dynamically populated.

---

## Repeatable parent containers

The recommended modern pattern is:

```text
Container
  data-customID|h3|repeat

  Heading widget
  Text Editor widget
  Button widget
```

The container represents one complete repeatable item.

For every matching DOCX H3 record, the generator:

1. Clones the marked parent container.
2. Finds the first Heading widget.
3. Inserts the DOCX H3 text.
4. Finds the first Text Editor widget.
5. Inserts the paragraphs associated with that H3.
6. Leaves unmarked child content alone.

This works well for:

- Services.
- Benefits.
- Process steps.
- Feature cards.
- FAQs.
- Icon boxes.
- Other repeated Elementor layouts.

### `h3|repeat` vs `h3|p|repeat`

Both are supported:

```text
data-customID|h3|repeat
```

```text
data-customID|h3|p|repeat
```

The first is the simplest canonical form. The second documents that the repeatable unit contains heading + paragraph content.

---

## Fixed heading + paragraph containers

For a non-cloned container that should receive one DOCX heading and its associated paragraph content:

```text
data-customID|h3|p
```

The generator uses the first Heading widget and first Text Editor widget inside the marked element.

This is useful for widgets such as Elementor Icon Box, where one widget contains both a title and description.

---

## Elementor Toggle

Toggle widgets have their own internal repeated item collection.

Use:

```text
data-customID|h3|repeat
```

on the Toggle widget itself.

The generator rebuilds the Toggle's internal items from the matching DOCX H3/body records instead of cloning the entire Toggle widget.

Existing visual settings, icons, typography, schema settings, and other widget settings are preserved.

---

## Image handling

### Template Image Replacement

The Template Image Replacement tool scans images already present in the saved Elementor JSON template.

It can:

- Show the original template image.
- Suggest a Media Library replacement using filename similarity.
- Preview the suggested replacement.
- Let you manually choose a Media Library image.
- Save the mapping for the template.

### Media Library Image Pool

The optional Image Pool can assign selected Media Library images to repeatable card sections.

Images are not duplicated within the same generated repeatable section, but they may be reused on another section or page.

---

## Queue processing

Generation jobs are stored in a WordPress option and processed in small batches.

The worker uses:

- A short-lived queue lock.
- A maximum number of jobs per request.
- A maximum processing-time guard.
- A near-immediate single cron event when a job is queued.
- The recurring minute worker as a backup.

This reduces the chance of a large batch monopolizing a single PHP request.

If WP-Cron is unavailable, use **Process Queue Now** from the admin screen.

---

## Generated page handling

After generation, pages appear in **Newly Created Pages — To-Do**.

The table provides quick access to:

- Edit Page.
- Edit with Elementor.
- View.
- Move to Trash where permitted.

**Clear Generated Pages Table** only clears the plugin's tracking table. It does not delete WordPress pages.

---

## Code organization

```text
wolf-forge-elementor-page-generator/
├── wolf-forge-elementor-page-generator.php
├── admin/
│   └── admin-page.php
├── assets/
│   ├── admin.css
│   ├── admin.js
│   └── frontend.css
├── docs/
│   ├── ARCHITECTURE.md
│   ├── INSTALLATION.md
│   └── MARKER-SYSTEM.md
├── includes/
│   ├── class-docx-reader.php
│   ├── class-generator.php
│   ├── class-logger.php
│   ├── class-phone-linker.php
│   └── class-template.php
└── README.md
```

### Responsibility of each class

**`WFEBPG_DOCX_Reader`**  
Reads `word/document.xml` from DOCX files and converts Word paragraphs into normalized document records.

**`WFEBPG_Template`**  
Decodes Elementor JSON, reads marker attributes, normalizes legacy marker names, and exposes template helpers.

**`WFEBPG_Generator`**  
Owns document mapping, repeatable sections, image assignment, page creation, Elementor data persistence, queue processing, and validation.

**`WFEBPG_Phone_Linker`**  
Converts supported US phone-number text into safe `tel:` links without modifying existing anchors or HTML attributes.

**`WFEBPG_Logger`**  
Maintains the rolling generator activity/error log.

**`admin/admin-page.php`**  
Registers the WordPress admin screens, forms, AJAX handlers, template library, validator, and generated-page management UI.

---

## Compatibility and design decisions

### Why the internal prefix is still `WFEBPG`

The original implementation uses the `WFEBPG` prefix throughout PHP classes, WordPress options, AJAX actions, cron hooks, CSS classes, and stored metadata.

Changing those identifiers as part of a cosmetic rebrand would turn a safe rename into a data migration.

For r2.0.1b, the public-facing brand is changed while the internal contract stays stable.

### Why the version is `r2.0.1b`

`r2.0.1b` identifies this rebranded build. The release includes the existing generator functionality plus documentation and maintainability-focused cleanup.

---

## Development guidelines

When extending the plugin:

1. Keep `WFEBPG_*` internal identifiers stable unless a migration is intentionally planned.
2. Do not populate an Elementor element unless it has an explicit marker.
3. Prefer small helper methods with descriptive names.
4. Keep DOCX parsing separate from Elementor mapping.
5. Keep template decoding/marker normalization in `WFEBPG_Template`.
6. Preserve existing queued-job compatibility.
7. Add comments for non-obvious Elementor JSON behavior, not for self-evident code.
8. Test on staging before processing a large DOCX batch.
9. Run PHP syntax checks before packaging.
10. Update the changelog when behavior changes.

---

## Documentation

- [`docs/MARKER-SYSTEM.md`](docs/MARKER-SYSTEM.md) — complete marker and mapping reference.
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — code structure and data flow.
- [`docs/INSTALLATION.md`](docs/INSTALLATION.md) — installation, upgrade, and troubleshooting.

---

## Changelog

### r2.0.1b — Wolf Forge rebrand and maintainability release

- Rebranded the public plugin name to **Wolf Forge Elementor Page Generator**.
- Set authors to **Macky Villafuerte** and **Arden Guinto**.
- Set release version to **r2.0.1b**.
- Renamed the distributable plugin directory and main plugin file to the Wolf Forge brand.
- Kept `WFEBPG_*` internal identifiers for upgrade compatibility.
- Reworked the README into a structured developer/user guide.
- Added architecture and installation documentation.
- Improved readability in core bootstrap and logging code.
- Preserved existing marker, queue, image replacement, image pool, validation, and page-generation behavior.

### Previous release history

The original codebase's historical changelog is preserved in `docs/CHANGELOG-LEGACY.md`.
