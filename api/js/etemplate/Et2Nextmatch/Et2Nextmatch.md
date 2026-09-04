
## Overview

### Named Template Mode

Use the `template` attribute when you already have a classic eTemplate rows template:

```xml
<et2-nextmatch id="nm" template="addressbook.index.rows"></et2-nextmatch>
```

The row/header structure is read from that template and converted for `et2-datagrid`.

### Slotted Template Mode

(WIP) When `template` is not set, `et2-nextmatch` reads slotted child markup from its light DOM.

```xml
<et2-nextmatch id="nm">
    <et2-box slot="header">Custom toolbar / filters / actions above grid</et2-box>

    <tr class="th" slot="columns">
        <et2-nextmatch-sortheader id="n_family" label="Name"></et2-nextmatch-sortheader>
        <et2-nextmatch-header id="note" label="Note"></et2-nextmatch-header>
    </tr>

    <tr class="$class $cat_id" slot="row">
        <et2-description id="n_family" noLang="1"></et2-description>
        <et2-textarea id="note" readonly="true" noLang="1"></et2-textarea>
    </tr>

    <tr slot="loader">
        <td colspan="2">
            <sl-skeleton effect="sheen" style="width:100%"></sl-skeleton>
        </td>
    </tr>
</et2-nextmatch>
```


Full details for columns, rows, expression syntax, wrapper behaviour, and loader slots are documented in:

- [Et2Datagrid](/components/et2-datagrid)

## Row expansion

`et2-nextmatch` maps the existing Nextmatch hierarchy contract onto `et2-datagrid` row expansion.
There is no separate client or server contract for recursive expansion:

- rows are expandable when the row data has `is_parent: true`, or when the configured
  `settings.is_parent` field matches `settings.is_parent_value`;
- child rows are fetched through the existing child-provider path, which sends `parent_id` to the
  server;
- every child level uses the same expansion semantics as its parent; only the `parent_id` scoped to
  that level changes.

Expanded content is an embedded `<et2-datagrid>` that reuses the parent Nextmatch row template,
column snapshot, row customizer, and row styles. The child grid is `embedded-virtualized`, so it does
not create a nested scrollbar. It reserves its own full virtualized height inside the parent
scrollport while rendering only the visible child rows.

Expansion is recursive for acyclic hierarchy data. If a child row is marked as a parent by the same
`is_parent` / configured marker contract, that child row can expand into another embedded datagrid
using the same contract. Each level keeps its own `total`; child totals are not rolled up into the
root total.

The server must not return cyclic hierarchy data such as A → B → A or a row as its own child.
Recursive expansion documents this as a data requirement; it does not add a runtime cycle/depth
guard.

### Notes

- If both a `template` attribute and slotted templates are provided, `template` wins.
- `setRows()` can preload initial rows; otherwise rows are fetched through the bound Nextmatch data provider.

## Row value bindings

Row templates bind in two ways, and the distinction is worth keeping straight:

- **An `id` gets you a value.** `id="title"` is the widget's stable id *and* names the row field it shows, so the
  datagrid supplies `rowData.title` as the widget value. This is the recommended form for row values.
- **`$` and `@` expressions set attributes and properties.** They work in any row-template attribute, and each widget
  then applies its normal interpretation of the resolved value.

`$field` reads `field` from the current row and `$[parent.child]` reads a nested value. `@field` returns the content
entry stored under that key, and `@@field` reads it from the root content instead of the current namespace.

```xml

<row class="$class $[category.css_class]">
    <et2-description id="title" noLang="1"></et2-description>
    <et2-date-time id="modified" readonly="true"></et2-date-time>
    <et2-image src="$icon" label="$type_label"></et2-image>
    <et2-description class="priority_$priority" id="status" noLang="1"></et2-description>
</row>
```

`value` is an ordinary property, so an expression can set it like any other. Reach for that only when the value comes
from a differently named or nested row field - when the id already names the field, it is redundant:

```xml

<et2-description id="summary" value="$[details.short]" noLang="1"></et2-description>
```

Keep action and compound ids as ids, for example `id="delete[$id]"`; they are not row-value bindings.

An id on a namespace-opening widget - a box, a grid, a nested nextmatch - scopes its children instead of naming a value
of its own, so nested row data can be addressed by nesting the template. Given row data `{id: 1, sub: {name: "cheese"}}`:

```xml

<row>
    <et2-description id="id"></et2-description>
    <et2-vbox id="sub">
        <et2-description id="name"></et2-description>
    </et2-vbox>
</row>
```

the inner description binds `sub.name`. Whether an id scopes or binds is the widget's own answer (`_createNamespace()`),
the same question etemplate asks everywhere else - so an ordinary widget's id still names a value even when the template
nests markup inside it, and an unnamed container is pure layout that never affects the path. Flat row data stays flat no
matter how deeply it is wrapped: `{id: 1, name: "cheese"}` still binds through `<et2-vbox><et2-hbox><et2-description
id="name"/></et2-hbox></et2-vbox>`.

Attribute expressions are never namespaced: `$field` and `${row}[field]` always address the row itself, wherever they
are written.

Existing templates do not need a bulk rewrite. Legacy `${row}[title]`, `{$row}[title]`,
`$row_cont[title]`, and `$row.title` continue to work in row templates. Use them only when documenting or maintaining
legacy markup; new examples and template edits should use direct bindings.

## Styling Rows

`et2-nextmatch` renders rows inside `et2-datagrid`, so normal application CSS does not automatically reach row contents.
Put row-specific styles in an `et2-styles` element inside the row template definition. `et2-nextmatch`
extracts those styles and adopts them into the datagrid row shadow DOM.

```xml

<template id="app.index.rows">
    <grid>
        ...
    </grid>

    <et2-styles src="row.css"></et2-styles>
</template>
```

The `et2-styles` element can be anywhere inside the row template definition, not only inside `<row>`.
Bare filenames such as `row.css` resolve relative to the `.xet` file containing the template.

When row-template-local styles are present, the current application's `templates/default/app.css` is
not loaded into the datagrid row shadow DOM. If the row template does not contain `et2-styles`,
`app.css` is still loaded as a compatibility fallback.

Row-template-local stylesheets have these advantages:

- fewer unrelated app rules inside row shadow DOM
- clearer ownership for styles used only by one row template
- smaller stylesheet parse cost for large `app.css` files
- fewer accidental matches between edit/view CSS and list rows
- easier deletion when a row template is removed or replaced

For the full set of row styling options, including `exportparts`, see
[Et2Datagrid: Styling Row Contents](/components/et2-datagrid#styling-row-contents).

### Anti-example: Styling Row Descendants Through `::part(row)`

Do not try to style widgets inside rows from outside the datagrid shadow DOM by selecting descendants of the exported
`row` part. `::part()` exposes the part element itself, not arbitrary descendants inside that part, so this selector
will
not style row links:

```less
et2-nextmatch {
  ::part(row) {
	/* Links in nextmatch should be blue */

	et2-link, et2-link-string {
	  color: var(--sl-color-sky-900);
	}
  }
}
```

Because the rows are managed by `et2-datagrid` inside its shadow DOM, you cannot style them from outside the datagrid.

* `et2-nextmatch::part(row) { ... }` can style the row elements themselves, but not their descendants.
* `et2-nextmatch::part(row) et2-link { ... }` cannot style links inside the row.
* `et2-nextmatch::part(exported-part) { ... }` can style explicitly exported parts.

For framework-level row styles, add to `Et2Nextmatch.row.styles.ts`. For app-specific row styles, use the app's
row-template `et2-styles`. `templates/default/app.css` remains the fallback for row templates that have
not been migrated.

For application rules generated at runtime, create a constructable stylesheet and add it through the nextmatch:

```ts
const style = new CSSStyleSheet();
style.replaceSync("tr.dynamic-state { color: var(--dynamic-color); }");
nextmatch.addRowStylesheet(style);
```

The stylesheet is adopted after the static row styles and is retained if the row template is reloaded.

### Highlighting an Overdue Entry

Have the server add an `overdue` class for rows that need attention, then style that class via CSS.

```xml

<row class="$class">
    <et2-description class="task-title" id="title" noLang="1"></et2-description>
    <et2-description class="task-due" id="due" noLang="1"></et2-description>
</row>
```

```css
.overdue .task-title {
	font-weight: var(--sl-font-weight-semibold);
}

.overdue .task-due {
	color: var(--sl-color-danger-700);
}
```

### Letting Users Show Or Hide Row Details

For a list option such as "show details", set a CSS custom property on the nextmatch widget when the option changes.
The row stylesheet can then use that value for every row, including rows that are rendered later while scrolling.

```ts
show_details(show, nextmatch : Et2Nextmatch)
{
	nextmatch?.style?.setProperty("--task-details-display", show ? "block" : "none");
}
```

```xml
<row class="$class">
    <et2-description class="task-title" id="title" noLang="1"></et2-description>
    <et2-description class="task-details" id="description" noLang="1"></et2-description>
</row>
```

```css
.task-details {
	display: var(--task-details-display, none);
	max-height: 5em;
	overflow: clip;
}
```

This is the same pattern InfoLog uses to show or hide description rows without updating each row individually.

### Wrapping Contact Details

If the row contains a widget with internal layout, expose the part you need and style it from CSS.

```xml

<row class="$class">
    <et2-hbox class="contact-methods" exportparts="base:contact-methods__base">
        <et2-url-phone id="tel_work" readonly="true"></et2-url-phone>
        <et2-url-phone id="tel_cell" readonly="true"></et2-url-phone>
        <et2-url-email id="email" readonly="true"></et2-url-email>
    </et2-hbox>
</row>
```

```css
et2-nextmatch::part(contact-methods__base) {
	flex-wrap: wrap;
	row-gap: var(--sl-spacing-2x-small);
}
```
