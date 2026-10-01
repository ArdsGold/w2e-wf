# Development Guide

## Principles

1. Prefer readable PHP over clever one-liners.
2. Preserve existing WordPress hooks and stored keys unless a migration is explicitly required.
3. Keep Elementor JSON transformations narrow and predictable.
4. Avoid changing generation behavior during branding-only releases.
5. Validate both Generic and Unique generation after changes.

## PHP style

Use normal spacing and explicit braces for control flow:

```php
if (!defined('ABSPATH')) {
    exit;
}

if ($items) {
    foreach ($items as $item) {
        // Work with one item.
    }
}
```

Prefer descriptive variable names when adding new code. Existing short variable names may remain where changing them would create a noisy, high-risk refactor.

Add a short comment when behavior is not obvious from the code itself. Do not comment every line.

## Compatibility rule

Do not rename these merely to match the new product name:

```text
WFEBPG_
wfebpg_
_wfebpg_
```

They are part of the plugin's existing WordPress data and integration surface.

If a future major release needs new identifiers, introduce compatibility aliases or a migration rather than silently abandoning old data.

## Template changes

When modifying the generator's Elementor behavior:

1. Test the marker in a minimal Elementor JSON template.
2. Test it in a realistic production template.
3. Test both Text Editor and Icon Box destinations where applicable.
4. Test repeatable content with one, two, and multiple items.
5. Test partial repeatable rows.
6. Test Toggle/FAQ content.
7. Test Media Library image pools if image handling changed.

## Required QA commands

Run PHP syntax checks against every PHP file:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

A successful result should report `No syntax errors detected` for every file.

## Packaging QA

Before creating the ZIP:

- Confirm the package root is `wolf-forge-elementor-page-generator/`.
- Confirm the main file is `wolf-forge-elementor-page-generator.php`.
- Confirm the plugin header version matches the release being packaged (currently `r2.0.1`).
- Confirm README and CHANGELOG agree with the version.
- Remove temporary files and local test artifacts.
- Verify the ZIP contains only the plugin source and documentation.

## Release checklist for r2.0.1

- [x] Product name changed to Wolf Forge Elementor Page Generator.
- [x] Version changed to r2.0.1.
- [x] Canonical markers documented as `h1`, `h2`, `h3`, `p`, `repeat`, and `step`.
- [x] Legacy marker aliases remain supported.
- [x] Internal WFEBPG compatibility identifiers retained.
- [x] Internal marker terminology cleaned up.
- [x] README and marker documentation updated.
- [x] PHP syntax validation performed before packaging.
