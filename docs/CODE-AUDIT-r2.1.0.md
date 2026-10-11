# Historical Code Audit — r2.1.0

> This audit describes the r2.1.0 source and maintenance work. It is not a current inventory of r2.2.0 code.

## Scope

The r2.1.0 audit removed private implementation code that had no internal callers, removed unused local state, and reduced duplicate marker parsing without changing the generator contract.

## Private methods removed in r2.1.0

- `next_block()`
- `next_block_by_heading_level()`
- `next_block_with_content()`
- `remove_unused_repeatable_columns()`
- `clear_unused_repeatable_columns()`
- `populate_content_pair_root()`
- `clone_repeatable_widgets()`
- `remove_at_path()`

These methods belonged to superseded implementation paths and had no callers in the source at that time.

## Local variables removed in r2.1.0

- `$cursor_key` in `populate_nonrepeat()`
- `$custom` in `analyze_elements()`

Both variables were assigned but not read.

## Optimization

`WFEBPG_Generator::analyze_elements()` was changed to parse each element's marker once and reuse the normalized value when checking repeatable state.

## Compatibility considerations at the time

The audit retained public helpers with no internal call sites because external integrations may call public methods. WordPress callbacks and event-driven JavaScript were not treated as unused based only on direct-reference counts.

The Mapping Debugger was retained because it is part of Template Validator functionality.

## Validation recorded for r2.1.0

- PHP syntax checks on plugin PHP files.
- `node --check assets/admin.js`.
- Static searches for function references and temporary debug controls.
- Checks that generation, queue, repeat, image-pool, template-image, validator, notification, and logging behavior remained in place.
