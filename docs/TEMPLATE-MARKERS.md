# Elementor Template Markers

Wolf Forge Elementor Page Generator uses short, human-readable Elementor markers. Add them in Elementor under **Advanced → Attributes / Custom Attributes** using `data-customID|marker`.

## Current markers

| Marker | Meaning | Typical use |
|---|---|---|
| `data-customID|h1` | Main H1 | Heading widget or heading destination |
| `data-customID|h2` | Section title / H2 | Heading widget |
| `data-customID|h3` | H3 / subsection heading | Heading widget |
| `data-customID|p` | Paragraph/body content | Text Editor, Icon Box, Toggle, and supported content widgets |
| `data-customID|repeat` | Repeatable content prototype | Widget/card inside a repeatable section |
| `data-customID|step` | Step number | Supported process/step layouts |

## Legacy aliases

Existing templates may use the older marker names below. The generator automatically normalizes them to the current names:

| Legacy marker | Current marker |
|---|---|
| `data-customID|h1NonRepeat` | `data-customID|h1` |
| `data-customID|sectionTitleNonRepeat` | `data-customID|h2` |
| `data-customID|hNonRepeat` | `data-customID|h3` |
| `data-customID|pNonRepeat` | `data-customID|p` |
| `data-customID|repeatableItem` | `data-customID|repeat` |
| `data-customID|stepNumber` | `data-customID|step` |

No template migration is required solely because of the marker rename. New templates should use the short names.

## Marker syntax

Use one marker after the first pipe:

```text
data-customID|h1
data-customID|h2
data-customID|h3
data-customID|p
data-customID|repeat
data-customID|step
```

Do not create compound markers such as:

```text
data-customID|h3|p
data-customID|h3|repeat
```

Those are not separate marker components. The generator reads the value after the first `|` as the marker identifier.

## DOCX-to-Elementor mapping

The DOCX parser works with semantic content such as headings, paragraphs, and repeatable sections. The Elementor markers identify the destination:

```text
DOCX H1        -> h1
DOCX H2        -> h2
DOCX H3        -> h3
DOCX paragraph -> p
Repeat section  -> repeat
Step number     -> step
```

## Paragraph behavior

`p` is a semantic content destination, not simply a blind "copy the next paragraph" instruction.

- **Text Editor:** receives body content associated with the mapped heading/block.
- **Icon Box:** receives heading/body content through the icon-box fields.
- **Toggle:** receives multiple heading/body pairs for supported FAQ-style content.

## Repeatable content

`repeat` identifies the Elementor widget that acts as the repeatable prototype in Unique page generation. The generator can clone the containing structure as required by repeatable DOCX sections and the configured widgets-per-section behavior.

## Recommended new-template convention

```text
H1 widget        -> data-customID|h1
H2 widget        -> data-customID|h2
H3 widget        -> data-customID|h3
Body Text Editor -> data-customID|p
Repeatable card  -> data-customID|repeat
Step number      -> data-customID|step
```
