# Converting an app from `et2_extension_nextmatch` to `Et2Nextmatch`

A checklist for moving one app's list view from the legacy `nextmatch` widget
(`api/js/etemplate/et2_extension_nextmatch.ts`, tag `<nextmatch>`) to the `Et2Nextmatch` web
component (`api/js/etemplate/Et2Nextmatch/Et2Nextmatch.ts` + `Et2Datagrid.ts`, tag
`<et2-nextmatch>`). For widget usage (attributes, row bindings, styling, row expansion), see
[`Et2Nextmatch.md`](../../../api/js/etemplate/Et2Nextmatch/Et2Nextmatch.md) and
[`Et2Datagrid.md`](../../../api/js/etemplate/Et2Nextmatch/Et2Datagrid.md) — this document does not
repeat that reference material.

Apps converted so far: Addressbook, Infolog, Filemanager, Mail. Apps still on the legacy widget:
Calendar, Timesheet, Admin, Importexport, Aiassistant, Preferences, Home. Related in-flight/reference
docs in the same directory as the widget source: `ColumnSelectionNotes.md`,
`Et2DatagridDirectoryMigrationPlan.md`, `NestedExpansion.md`.

## Status

In progress. The checklist below is validated against four real conversions (see the reference
sections for the evidence each item is based on). Expect it to grow as more apps convert.

## Conversion checklist

Do **not** split this across multiple commits by layer (template-only, then JS-only, etc.) — see
[Why template + app-JS must land together](#why-template--app-js-must-land-together). Work through
these in order, in one commit, then expect follow-up fixups.

1. **Convert the `.xet` template(s).** Apply the mechanical renames in
   [Template rename patterns](#reference-template-rename-patterns): tag renames, header markup
   restructuring, row-value binding syntax fixes, `et2-styles` for row CSS. Check off each pattern
   that applies to this app's templates; don't assume a pattern doesn't apply without checking.

2. **Rewrite `app.ts`/`app.js` in the same commit.** Grep the app's JS for each of these and replace
   per [Legacy API replacement table](#reference-legacy-api-replacement-table):
   - `.controller.` (any access)
   - `.options.settings.` / `.options.onselect`
   - `.activeFilters` (direct mutation, not read)
   - `_getPreferences(`
   - `et2_extension_nextmatch_actions` (import)
   - `_get_autorefresh(` / `_set_autorefresh(`
   - `.set_onfiledrop(`
   - jQuery `.on('refresh', ...)`
   - `et2_nextmatch.DELETE` or other `et2_nextmatch.*` constants
   A zero-result grep for a pattern means that pattern doesn't apply to this app — it does not mean
   skip the step.

3. **Check the settings allow-list.** For every `$content['nm'][key]` the app's own JS reads back via
   `nm.settings.<key>`, confirm `key` is in `Et2Nextmatch.ts`'s `ALLOWED_SETTINGS`. See
   [Settings allow-list](#reference-settings-allow-list). If it's missing, either add it there (it's
   shared framework code — check with a reviewer first, per `AGENTS.md`) or read the value from
   content directly (`this.et2.getArrayMgr("content").getEntry("nm[<key>]")`).

4. **If the app needs a per-request-varying column-preference key** (e.g. different visible columns
   for two different views of the same row template), set `$content['nm']['columnselection_pref']` to
   that key server-side, same as before. `Et2Nextmatch` now forwards this to `columnPreferenceName`
   automatically (fixed in this project — see
   [Case study: `columnselection_pref` → `columnPreferenceName`](#case-study-columnselection_pref--columnpreferencename)),
   so no special handling is needed on the app side for the persistence itself. Just confirm in the
   browser (step 6) that column visibility persists correctly across a reload for each variant of the
   view.

   **If the app's own PHP also reads that same preference back** (e.g. to decide whether an expensive
   column is currently visible, the way Infolog does for `show_times`), make sure it re-derives the key
   the same way it was computed for the current request, rather than reading it back off the AJAX
   `$query` array. `Et2Nextmatch`'s row-fetch requests only resend filter-value settings (`filter`,
   `filter2`, `cat_id`, `search`, `col_filter`, `searchletter`), not the whole settings object, so
   anything else in `$query` — including `columnselection_pref` itself — can be one full-page-load
   behind the current request. This is exactly the bug fixed in Infolog's `get_rows()` (see the case
   study) and is worth checking for in any app doing something similar.

5. **Add row CSS via `<et2-styles>`** if the app doesn't already have `rows.css`/`rows.less` loaded
   this way — see `Et2Nextmatch.md` § Styling Rows for the fallback rule to `app.css`.

6. **Verify in a browser**, watching the console the whole time (`.controller`/`.options` access
   failures throw at first use, not at page load, so a quiet page load proves nothing):
   - list loads, sorts, filters, selects rows
   - bulk actions (delete, move, whatever the app has) work on both a partial selection and "select
     all"
   - any view switch the app has (row/tile/kanban/etc.) works and doesn't leave stale state
   - column visibility/order/width persists across a reload, including for every distinct
     column-preference key the app uses (see step 4)
   - push/refresh notifications update the list correctly
   - keep [Startup/lifecycle timing pitfalls](#reference-startuplifecycle-timing-pitfalls) in mind
     while doing this — several of these bugs only show up on interaction, not on load

7. **Budget for 1-3 follow-up fixup commits.** Every real conversion so far needed at least one; treat
   the first commit as "converted, pending fixups," not "done."

## Why template + app-JS must land together

Converting only the `.xet` template (and PHP, if any) is not sufficient. The app's own
`app.ts`/`app.js` almost always contains code written against the legacy `et2_nextmatch` widget's
public surface. A template-only conversion loads without error and breaks the first time the app's
own JS calls one of those legacy methods. This is exactly what forced Addressbook's first attempt
(`1353843c93`) to be reverted (`6e74ee6117`) and redone as a combined template+`app.ts` change two
days later (`cd326f9904`).

## Reference: template rename patterns

Mechanical renames seen in every conversion:

- `<nextmatch id="nm" .../>` → `<et2-nextmatch id="nm" ...></et2-nextmatch>`.
- `<nextmatch-header>` → `<et2-nextmatch-header>`, `<nextmatch-sortheader>` →
  `<et2-nextmatch-sortheader>`.
- `<nextmatch-customfields>` → `<et2-nextmatch-header-customfields>` — note the tag name itself
  changes (`-customfields` becomes `-header-customfields`), it's not just an `et2-` prefix.
- `<customfields-list>` → `<et2-customfields-list>` — rides along because this widget typically only
  appears inside nextmatch row templates.
- Legacy VFS row widgets: `<vfs id="$row"/>` → `<et2-vfs-name id="$row"/>`, `<vfs-size .../>` →
  `<et2-vfs-size .../>`, `<vfs-mode .../>` → `<et2-vfs-mode .../>` (Filemanager, Mail).
- Read-only select widgets inside rows: `<et2-select-country readonly="true">` must become
  `<et2-select-country_ro readonly="true">` — the plain widget does not render correctly read-only
  inside the new datagrid rows (Addressbook).
- `<html id="${row}[attachments]"/>` (a raw HTML-string cell) is not supported the same way. Replace
  with plain widgets (e.g. `<et2-image>`) bound to dedicated server-provided fields, rather than one
  HTML blob built server-side — this pushes icon-selection logic into row-data preparation instead of
  the template (Mail: `attachment_icon`, `flagged_icon` fields added specifically for this).
- Nested `<grid>`/`<columns>`/`<rows>` inside a `<nextmatch-header>` cell (multi-line sortable headers)
  does not carry over — replace with `<et2-vbox>`/`<et2-hbox>` wrapping
  `<et2-nextmatch-sortheader>` elements (Addressbook, Infolog).
- **Check for other files in the same app that duplicate the main row-template id.** Some apps
  (Infolog: `delete.xet`) embed their own independent copy of a template like `infolog.index.rows`
  under the exact same id, rather than referencing a shared file — see
  [Case study: `infolog.delete`'s stale duplicate row template](#case-study-infologdeletes-stale-duplicate-row-template)
  for why converting only the "main" copy silently leaves the duplicate on the legacy nested-grid
  header markup, and why the fix requires giving the duplicate its own id, not just renaming tags.
- Row `class` binding: `<row class="$row_cont[class] $row_cont[cat_id]">` can be simplified to the
  direct-binding form `<row class="$class $cat_id">` — both syntaxes work, but new/edited rows should
  use direct bindings (see `Et2Nextmatch.md` § Row value bindings).
- Row-value binding syntax matters per-widget: a `<progress>` bound with `value="${row}[percent2]"`
  (wrong field name — an easy typo to carry over) silently showed the row number instead of a
  percentage; even after fixing the field name, `${row}[percent]` still misbehaved and needed the
  direct-binding form `value="$row_cont[percent]"` to render correctly (Infolog, two attempts).
- Attributes that stop being used: `disable_selection_advance="true"` has no widget-level equivalent —
  implement the same "select the next/previous row after this one is removed" behavior in `app.ts` via
  the `et2-rows-deleted` event instead (see the replacement table below). `no_dynheight="true"` was
  also dropped without replacement in the one conversion that had it.
- Add `<et2-styles src="rows.css">` inside the row template to load row-scoped CSS into the datagrid's
  row shadow DOM; add a `rows.css`/`rows.less` file per app for this if one doesn't already exist. See
  `Et2Nextmatch.md` § Styling Rows for the fallback rules to `app.css`.
- If an app has filter/search/sort controls that must be available before the nextmatch row template
  loads (e.g. for a tile view rendered without waiting on the row template), pull them into a static
  `<et2-template id="app.index.filter">` rather than relying on them being built from the nextmatch
  header row (Filemanager, `ea58bfd53e`) — the new component does not always eagerly build filter
  markup from the row template header the way the legacy widget did.

## Reference: legacy API replacement table

Legacy `et2_nextmatch` widget API usage that has no direct equivalent and must be rewritten against
`Et2Nextmatch`'s public surface:

| Legacy pattern | Replacement |
|---|---|
| `import {fetchAll, nm_action, nm_compare_field} from "et2_extension_nextmatch_actions"` | Removed. Use `nm.executeAction(actionId, {ids, all}, {nmAction})`, `nm.fetchAllIds()`, and an inline comparison closure. |
| `nm._getPreferences()` | `nm.getValue().selectcols` (split on `,` if it comes back as a string). |
| `_action.data.nm_action = "submit"/"popup"; nm_action(_action, _senders)` | `nm.executeAction(_action.id, {ids, all: nm.getSelection().all === true}, {nmAction: "submit"/"popup"})`. |
| `selected[0].getAllSelected()` / `fetchAll(selected, nm, cb)` | `nm.getSelection().all` / `nm.fetchAllIds().then(cb)`. |
| `nm.activeFilters = {}` then apply filters | `nm.applyFilters({}, {reload: false})` then `nm.applyFilters(filters)`. |
| `nm.controller._actionManager.getActionById(...)` + manual `nm_action(...)` | `nm.executeAction(id, {ids, all}, {nmAction: "submit"})`. |
| `<et2_nextmatch>` TS type, `nm.getWidgetById(id)`, `nm.getDOMNode(nm)` + `egw.css(...)` for a class toggle | `<Et2Nextmatch>` type, `this.et2.getWidgetById(id)`, `nextmatch.style.setProperty("--custom-prop", ...)` — see `Et2Nextmatch.md` § Letting Users Show Or Hide Row Details. |
| `nm.getController()?.getTotalCount()` | `nm?.totalCount`. |
| `nm.options.settings.<key>` | `this.et2.getArrayMgr("content").getEntry("nm[<key>]")` for content set at page load, or `nm.settings.<key>` if it's one of `Et2Nextmatch`'s `ALLOWED_SETTINGS` (checklist step 3 — most legacy setting names are *not* in that list and will read as `undefined`). |
| `nm.set_onfiledrop(jQuery.proxy(cb, this))` (2-arg legacy callback) | `nm.getDOMNode().addEventListener("et2-filedrop", (e: CustomEvent) => { if (e.cancelable) e.preventDefault(); cb(e.detail?.rowUid, e.detail?.files); })`. |
| `nm.activeFilters["view"] = view` then wait on `nm.getWidgetById(template).loading` | `await nm.set_template(...)` then `nm.applyFilters({view}, {reload: false, clearActions: false})`. If the app has expandable child rows, also call the new `nm.collapseExpandedRows()` when switching views. |
| `nm.controller._selectionMgr._getRegisteredRowsEntry(r)` / `.setSelected()` / `.setFocused()` / manual scroll-into-view for "select next row after delete" | Listen for the `et2-rows-deleted` CustomEvent (`detail: {previousRowId, nextRowId}`), then call `nm.selectSingleRow(id)` / `nm.focusRowById(id)`. |
| `nm._get_autorefresh()` / `nm._set_autorefresh(0/n)` (pause auto-refresh during a long request) | **No replacement exists.** This behavior was simply dropped in the one conversion that had it (Mail) — decide per-app whether that's acceptable or needs a new API added to `Et2Nextmatch`. |
| `nm.controller._selectionMgr.resetSelection()` | `nm.clearSelection()`. |
| `nm.options.onselect = null` (temporarily suppress auto-preview-on-select) | `nm.addEventListener("et2-selection-changed", e => e.preventDefault(), {capture: true, once: true})`. |
| `et2_nextmatch.DELETE` constant | `Et2DatagridUpdateTypes.DELETE` from `Et2Datagrid.types`. |
| `this.nm.controller.getObjectManager()` | `egw_getObjectManager(appname).getObjectById(nm_index)` — grep the app for `.controller.` before considering it converted; every remaining hit is a crash waiting to happen. |
| jQuery `.on('refresh', (_event, _widget, _row_id, _type) => ...)` | `Et2Nextmatch.refresh()` dispatches a plain DOM `CustomEvent` with **no extra arguments** — `_widget`/`_row_id`/`_type` are always `undefined` now. Close over an already-captured reference instead of reading widget/row from the event. |
| Guessing at a renamed setting (e.g. `nm.settings.foldertree`) | Verify the replacement property actually exists on `Et2Nextmatch` (check `Et2Nextmatch.ts`) before using it — two separate Mail fixups each initially guessed wrong (see [Startup/lifecycle timing pitfalls](#reference-startuplifecycle-timing-pitfalls)). |

## Reference: settings allow-list

`Et2Nextmatch.settings` only keeps an explicit allow-list of keys from `$content['nm']`
(`Et2Nextmatch.ts`'s `ALLOWED_SETTINGS`); anything else sent by the app's PHP is silently dropped from
`.settings`, so legacy app code reading `nm.options.settings.<key>` for an arbitrary app-specific key
will get `undefined` after conversion even though the server sent it. In every conversion inspected so
far, the **`$content['nm']` array shape built server-side did not need to change** — the conversion was
template + app JS/TS only — but that's an observed outcome for four apps, not a guarantee; check
`ALLOWED_SETTINGS` if the app relies on a setting that isn't in that list.

## Reference: startup/lifecycle timing pitfalls

- **Columns are not synchronously available at `et2_ready()` time.** If app JS needs to know the
  current visible columns as soon as the page loads (e.g. to drive view-specific CSS), wrap it in
  `nm.whenColumnsReady()` rather than assuming `nm.getValue().selectcols` is populated by
  `et2_ready()` — and don't rely on a loading event either, since preloaded rows (`setInitialRows`)
  don't fire one (Filemanager).
- **`refresh()` went from a jQuery trigger with extra arguments to a plain `CustomEvent`.** Any handler
  bound the jQuery way silently receives `undefined` for what used to be `_widget`/`_row_id`/`_type` —
  this broke a push-notification refresh handler in Mail and wasn't caught until a dedicated fixup.
- **`.controller` went from a public property to a private `_actionController`.** Code walking
  `nm.controller.*` breaks or crashes silently. Grep for `.controller.` across the app's JS as a
  conversion-completeness check.
- **Don't guess at renamed settings.** Two separate Mail fixups each guessed a replacement for removed
  widget state (first `nm.settings.foldertree`, which also doesn't exist, then correctly
  `nm.activeFilters.selectedFolder`) — verify the property exists on `Et2Nextmatch.ts` before shipping.
- Auto-refresh pause/resume around long-running requests has no equivalent and was dropped, not fixed,
  in the one conversion that had it — call this out explicitly when converting an app that relies on
  it, rather than assuming it's covered.
- **Direct `_filters` mutation while a fetch is in flight can make `Et2Datagrid` silently discard that
  fetch's response.** `Et2Datagrid._fetchPage()` captures `dataProvider.getQuerySignature()` (a
  serialization of the live `_filters` object) at dispatch time and compares it again once the response
  arrives; a mismatch is treated as "superseded" and the response is dropped with no retry. Anything
  that mutates `_filters` directly instead of going through `applyFilters()` — e.g. `sortBy(id, asc,
  false)` — changes that signature without dispatching a new request, so if it runs while an earlier
  fetch for the same nextmatch is still in flight, that fetch's real, valid response gets thrown away
  and nothing re-fetches. This is exactly what caused "rows fetched and returned from the server, but
  never shown" in InfoLog's CRM view: `_initializeSettingsSort()` (equivalent) used to run late enough
  that `sortBy(..., false)` could land after `CRM.set_contact_ids()`'s `applyFilters()` had already
  dispatched a fetch. Fixed for that one case by moving the sort-seeding earlier so it can no longer
  overlap a dispatched fetch — but check for *other* direct-`_filters`-mutation call sites during a
  conversion or when debugging an app with a similar "empty despite a successful fetch" symptom.

## Case study: `columnselection_pref` → `columnPreferenceName`

This is closed-out history, not an open checklist item — kept for context on why checklist step 4
above is safe to treat as "just works now."

Legacy `et2_extension_nextmatch` stored column visibility as a CSV string of visible column keys under
a preference key the app chose via the `columnselection_pref` setting (falling back to the template
name). Some apps read that same preference value back themselves server-side to make decisions the
widget itself doesn't make — e.g. whether to compute an expensive derived value, or which columns to
suppress in a secondary rendering of the same data. `Et2Datagrid` stores the modern format as a
structured array (`{key, width, hidden, customFields}` per column) under a key resolved by
`columnPreferenceName` (falling back to `<owner>-<rowTemplateId>-prefs`), and `columnselection_pref`
remained a recognized `Et2Nextmatch.settings` key (used only for deriving the lettersearch preference
name) — but nothing forwarded it to `columnPreferenceName`, so apps that pass a dynamic
`columnselection_pref` per request had no way to make the new component honor it.

**Apps using `<et2-nextmatch>` checked:** Addressbook, Filemanager, Infolog, Mail (the only apps with a
top-level `<et2-nextmatch ...>` tag as of this audit — anything matching `et2-nextmatch-header*` or a
plain `<nextmatch>` tag doesn't count, and earlier greps in this session had false positives from tag
names like `et2-nextmatch-header-account` word-matching `et2-nextmatch\b`).

- **Infolog** (`infolog/inc/class.infolog_ui.inc.php`) — real bug. Sets
  `$content['nm']['columnselection_pref']` to a key that varies with the filter2 "details" toggle and
  the active row template (`'nextmatch-' . $template . ($details ? '-details' : '')`), then reads
  `$this->prefs[$columnselection_pref]` back (CSV `explode`/`strpos`) to compute `show_times` and which
  columns to suppress on the embedded "sp" sub-entry view. Since nothing propagated
  `columnselection_pref` to `columnPreferenceName`, the actual persisted key was always the
  auto-generated default (ownerPrefix + row template id, no `-details` distinction), and the
  CSV-format compatibility write in `Et2Datagrid._persistColumnPreferences()` always targeted
  `nextmatch-<rowTemplateId>` regardless of `columnPreferenceName` — so Infolog's PHP read from a key
  the client never actually wrote in the shape it expected, and lost the distinction between the
  "details" and "no details" column sets it used to keep separately.
- **Filemanager** (`filemanager_shares.inc.php`) — sets `columnselection_pref`, but only for
  `filemanager.shares` (still the legacy `<nextmatch>` tag, not converted). No effect on the converted
  `<et2-nextmatch>` index page; `class.filemanager_ui.inc.php` (the converted page's controller) does
  not use `columnselection_pref` at all.
- **Addressbook** (`class.addressbook_ui.inc.php`) — sets `columnselection_pref` (and forces
  `no_columnselection = true`) only when `isset($template)`, i.e. only for the alternate/embedded
  `?template=` entry point, which is not the default `<et2-nextmatch>` index page. The main index flow
  passes `columnselection_pref => null`. No fix needed.
- **Mail** — does not use `columnselection_pref` anywhere.

**Fix applied**, in shared framework code so every app benefits, not just Infolog:

1. `Et2Nextmatch.ts`'s `settings` setter now forwards `settings.columnselection_pref` to
   `this.columnPreferenceName` whenever the setting is present (with a one-time deprecation warning,
   since `columnPreferenceName` is the property apps should set directly going forward), so it keeps
   re-deriving the key as the app's server-side logic changes it (e.g. Infolog's details toggle) — not
   just on first load. This drives `Et2Datagrid`'s *structured* `{key,hidden,width,customFields}`
   preference, which is genuinely per-app-key-aware.
2. The legacy CSV-format compatibility write (the one "some apps look for") **stayed in
   `Et2Datagrid`'s `_persistColumnPreferences()` only briefly**, gated by a `columnPreferenceName`-aware
   key — but that was wrong and got corrected during this same session: `_columnPreferenceName()` (the
   structured-preference key) already uses `columnPreferenceName` verbatim when set, so making the
   legacy write follow the *same* property pointed both writes at the exact same preference for any app
   using it. Since the structured (array) write runs after the CSV (string) write in the same call, it
   silently clobbered the CSV string with an array — corrupting the value for exactly the apps this was
   meant to help. Fixed properly by removing the legacy write from `Et2Datagrid` entirely (it has no
   business knowing the legacy Nextmatch CSV format exists) and moving it to `Et2Nextmatch`'s
   `_persistLegacyColumnSelection()`, called from the existing `_handleDatagridColumnsChanged()` handler.
   It targets `nextmatch-<rowTemplateId>` unconditionally, **never** `columnPreferenceName` — those are
   two independent concerns that must never share a key. The reusable CSV-building logic lives in
   `Et2NextmatchColumnPreferences.ts`'s new `legacyColumnSelectionCsv()`, alongside the other
   legacy-Nextmatch-specific preference helpers already there (see
   `Et2DatagridDirectoryMigrationPlan.md`'s ownership split — this is exactly the kind of function that
   plan says belongs there, not in `Et2Datagrid`).

Because Infolog's own no-details key (`nextmatch-infolog.index.rows`) happens to be textually identical
to the row-template id, it still ends up sharing a key with the unconditional legacy write in that one
state (not in the `-details` state, which differs). The legacy CSV write and the structured array write
now come from two different components entirely, but the write order is unchanged (CSV first via the
synchronous `et2-columns-changed` event, array second from `Et2Datagrid`'s own persist call), so the
final value at that shared key is still the structured array — which is exactly why the Infolog PHP fix
below (accepting either shape) is required, not optional. This required no changes to how Infolog
*computes* `columnselection_pref`. Covered by new/updated cases in
`api/js/etemplate/Et2Nextmatch/test/Et2DatagridColumnPreferences.test.ts` (`Et2Datagrid` never writes a
CSV-format value, regardless of `columnPreferenceName`) and
`api/js/etemplate/Et2Nextmatch/test/Et2Nextmatch.filters.test.ts` (`Et2Nextmatch` persists the legacy CSV
under the row-template key, ignoring `columnPreferenceName`).

One PHP fix was still needed, found on a second pass through `class.infolog_ui.inc.php`: at the "set
old show_times pref" line (`get_rows()`), the code read
`$this->prefs[$query['columnselection_pref']]` instead of the freshly-recomputed local
`$columnselection_pref` variable computed a few lines above from the current request's `filter2`. Under
the legacy widget this may have gone unnoticed if the full settings blob was resent with every fetch;
`Et2Nextmatch`'s data provider only resends the filter-value settings (`filter`, `filter2`, `cat_id`,
`search`, `col_filter`, `searchletter`) on each row fetch, not the whole settings object, and the
server backfills anything else from the *previous* full-page-render's preserved content. That leaves
`$query['columnselection_pref']` frozen at whatever it was on the last full page load, so after a
filter2 "details" toggle — which does take effect immediately for column persistence, since that goes
through `filter2` (always resent) → the freshly-recomputed local variable — this one derived value
(`show_times`, used by `get_info()` for the cumulated timesheet time columns) kept reading the
pre-toggle key for the rest of the AJAX session, until the next full page reload. Fixed by using the
local `$columnselection_pref` variable instead of `$query['columnselection_pref']` — same fix direction
as the framework change: prefer the freshly-derived key over a value that may have gone stale in the
request/response round trip. Not covered by an automated test (no existing PHP test exercises
`infolog_ui::get_rows()`); verify by toggling the "details" filter without a full page reload and
confirming cumulated timesheet times still show/hide correctly on the next scroll/sort fetch.

**Related, already in progress:** `infolog/js/app.ts`'s `filter2_change()` has an uncommitted,
in-progress rewrite (present before this session started) that replaces the legacy
`this.nm.options.settings.columnselection_pref` / `nm.set_columns()` / `nm.dataview.getColumnMgr()`
column-reload dance with a direct `this.nm.columnPreferenceName = ...` assignment, relying on
`Et2Datagrid` reactively reloading preferences when that property changes (it does — see `willUpdate()`
in `Et2Datagrid.ts`). That code derives the new key by stripping/appending `-details` from the
`columnPreferenceName` *already on the widget*, so it depends on `columnPreferenceName` being correctly
initialized from the server's `columnselection_pref` on first load — exactly the gap the framework fix
above closes. Without that fix, the very first toggle would have derived a malformed key (missing the
`nextmatch-infolog.index.rows` prefix entirely, since `columnPreferenceName` would still be `""`). Not
authored in this session; flagged here because it's concrete evidence the framework fix was load-bearing
for already-in-flight infolog work, not just a theoretical gap.

**Not changed, flagged for later:** the `no_columnselection` setting (used by Addressbook's
`?template=` branch to disable the column-selection UI) does not currently map to anything on
`Et2Datagrid` (`noColumnPersistence`/`noVisibleHeader`) — it's a pre-existing, unrelated gap, harmless
today only because the one app setting it also doesn't rely on column persistence there.

## Case study: `infolog.delete`'s stale duplicate row template

Closed-out history, kept for context on the "duplicate row-template id" checklist item above.

`infolog/templates/default/delete.xet` — the single-entry delete-confirmation popup opened from the
edit popup's own "Delete" button when the entry has sub-entries (`infolog_ui::delete()`,
`class.infolog_ui.inc.php`) — had its own top-level `<nextmatch options="infolog.index.rows" .../>`
showing the entry's sub-entries, using the *same row-template id* as the main index page
(`infolog.index.rows`). This was never a shared reference: EGroupware's eTemplate loader resolves a
`template="app.rest"` id purely by filename convention (`app.rest` → `app/templates/default/rest.xet`),
with no cross-file search. For a two-segment id like `infolog.index.rows` that convention needs a
dedicated `infolog/templates/default/index.rows.xet` file, which infolog never had (unlike addressbook,
calendar, and timesheet, which do ship that file and so genuinely share one row template by reference).
Instead, `index.xet` and `delete.xet` each embedded their own independent copy under the same id — pure
duplication, invisible until one copy gets converted and the other doesn't.

**Converting only the tag** (`<nextmatch>` → `<et2-nextmatch>`, keeping `template="infolog.index.rows"`)
rendered the sub-entries list with literal `[object Object]` header cells. Root cause: `delete.xet`'s own
copy of `infolog.index.rows` still had the pre-conversion nested `<grid>/<columns>/<rows>` header
markup — `index.xet`'s copy had already been converted to `<et2-vbox>/<et2-hbox>` wrapping
`<et2-nextmatch-sortheader>` elements as part of the original conversion, but nothing ever touched
`delete.xet`'s copy, since PHP resolves `infolog.index.rows` by scanning whichever file is *actually
being read* for the current request (`delete.xet`, in this popup) top-to-bottom and caching every
`<template>` block it encounters by id — so `infolog_ui::delete()` always got `delete.xet`'s own, stale
copy, never `index.xet`'s.

**Fix chosen:** rename `delete.xet`'s two local copies (`infolog.index.rows-noheader` →
`infolog.delete.rows-noheader`, `infolog.index.rows` → `infolog.delete.rows`) to their own distinct ids
and update the two references inside the same file. This is safe without creating a dedicated
`index.rows.xet` file: the renamed ids don't need to match the filename convention at all, because
they're still defined earlier in the same `delete.xet` file than where they're referenced, so the
server's per-request template cache picks them up from the linear file scan before anything asks for
them — confirmed by reading `Et2Nextmatch/Widget/Template.php`'s `instance()`/`relPath()` before making
this change, not by trial and error. (The alternative — extracting a real shared
`infolog/templates/default/index.rows.xet` file, so `index.xet` and `delete.xet` reference one
definition — was also considered and rejected as more invasive than this app actually needed.)

**First attempt at the body content was wrong, not just the id.** The initial pass left
`infolog.delete.rows`'s row/column markup as `delete.xet`'s pre-existing content, only mechanically
converting its legacy tags in place (nested grid header → `et2-vbox`/`et2-hbox` wrapping
`et2-nextmatch-sortheader`, `nextmatch-sortheader`/`nextmatch-header`/`nextmatch-customfields` →
`et2-` renames). That fixed the broken headers but left the row body as a *years-older* snapshot than
`index.xet`'s: a trailing action-button column (Edit/Delete/Close) and `Sub`/`Action` header columns
`index.xet` no longer has, no `<et2-customfields-list>`/kanban-link/click-to-open-row behavior, and no
`<et2-styles src="rows.css">` — so it visually didn't match the main list at all (spotted by the user
after the "no row template configured" fix landed, not caught by the automated verification). Corrected
by deleting that hand-converted body entirely and pasting `index.xet`'s *current* `infolog.index.rows`
grid/columns/rows content verbatim, keeping only the id renamed to `infolog.delete.rows`. **Lesson:**
when a duplicate template has drifted, converting its existing legacy tags in place is not equivalent to
having the same template — check whether the "reference" copy has evolved features (new columns, new
widgets, new stylesheet links) beyond a mechanical tag rename, and prefer copying the current reference
content wholesale over patching the stale copy, unless the duplicate is deliberately meant to differ.

**Second bug found only by testing the actual delete flow in a browser**, not by reading templates: after
the rename, the popup regressed from "wrong headers" to a hard failure — `Et2Datagrid: No row template
configured`, with the client 404-ing on `.../infolog/templates/default/index.rows.xet` (a URL the *old*
id would produce, not the renamed one). `infolog_ui::delete()`'s own `$values['nm']` array never set a
`template` key at all, and `infolog_ui::get_rows()` (`class.infolog_ui.inc.php`, shared by every
infolog nextmatch instance via `get_rows: 'infolog.infolog_ui.get_rows'`) has a long-standing fallback:
if `$query['template']` isn't already set, it defaults it to the literal string `'infolog.index.rows'`
— reasonable when every infolog nextmatch really did use that one row template, silently wrong the
moment `delete.xet` stopped being one of them. That server-computed default gets sent back to the client
as the widget's `template` setting, overriding whatever the XML tag's `template=` attribute said. Fixed
by having `delete()` set `'template' => 'infolog.delete.rows'` explicitly in its own `$values['nm']`
array, the same way `index()` explicitly sets `'template' => 'infolog.index.rows'` in its own — so
`get_rows()`'s "not set" fallback never triggers. Anything that shares a `get_rows` callback with another,
already-converted nextmatch instance should be checked for this same kind of hardcoded/defaulted
`template` value, not just the XML tag's own attribute.

Verified by creating a real parent entry with one sub-entry, opening the edit popup's Delete button
(not the list's row-level Delete action, which uses a different, purely client-side confirm dialog and
never touches this template), confirming the sub-entries list renders with correct headers and the same
row layout/styling as the main index (no action-button column, `rows.css` loaded), and confirming
"Yes - Delete including sub entries" removes both rows with no console errors from the change.
