# Admin Dashboard Guide — r2.3.0

The dashboard has five tabs. Switch tabs without reloading the page.

The installed version appears below the dashboard title. It is compared with the plugin header on the GitHub repository's `main` branch. A newer repository version shows **Update available** with a link to the repository in a new tab. Matching versions show **Up to date with GitHub**; a newer local version is identified as ahead of GitHub. Every dashboard open or refresh checks GitHub again, bypassing saved version results and upstream caching. Failed checks show an unavailable status and retry on the next load; the request has a three-second timeout. The installed version identifies the local plugin files and only changes after an upgrade. This only checks versions and does not install updates.

## Page Generation

Upload DOCX files and select or upload an Elementor JSON template. Pages are queued for background processing. Optional controls include the image pool, parent page, and overwrite setting.

In Queue, click **Process All Queue Now** once to process the waiting jobs in successive batches. Keep the dashboard open; switching its tabs is fine. Progress reports handled jobs, remaining jobs, and failures in this run. Failed jobs are logged and do not prevent later jobs from running. If another worker owns the queue lock, the dashboard waits and retries. A request failure stops the manual loop and enables the button so you can continue after checking Logs. Refresh after completion to see the updated generated-page list. Closing or refreshing the dashboard ends the manual loop; normal WP-Cron processing is unaffected.

**Cancel Queue** removes all waiting jobs and stops this dashboard's manual loop. Any job already being generated finishes normally; generated pages and saved templates are not deleted. Cancellation is logged. New jobs can be queued afterward. If cancellation cannot be confirmed, the dashboard shows a retry message and enables the controls again.

The right-side preview updates automatically when the template changes. It shows the Elementor structure, not a rendered screenshot. Hover over a marked element to view its normalized marker. With no template selected, the preview is empty.

Paragraph markers can populate Heading widgets as well as Text Editors. A section's sole marked H1/H2 is used as its scope even when the FAQ widget comes before that heading in the exported JSON. The visual layout stays in its original order. Regenerate existing pages to apply mapping fixes.

Choose either a saved template or a new upload. To use an uploaded file as a saved template, add it to the library in Template Validator.

The DOCX Compatibility box reports whether the site's PHP has `ZipArchive`. If it does not, the plugin enables the zip-free fallback for all administrators. The fallback requires PHP zlib.

## Markers in Elementor Free

Select a widget or structural element and open **Advanced > Wolf Forge > Wolf Forge Marker**. Enter values such as `h3|link`, `h3|p`, or `repeat`. The plugin must be active while editing to show this control. The value is saved with that element and exported with the template.

You can instead use **Advanced > CSS Classes**, such as `wf-h3 wf-link`, alongside your styling classes. All existing marker types work through either input. The dedicated field takes priority when nonblank, followed by legacy attributes, then CSS classes. See [Marker System](MARKER-SYSTEM.md) for equivalent examples. No Elementor Pro subscription is required for these inputs.

## Automatic shared settings

Changes save automatically without submitting a form. Settings are shared across administrators on this site and restored when reopening or refreshing the dashboard. Wait for **Settings saved** after changing dashboard controls. A save failure is shown inline; changing a setting retries unsaved preferences.

Remembered settings include Widgets per section, No limit, partial-row expansion, Flip Box back-title copying, Parent Page, Overwrite matching existing pages, Media Library Image Pool enablement and selected images, zip-free fallback, selected saved template, saved image mappings, active tab, log level, and log search. Clearing an image selection or unchecking a checkbox saves that empty/off state. A disabled image pool retains its selection for later use and does not attach those images to new jobs.

The first administrator to load an existing preference seeds its shared value from their previous saved setting. Other administrators subsequently read the shared value. An already-open dashboard loads another administrator's updates on refresh. Notifications and temporary preview ownership remain personal. Browser file inputs must be selected again after refresh; queue and delete actions are never repeated automatically.

## Repeatable Section

**No limit** appears beside **Widgets per section**. Check it to disable and gray out the number input while keeping every widget in each repeatable group in one generated section. Separate groups keep their own sections. Uncheck it to restore the previous number. The number and checkbox both persist, including across refreshes while No limit is checked.

Unlimited mode applies to structural and legacy/widget repeat groups and supports more than 100 items. With No limit off, the numeric 1–100 capacity still controls generated rows. Existing template layout rules determine how widgets render inside the section.

**Expand partial rows to full width** is off by default. When enabled, a row with fewer widgets than the configured capacity shares its width equally among the remaining cards: two cards use 50% each; one uses 100%. Full rows keep the configured capacity. This applies to generated repeat columns, including legacy partial rows; existing responsive overrides remain in place.

## Automatic page links

In the Elementor template, add `data-customID|h3|link` to a supported title/link widget to fill its title from the DOCX and link to a published page with the same title. Use `data-customID|link` to keep and match the existing template title. Capitalization and extra spaces are ignored. Missing or duplicate-title matches leave the link blank; duplicates appear in Logs. Existing URLs and dynamic links are preserved (`#` counts as a blank placeholder).

Pages generated later in the queue can fill earlier unresolved links automatically after generation completes. Link options remain in the template; no dashboard setting is required. See [Marker System](MARKER-SYSTEM.md#link-a-widget-title-to-a-matching-page) for supported widgets and repeat examples.

## Flip Boxes

**Use heading as back title** is on by default. Turn it off to preserve the template's original back title, including an empty title. The heading still fills the front title and paragraphs still fill only the back description. Unmarked widgets remain unchanged.

Both options save automatically for all administrators. Queued jobs keep the choices made when queued, even if the dashboard settings change later. Regenerate existing pages to apply new choices.

## Template Image Replacement

Match images in a saved template to Media Library images. Review suggested matches and apply the ones you want. This tool is separate from the Image Pool.

The tab warns when the selected template has unresolved image mappings.

## New Pages

Review generated pages, edit them in WordPress or Elementor, view them, or move them to Trash. Clearing the generated-page table removes only the plugin's tracking records.

A notification appears when a new page is generated. Open this tab to acknowledge it.

## Template Validator

Select or upload a template and choose **Preview & Validate Template** to see its structure and validation results. Add a DOCX to check marker and content compatibility.

The preview shows the Elementor structure. Hover over a marked element to view its normalized marker.

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

## Refresh generated links

In **Page Generation**, **Refresh generated links** scans all Elementor pages, including manually created pages, for supported widgets marked with `link`. It fills blank marked links using matching published titles. Unmarked widgets and non-Elementor pages remain unchanged. Manually created pages are not added to the generated-page table or given generated-page styling. Use the action to update tracked title links and identify older marked links with a verified matching title and current URL. New links follow target page IDs when slugs or parents change, including descendant URL changes. Manual overrides stop tracking. Unverified older URLs remain unchanged and appear in Logs. Clear the marked widget's link field and refresh again to match its title, or regenerate plugin-created pages from a template with blank link fields. A manually entered URL identical to the verified title/permalink pair can be adopted by this explicit action. Automatic updates preserve untracked populated URLs.

## Phone links

Automatic phone processing leaves Button widgets with an existing native `tel:` link untouched. For other widgets, it leaves their settings untouched when a native `tel:` destination matches a number in the widget's text. Matching handles punctuation, spaces, and an optional US +1 prefix; telephone URI extensions do not change the base-number match. Existing HTML anchors keep their original text, link, colors, and decoration.

Unlinked numbers continue to become underlined phone links with inherited text color, including in buttons without a `tel:` link. These rules apply to phone processing after marker-based content mapping. Regenerate existing pages from their template to apply the new behavior; already-generated markup is not removed automatically.
