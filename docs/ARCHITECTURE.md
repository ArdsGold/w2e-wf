# Architecture

## Runtime flow

```text
DOCX upload
   │
   ▼
DOCX Reader
   │
   ▼
Normalized document records
   │
   ├───────────────┐
   │               │
   ▼               ▼
Elementor JSON   Template Validator
   │
   ▼
Marker normalization
   │
   ▼
Content mapping
   │
   ├── fixed markers
   ├── repeatable containers
   ├── internal repeaters
   └── image replacement/pool
   │
   ▼
Queued generation job
   │
   ▼
WP-Cron worker
   │
   ▼
WordPress page + Elementor metadata
   │
   ▼
Elementor CSS regeneration
```

## Bootstrap

`wolf-forge-elementor-page-generator.php` is intentionally small. It:

- Defines plugin paths and the release version.
- Loads the core classes.
- Registers activation/deactivation behavior.
- Registers the queue cron hook.
- Adds the generated-page frontend CSS.

Business logic belongs in the included classes rather than the bootstrap file.

## DOCX parsing

`WFEBPG_DOCX_Reader` reads the DOCX ZIP package directly and parses `word/document.xml`.

The reader converts Word paragraphs into normalized records containing:

- Text.
- Heading status.
- Heading level.
- Repeatable/yellow-heading compatibility information.
- Source order.

This keeps Word-specific parsing out of the Elementor generator.

## Template handling

`WFEBPG_Template` is the boundary between raw Elementor JSON and the generator.

It is responsible for:

- Decoding supported Elementor export shapes.
- Returning page settings.
- Reading custom attributes.
- Normalizing marker names.
- Recognizing marker flags.
- Walking nested Elementor elements.
- Identifying repeatable scope.

## Generation

`WFEBPG_Generator` maps normalized DOCX records onto Elementor element settings.

The generator deliberately follows the rule:

```text
unmarked element → do not populate
marked element   → populate according to marker
marked repeat    → clone/rebuild according to repeat model
```

It also owns page creation and Elementor post metadata.

## Queue

The queue is stored in the WordPress option `wfebpg_queue`.

The worker uses a lock option to prevent two workers from processing the same queue at the same time. The lock is short-lived so a stale lock does not permanently stop generation.

The queue processes a small number of jobs per request and has a time guard.

## Compatibility layer

The `WFEBPG` prefix is retained intentionally.

These identifiers are persisted or externally referenced:

- WordPress options.
- User metadata.
- AJAX action names.
- Cron hook names.
- Generated-page metadata.
- CSS selectors.
- PHP class names.

Changing them would require a migration layer. r2.0.1b does not perform that migration.

## Maintainability rules

- Prefer explicit names over compressed one-liners.
- Keep functions focused on one responsibility.
- Keep comments focused on behavior that is not obvious from the code.
- Preserve compatibility with existing stored data.
- Avoid introducing dependencies for simple WordPress/PHP tasks.
- Validate PHP syntax before packaging.
