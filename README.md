
## v1.12.4 — Stop Queueing

- Added a **Stop Queueing** button to the bulk-generation form.
- While DOCX files are being uploaded individually, the button cancels the active upload sequence and prevents the remaining files from being queued.
- The temporary upload batch is cleaned up when queueing is stopped.
- No generated pages are affected because the main queue submission is not completed.

## v1.12.1 — Saved Template Selector Fix

- The saved JSON template selector is now always visible, including when the library is empty.
- Saved templates show their filename and saved date for easier selection.
- Uploading a new `.json` remains optional when a saved template is selected.
- Uploading a new JSON with the same filename replaces the saved copy.
- The admin screen clearly separates **Use a previously uploaded template** from **Upload a new JSON template**.

# Wolf Forge Elementor Bulk Page Generator

**Version 1.12.0**

Bulk-generate Elementor pages from DOCX content and Elementor JSON templates. The plugin supports Generic and Unique mapping, yellow-heading repeatable sections, parent pages, phone-link conversion, queue processing, template validation, Media Library image pools, randomized repeatable images, previews/logs, generated-page tracking, and rollback/reset tools.

## What changed in 1.11.0

Version 1.11.0 focuses on the repeatable-section generation path and keeps the existing v1.10.0 visual behavior while reducing unnecessary Elementor-tree work.

### Performance and algorithm improvements

- Repeatable widgets now record their containing Column path during the original tree scan instead of repeatedly searching for the same Column later.
- Repeatable-section expansion no longer performs a separate full-tree `has_repeatable_section()` scan before the actual expansion pass.
- Repeatable color detection is validated once per prototype section instead of re-validating the entire A/B/A/B pattern for every generated card.
- Color selection uses an O(1) XOR slot calculation: the first generated section preserves the template pattern, the next section reverses it, and the pattern continues alternating.
- Image assignment and color assignment now share one Column traversal per generated card.
- Media Library attachment type/MIME/URL validation is prepared once and reused across generated sections instead of repeating WordPress attachment lookups.
- Elementor element paths are traversed without the previous `end($path)` side effect and with cached path length.
- Literal Elementor colors and Elementor global color tokens retain their original storage style when the alternating pattern is applied.


## Remembered JSON templates

The main generator now keeps uploaded Elementor `.json` templates in a small WordPress uploads library so they can be reused without uploading the same file every time.

- Previously uploaded JSON templates appear in the **Elementor JSON Template** dropdown.
- Uploading a new `.json` file saves it automatically.
- A new upload with the same filename replaces the previously remembered copy.
- Generated jobs copy the selected template into their own job folder, so replacing or clearing the saved library does not change an already queued job.
- **Clear Saved JSON Templates** removes the remembered template files only; it does not delete generated pages or templates already copied into queued jobs.
- Templates are validated as Elementor JSON before they are added to the remembered library.

## Template markers

The plugin is designed for the Elementor JSON structure used by Wolf Forge.

Use Elementor **Advanced > Attributes / Custom Attributes** with these values:

- `data-customID|h1NonRepeat` — main H1 destination; maps to DOCX Heading 1.
- `data-customID|sectionTitleNonRepeat` — major section-title destination; maps to DOCX Heading 2.
- `data-customID|hNonRepeat` — normal heading destination; maps to DOCX Heading 3.
- `data-customID|pNonRepeat` — paragraph/content destination.
- `data-customID|repeatableItem` — repeatable widget destination.

The plugin reads the value after the pipe (`|`).

## Unique DOCX repeatable sections

A DOCX paragraph is a repeatable boundary when it is both:

1. a Word heading, and
2. yellow font or yellow highlight.

Everything after that yellow heading belongs to the repeatable item until the next yellow heading.

For an `icon-box` marked `data-customID|repeatableItem`:

- yellow heading -> `title_text`
- following content -> `description_text`

The plugin clones the marked widget itself when widget-level cloning is required. When **Widgets per section** is enabled and the template contains a repeatable section, the containing Section is cloned so the complete visual card layout is preserved.

## Widgets per section

Unique mode can use a **Widgets per section** value to control how many repeatable cards are placed in each generated Elementor Section.

For a four-card template, a value of `4` means:

- DOCX items 1–4 -> generated Section 1
- DOCX items 5–8 -> generated Section 2
- DOCX items 9–12 -> generated Section 3

The source repeatable widgets are used as visual prototypes and are distributed round-robin when additional sections are created.

## Alternating repeatable card colors

When the repeatable card Columns use a two-color alternating pattern, the generator reads the pattern directly from the Elementor template.

Example source pattern:

`A / B / A / B`

Generated sections become:

- Section 1: `A / B / A / B`
- Section 2: `B / A / B / A`
- Section 3: `A / B / A / B`
- Section 4: `B / A / B / A`

The algorithm only activates when the template actually contains an alternating two-color pattern. Templates with three or more colors, repeated non-alternating colors, or missing color values are left unchanged rather than being rewritten unexpectedly.

### Supported color formats

The detector supports both:

- Elementor global color tokens such as `globals/colors?id=primary` and `globals/colors?id=secondary`.
- Literal Elementor `background_overlay_color` values.
- Literal/global `background_color` values when no overlay color is defined.

The generator preserves whether the source color was stored as a global token or as a literal setting.

### Color and image independence

The Media Library Image Pool is independent from color assignment. A section can receive randomized, non-duplicated images while its card colors still follow the template's alternating pattern.

## Media Library Image Pool

Unique-mode repeatable sections can use selected Media Library images.

Behavior:

- Images are validated as WordPress image attachments before use.
- Images are randomized separately for each repeatable section.
- The same selected pool image is not assigned twice within one generated section.
- Images may be reused in another generated section or another generated page.
- If the pool has fewer usable images than the number of cards, the available pool images are used once and remaining cards keep their template image.
- Non-repeatable template backgrounds are not changed by the repeatable image pool.

## Non-repeat content

Non-repeat content is mapped according to the semantic DOCX structure.

- `h1NonRepeat` maps to Heading 1.
- `sectionTitleNonRepeat` maps to Heading 2.
- `hNonRepeat` maps to Heading 3.
- `pNonRepeat` adapts to the Elementor widget type.
  - Text Editor receives the body of its mapped heading.
  - Icon Box receives a heading plus body content.
  - Toggle receives multiple heading/body pairs for FAQ entries.

This prevents paragraphs from shifting or duplicating simply because a DOCX contains many headings between content blocks.

## Generic mode

Generic mode does not depend on yellow repeatable headings. It maps the DOCX's non-repeat content to the marked Elementor widgets using the document's H1/H2/H3 hierarchy.

This makes Generic mode useful for templates that do not use repeatable markers.

## Queue processing

Jobs are stored in the plugin queue and processed by WP-Cron.

New jobs also schedule a near-immediate single WP-Cron event so processing can start promptly, while the recurring worker remains as a backup.

If WP-Cron is delayed or disabled, the admin Queue panel provides **Process Queue Now** for manually processing one queued job.

## Elementor CSS and generated pages

After Elementor data is written, the plugin explicitly regenerates Elementor's post CSS and clears Elementor's file cache. This helps prevent generated pages from appearing extremely tall or narrow until they are manually opened in Elementor.

Pages created by the plugin are marked with `_wfebpg_generated` so the generated-page frontend CSS remains scoped to plugin-created pages.

## Template Validator

The admin Template Validator checks the Elementor JSON for:

- H1/H2/H3/non-repeat markers.
- `repeatableItem` markers.
- Repeatable Sections and Columns.
- Image/background locations.
- DOCX compatibility when a DOCX is supplied.

The validator is read-only and does not modify the template.

## Created Pages To-Do

After each page is generated, it is recorded in the **Newly Created Pages — To-Do** list on the admin screen.

Each entry provides quick links for:

- Edit Page
- Edit with Elementor
- View

## Reset and rollback safety

Use the reset tools on staging first.

The reset workflow safely deletes pages explicitly marked as created by the plugin, clears the generated-page To-Do list, and clears logs. Pages that were deliberately overwritten from an existing page are tracked but are not automatically deleted by the reset operation.

## Installation / update

1. Back up the current plugin and generated pages.
2. Deactivate the previous plugin version if your WordPress installation requires it.
3. Install the new plugin ZIP through **WordPress > Plugins > Add New > Upload Plugin**.
4. Activate the plugin.
5. Validate the Elementor template before a production batch.
6. Run a small staging batch first.

## Recommended template workflow

For a four-card visual grid:

1. Create four Elementor Columns/cards.
2. Put one `data-customID|repeatableItem` widget in each card.
3. Use an alternating background/overlay pattern such as A/B/A/B.
4. Set **Widgets per section** to `4`.
5. Optionally select an Image Pool.
6. Validate the template.
7. Generate a small test batch before running a large DOCX batch.

## Compatibility notes

- WordPress: 6.0+
- PHP: 7.4+
- Elementor JSON structures can vary between Elementor versions and third-party widgets.
- Always validate and test on staging before large production runs.

## Changelog

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

## Safety

Install and test on staging first. Elementor JSON structures and third-party widgets can vary by Elementor version. The plugin publishes generated pages after queue processing.


## v1.12.2 — Saved Template Selection Fix
- Fixed saved JSON template selection so the selected filename is explicitly preserved in the generation form submission.
- Selecting a remembered template no longer depends solely on the browser's handling of the `<select>` field.
- New JSON uploads and same-name replacement behavior remain unchanged.

## 1.12.3 — Reliable large-file uploads

Version 1.12.3 changes the upload flow so Elementor JSON templates and DOCX files are uploaded individually before the generation form is submitted. This avoids PHP's `max_file_uploads` limit when many DOCX files are selected at once.

- New JSON templates are uploaded and remembered automatically before queuing.
- Previously saved JSON templates can still be selected without uploading a file.
- Multiple DOCX files are uploaded one at a time, so batches larger than the server's normal PHP file-count limit are supported.
- The queue form receives a temporary upload batch and copies those files into the normal job workspace before creating queued jobs.
- Same-name JSON uploads continue to replace the saved template.

## 1.12.5 — Center partial final repeatable sections

- When the final repeatable section contains fewer cards than the configured section capacity, the remaining cards are centered across the prototype columns instead of starting from the far-left column.
- For example, a four-card template with two final items uses the middle two card positions.
- Existing card content, images, and alternating color behavior remain unchanged.



### 1.12.6
- Changed **Clear Generated Pages Table** so it only clears the plugin's generated-page To-Do table. It never deletes WordPress pages and does not clear logs.


## v1.12.8 — Reliable centering for partial repeatable rows

Partial final repeatable rows now keep the template's original Elementor column grid and use unused columns as invisible spacers. This avoids Elementor collapsing empty legacy columns and reliably centers 1, 2, or 3 cards in a 4-card row.

The source-column selection uses the centered offset `ceil((prototype_count - card_count) / 2)`, so a four-column template places partial rows as:

- 3 cards: blank, card, card, card
- 2 cards: blank, card, card, blank
- 1 card: blank, blank, card, blank

## v1.12.7 — Center partial repeatable sections

Partial repeatable rows are centered by adding transparent spacer columns while preserving the prototype card width. For a 4-card prototype, 3 cards are rendered as 12.5% spacer + 25% + 25% + 25% + 12.5% spacer; 2 cards use 25% side spacing; 1 card is centered with equal side spacing. Full rows are unchanged.


## v1.12.9 — True centering for partial repeatable rows

Partial repeatable sections now use equal half-width spacer columns on both sides of the remaining cards. For a four-card template, 3 cards render as 12.5% spacer + 25% + 25% + 25% + 12.5% spacer, producing true geometric centering within the section. Full sections remain unchanged.


## v1.13.0 — True partial-row centering and remembered image pools

- Partial repeatable rows are now centered by Elementor's actual flex container, so the entire group is centered in the section rather than placed in a left, middle, or right slot.
- The original card width is preserved. A four-card 25% template with three cards now produces three 25% cards centered as a group.
- The unused prototype columns are removed only from the partial generated section; full sections are unchanged.
- Selected Media Library Image Pool IDs are remembered per WordPress admin user.
- Returning to the generator restores the previous image selection and previews the saved images.
- Clearing the image selection also clears the remembered selection.
- The image pool is also saved when a generation job is submitted.


## v1.14.0 — Automatic DOCX page-type detection and template mapping

The generator can now classify each DOCX independently when **Auto Detect** is selected:

- A DOCX with one or more **yellow-font headings** is classified as **Unique**.
- A DOCX with no yellow repeatable headings is classified as **Generic**.
- Mixed uploads can contain Unique and Generic DOCX files in the same batch.
- Each detected DOCX is queued with the matching Elementor JSON template.

### Automatic JSON template mapping

Saved Elementor JSON templates are classified automatically from their structure:

- A template containing `data-customID|repeatableItem` is classified as **Unique**.
- A template without repeatable-item markers is classified as **Generic**.

Auto Detect provides separate saved-template selectors for Unique and Generic templates. New Unique and Generic JSON files can also be uploaded and are remembered automatically.

If a detected DOCX type has no matching template selected, the batch is stopped before jobs are queued. Manual **Unique Pages** and **Generic Pages** modes remain available as overrides.

The upload progress also reports the number of DOCX files detected as Unique and Generic before the final queue submission.
