# Installation and Operations

## Requirements

- WordPress 6.0 or newer.
- PHP 7.4 or newer.
- Elementor installed and active.
- PHP `ZipArchive`, or PHP zlib for the zip-free DOCX fallback.
- WordPress cron or an equivalent cron trigger for automatic queue processing.

## Installation

1. Back up the existing site.
2. Open **Plugins → Add New → Upload Plugin**.
3. Upload the Wolf Forge Elementor Page Generator ZIP.
4. Activate the plugin.
5. Open **Wolf Forge Page Generator** in the WordPress admin.
   - Page Generation shows whether the site's PHP has `ZipArchive`.
   - If it is missing, the plugin enables the fallback for all administrators. The fallback requires PHP zlib.
6. Upload an Elementor JSON template.
7. Validate the template.
8. Upload a small DOCX test batch.
9. Confirm the generated pages in Elementor.
10. Run larger batches only after the staging test succeeds.

## Template checklist

Before generating:

- Add a `data-customID` marker to every dynamic element.
- Match marker types to DOCX heading levels.
- Add `|repeat` to repeatable elements.
- Check that the first Heading and Text Editor inside each repeatable parent are the intended fields.
- Leave static and decorative elements unmarked.
- Validate with a representative DOCX.

## Queue checklist

If jobs do not start automatically:

1. Check whether WordPress cron is running.
2. Open the plugin's Queue section.
3. Use **Process Queue Now**.
4. Review the Logs section for errors.

For large production sites, a server cron that triggers `wp-cron.php` is more reliable than visitor-triggered cron.

## Safe upgrade

This release keeps the existing `WFEBPG` storage identifiers.

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

### Paragraphs are missing

Check the marker.

To map an H3 and its following paragraphs to one widget:

```text
data-customID|h3|p
```

For a repeatable parent:

```text
data-customID|h3|repeat
```

The generator fills the first Heading and Text Editor inside the marked parent.

### The wrong H3 content is used

Validate with the same DOCX. The Mapping Debugger shows each marker's scope and DOCX source.

### Images are not replaced

Confirm that:

- The JSON template is saved in the plugin template library.
- The image exists in the template JSON.
- The replacement exists in the WordPress Media Library.
- The saved image mapping belongs to the selected template.

### Generated page styles look wrong

The generator regenerates Elementor CSS after writing page data. If styles still look wrong, open the page in Elementor and regenerate CSS/files.

### Queue remains pending

Use **Process Queue Now** and inspect the logs. On a production server, verify WP-Cron is not disabled or blocked.

## Production recommendation

Test with one or two DOCX files before a large batch. Keep a backup of the original Elementor JSON.

## Updating to r2.2.2

Install the updated plugin files; both the WordPress plugin version and `WFEBPG_VERSION` are r2.2.2. Open the dashboard using the administrator whose existing template, image, and fallback preferences should seed the shared settings. Settings then save automatically for all administrators. File uploads must be selected again after refresh. Existing queued jobs retain their captured options.

Check the No limit control and persistence behavior described in [the release notes](RELEASE-r2.2.2.md).
