# Admin Dashboard Guide — r2.1.0

## Overview

Version 2.1.0 reorganizes the Wolf Forge Elementor Page Generator admin screen into five client-side tabs. The change is intentionally UI-focused: the existing generation, repeat-marker, queue, template, image-pool, image-mapping, validation, and page-processing contracts remain in place.

## Tabs

### Page Generation

Contains the existing page-generation workflow, including DOCX uploads, template selection, saved templates, image-pool controls, parent-page settings, overwrite behavior, and queue processing.

The Saved Elementor JSON selector and the Upload New JSON Template control are mutually exclusive. When a saved template is selected, the upload control is disabled. When a new JSON template is chosen, the saved-template selector is disabled until the upload is cleared.

### Template Image Replacement

Uses the same shared selected template as Page Generation and Template Validator. The detailed image scan remains available through **Scan Template Images**. Image previews can be opened in a larger lightbox view.

The tab can show an orange warning when the selected template has unresolved image mappings. This status is calculated from the saved template without requiring the user to click the scan button.

### New Pages

Contains the generated-page table and its existing actions: Edit, Edit with Elementor, View, Move to Trash, and clearing the generated-page To-Do table.

When a new page is successfully generated, the tab receives a light-green notification. The notification is acknowledged when the New Pages tab is opened. Background queue completion is also detected while the dashboard remains open.

### Template Validator

Contains the template validator and Saved Template management. A template can be uploaded directly into the Saved Templates library without generating a page. Saved templates can be renamed or deleted from this area.

The tab status has three states:

- **Normal:** no validator warnings or errors.
- **Orange:** one or more validator warnings.
- **Red:** one or more validator errors; red takes priority if warnings and errors are both present.

The status is calculated from the persistent selected template in the background. The **Validate Template** button remains available for the detailed validation report.

### Logs

Contains the existing plugin log table plus level filtering and message search.

A newly detected error-level log entry turns the Logs tab red. Opening Logs acknowledges the notification. Historical errors do not permanently keep the tab red once acknowledged.

## Shared template state

Page Generation, Template Image Replacement, and Template Validator use one shared selected saved-template value. Changing the selection in any of those areas updates the others without a full admin-page reload.

Uploading a new template to the Saved Templates library automatically selects it as the shared template. Renaming the active template updates the shared selection. Deleting the active template clears the shared selection.

## Tab switching and performance

Tabs are switched client-side. No WordPress page navigation is required to move between the five sections. The last active tab is remembered in the browser session when possible.

Status polling is limited to lightweight dashboard notifications. It does not replace the existing generation or validation logic.

## Status colors

| Tab | State | Meaning |
|---|---|---|
| Template Image Replacement | Orange | Selected template has unresolved image-replacement work. |
| Template Validator | Orange | Selected template has validation warnings. |
| Template Validator | Red | Selected template has validation errors. |
| New Pages | Light green | A newly generated page has not yet been acknowledged by opening New Pages. |
| Logs | Red | A newly detected error-level log entry has not yet been acknowledged by opening Logs. |

## Release QA checklist

Before production deployment, verify:

1. Switch between all five tabs without a page reload.
2. Select a saved template in each of the three template selectors and confirm they remain synchronized.
3. Upload a JSON to the Saved Templates library and confirm it becomes the shared selection without generating a page.
4. Rename and delete a saved template from Template Validator.
5. Confirm unresolved template images turn the Image Replacement tab orange without clicking Scan.
6. Confirm validator warnings turn Validator orange and validator errors turn it red without clicking Validate.
7. Generate a page and confirm New Pages turns light green until opened.
8. Create a real error-level log entry in a staging environment and confirm Logs turns red until opened.
9. Open image previews in Template Image Replacement.
10. Verify generation, repeat markers, queue processing, and image mapping still behave as before.

## Developer note

The temporary **Test Error Notification** control used during r2.1.0 development is intentionally removed from the release build. Error notifications should be tested with a controlled staging error or automated integration test rather than a production-facing debug control.
