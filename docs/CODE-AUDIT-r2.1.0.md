# Code Audit — r2.1.0

## Scope

This audit covers the r2.1.0 release source immediately before the r2.1.0 maintenance refactor. The goal was to identify code that is provably unused inside the plugin, remove only safe dead private implementation code, and make a small low-risk performance improvement without changing the generator contract.

## Confirmed unused private functions removed

The following private `WFEBPG_Generator` methods had no callers anywhere in the plugin source and were removed:

- `next_block()`
- `next_block_by_heading_level()`
- `next_block_with_content()`
- `remove_unused_repeatable_columns()`
- `clear_unused_repeatable_columns()`
- `populate_content_pair_root()`
- `clone_repeatable_widgets()`
- `remove_at_path()`

These were legacy/alternate implementation paths that had been superseded by the current structured-document, scoped-repeatable, and repeatable-group logic. They were private methods, so removing them does not remove a documented external API.

## Confirmed unused local variables removed

Two local variables were confirmed to be assigned but never read:

- `$cursor_key` in `populate_nonrepeat()`
- `$custom` in `analyze_elements()`

Removing them has no behavioral effect.

## Public methods intentionally retained

The following public helper methods currently have no direct caller elsewhere in the plugin source:

- `WFEBPG_Template::repeatable_scope_parent_ordinal()`
- `WFEBPG_Template::marker_counts()`

They are intentionally retained because public methods can be consumed by custom integrations, snippets, tests, or future plugin code. They should not be classified as safe dead code solely from an internal call-site search.

## Optimization performed

`WFEBPG_Generator::analyze_elements()` previously asked `WFEBPG_Template` to parse an element's marker through `is_repeatable()` and then separately through `custom_id()`. The traversal now parses the normalized marker once and reads the repeatable flag directly.

This is a small optimization, but the validator recursively scans the complete Elementor tree, so avoiding duplicate marker parsing reduces unnecessary work on large templates.

## Other audit findings

### No confirmed unused PHP functions remain

After the refactor, the remaining private PHP methods have at least one internal caller. Public methods and WordPress callbacks were evaluated separately because external hooks can call them without a direct source-level call site.

### WordPress callbacks are not dead code

Functions registered through `add_action()`, `add_filter()`, `admin_post_*`, and `wp_ajax_*` were not treated as unused merely because they are not called with a normal PHP function call. WordPress invokes these functions dynamically.

### JavaScript functions are event-driven

The admin JavaScript contains functions used by click/change/load callbacks and therefore cannot be reliably classified as unused by simple function-name counts. They were retained unless a function had no registration or call-site evidence.

### Debug/mapping code is intentional

The Mapping Debugger is part of Template Validator functionality and is not temporary error debugging. It remains in the release. The temporary **Test Error Notification** control remains removed.

## Validation

The refactored source was checked with:

- `php -l` on every PHP file.
- `node --check assets/admin.js`.
- Static function/reference scan across PHP and JavaScript source.
- Search for temporary error-test controls and development debug calls.

No syntax errors were detected.

## Deliberately not changed

The refactor does not alter:

- Marker syntax.
- `h1`, `h2`, `h3`, `p`, and `repeat` mapping behavior.
- `h3|p|repeat` inline Text Editor behavior.
- Parent-container repeatable behavior.
- Elementor Toggle internal repeaters.
- Image pool behavior.
- Template image mapping.
- WP-Cron queue behavior.
- Saved template storage.
- Notification state tracking.
- AJAX action names/nonces.
- Existing WordPress option names.
