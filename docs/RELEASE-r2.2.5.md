# Wolf Forge Elementor Page Generator r2.2.5

Release date: October 21, 2026.

## JKit and Flip Box compatibility

This release adds the following text destinations for marked widgets:

| Widget | Heading destination | Paragraph destination |
|---|---|---|
| JKit Heading | Main title (`sg_title_text`) | Description (`sg_description`) |
| JKit Icon Box | Title (`sg_icon_text`) | Description (`sg_icon_description`) |
| Elementor Flip Box | Front and back titles (`title_text_a`, `title_text_b`) | Back description only (`description_text_b`) |
| ElementsKit Heading | Heading title (`ekit_heading_title`) | Rich-text Title Description (`ekit_heading_extra_title`) |

Use `data-customID|h3|p` for the item heading and body, adding `|repeat` when appropriate. The fields also work in structural repeat descendants and with legacy repeat markers. A heading-only marker leaves descriptions unchanged; a paragraph-only marker leaves titles unchanged. An `h2` marker on JKit Heading fills its main title; add `|p` to populate its associated description.

JKit Heading prefix, focused text, subtitle, shadow text, and display controls remain unchanged. JKit Icon Box badges and buttons remain unchanged. Flip Box front descriptions and buttons remain unchanged. Enable a description's display control in Elementor if needed to show its populated text. Unmarked widgets remain unchanged, and the uploaded template is not rewritten.

The structural preview now recognizes the added title and description fields. This release retains known-field mapping; it does not discover arbitrary third-party widget text controls.

The final ElementsKit Heading in the supplied Sud Slingers template uses `data-customID|h2|p`. It now receives H2 in its heading title and the associated paragraphs in Title Description. Focused title, subtitle, visibility, and styling remain unchanged.

## Upgrade and compatibility

The plugin header and `WFEBPG_VERSION` are r2.2.5. Existing r2.2.2 behavior is retained, including No limit, automatic shared settings, paragraph/FAQ corrections, and structural/mixed repeat fixes. Storage keys and queued-job formats remain compatible. Existing pages must be regenerated to receive the added widget mappings.

The [r2.2.2 release notes](RELEASE-r2.2.2.md) retain their previous scope. Today's widget compatibility additions belong to r2.2.5.

## Verification

```sh
php tests/widget-compatibility.php
php tests/generation-mapping.php
php tests/repeatable-limit.php
php tests/admin-settings.php
node tests/admin-settings.js
php -l wolf-forge-elementor-page-generator.php
php -l includes/class-generator.php
php -l admin/admin-page.php
node --check assets/admin.js
```

To check the supplied template, run `php tests/widget-compatibility.php "path/to/sudslingerspwtemplate1.0.json"`. It checks all 12 marked JKit/Flip Box/ElementsKit Heading widgets against their exported settings and confirms other settings are preserved. Standalone checks cover H2/H3 heading-only, paragraph-only, combined, normal, modern/legacy/structural repeat, and unmarked behavior.

Live WordPress/Elementor rendering remains unverified. The earlier Services DOCX check could not be rerun because its previous local file was unavailable.
