# Wolf Forge Elementor Page Generator r2.2.8

Release date: October 30, 2026.

## Flip Box back titles

Page Generation includes **Use heading as back title**, enabled by default. Disable it to preserve each marked Flip Box's original back title, including an empty title. The heading still fills the front title, and paragraphs fill the back description. Front descriptions, styling, and unmarked widgets remain unchanged. The choice applies to normal and repeated Flip Boxes.

## Partial repeat rows

**Expand partial rows to full width** appears in Repeatable Section and is disabled by default. Enable it to divide a partial row equally among the remaining repeat columns: two columns use 50% each; one uses 100%. Full rows retain the configured capacity. Structural repeat columns and legacy partial rows are supported, with existing responsive overrides preserved.

## Saved settings and upgrades

Both choices save automatically and are shared across administrators. Every newly queued job stores its own choices, so later dashboard changes do not change that job. Older jobs without these options retain the previous behavior: copy the heading to the back title and keep partial cards at their usual width. A failed job cannot leak its back-title preference into subsequent jobs.

The plugin header, runtime version, and current documentation report r2.2.8. Earlier release documents retain their original scope. Install the updated plugin and regenerate existing pages to apply new choices. GitHub version checks remain informational; automatic installation is not included.

## Verification

```sh
php tests/generator-options.php
php tests/repeatable-layout.php
php tests/repeatable-limit.php
php tests/widget-compatibility.php
php tests/generation-mapping.php
php tests/admin-settings.php
node tests/admin-settings.js
php tests/queue-worker.php
node tests/queue-processing.js
php tests/phone-linker.php
php tests/version-check.php
node --check assets/admin.js
```

Full-generation checks use a generated DOCX and WordPress storage stubs to verify original and empty back titles, repeated cards, width choices, defaults, and failure isolation. Layout checks cover limits 3/6/7 with 1/2/4/10/11 records, both expansion modes, integer column classes, exact percentages, and prototype preservation. Saved-settings checks cover both checkbox states. PHP syntax checks cover all PHP files. These standalone checks do not claim a hosted Elementor rendering test.
