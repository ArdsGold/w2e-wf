# Wolf Forge Elementor Page Generator r2.2.6

Release date: October 30, 2026.

## Phone-link colors

New automatically generated phone anchors use `color: inherit !important` so their text follows the immediate surrounding color, including on hover. Their `tel:` destinations and displayed numbers remain unchanged. Existing anchors retain their styling. The rule is included in the generated markup and does not depend on the page-layout stylesheet.

Regenerate pages to apply this styling to automatically linked numbers. The existing limitations remain: nested FAQ/repeater text is not processed by the phone linker, and fields that escape HTML may not render clickable links.

## GitHub version status

The installed version appears below the dashboard title. The plugin compares it with the `Version:` header on the `main` branch of `ArdsGold/w2e-wf`. A newer repository version produces a link to `https://github.com/ArdsGold/w2e-wf`; matching, ahead-of-GitHub, and unavailable statuses are also handled.

The WordPress HTTP request has a three-second timeout and a bounded response size. Successful responses cache for six hours, and failures cache for 15 minutes. Numeric comparisons ignore the `r` prefix. The installed version is compared afresh against the cached remote version.

This is a status check and repository link. It does not download, install, or automatically update the plugin. The proposed integration with WordPress's updater and GitHub Releases is not included.

## Compatibility and upgrade

The plugin header, `WFEBPG_VERSION`, README, and current dashboard documentation use r2.2.6. Existing settings, saved templates, queue formats, and r2.2.5 widget compatibility are retained. The r2.2.5 release notes keep their original scope.

## Verification

```sh
php tests/phone-linker.php
php tests/version-check.php
php tests/widget-compatibility.php
php tests/generation-mapping.php
php tests/repeatable-limit.php
php tests/admin-settings.php
node tests/admin-settings.js
node --check assets/admin.js
```

PHP syntax checks cover every PHP file. Regression checks cover inherited phone-link styles and preserved anchors; version comparisons, caching, update links, and network failures; widget mapping; independent and mixed repeat groups; and shared settings. Live WordPress/Elementor browser verification is not claimed.
