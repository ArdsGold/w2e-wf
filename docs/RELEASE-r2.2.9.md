# Wolf Forge Elementor Page Generator r2.2.9

Release date: October 30, 2026.

## Fresh GitHub version checks

Every plugin dashboard open or refresh now requests the plugin version from the GitHub repository's `main` branch. The checker no longer reads or writes the previous version transient, which could retain a result for six hours. Requests use a unique query value and a no-cache header to bypass upstream caching.

Matching versions show **Up to date with GitHub**. A newer repository version shows **Update available** with the repository link. Failed requests show an unavailable status and retry on the next dashboard load. Requests retain a three-second timeout, response-size limit, and disabled redirects.

## Upgrade behavior

The displayed installed version comes from the installed plugin files. Checking GitHub does not upgrade the plugin or change that number. Install r2.2.9 on each target site to receive this fix. No page regeneration is required. Generated pages, queued jobs, templates, and shared preferences remain unchanged.

The plugin header, runtime version, and current documentation report r2.2.9. Earlier release documents retain their original scope. Automatic downloading and installation are not included.

## Verification

```sh
php tests/version-check.php
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
node --check assets/admin.js
```

Version-check regressions verify a fresh request on each dashboard render, unique upstream cache keys, detection of a new repository version on the next render, numeric version comparisons, update links, and retries after network, HTTP, or malformed-response failures. PHP syntax checks cover every PHP file. These checks use mocked HTTP responses and do not claim a hosted WordPress test.
