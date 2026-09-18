# Converting the history log to `Et2Datagrid`: `et2-historylog`

Replacing the legacy `historylog` widget (`api/js/etemplate/et2_widget_historylog.ts`, tag
`<historylog>`) with a web component (`et2-historylog`) built on `Et2Datagrid` and friends, and
giving it the filtering the legacy widget never had.

Status: **done.** All phases complete, verified live in all five apps, committed on
`claude/et2-historylog` as `* Api: convert historylog to webComponent` plus a separate removal
commit. What was knowingly left undone is listed under
[Implementation status](#implementation-status).

For widget usage of the pieces being reused, see the generated component docs for
[`et2-datagrid`](https://etemplate.egroupware.org/components/et2-datagrid/) and
[`et2-filterbox`](https://etemplate.egroupware.org/components/et2-filterbox/), plus
`api/js/etemplate/Et2Datagrid/Et2Datagrid.md` for the owner-widget contract — this document does not
repeat that reference material.

Closely related: `et2-nextmatch-conversion.md` in this directory. The two share `Et2Datagrid`,
`Et2RowProvider`, `Et2NextmatchDataProvider`, `Et2Filterbox` and `NextmatchInterface`, and the
history log is the *second* consumer of that stack rather than a variant of the first.

---

## Why

Two reasons, the second one being the real driver.

1. The legacy widget hand-builds `et2_dataview` + `et2_dataview_controller`, writes its column
   headers with `jQuery().text()`, clones detached DOM nodes per row, and lays out its diff rows with
   a jQuery `colspan` hack plus hand-computed pixel widths read back from the column manager. It has
   no filtering, no search, and no keyboard navigation.

2. `et2_widget_historylog.ts` and `et2_extension_nextmatch.ts` are the **last two consumers of the
   whole legacy `et2_dataview_*` stack** (13 files: `et2_dataview.ts`, `et2_dataview_controller.ts`,
   `et2_dataview_controller_selection.ts`, `et2_dataview_model_columns.ts`, `et2_dataview_view_*.ts`,
   `et2_dataview_interfaces.ts`). Converting the history log removes half of what keeps that stack
   alive; the nextmatch conversion removes the other half.

## What exists today

### Client

`et2_widget_historylog.ts` (746 lines), `et2_valueWidget` subclass, registered as `historylog`:

- Implements `et2_IDataProvider` itself, calling `egw.dataFetch()` / `egw.dataRegisterUID()` directly.
- Hard-codes five columns in a `static columns` array: `user_ts`, `owner`, `status`, `new_value`,
  `old_value`, with index constants `TIMESTAMP`/`OWNER`/`FIELD`/`NEW_VALUE`/`OLD_VALUE`. The
  `columns` attribute controls visibility only.
- `createWidgets()` eagerly instantiates one widget per entry in `value['status-widgets']`, **plus
  one per custom field** (via the legacy `et2_customfields_list`), and keeps each one's
  `getDetachedNodes()`.
- `rowCallback()` picks the right prototype per row from `_data.status` and clones it. The widget
  varies **per row**, which is the one thing `Et2Datagrid`'s fixed row template does not do natively
  — see [The polymorphic value cell](#the-polymorphic-value-cell).
- Diff rows: when `old_value === '***diff***'`, the new-value cell gets an `<et2-diff>`, then
  `_spanValueColumns()` sets `colspan=2` and a pixel width via jQuery and deletes the next `<td>`.
- `share_email` overrides the owner cell.
- Defers all of `finishInit()` until its `sl-tab-show` fires, so an unopened history tab costs nothing.
- `_createNamespace()` returns `true` — content lives under the widget id (`content['history']`).

### Server

- `api/src/Etemplate/Widget/HistoryLog.php` — seeds the trusted `get_rows`
  (`Api\Storage\History::get_rows` unless the template overrides it) and namespaces `sel_options`
  under the widget's form name, so the history log's `owner` select can hold all accounts without
  clobbering the surrounding dialog's own `owner` options.
- `Api\Storage\History::get_rows()` — the data source. `UNION`s the history table with a second
  query over `egw_sqlfs` for `/apps/<app>/<id>` attachments, computes unified diffs for large values
  via `Horde_Text_Diff`, and fires the `etemplate2_history_get_rows` hook so apps can post-process
  rows (calendar uses it to shift timestamps to user time).
- Rows are fetched through `Nextmatch::ajax_get_rows()`, shared with the real nextmatch.

### Consumers

Five templates, all `edit.xet`: addressbook, infolog, calendar (with `options="history_status"`,
i.e. `status_id`), timesheet, resources. Plus `api/templates/test/nextmatch_test.xet`. **No
`templates/mobile/` counterpart uses it.**

## Server-side gaps found while reading

These are pre-existing and worth knowing before touching `History::get_rows()`.

| Finding | Detail |
|---|---|
| `get_rows()` read `$query['colfilter']` | Nothing has **ever** set `colfilter` — added in 2010 (`7ea2ef612c`) as a read-only key, with no writer anywhere in the repo's history, so the filtering behind it was unreachable for fifteen years. eTemplate speaks `col_filter`. **Removed** rather than kept as an alias, so there is one spelling; `testGetRowsIgnoresTheOldColfilterSpelling` pins that. |
| `$query['search']` ignored | No search support at all. |
| `$query['filter']` was ignored | `calendar_uiforms::setup_history()` sets `$content['history']['filter']` to a raw SQL fragment scoping participant changes to the recurrence being viewed, but `get_rows()` built its `$filter` array from scratch and never read the key — so calendar's recurrence scoping had never worked. **Revived**, see [The server-side `filter` fragment](#the-server-side-filter-fragment). |
| `HistoryLog::validate()` is a pass-through | `if (true) $valid = $value;` copies client-supplied keys verbatim. Harmless today only because `get_rows()` ignores everything but `appname`/`record_id`. See [Returning no values](#returning-no-values) for why this must change in the same commit that adds filtering. |
| The attachments `UNION` leg | Any new filter must apply to both legs, or the `egw_sqlfs` leg must be dropped when a filter cannot apply to it. |

## Architecture: composition, not a nextmatch subclass

**`Et2Historylog` is a new owner widget over `et2-datagrid`. It does not extend `Et2Nextmatch`.**

`Et2Datagrid.md` prescribes exactly this case ("Use `et2-datagrid` from another Et2 webcomponent when
the owner needs datagrid mechanics but has its own way to prepare templates, columns, filters, and
data"). `Et2Nextmatch` is 4052 lines, and essentially all of the difference is things a history tab
must *not* have: per-app column preferences, favourites, the print dialog, the action
controller/drag-and-drop, letter search, auto-refresh, `parent_id` subgrids, and a filterbox that
walks up the DOM looking for an `egw-app` `slot="filter"` that does not exist inside an edit dialog.

Subclassing would mean less new code up front and then permanently suppressing all of the above.

Decisions taken since (see [Scope](#scope)) have made the gap wider, not narrower: with sorting,
selection, and actions all out, what remains is genuinely a thin owner.

### What is reused rather than reimplemented

| Reused | How |
|---|---|
| `Et2Datagrid` | virtualization, column sizing/resize, keyboard navigation, loaders, no-results |
| `Et2RowProvider` | `fromTemplate("api.historylog.rows")` → columns + row `<template>` |
| `Et2NextmatchDataProvider` | wraps `egw.dataFetch`/`dataRegisterUID`, including the row-cache keep-alive retention logic. Needs its `host : Et2Nextmatch` type widened — see below |
| `Et2Filterbox` | drives anything implementing `NextmatchInterface` |
| `mapCustomfieldToWidget()` | already returns `{tagName, attrs}` — the exact shape the value cell wants, with a `context: "row"` mode |
| `Et2Diff` | owns its own height cap and pop-out dialog already |
| `Et2AppBox`'s filter button + drawer | the pattern to echo for the filter UI |
| `Et2Nextmatch._whenLazyVisible()` | the tab-defer logic, whose docblock already says it mirrors the history log's. Extract to a shared helper rather than copy. |

### Data provider host widening

`Et2NextmatchDataProvider` is typed `host : Et2Nextmatch` but only uses a small surface: `egw()`,
`getInstanceManager()`, `id`, `_filters`, `settings`, `getArrayMgr()`, `getWidgetById()`, `sortBy()`,
`refreshColumnVisibility()`, `activeFilters`. Extract that as `NextmatchDataProviderHost` and widen
the constructor. Two branches in `processAdditionalData()` are nextmatch-specific (the `egw-app`
toolbar lookup and `_filterbox`) and need guards.

This is a shared-API change that every nextmatch depends on, so it gets its own commit and its own
run of the nextmatch suite before and after.

## Scope

Settled with Nathan while planning. Recorded here because several of these are the reason the
architecture above is as small as it is.

| Decision | Consequence |
|---|---|
| **Sort is locked to date descending.** Users cannot change it. | No sortable headers. `sortBy()`/`resetSort()` exist as no-ops for `NextmatchInterface` (and because `processAdditionalData()` calls `host.sortBy()` when a response echoes `order`). `validate()` **rejects** `order`/`sort` from the client, so no client input reaches an `ORDER BY` at all. `get_rows()` already defaults to `ORDER BY history_timestamp DESC, history_id ASC`. |
| **No row selection.** There are no actions. | `selection-mode="none"`. Keyboard navigation is kept — see [Keyboard, not selection](#keyboard-not-selection). `Et2NextmatchActionController` (2240 lines) stays out entirely, as do `_initActions()` / `findActionTarget()`. |
| **Keyboard navigation only.** | Arrow/Home/End move a visible cursor; nothing is ever selected. |
| **Diff rows stay height-capped with the existing pop-out.** | Zero work — it is `Et2Diff`'s own feature. See [Diff rows](#diff-rows). |
| **The legacy widget is not touched.** | One app is converted by hand for live testing, then all of them at once via `api/etemplate.php`. See [Migration](#migration). |
| **All four filters ship in v1.** | Changed field, user, date range, free-text search. |
| **Filters live in a drawer only, not in column headers.** | No `et2-nextmatch-header-*` widgets in the row template. See [Filtering](#filtering). |
| **The widget returns no values.** | Not an input widget client-side; contributes nothing to `$validated` server-side. See [Returning no values](#returning-no-values). |
| **No column persistence.** | `noColumnPersistence` on the datagrid. Columns come from the template's `columns` attribute every time, exactly as the legacy widget behaved. No preference key to design, none to migrate, and no repeat of the `columnselection_pref` audit the nextmatch conversion needed. |
| **Attachments are filtered via the existing `~file~` status**, not a new filter value. | See [Filter set](#filter-set-and-wire-format). Falls out of the status option list the widget already builds. |
| **`share_email` keeps working.** | A change made through a share records the share recipient, not an account. One cell (`et2-hbox`) holds an `et2-select-account` for `owner` and an `et2-description` for `share_email`; each hides itself via `hidden="$row_cont[share_email]"` / `hidden="!$row_cont[share_email]"`. Verified live with a synthetic row - see below. |

## Components

### `api/templates/default/historylog.xet` (new)

Row/header structure moves out of TypeScript into a real eTemplate, the way `cf-tab.xet` does for the
customfields conversion. Two templates in one file:

```xml
<template id="api.historylog.rows">
  <grid>
    <rows>
      <row class="th">
        <et2-nextmatch-header id="user_ts"   label="Date"/>
        <et2-nextmatch-header id="owner"     label="User"/>
        <et2-nextmatch-header id="status"    label="Changed"/>
        <et2-nextmatch-header id="new_value" label="New value"/>
        <et2-nextmatch-header id="old_value" label="Old value"/>
      </row>
      <row>
        <et2-date-time        id="user_ts" readonly="true"/>
        <et2-historylog-user  id="$row"/>
        <et2-select           id="status"  readonly="true"/>
        <et2-historylog-value id="$row" field="new_value"/>
        <et2-historylog-value id="$row" field="old_value"/>
      </row>
    </rows>
  </grid>
</template>

<template id="api.historylog.filters">
  <et2-vbox>
    <et2-searchbox      id="search"                label="Search"/>
    <et2-select         id="col_filter[status]"    label="Changed" multiple="true" emptyLabel="All"/>
    <et2-select-account id="col_filter[owner]"     label="User"    emptyLabel="All"/>
    <et2-date-range     id="col_filter[user_ts]"   label="Date"/>
  </et2-vbox>
</template>
```

Headers are plain labels — no sort affordance, no in-header filters. The existing `columns` attribute
maps to datagrid column `hidden` flags, so it keeps working, and users additionally get the
datagrid's column chooser and column resize for free.

### The polymorphic value cell

`Et2HistorylogValue` (`et2-historylog-value`) is the one genuinely new piece of machinery. The
datagrid renders a *fixed* row template; the history log needs a *different widget per row* depending
on `status`. Rather than add a per-row widget-swap hook to the shared row renderer, one fixed tag per
value cell owns the variation internally:

- Receives the whole row via `id="$row"` (the binding `et2-customfields-list` already uses — see
  `rowTemplateFieldMap` and `isRowObjectBinding` in `Et2DatagridRowRenderer.applyRowElementAttributes()`)
  plus `field="new_value" | "old_value"`.
- Resolves a **render spec** `{tagName, attrs}` for `row.status` from a per-historylog registry,
  lazily instantiates one prototype per status, and clones per row.
- Handles the four special statuses the legacy widget bolts on: `~link~`, `~file~`
  (`et2-vfs-path`), `user_agent_action`, and `#customfield` — the last delegated to
  `mapCustomfieldToWidget(..., {context: "row", readonly: true})`.
- Multi-part statuses (calendar `participants` → `[select-account, status, role]`) render as a
  stacked `et2-vbox`, as today.
- Keeps a fallback for a `status-widgets` entry naming a widget that is not a web component, with a
  console warning, as the legacy widget does. Not every app is converted.

`Et2HistorylogUser` (`et2-historylog-user`) is a ~40-line sibling: `et2-select-account_ro` normally,
plain text when `share_email` is set.

### `Et2Historylog`

`api/js/etemplate/Et2Historylog/Et2Historylog.ts`, tag `et2-historylog`, `Et2Widget(LitElement)`,
`implements NextmatchInterface`. Deliberately **not** `et2_IInput`.

- Properties: `value` (`{app, id, status-widgets, num_rows, rows, total, …}` — the content contract is
  unchanged), `statusId` (was the `status_id` legacy option), `columns`, `lazy` (default `true`).
- Builds the status render-spec registry once from `value['status-widgets']` + customfield
  definitions + the special statuses, and pushes the matching labels into the `status` column's
  select options, as today.
- `activeFilters` / `applyFilters()` / `sortBy()` / `resetSort()` / `getWidgetById()` /
  `getDOMNode()` / `getChildren()` — the `NextmatchInterface` contract, so `Et2Filterbox` drives it
  unchanged.
- `applyFilters()` must dispatch the `et2-filter` event; `Et2Filterbox.handleNextmatchFilter()`
  listens on `getDOMNode()` to sync its widgets back.
- Needs a `filtersDrawer` getter — see [Filtering](#filtering).
- Seeds the grid from server-sent `value.rows` via `setInitialRows()` instead of a first fetch, as
  today.
- `_createNamespace()` returns `true`, preserving `content['history']`.

## Keyboard, not selection

`selectionMode = "none"` already separates the two cleanly, so this needs no new code:

| Gated off by `"none"` | Still works |
|---|---|
| `updateSelectionFromPointer()` early-returns (`Et2DatagridSelectionController.ts:307`) — clicks never select | `moveActiveRow()` (`:379`) is **not** gated — arrows/Home/End move the active-row cursor and scroll it into view |
| `toggleSelectionOnActiveRow()` early-returns (`:270`) — Space does nothing | `.dg-row-active`'s inset box-shadow renders, giving a visible keyboard cursor |
| Ctrl+A gated to `"multiple"` | `focusRowByIndex()` — focus follows the cursor, for a11y |
| `selectSingleRow()` early-returns | |
| `aria-multiselectable="false"`; the `[aria-selected="true"]` background never applies | |

Two settings go with it:

- **`auto-activate-first-row="false"`** — the datagrid defaults it to `true` (`Et2Datagrid.ts:583`),
  which would put a cursor outline on row 1 as soon as the tab opens. Off means the cursor appears
  only once a key is pressed.
- **Nothing to do about a checkbox column.** `_effectiveMetaColumnWidth()` (`Et2Datagrid.ts:4577`)
  returns `"0px"` when there is no `expansionConfig` and no explicit `--meta-column-width`. The
  history log has neither, so the leading meta column collapses to zero on its own.

No selection events fire, so `Et2Historylog` needs no `et2-selection-changed` listener.

## Diff rows

The height cap and pop-out are **`Et2Diff`'s own features**, not the history log's:
`Et2Diff.styles.ts` caps `.form-control-input` at `max-height: 9em; overflow: hidden`, reveals an
`arrows-fullscreen` `et2-button-icon` on hover, and the `open` attribute swaps the inline render for
an `et2-dialog`. The click that toggles it comes from `Et2Widget`'s base `connectedCallback` wiring
`click` → `_handleClick` (`Et2Widget.ts:224`), which `Et2Diff._handleClick` overrides.

Keeping it meant instantiating `<et2-diff>` without `noDialog` — which was expected to cost nothing,
and did not: see [What a diff needed](#what-a-diff-needed). The click wiring itself is in fact *more*
reliable than today, since the legacy widget clones `getDetachedNodes()` and calls
`setDetachedAttributes()`, whereas the new path has a real connected `Et2Widget`, which is what that
wiring depends on.

**This retires the biggest risk in the original plan.** Row-height variance destabilizing the
virtualizer's pitch settling (`MAX_SETTLE_PASSES`) was the main thing needing proof; with diffs
capped at 9em, row heights stay in a narrow band and mixed pitch does not arise.

`grid-column: span 2` for the diff cell stays, but demoted from structural to cosmetic — unified-diff
text reads badly in a half-width column. This is *cleaner* than the legacy approach: datagrid rows
are CSS grid (`tbody > tr { display: grid; grid-template-columns: … }`,
`Et2Datagrid.styles.ts:236`), so the jQuery `colspan` plus hand-computed pixel width disappears
entirely. The `old_value` cell sets `display: none`, removing it from grid flow so the spanning cell
takes both tracks.

## Filtering

Filters live **only** in a drawer, echoing `Et2AppBox`'s filter UI rather than inventing one.
`Et2AppBox` is already the etemplate-side counterpart of `EgwFrameworkApp`'s, so the pattern to
mirror is:

- **Button** — `_filterButtonTemplate()` (`Et2AppBox.ts:430`): `<et2-button-icon nosubmit>` whose
  `name` flips between `filter-circle` and `filter-circle-fill` depending on whether anything is set,
  with a `"Filters: N"` tooltip. `filterInfo()` (`:196`) has the emptiness test written already,
  including a `delete values.sort` we no longer need.
- **Drawer** — `_filterTemplate()` (`:459`): `<sl-drawer part="filter"
  exportparts="panel:filter__panel" contained>` with the filterbox inside. **`contained` is the key
  attribute** — it scopes the drawer to its positioned ancestor instead of the viewport, which is
  what makes this work inside a dialog tab rather than sliding over the whole window.
- **Re-render on change** — `handleFilterChange()` (`:397`) exists because the filterbox's value is
  neither a reactive property nor reachable by Lit, so the button icon would otherwise go stale after
  clearing a filter; its comment records that being found live. Copy it, including the
  `event.target !== this.filters` guard that stops every input's bubbling `change` from triggering it.

A drawer is a panel, so `Et2Filterbox`'s vertical sidebar layout (`flex-direction: column`,
`--label-width: min(20rem, 30%)`) is correct as-is rather than something to restyle.

`Et2Historylog` owns the filterbox itself — there is no `egw-app` shell in an edit dialog — rendering
`<et2-filterbox>` in its own shadow DOM with `._parent=${this}`, `.nextmatch=${this}`, `autoapply`,
`clearable`, and `.filterTemplate="api.historylog.filters"`.

### Filter set and wire format

| Filter | Wire | Server |
|---|---|---|
| Changed field | `col_filter[status]` (multi) | `history_status IN (…)`, must survive the existing private-CF `NOT LIKE '#%'` guard |
| User | `col_filter[owner]` | `history_owner` |
| Date range | `col_filter[user_ts]` | → `history_timestamp BETWEEN`, converted **user → server time**; maps to `fs_modified` on the attachments leg |
| Search | `search` | `LIKE` over `history_new_value` / `history_old_value` |

### Attachments and the `~file~` status

Attachments come from the `egw_sqlfs` `UNION` leg, whose columns do not exist on the history table,
so a field or user filter cannot be applied to them. They are **not** given a filter value of their
own. The Changed-field filter simply offers the **status column's own option list**, which the widget
already builds and which already contains `~file~` (labelled "File"), `~link~`, `user_agent_action`
and every `#customfield` — see `createWidgets()` in the legacy widget, which pushes exactly these.

Server semantics follow from that:

- `col_filter[status]` empty → both legs, as now.
- `col_filter[status]` set **and containing `~file~`** → include the attachments leg.
- `col_filter[status]` set and **not** containing `~file~` → drop the attachments leg.

So "show me only attachments" and "show me only status changes" both work, and neither needs a
concept the data model does not already have. `~file~` is the value the rows genuinely carry; a
synthetic "Attachment" option would have been a second name for the same thing, and would have made
the filter list diverge from the display column's.

This also shrinks integration risk 1 below: the filter's option list is *identical* to the display
column's, so the server mirrors one list to a second path rather than constructing a different one.

Search is cheap despite the `LIKE`: every history query is already scoped
`WHERE history_record_id = X AND history_appname = Y`, so it never scans more than one entry's
history rows.

`Et2Filterbox.get value` collects through `getInstanceManager().getValues()`, which nests
`col_filter[...]` ids into `{col_filter: {…}}` correctly.

### Known integration risks

Both to settle in phase 6, both failure modes that are silent rather than loud.

1. **Status options path.** `HistoryLog::beforeSendToClient()` namespaces `sel_options` under the
   widget's form name, so the display column reads `sel_options['history']['status']`. A filter
   widget with id `col_filter[status]` looks for `sel_options['history']['col_filter']['status']` and
   finds nothing. The server must mirror the label list — including the `~link~` / `~file~` /
   `user_agent_action` / `#cf` additions — to that path.
2. **Namespace unwrapping.** `Et2Filterbox.get value` unwraps its own namespace with
   `this.getPath().toReversed()`. The history log namespaces content under `history`. If that does
   not line up, `value` comes back `{}` with no error. The filterbox is also the *only* filter UI, so
   a no-template guard is needed or a load failure leaves no way to filter.

## Returning no values

Client side this is mostly what *not* to do: `Et2Historylog` extends `Et2Widget(LitElement)` and does
not `implements et2_IInput` — unlike `Et2Nextmatch`, which does. No input mixin, no `getValue()`, so
it never appears in a submit.

Server side is more interesting, because `HistoryLog::validate()` has two callers:

1. A real form submit, walking the template — where it must contribute nothing.
2. `Nextmatch::ajax_get_rows()`, which deliberately runs `validate()` as its filter sanitizer and
   then *replaces* the client's filters with the result: `$filters = $valid_filters[$form_name];`
   (`Nextmatch.php:508`).

The allowlist resolves both without needing to know which caller it is: **build the allowlisted
subset, and write `$validated` only if it is non-empty.** On a submit the client sends no filter keys
(the widget is not an input), so the subset is empty and nothing is written. On `ajax_get_rows` it is
the filters. Deny-by-default falls out for free, and no static flag or extra method is needed.

That also fixes a current side effect: `if (true) $valid = $value;` writes a `null` entry into every
submitting app's validated content when nothing was sent.

**The allowlist is `col_filter` (keys restricted to `status`, `owner`, `user_ts`, `#cf*`) and
`search`. Nothing else** — in particular not `order`/`sort` (locked), and not `start`/`num_rows`,
which never travel as filter keys because `ajax_get_rows()` sets both from `$queriedRange` *after*
the merge (`Nextmatch.php:543`).

## Migration

`api/etemplate.php` rewrites legacy tags to `et2-` prefixed ones as the `.xet` is served to the
client, via `ADD_ET2_PREFIX_LEGACY_REGEXP` (`api/etemplate.php:18`). Adding `historylog` to that list
converts **every `<historylog>` everywhere — including EPL and custom installs — with zero template
edits.**

One thing this splits in two: the rewriting only touches the client copy. `Template::read()` parses
the raw file with `XMLReader->open(rel2path($path))` (`Template.php:115`), bypassing
`api/etemplate.php` entirely, so server-side the tag stays `<historylog>` unless a file is
hand-edited. Therefore:

- `HistoryLog.php` must `registerWidget` for **both** `historylog` and `et2-historylog` — the same
  dual registration `Textbox.php` already carries (`['et2-textarea', 'et2-textbox', 'textbox',
  'text', …]`).
- `Nextmatch::ajax_get_rows()` must try both types. Its current line is
  `getElementById($form_name, strpos($form_name, 'history') === 0 ? 'historylog' : 'et2-nextmatch')`
  (`Nextmatch.php:498`) — a heuristic on the *id* that already forces every history log to be called
  `history`. Replace the guess with "try both types" rather than extend the guess.

**Infolog is the app to convert first** (phase 7a). It has the richest `status-widgets` map (15
entries including multi-part PM duration fields), a `De` description field that reliably triggers the
diff path, and `infolog/js/app.ts:158` already mutates `history['status-widgets']` at runtime — so
the awkward parts get exercised first rather than last.

## Phases

| # | Phase | Deliverable |
|---|---|---|
| 1 | Server filtering + hardening | `History::get_rows()` `col_filter`/`search`/`UNION`-leg handling; `HistoryLog::validate()` allowlist; coverage in `api/tests/Storage/HistoryTest.php`. Independently shippable, legacy widget unaffected. |
| 2 | Provider host widening | Extract `NextmatchDataProviderHost`; nextmatch suite green before and after. |
| 3 | Widget skeleton **+ height spike** | `Et2Historylog` + `historylog.xet` + `Et2HistorylogUser`, rendering timestamp/user/status only. `et2-historylog` registered client- and server-side; `ajax_get_rows()` learns the new tag. **Ends with a live check that the grid virtualizes inside a tab panel** — see [The tab-panel height problem](#the-tab-panel-height-problem). Do not start phase 4 until that is answered. |
| 4 | The value cell | `Et2HistorylogValue`: render-spec registry, all status types, custom fields, multi-part, `~link~`/`~file~`/`user_agent_action`, non-web-component fallback. |
| 5 | Diff rows | `et2-diff` + grid-span. |
| 6 | Filters | Filter template, drawer + button, `applyFilters()`, `filtersDrawer` getter, `et2-filter` dispatch, lang entries. |
| 7a | One app live | Hand-edit infolog's `edit.xet` to `<et2-historylog>`; browser-verify. |
| 7b | All apps | Add `historylog` to `ADD_ET2_PREFIX_LEGACY_REGEXP`; revert the 7a hand-edit. |
| 8 | Docs + cleanup | `Et2Historylog.md`; delete `et2_widget_historylog.ts` in its own commit, once 7b has run live. |

Phases 1 and 2 are independent of each other and of everything else, and can land first.

## Testing

- **Unit** (`api/js/etemplate/Et2Historylog/test/`, mirroring `Et2Nextmatch/test/`): render-spec
  resolution per status type, diff detection and span, `statusId` remap, filter→`applyFilters`
  plumbing, initial-rows seeding, drawer open/close incl. Escape.
  - **`share_email` is covered here and only here.** There are no `share_email` rows anywhere in the
    database, so `Et2HistorylogUser`'s owner-vs-share swap has no live verification — the branch is
    ported faithfully from the legacy widget and pinned by unit test. Stated so nobody later reads
    the live-test list and assumes it was checked in a browser.
- **PHP**: extend `api/tests/Storage/HistoryTest.php` (filters, search, `UNION` leg, date-range
  timezone) and `api/tests/Etemplate/Widget/NextmatchTest.php` (new tag resolves; `validate()`
  rejects injected keys; `validate()` writes nothing on a submit).
- **Live** — required, not optional; tsc and unit tests are not verification. Infolog first (phase
  7a), then addressbook, calendar (recurring event, multi-part participants), timesheet, resources:
  history tab lazy-load, scroll paging, a long-description diff row and its pop-out, a custom-field
  change, an attachment row, each of the four filters, the filter-button icon state, keyboard
  navigation with nothing selected.

## The tab-panel height problem

**`Et2Historylog` would be the first datagrid-based list inside a tab panel anywhere in the
codebase.** No `.xet` file contains both a nextmatch and a tabbox/tab-panel, so there is no precedent
to copy.

This matters because `Et2Datagrid`'s `.dg-root` is `height: 100%` and needs a bounded ancestor to
virtualize against. The legacy widget compensated with a manual `_resize()` that read the tab's
computed width and height — precisely the code this conversion exists to delete. `autoHeight` is the
escape hatch, but it drops the grid's own scroll body, so a 131-row entry (infolog 1284) would render
as one very long tab.

Resolve this **at the end of phase 3**, with the skeleton widget and real data, before four phases of
work are stacked on top of the answer.

## Test fixtures

Read-only survey of the dev database, recorded so the live passes do not have to hunt for data.

| App | Rows | Entries | Diff rows | CF rows |
|---|---|---|---|---|
| calendar | 416,782 | 180,288 | 586 | 6,222 |
| infolog | 18,308 | 10,110 | 47 | 174 |
| addressbook | 8,681 | 3,464 | 25 | 120 |
| timesheet | 2,703 | 1,610 | — | 6 |
| resources | 14 | 7 | — | — |

Best single entries, all infolog (the phase-7a app):

- **infolog 1284** — 131 rows, 3 diffs, 2 custom fields. All-rounder, and long enough to exercise
  paging and the height question.
- **infolog 178** — 94 rows, 2 diffs, 3 CFs.
- **infolog 1653** — 62 rows, **18 CFs**. The custom-field workhorse.

`/apps/infolog/<id>` attachment directories exist, so the `UNION` leg has live data.

**`share_email` has zero rows table-wide**, in any app — hence the unit-test-only decision.

Unrelated find from the same survey, spun off rather than fixed here: 13,905 rows carry
`history_appname = '!file'`, `history_record_id = 0`, `history_status = '~link~'` and an empty value.
Nothing displays them (no widget ever requests that appname), but something is writing them — it
looks like a negated `only_app` filter string reaching a field that wants a real app name.

## Risks

1. **`Et2NextmatchDataProvider` widening** touches a file every nextmatch depends on. Isolated
   commit, nextmatch suite before and after.
2. **Virtualized row reuse vs. `Et2Diff`'s lightDOM.** `Et2Diff` renders diff2html output into its
   own *light* DOM in `updated()`, guarded by `this.value && this.childElementCount == 0`. The
   datagrid reuses row elements while scrolling and the row-commit path does an `outerHTML`
   round-trip. If a serialized-and-restored diff comes back with children but a stale value, that
   guard skips the re-render and shows the previous row's diff. Cheap to test, ugly if missed.
   Phase 5.
3. **Calendar's dead `filter` SQL fragment** needs a decision once confirmed dead: drop it, or
   re-implement recurrence scoping as a proper `col_filter`. It is the only per-app history filtering
   anyone has tried to write.
4. **`status-widgets` can name anything** an app puts in it. Keep the legacy fallback rather than
   assume every app is converted.

---

## Implementation status

All phases are written, unit-tested and verified live. What is *not* done is listed under the
table.

| | |
|---|---|
| Phase 1 | `History::get_rows()` takes `col_filter`/`search`, gates the attachments `UNION` leg on `~file~`, converts the date range user->server time; `HistoryLog::validate()` is an allow-list that also re-derives `record_id`/`appname` server-side. 36 tests in `api/tests/Storage/HistoryTest.php` (13 new, 7 of which fail if the change is reverted - checked), 9 in `NextmatchTest.php` (4 new). |
| Phase 2 | `NextmatchDataProviderHost` extracted; `Et2NextmatchDataProvider` widened to it. Nextmatch suite green (289 tests, both browsers), plus `Et2HistorylogPaging.test.ts` pinning that the provider pages correctly for a host that is not an `Et2Nextmatch`. |
| Phases 3-6 | `Et2Historylog` + `Et2HistorylogValue` / `Et2HistorylogStatus` + `Et2HistorylogWidgetRegistry`, `historylog.rows.xet` / `historylog.filters.xet`, filter drawer. 52 unit tests across 5 files, both browsers. No new TS errors. (A separate `Et2HistorylogUser` cell was written and then dropped - the User column is two ordinary widgets in an `et2-hbox`.) |
| Phase 7a | infolog converted by hand, live-verified, then **reverted** - 7b covers it. |
| Phase 7b | `historylog` added to `ADD_ET2_PREFIX_LEGACY_REGEXP` in `api/etemplate.php`. **Every app converts with zero template edits**, confirmed by fetching all five `edit.xet` files: each now serves `<et2-historylog>`. Calendar's legacy `options="history_status"` maps through the existing legacy-options table to `statusId`. |
| Phase 8 | `Et2Historylog.md`; `et2_widget_historylog.ts` deleted in its own commit, along with its two remaining references. |
| Post-phase | The two diff problems below, plus gating the pop-out on the diff actually being cut off. 5 more tests in `Et2HistorylogDiff.test.ts`, 4 in `Et2Diff.test.ts`. |

**Knowingly not done**, each with its reasoning where it comes up below:

- **Calendar, single occurrence.** The revived `filter` fragment still hides series-wide participant
  changes rather than showing them alongside the occurrence's own; widening the match is a
  calendar-semantics decision. That view is also not reachable by URL, so the fragment's behaviour
  there rests on `testGetRowsAppliesServerSideFilterFragment` rather than a browser check, and
  `calendar_uiforms::setup_history()` has no test harness at all.
- **`share_email` against a real share.** No database seen has one, and only the share-by-mail flow
  records one. Verified by patching a loaded row in the client-side store instead.
- **`Et2Diff`'s dialog in any other virtualized grid.** Fixed for the history log by hosting the
  dialog outside the virtualizer; `Et2Diff` itself still opens a dialog that would collapse the
  same way anywhere else inside one.
- **An unresolvable `owner`.** An account that no longer exists, or `0` - which attachment rows
  inherit from a directory created by a system process - leaves the User cell empty, because
  `Link::title()` returns `''` for a falsy id before `Accounts::title()`'s `#0` fallback can run.
  The legacy widget did the same, so this is not a regression and was left alone.

### Verified live in every app (2026-09-18)

| App | Entry | Result |
|---|---|---|
| infolog | 1284 / 1653 | 137 and 63 rows; all columns, filters, diffs |
| addressbook | 2 | 3771 rows, 94 status labels |
| calendar | 6347 | 293 rows, `statusId="history_status"` honoured, `participants` multi-part value renders as a 3-part box |
| timesheet | 101 | 22 rows |
| resources | 4 | 1 row |

Rendered row content (hydration driven manually, see below): date `2024-08-02 13:50`, user
`[nathan] Gray, Nathan`, changed `Link` / `number`, values `1432567.690` -> `1234567.690`.

### Verified live (infolog 1284, 137 history rows)

- **The tab-panel height question is settled: it works.** The panel gives a bounded height (336px
  host, 283px grid body) and the grid virtualizes and scrolls inside it. The manual `_resize()` the
  legacy widget needed is genuinely unnecessary. This was the plan's #1 risk.
- All five columns render: date, user, the changed-field label, and both
  values. Attachments render as VFS breadcrumbs; `~link~` and infolog's own field labels resolve.
- Paging: `queueChunk(50)` -> `fetchPage(start=50)` -> 50 rows, 0 missing, `rows.length` 100 of 137.
- Every filter, end to end against the real server:
  - `status=['~file~']` -> 137 down to 10, all attachments (the `UNION` leg correctly *kept*).
  - `status=['Fr']` -> 28 rows, no attachments (the leg correctly *dropped*).
  - `search='adgasdg'` -> 1 row, the right one. `search='***diff***'` -> 3 rows, which also shows the
    search matches `old_value` and that the LIKE-wildcard escaping leaves `*` alone.
  - Clearing restores all 137.
  - Driven from the drawer's own widget (not just `applyFilters()`): setting the status filter and
    firing `change` takes the grid to the 10 attachment rows via autoapply.
- The filter drawer opens, builds its filterbox, loads its template, and its status filter carries
  68 options including `~file~` - ie. the server-side `sel_options` mirroring to the
  `col_filter[status]` path works.
- Diff rows: 3 found, the new-value cell is marked `diff`/`diff-row` and builds an `et2-diff` with
  rendered content; the paired old-value cell is marked `diff-row`, builds nothing, and shows an
  empty string rather than leaking the `***diff***` marker.

### What a diff needed

The plan assumed an `et2-diff` dropped into a row would just work. Two things broke, both because
the grid puts it somewhere the widget was never designed for, and both invisible until a real diff
row was looked at.

**Its styles.** `et2-diff` renders diff2html's markup into its own *light* DOM deliberately, because
the CSS that colours it - the library's, plus EGroupware's overrides that hide the library file
header and recolour the +/- lines - ships in the page's theme stylesheet (grunt concatenates
`diff2html.min.css` and `etemplate2.css` into `<theme>.min.css`). A document stylesheet never
crosses a shadow boundary, and the history log puts diffs behind two: `et2-historylog-value`'s, and
its own. The diff rendered as unstyled text with the library's "diff CHANGED" header showing - the
rule that hides it being one of the ones that could not reach. `Et2Historylog.diff.styles.ts` lifts
those rules out of whichever document stylesheet already carries them and hands them over as one
adoptable sheet, so there is no second copy of the library CSS to drift and a theme that restyles
diffs restyles these too.

**Its dialog.** `sl-dialog`'s base is `position: fixed`, which resolves against the nearest ancestor
establishing a containing block rather than against the viewport - and the virtualizer gives every
`<tr>` a transform and `<tbody>` `contain: layout`, each of which establishes one. Measured: the
base came out `1884x34`, the row's own size, so the panel collapsed to ~3px and its title, body and
OK button spilled across the grid. The cell now catches the click in the capture phase and hands the
diff to `Et2Historylog.showDiff()`, which opens the dialog in its own shadow root, above the
virtualizer. Measured after: base `1920x937`, panel `840x238` centred, `max-height: 80vh`.
`Et2Diff` itself is untouched by this - the same bug will bite any diff in a virtualized grid, which
is a separate decision.

**And a change to `Et2Diff` that is not about the grid:** the pop-out was offered on hover whether
or not anything was cut off. It now tracks `scrollHeight` vs `clientHeight` against its own cap in
an `overflowing` attribute, and both the hover button and the click that opens the dialog turn on
it. A diff that already fits is not clickable at all.

Testing this needed the `Diff2HtmlStub` used by web-test-runner to stop being a no-op - real
diff2html cannot import in the browser test context (it pulls in a Node-only `hogan`), and a stub
returning `''` made the height assertions pass against zero. It now emits one block element per
changed line: enough to have a height, explicitly not diff2html's markup, and still rendering
nothing for a value with no changed lines so the existing empty-display test keeps its meaning.

### The server-side `filter` fragment

`$content[<widget id>]['filter']` is raw SQL an app adds server-side. Calendar is the only user:
viewing one occurrence of a recurring event, it restricts `participants*` rows to those whose value
ends with that recurrence while leaving every other field's history alone. `get_rows()` never read
the key, so that scoping had silently never worked.

It is honoured now, and the trust boundary is what makes that safe:

- It can only arrive from `$content[<widget id>]`, which the app itself wrote.
- `HistoryLog::validate()` deliberately keeps `filter` out of its allow-list, so a client cannot
  supply one, and `Nextmatch::ajax_get_rows()` merges the client's filters *over* the content
  without ever introducing the key.
- It joins the history leg's `WHERE` only, so the attachments `UNION` leg (whose columns it does not
  name) is unaffected.

Non-string and empty entries are skipped rather than concatenated into the SQL. Both halves are
tested: `testGetRowsAppliesServerSideFilterFragment` (a server fragment matching calendar's real
shape filters correctly) and `testHistoryValidateNeverAcceptsAClientSqlFilter` (a client-supplied
one never reaches `get_rows()`).

**Making it work exposed a bug in calendar's fragment.** On event 6347 the history went from 293
rows to 103 - exactly the 190 `participants*` rows disappeared. The guard was
`if($content['recur_type'] || $content['recurrence'])`, so it fired for a *series master*, where
`$content['recurrence']` is empty: the fragment interpolated to `LIKE '%~|~'` and matched nothing,
hiding the series' entire participant history. Fixed by scoping the guard to an actual single
occurrence (`if(!empty($content['recurrence']))`), which restores what users see today for a series
and applies the filter only where "only this one" means something.

`calendar_uiforms::modify_history()` also calls `setup_history()`, with an empty array, so it sets
no filter under either guard - unaffected.

**Known gap, deliberately deferred:** a participant value ending `~|~0` means "applies to the whole
series", so a single-occurrence view still hides series-wide participant changes rather than showing
them alongside that occurrence's own. Widening the match is a calendar-semantics decision and was
left for later; the reasoning is in a comment at the fragment.

**Verified live (series):** event 6347 is back to **293 rows** with participant history present, and
the widget's content carries no `filter` key at all - ie. the guard now correctly declines to filter
a series. That is identical to the behaviour before the conversion, so there is no regression.

**Not verified live (single occurrence):** the single-occurrence edit is not reachable by URL -
`cal_id=<id>:<recur_date>`, `&date=`, and `&recur_date=` all open "Edit series". It is entered from
the calendar view via "Edit this occurrence", which sets `edit_single`. So the filter's behaviour
there rests on `testGetRowsAppliesServerSideFilterFragment` (a fragment of calendar's exact shape
filters correctly) rather than on a browser check. Given the known gap above, that view is expected
to hide series-wide participant changes until the match is widened.

The calendar change itself has no automated coverage - there is no test harness around
`calendar_uiforms::setup_history()`.

### A missing `app` in the history log's content

Apps are expected to set it (`Api\Storage\Tracking` documents the
`$content['history'] = ['id' => ..., 'app' => ...]` shape) and every in-tree caller does, but
nothing enforces it - and `HistoryLog::validate()` now takes the value from the server's own content,
so a caller that omits it produces null rather than whatever the client happened to send.

Measured rather than assumed: `History::get_rows()` with a null or empty appname returns **0 rows**,
so there is no leak across apps and no fatal - the history tab just renders empty with nothing to
explain why. Two changes came out of checking:

- `HistoryLog::validate()` falls back to `$GLOBALS['egw_info']['flags']['currentapp']`, which is what
  `History::__construct()` already does for the same reason and is the app whose dialog the history
  log sits in. The client's own `appname` is still never honoured.
- `get_rows()` initialises `$cfs = array()` before the `if($filter['history_appname'])` branch. The
  row loop reads `$cfs` far below, and it was only ever assigned inside that branch - unreachable
  today only because an empty appname happens to return no rows, which is not a property worth
  depending on.

Both are pinned by tests (`testGetRowsWithoutAppname`,
`testGetRowsWithoutAppnameRaisesNoWarning`, `testHistoryValidateFallsBackToCurrentAppWithoutAppInContent`).
The fallback test caught an `isset()`/null bug in the first attempt at the guard: `isset()` is false
for null, which is precisely the case it existed to catch, so it never fired.

### Verifying `share_email` without a share

No database seen so far has a single `share_email` row, and the obvious UI route does not produce
one: the share dialog collects no recipients and `Sharing::ajax_create()` passes an empty recipient
list, so `share_with` stays empty. Only the *share-by-mail* flow sets it, from the mail's To
addresses - which means actually sending mail to create test data.

What the conversion changed is the row's conditional rendering, not the server's recording
(`History::get_share_with()` is untouched). So it was verified by patching one already-loaded row in
the client-side store (`egw.dataStoreUID(uid, {...row, share_email: "..."}, true)`) and re-running
row hydration - no database write, no mail, and it exercises the real template, the real resolver
and the real widgets:

- share row: account hidden, address shown (`shared-with@example.org`)
- normal rows: address hidden, account shown (`[nathan] Gray, Nathan`)

Two things bit on the way there, both silent:

- **A `<row>`'s direct children ARE the cells.** Putting the two widgets in the row as siblings made
  a sixth cell in a five-column grid, and `_buildRowElement()` drops cells beyond `columns.length` -
  so the address widget simply never existed, while the account's `hidden` worked perfectly and made
  it look half-right. They need a box.
- **`$row_cont[field]` and `$[field]` are the same expression** to Et2Datagrid's boolean row
  resolver - `_canonicalRowExpression()` rewrites the former into the latter. An earlier comment
  here claimed `$row_cont` was ignored; only a *bare* `$row_cont`, with no field, is. The test added
  for this caught that false claim.

### Retracted: the two "bugs" reported earlier were a test-harness artifact

An earlier pass reported paging leaving blank rows and the filter drawer wedging the renderer. Both
were wrong, and they had a single cause: **`claude-in-chrome` drives a tab whose
`document.visibilityState` is `"hidden"`, and a hidden tab never runs `requestAnimationFrame`.**
Measured directly: `document.hidden === true` and a rAF ticker installed at page load had fired
**zero** times.

That one fact produces both symptoms, because both depend on rAF:

- `@lit-labs/virtualizer` updates its render range from rAF, so scrolling never asked for the next
  page - no request was ever queued. Driving `loadRowRange(50, 80)` (which does not need rAF) fetched
  and materialized the page immediately. Row *hydration* is rAF-driven too
  (`Et2DatagridRowRenderer._processRowUpgradeQueue`), which is the other half of why those rows
  looked blank.
- No rAF means no frames, so `Page.captureScreenshot` times out. That is the whole "renderer wedged"
  symptom - JavaScript stayed responsive throughout because only painting had stopped. A hypothesis
  that Shoelace's modal focus trap was fighting the virtualizer's row mutations was also checked and
  is **wrong**: `sl-drawer` explicitly skips `modal.activate()` and `lockBodyScrolling()` when
  `contained` is set.

One more thing this cost: the same rAF/ResizeObserver machinery makes a test that connects a real
`et2-datagrid` fail at random (about one run in three) with *"ResizeObserver loop completed with
undelivered notifications"* - benign browser noise that web-test-runner counts as an uncaught error.
`Et2Datagrid.test.ts` already suppresses it inline; `test/resizeObserverNoise.ts` is that same
suppression factored out for these tests, wired into `Et2Historylog.test.ts`'s `before`/`after`.

**Lesson for anyone verifying a virtualized list this way:** screenshots and scroll-driven paging
cannot be assessed in a hidden tab. Assert on state through `javascript_tool`, and use APIs that do
not depend on rAF (`loadRowRange()`, `applyFilters()`, constructing a cell and awaiting
`updateComplete`). The one thing still *not* visually confirmed is the drawer's appearance, because
this harness cannot paint it.

### Gotchas found live, all fixed

- **A namespace-creating web component does not get its own `value`.** `_createNamespace()` only
  opens the *children's* content perspective; nothing assigns the widget's own value, and once that
  perspective is open the widget's content entry is no longer reachable under its id. It has to be
  read in `transformAttributes()`, which runs before namespace creation - the same place
  `Et2Nextmatch` reads its `settings` from `attrs.id`. Without this the widget silently renders
  nothing, because `value.id` is missing and it correctly concludes there is no record to show.
- **A row widget with no row-bound attributes never gets `transformAttributes()`**, because
  `Et2DatagridRowRenderer.applyRowElementAttributes()` skips any element whose stored attribute set
  is empty - and a select resolves its `sel_options` inside `transformAttributes()`. So
  `<et2-select readonly>` in a row template renders blank unless something unrelated happens to give
  it a row attribute. That is why the "Changed" column is its own `et2-historylog-status` cell
  reading the label off the registry. (It briefly appeared to work only because an unrelated
  attribute was being set on the same element.)
- **Apps put history status labels at the *root* `sel_options['status']`**, and the client's array
  manager finds them by falling back to the root. Writing a namespaced list therefore *shadows*
  them rather than merging - `addStatusOptions()` seeds from the root list first.
- **`Et2VfsPath.value` is string-only**; a `~file~` row's value is the server's stat object, and only
  `setValue()`/`set_value()` pulls `.path` out of it. Assigning `.value` renders `[object Object]`.
  The value cell now prefers `set_value`/`setValue` over the plain property for every widget.

Also learned, and a trap for any `.xet` work: **a template name maps to a filename**, so
`api.historylog.rows` must live in `historylog.rows.xet` - two templates cannot share one file
unless every name that reaches them resolves to it. And `Et2Template`'s cache-buster is
`days-since-epoch`, so an edited `.xet` is invisible to a browser that already loaded it today;
force-refresh the exact URL (including its `?download=` value, which is *not* the same URL the
error toast shows) when verifying template changes.
