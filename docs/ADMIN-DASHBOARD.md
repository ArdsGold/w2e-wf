# Admin Dashboard Guide — r2.2.0

The dashboard has five tabs. Switch tabs without reloading the page.

## Page Generation

Upload DOCX files and select or upload an Elementor JSON template. Pages are queued for background processing. Optional controls include the image pool, parent page, and overwrite setting.

The right-side preview updates automatically when the template changes. It shows the Elementor structure, not a rendered screenshot. Hover over a marked element to view its `data-customID` marker. With no template selected, the preview is empty.

Choose either a saved template or a new upload. To use an uploaded file as a saved template, add it to the library in Template Validator.

The DOCX Compatibility box reports whether the site's PHP has `ZipArchive`. If it does not, the plugin enables the zip-free fallback for your account. The fallback requires PHP zlib.

## Template Image Replacement

Match images in a saved template to Media Library images. Review suggested matches and apply the ones you want. This tool is separate from the Image Pool.

The tab warns when the selected template has unresolved image mappings.

## New Pages

Review generated pages, edit them in WordPress or Elementor, view them, or move them to Trash. Clearing the generated-page table removes only the plugin's tracking records.

A notification appears when a new page is generated. Open this tab to acknowledge it.

## Template Validator

Select or upload a template and choose **Preview & Validate Template** to see its structure and validation results. Add a DOCX to check marker and content compatibility.

The preview shows the Elementor structure. Hover over a marked element to view its `data-customID` marker.

Manage saved templates here. Uploading a template to the library does not generate a page. Renaming a template updates its image mappings; deleting it removes only the saved template.

The tab is orange when the selected template has warnings and red when it has errors. Errors take priority. The status updates from the selected saved template.

## Logs

Filter logs by level or search their messages. The tab turns red when a new error is recorded; opening Logs acknowledges the notification.

## Shared template selection

Page Generation, Template Image Replacement, and Template Validator share the selected saved template. Changing it in one tab updates the others. Uploading to the library selects the new template; renaming or deleting the active template updates or clears the selection.

## Troubleshooting

- If a queue is stuck, choose **Process Queue Now** and check Logs.
- If content is missing, verify its marker and run validation with the same DOCX.
- If an image is not replaced, confirm the mapping is saved for the selected template.

For marker syntax and mapping rules, see [`MARKER-SYSTEM.md`](MARKER-SYSTEM.md). For installation and queue setup, see [`INSTALLATION.md`](INSTALLATION.md).
