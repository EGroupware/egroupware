# Converting an app from `et2_extension_nextmatch` to `Et2Nextmatch`

A checklist for moving one app's list view from the legacy `nextmatch` widget
(`api/js/etemplate/et2_extension_nextmatch.ts`, tag `<nextmatch>`) to the `Et2Nextmatch` web
component (`api/js/etemplate/Et2Nextmatch/Et2Nextmatch.ts` + `Et2Datagrid.ts`, tag
`<et2-nextmatch>`). For widget usage (attributes, row bindings, styling, row expansion), see the
generated component docs for
[`et2-nextmatch`](https://etemplate.egroupware.org/components/et2-nextmatch/) and
[`et2-datagrid`](https://etemplate.egroupware.org/components/et2-datagrid/) — this document does not
repeat that reference material.

Apps converted so far: Addressbook, Infolog, Filemanager, Mail. Apps still on the legacy widget:
Calendar, Timesheet, Admin, Importexport, Aiassistant, Preferences, Home. Related in-flight/reference
docs in the same directory as the widget source: `ColumnSelectionNotes.md`,
`Et2DatagridDirectoryMigrationPlan.md`, `NestedExpansion.md`.

Addressbook's conversion covers every *reachable* list view the app itself owns: the main index
(including its mobile skin), the org/duplicate grouped views, the CRM popup (`CRM.ts`), and the
contact-picker popup. Two addressbook-owned templates were deliberately left on the legacy widget:

- `index.rows.xet`'s Home-favorite-portlet variant — rendered through `home_favorite_portlet.inc.php`,
  shared framework code used by ~9 other apps' portlets, so converting it is a separate, cross-app
  change (see [Why template + app-JS must land together](#why-template--app-js-must-land-together)'s
  shared-framework-code caution).
- `display.xet` (the Sitemgr "display" module, `class.addressbook_display.inc.php`) — this is a CMS
  content-block view, only reachable when the `sitemgr` app is installed and a page/module is
  configured to embed it. On an instance without `sitemgr` installed there is no way to load or
  browser-verify this template at all (the menuaction silently redirects to Home instead of erroring),
  so it was converted once, found untestable, and reverted rather than ship an unverified change to a
  template with no test coverage. Convert it for real only alongside access to an instance that has
  `sitemgr` installed and a page configured to use the module.

Filemanager's conversion covers the main index (desktop + mobile skin), the tile view, the background
jobs list (`jobs.xet`), and the shares list (`shares.xet`). `home.rows.xet` (the Home favorite-portlet
variant) is deliberately left on the legacy widget, same shared-framework-code reason as Addressbook's
portlet view above.

## Status

In progress. The checklist below is validated against four real conversions (see the reference
sections for the evidence each item is based on). Expect it to grow as more apps convert.

## Conversion checklist

Do **not** split this across multiple commits by layer (template-only, then JS-only, etc.) — see
[Why template + app-JS must land together](#why-template--app-js-must-land-together). Work through
these in order, in one commit, then expect follow-up fixups.

0. **Find every nextmatch instance the app owns, not just the main list.** An app's "list view" is
   often more than one `.xet` file: grouped/alternate views of the same list (e.g. an org/duplicate
   view switched via a toolbar select), a print/display/merge view, a picker/select popup, a
   secondary popup list driven by its own `app.ts`-sibling class (e.g. a "CRM"-style related-entries
   view), and the app's own mobile-skin templates all commonly define their own `<nextmatch>` and
   row templates independent of `index.xet`. `grep -rl "<nextmatch\b" <app>/templates/` (and the
   equivalent JS grep from step 2, run across every file in `<app>/js/`, not just `app.ts`) before
   considering the app converted — Addressbook's initial conversion commit only did the main index
   and silently left five more templates plus a whole secondary `CRM.ts` widget class on the legacy
   widget.

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
   that key server-side, same as before. `Et2Nextmatch`'s `settings` setter forwards this to
   `columnPreferenceName` automatically, re-deriving it on every settings update (not just first
   load), so no special handling is needed on the app side for the persistence itself. Just confirm in
   the browser (step 6) that column visibility persists correctly across a reload for each variant of
   the view.

   **If the app's own PHP also reads that same preference back** (e.g. to decide whether an expensive
   column is currently visible, the way Infolog does for `show_times`), make sure it re-derives the key
   the same way it was computed for the current request, rather than reading it back off the AJAX
   `$query` array. `Et2Nextmatch`'s row-fetch requests only resend filter-value settings (`filter`,
   `filter2`, `cat_id`, `search`, `col_filter`, `searchletter`), not the whole settings object, so
   anything else in `$query` — including `columnselection_pref` itself — can be one full-page-load
   behind the current request.

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
   - push/refresh notifications update the list correctly — first check *which* push mechanism the
     app actually uses; not every app shares the generic `api.queue` long-poll (e.g. mail registers
     its own IMAP push via `ajax_enablePush`). Confirming the registration call succeeds is not the
     same as watching a real push event land and refresh the list — say explicitly which of the two
     you verified. Don't conclude a transport is broken from one AJAX error either: a page
     reload/navigation aborting an in-flight long-poll logs a failure indistinguishable from a real
     one — retest against a fresh, idle load before drawing that conclusion.
   - if the app has a mobile skin, verify it with real device/User-Agent emulation plus a reload, not
     a resized desktop browser window (see `doc/ai/testing.md` § Browser / manual verification) —
     EGroupware selects the mobile template server-side from the request's `User-Agent`, so a plain
     resize just squeezes the desktop layout into a width it was never built for and can produce
     scary-looking but meaningless collapses (e.g. row text rendering into a 0-width cell)
   - a UI element that looks to be missing right after SPA-navigating into the app (as opposed to a
     full page load) may just be a stale-view artifact, not a regression — confirm on a fresh reload
     before reporting it
   - keep [Startup/lifecycle timing pitfalls](#reference-startuplifecycle-timing-pitfalls) in mind
     while doing this — several of these bugs only show up on interaction, not on load

7. **Budget for 1-3 follow-up fixup commits.** Every real conversion so far needed at least one; treat
   the first commit as "converted, pending fixups," not "done."

## Why template + app-JS must land together

Converting only the `.xet` template (and PHP, if any) is not sufficient. The app's own
`app.ts`/`app.js` almost always contains code written against the legacy `et2_nextmatch` widget's
public surface, and quite often private internals. A template-only conversion loads without error and breaks the first time the app's
own JS calls one of those legacy methods. 

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
  `<et2-vfs-size .../>`, `<vfs-mode .../>` → `<et2-vfs-mode .../>` (Filemanager, Mail). `<et2-vfs-name>`
  bound to a single string field instead of the whole row (Filemanager's `shares.xet`) just shows plain
  text, not a clickable breadcrumb — matches the legacy widget's own behavior for a scalar field, so no
  functional change.
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
- Row `class` binding: `<row class="$row_cont[class] $row_cont[cat_id]">` can be simplified to the
  direct-binding form `<row class="$class $cat_id">` — both syntaxes work, but new/edited rows should
  use direct bindings (see `Et2Nextmatch.md` § Row value bindings).
- Row-value binding syntax matters per-widget: `${row}[fieldname]` doesn't always work where the
  direct-binding form `$row_cont[fieldname]` does (Infolog) — if a bound value renders wrong or blank
  after confirming the field name is correct, try the direct-binding form before assuming the data
  itself is missing.
- Attributes that stop being used: `disable_selection_advance="true"` has no widget-level equivalent —
  implement the same "select the next/previous row after this one is removed" behavior in `app.ts` via
  the `et2-rows-deleted` event instead (see the replacement table below). `no_dynheight="true"` was
  also dropped without replacement in the one conversion that had it.
- **`header_right="some.template.id"` (a template shown to the right of the header row) has no
  `Et2Nextmatch` property equivalent** — `Et2Nextmatch` doesn't expose a `headerRight`/`header_right`
  attribute at all. If there's room for it, replace it with the pre-existing, widget-independent
  `slot="main-header"` mechanism instead: keep the `header_right` template unchanged and add
  `<template template="that.template.id" slot="main-header"></template>` as a sibling of the
  `<et2-nextmatch>` tag (this is how Tracker and Filemanager's mobile skins place their own header
  content, though those two hadn't converted the `<nextmatch>` tag itself when this was written). On a
  cramped mobile header a visible-label select can end up with too little vertical room for the label
  (a plain `<et2-select label="Type" ...>` needs more height than `main-header` has) — rather than
  fight for space, it's fine to just drop the filter from the header on mobile entirely, same as
  Addressbook's mobile skin ended up doing; the underlying `col_filter` setting still works, it's just
  not exposed as a header control there.
- **A legacy `options="..."` attribute on any widget now throws instead of being silently ignored.**
  `Et2Widget`'s base class repurposed `.options` into a read-only diagnostic getter (`@deprecated use
  widget methods`) that collects declared properties into an object — it no longer accepts the old
  positional/comma-separated config string legacy widgets used (e.g. `<et2-date-time-today
  options=",8">`). Setting it from the XML attribute throws `TypeError: Cannot set property options of
  #<Et2WidgetClass> which has only a getter`, and `Et2RowProvider` logs `failed to transform row
  template widget` and drops that widget from the row. Just remove `options="..."` attributes found on
  row-template widgets during conversion; there is no modern equivalent to migrate them to, since the
  concept itself is gone.
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
| `nm._get_autorefresh()` / `nm._set_autorefresh(0/n)` (pause auto-refresh during a long request) | **No replacement exists.** Decide per-app whether dropping this behavior is acceptable or needs a new API added to `Et2Nextmatch`. |
| `nm.controller._selectionMgr.resetSelection()` | `nm.clearSelection()`. |
| `nm.options.onselect = null` (temporarily suppress auto-preview-on-select) | `nm.addEventListener("et2-selection-changed", e => e.preventDefault(), {capture: true, once: true})`. |
| `et2_nextmatch.DELETE` constant | `Et2DatagridUpdateTypes.DELETE` from `Et2Datagrid.types`. |
| `nm.controller._indexMap` (check whether a uid is currently loaded/rendered in *this* nextmatch instance, e.g. before deciding to `refresh()` it from a push notification) | **No public replacement exists.** Closest option: `(nm.shadowRoot?.querySelector("et2-datagrid") as Et2Datagrid)?.rows` — the live, kept-in-sync row list (`{id, data}[]`), reached the same way `Et2Nextmatch`'s own private `_datagrid` getter does. This walks past a `private`-in-TS-only boundary via a real DOM query, not a sanctioned API — flag it to the `Et2Nextmatch` maintainer as a gap (a public `hasRow(uid)`/`getLoadedRows()` would be cleaner) rather than treating it as fully idiomatic (Addressbook's `CRM.ts`). |
| `this.nm.controller.getObjectManager()` | `egw_getObjectManager(appname).getObjectById(nm_index)` — grep the app for `.controller.` before considering it converted; every remaining hit is a crash waiting to happen. |
| jQuery `.on('refresh', (_event, _widget, _row_id, _type) => ...)` | `Et2Nextmatch.refresh()` dispatches a plain DOM `CustomEvent` with **no extra arguments** — `_widget`/`_row_id`/`_type` are always `undefined` now. Close over an already-captured reference instead of reading widget/row from the event. |
| Guessing at a renamed setting (e.g. `nm.settings.foldertree`) | Verify the replacement property actually exists on `Et2Nextmatch` (check `Et2Nextmatch.ts`) before using it. |

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
  bound the jQuery way silently receives `undefined` for what used to be `_widget`/`_row_id`/`_type`
  (seen breaking a push-notification refresh handler in Mail) — close over an already-captured
  reference instead of reading widget/row from the event.
- **`.controller` went from a public property to a private `_actionController`.** Code walking
  `nm.controller.*` breaks or crashes silently. Grep for `.controller.` across the app's JS as a
  conversion-completeness check.
- **Generic `et2_ready()` code (not gated by a per-template `switch`) can still reach a legacy widget
  instance** if the app has a template deliberately left unconverted, eg. a Home favorite-portlet
  variant (Filemanager: `scheduleChangeViewButtonUpdate()` crashed on `nm.updateComplete.then(...)` —
  `updateComplete` is LitElement-only). Grepping for `typeof nm\.` isn't a complete check; any
  assumed-modern-only property/method access on `nm` is a candidate.
- **Don't guess at renamed settings.** A removed widget property doesn't always have an obviously-named
  replacement (e.g. `nm.settings.foldertree` doesn't exist; the correct property for the current
  folder is `nm.activeFilters.selectedFolder`) — verify the property exists on `Et2Nextmatch.ts` before
  shipping.
- Auto-refresh pause/resume around long-running requests has no equivalent — call this out explicitly
  when converting an app that relies on it, rather than assuming it's covered.
- **The row-shadow-DOM `app.css` compatibility fallback** (used when the row template has no
  `<et2-styles>`) resolves the *containing template's own* template_set — derived from
  `closest("et2-template").getUrl()` — falling back to `default` only if the skin-specific file 404s.
  Don't assume it always loads `templates/default/app.css` regardless of the active skin; a
  mobile-skin conversion needs `templates/mobile/app.css` to actually be the one that loads. Bare
  `<et2-styles src="...">` values inside a row template resolve the same way, relative to that
  template's own file.
- **Category-color row indicators have a built-in mechanism — don't hand-roll a dedicated column for
  it.** Give the `<row>` element's `class` binding the bare recognized placeholder for the category field
  (`$row_cont[info_cat]`, `$cat_id`, `$category`, or `$cat` — see `Et2RowProvider`'s
  `CATEGORY_CLASS_PLACEHOLDER_FIELDS`), *not* a hand-prefixed token like `cat_$row_cont[info_cat]` (which
  isn't recognized and just becomes a literal, inert class). The row-class normalization step then emits
  both `row_category` and `cat_<id>` automatically, which `Et2Nextmatch`'s built-in
  `_customizeDatagridRow` hook picks up to set `--category-color` and `part="row-meta row-meta-category"`
  on the datagrid's own meta cell — styled by the framework default
  `et2-datagrid::part(row-meta-category) { border-left-color: var(--category-color, transparent); }`.
  Prefer this over a hand-rolled column with an inline `style="background-color: ..."` (which is also
  easy to get wrong — the framework's custom property is hyphenated, `--cat-<id>-color`, not
  underscored).
- **Direct `_filters` mutation while a fetch is in flight can make `Et2Datagrid` silently discard that
  fetch's response.** `Et2Datagrid._fetchPage()` captures `dataProvider.getQuerySignature()` (a
  serialization of the live `_filters` object) at dispatch time and compares it again once the response
  arrives; a mismatch is treated as "superseded" and the response is dropped with no retry. Anything
  that mutates `_filters` directly instead of going through `applyFilters()` — e.g. `sortBy(id, asc,
  false)` — changes that signature without dispatching a new request, so if it runs while an earlier
  fetch for the same nextmatch is still in flight, that fetch's real, valid response gets thrown away
  and nothing re-fetches, producing "rows fetched and returned from the server, but never shown" with
  no error anywhere. Check for direct-`_filters`-mutation call sites (particularly sort-seeding that
  runs during startup, racing an initial `applyFilters()`) when debugging a symptom like this.
- **The column-selection button can render at 0 width and become invisible/unclickable on any
  platform with no native scrollbar gutter** (touch/mobile browsers, macOS overlay scrollbars, or
  simply a grid whose content doesn't currently overflow). `Et2Datagrid.styles.ts`'s
  `--column-selection-width` floors at `clamp(16px, var(--scrollbar-space), 24px)` rather than
  shrinking toward zero, and `.dg-header`'s `padding-right` reserves exactly that same space — if
  you're touching either of those declarations, keep them pointed at the same custom property or the
  button will start overlapping the last column again.
- **`Et2Nextmatch` always renders its header row and column-selection button — there is no
  per-instance property to suppress them.** (`Et2Datagrid` itself still has internal
  `noVisibleHeader`/`noColumnSelection` properties, but those are only ever set by
  `Et2Nextmatch` for embedded subgrids/expanded child rows, not exposed for a top-level grid to
  opt into.) Legacy nextmatch visually hid its header on mobile the same always-built-then-hidden
  way: the mobile theme's CSS hid it, not a widget flag. The modern equivalent lives in
  `kdots/css/src/mobile.less` (`et2-nextmatch::part(header) { display: none; }`, next to the
  legacy `.et2_nextmatch` header-hiding rules) — reachable through `Et2Datagrid`'s shadow root
  because `Et2Nextmatch` forwards its `header` part via `exportparts`. Don't reach for a
  JS/property-based toggle for this kind of "hide entirely on mobile" styling; match the existing
  CSS-only pattern instead.
