# Wolf Forge Elementor Page Generator r2.1.0

## Release summary

This release is a user-interface and workflow release. It reorganizes the existing admin functionality into a single, client-side dashboard while preserving the plugin's generation and processing behavior.

## Included

- Five-tab admin dashboard.
- Shared saved-template selection across generation, image replacement, and validation.
- Saved Template library management from Template Validator.
- Inline rename/delete workflow.
- Image preview lightbox.
- Template Image Replacement warning indicator.
- Template Validator warning/error indicators.
- New Pages notification indicator.
- Logs error notification indicator.
- Logs filtering and search.
- Clearer repeatable-marker validator messaging.
- Release documentation and QA checklist.

## Compatibility

The release preserves the existing `WFEBPG_*` PHP identifiers, stored options, AJAX contracts, queue behavior, saved templates, image mappings, logs, and generated-page records. No migration of those internal contracts is introduced by r2.1.0.

## Removed development-only functionality

The temporary Logs **Test Error Notification** button used during development and QA is removed before release.

## Upgrade guidance

1. Back up the current plugin and production data.
2. Install r2.1.0 as a replacement for the existing Wolf Forge Elementor Page Generator build.
3. Do not run two copies of the plugin simultaneously.
4. Confirm the Saved Templates library and last selected template are intact.
5. Run the release QA checklist on staging before processing a production batch.

## Internal code cleanup included in r2.1.0

The r2.1.0 source also includes the completed code audit and maintenance refactor:

### Removed confirmed-dead private methods

- `next_block()`
- `next_block_by_heading_level()`
- `next_block_with_content()`
- `remove_unused_repeatable_columns()`
- `clear_unused_repeatable_columns()`
- `populate_content_pair_root()`
- `clone_repeatable_widgets()`
- `remove_at_path()`

### Removed confirmed-unused local variables

- `$cursor_key`
- `$custom`

### Optimization

Template Validator element analysis now parses each element marker once and reuses the normalized result when determining repeatable state, avoiding duplicate marker parsing during traversal.

### Compatibility

Public `WFEBPG_*` helper methods without current internal callers were retained for external compatibility. Marker syntax, repeatable behavior, image mapping, queue processing, saved-template storage, AJAX contracts, notifications, and generated-page behavior remain unchanged.

See [`CODE-AUDIT-r2.1.0.md`](CODE-AUDIT-r2.1.0.md) for the detailed audit.
