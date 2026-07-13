`et2-datagrid` is used by other widgets to display a list of rows. The owner widget will provide

- `columns` metadata
- row template data (`templateData`)
- row data from a `dataProvider` or preloaded rows

This page documents column and row template behaviour shared by both:

- named templates loaded through `et2-nextmatch template="app.index.rows"`
- slotted templates provided directly to `et2-nextmatch`

## Columns

Columns are derived from header widgets/elements and include key, title, and optional width/minWidth/disabled metadata.

### From Named Templates

When using `et2-nextmatch template="app.index.rows"`:

- Header definition is parsed from the template header row (`.th`, `thead`, or grid header structure).
- Column widgets (such as `et2-nextmatch-header`, `et2-nextmatch-sortheader`) are used to build column definitions.
- Legacy Nextmatch visibility/size preferences are mapped and applied by `et2-nextmatch`.

### From Slotted Templates

(WIP) When using slots (`template` attribute not set):

- Columns come from `slot="columns"`.
- Any wrapper can carry the slot (`tr`, `div`, `et2-box`, etc.).
- The columns wrapper itself is not treated as a column; its child elements are used as column definitions.

Example:

```xml

<et2-nextmatch>
    <tr class="th" slot="columns">
        <et2-nextmatch-sortheader id="n_family" label="Name"></et2-nextmatch-sortheader>
        <et2-nextmatch-header id="note" label="Note"></et2-nextmatch-header>
        <et2-vbox>
            <et2-nextmatch-header id="tel_work" label="Business phone"></et2-nextmatch-header>
            <et2-nextmatch-header id="tel_cell" label="Mobile phone"></et2-nextmatch-header>
            <et2-nextmatch-header id="tel_home" label="Home phone"></et2-nextmatch-header>
        </et2-vbox>
    </tr>
    <tr slot="rows">...</tr>
</et2-nextmatch>
```

## Rows

Row rendering is driven by a row template.  
:::note

* The row template is not given any server-side processing when the template is initially loaded.
* Avoid legacy widgets in the row template.
  :::

Field expression support in row templates:

- Legacy: `${row}[note]`, `$row_cont[note]`
- Shorthand: `${note}`, `$note`

Both work. For new templates, shorthand is recommended for readability.

### From Named Templates

Named row templates are normalized from legacy eTemplate structures (`<row>`) into datagrid renderable markup.
Common legacy patterns continue to work.

### From Slotted Templates

(WIP) Slotted row template comes from `slot="row"`.

- Preferred wrapper: `<tr slot="row">`
- Legacy wrapper: `<row slot="row">`
- Any other wrapper is preserved as-is (for example `<sl-card slot="row">`)

If the wrapper is `<tr>` or `<row>`, bare child widgets are allowed and are auto-wrapped into `<td>` cells.

Example:

```xml
<tr class="$class $cat_id" slot="row">
    <et2-description id="n_family" noLang="1"></et2-description>
    <et2-textarea id="${note}" readonly="true" noLang="1"></et2-textarea>
    <et2-vbox>
        <et2-url-phone id="tel_work"
                       readonly="true" class="telNumbers" statustext="Business phone"
        ></et2-url-phone>
        <et2-url-phone id="tel_cell"
                       readonly="true" class="telNumbers" statustext="Mobile phone"
        ></et2-url-phone>
        <et2-url-phone id="tel_home"
                       readonly="true" class="telNumbers" statustext="Home phone"
        ></et2-url-phone>
    </et2-vbox>
</tr>
```

## Styling Row Contents

Rows are rendered inside the `et2-datagrid` shadow DOM. Normal page CSS does not reach row contents unless the target is
exposed as a CSS part. `et2-nextmatch` loads the current application's
`templates/default/app.css` into the datagrid row shadow DOM, so row classes and widget selectors that must affect row
contents can live there.

### Static Widget Style

For a simple static change that applies to every row, add a class to the row template and style it in `app.css`.

```xml

<row>
    <et2-description class="entry-title" id="title" noLang="1"></et2-description>
    <et2-description id="owner" noLang="1"></et2-description>
</row>
```

```css
.entry-title {
	color: var(--sl-color-primary-700);
	font-weight: var(--sl-font-weight-semibold);
}
```

### Static Widget Internals

To style a widget's exposed internal part from the stylesheet, use `::part()` on the widget in the row template.

```xml

<row>
    <et2-image class="status" label="Open"></et2-image>
</row>
```

```css
.status::part(base) {
	min-width: 0;
	padding-inline: var(--sl-spacing-small);
}
```

### Static Exported Parts

Use `exportparts` when normal application CSS outside the datagrid needs to style a row widget's internal part. The
datagrid gathers row-template `exportparts` and forwards them through `et2-nextmatch`.

```xml

<row>
    <et2-hbox class="contact-methods" exportparts="base:contact-methods__base">
        <et2-url-phone id="${row}[tel_work]" readonly="true"></et2-url-phone>
        <et2-url-phone id="${row}[tel_cell]" readonly="true"></et2-url-phone>
        <et2-url-email id="${row}[email]" readonly="true"></et2-url-email>
    </et2-hbox>
</row>
```

```css
et2-nextmatch::part(contact-methods__base) {
	flex-wrap: wrap;
	align-content: flex-start;
	row-gap: var(--sl-spacing-2x-small);
}
```

### Dynamic Row Class

For dynamic row state, put a class expression on the row or widget and style it in the stylesheet. This is the most
direct option when the server already supplies a class such as `overdue`, `readonly`, or `cat_<ID>`.

```xml

<row class="$class priority_${priority}">
    <et2-description class="entry-title" id="${title}" noLang="1"></et2-description>
    <et2-description class="entry-status" id="${status}" noLang="1"></et2-description>
</row>
```

```css
tr.overdue .entry-status {
	color: var(--sl-color-danger-700);
	font-weight: var(--sl-font-weight-semibold);
}

tr.priority_high .entry-title {
	border-inline-start: 3px solid var(--warning-color);
	padding-inline-start: var(--sl-spacing-x-small);
}
```

### Dynamic Widget Class

When only one widget needs dynamic styling, you can put the class expression on that widget instead of the whole row.

```xml

<row>
    <et2-description class="entry-status status_${status_class}" id="${status}" noLang="1"></et2-description>
</row>
```

```css
.entry-status.status_warning {
	color: var(--sl-color-warning-700);
}

.entry-status.status_error {
	color: var(--sl-color-danger-700);
}
```

### Dynamic CSS Property

When an owner widget needs to change the same row styling for all rendered rows, set a CSS custom property on the
datagrid or owner widget and use it from the row stylesheet. This keeps row DOM updates out of application code and lets
the browser apply the change to currently rendered and newly virtualized rows.

```ts
this.datagrid.style.setProperty("--app-row-details-display", showDetails ? "block" : "none");
```

```xml
<row>
    <et2-description class="entry-title" id="${title}" noLang="1"></et2-description>
    <et2-description class="entry-details" id="${details}" noLang="1"></et2-description>
</row>
```

```css
.entry-details {
	display: var(--app-row-details-display, none);
}
```

## Loader Template

Optional placeholder template while rows are loading:

- `slot="loader"` (preferred)

Any wrapper can be used for loader slot content.

Example:

```xml
<tr slot="loader">
    <td colspan="2">
        <sl-skeleton effect="sheen" style="width:100%"></sl-skeleton>
    </td>
</tr>
```

## No-Results Template

Optional template shown when loading is complete and no rows are available:

- Replaces the default no-results `<sl-alert>` entirely

Example:

```xml
<sl-alert slot="noResults" variant="neutral" open>
    <sl-icon slot="icon" name="inbox"></sl-icon>
    <strong>This mailbox is empty</strong>
</sl-alert>
```

## Developer Notes

`et2-datagrid` is the low-level row renderer used by owner widgets such as `et2-nextmatch`. It should stay generic:
owners supply structure and data, while the datagrid owns virtualization, row DOM creation, selection state, keyboard
navigation, column layout, loaders, no-results rendering, and refresh application.

### Using Et2Datagrid From Widgets

An owner widget should configure the datagrid by passing:

- `columns`: normalized column metadata used for headers, column widths, visibility, and row layout.
- `templateData`: prepared row, loader, and attribute-map data, usually produced by `Et2RowProvider`.
- `dataProvider`: an `Et2DatagridDataProvider` implementation for paged loading and row refreshes.
- Optional presentation hooks such as `rowCustomizer`, `selectionMode`, `expansionConfig`, and preloaded rows.

Keep app-specific concerns in the owner widget or its provider. The datagrid should not know how a particular app stores
filters, talks to the server, resolves a named template, or interprets legacy Nextmatch response payloads. It should
receive already-normalized columns, rows, and row templates.

### RowProvider

`Et2RowProvider` is the template adapter between an owner widget and the datagrid. It reads either a named eTemplate or
slotted markup and returns `Et2DatagridTemplateData`.

Its responsibilities include:

- finding the effective header and row template markup
- extracting column metadata from headers
- normalizing legacy row wrappers such as `<row>` into datagrid-renderable markup
- preparing a reusable `HTMLTemplateElement` for rows
- converting row widgets to readonly display behaviour where needed
- recording row-scoped widget attributes in `rowTemplateAttrMap`
- preparing optional loader template data

`Et2RowProvider` does not fetch row data. It prepares the shape used to render rows once data is available.

### DataProviders

`Et2DatagridDataProvider` is the data boundary for the datagrid. A provider supplies rows in the datagrid's normalized
shape:

```ts
{
	id: string;
	data: any;
}
```

The required provider methods are:

- `fetchPage(start, pageSize)`: load a page of rows and optionally return the total row count.
- `refresh(rowIds, type)`: resolve updated rows and removed row ids for refresh operations.
- `normalizeRowId(rowId, ensurePrefix)`: convert ids to the datastore form used by the rendered grid.
- `toProviderRowId(dataStoreRowId)`: convert rendered/datastore ids back to the provider's native row id.

Optional methods such as `getQuerySignature()` and `getDataStorePrefix()` let the datagrid detect query changes and keep
row ids stable across paging, refreshes, nested grids, and action handling.

`Et2NextmatchDataProvider` is the Nextmatch implementation. It adapts `egw.dataFetch()` and `egw.dataRegisterUID()` into
the generic provider interface, processes extra Nextmatch response data such as select options and filter values, reads
refreshed rows back from the central UID cache, and supports child-grid providers for expandable rows.

### Rendering Pipeline

Row rendering is split into preparation and per-row hydration:

1. `Et2RowProvider` finds the row template, normalizes it, extracts columns, and compiles it into a reusable template.
   Row-independent values are resolved at this stage, including static `$cont` / `@...` expressions and literal strings.
2. `Et2RowProvider` records row-scoped widget attributes in the template attribute map instead of permanently resolving
   them on the reusable template.
3. `Et2Datagrid` clones the prepared row template for each row needed by the virtualizer.
4. `Et2Datagrid` applies recorded row-scoped widget attributes to the clone using that row's `rowData`, which came from
   the configured `Et2DatagridDataProvider`.

This order keeps static template work shared across all rows while preserving correct row-specific values for each
physical row clone. The virtualizer determines which row indexes need DOM nodes and manages their addition and removal;
the data provider supplies row objects, and the prepared template controls how each row object becomes DOM.
