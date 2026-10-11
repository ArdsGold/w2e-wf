# Historical Release: Wolf Forge Elementor Page Generator r2.1.0

> This document records the r2.1.0 release. It is historical; the current plugin release is r2.2.0.

## Release summary

r2.1.0 reorganized the existing admin functionality into a client-side dashboard while preserving generation and processing behavior.

## Included

- Five-tab admin dashboard.
- Shared saved-template selection across generation, image replacement, and validation.
- Saved Template library management from Template Validator, including upload, rename, delete, and clear.
- Inline rename and image-preview lightbox workflows.
- Template Image Replacement and Template Validator status indicators.
- New Pages and Logs notifications.
- Log filtering and search.
- Clearer repeatable-marker validator messaging.

## Compatibility

The release preserved existing `WFEBPG_*` identifiers, stored options, AJAX contracts, queue behavior, saved templates, image mappings, logs, and generated-page records.

## Removed development-only functionality

The temporary Logs **Test Error Notification** control used during development and QA was removed.

## Upgrade guidance

1. Back up the plugin and production data.
2. Install r2.1.0 as a replacement for the existing Wolf Forge build.
3. Do not activate two copies at the same time.
4. Confirm that saved templates and the last selected template are intact.
5. Run the release checks on staging before processing a production batch.

## Internal maintenance

The release also removed private generator helpers and unused local variables identified by the code audit. Template validation was adjusted to parse each marker once during element analysis.

See [`CODE-AUDIT-r2.1.0.md`](CODE-AUDIT-r2.1.0.md) for the audit details.
