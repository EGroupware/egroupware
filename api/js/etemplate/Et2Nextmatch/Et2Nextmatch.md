
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

### Tile View

Use tile view when entries should be shown as individual cards or thumbnails instead of table rows. The default view is
`row`; switch to tile view on the client with `setView("tile")` or by setting the `view` property.

```ts
nextmatch.setView("tile");
nextmatch.template = "example.tile";
```

The `view` property only changes client-side rendering. It does not change the data provider request shape by itself.
Calling `applyFilters({view: "tile"})` is not required for tile rendering.

A tile template uses the normal nextmatch template structure, but the data row has the `tile` class. Each entry remains
its own virtualized item and flows left-to-right, wrapping to the next visual line according to available width.

```xml
<template id="example.tile">
    <grid width="100%">
        <columns>
            <column width="100%"/>
        </columns>
        <rows>
            <row class="th">
                <nextmatch-header/>
            </row>
            <row class="tile $row_cont[class]">
                <et2-vbox class="tile-card" width="140px" height="120px">
                    <et2-image src="${row}[icon]" label="${row}[title]"></et2-image>
                    <et2-description id="${row}[title]" noLang="1"></et2-description>
                </et2-vbox>
            </row>
        </rows>
    </grid>
</template>
```

Set fixed dimensions on the tile content with `width` and `height`, or set `data-tile-width` and `data-tile-height` on
the tile row. The virtualizer uses those values to estimate placement and scrolling.

```xml
<row class="tile" data-tile-width="140px" data-tile-height="120px">
    <et2-vbox class="tile-card">
        ...
    </et2-vbox>
</row>
```

Keep tile CSS in the application's `templates/default/app.css`, the same as row CSS. Use application-owned class names
for tile content; shared Nextmatch code only depends on the `tile` row class and generic sizing attributes.

### Notes

- If both a `template` attribute and slotted templates are provided, `template` wins.
- `setRows()` can preload initial rows; otherwise rows are fetched through the bound Nextmatch data provider.

## Styling Rows

`et2-nextmatch` renders rows inside `et2-datagrid`, so normal application CSS does not automatically reach row contents.
`et2-nextmatch` loads the current application's `templates/default/app.css` into the
datagrid row shadow DOM. Use that file for row classes and widget selectors that must affect row contents.

We could add optional row-specific stylesheets later, for example `addressbook/templates/default/index.rows.css`, if we
want these advantages:

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
`templates/default/app.css`.

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
