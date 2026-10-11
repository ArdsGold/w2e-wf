# Historical Changelog

> Archived notes from releases before r2.2.0. The original legacy file was unavailable when this archive was restored, so these entries are reconstructed from retained release references and documentation, not a verbatim copy. The current release notes are in [`CHANGELOG.md`](CHANGELOG.md).

## r2.0.1b — Widget-level repeat scope fix

- Scoped widget-level repeat markers to the relevant parent heading section in the DOCX.
- Kept separate repeatable regions, such as Services and FAQs, from consuming the same records.

## r2.0.1b — Wolf Forge scoped repeatable regions

- Preserved the existing `WFEBPG_*` identifiers while applying the Wolf Forge public-facing name.
- Kept the marker, queue, and saved-template behavior compatible with existing data.

## r2.0.1b — Internal repeaters

- Added internal repeater support for Elementor Toggle widgets.
- Rebuilt Toggle items from matching DOCX H3 headings and following paragraphs instead of cloning the Toggle widget.
- Scoped repeatable Toggle content to the corresponding H2 section and preserved the widget's existing settings.

## r2.0.1b — Inline Text Editor repeat stream

- Supported combined heading and paragraph markers on Text Editor widgets.
- Rendered each matching H3 and its following paragraphs into the Text Editor's `editor` field.

## Generated-page management

- Added a per-row **Move to Trash** action for generated pages.
- Moving a page to Trash removes it from the plugin's generated-page list without permanently deleting the WordPress page.
