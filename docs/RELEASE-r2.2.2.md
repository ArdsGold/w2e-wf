# Wolf Forge Elementor Page Generator r2.2.2

Release date: October 20, 2026.

The plugin header, runtime constant, README, and current documentation remain at **r2.2.2**, including the paragraph, FAQ, repeat-group, and shared-settings corrections below. Historical release files keep their original release numbers.

## Mapping scope

This release retains the existing known-field text mapper. It supports the documented text destinations and internal FAQ repeaters; it does not introduce universal discovery of arbitrary widget text controls. See the [marker reference](MARKER-SYSTEM.md) for supported fields and the limits of section-heading-first traversal.

## Repeatable Section: No limit

The Page Generation setting has one **No limit** checkbox beside **Widgets per section**. Checking it disables and grays out the number field. All widgets for each repeatable group stay in one generated section; different groups stay separate. Unchecking re-enables the previous number. Both values survive reopening and refreshes, including when the numeric field is disabled at submission.

Structural `data-customID|repeat` and legacy/widget repeat paths support unlimited mode, including groups larger than 100 records. Numeric mode retains its 1–100 capacity. Existing Elementor layout determines how the widgets appear within that section.

## Automatic shared settings

Dashboard preferences save without submitting a form or generating a page. They are stored in WordPress and shared across administrators on the same site:

- Widgets per section and No limit.
- Parent Page and Overwrite matching existing pages.
- Media Library Image Pool toggle and selected images.
- Zip-free DOCX fallback, forced on when PHP ZipArchive is unavailable.
- Selected saved template across the three template tabs, and saved template image mappings.
- Active tab, log level, and log search.

Unchecked boxes and cleared selections are saved. Loading image IDs no longer re-enables a disabled pool. A disabled pool retains its saved selection but supplies no images to new jobs. Temporary preview ownership and notification acknowledgments remain personal.

Dashboard preference changes show saving, saved, or error status. Autosave uses a capability check, nonce, and allowed-field validation. Requests save changed fields in order so an edit does not overwrite unrelated shared settings. Other open dashboards load saved changes on refresh; there is no live synchronization. Existing template/image/fallback tools keep their own save responses.

File inputs must be selected again after refresh. The plugin does not restore temporary uploads or replay queue, generation, deletion, or other action buttons. Queued jobs retain the options captured at submission.

## Upgrade

Both the WordPress plugin header and `WFEBPG_VERSION` are r2.2.2. On first read of each shared preference, the visiting administrator's existing per-user value seeds it if no shared value exists; otherwise the default seeds it. Open the updated dashboard first with the account whose existing template, image, and fallback choices should become shared. Later administrators use those shared options. Existing user metadata is retained.

## Verification

### Paragraph and FAQ corrections

`data-customID|p` also populates a Heading widget's `title`, so an introductory paragraph placed in a Heading widget is no longer left as template text. Existing body fields retain priority, and a combined heading/paragraph marker does not overwrite its heading when there is no separate body field.

A section's sole marked H1/H2 branch establishes its content scope before the other branches are visited, even when an Accordion/Toggle appears first in the exported JSON. This changes mapping order only; it does not rearrange the Elementor layout. The Validator uses the same heading order. Internal FAQ repeaters populate without requiring external repeatable cards.

Structural collections process from last to first so cloned row headings cannot renumber later collections. Mixed structural/widget collections both run. Existing pages must be regenerated to apply these corrections.

Run from the plugin directory:

```sh
php tests/repeatable-limit.php
php tests/generation-mapping.php
php tests/admin-settings.php
node tests/admin-settings.js
php -l wolf-forge-elementor-page-generator.php
php -l includes/admin-settings.php
php -l admin/admin-page.php
php -l includes/class-generator.php
node --check assets/admin.js
```

The repeat check covers numeric/unlimited structural and widget paths, ordered content, independent structural groups with 105+7 records, and mixed styles. Preference checks cover migration, shared reads across administrators, off/empty values, allowed fields, authentication, ordered saves, failure retry, and restoration of the numeric field.

For a check against the supplied Services example, pass the local JSON and DOCX paths to `php tests/generation-mapping.php "path/to/template.json" "path/to/document.docx"`. This optional case checks its introductory and contact paragraphs, 18 card titles/bodies, six FAQ questions/answers, Validator scopes, and FAQ-only generation. It runs the real reader and generator with in-memory WordPress stubs and does not create or modify WordPress pages. The sample files remain outside the repository.

Live WordPress/Elementor browser verification remains a manual check: change each setting, wait for its save response, refresh/reopen, and sign in as a second administrator to confirm shared values. Confirm fresh file inputs and that disabled image-pool selections remain saved without being applied to new jobs.
