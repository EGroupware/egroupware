
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
        <et2-description id="${n_family}" noLang="1"></et2-description>
        <et2-textarea id="${note}" readonly="true" noLang="1"></et2-textarea>
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

### Notes

- If both a `template` attribute and slotted templates are provided, `template` wins.
- `setRows()` can preload initial rows; otherwise rows are fetched through the bound Nextmatch data provider.

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
    <et2-description class="task-title" id="${title}" noLang="1"></et2-description>
    <et2-description class="task-details" id="${description}" noLang="1"></et2-description>
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
