# Wolf Forge Elementor Page Generator r2.2.7

Release date: October 30, 2026.

## Repeat-column layout correction

Limits such as three previously wrote `33.333333...` into `_column_size` and cleared `_inline_size`. Elementor turned this into a fractional `elementor-col-*` class with no normal column-width rule. Columns could collapse, leaving Flip Boxes with zero width and overlapping text.

Structural rows and legacy partial-row centering now use an integer `_column_size` for the CSS class and preserve the exact percentage in `_inline_size`. Partial rows keep the same card width as full rows and remain centered. Legacy centering also retains its updated width settings instead of discarding them. Card order, images, text styling, and responsive overrides remain intact.

Install the updated plugin on the target site and regenerate affected pages. The fix does not rewrite existing hosted pages automatically.

## Process All Queue Now

The manual queue button requests sequential batches until the queue is empty. Each request handles at most three jobs with the existing worker time guard and lock. Progress shows handled, remaining, and failed jobs. An active worker causes a wait/retry; failed jobs are logged and subsequent jobs continue. Request failures stop the loop and allow retry. Jobs enqueued during generation are retained.

Keep the dashboard open until completion; switching its tabs is fine. Closing or refreshing ends the manual loop. Ordinary WP-Cron remains available. Refresh after completion to see updated generated-page and log tables. Progress counters describe the current manual run.

## Cancel Queue

Cancel Queue clears waiting jobs and stops the dashboard's manual processing loop. An already-running job finishes normally. Generated pages, saved templates, and upload files are retained. Cancellation is logged and protected by administrator capability and nonce checks. The worker refreshes its cached queue between jobs to observe cancellation from another request. The browser clears scheduled requests and ignores late responses from its previous run.

New jobs can be queued afterward. A failed cancellation request displays a retry message rather than claiming success. Admin JavaScript uses its modification time for cache invalidation so changed queue controls load when the dashboard is refreshed.

## Compatibility

The plugin header, `WFEBPG_VERSION`, README, and current dashboard guide report r2.2.7. Stored settings, templates, and queue formats remain compatible. Earlier release documents retain their original scope. GitHub version checks remain informational; automatic installation is not included.

## Verification

```sh
php tests/repeatable-layout.php
php tests/repeatable-limit.php
php tests/queue-worker.php
node tests/queue-processing.js
php tests/widget-compatibility.php
php tests/generation-mapping.php
php tests/phone-linker.php
php tests/version-check.php
php tests/admin-settings.php
node tests/admin-settings.js
node --check assets/admin.js
```

PHP syntax checks cover every PHP file. Layout regressions cover limits 3/6/7 with 4/10/11 records, exact custom widths, integer classes, preserved prototypes, and centered partial rows. Pass the supplied template as an optional argument to `tests/repeatable-layout.php` to check its exported settings. Queue checks cover complete drain, concurrent enqueue, failures, locks, authorization, cancellation during generation, stale cache, cleared timers, and ignored late responses.

Chrome DOM inspection of the affected hosted page confirmed zero-width Flip Boxes before a temporary width preview and approximately 615-pixel cards afterward. The preview was removed; no hosted page was saved or updated. Live end-to-end queue processing is not claimed by the standalone tests.
