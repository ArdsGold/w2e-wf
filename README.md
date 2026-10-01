# Wolf Forge Elementor Page Generator

**Version:** r2.0.0  
**Authors:** Macky Villafuerte, Arden Guinto

Wolf Forge Elementor Page Generator is a WordPress plugin for generating Elementor pages in bulk from DOCX content and Elementor JSON templates.

It is designed around the Wolf Forge content-production workflow: prepare structured DOCX content, mark an Elementor template with `data-customID` attributes, select the matching template, and let the generator build and queue WordPress pages while preserving the Elementor design structure.

## What r2.0.0 changes

This release is a **Maintainability release**. The goal is to make the plugin easier for a human developer to understand and maintain without unnecessarily changing its existing generation behavior.

### Compatibility-first implementation

The internal `WFEBPG_` PHP prefix, WordPress option names, cron hook, AJAX action names, and stored job keys are intentionally retained. This avoids breaking pages, queued jobs, saved templates, user settings, or existing WordPress data merely because the product name changed.

### Readability improvements

- Added clearer file-level documentation and compatibility notes.
- Added developer documentation for the template marker system and generation pipeline.
- Normalized the main plugin bootstrap formatting.
- Improved readability of the Elementor template helper without changing its public behavior.
- Kept the large generation engine structurally intact so the rebrand does not become an unnecessary functional rewrite.

## Requirements

- WordPress 6.0 or newer
- PHP 7.4 or newer
- Elementor installed and active
- A WordPress administrator account with the required page/media permissions
- DOCX files containing structured content
- Elementor JSON templates containing the Wolf Forge marker attributes

## Main workflow

1. Open **Wolf Forge Elementor Page Generator** in WordPress Admin.
2. Choose the generation mode or use Auto Detect when available.
3. Upload one or more DOCX files.
4. Select or upload the appropriate Elementor JSON template(s).
5. Optionally configure the Media Library image pool.
6. Queue the pages.
7. Allow WP-Cron to process the queue, or use **Process Queue Now**.
8. Review the generated pages from the **Newly Created Pages — To-Do** table.
9. Open the page in WordPress or Elementor for final QA.

## Elementor marker system

The generator reads custom attributes from Elementor widgets. In Elementor, add the marker under **Advanced → Attributes / Custom Attributes**.

| Marker | Purpose |
|---|---|
| `data-customID|h1NonRepeat` | Main H1 destination; maps to a DOCX Heading 1 block. |
| `data-customID|sectionTitleNonRepeat` | Major section-title destination; maps to a DOCX Heading 2 block. |
| `data-customID|hNonRepeat` | Normal heading destination; maps to a DOCX Heading 3 block. |
| `data-customID|pNonRepeat` | Paragraph/body destination. The widget type determines how content is inserted. |
| `data-customID|repeatableItem` | Repeatable widget prototype for Unique pages. |
| `data-customID|stepNumber` | Step-number marker used by supported process layouts. |

### Marker syntax

The general syntax is:

```text
data-customID|MARKER_NAME
```

The parser reads the value after the first `|`.

For example:

```text
data-customID|h3
```

and

```text
data-customID|h3|repeatable
```

are interpreted according to the marker parser's supported marker rules. The marker parser is deliberately tolerant of common Elementor attribute representations.

For the current production workflow, use the documented marker names above rather than inventing new marker names unless the generator code is also updated.

## Paragraph behavior

`pNonRepeat` is semantic rather than a blind "next paragraph" replacement.

- **Text Editor:** receives body content associated with the mapped heading/block.
- **Icon Box:** receives the heading and its associated body content through the icon-box fields.
- **Toggle:** receives multiple heading/body pairs for FAQ-style content.

This design prevents paragraphs from drifting when a DOCX contains several headings between content blocks.

## Unique / repeatable content

A repeatable DOCX item is identified by a heading that is both a Word heading and marked with the configured yellow font/highlight convention.

Everything after that yellow heading belongs to the repeatable item until the next yellow repeatable heading.

For an Elementor widget marked as a repeatable item, the generator can clone the visual prototype and populate each generated item.

### Icon Box repeatables

For an Icon Box repeatable widget:

- repeatable heading → `title_text`
- associated body → `description_text`
- widget styling, icon, link, spacing, and other Elementor settings remain based on the template prototype

### Widgets per section

When the template contains multiple repeatable card columns, **Widgets per section** controls how many generated cards are placed in each Elementor section.

For a four-card prototype and a value of `4`:

```text
Items 1–4   → Section 1
Items 5–8   → Section 2
Items 9–12  → Section 3
```

Partial rows are centered while preserving the prototype card width.

## Alternating repeatable card colors

The generator can preserve a two-color alternating pattern found in the template.

Example source pattern:

```text
A / B / A / B
```

Generated sections alternate the prototype pattern without rewriting templates that do not clearly contain a two-color alternating system.

Supported color sources include Elementor global color tokens and literal Elementor color values.

## Media Library image pool

Unique repeatable sections can optionally use selected WordPress Media Library images.

Behavior:

- Selected images are validated as image attachments.
- Images are randomized independently for repeatable sections.
- A single generated section does not receive the same pool image twice when enough usable images exist.
- Images may be reused in another generated section or another generated page.
- If the pool is smaller than the number of cards, remaining cards keep their template image.
- Static, non-repeatable template backgrounds are not replaced by the repeatable image pool.

## Saved Elementor JSON templates

The plugin remembers uploaded Elementor JSON templates in its WordPress uploads library.

- Previously saved templates appear in the selector.
- Uploading a JSON file saves it automatically.
- Uploading another file with the same filename replaces the saved copy.
- A queued job receives its own copy of the selected template.
- Clearing the saved library does not delete generated pages or templates already copied into queued jobs.
- Templates are validated before being stored.

## Automatic page-type detection

When Auto Detect is used, the generator can classify each DOCX independently.

- DOCX with yellow repeatable headings → **Unique**
- DOCX without yellow repeatable headings → **Generic**

Saved JSON templates are classified from their Elementor structure:

- Template containing the repeatable marker → **Unique**
- Template without the repeatable marker → **Generic**

Auto Detect can therefore process mixed batches of Generic and Unique DOCX files while selecting the matching template for each page.

## Queue processing

Generation is queued rather than forcing every page to be generated inside one browser request.

The plugin uses:

- a WordPress option-backed queue;
- a recurring WP-Cron worker;
- a near-immediate single event when a new job is queued;
- a worker lock to avoid overlapping queue processing;
- a small per-request job limit and time guard.

This keeps large batches from requiring one long-running admin request.

## Generated-page tracking

Pages created by the generator are marked with the `_wfebpg_generated` post meta value.

That marker is used to:

- identify generated pages;
- scope the plugin's generated-page CSS;
- populate the admin To-Do table;
- provide page review links;
- support the generated-page management workflow.

The legacy `_wfebpg_` key is intentionally preserved in r2.0.0 for compatibility.

## Template validation

The built-in validator can inspect an Elementor JSON template and optionally a DOCX file.

It reports information such as:

- content marker counts;
- repeatable sections;
- repeatable columns;
- image/background locations;
- repeatable image locations;
- DOCX compatibility information;
- blocking errors;
- warnings.

Use the validator before a large batch when a new template has been created or modified.

## Static template image replacement

The generator supports filename-based replacement for static images in saved Elementor templates.

The workflow allows you to:

1. scan a saved template;
2. review matching Media Library suggestions;
3. manually choose replacements when required;
4. save the mapping for that template;
5. apply the mapping to generated template copies before page generation.

This is separate from the Dynamic Image Pool used by repeatable Unique sections.

## Phone links

The generator includes phone-link processing for supported generated content. This allows phone numbers in generated page content to be converted into `tel:` links according to the plugin's existing rules.

## Parent pages and existing slugs

Generated pages can optionally be assigned a WordPress parent page.

When overwrite is disabled, the generator avoids unintentionally replacing an existing page and uses WordPress-safe slug handling for the new page.

Page titles preserve meaningful filename characters while WordPress continues to sanitize the URL slug separately.

## Rollback and cleanup

The admin interface includes tools for:

- reviewing generated pages;
- moving selected generated pages to WordPress Trash;
- clearing the generated-page To-Do table without deleting pages;
- clearing plugin logs;
- clearing saved JSON templates;
- stopping an upload/queueing sequence before final submission.

These controls are intentionally separate so clearing tracking data does not silently delete WordPress content.

## Developer documentation

Detailed technical notes are in the `docs/` directory:

- `docs/TEMPLATE-MARKERS.md` — marker syntax and content-mapping rules.
- `docs/ARCHITECTURE.md` — file responsibilities and generation flow.
- `docs/DEVELOPMENT.md` — safe maintenance, compatibility, and QA guidance.
- `CHANGELOG.md` — release history and the r2.0.0 rebrand notes.

## Codebase layout

```text
wolf-forge-elementor-page-generator/
├── wolf-forge-elementor-page-generator.php  # Plugin bootstrap
├── README.md                                 # User/developer overview
├── CHANGELOG.md                              # Release history
├── docs/
│   ├── ARCHITECTURE.md
│   ├── DEVELOPMENT.md
│   └── TEMPLATE-MARKERS.md
├── admin/
│   └── admin-page.php                        # Admin screens and handlers
├── assets/
│   ├── admin.css
│   ├── admin.js
│   └── frontend.css
└── includes/
    ├── class-docx-reader.php                 # DOCX parsing
    ├── class-generator.php                   # Page generation engine
    ├── class-logger.php                      # Logging
    ├── class-phone-linker.php                # Phone link conversion
    └── class-template.php                    # Elementor JSON helpers
```

## Compatibility note for developers

Do not rename the existing `WFEBPG_` classes/functions, `wfebpg_*` actions, options, cron hooks, or post-meta keys as part of a normal branding update. Those identifiers are implementation details that are already persisted in WordPress sites and jobs.

The visible product name is now **Wolf Forge Elementor Page Generator**, while the internal identifiers remain stable by design.

## QA checklist

Before deploying a new build:

- Validate PHP syntax for every PHP file.
- Confirm the plugin header reports `r2.0.0`.
- Confirm the admin title shows **Wolf Forge Elementor Page Generator**.
- Load the generator screen without PHP notices or fatal errors.
- Load a saved JSON template.
- Validate a template with the built-in validator.
- Generate at least one Generic page.
- Generate at least one Unique page.
- Confirm repeatable Icon Box title and description content populate correctly.
- Confirm Toggle/FAQ content remains aligned.
- Confirm Media Library image-pool behavior.
- Confirm queued jobs process through WP-Cron or **Process Queue Now**.
- Confirm generated pages appear in the To-Do table.
- Confirm Elementor editing still opens normally.
- Confirm generated-page frontend CSS is scoped to generated pages.

## License / distribution

This package contains the Wolf Forge Elementor Page Generator codebase. Add the project's chosen license text before public redistribution if the distribution terms require an explicit license file.
