# Installation and Operations

## Requirements

- WordPress 6.0 or newer.
- PHP 7.4 or newer.
- Elementor installed and active.
- PHP `ZipArchive` support for DOCX reading.
- WordPress cron or an equivalent cron trigger for automatic queue processing.

## Installation

1. Back up the existing site.
2. Open **Plugins → Add New → Upload Plugin**.
3. Upload the Wolf Forge Elementor Page Generator ZIP.
4. Activate the plugin.
5. Open **Wolf Forge Page Generator** in the WordPress admin.
6. Upload an Elementor JSON template.
7. Validate the template.
8. Upload a small DOCX test batch.
9. Confirm the generated pages in Elementor.
10. Move to a larger production batch only after the staging test succeeds.

## Template checklist

Before generating:

- Confirm every dynamic widget has a `data-customID` marker.
- Confirm the marker type matches the DOCX heading level.
- Confirm repeatable containers have `|repeat`.
- Confirm the first Heading and Text Editor inside a repeatable parent are the intended dynamic fields.
- Confirm static buttons and decorative content are intentionally unmarked.
- Run Template Validator with a representative DOCX.

## Queue checklist

If jobs do not start automatically:

1. Check whether WordPress cron is running.
2. Open the plugin's Queue section.
3. Use **Process Queue Now**.
4. Review the Logs section for errors.

For high-volume production sites, a real server cron triggering `wp-cron.php` is generally more reliable than relying only on visitor traffic.

## Safe upgrade

The release keeps the existing `WFEBPG` storage identifiers.

Before replacing an older installation:

1. Back up the plugin directory.
2. Back up the database.
3. Back up any saved Elementor JSON templates you cannot recreate.
4. Ensure no generation batch is actively running.
5. Replace the plugin with the new build.
6. Activate only the Wolf Forge build.
7. Confirm the saved template library and queue are visible.
8. Run a one-page test.

Do not activate two copies of the plugin simultaneously.

## Troubleshooting

### A paragraph is not transferred

Check the marker.

For a widget that should receive both an H3 title and associated paragraph content:

```text
data-customID|h3|p
```

For a repeatable parent container:

```text
data-customID|h3|repeat
```

The parent-container model automatically looks for the first Heading and first Text Editor inside the marked parent.

### The wrong H3 content is used

Run Template Validator with the same DOCX. The Mapping Debugger shows the template marker, hierarchy scope, and selected DOCX source.

### Images are not replaced

Confirm that:

- The JSON template is saved in the plugin template library.
- The image exists in the template JSON.
- The replacement exists in the WordPress Media Library.
- The saved image mapping belongs to the selected template.

### Pages are created but styles look wrong

The generator attempts Elementor CSS regeneration after writing Elementor data. If a page still looks wrong, open it once in Elementor and regenerate CSS/files using Elementor's tools.

### Queue remains pending

Use **Process Queue Now** and inspect the logs. On a production server, verify WP-Cron is not disabled or blocked.

## Production recommendation

Always test with one or two DOCX files before running a large batch. Keep the original Elementor JSON template as a known-good backup.
