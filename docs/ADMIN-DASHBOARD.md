# Admin Dashboard Guide — r2.2.7

The dashboard has five tabs. Switch tabs without reloading the page.

The installed version appears below the dashboard title. It is compared with the plugin header on the GitHub repository's `main` branch. A newer repository version shows **Update available** with a link to the repository in a new tab. Matching versions show **Up to date with GitHub**; a newer local version is identified as ahead of GitHub. Successful checks cache for six hours. Failed checks show an unavailable status and cache for 15 minutes; the dashboard remains usable. This only checks versions and does not install updates.

## Page Generation

Upload DOCX files and select or upload an Elementor JSON template. Pages are queued for background processing. Optional controls include the image pool, parent page, and overwrite setting.

In Queue, click **Process All Queue Now** once to process the waiting jobs in successive batches. Keep the dashboard open; switching its tabs is fine. Progress reports handled jobs, remaining jobs, and failures in this run. Failed jobs are logged and do not prevent later jobs from running. If another worker owns the queue lock, the dashboard waits and retries. A request failure stops the manual loop and enables the button so you can continue after checking Logs. Refresh after completion to see the updated generated-page list. Closing or refreshing the dashboard ends the manual loop; normal WP-Cron processing is unaffected.

**Cancel Queue** removes all waiting jobs and stops this dashboard's manual loop. Any job already being generated finishes normally; generated pages and saved templates are not deleted. Cancellation is logged. New jobs can be queued afterward. If cancellation cannot be confirmed, the dashboard shows a retry message and enables the controls again.

The right-side preview updates automatically when the template changes. It shows the Elementor structure, not a rendered screenshot. Hover over a marked element to view its `data-customID` marker. With no template selected, the preview is empty.

Paragraph markers can populate Heading widgets as well as Text Editors. A section's sole marked H1/H2 is used as its scope even when the FAQ widget comes before that heading in the exported JSON. The visual layout stays in its original order. Regenerate existing pages to apply mapping fixes.

Choose either a saved template or a new upload. To use an uploaded file as a saved template, add it to the library in Template Validator.

The DOCX Compatibility box reports whether the site's PHP has `ZipArchive`. If it does not, the plugin enables the zip-free fallback for all administrators. The fallback requires PHP zlib.

## Automatic shared settings

Changes save automatically without submitting a form. Settings are shared across administrators on this site and restored when reopening or refreshing the dashboard. Wait for **Settings saved** after changing dashboard controls. A save failure is shown inline; changing a setting retries unsaved preferences.

Remembered settings include Widgets per section, No limit, Parent Page, Overwrite matching existing pages, Media Library Image Pool enablement and selected images, zip-free fallback, selected saved template, saved image mappings, active tab, log level, and log search. Clearing an image selection or unchecking a checkbox saves that empty/off state. A disabled image pool retains its selection for later use and does not attach those images to new jobs.

The first administrator to load an existing preference seeds its shared value from their previous saved setting. Other administrators subsequently read the shared value. An already-open dashboard loads another administrator's updates on refresh. Notifications and temporary preview ownership remain personal. Browser file inputs must be selected again after refresh; queue and delete actions are never repeated automatically.

## Repeatable Section

**No limit** appears beside **Widgets per section**. Check it to disable and gray out the number input while keeping every widget in each repeatable group in one generated section. Separate groups keep their own sections. Uncheck it to restore the previous number. The number and checkbox both persist, including across refreshes while No limit is checked.

Unlimited mode applies to structural and legacy/widget repeat groups and supports more than 100 items. With No limit off, the numeric 1–100 capacity still controls generated rows. Existing template layout rules determine how widgets render inside the section.

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

- If a queue is stuck, choose **Process All Queue Now**, keep the dashboard open, and check Logs.
- If content is missing, verify its marker and run validation with the same DOCX.
- If an image is not replaced, confirm the mapping is saved for the selected template.

For marker syntax and mapping rules, see [`MARKER-SYSTEM.md`](MARKER-SYSTEM.md). For installation and queue setup, see [`INSTALLATION.md`](INSTALLATION.md).
