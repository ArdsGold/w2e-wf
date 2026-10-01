# Changelog

## r2.1.0 — Admin dashboard and workflow release

### Internal refactor and performance maintenance

### Refactored

- Removed eight confirmed-dead private generator helpers that were no longer reachable by the current repeatable-generation pipeline.
- Removed unused local state from non-repeat mapping and template validation traversal.
- Simplified repeatable-section validation to parse each element marker once instead of calling the marker parser twice for the same element.
- Kept all public `WFEBPG_*` helper methods that may be consumed externally, even where the current plugin source has no direct caller.

### Verified

- PHP syntax checks pass for all plugin PHP files.
- JavaScript syntax check passes for `assets/admin.js`.
- No temporary error-test/debug control code was reintroduced.
- Generation, queue, repeatable, image-pool, template-image, validator, notification, and logging contracts were left unchanged.

### Audit notes

See [`CODE-AUDIT-r2.1.0.md`](CODE-AUDIT-r2.1.0.md) for the unused-function/variable audit and optimization notes.

All notable changes to the Wolf Forge Elementor Page Generator are documented here.


### Added

- Five client-side admin tabs: Page Generation, Template Image Replacement, New Pages, Template Validator, and Logs.
- Shared saved-template selection across the three template-related tabs.
- Saved Template library upload, rename, delete, and clear actions from Template Validator.
- Inline saved-template rename UI.
- Image preview/lightbox support in Template Image Replacement.
- Template Image Replacement orange warning indicator for unresolved image mappings.
- Template Validator orange warning indicator and red error indicator.
- New Pages light-green unread notification when a page is generated.
- Logs red unread notification for newly detected error-level entries.
- Log level filtering and message search.
- Release and admin-dashboard documentation.

### Changed

- Improved tab contrast and active-state readability.
- Template names in Saved Templates are visually prioritized over upload dates.
- Page Generation now treats saved-template selection and new JSON upload as mutually exclusive sources.
- Uploading a JSON to the Saved Templates library does not generate a page and automatically becomes the shared selected template.
- Template Image Replacement and Template Validator status indicators are evaluated in the background from the persistent selected template; manual scan/validation is not required for the tab indicators.
- Validator repeatable-marker fallback messaging now explains that element-level cloning will be used when no containing section/container can be identified.

### Preserved

- Existing generation and queue processing.
- Existing repeat-marker behavior and Elementor mapping contracts.
- Existing image-pool and template-image mapping behavior.
- Existing Saved Template storage and user preference data.
- Existing generated-page records and Logs data.
- Existing `WFEBPG_*` internal PHP identifiers, options, AJAX contracts, and hooks.

### Removed

- Temporary developer-only **Test Error Notification** control used during r2.1.0 QA.

## Previous releases

See [`CHANGELOG-LEGACY.md`](CHANGELOG-LEGACY.md) for the preserved historical changelog.
