# Wolf Forge Elementor Page Generator r2.2.0

## Release summary

r2.2.0 adds structural repeat elements and updates the admin workflow for previewing and validating templates.

## Structural repeat elements

Add `data-customID|repeat` to a Column, Container, or Section that represents one complete item. Mark its child Heading and Text Editor with the appropriate content markers:

```text
Column
  data-customID|repeat
  ├── Heading
  │   data-customID|h3
  └── Text Editor
      data-customID|p
```

The generator infers the DOCX heading level from the first marked heading inside the repeat element. It creates or removes items to match the records in that heading scope. **Widgets per section** controls the number of items per generated row. When a template has multiple prototype rows, their prototypes are preserved and reused in order. Generated columns use consistent widths, and a partial final row is centered.

Structural repeat regions can span adjacent Elementor Sections when they share the same DOCX scope. A scope with no matching records produces no repeat items.

Existing heading/widget repeat markers, including `h3|repeat` and `h3|p|repeat`, and the legacy `|repeatable` alias remain supported. Toggle and Accordion widgets can use `h3|p|repeat` to populate their internal items.

## Admin workflow

- Page Generation previews the selected saved or uploaded template automatically. The preview shows the Elementor structure and marker values on hover.
- Template Validator can preview and validate a saved or uploaded template with one action. An optional DOCX checks content compatibility.
- Page Generation reports whether the site's PHP has `ZipArchive`. If it is missing, the zip-free DOCX fallback is enabled and saved for the account. The fallback requires PHP zlib.

## Compatibility

Existing `WFEBPG_*` classes, WordPress options, AJAX actions, queue hooks, and stored data identifiers are retained.

## Release checks

Before packaging, verify repeat contraction, expansion, empty scopes, row limits, prototype preservation, consistent widths, and partial-row centering. Run PHP syntax checks and `node --check` on `assets/admin.js`.
