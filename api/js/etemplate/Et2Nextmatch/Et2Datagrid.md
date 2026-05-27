# Et2Datagrid

`et2-datagrid` renders a virtualized table/grid from:

- `columns` metadata
- row template data (`templateData`)
- row data from a `dataProvider` or preloaded rows

This page documents column and row template behavior shared by both:

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
<tr class="th" slot="columns">
    <et2-nextmatch-sortheader id="n_family" label="Name"></et2-nextmatch-sortheader>
    <et2-nextmatch-header id="note" label="Note"></et2-nextmatch-header>
    <et2-vbox>
        <et2-nextmatch-header id="tel_work" label="Business phone"></et2-nextmatch-header>
        <et2-nextmatch-header id="tel_cell" label="Mobile phone"></et2-nextmatch-header>
        <et2-nextmatch-header id="tel_home" label="Home phone"></et2-nextmatch-header>
    </et2-vbox>
</tr>
```

## Rows

Row rendering is driven by one row template plus row data objects.

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
    <et2-description id="${n_family}" noLang="1"></et2-description>
    <et2-textarea id="${note}" readonly="true" noLang="1"></et2-textarea>
    <et2-vbox>
        <et2-url-phone id="${tel_work}" 
           readonly="true" class="telNumbers" statustext="Business phone"
        ></et2-url-phone>
        <et2-url-phone id="${tel_cell}" 
           readonly="true" class="telNumbers" statustext="Mobile phone"
        ></et2-url-phone>
        <et2-url-phone id="${tel_home}" 
           readonly="true" class="telNumbers" statustext="Home phone"
        ></et2-url-phone>
    </et2-vbox>
</tr>
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
