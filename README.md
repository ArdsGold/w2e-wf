# Wolf Forge Elementor Page Generator

**Version:** r2.2.5  
**Authors:** Macky Villafuerte, Arden Guinto  
**WordPress:** 6.0+  
**PHP:** 7.4+  
**Requires:** Elementor

Generate WordPress pages from DOCX content and an Elementor JSON template.

The template's `data-customID` markers control where DOCX content appears. Your Elementor design stays intact.

> **Compatibility note:** The plugin's internal `WFEBPG_*` PHP classes, WordPress option names, AJAX actions, cron hook, and CSS selectors are intentionally retained from the previous codebase. They are implementation identifiers, not the public product name. Keeping them stable protects existing saved templates, queued jobs, logs, and user settings during the rebrand.

---

## What it does

- Generates WordPress pages from one reusable Elementor JSON template.
- Reads normal DOCX Heading 1/2/3 styles and paragraphs.
- Maps content with `data-customID` markers.
- Supports marked JKit Heading and Icon Box widgets, ElementsKit Heading titles/descriptions, and Flip Boxes with matching front/back headings and back-only paragraph content.
- Supports fixed and repeatable content blocks.
- Offers **No limit** to keep each repeatable group together in one section.
- Automatically remembers dashboard settings across refreshes and shares them across administrators.
- Supports the r2.2.0 structural `data-customID|repeat` marker for complete Elementor elements.
- Structural repeat regions automatically shrink, expand, or disappear to match their scoped DOCX record count.
- Existing `h3|repeat`, `h3|p|repeat`, and legacy `|repeatable` behavior remains supported.
- Repeatable regions follow the document's heading hierarchy.
- Supports repeatable parent containers and Elementor Toggle/Accordion items.
- Remembers uploaded Elementor JSON templates.
- Lets you map template images to Media Library images.
- Supports an optional Media Library image pool for repeatable cards.
- Processes large batches through a WP-Cron queue.
- Regenerates Elementor CSS after page creation.
- Provides a Template Validator and Mapping Debugger.
- Tracks generated pages in the New Pages tab, with review and Trash actions.

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

## Remembered settings and repeat limits

In **Page Generation > Repeatable Section**, check **No limit** beside **Widgets per section** to gray out the number field and put all widgets for each repeatable group into one section. Separate DOCX groups remain separate. Uncheck it to reuse the previous number. Numeric mode retains its 1–100 limit; unlimited mode also supports groups larger than 100 items.

Settings save automatically without submitting or generating pages and are shared across all administrators on this WordPress site. Remembered controls include the repeat limit and No limit checkbox, parent page, overwrite checkbox, image-pool enablement and selections, zip-free fallback, selected saved template, template image mappings, active tab, and log filters. The overwrite checkbox remains enabled on future visits if you leave it enabled.

On first use, existing per-administrator template, image, and fallback preferences seed the shared settings where no shared value exists. Subsequent administrator changes use those shared values. Already-open dashboards are not synchronized live; refresh to load another administrator's saved changes. File inputs must be selected again after refreshing; uploads and queue actions are not replayed.

---

## Basic workflow

### 1. Build the Elementor template

Create the visual page in Elementor.

Only elements with a `data-customID` marker receive content. Other elements remain unchanged.

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

Structural repeat marker for a complete Elementor item:

```text
data-customID|repeat
```

Child content markers remain ordinary markers:

```text
data-customID|h3
data-customID|p
```

Existing heading/widget repeat markers such as `data-customID|h3|repeat` and `data-customID|h3|p|repeat` remain supported. See [`docs/MARKER-SYSTEM.md`](docs/MARKER-SYSTEM.md) for the complete mapping contract.

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

Each heading is paired with its following paragraphs, up to the next relevant heading.

### 4. Validate

Use **Template Validator** before a large batch.

It reports:

- Marker counts.
- Repeatable sections.
- Image locations.
- DOCX compatibility.
- Mapping/debug information.

### 5. Generate

Upload the DOCX files, choose the Elementor template, configure the optional image pool and parent page, then queue the pages.

WP-Cron processes the queue. Choose **Process Queue Now** to run a job manually.

---

## Marker safety rule

> **No `data-customID` = no content mapping.**

Unmarked content—including headings, buttons, icons, and images—is not changed by the content mapper.

An unmarked element inside a marked repeatable parent may be cloned, but its content is not populated.

---

## Repeatable parent containers

Recommended pattern:

```text
Container
  data-customID|repeat

  Heading widget
  Text Editor widget
  Button widget
```

The container is one repeatable item.

For every matching DOCX H3 record, the generator:

1. Clones the marked parent container.
2. Finds the first Heading widget.
3. Inserts the DOCX H3 text.
4. Finds the first Text Editor widget.
5. Inserts the paragraphs associated with that H3.
6. Leaves unmarked child content alone.

Use this pattern for:

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

The generator fills the first Heading and Text Editor inside the marked element.

Use this with widgets that have separate title and description fields, such as Icon Box.

---

## Elementor Toggle and Accordion

These widgets contain their own repeated items.

Add this marker to the widget:

```text
data-customID|h3|p|repeat
```

The generator replaces the widget's items with matching DOCX H3/paragraph records. It does not clone the widget.

Existing visual, icon, typography, and schema settings are preserved.

---

## Image handling

### Template Image Replacement

Template Image Replacement scans images in a saved Elementor JSON template.

It can:

- Show the original template image.
- Suggest a Media Library replacement using filename similarity.
- Preview the suggested replacement.
- Let you manually choose a Media Library image.
- Save the mapping for the template.

### Media Library Image Pool

The optional Image Pool assigns selected Media Library images to repeatable sections.

Images are not repeated within a section, but can be reused in other sections or pages.

---

## Queue processing

Generation jobs are stored in a WordPress option and processed in small batches to limit request time.

The worker uses:

- A short-lived queue lock.
- A maximum number of jobs per request.
- A maximum processing-time guard.
- A near-immediate single cron event when a job is queued.
- The recurring minute worker as a backup.

If WP-Cron is unavailable, choose **Process Queue Now** in the admin screen.

---

## Generated page handling

Generated pages appear in **New Pages**.

The table provides quick access to:

- Edit Page.
- Edit with Elementor.
- View.
- Move to Trash where permitted.

**Clear Generated Pages Table** clears tracking records only; it does not delete WordPress pages.

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
│   ├── ADMIN-DASHBOARD.md
│   ├── ARCHITECTURE.md
│   ├── CHANGELOG.md
│   ├── CHANGELOG-LEGACY.md
│   ├── CODE-AUDIT-r2.1.0.md
│   ├── INSTALLATION.md
│   ├── MARKER-SYSTEM.md
│   ├── RELEASE-r2.1.0.md
│   ├── RELEASE-r2.2.0.md
│   ├── RELEASE-r2.2.2.md
│   └── RELEASE-r2.2.5.md
├── includes/
│   ├── admin-settings.php
│   ├── class-docx-reader.php
│   ├── class-generator.php
│   ├── class-logger.php
│   ├── class-phone-linker.php
│   └── class-template.php
├── tests/
│   ├── admin-settings.js
│   ├── admin-settings.php
│   ├── generation-mapping.php
│   ├── repeatable-limit.php
│   └── widget-compatibility.php
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

The public-facing brand can change without renaming these internal identifiers.

### Why the version is `r2.2.5`

`r2.2.5` adds marked JKit Heading, JKit Icon Box, and Elementor Flip Box compatibility. Flip Boxes receive the same heading on both faces and paragraph content only in the back description. It includes the No limit, shared-settings, paragraph, FAQ, and repeat-group fixes previously released in r2.2.2. See [release notes](docs/RELEASE-r2.2.5.md).

Text destinations use the supported fields documented in the [marker reference](docs/MARKER-SYSTEM.md). Automatic discovery of every widget's text controls is not part of this release. Regenerate pages to apply the compatibility additions.

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
- [`docs/ADMIN-DASHBOARD.md`](docs/ADMIN-DASHBOARD.md) — r2.2.5 admin tabs, shared preferences, and notification indicators.
- [`docs/RELEASE-r2.2.5.md`](docs/RELEASE-r2.2.5.md) — current release scope and verification commands.
- [`docs/CHANGELOG.md`](docs/CHANGELOG.md) — r2.2.5 changes.

### Historical documentation

These files document earlier releases and are separate from the current r2.2.5 documentation:

- [`docs/RELEASE-r2.2.2.md`](docs/RELEASE-r2.2.2.md) - shared settings, No limit, paragraph/FAQ, and repeat-group fixes.

- [`docs/RELEASE-r2.2.0.md`](docs/RELEASE-r2.2.0.md) — structural repeats and preview workflow.

- [`docs/RELEASE-r2.1.0.md`](docs/RELEASE-r2.1.0.md) — historical r2.1.0 release.
- [`docs/CODE-AUDIT-r2.1.0.md`](docs/CODE-AUDIT-r2.1.0.md) — r2.1.0 maintenance audit.
- [`docs/CHANGELOG-LEGACY.md`](docs/CHANGELOG-LEGACY.md) — archived pre-r2.2.0 release notes.

---

## Changelog

See [`docs/CHANGELOG.md`](docs/CHANGELOG.md) for the current r2.2.5 release notes.
