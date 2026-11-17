# Wolf Forge Elementor Page Generator r2.3.0

Release date: November 18, 2026.

## Matching-title page links

Use `data-customID|h3|link` on a supported widget to fill its title from a DOCX H3 and populate its native Link field with the matching published page URL. Use `data-customID|link` to match a title already in the template. Combined content and repeat markers also work, such as `data-customID|h3|p|repeat|link`.

Matching searches published pages on this WordPress site, ignoring capitalization and extra whitespace. Visible text is normalized for HTML tags and entities; punctuation, accents, and word order must otherwise match. Drafts, private pages, posts, and the source page are excluded. Missing and duplicate-title matches stay blank; duplicates are logged with matching page IDs. Existing URLs and dynamic links are preserved, while `#` counts as a blank placeholder.

Supported native destinations include Elementor Heading, Icon Box, Image Box, Button, Flip Box, and Icon List; ElementsKit Heading and Icon Box; and JKit Heading and Icon Box. The [Marker System](MARKER-SYSTEM.md#link-a-widget-title-to-a-matching-page) lists their fields and behavior.

Unresolved sources are tracked in post metadata and retried after later page generations, including separate queue batches. This lets a target generated later fill an earlier widget's blank link. Updated sources have their Elementor HTML cache cleared and CSS regenerated. The feature does not continuously synchronize links after target renames or deletions. The Template Validator counts link markers and warns about unsupported widgets.

## Phone links and dashboard icon

Phone links created during generation are underlined and retain their surrounding text color. Existing anchors keep their styling. The dashboard menu uses `pluginIcon.png` at 20 by 20 pixels; its URL includes the image modification time so replacing the image loads the new icon after refresh.

## Upgrading

The plugin header, runtime version, and current documentation report r2.3.0. Earlier release notes retain their original scope. Saved preferences, templates, and queued-job formats remain compatible. Regenerate existing pages to apply new link markers or phone-link styling. GitHub checks run on dashboard open/refresh; automatic downloading and installation are not included.

## Verification

```sh
php tests/title-linker.php
php tests/phone-linker.php
php tests/version-check.php
php tests/generator-options.php
php tests/generation-mapping.php
php tests/widget-compatibility.php
php tests/repeatable-limit.php
php tests/repeatable-layout.php
php tests/admin-settings.php
node tests/admin-settings.js
php tests/queue-worker.php
node tests/queue-processing.js
node --check assets/admin.js
```

The regression suite and PHP syntax checks passed before publication. Local browser testing on krakentesting.local with Elementor 4.3.4 verified real DOCX/template uploads, queue processing, rendered links, clicking destinations, preserved colors/manual links, and blank missing/duplicate/unpublished matches. A target generated in a later queue run filled an earlier normal heading, repeated heading, and list item after frontend refresh. Both test generation runs reported zero failed jobs. JKit was not installed locally; its field mappings were verified by standalone tests. The replacement dashboard icon was also verified in the browser. Test pages and templates remain on the local site for review at the user's request.
