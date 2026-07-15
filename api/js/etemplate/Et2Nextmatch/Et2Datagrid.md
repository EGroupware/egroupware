`et2-datagrid` is the low-level, virtualized row renderer used by owner widgets such as
`et2-nextmatch`. It should stay generic: owners supply structure and data, while the datagrid owns
virtualization, row DOM creation, selection state, keyboard navigation, column layout, loaders,
no-results rendering, and refresh application.

[Overview](#overview)<br>
[Configuring the datagrid from an owner widget](#configuring-the-datagrid-from-an-owner-widget)<br>
[Minimal owner widget wiring](#minimal-owner-widget-wiring)<br>
[Row templates: columns and rows](#row-templates-columns-and-rows)<br>
[Styling row contents](#styling-row-contents)<br>
[Lifecycle (class internals)](#lifecycle-class-internals)<br>
[Rendering pipeline (summary)](#rendering-pipeline-summary)<br>
[Key methods](#key-methods)<br>
[Customization points and overrides](#customization-points-and-overrides)

If you are wiring `et2-datagrid` into another Et2 widget, start with
[Configuring the datagrid from an owner widget](#configuring-the-datagrid-from-an-owner-widget),
[Minimal owner widget wiring](#minimal-owner-widget-wiring), [Key methods](#key-methods), and
[Customization points and overrides](#customization-points-and-overrides). The lifecycle section is
for maintainers changing datagrid internals.

---

## Overview

`Et2Datagrid` is the reusable grid engine under higher-level list widgets. It renders large,
incrementally-loaded datasets by combining Lit rendering with `@lit-labs/virtualizer`, then adds the
grid behavior EGroupware lists need: column sizing and visibility, row selection, keyboard focus,
loading and empty states, row refreshes, and optional expanded rows.

It is deliberately not an application widget. It does not know what a filter means, how a server
endpoint is called, how a named eTemplate is resolved, or how an application response should be
interpreted. Those responsibilities stay in an owner widget such as `et2-nextmatch` and in the
owner's template/data adapters.

Use `et2-nextmatch` when you need the standard EGroupware list widget with filters, actions,
preferences, and legacy Nextmatch compatibility. Use `et2-datagrid` directly only when another Et2
webcomponent needs the same virtualized grid mechanics but owns its own application-level behavior.

---

## Configuring the datagrid from an owner widget

An owner widget configures the datagrid by passing:

- **`columns`** — normalized column metadata used for headers, column widths, visibility, and row layout.
- **`templateData`** — prepared row, loader, and attribute-map data, usually produced by `Et2RowProvider`.
- **`dataProvider`** — an `Et2DatagridDataProvider` implementation for paged loading and row refreshes.
- Optional presentation hooks such as **`rowCustomizer`**, **`selectionMode`**, **`expansionConfig`**, and preloaded
  rows.

Keep app-specific concerns in the owner widget or its provider. The datagrid receives normalized
columns, rows, and row templates; it does not resolve application templates, filters, or response
payloads itself. It asks the configured provider for already-normalized rows.

### RowProvider

`Et2RowProvider` is the template adapter between an owner widget and the datagrid. It reads either a
named eTemplate or slotted markup and returns `Et2DatagridTemplateData`.

Its responsibilities include:

- finding the effective header and row template markup
- extracting column metadata from headers
- normalizing legacy row wrappers such as `<row>` into datagrid-renderable markup
- preparing a reusable `HTMLTemplateElement` for rows
- converting row widgets to readonly display behaviour where needed
- recording row-scoped widget attributes in `rowTemplateAttrMap`
- preparing optional loader template data

`Et2RowProvider` does not fetch row data. It prepares the shape used to render rows once data is
available.

### DataProviders

`Et2DatagridDataProvider` is the data boundary for the datagrid. A provider supplies rows in the
datagrid's normalized shape:

```ts
{
	id: string;
	data: any;
}
```

The required provider methods are:

- `fetchPage(start, pageSize)` — load a page of rows and optionally return the total row count.
- `refresh(rowIds, type)` — resolve updated rows and removed row ids for refresh operations.
- `normalizeRowId(rowId, ensurePrefix)` — convert ids to the datastore form used by the rendered grid.
- `toProviderRowId(dataStoreRowId)` — convert rendered/datastore ids back to the provider's native row id.

`Et2DatagridRow.id` should be the stable id the grid uses for selection, duplicate suppression,
refresh matching, and rendered `data-row-id` attributes. Providers that work with application-native
ids should normalize them in `fetchPage()` / `refresh()` and use `toProviderRowId()` when they need
to call back into the application or server with the native id.

Optional methods such as `getQuerySignature()` and `getDataStorePrefix()` let the datagrid detect
query changes and keep row ids stable across paging, refreshes, nested grids, and action handling.

`Et2NextmatchDataProvider` is the Nextmatch implementation. It adapts `egw.dataFetch()` and
`egw.dataRegisterUID()` into the generic provider interface, processes extra Nextmatch response
data such as select options and filter values, reads refreshed rows back from the central UID cache,
and supports child-grid providers for expandable rows.

### Presentation hooks (owner-facing properties)

| Property          | Type                                 | Purpose                                                                       |
|-------------------|--------------------------------------|-------------------------------------------------------------------------------|
| `columns`         | `Et2DatagridColumn[]`                | Visible column configuration, including sizing and optional hide expressions. |
| `dataProvider`    | `Et2DatagridDataProvider \| null`    | Paging adapter used by infinite scroll.                                       |
| `templateData`    | `Et2DatagridTemplateData \| null`    | Prepared template and metadata used to render each row.                       |
| `rowIdField`      | `string` (`"id"`)                    | Row-data field that contains the application row id.                          |
| `rowCustomizer`   | `Et2DatagridRowCustomizer \| null`   | Per-row hook for row/meta-cell presentation tweaks.                           |
| `selectionMode`   | `"none" \| "single" \| "multiple"`   | Row selection behavior (default `"multiple"`).                                |
| `expansionConfig` | `Et2DatagridExpansionConfig \| null` | Generic expanded-row hooks.                                                   |
| `view`            | `"row" \| "tile"`                    | Visual layout mode.                                                           |
| `pageSize`        | `number` (`50`)                      | Maximum rows requested per page load.                                         |
| `rowStylesheets`  | `CSSStyleSheet[]`                    | Stylesheets adopted into the row shadow DOM.                                  |

Child-grid / layout flags are covered in [Customization points](#customization-points-and-overrides).

---

## Minimal owner widget wiring

Most application code should use `et2-nextmatch` directly. Use `et2-datagrid` from another Et2
webcomponent when the owner needs datagrid mechanics but has its own way to prepare templates,
columns, filters, and data.

The owner is responsible for:

1. creating an `Et2RowProvider` with the owner as host,
2. resolving `Et2DatagridTemplateData` from a named template or from the owner's light-DOM slots,
3. rendering `et2-datagrid` with property bindings for columns, template data, and data provider,
4. calling `reload()` after configuration is ready, or `setInitialRows()` when rows were already
   loaded.

This is the minimal pattern used by an Et2 owner widget with slotted column/row templates:

```ts
import {html, LitElement, PropertyValues} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {state} from "lit/decorators/state.js";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {Et2Datagrid} from "./Et2Datagrid";
import {Et2RowProvider} from "./Et2RowProvider";
import type {
	Et2DatagridColumn,
	Et2DatagridDataProvider,
	Et2DatagridSelectionDetail,
	Et2DatagridTemplateData
} from "./Et2Datagrid.types";

@customElement("my-grid-owner")
export class MyGridOwner extends Et2Widget(LitElement)
{
	private readonly _rowProvider = new Et2RowProvider(this);

	@state()
	private _templateData : Et2DatagridTemplateData | null = null;

	@state()
	private _columns : Et2DatagridColumn[] = [];

	@state()
	private _configurationLoading = true;

	private _dataProvider : Et2DatagridDataProvider = new MyProvider();

	private get _datagrid() : Et2Datagrid | null
	{
		return this.shadowRoot?.querySelector("et2-datagrid") as Et2Datagrid | null;
	}

	private _handleSelectionChanged(event : CustomEvent<Et2DatagridSelectionDetail>)
	{
		this.dispatchEvent(new CustomEvent("my-grid-selection-changed", {
			detail: event.detail,
			bubbles: true,
			composed: true
		}));
	}

	private _handleActiveRowChanged(event : CustomEvent<{activeRowId : string | null; activeRowIndex : number}>)
	{
		this.dispatchEvent(new CustomEvent("my-grid-active-row-changed", {
			detail: event.detail,
			bubbles: true,
			composed: true
		}));
	}

	private _handleColumnsChanged(event : CustomEvent<{columns : Et2DatagridColumn[]}>)
	{
		this._columns = event.detail.columns;
	}

	private _handleLoadingError()
	{
		this.dispatchEvent(new CustomEvent("my-grid-loading-error", {
			bubbles: true,
			composed: true
		}));
	}

	async firstUpdated(changedProperties : PropertyValues)
	{
		super.firstUpdated(changedProperties);

		const templateData = await this._rowProvider.fromSlots();
		this._templateData = templateData;
		this._columns = templateData?.columns || [];
		this._configurationLoading = false;

		await this.updateComplete;
		await this._datagrid?.reload();
	}

	render()
	{
		return html`
            <et2-datagrid
                    ._parent=${this}
                    .columns=${this._columns}
                    .templateData=${this._templateData}
                    .dataProvider=${this._dataProvider}
                    .configurationLoading=${this._configurationLoading}
                    row-id-field="id"
                    selection-mode="multiple"
                    @et2-selection-changed=${this._handleSelectionChanged}
                    @et2-active-row-changed=${this._handleActiveRowChanged}
                    @et2-columns-changed=${this._handleColumnsChanged}
                    @et2-loading-error=${this._handleLoadingError}
            >
                <slot name="noResults" slot="noResults"></slot>
            </et2-datagrid>
        `;
	}
}
```

The provider class is implemented separately; see [`dataProvider` override](#dataprovider-override)
for the minimal contract. Owners commonly listen to `et2-selection-changed`,
`et2-active-row-changed`, `et2-columns-changed`, and loading events when they need to synchronize
outer action state, focus, preferences, or error handling.

The slotted markup belongs to the owner element, not directly to `et2-datagrid`; `Et2RowProvider`
reads these slots from its host:

```xml

<my-grid-owner>
    <tr class="th" slot="columns">
        <et2-nextmatch-header id="title" label="Title"></et2-nextmatch-header>
        <et2-nextmatch-header id="owner" label="Owner"></et2-nextmatch-header>
    </tr>

    <tr class="$class" slot="row">
        <et2-description id="${title}" noLang="1"></et2-description>
        <et2-description id="${owner}" noLang="1"></et2-description>
    </tr>
</my-grid-owner>
```

`._parent=${this}` is needed for Et2Widget-owned grids that rely on the owner widget's array
manager context during row hydration. `Et2Nextmatch` uses the same binding for its root grid.

If rows are already available, seed the grid instead of fetching the first page:

```ts
await this.updateComplete;
this._datagrid?.setInitialRows(preloadedRows);
```

For named eTemplates, replace `fromSlots()` with `fromTemplate(templateName)`:

```ts
const templateData = await this._rowProvider.fromTemplate("myapp.index.rows");
```

---

## Row templates: columns and rows

### Columns

Columns are derived from header widgets/elements and include key, title, and optional
width/minWidth/disabled metadata.

#### From Named Templates

When using `et2-nextmatch template="app.index.rows"`:

- Header definition is parsed from the template header row (`.th`, `thead`, or grid header structure).
- Column widgets (such as `et2-nextmatch-header`, `et2-nextmatch-sortheader`) are used to build column definitions.
- Legacy Nextmatch visibility/size preferences are mapped and applied by `et2-nextmatch`.

#### From Slotted Templates

When using slots (`template` attribute not set):

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
    <tr slot="row">...</tr>
</et2-nextmatch>
```

### Rows

Row rendering is driven by a row template.

:::tip

- The row template is not given any server-side processing when the template is initially loaded.
- Avoid legacy widgets in the row template.

:::

Field expression support in row templates:

- Legacy: `${row}[note]`, `$row_cont[note]`
- Shorthand: `${note}`, `$note`

Both work. For new templates, shorthand is recommended for readability.

#### From Named Templates

Named row templates are normalized from legacy eTemplate structures (`<row>`) into datagrid
renderable markup. Common legacy patterns continue to work.

#### From Slotted Templates

Slotted row template comes from `slot="row"`.

- Preferred wrapper: `<tr slot="row">`
- Legacy wrapper: `<row slot="row">`
- Any other wrapper is preserved as-is (for example `<sl-card slot="row">`)

If the wrapper is `<tr>` or `<row>`, bare child widgets are allowed and are auto-wrapped into `<td>`
cells.

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

### Loader Template

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

### No-Results Template

Optional template shown when loading is complete and no rows are available:

- Replaces the default no-results `<sl-alert>` entirely

Example:

```xml

<sl-alert slot="noResults" variant="neutral" open>
    <sl-icon slot="icon" name="inbox"></sl-icon>
    <strong>This mailbox is empty</strong>
</sl-alert>
```

---

## Styling row contents

Rows are rendered inside the `et2-datagrid` shadow DOM. Normal page CSS does not reach row contents
unless the target is exposed as a CSS part. `et2-nextmatch` loads the current application's
`templates/default/app.css` into the datagrid row shadow DOM, so row classes and widget selectors
that must affect row contents can live there.

### Static Widget Style

For a simple static change that applies to every row, add a class to the row template and style it
in `app.css`.

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

To style a widget's exposed internal part from the stylesheet, use `::part()` on the widget in the
row template.

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

Use `exportparts` when normal application CSS outside the datagrid needs to style a row widget's
internal part. The datagrid gathers row-template `exportparts` and forwards them through
`et2-nextmatch`.

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

For dynamic row state, put a class expression on the row or widget and style it in the stylesheet.
This is the most direct option when the server already supplies a class such as `overdue`,
`readonly`, or `cat_<ID>`.

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

When only one widget needs dynamic styling, you can put the class expression on that widget instead
of the whole row.

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

When an owner widget needs to change the same row styling for all rendered rows, set a CSS custom
property on the datagrid or owner widget and use it from the row stylesheet. This keeps row DOM
updates out of application code and lets the browser apply the change to currently rendered and newly
virtualized rows.

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

---

## Lifecycle (class internals)

The datagrid coordinates Lit's reactive lifecycle, the `@lit-labs/virtualizer`, a debounced request
queue, and a deferred row-upgrade pass. This section follows a row from initial setup to a hydrated,
interactive DOM node.

<svg viewBox="0 0 760 660" width="100%" role="img" aria-label="Et2Datagrid reactive lifecycle and async data pipeline" xmlns="http://www.w3.org/2000/svg">
    <style>
        .dg-title { font: 700 14px sans-serif; fill: #202124; }
        .dg-col-title { font: 700 12px sans-serif; }
        .dg-step { font: 600 11px sans-serif; }
        .dg-detail { font: 10px sans-serif; fill: #3c4043; }
        .dg-legend { font: 10px sans-serif; fill: #3c4043; }
    </style>
    <text class="dg-title" x="380" y="20" text-anchor="middle">Et2Datagrid lifecycle</text>
    <text class="dg-col-title" x="240" y="42" text-anchor="middle" fill="#1e8e3e">Reactive update cycle</text>
    <text class="dg-col-title" x="600" y="42" text-anchor="middle" fill="#b06000">Async data pipeline</text>
    <rect x="60" y="58" width="360" height="42" rx="8" fill="#e6f4ea" stroke="#1e8e3e" stroke-width="1.5"/>
    <text class="dg-step" x="240" y="83" fill="#0d652d" text-anchor="middle">constructor()</text>
    <rect x="60" y="114" width="360" height="58" rx="8" fill="#e6f4ea" stroke="#1e8e3e" stroke-width="1.5"/>
    <text class="dg-step" x="240" y="139" fill="#0d652d" text-anchor="middle">requestUpdate()</text>
    <text class="dg-detail" x="240" y="155" text-anchor="middle">property change schedules render</text>
    <rect x="60" y="186" width="360" height="58" rx="8" fill="#e6f4ea" stroke="#1e8e3e" stroke-width="1.5"/>
    <text class="dg-step" x="240" y="211" fill="#0d652d" text-anchor="middle">willUpdate()</text>
    <text class="dg-detail" x="240" y="227" text-anchor="middle">structure detection, column prefs, sourceColumnKeys</text>
    <rect x="60" y="258" width="360" height="58" rx="8" fill="#e6f4ea" stroke="#1e8e3e" stroke-width="1.5"/>
    <text class="dg-step" x="240" y="283" fill="#0d652d" text-anchor="middle">render()</text>
    <text class="dg-detail" x="240" y="299" text-anchor="middle">virtualizer config, virtual items, state</text>
    <rect x="60" y="330" width="360" height="72" rx="8" fill="#e6f4ea" stroke="#1e8e3e" stroke-width="1.5"/>
    <text class="dg-step" x="240" y="356" fill="#0d652d" text-anchor="middle">_renderVirtualRow() -> _buildRowElement()</text>
    <text class="dg-detail" x="240" y="373" text-anchor="middle">clone template, placeholders, _markRowElement</text>
    <text class="dg-detail" x="240" y="388" text-anchor="middle">_ensureMetaCell</text>
    <rect x="60" y="416" width="360" height="58" rx="8" fill="#e6f4ea" stroke="#1e8e3e" stroke-width="1.5"/>
    <text class="dg-step" x="240" y="441" fill="#0d652d" text-anchor="middle">updated()</text>
    <text class="dg-detail" x="240" y="457" text-anchor="middle">post-render structure sync, stylesheets</text>
    <rect x="60" y="488" width="360" height="58" rx="8" fill="#e8eaed" stroke="#80868b" stroke-dasharray="6 4" stroke-width="1.5"/>
    <text class="dg-step" x="240" y="513" fill="#3c4043" text-anchor="middle">firstUpdated()</text>
    <text class="dg-detail" x="240" y="529" text-anchor="middle">first time only</text>
    <rect x="60" y="560" width="360" height="72" rx="8" fill="#e8f0fe" stroke="#1a73e8" stroke-width="1.5"/>
    <text class="dg-step" x="240" y="586" fill="#174ea6" text-anchor="middle">Deferred hydration (async)</text>
    <text class="dg-detail" x="240" y="603" text-anchor="middle">MutationObserver -> _processRowUpgradeQueue</text>
    <text class="dg-detail" x="240" y="618" text-anchor="middle">_applyRowElementAttributes</text>
    <rect x="470" y="114" width="260" height="72" rx="8" fill="#fef7e0" stroke="#f9ab00" stroke-width="1.5"/>
    <text class="dg-step" x="600" y="139" fill="#b06000" text-anchor="middle">loadMore() / reload()</text>
    <text class="dg-step" x="600" y="155" fill="#b06000" text-anchor="middle">or placeholder</text>
    <text class="dg-detail" x="600" y="171" text-anchor="middle">-> _requestChunkForRowIndex()</text>
    <rect x="470" y="202" width="260" height="42" rx="8" fill="#fef7e0" stroke="#f9ab00" stroke-width="1.5"/>
    <text class="dg-step" x="600" y="227" fill="#b06000" text-anchor="middle">_queueRequest() (debounced 100ms)</text>
    <rect x="470" y="258" width="260" height="42" rx="8" fill="#fef7e0" stroke="#f9ab00" stroke-width="1.5"/>
    <text class="dg-step" x="600" y="283" fill="#b06000" text-anchor="middle">_fetchPage()</text>
    <rect x="470" y="316" width="260" height="58" rx="8" fill="#fef7e0" stroke="#f9ab00" stroke-width="1.5"/>
    <text class="dg-step" x="600" y="341" fill="#b06000" text-anchor="middle">dataProvider.fetchPage()</text>
    <text class="dg-detail" x="600" y="357" text-anchor="middle">await rows + total</text>
    <rect x="470" y="390" width="260" height="58" rx="8" fill="#fef7e0" stroke="#f9ab00" stroke-width="1.5"/>
    <text class="dg-step" x="600" y="415" fill="#b06000" text-anchor="middle">_reconcileRowRenderState()</text>
    <text class="dg-detail" x="600" y="431" text-anchor="middle">-> requestUpdate()</text>
    <line x1="240" y1="100" x2="240" y2="114" stroke="#5f6368" stroke-width="1.4"/>
    <polygon points="240,114 235,105 245,105" fill="#5f6368"/>
    <line x1="240" y1="172" x2="240" y2="186" stroke="#5f6368" stroke-width="1.4"/>
    <polygon points="240,186 235,177 245,177" fill="#5f6368"/>
    <line x1="240" y1="244" x2="240" y2="258" stroke="#5f6368" stroke-width="1.4"/>
    <polygon points="240,258 235,249 245,249" fill="#5f6368"/>
    <line x1="240" y1="316" x2="240" y2="330" stroke="#5f6368" stroke-width="1.4"/>
    <polygon points="240,330 235,321 245,321" fill="#5f6368"/>
    <line x1="240" y1="402" x2="240" y2="416" stroke="#5f6368" stroke-width="1.4"/>
    <polygon points="240,416 235,407 245,407" fill="#5f6368"/>
    <line x1="240" y1="474" x2="240" y2="488" stroke="#5f6368" stroke-width="1.4"/>
    <polygon points="240,488 235,479 245,479" fill="#5f6368"/>
    <line x1="240" y1="546" x2="240" y2="560" stroke="#5f6368" stroke-width="1.4"/>
    <polygon points="240,560 235,551 245,551" fill="#5f6368"/>
    <line x1="600" y1="186" x2="600" y2="202" stroke="#b06000" stroke-width="1.4"/>
    <polygon points="600,202 595,193 605,193" fill="#b06000"/>
    <line x1="600" y1="244" x2="600" y2="258" stroke="#b06000" stroke-width="1.4"/>
    <polygon points="600,258 595,249 605,249" fill="#b06000"/>
    <line x1="600" y1="300" x2="600" y2="316" stroke="#b06000" stroke-width="1.4"/>
    <polygon points="600,316 595,307 605,307" fill="#b06000"/>
    <line x1="600" y1="374" x2="600" y2="390" stroke="#b06000" stroke-width="1.4"/>
    <polygon points="600,390 595,381 605,381" fill="#b06000"/>
    <line x1="470" y1="150" x2="420" y2="287" stroke="#b06000" stroke-dasharray="5 4" stroke-width="1.4"/>
    <polygon points="420,287 418,277 428,281" fill="#b06000"/>
    <line x1="470" y1="419" x2="420" y2="143" stroke="#b06000" stroke-dasharray="5 4" stroke-width="1.4"/>
    <polygon points="420,143 426,151 417,153" fill="#b06000"/>
    <rect x="60" y="646" width="12" height="12" rx="3" fill="#e6f4ea" stroke="#1e8e3e"/>
    <text class="dg-legend" x="78" y="656">reactive lifecycle</text>
    <rect x="210" y="646" width="12" height="12" rx="3" fill="#fef7e0" stroke="#f9ab00"/>
    <text class="dg-legend" x="228" y="656">async data fetch</text>
    <rect x="350" y="646" width="12" height="12" rx="3" fill="#e8f0fe" stroke="#1a73e8"/>
    <text class="dg-legend" x="368" y="656">deferred hydration</text>
    <line x1="500" y1="652" x2="528" y2="652" stroke="#5f6368" stroke-width="1.4" stroke-dasharray="5 4"/>
    <text class="dg-legend" x="536" y="656">cross-cutting / entry</text>
</svg>

### Construction

`constructor()` ([Et2Datagrid.ts:490](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L490))
is intentionally light: it measures the browser scrollbar
width once and binds stable method references so listeners and the virtualizer can add/remove them
cleanly. No DOM or data work happens here. The default properties (`columns`, `dataProvider`,
`pageSize`, `selectionMode`, and the private `_columnManager` / `_columnState` instances) are set by
the property decorators above.

### First paint

`firstUpdated()` ([Et2Datagrid.ts:542](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L542))
runs once after the first render. It:

- attaches the passive scroll listener (`_maybePrefetchOnScroll`) to the scroll body,
- installs the `MutationObserver` row-upgrade watcher via `_initRowUpgradeObserver()`,
- sets up column-resize `interact` handlers.

### Structural change detection

`willUpdate()` ([Et2Datagrid.ts:556](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L556))
runs before each render whenever a reactive property changed.
For structure-defining inputs — `templateData`, `view`, `rowIdField`, `columns`,
`columnPreferenceName`, `noColumnPersistence`, `noVisibleHeader` — it:

- invalidates the loaded column-preference key so `_loadColumnPreferencesIfNeeded()` re-reads persisted column state,
- captures `_sourceColumnKeys` from `templateData.sourceColumns` (used to remap cells after column reordering),
- rebuilds the visible header node set via `_prepareVisibleHeaders()`,
- marks `_postRenderStructureSyncNeeded` so `updated()` can reapply column sizes/visibility,
- clears the row-upgrade queue and resets virtualizer caches when the structure changed.

When `columns` change it also rebuilds the cached customfield column state
(`_rebuildCustomfieldColumnStateCache()`), which row hydration reads to avoid per-row header scans.

`updated()` ([Et2Datagrid.ts:618](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L618))
performs the post-render structure sync (column sizes,
visibility), re-adopts `rowStylesheets`, re-runs the row-upgrade observer/scan, and restores focus
to the active row after a render if needed.

### Data-load trigger

There are two ways rows start loading:

1. **Owner-driven** —
   `loadMore()` ([Et2Datagrid.ts:4197](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L4197))
   and
   `reload()` ([Et2Datagrid.ts:3982](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L3982))
   request from index `0`. `reload()` wipes all loaded rows (`_clearRows()`), resets `total`, and
   re-fetches. These are the public entry points an owner widget calls.
2. **Virtualizer-driven** — when the virtualizer renders an index that has no loaded row,
   `_renderVirtualRow()` ([Et2Datagrid.ts:1835](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1835))
   emits a placeholder and calls
   `_requestChunkForRowIndex()` ([Et2Datagrid.ts:1934](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1934)).
   This is how infinite-scroll paging and
   scroll-into-view prefetch happen. `scroll` events also re-arm queued-request processing via
   `_maybePrefetchOnScroll()`.

Both paths funnel into the same debounced queue.

### Fetch

The request queue coalesces bursts (fast scrolling) and dedupes by a deterministic key built from
start, count, and provider query signature (`_requestKey()`):

`_queueRequest()` ([Et2Datagrid.ts:1205](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1205))
records the request and bumps `_pendingPlaceholderCount`
(embedded grids reserve a single loading row).

`_scheduleQueuedRequestProcessing()` ([Et2Datagrid.ts:1219](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1219))
debounces (default 100 ms).

`_processQueuedRequests()` ([Et2Datagrid.ts:1349](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1349))
marks the request in-flight, sets `loading` /
`fetching`, dispatches `et2-loading-start`, and calls `_fetchPage()`.

`_fetchPage()` ([Et2Datagrid.ts:1243](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1243))
awaits `dataProvider.fetchPage(start, count)` and:

- sets `fetchFailed` / `fetchErrorMessage` and `_hasFetchedOnce`,
- writes `this.total` from the response when present,
- merges rows into `_rowsByIndex[index]`, deduplicating via `displayedRowIds`, then rebuilds the flat
  `this.rows` array,
- in `finally`, decrements placeholders, removes the in-flight key, fires `et2-loading-done` or
  `et2-loading-error`, and calls `_reconcileRowRenderState()`.

`_reconcileRowRenderState()` ([Et2Datagrid.ts:1394](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1394))
prunes expanded rows that became non-expandable
after refresh and, when `autoActivateFirstRow` is set and no active row exists, pins `activeRowIndex
= 0` so keyboard navigation works as soon as the first row appears.

`loading`, `fetching`, and `total` are `@state()` so the relevant templates re-render. The
`_stateTemplate()` ([Et2Datagrid.ts:4250](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L4250))
resolves the high-level visual state — initial loading,
fetch error, missing template, or empty — and renders the loader, error, or no-results UI accordingly.

### Row generation

`render()` ([Et2Datagrid.ts:4444](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L4444))
is the heart of presentation. For each render it:

- computes `visibleColumns` and resolves CSS custom properties (`--column-sizes`, `--column-count`,
  `--scrollbar-space`, `--embedded-virtualized-height`),
- computes
  `rowCount = _virtualRowCount()` ([Et2Datagrid.ts:2022](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2022)),
  which is `total` when known or the
  materialized count otherwise,
- builds the virtualizer `items` via
  `_getVirtualItems()` ([Et2Datagrid.ts:1982](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1982)) —
  inserting an
  `expanded` item immediately after each expanded parent,
- passes a stable `keyFunction` (
  `_virtualRowKey`, [Et2Datagrid.ts:2062](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2062))
  and the
  `renderItem` callback (`_renderVirtualRow`) to `virtualize()`.

`_renderVirtualRow()` ([Et2Datagrid.ts:1835](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1835))
is invoked by the virtualizer for every visible slot:

- if the row exists in `_rowsByIndex`, it calls `_buildRowElement()` and serializes the result with
  `unsafeHTML` (rows are stamped as fast, inert DOM strings for throughput),
- otherwise it renders a placeholder (skeleton or the slot loader) and triggers a chunk request.

`_buildRowElement()` ([Et2Datagrid.ts:1544](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1544))
is where a logical row becomes a DOM node:

1. It clones the prepared `<template>` (`document.importNode(template.content, true)`) — or falls back
   to a simple `<tr>` built from column values when no template exists.
2. `_populateCloneWithRow()` ([Et2Datagrid.ts:2401](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2401))
   walks text nodes and resolves simple `$row.*` placeholders via
   `Et2RowProvider.resolveSimpleRowPlaceholders()`.
3. `_populateRowRootAttributes()` ([Et2Datagrid.ts:2423](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2423))
   resolves root-level placeholder attributes (e.g. dynamic row classes) via
   `Et2RowProvider.customizeRowRootAttributes()`.
4. `_markRowElement()` ([Et2Datagrid.ts:2116](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2116))
   stamps accessibility/identity attributes: `role`, `data-row-id`, `data-row-index`,
   `aria-rowindex`, `aria-selected`, and `tabindex`.
5. `_ensureMetaCell()` ([Et2Datagrid.ts:1602](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1602))
   ensures the leading metadata cell exists and wires the row expander when the row is expandable;
   it also invokes `rowCustomizer` (see [Customization points](#customization-points-and-overrides)).
6. The node is tagged with the `loading` class and `_applyColumnLayoutToRowElement()` applies column
   track sizing.

### Hydration (deferred row binding)

Row templates are stamped as inert strings, so widget binding and row-scoped array-manager
expansion are deferred to keep scrolling/rendering responsive.

`_initRowUpgradeObserver()` ([Et2Datagrid.ts:1755](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L1755))
installs a `MutationObserver` on the scroll body
that calls `_upgradeRenderedRows()` whenever rows are added/moved.

`_upgradeRenderedRows()` ([Et2Datagrid.ts:2211](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2211))
finds newly realized physical rows, skips already-upgraded
nodes for the same row identity, and enqueues them.

`_processRowUpgradeQueue()` ([Et2Datagrid.ts:2326](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2326))
processes a bounded batch per animation frame
(≤ 8 rows, 8 ms budget) so input/paint stay smooth. For each row it calls
`_applyRowElementAttributes()`.

`_applyRowElementAttributes()` ([Et2Datagrid.ts:2436](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2436))
is the hydration core. It:

- reads `rowTemplateAttrMap` for each element carrying `data-et2nm-id`,
- builds a row-scoped content array manager (`mgrRowData[rowIndex] = rowData`) and opens a perspective
  so `$row.*` expressions resolve against *this* row,
- for `et2-customfields-list` elements, applies cached customfield state via
  `_applyCustomfieldRowState()`,
- for the row root, applies stored root attributes,
- for other widgets, calls `transformAttributes(stored)` (or falls back to `setAttribute` with
  `mgr.expandName()`),
- re-runs the row customizer and finally removes the `loading` class.

Because the virtualizer can materialize rows after `updated()`, `_scheduleRenderedRowsUpgradeScan()`
([Et2Datagrid.ts:2249](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2249))
re-scans for up to ~30 frames to catch late handoff.

### Refresh and reload

`refresh(row_ids, type)` ([Et2Datagrid.ts:4001](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L4001))
applies a targeted refresh without a full reload.
For `DELETE` it removes rows client-side (
`_removeRowsById`, [Et2Datagrid.ts:4121](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L4121));
otherwise it
awaits `dataProvider.refresh()` and merges results via
`_applyRefreshedRows()` ([Et2Datagrid.ts:4048](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L4048)),
which swaps row data in place, bumps a render version (triggering a re-render of that row via the
virtual key), and pulses the changed rows for visual feedback.

`reload()` ([Et2Datagrid.ts:3982](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L3982))
wipes loaded state and re-fetches from index 0.

---

## Rendering pipeline (summary)

Row rendering is split into preparation and per-row hydration:

1. `Et2RowProvider` finds the row template, normalizes it, extracts columns, and compiles it into a
   reusable template. Row-independent values are resolved at this stage, including static `$cont` /
   `@...` expressions and literal strings.
2. `Et2RowProvider` records row-scoped widget attributes in the template attribute map instead of
   permanently resolving them on the reusable template.
3. `Et2Datagrid` clones the prepared row template for each row needed by the virtualizer.
4. `Et2Datagrid` applies recorded row-scoped widget attributes to the clone using that row's
   `rowData`, which came from the configured `Et2DatagridDataProvider`.

This order keeps static template work shared across all rows while preserving correct row-specific
values for each physical row clone. The virtualizer determines which row indexes need DOM nodes and
manages their addition and removal; the data provider supplies row objects, and the prepared template
controls how each row object becomes DOM.

---

## Key methods

Visibility legend: **public** (called by owner widgets), **private** (internal only).

| Method                               | Visibility | Stage    | Purpose                                                                       |
|--------------------------------------|------------|----------|-------------------------------------------------------------------------------|
| `loadMore()`                         | public     | load     | Request the first (or next missing) page from index 0.                        |
| `reload()`                           | public     | load     | Clear all rows and re-fetch; reset `total` and error state.                   |
| `setInitialRows(rows)`               | public     | load     | Seed preloaded row data and skip the initial provider fetch.                  |
| `refresh(row_ids, type)`             | public     | refresh  | Targeted in-place refresh or removal without a full reload.                   |
| `selectSingleRow(rowId)`             | public     | selection | Select one loaded row and emit `et2-selection-changed`.                       |
| `selectAllRows()`                    | public     | selection | Select all currently loaded rows when `selectionMode` is `multiple`.          |
| `clearSelection()`                   | public     | selection | Clear selected rows and optionally emit the selection event.                  |
| `focusFirstRow()`                    | public     | focus    | Move active focus to the first loaded row.                                    |
| `focusRowById(rowId)`                | public     | focus    | Move active focus to a loaded row by id.                                      |
| `clearActiveRow()`                   | public     | focus    | Clear active-row focus state.                                                 |
| `openColumnSelection(event?)`        | public     | columns  | Open the column chooser from an owner-level control.                          |
| `render()`                           | Lit        | render   | Builds the virtualizer config, headers, state template, and CSS vars.         |
| `_getVirtualItems()`                 | private    | render   | Builds virtualizer item list, inserting expanded items after parents.         |
| `_virtualRowCount()`                 | private    | render   | Resolves slot count (`total` vs materialized count).                          |
| `_virtualRowKey()`                   | private    | render   | Stable key per row/expanded/placeholder; includes structure + render version. |
| `_renderVirtualRow()`                | private    | generate | Per-slot callback: build row DOM or emit placeholder + chunk request.         |
| `_requestChunkForRowIndex()`         | private    | generate | Queue a page fetch for the chunk owning an unloaded index.                    |
| `_buildRowElement()`                 | private    | generate | Clone template, resolve placeholders, mark, ensure meta cell, layout.         |
| `_populateCloneWithRow()`            | private    | generate | Resolve `$row.*` text placeholders in the cloned fragment.                    |
| `_populateRowRootAttributes()`       | private    | generate | Resolve placeholder attributes on the row root.                               |
| `_markRowElement()`                  | private    | generate | Stamp a11y/identity attributes (`role`, `data-row-*`, `aria-*`, `tabindex`).  |
| `_ensureMetaCell()`                  | private    | generate | Ensure leading metadata cell + expander; invoke `rowCustomizer`.              |
| `_applyColumnLayoutToRowElement()`   | private    | generate | Apply column track sizing to the row.                                         |
| `_initRowUpgradeObserver()`          | private    | hydrate  | `MutationObserver` that enqueues realized rows for hydration.                 |
| `_upgradeRenderedRows()`             | private    | hydrate  | Find newly realized rows, skip upgraded, enqueue.                             |
| `_processRowUpgradeQueue()`          | private    | hydrate  | Bounded per-frame hydration: bind widgets, customfields, customizer.          |
| `_applyRowElementAttributes()`       | private    | hydrate  | Apply `rowTemplateAttrMap` attrs via row-scoped array manager.                |
| `_scheduleRenderedRowsUpgradeScan()` | private    | hydrate  | Re-scan frames to catch virtualizer-late rows.                                |
| `_queueRequest()`                    | private    | fetch    | Record a debounced request + placeholder count.                               |
| `_processQueuedRequests()`           | private    | fetch    | Dispatch queued requests, fire `et2-loading-start`.                           |
| `_fetchPage()`                       | private    | fetch    | Await `dataProvider.fetchPage()`, merge rows, fire done/error.                |
| `_reconcileRowRenderState()`         | private    | fetch    | Prune stale expansions, pin first active row, request render.                 |
| `_stateTemplate()`                   | private    | state    | Resolve loading/error/missing-template/empty UI.                              |
| `_applyRefreshedRows()`              | private    | refresh  | Merge provider refresh result; pulse changed rows.                            |
| `_removeRowsById()`                  | private    | refresh  | Remove rows client-side; fix counts/selection.                                |

---

## Customization points and overrides

### `rowCustomizer`

`rowCustomizer` is a `Et2DatagridRowCustomizer` callback invoked for every realized row, both during
row generation (`_ensureMetaCell`) and again after hydration (`_rerunRowCustomizer`,
[Et2Datagrid.ts:2530](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2530)).
Use it for presentation tweaks that depend on row data — badges, meta-cell
indicators, or DOM adjustments the row template cannot express.

```ts
import type {Et2DatagridRowCustomizer} from "./Et2Datagrid.types";

const rowCustomizer : Et2DatagridRowCustomizer = ({rowElement, rowData, rowIndex, metaCell}) =>
{
	// Add a per-row indicator into the leading metadata cell.
	if(rowData.overdue)
	{
		metaCell.classList.add("row-overdue");
	}
	// Toggle a class on the whole row based on data.
	rowElement.classList.toggle("row-priority-high", rowData.priority === "high");
};

this.datagrid.rowCustomizer = rowCustomizer;
```

The context is `{ rowElement, rowData, rowIndex, metaCell }`. `metaCell` is the leading column-0 cell
(`td[data-dg-meta-cell="1"]` in row view, `[data-dg-meta-cell="1"]` in tile view).

### `expansionConfig` (expandable rows)

`Et2DatagridExpansionConfig` owns the *content* of expandable rows; the datagrid owns the expander
mechanics and column alignment. The contract is intentionally free of Nextmatch-specific hierarchy —
Nextmatch maps its `parent_id` / `is_parent` fields into these hooks.

| Hook                             | Purpose                                                                                    |
|----------------------------------|--------------------------------------------------------------------------------------------|
| `isExpandable(row, rowIndex)`    | Return `true` for rows that should render an expander.                                     |
| `renderExpandedContent(context)` | Render detail content immediately after the parent row. Can return another `et2-datagrid`. |
| `expandedRowIds?`                | Optional controlled set of expanded row ids.                                               |
| `onExpandedRowIdsChanged?`       | Called with the next expanded-id set when expansion toggles.                               |
| `emptyTemplate?`                 | Optional empty-state renderer for custom detail UI.                                        |

```ts
import {Et2Datagrid} from "./Et2Datagrid";
import type {Et2DatagridExpansionConfig} from "./Et2Datagrid.types";

const expansionConfig : Et2DatagridExpansionConfig = {
	// Expand only parent rows (Nextmatch hierarchy contract).
	isExpandable: (row) => !!row.data.is_parent,

	renderExpandedContent: ({row, parentGrid}) =>
	{
		// Return another datagrid as the child detail view.
		const parent = parentGrid as Et2Datagrid;
		const child = document.createElement("et2-datagrid") as Et2Datagrid;
		child.dataProvider = new ChildProvider(row.data.id);
		child.columns = parent.columns;
		child.templateData = parent.templateData;
		child.embeddedVirtualized = true;     // no own scrollport; parent scrolls
		child.noColumnPersistence = true;     // columns owned by parent
		return child;
	},

	// Controlled expansion state (optional).
	expandedRowIds: new Set<string>(),
	onExpandedRowIdsChanged: (next) =>
	{
		// Persist or reflect expansion state in the owner widget.
	},
};

this.datagrid.expansionConfig = expansionConfig;
```

The expanded content receives `Et2DatagridExpandedRowContext` (`row`, `rowIndex`, `parentGrid`,
`columnSizes`, `metaColumnWidth`) so it can align itself to the parent column tracks.

### `dataProvider` override

Subclass or implement `Et2DatagridDataProvider` to supply rows from any source. A minimal custom
provider:

```ts
import type {
	Et2DatagridDataProvider,
	Et2DatagridPageResult,
	Et2DatagridRefreshResult,
	Et2DatagridUpdateType
} from "./Et2Datagrid.types";

class MyProvider implements Et2DatagridDataProvider
{
	async fetchPage(start : number, pageSize : number) : Promise<Et2DatagridPageResult>
	{
		const page = await myApi.getRows(start, pageSize);
		return {
			rows: page.rows.map(r => ({id: this.normalizeRowId(r.id, true), data: r})),
			total: page.total
		};
	}

	async refresh(row_ids : string[], _type : Et2DatagridUpdateType) : Promise<Et2DatagridRefreshResult>
	{
		const providerIds = row_ids.map(id => this.toProviderRowId(this.normalizeRowId(id, true)));
		const rows = await myApi.getRowsById(providerIds);
		return {
			rows: rows.map(r => ({id: this.normalizeRowId(r.id, true), data: r})),
			removedRowIds: []
		};
	}

	// Datastore ids may carry a type prefix (e.g. "calendar::123"). Keep them stable.
	normalizeRowId(rowId : string | number, ensurePrefix = false) : string
	{
		const id = String(rowId);
		return ensurePrefix && !id.includes("::") ? `myapp::${id}` : id;
	}

	toProviderRowId(dataStoreRowId : string) : string
	{
		return dataStoreRowId.replace(/^myapp::/, "");
	}

	// Optional: lets the grid skip refetching when the query is unchanged.
	getQuerySignature() : string
	{
		return myApi.currentQuerySignature();
	}
}

this.datagrid.dataProvider = new MyProvider();
```

`getQuerySignature()` should change whenever the underlying query (filters, sort) changes, so the
request-dedup key (`_requestKey`) and virtual keys invalidate stale rows. `getDataStorePrefix()` is
used by nested/child grids to namespace row ids.

### Row-scoped deferred attributes (`rowTemplateAttrMap`)

Row templates may carry attributes that depend on the row, such as `class="$class
priority_${priority}"`. `Et2RowProvider` does **not** permanently resolve these on the shared template;
instead it records them in `templateData.rowTemplateAttrMap` keyed by the generated widget id
(`data-et2nm-id`). During hydration, `_applyRowElementAttributes()` expands them against a row-scoped
array manager so each physical clone gets its own correct values.

As an app author you normally do not populate `rowTemplateAttrMap` directly — you write the expression
in the template and let `Et2RowProvider` record it. The mechanism matters when you build custom row
templates or providers and need row-specific widget attributes that cannot be expressed as static
template text.

### Column hide expressions and headers

Column metadata (`Et2DatagridColumn`) may carry a `disabled` flag (fixed hidden/unavailable in the
chooser) and a `hidden` flag (currently hidden but user-toggleable). Headers are live elements
(`column.header`) and may expose methods such as `getCustomfieldVisibility()` — used by the datagrid
to cache customfield state per column (`_rebuildCustomfieldColumnStateCache`). Column hide/show
expressions are evaluated by
`_parseColumnBooleanExpression()` ([Et2Datagrid.ts:2709](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Nextmatch/Et2Datagrid.ts#L2709))
against the
content array manager. Custom column widgets can therefore drive visibility and sizing through their
own header element.

### Child-grid and layout flags

| Property               | Effect                                                                                             |
|------------------------|----------------------------------------------------------------------------------------------------|
| `inheritColumnSizes`   | Let `--column-sizes` inherit from the host (child grids whose tracks are owned by a parent).       |
| `noColumnPersistence`  | Disable loading/saving column preferences (child grids).                                           |
| `noColumnResize`       | Disable interactive column resizing owned by another component.                                    |
| `noVisibleHeader`      | Hide only the visible header row; `<thead>` still renders for a11y/sizing.                         |
| `autoHeight`           | Grow to fit rows instead of creating an own scroll body (expanded children).                       |
| `embeddedVirtualized`  | Embedded virtualized grid inside an ancestor scrollport — keeps lazy paging but no own scrollport. |
| `autoActivateFirstRow` | Mark the first loaded row active (subgrids disable this).                                          |
| `view = "tile"`        | Non-row wrapping virtualized layout; every entry remains its own item.                             |
