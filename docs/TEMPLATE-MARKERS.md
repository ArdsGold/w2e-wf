# Elementor Template Markers

## Overview

The generator uses Elementor custom attributes as semantic placeholders. A marker tells the generator what content should be written into a widget.

Add markers in Elementor under:

**Advanced → Attributes / Custom Attributes**

The normal form is:

```text
data-customID|MARKER_NAME
```

The parser reads the value after the first pipe character.

## Core markers

### H1

```text
data-customID|h1NonRepeat
```

Maps to a DOCX Heading 1 block.

### Section title

```text
data-customID|sectionTitleNonRepeat
```

Maps to a DOCX Heading 2 block.

### Normal heading

```text
data-customID|hNonRepeat
```

Maps to a DOCX Heading 3 block.

### Paragraph/body

```text
data-customID|pNonRepeat
```

Maps body content to the appropriate Elementor widget based on its widget type.

### Repeatable item

```text
data-customID|repeatableItem
```

Marks a widget as the prototype for Unique/repeatable content.

### Step number

```text
data-customID|stepNumber
```

Used by supported process/step layouts.

## Important distinction: heading vs. paragraph

A heading marker and a paragraph marker are separate semantic destinations.

For a standard content block:

```text
Heading widget → data-customID|hNonRepeat
Text Editor    → data-customID|pNonRepeat
```

The generator uses the DOCX block structure to keep the heading and its body together rather than simply taking the next paragraph in isolation.

## Repeatable Icon Boxes

For a repeatable Icon Box, use:

```text
data-customID|repeatableItem
```

The repeatable item can populate:

- `title_text` from the item heading;
- `description_text` from the item body.

The rest of the Icon Box settings remain controlled by the Elementor prototype.

## Toggle / FAQ

A Toggle widget can use the repeatable marker to represent multiple FAQ entries. Each DOCX repeatable heading becomes a toggle title and its associated body becomes the toggle content.

The Elementor JSON structure stores Toggle entries inside the widget's `tabs` array. The generator updates those entries instead of treating them like independent Elementor widgets.

## Marker compatibility

The parser accepts common representations of the custom attribute, including:

- `customID`
- `custom_id`
- `data-customID`
- `data_customID`
- Elementor's `_attributes` field containing `data-customID|...`

Do not change the internal parser or marker names casually; templates are production inputs and marker changes can affect existing page-generation workflows.

## Recommended template pattern

A typical generic template can be structured as:

```text
H1 widget          → data-customID|h1NonRepeat
Intro Text Editor  → data-customID|pNonRepeat
H2 widget          → data-customID|sectionTitleNonRepeat
H3 widget          → data-customID|hNonRepeat
Body Text Editor   → data-customID|pNonRepeat
```

A Unique section can use:

```text
Icon Box → data-customID|repeatableItem
```

If a section contains multiple repeatable card columns, configure **Widgets per section** to match the intended visual layout.
