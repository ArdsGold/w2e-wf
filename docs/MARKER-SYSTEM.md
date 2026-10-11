# Wolf Forge Elementor Page Generator — Marker System

## Purpose

The marker system is the contract between an Elementor JSON template and the DOCX parser.

> **If an Elementor element has no `data-customID` marker, the generator does not populate it.**

Unmarked elements may still be cloned when they live inside a marked repeatable parent. Their content is otherwise left untouched.

## Marker syntax

Use Elementor **Advanced → Attributes / Custom Attributes**.

### Basic content markers

```text
data-customID|h1
data-customID|h2
data-customID|h3
data-customID|p
```

The first token is the content source. Additional tokens are behavior/content flags.

### Repeat marker

The short form is:

```text
data-customID|h3|repeat
```

`|repeat` is the canonical repeat modifier. The legacy `|repeatable` spelling is still accepted for backward compatibility, but new templates should use `|repeat`.

### `repeat` and `repeatable` are equivalent

For the generator's marker parser, these two forms intentionally mean the same thing:

```text
data-customID|h3|p|repeat
data-customID|h3|p|repeatable
```

`repeat` is the preferred spelling for new templates. `repeatable` is an accepted legacy alias so existing templates do not need to be edited. Internally, both normalize to the same `repeatable` behavior flag. There is no difference in how many records they generate or how the repeat cursor works.

```text
data-customID|h3|repeat
```

## Structural repeat element model

For new r2.2.0 templates, put the structural repeat marker on the **Elementor element that represents one complete item**. This may be a Container, Section, or Column:

```text
data-customID|repeat
```

Example:

```text
Container / Column
  data-customID|repeat
  ├── Heading widget
  │   data-customID|h3
  └── Text Editor widget
      data-customID|p
```

The child Heading and Text Editor use ordinary content markers. The repeat marker belongs only to the visual item boundary.

For each matching record in the DOCX scope, the generator:

1. Clones the marked parent container.
2. Finds the **first Heading widget** inside that container.
3. Places the H3 text into that Heading widget.
4. Finds the **first Text Editor widget** inside that container.
5. Places all paragraphs belonging to that H3 into that Text Editor.

This works for cards, service boxes, process steps, benefits, and other layouts.

The **Widgets per section** setting controls the number of generated cards per row. If the template has prototypes in multiple rows, all prototypes are reused in order. Generated repeat Columns use the configured row width; an incomplete final row is centered. A scope with no matching records produces no repeat items.

## Widget-level repeatable Text Editor

A repeat marker does not have to be placed on the parent Section/Container. It may be placed directly on a widget inside an ordinary section, for example:

```text
Section
  ├── Heading widget
  │   data-customID|h2
  └── Text Editor widget
      data-customID|h3|p|repeat
```

In this form, the **Text Editor itself is the repeat unit**. The containing Section/Container is only the visual owner used while the Elementor tree is expanded. The document scope comes from the nearest preceding marked higher-level heading, not from the Section/Container title.

For an `h3|p|repeat` Text Editor, the generator:

1. Uses the nearest preceding marked H2 as the DOCX scope.
2. Finds every H3/P record after that H2.
3. Stops when the next higher-level H2 is reached.
4. Keeps the single Text Editor widget in place; it is **not cloned** and its containing Section/Container is **not duplicated** for these records.
5. Appends every H3/P pair into that one Text Editor's `editor` field as semantic HTML, for example:

```html
<h3>Schedule Your Consultation</h3>
<p>Contact Detroit Roofers of Canton...</p>
```

Nested Elementor Containers between the Section and the marked Text Editor do not create a new DOCX scope. This keeps a widget-level repeat marker in the same Section as its H2 working as expected.

### `h3|repeat` versus `h3|p|repeat`

Both forms create an H3-based repeatable record. On a widget-level Text Editor, `h3|p|repeat` explicitly requests the combined H3 + paragraph stream described above:

```text
data-customID|h3|repeat
```

and:

```text
data-customID|h3|p|repeat
```

The H3 is the repeat boundary and its following paragraphs belong to that record. The `p` flag can be used when you want the marker to explicitly document that the repeatable unit contains heading + paragraph content, but it is not required for the automatic Heading/Text Editor behavior.

## DOCX hierarchy and collection rules

For a repeatable H3:

```text
Heading 3
Service A
Paragraph
Paragraph

Heading 3
Service B
Paragraph
```

The records are:

```text
Service A
  Paragraph
  Paragraph

Service B
  Paragraph
```

All immediate following paragraphs belong to the current heading until another heading appears.

A heading **higher in the hierarchy** closes the current collection. The rule is not hard-coded to H2/H3: an H3 collection is closed by H2/H1, an H4 collection by H3/H2/H1, an H5 collection by H4/H3/H2/H1, and so on.

For example:

```text
Heading 2
Services

Heading 3
Service A
Paragraph
Paragraph

Heading 3
Service B
Paragraph

Heading 2
Why Choose Us
```

The `Why Choose Us` H2 ends the Services H3 collection.

## Repeatable scope

When a repeatable H3 marker appears inside a template region associated with an H2, the generator uses the corresponding DOCX H2 section to keep unrelated H3 groups from being consumed by the same repeatable region.

This is based on heading hierarchy and template marker position, not on section names such as “Services” or “Why Choose Us”.

## Internal repeaters such as Toggle and Accordion

Toggle and Accordion widgets store their items in the widget's `tabs` setting and share the same marker.

Mark either widget with:

```text
data-customID|h3|p|repeat
```

The generator does **not** clone the widget. It replaces its internal items with one item per matching H3:

```text
Toggle or Accordion
  data-customID|h3|repeat

  H3 #1 -> Item #1
  H3 #2 -> Item #2
  H3 #3 -> Item #3
```

Each item uses the H3 as its title and the following paragraphs as its content. Existing widget settings are preserved.

The widget's position relative to marked H2 sections determines its scope. For example, a Services container and FAQ Accordion can map to separate H2 sections in one DOCX.

Internal repeaters support Elementor Toggle and Accordion.

## Non-repeat heading + paragraph containers

A Section/Container can also declare a fixed heading + paragraph unit:

```text
data-customID|h3|p
```

The generator fills the first Heading and Text Editor inside the container from the next matching H3 block.

The container is **not cloned** because `repeat` was not requested.

## Simple fixed markers

A plain marker targets a single content slot:

```text
data-customID|h1
data-customID|h2
data-customID|h3
data-customID|p
```

H1, H2, and H3 cursors are independent.

## Legacy compatibility

Older marker values remain readable, including:

```text
data-customID|h1NonRepeat
data-customID|sectionTitleNonRepeat
data-customID|hNonRepeat
data-customID|pNonRepeat
data-customID|repeatableItem
data-customID|stepNumber
```

Legacy repeatable widget-level templates continue to work. New templates should prefer the parent-container model described above.

## Hierarchical non-repeat mapping

Normal heading markers are also scoped by document hierarchy. The generator does not use one global H3 cursor. Instead, a child heading marker is bound to the nearest preceding marked heading one level above it:

- H2 → preceding H1
- H3 → preceding H2
- H4 → preceding H3
- H5 → preceding H4
- and so on

This means unused H3 slots in a “Why Choose Us” section cannot consume an H3 from the following “Process” section. Paragraph markers use the most recently mapped heading in the same traversal scope.

## Mapping Debugger

Upload both the Elementor JSON and DOCX in the Template Validator to see a mapping trace. Each row reports the Elementor element ID, marker, parent heading scope, selected DOCX source, and match status. This is the recommended way to diagnose a template/document mismatch before generating pages.
### Combined fixed heading + paragraph marker

`data-customID|h3|p` maps one DOCX H3 record to a single Elementor widget: the H3 populates the widget title field and the paragraphs immediately following that H3 populate the widget text/description field. This is useful for widgets such as Icon Box that contain both `title_text` and `description_text`.



## Release note

### Combined heading + paragraph markers on Text Editor widgets

When `h1`, `h2`, or `h3` is combined with `p` on a Text Editor widget, the generator renders both pieces into the widget's single `editor` field. For example, `data-customID|h3|p|repeat` produces an `<h3>` followed by the associated paragraph content. Widgets with dedicated title/description fields continue to receive the heading and paragraph in those separate fields.
