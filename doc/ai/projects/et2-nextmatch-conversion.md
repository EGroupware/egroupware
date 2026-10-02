# Converting an app from `et2_extension_nextmatch` to `Et2Nextmatch`

A checklist for moving one app's list view from the legacy `nextmatch` widget
(`api/js/etemplate/et2_extension_nextmatch.ts`, tag `<nextmatch>`) to the `Et2Nextmatch` web
component (`api/js/etemplate/Et2Nextmatch/Et2Nextmatch.ts` + `Et2Datagrid.ts`, tag
`<et2-nextmatch>`). For widget usage (attributes, row bindings, styling, row expansion), see the
generated component docs for
[`et2-nextmatch`](https://etemplate.egroupware.org/components/et2-nextmatch/) and
[`et2-datagrid`](https://etemplate.egroupware.org/components/et2-datagrid/) — this document does not
repeat that reference material.

How this document is organised: the [checklist](#conversion-checklist) is the procedure, and each step
links to the reference and lessons it depends on. [Status by app](#status-by-app) says what is
converted. [Lessons learned](#lessons-learned) collects what earlier conversions taught, by topic, so
read the topics that apply to the app in hand. The reference sections hold the lookup tables. The
[appendix](#appendix-removing-the-legacy-widget) is for whoever eventually deletes the legacy widget.

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
   Then check every tag left in the row template against `customElements` - `Et2RowProvider`
   clones row cells **by tag name**, so an app-specific tag with no client-side registration
   renders nothing at all, silently - see [Row widgets and row styling](#row-widgets-and-row-styling).

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

   This step is about settings the app's **JS** reads back — it is not a licence to delete every
   `$content['nm']` key that isn't on the list. Several are consumed server-side and deliberately never
   sent to the client, `no_filter`/`no_filter2`/`no_cat` being the ones most likely to get pruned by
   mistake; see [the filterbox and `filter-template.php`](#reference-the-filterbox-and-filter-templatephp).

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
   this way. `app.css` never reaches the rows, so every row rule has to move there.

6. **Verify in a browser**, watching the console the whole time (`.controller`/`.options` access
   failures throw at first use, not at page load, so a quiet page load proves nothing):
   - list loads, sorts, filters, selects rows
   - bulk actions (delete, move, whatever the app has) work on both a partial selection and "select
     all"
   - any view switch the app has (row/tile/kanban/etc.) works and doesn't leave stale state
   - column visibility/order/width persists across a reload, including for every distinct
     column-preference key the app uses (see step 4)
   - the filter drawer holds what it should: search, the Sorting select, the standard
     `filter`/`filter2`/`cat_id` controls the app actually offers, and a Column Filters section matching
     the row template's filtering headers. A filter that silently vanished usually means a `no_*` flag
     or a header widget changed kind during the template conversion — see
     [the filterbox and `filter-template.php`](#reference-the-filterbox-and-filter-templatephp)
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
   - the app header looks like the other apps': an Add button is a plain + icon,
     `<et2-button-icon image="add" statustext="...">` with no label, in the app header
     (`main-header`), not a labelled `<et2-button>` left over from a `header_left` template - see
     [Layout and template structure](#layout-and-template-structure) for lists shown inside Admin
   - toolbar controls that mirror an `nm` filter (a details/no-details toggle, a view-mode select,
     etc.) show the *correct, persisted* state on a fresh page load, not just after the first click —
     set a non-default filter value, reload, and confirm the control's displayed state already matches
     `nm.activeFilters` before touching it. A control that looks right only after one "wasted" click is
     a real bug class, not a quirk (Timesheet's details toggle); see
     [Startup/lifecycle timing pitfalls](#reference-startuplifecycle-timing-pitfalls) below
   - keep [Startup/lifecycle timing pitfalls](#reference-startuplifecycle-timing-pitfalls) in mind
     while doing this — several of these bugs only show up on interaction, not on load

7. **Budget for 1-3 follow-up fixup commits.** Every real conversion so far needed at least one; treat
   the first commit as "converted, pending fixups," not "done."

## Why template + app-JS must land together

Converting only the `.xet` template (and PHP, if any) is not sufficient. The app's own
`app.ts`/`app.js` almost always contains code written against the legacy `et2_nextmatch` widget's
public surface, and quite often private internals. A template-only conversion loads without error and breaks the first time the app's
own JS calls one of those legacy methods. 

## Automatic fallback for unconverted templates

`convertNextmatch()` in `api/etemplate.php` rewrites every `<nextmatch>` to `<et2-nextmatch>` in the
copy of the template sent to the client, so an app nobody converted — ours or a customer's — still
shows its list and data. It is a stopgap, not a conversion: an app running on it still needs this
checklist.

- **How to tell:** the `.xet` source still says `<nextmatch>`, but the page has an `et2-nextmatch`.
  The server keeps parsing the raw file, so server-side it is still the legacy widget.
- **What it converts:** the mechanical template patterns only — `options=` → `template=`,
  `header_left`/`header_right` → slots, `class="th"` on the header row, sortheader/customfields header
  renames, `$field` row classes, leftover `options=` on row widgets, and a grid nested in a header or
  row cell → `et2-vbox`/`et2-hbox` (row/column `disabled=` kept, column alignment lost).
- **Opting out:** `<nextmatch legacy="true">` keeps the legacy widget for that nextmatch and its row
  template — for an app whose JS breaks on the new widget and can't be converted yet.
- **Known gaps:** app JS using the legacy API (`.controller`, `nm_action`, `.options.settings`, …) can
  fail on first use; `fetchAll()` is the exception, it hands over to `fetchAllIds()`. A repeating grid
  (`<grid id="${row}[…]">`, one row per array entry) can't be converted and its cell stays empty; the
  console shows `Et2RowProvider: <grid …> in a row template is not supported` (Resources' accessories
  column).
- **As a starting point for a real conversion:** `php api/etemplate.php -i <app>/templates/default/<name>.xet`
  now includes this rewrite. It also reformats the file (attribute spacing collapses, `>` becomes
  `&gt;`, umlauts become `&#xF6;` entities, the `<?xml-model` line goes), so review the diff. Two of
  its header renames are wrong: `nextmatch-filterheader` comes out as a plain `et2-nextmatch-header`
  and `nextmatch-accountfilter` as `et2-nextmatch-filter` - make them `et2-nextmatch-header-filter`
  and `et2-nextmatch-header-account` by hand (Records). It also maps a sortheader's legacy `options=`
  to `sortmode=`, which is rarely right. Run it in the container (`docker exec -u www-data -w
  /var/www/egroupware egroupware php api/etemplate.php <path>`); without `-i` it prints the result.

## Status by app

| App | Status | Covers |
|---|---|---|
| Addressbook | converted, verified | main index (desktop + mobile), org/duplicate grouped views, CRM popup, contact picker. `display.xet` (Sitemgr content block) is parked on the legacy widget: it is only reachable with `sitemgr` installed, so it cannot be verified here |
| Infolog | converted, verified | main index (desktop + mobile) |
| Filemanager | converted, verified | main index (desktop + mobile), tile view, `jobs.xet`, `shares.xet` |
| Mail | converted, partly verified | main index (desktop + mobile); live push-triggered refresh not yet observed |
| Timesheet | converted, verified | main index (desktop + mobile) |
| Tracker | converted, verified | main index (desktop + mobile), Escalations, the edit popup's lazy comments list |
| Home | converted, verified | the favourite portlet and every app's portlet row template, see [below](#the-home-favourite-portlet) |
| Calendar | converted, verified | `calendar.list` (desktop + mobile) |
| ProjectManager | converted, verified | project list, element list, pricelist (desktop + mobile) |
| Admin | converted, verified | all nine lists, incl. push refresh |
| Importexport | converted, verified | the definition list (shown inside Admin or Preferences), incl. its "Change" owner / allowed users dialogs |
| Preferences | converted, verified | application passwords tab of the "Security & Password" popup |
| Resources | converted, partly verified | resource list (incl. accessory links and the delete / un-delete dialogs) and category ACL list verified; mobile skin and Home's portlet row template not |
| openid, webauthn | converted, partly verified | their "Security & Password" tabs and openid's client list in Admin; mobile skins and webauthn's Register (needs a real authenticator) not |
| aitools, bookmarks, developer, esyncpro, invoices, kanban, news_admin, rag, records, smallpart | converted, verified | every list; esyncpro had no devices to show, smallpart's mobile courses list not checked |
| policy | converted, not to be committed yet | popup printing fixed in kdots (`framework.print(window)` prints the popup); still needs its lists' `printOptions` set (replacing `_override_print_dialogs()`), and `Et2Tabs.beforePrint()` fixed, which throws for any template with tabs |
| stylite | converted, partly verified | call history (empty here); Placetel VoIP destinations need a configured Placetel account |

Still on the legacy widget: Addressbook's `display.xet` (Sitemgr only, see above), and
Aiassistant's conversation list, skipped on purpose: nothing links to it, its row template fails
server-side (`findLastRow()`) and its button handlers don't exist, with or without a conversion. Phpbrain
(deprecated, to be replaced by the knowledgebase app) and Schulmanager (unused) are deliberately left
out. They, and every customer template, *run* on `Et2Nextmatch` through the
[automatic fallback](#automatic-fallback-for-unconverted-templates), but that is not a conversion.
Related in-flight/reference docs in the same directory as the widget source: `ColumnSelectionNotes.md`,
`Et2DatagridDirectoryMigrationPlan.md`, `NestedExpansion.md`.

The checklist is based on these conversions; expect it to grow as more apps convert.

## Lessons learned

What earlier conversions taught, by topic. Each entry is the rule, with the app it came from in
parentheses. Where a reference section already has the full entry, the lesson links to it instead of
repeating it.

### Layout and template structure

- **The `<et2-nextmatch>` must be a direct child of the index template, never inside a `<grid>`.** A
  grid is a `<table>`, and a table cell does not bound its child's height: the datagrid grows to fit
  every row, the page scrolls instead of the grid, and the virtualizer renders the whole result as
  empty placeholders. The console also says `Legacy widget grid[#] could not handle adding a child
  (ET2-NEXTMATCH)`. Widgets that shared the wrapper grid become direct children with their own
  `disabled=` (Calendar, ProjectManager's mobile skin). The symptom is a list that doesn't scroll,
  easy to mistake for a styling problem.
- **A list that shares its template with other widgets needs `layout="stack"`, plus one CSS rule for
  now** (the [pitfall entry](#reference-startuplifecycle-timing-pitfalls) has the why). `grow="1"`
  doesn't reach the DOM from a `.xet`: `transformAttributes()` only sets an attribute for a reflecting
  property, so `[layout="stack"] [grow]` never matches. Keep `grow="1"` in the template anyway and add
  `et2-template[layout="stack"] > et2-nextmatch { flex: 1 1 auto; min-height: 0 }` to the app CSS. The
  upstream fix is to reflect `grow`, or to add `et2-nextmatch` to `GROW_TAG_SELECTOR` in
  `Et2LayoutStrategies.ts`. In a popup, give the etemplate container a height too (Admin).
- **`et2-template` lays out its children inside a `<div part="base">` in its shadow root**, so that
  div is the flex container, not the element. Its `id` attribute is `<dom id>_<template name>`, so
  `[id="admin.accesslog"]` matches nothing (Admin).
- **Legacy `header_left`/`header_right` templates move into a slot** of the nextmatch:
  `<et2-template id="<template id>" slot="header">` (the name goes in `id`, not `template`, and
  `getWidgetById()` still finds it by that name) (Calendar), or `main-header`, see the rename patterns.
- **A list another app shows inside Admin gets its Add button into Admin's header** the way
  Admin's own lists do: a `<template id>.header` template with `slot="main-header"`, plus
  `app.admin.enableAppToolbar(et2, name)` from the app's `et2_ready()`, which moves it into the
  `<egw-app>` and hides it again when Admin shows something else (openid, aitools, Filemanager's
  jobs). If the same list can also be shown elsewhere (Importexport's in Preferences, for users
  without admin rights), nothing moves the header there, so put it into the nextmatch's `header`
  slot instead. Either way, make it a + icon (`<et2-button-icon image="add">`), not a labelled button.
- **Keep a footer widget a sibling of the nextmatch, not in its `footer` slot**, unless its value
  lives in the nextmatch's namespace: children of `<et2-nextmatch>` read `$content['nm'][...]`
  (Admin's access-log percentage went blank).
- **Replace `class="hide"` plus `set_disabled()` with `disabled=`.** `Et2Widget.set_disabled(false)`
  clears `hidden`, which can't beat a `.hide` class still on the element, so the widget never
  reappears (Admin's group list). Grep converted templates for `class="hide"`.
- **On `<row class=>`, bind with `$row_cont[field]`, not `$field`**: the bare form logged "Error
  compiling PHP $status_class" and set no class (Admin).
- **A CSS rule that hides something inside a web component stops working once the component moves
  its content into a shadow root** (Calendar's filter-drawer iframe). Use the widget's `disabled=`.

### Row widgets and row styling

- **Every tag in a row template needs a client-side custom element.** `Et2RowProvider` clones row
  cells by tag name and never consults the server's `type` modifications, so a server-transformed tag
  renders nothing, silently (ProjectManager's `<projectmanager-select-erole>`, Admin's
  `<customfields-types>`). Register a custom element, plus a `<tag>_ro` variant, which is what a
  read-only cell gets. Or, if the app already publishes the options, use a plain
  `<et2-select readonly="true">` (Admin). Grep the row templates for tags with no
  `customElements.define()`.
- **A nested repeating `<grid>` in a row cell is inert**: the row provider has no autorepeat
  (Resources' accessories column). It needs a widget the row provider understands, or a flat field
  from the server.
- **Row CSS moves to `rows.css` behind `<et2-styles>`, keyed on classes**: see the pitfall entry
  "`app.css` never reaches the rows". Rules keyed on generated widget ids
  (`span[id^="admin-index"]...`) or legacy grid markup (`tr.x td`, `.egwGridView_outer`) don't survive
  either (Admin). `et2-image`/`et2-appicon` keep their `<img>` in the light DOM, so a class on the host
  needs `.cls, .cls img`.
- **Category colour goes in the row's class, not in a column of its own**: see the pitfall entry.
- **Check a `<progress>` field's real format before adding `%`**: `pm_completion` arrives as `7`,
  `pe_completion` as `7%` (ProjectManager).
- **Header-less row templates (an empty `<row class="th">`, most mobile skins) save no column state.**
  Their columns only have positional keys (`col0`, ...), which name a different column as soon as the
  template adds or removes one, and nobody can resize them anyway.
- **Give every column header a widget with an `id`.** A plain `<et2-description value="News">` as
  header leaves the column with a positional key (`col0`), and a saved column preference for `col0`
  can hide it for good - News' main column was invisible, under the legacy fallback as well. An
  `<et2-nextmatch-header id="news" label="News">` gives it a stable key (News).
- **An `et2-link`/`et2-link-string` entry only opens with a string id.** `Et2Link._handleClick()`
  gives up when `entryId` is a number, so a server-built `[{app, id, title}]` list needs
  `'id' => (string)$id` (Resources' accessory links).
- **`<column width="…em">` doesn't work yet**: the unit is dropped (`6em` becomes `6px`). Use px.
- **`<et2-description value="#%s">` no longer formats**: the web component substitutes into its own
  value and renders `46`, not `#46` (Calendar). Not fixed; don't rely on it.

### Several lists on one page

- **Each `Et2Nextmatch` appends its own filterbox to the `<egw-app>`, so the app must mark the
  inactive ones `hidden`.** Use the attribute, not CSS: `EgwFrameworkApp.filters`, and with it "Clear
  filters", the filter icon and `getFilterInfo()`, is
  `querySelector("et2-filterbox:not([hidden],[disabled])")`. Sync on every view switch, once
  immediately (Admin's boxes already existed at `et2_ready()`), and from a `MutationObserver` on the
  `<egw-app>`, since a filterbox only appears once its filter template arrives (ProjectManager, Admin).
- **Override `EgwFrameworkApp.getNextmatch` to return the list on screen**, from the app's own view
  state (ProjectManager's view, Admin's tree value). The drawer label, column selection and the
  filter drawer's auto-open all use it.
- **On a view switch, set `<egw-app>.rowCount` from the current nextmatch's `totalCount`**: the switch
  produces no new search result, so the drawer heading keeps the old count (ProjectManager).
- **Don't find "the" nextmatch with `getWidgetById('nm')`**: ids differ per list (Admin's tokens list
  is `token`). Query `et2-nextmatch` in the DOM.
- **A list loaded on demand needs `lazy="true"`, not just `num_rows => 0`.** `lazy` defers the fetch
  until the nextmatch is actually displayed (inactive tab, `disabled`, an app's hidden view). If the
  app also refreshes on show, skip the first show, which already is the initial fetch (Admin's group
  list).

### Filters and the filter drawer

- **A nextmatch in a popup gets no filterbox**: put its controls in the nextmatch's `header` slot,
  see the [filterbox reference](#reference-the-filterbox-and-filter-templatephp) (Admin's ACL popup).
  Or, if its row template already has filter headers, keep them in the column headers with
  `replaceFilters="false"` on the `<et2-nextmatch>`: `api/etemplate.php` otherwise turns every filter
  header into a plain label when serving the template, expecting the filterbox to take over. The
  attribute is live, not legacy - it was dropped once as "unused" and the popup lost its filters
  (Preferences' application passwords, openid's access tokens, Policy's history).
- **A nextmatch in a tab added through `extraTabs`** (the hook-provided tabs of Preferences' "Security
  & Password" popup) used to fail every fetch after the page's own rows - sorting, paging, refresh -
  with "Unknown nextmatch/historylog widget": the tab is only attached to the template while it runs,
  so `Nextmatch::ajax_get_rows()`'s `getElementById()` could not see it. Fixed in `33dff7dff2`:
  `Nextmatch::getExtraTabsElementById()` searches only the extraTabs stored in the request's
  modifications, and only at the exact namespaced `form_name`.
- **An app with its own `slot="filter"` template needs no drawer work** (Calendar).
- **An app whose filters are never empty needs its own `getFilterInfo`**, or the filter icon stays lit
  and "Clear filters" can't clear it. Calendar always has a date range and a status filter defaulting
  to `"default"`; its `getFilterInfo` drops those before delegating.
- **Fold a sort into the same `applyFilters()` call.** A separate `sortBy()` straight after
  `applyFilters()` changes the query signature mid-fetch, and the response is dropped as superseded
  (Calendar); see the pitfall entry on `_filters` mutation.

### Actions and popups

An action with `'nm_action' => 'open_popup'` opens the element whose id contains `<action id>_popup`.
`Et2NextmatchActionController.openActionPopup()` expects that element to **be an `<et2-dialog>`**: it
sets `.selectedIds` and calls `.show()`. For a legacy box popup (`<et2-box class="action_popup prompt">`,
shown and hidden by CSS) it delegates to the legacy `nm_open_popup()`, which upgrades the box into a
dialog at runtime. That delegation is a stopgap that keeps `et2_extension_nextmatch_actions.js` in use;
the goal is for every converted app to ship real dialogs. If no matching element exists at all, the
action falls through to a plain form submit with whatever the fields hold, which is a silent no-op at
best.

**Action item for every conversion:** grep the app's list templates for `action_popup`/`prompt` boxes
and convert them to `<et2-dialog>`s in the same commit. Put the fields in an `<et2-box id="<action>_popup">`
inside the dialog (for the namespace) and the buttons in its `footer` slot. The buttons are the hard
part: a box popup's buttons rely on `nm_open_popup()` setting the `window.nm_popup_action`/
`nm_popup_ids` globals for `nm_submit_popup()`, and a real dialog skips that upgrade, so each button's
onclick has to submit without those globals (Tracker's `app.tracker.submit_popup()`, Admin's
`app.admin.submit_popup()`). Also grep the app's CSS for the popup's id: a leftover `display:none` from
the box version collapses the new dialog's body to nothing. Check the mobile skin too: if an
`open_popup` action is not `hideOnMobile`, the mobile list template needs the dialog as well.

**State of the converted apps (2026-10-01):** no legacy box popups and no `nm_submit_popup`/
`nm_hide_popup`/`nm_open_popup` calls are left in their list templates. Every `open_popup` action has a
real dialog on desktop: InfoLog `startdate`/`enddate`/`responsible`, Tracker `admin`/`assigned`/`group`,
ProjectManager `add_existing` (desktop and mobile), Admin categories `owner` (desktop and mobile),
Importexport `owner`/`allowed`. On
mobile, InfoLog's and Tracker's "Change" submenus are `hideOnMobile`, so their dialogs are not needed
there, and Tracker's top-level "Multiple changes" (`admin`) is `hideOnMobile` as well, since the mobile list
has no `admin_popup_dialog`.

- **Size a dialog with `::part(panel)` CSS, not `width=`.** In a `.xet`, `width=` on any web component
  becomes the host's inline CSS `width` (`loadWebComponent()`), so it never reaches `Et2Dialog.width`.
  `et2-dialog.<class>::part(panel) { width: 40em }` in the template's `<et2-styles>` works
  (Importexport).
- **A multiple select's value comes in option order, not in click order.** `sl-select` builds it from
  its selected options. App code that treats the last value as the one just picked, eg. to keep
  "Just me"/"All users" exclusive of groups, has to compare with the previous value instead
  (Importexport's `allowed_users_change()`).
- **Check whether a popup is still reachable before converting it.** Calendar's `delete_popup`/
  `undelete_popup` boxes had long been replaced by `onExecute` handlers (`app.calendar.cal_delete`, a
  real dialog plus an ajax call) and were deleted, not converted.
- **Don't reach into an `Et2Nextmatch`'s action manager.** It deliberately has no accessor (a decision,
  not an oversight). `nm.executeAction(id, {ids, all})` runs the framework's default execute and skips
  the action's `onExecute`, so it replaces an `nm_action(...)` call for a url/submit action but not a
  JS-handled one. It also re-resolves the action by id from the nextmatch's own manager, which matters
  when an app builds a second action tree from the same array (Calendar's non-list views). Where app
  code has to drive a JS-handled action itself, pass a plain action-shaped literal, as
  `smallpartApp.mergeVideo()` does.

#### Before converting a `link_popup`-style action, check for the auto-added "Link" action

EGroupware's action framework already auto-adds a generic "Link" context-menu action to **every** app
whose entries are registered in the cross-app Link registry (i.e. the app's `setup.inc.php` has a
`hooks['search_link']` entry) — see `EgwPopupActionImplementation._addLinkAction()`
(`api/js/egw_action/EgwPopupActionImplementation.ts:968`), wired into every popup/context menu's
`_buildMenu()` (`:636`) and gated only on `egw.link_get_registry(app, 'query'|'title')` returning
something. It opens `LinkAction.open()` (`api/js/etemplate/Et2Link/LinkAction.ts`): a small dialog to
pick one target entry via `<et2-link-entry>`, then Add (link) or Remove (unlink) every currently
selected entry — including a proper "select all" (`nextmatch.fetchAllIds()`), per-entry success/failure
reporting, and a real per-source edit-rights check (`Widget\Link::checkLinkAccess()`, called once per
source entry inside `Widget\Link::ajax_link()`/`ajax_delete()`) — all via `jsonq()`, no page reload.

If an app being converted has its own hand-rolled `link_popup`/`link_action`-style mass-action (a
`<et2-link-entry>` plus Add/Delete buttons, backed by the app's own `case 'link':` in its `*_ui::action()`
that calls `Link::link()`/`Link::unlink()` directly), **check whether it can just be deleted** instead of
converted to a `<et2-dialog>`. Not every link-like popup is a duplicate, though: ProjectManager's
`add_existing` links the *picked* entry to the project on screen, ignoring the selected rows, which
is the opposite direction of the generic action, so it was converted:

- Confirm the app is actually in the Link registry (it almost certainly is, if it has its own link
  action at all) — `grep -n "search_link" <app>/setup/setup.inc.php`, or check live via
  `egw.link_get_registry('<app>', 'query')` in the browser console.
- Confirm live that right-clicking a row already shows a top-level "Link" item (with the link icon) —
  if the app's own action is nested under a submenu (Tracker's was under "Change"), the two coexist
  without colliding, so this is safe to check on an unconverted app too, before doing anything else.
- **Check for anything the app's own `case 'link':` does that the generic action does not**, before
  deleting it — Tracker's had nothing extra (it did no edit-rights check at all, which the generic action does), but another
  app's version might: an extra confirmation, a restriction to certain link types/apps, a side effect
  (e.g. also notifying someone, or writing to the app's own audit/history log), or a different rights
  model for who may link vs. unlink. Losing a silent app-specific restriction is easy to miss since
  both the old and new action "work" from the end user's point of view — the only way to catch a
  difference is reading the old handler's full body once before deleting it, not just diffing behavior
  in a manual click-test.
- If it does turn out to be pure app-specific reproduction of the generic behaviour, delete: the popup
  markup, the action-tree entry, the `case 'link':` handler, and anywhere the app's own JS/PHP builds a
  composite `<action>_<verb>_<value>` string specifically for `'link'` (Tracker had this in the
  `in_array($multi_action, [...])` block in `tracker_ui::index()`, shared with `assigned`/`group` —
  remove only the `'link'` member and its `is_array()` special-case, not the whole block).

### Preferences, refresh and push

- **App code that reads a nextmatch preference must use the framework's key fallback,
  `columnselection_pref ?? template`** (Calendar read `nextmatch-undefined-autorefresh`).
- **A `set_<x>` method an app defines on the widget may already be dead**: those are only called by
  the JSON `assign` plugin, which no PHP uses (Calendar's `set_startdate`/`set_enddate`). Grep for
  `assign(` before porting one.
- **Verify push with a change made in another session.** A refresh in the editor's own session proves
  little: account searches used to be cached per session, so only other sessions got stale rows
  (fixed by `Accounts::__wakeup()`, Admin).

### The Home favourite portlet

Home's "favourite" portlet renders another app's list inside a small tile, so converting it converts a
piece of every app at once. It is one template (`home/templates/default/favorite.xet`), one widget class
(`home/js/Et2PortletFavorite.ts`) and the nine row templates the portlet can be pointed at:
`addressbook.index.rows`, `calendar.list.rows`, `filemanager.home.rows`, `infolog.home`,
`news_admin.index.rows`, `projectmanager.list.rows`, `resources.show.rows`, `timesheet.index.rows`,
`tracker.index.rows`.

**Those nine live in standalone `.xet` files that duplicate the app's own row template, on purpose.**
The portlet asks for the row template by name and nothing else on the Home page defines it, so
`Et2Template` falls through its cache to `<app>/templates/<set>/<rest>.xet` and fetches the file. In the
app itself the same template id is already in the cache, inlined in `index.xet`, so the standalone file
is never fetched there — which is exactly why converting one of these files cannot break the app's own
list view, and equally why the two copies have to be kept in sync by hand. Several had already drifted
before the conversion.

Portlet-specific things that do not come up when converting an app's own list:

- **There is no header bar to hide.** The legacy portlet's chevron called `set_hide_header()`, which hid
  the nextmatch's search/filter/favourite bar, and CSS additionally collapsed the column header row.
  `Et2Nextmatch` has neither — its filters live in the app shell's filter drawer, which a portlet on
  Home cannot reach. The chevron now only toggles a `header_hidden` class on the portlet, and
  `home/templates/default/app.css` hides `et2-nextmatch::part(header)`; that one part covers both
  Et2Nextmatch's own header slot and the datagrid's column header row, because Et2Nextmatch re-exports
  the datagrid's `header` part under the same name.
- **`header_left` has no property equivalent**, and Filemanager is the only app that sends one (its
  up/home/path navigation). `Et2PortletFavorite.applyHeaderTemplate()` reads the template name straight
  out of the portlet's content — it is not in `ALLOWED_SETTINGS`, and shouldn't be — and slots an
  `<et2-template>` into the nextmatch's `header` slot. Home's `app.ts` calls it from `et2_ready()`, the
  first point where both the content and the nextmatch exist.
- **Turn the filter drawer off with `''`, not `false`.** `$content['nm']['filter_template'] = false`
  reaches the client as the *string* `"false"`, which is truthy, so a filterbox gets built and appended
  to whatever `<egw-app>` contains it — on Home that is Home's own drawer, filling it with eight other
  apps' filters. `home_favorite_portlet` now sets `''` and normalises any subclass's `false` in
  `exec()`. (Same shape as `Et2Template.getUrl()`'s existing `"null"` special case.)
- **`row_modified` is a key into the row *content*, not a sort column.** A nextmatch with no `order` of
  its own falls back to ordering by `row_modified`, which fails the whole query when the two namespaces
  differ — Calendar's rows carry `modified` while the column is `cal_modified`. Two portlets were dead
  because of this (`calendar_favorite_portlet`, and `resources_favorite_portlet`, which had
  Timesheet's `ts_modified` copied into it); give the portlet an explicit `order`/`sort` rather than
  bending `row_modified` into a column name it then can't do its real job with.
- **Customfield widgets need an explicit `app=`.** `Customfields::beforeSendToClient()` falls back to
  the current app, and a portlet is rendered under Home for part of its request. Both
  `<et2-customfields-list>` and `<et2-nextmatch-header-customfields>` take the attribute.
- **A nested autorepeating `<grid>` inside a row cell is inert.** `Et2RowProvider` builds a row by
  cloning and hydrating individual widgets; it has no autorepeat, so the nested `<grid>`/`<columns>`/
  `<rows>` tags are stamped into the DOM as unknown elements and render nothing, silently. Resources'
  accessory sub-list was the one instance; it now needs either a repeating widget the row provider
  understands or a flat server-provided field.

### Testing a conversion

Checklist step 6 has the full list. In addition:

- **Reach a sub-page the way the app's UI does** (Admin: through the tree, `app.admin.load(url)`), not
  by its own URL. A synthetic `app.admin.run()` is not a click either: it doesn't set the tree value
  that `getNextmatch()` reads. `app.admin.load(url)` without `ajax=true` in the url loads the page
  into Admin's iframe, where a nextmatch finds no `<egw-app>` and appends its filterbox to the
  iframe's `<body>`, out of reach. The tree's own links carry `ajax=true` (Importexport).
- **Take a settled reading.** Right after "Clear filters" the row count can read 0 while the reload
  is in flight (Calendar).

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
  the template (Mail: `attachment_icon`, `flagged_icon` fields added specifically for this). If the
  field genuinely is rich-text/HTML content (not just an icon-selection hack) rather than a
  server-computed blob to eliminate, see the `<html>`/`<htmlarea>` entry below instead — don't try to
  decompose real HTML content into per-field widgets.
- **Any bare `<html id="${row}[field]"/>` row-template widget silently renders nothing, with no
  console warning at all** (Tracker: `tr_description`, `reply_message`) — `<html>` is a legacy
  jQuery-only widget (`et2_widget_html.ts`, registered in `et2_registry`) that `Et2RowProvider`'s
  clone step doesn't know about; it falls through to a bare `document.createElement("html")`, an
  inert native element that never receives a value. This is a **different, silent failure mode**
  from the documented `options="..."` case (`Et2RowProvider` logs `failed to transform row template
  widget` for that one) — here there is nothing to see in the console, the cell is just empty.
  Replace with `<et2-htmlarea readonly="true">` (the modern widget that actually renders `unsafeHTML`
  and supports row hydration via `Et2InputWidget`'s `transformAttributes`) — not `<et2-description>`,
  which escapes its value and has no raw-HTML mode at all. Immediately add `noAiTools="true"` too (see
  the next bullet) or the fix appears to do nothing.
- **`<progress id="${row}[field]"/>` renders as a *native* `<progress>` stuck in its indeterminate
  animation** (Tracker: `tr_completion`) — same root cause as the `<html>` bullet above (`et2_progress`
  is a legacy widget, so `Et2RowProvider`'s clone step falls through to `document.createElement`), but
  this one is worse than an empty cell: `progress` *is* a real HTML element, so every row shows a
  plausible-looking animated bar and nothing hints that no value ever arrived. There is no
  `et2-progress` web component, and none is needed - bind the native element's own attributes instead:
  `<progress title="$row_cont[field]%" value="$row_cont[field]" max="100"/>`. `label=` does NOT work
  here (it is what InfoLog's converted `index.xet` originally carried over): it is inert on a native
  `<progress>`, which then has no accessible name and no hover text at all, where the legacy widget
  used to put its label in the element's `title`. Drop the `id="${row}[field]"` binding too: a plain
  element has no `value` property options and no `set_value()`, so row hydration never reaches the
  value branch and instead `setAttribute()`s the *resolved* id, leaving `id="70"` on the element.
  Finally add `width: 100%` CSS - a native `<progress>` defaults to ~140px, which overflows a narrow
  list column and gets clipped, so an un-styled 70% bar looks full.
- **Any `<et2-textarea>`/`<et2-htmlarea>`/`<htmlarea>` tag anywhere in a served `.xet` file — including
  inside a nextmatch row template — gets blindly wrapped in `<et2-ai>` server-side** by a blanket regex
  in `api/etemplate.php` (`# wrap et2-textarea and htmlarea in et2-ai ...`), unconditionally, unrelated
  to nextmatch. `Et2RowProvider` can't hydrate a widget nested inside that extra wrapper level, so a
  freshly-added row-template `<et2-htmlarea>` still renders empty even after fixing the `<html>` tag
  itself, with no error either. Add `noAiTools="true"` to the widget to opt out — the same escape hatch
  Tracker's own mobile `edit.xet` already uses for exactly this widget in exactly this row context.
- **A row-template's header `<row>` must have `class="th"`, even for header widgets that aren't
  sortheaders/filters** (Tracker's `tracker.edit.comment_row`, a comments/replies row template using
  plain `<et2-nextmatch-header-account>`/`<et2-nextmatch-header>`, no sorting or filters wanted).
  `Et2RowProvider._fromTemplateRoot()` looks for `.th` (or `thead`) specifically to find the header row;
  without it, it falls back to `tplRoot.children[0]`/`[1]` by position, which is fragile and — for a
  `<grid><columns>/<rows></grid>` structure — resolves to the wrong element entirely, throwing
  `Cannot read properties of null (reading 'tagName')` inside `_headerColumnSourceNodes()` and leaving
  the whole grid stuck on "No row template configured", not just that column. This can pass earlier,
  narrower testing (e.g. a ticket that already has replies) and still be a real, general parse failure —
  don't assume a `class="th"`-less header row is safe just because one row template already using it
  loaded once; check every row template's header row explicitly, including ones for embedded/tab-panel
  grids that don't need a "real" header UI.
- Nested `<grid>`/`<columns>`/`<rows>` inside a `<nextmatch-header>` cell (multi-line sortable headers)
  does not carry over — replace with `<et2-vbox>`/`<et2-hbox>` wrapping
  `<et2-nextmatch-sortheader>` elements (Addressbook, Infolog).
- Row `class` binding: **use `<row class="$row_cont[class] $row_cont[cat_id]">`, not the
  direct-binding form `<row class="$class $cat_id">`.** The direct form is right for widgets *inside*
  the row, but on the `<row>` element itself it is evaluated as a PHP expression and fails — Admin's
  converted index logged *"Error compiling PHP $status_class --> using it literally (Variable
  $status_class is not defined)"* on every load and put no class on the row at all, silently
  (an unstyled row looks like a styling problem, not a binding one). The bare-placeholder
  category-colour mechanism further down is the one exception, and it takes `$cat_id`-style names
  because `Et2RowProvider` matches them by name, not because the expression resolves.
- **`class="hide"` on a widget no longer survives `set_disabled(false)`.** Legacy's `set_disabled()`
  went through jQuery `.toggle()`, which writes an inline `display` that beats `.hide { display:none }`
  from `etemplate2.css`; `Et2Widget.set_disabled()` sets the `hidden` property instead, which can
  never out-specify a class still on the element. A widget hidden by both (Admin's groups nextmatch)
  becomes permanently invisible — replace the class with `disabled="true"`.
- Row-value binding syntax matters per-widget: `${row}[fieldname]` doesn't always work where the
  direct-binding form `$row_cont[fieldname]` does (Infolog) — if a bound value renders wrong or blank
  after confirming the field name is correct, try the direct-binding form before assuming the data
  itself is missing.
- **A row-scoped `class=`/`disabled=` expression on a widget *nested inside* a row template must use
  bare `$row_cont[fieldname]` (or `${row}[fieldname]`) — a legacy-looking `@@<nm-id>[$row][fieldname]`
  form silently resolves to the wrong thing for every row, with no error.** (Tracker: a conditional
  `disabled` pair meant to show one widget for real commenters and a fallback for system-generated
  ones showed the fallback for *every* row instead, only for `disabled=`; a `class=` binding using the
  same `@@`-form on a sibling widget failed the same way but silently — no visible symptom at all,
  since a missing class is much easier to miss than "wrong branch always active".) Root cause:
  `Et2RowProvider`'s prep-time rewrite (`_normalizeLegacyRowExpressionShorthand()`) only recognizes
  `$row_cont[f]` / `${row}[f]` / `{$row}[f]` / `$row.f` shorthands; anything else - including a
  `@@`-prefixed path someone hand-adapted from a *different*, working `disabled="!@@top_level_field"`
  example elsewhere in the same template (where `top_level_field` is genuinely top-level ticket
  content, not row-scoped) - passes through unrecognized. For a `disabled=` attribute specifically
  (a Boolean-typed property) this then fails a second, narrower regex
  (`Et2Datagrid._directBooleanRowValue()`, `^(!)?(?:\$\[path\]|\$field)$`) and falls through to a
  looser fallback that does a raw `$row` → row-uid text substitution instead of indexing into that
  row's own content - so the final `getEntry()` lookup always misses and always resolves the same way
  for every row, not correctly per-row. Don't adapt a working `disabled="!@@field"` example to a new,
  row-scoped field name without checking whether the original example's field was actually top-level
  content or row content - the correct row-scoped form for either `class=` or `disabled=` is always
  the same shorthand already used elsewhere for `id=`/`class=` in the same template
  (`$row_cont[fieldname]`), never a hand-built `@@`-prefixed path.
- Attributes that stop being used: `disable_selection_advance="true"` has no widget-level equivalent —
  implement the same "select the next/previous row after this one is removed" behavior in `app.ts` via
  the `et2-rows-deleted` event instead (see the replacement table below). `no_dynheight="true"` was
  also dropped without replacement in the one conversion that had it.
- **`<et2-nextmatch>`'s `header`/`footer` slots are inside its namespace.** Moving a widget into one
  of them (eg. while lifting the nextmatch out of a wrapper box) re-scopes it: `<et2-number id="percent">`
  starts reading `$content['nm']['percent']` instead of `$content['percent']` and renders blank, with
  no error (Admin's access log). That is right for a header template the app already treats as part of
  the nextmatch — legacy's `header_left` was in that namespace too, which is why
  `admin_cmds::remotes()` reads its Add button back as `$content['nm']['add']` — and wrong for
  anything the controller sets at top level, which stays a plain sibling of the `<et2-nextmatch>` tag.
- **`header_right="some.template.id"` (a template shown to the right of the header row) has no
  `Et2Nextmatch` property equivalent** — `Et2Nextmatch` doesn't expose a `headerRight`/`header_right`
  attribute at all. If there's room for it, replace it with the pre-existing, widget-independent
  `slot="main-header"` mechanism instead: keep the `header_right` template unchanged and add
  `<template template="that.template.id" slot="main-header"></template>` as a sibling of the
  `<et2-nextmatch>` tag (this is how Tracker and Filemanager's mobile skins place their own header
  content, though both had this pattern in place well before either app's own `<nextmatch>` tag was
  converted). On a
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
  row shadow DOM; add a `rows.css`/`rows.less` file per app for this if one doesn't already exist.
  There is no `app.css` fallback in the rows.
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
| `nm._get_autorefresh()` / `nm._set_autorefresh(0/n)` (pause auto-refresh during a long request) | No direct equivalent - `Et2Nextmatch` now has a built-in background autorefresh poll instead (see "Autorefresh" below), driven entirely by the same `nextmatch-<pref>-autorefresh` preference and `disable_autorefresh` setting, with no per-app API to call. There is still no way to pause it mid-request from app code; raise this if an app actually needs it. |
| `nm.controller._selectionMgr.resetSelection()` | `nm.clearSelection()`. |
| `nm.options.onselect = null` (temporarily suppress auto-preview-on-select) | `nm.addEventListener("et2-selection-changed", e => e.preventDefault(), {capture: true, once: true})`. |
| `et2_nextmatch.DELETE` constant | `Et2DatagridUpdateTypes.DELETE` from `Et2Datagrid.types`. |
| `nm.controller._indexMap` (which uids are currently loaded in *this* nextmatch instance, e.g. before deciding to `refresh()` one from a push notification) | `nm.getLoadedRowIds()` — row ids by index, `null` for indexes not loaded. Row *content* still comes from `egw.dataGetUIDdata(uid)`. Added with the `ExposeMixin` gallery fix above; Addressbook's `CRM.ts` still reaches into `_datagrid.rows` through a DOM query and should move to this. |
| `nm.controller._gridCallback(start, end)` (force-load a range of rows the user has not scrolled to) | `await nm.loadRowRange(start, end)` — resolves once the range is loaded, or once fetching stops making progress. |
| `nm.controller.getRowByNode(node)` / `entry.controller.getDepth()` | `nm.getRowByNode(node)` → `{id, depth}` or `null`; `depth` is 0 for a top-level row and counts up per level of expanded child grid. |
| `nm.update_in_progress` | `nm.isLoading`. |
| `this.nm.controller.getObjectManager()` | `egw_getObjectManager(appname).getObjectById(nm_index)` — grep the app for `.controller.` before considering it converted; every remaining hit is a crash waiting to happen. |
| jQuery `.on('refresh', (_event, _widget, _row_id, _type) => ...)` | `Et2Nextmatch.refresh()` dispatches a plain DOM `CustomEvent` with **no extra arguments** — `_widget`/`_row_id`/`_type` are always `undefined` now. Close over an already-captured reference instead of reading widget/row from the event. |
| Overriding `nm._create_print_dialog` to print without asking (preset columns / rows / orientation) | `nm.printOptions = {columns: "all" \| [keys], rowCount, orientation}` — no print dialog, nothing saved as print preference; options left out use the dialog's defaults. |
| Guessing at a renamed setting (e.g. `nm.settings.foldertree`) | Verify the replacement property actually exists on `Et2Nextmatch` (check `Et2Nextmatch.ts`) before using it. |

### Autorefresh

`Et2Nextmatch` has a built-in background autorefresh poll (added after the gap noted below was
identified), implemented as its own collaborator class, `Et2NextmatchAutoRefresh.ts`. Unlike this
directory's older collaborators (`Et2NextmatchActionController`/`Et2NextmatchDataProvider`/
`Et2RowProvider`, all manually wired via explicit calls from `connectedCallback()`/
`disconnectedCallback()`), it's a Lit `ReactiveController` - constructed once in `Et2Nextmatch`'s
constructor, registered via `host.addController(this)`, with `hostConnected()`/`hostDisconnected()`
called by Lit itself on every connect/disconnect cycle rather than by hand. Same pattern already
established by `Et2Ai/AiAssistantController.ts`. `restart()` is called from `_handleLoadingDone()`
after every (re)load:

- **Interval source**: the same preference legacy used, `nextmatch-<pref>-autorefresh` (seconds,
  `<pref>` = `Et2NextmatchAutoRefresh.preferenceBase` = `settings.columnselection_pref` if the app
  sets it, else the widget's own `template` attribute - matching `Nextmatch.php`'s own `'nextmatch-'
  . ($columnselection_pref ?? $template)` formula exactly, so admin-configured defaults/forced
  values keep working unchanged). `preferenceBase` is a small getter specifically so any further
  Et2Nextmatch-owned preference this class grows can key off the same base instead of each one
  inventing its own fallback - `Et2Nextmatch.ts`'s own `_lettersearchPreferenceKey` predates it and
  still has its own (subtly different - falls back to `columnPreferenceName`, not `template`)
  formula; left alone since changing it is a behavior change for existing installs, not something to
  fold in incidentally. Note this
  means an app whose `columnselection_pref` already includes a `nextmatch-` prefix (Infolog does,
  `class.infolog_ui.inc.php:1162`) ends up with a doubled `nextmatch-nextmatch-...` key - that
  matches what `Nextmatch.php` itself computes for the same app, so it's consistent, if odd; it's
  moot for Infolog anyway since `disable_autorefresh` is set. Also note the fallback uses the
  *widget's* `template` (set once from server attrs), not `columnPreferenceName`. An app whose
  row template varies per view, but which wants one interval for all of them, sets the
  `autorefreshPreference` attribute: it replaces `preferenceBase` for the autorefresh key only, so
  column preferences stay per template. Mail's `index.xet` does this (`mail.index.rows`, for its
  `mail.index.rows.vertical`/`.horizontal` templates), which is also the name its shipped default
  (`mail/setup/default_records.inc.php`) has always used.
- **Opt-out**: `disable_autorefresh` was added to `ALLOWED_SETTINGS` - Infolog/Timesheet/Invoices'
  existing `disable_autorefresh => true // we have push` now actually takes effect.
- **What a tick does**: `refresh(undefined)` - a full reload, same as the toolbar refresh action,
  exactly one `ajax_get_rows` request. Autorefresh exists specifically for instances where an admin
  has disabled push, so it has to catch new/removed/reordered rows too, not just changes to rows
  already loaded - the targeted per-row patch path (`refresh(ids, 'update')`, what push-driven
  single-row updates use elsewhere) would miss exactly what autorefresh is for. An earlier version
  of this used that per-row patch to avoid disturbing scroll position, but that fanned out into one
  `ajax_get_rows` request *per row* (`Et2NextmatchDataProvider.refresh()` calls `_refreshSingleRow()`
  once per id) and, more importantly, silently never surfaced new rows at all - wrong on both counts
  for the no-push case this feature is actually for.
- **Pause/resume**: two independent signals, both checked live via `Et2NextmatchAutoRefresh.shouldRun`
  rather than tracked as a fragile toggled flag - the `hide`/`show` native `CustomEvent`s the framework
  dispatches on the nearest `<egw-app>` ancestor when switching EGroupware app tabs
  (`kdots/js/EgwFramework.ts`'s `showTab()`), and the standard `document.visibilitychange` (covers
  the browser tab/window itself being backgrounded, and popups, neither of which legacy handled).
  Resuming does one immediate refresh (data may be stale) then resumes the interval.
- **Column-selection UI**: `api/templates/default/nm_column_selection.xet` already had an
  `autoRefresh` `<et2-select>` (dead in the modern flow before this), now wired end-to-end.
  `Et2Datagrid.openColumnSelection()` doesn't know about `autoRefresh` specifically - it passes
  through whatever the template returns generically, so the template can grow new fields without
  touching `Et2Datagrid.ts` again: `et2-column-selection-items`' `content` (an object listeners
  fill in by widget id to seed the dialog) and `et2-column-selection-apply`'s `values` (the dialog's
  full raw result, unfiltered). `Et2Nextmatch`'s column-selection handlers forward `content`/`values`
  to `Et2NextmatchAutoRefresh.seedColumnSelection()`/`.applyColumnSelection()`, which read/write
  `autoRefresh` and persist a changed value to the preference above. `openColumnSelection()`'s
  `et2-column-selection-items` event now also carries `modifications` and `sel_options` objects
  (same by-widget-id pattern as `content`, matching the standard eTemplate dialog `value` keys) that
  listeners fill in to reach into the dialog's widgets - eg. gray one out, hide it, or populate its
  options - without `Et2Datagrid` needing to know about any of them. `seedColumnSelection()` uses
  `modifications` to hide the `autoRefresh` select entirely for `disable_autorefresh` apps (there's
  nothing to configure, so unlike legacy's grayed-out-but-visible dialog it isn't shown at all)
  instead of silently accepting and dropping a submitted value.

- **Admin "save as default/force/reset"** (the `default_preference` select next to `autoRefresh`):
  was completely dead in the modern flow until fixed here (2026-09-01) - not just unreachable for
  `disable_autorefresh` apps, but non-functional for everyone, and visible to non-admins too. Two
  independent problems, both now fixed:
  - **Not admin-gated**: legacy's dialog hid this select via `readonlys: {default_preference: !apps.admin}`;
    the modern `.xet` field had no equivalent. `Et2Nextmatch._handleColumnSelectionItems()` now hides
    it via `modifications` (the same mechanism used for `disable_autorefresh` above) when
    `egw().user('apps')?.admin` is falsy.
  - **Selecting a value did nothing**: `Nextmatch::validate()` (~line 1347) has an equivalent
    save/force/reset block, but it's unreachable - `Et2Datagrid.openColumnSelection()`'s dialog never
    does a real form submit (`dialog.getComplete()` returns values purely client-side), so `validate()`
    never runs for this dialog. Even discounting that, `validate()` reads field names
    (`nm_col_preference`/`nm_autorefresh`) from the *original 2013* programmatic-widget implementation
    (commit `5e84ddd935`) that the 2022 static-template rewrite (commit `4318d1c0a5`) renamed to
    `default_preference`/`autoRefresh` without updating - and even if the names matched, it writes
    columns under the legacy `nextmatch-<pref>` comma-separated-name key, which
    `Et2Datagrid._loadColumnPreferencesIfNeeded()` never reads (it reads its own generated
    `<owner>-<rowTemplateId>-prefs` key, in a JSON array-of-`{key,width,hidden,customFields}` shape).
    Do not treat `validate()`'s block as a reference for what the client currently sends or what key
    columns belong under - it's vestigial.

    Fixed with a new, dedicated ajax method, `Nextmatch::ajax_set_admin_default($exec_id, $form_name,
    array $prefs, $action)` - admin-gated AND `exec_id`-gated (resolves the real widget/app via
    `Etemplate\Request::read($exec_id, false)` + `Template::instance()->getElementById()`, the same
    pattern `ajax_get_rows()` uses, rather than trusting a client-supplied app name). `$prefs` is a
    flat preference-name => value map built entirely client-side, since each preference's key/format
    is owned by whichever widget reads it back - not by this PHP method. `Et2Datagrid.openColumnSelection()`
    fires it only when `values.default_preference` is truthy (unlike legacy, which re-saved the admin's
    own selection as their personal preference on *every* submit regardless of what they picked), via
    a new private `_maybeSaveColumnSelectionAsAdminDefault()`, using `_columnPreferenceKeyValue()`
    (factored out of `_persistColumnPreferences()` so both write the exact same key/shape) for its own
    columns entry. Other widgets contribute their own key/value pairs through a third by-widget-id
    bucket on `et2-column-selection-apply`'s detail, `adminPrefs` (same pattern as `content`/
    `modifications`/`sel_options` on the `-items` event) - `Et2NextmatchAutoRefresh.applyColumnSelection()`
    adds the autorefresh interval, `Et2Nextmatch._handleColumnSelectionApply()` adds lettersearch
    visibility, both unconditionally (whether they're actually used is `Et2Datagrid`'s call). Tests:
    `Et2Datagrid.test.ts`'s "admin save-as-default action" describe block.

### Lazy loading a nextmatch that lives inside a tab

Added for Tracker's `replies` comments nextmatch (`tracker/templates/default/edit.xet`'s Comments
tab) - a nextmatch embedded in one panel of an `<et2-tabbox>` is otherwise loaded (server-side rows
baked into the page payload, or a client fetch) unconditionally on page load, whether or not the
user ever opens that tab. `Et2Tabs` renders every panel's full widget subtree eagerly at parse time
(`createTabs()`/`createPanel()`), hiding inactive ones with CSS only (`display:none`) - there is no
lazy-render support anywhere in the tab widget, and `Et2Nextmatch` itself has no panel-visibility
awareness at all (`firstUpdated()` unconditionally calls `_datagrid?.reload()` once template/columns
are parsed, regardless of the widget's own visibility).

Fix: a new `lazy` boolean property on `Et2Nextmatch` (`Et2Nextmatch.ts`, alongside `lettersearch`).
When set, `firstUpdated()` calls a new private `_whenLazyVisible()` before the client-fetch
`_datagrid?.reload()` call (only that branch - template/column parsing and the server-preloaded-rows
branch are untouched, so headers still render immediately even though row data is deferred).
`_whenLazyVisible()` no-ops unless the nextmatch is not currently being displayed, in which case it
returns `Et2LazyLoadController`'s `whenReady` - the controller `Et2LinkString` already uses to hold a
per-row request until the row is worth loading. It answers "is it displayed" from the element itself
(`checkVisibility()`, triggered by an `IntersectionObserver`), so it covers `display: none` anywhere
up the tree without the widget knowing what put it there: an inactive `<et2-tab-panel>` (Shoelace
gives it `display: none` unless `[active]`), a `disabled`/`hidden` widget, an app toggling between
its own views. Being scrolled out of view deliberately does not count as hidden, the same call
`Et2NextmatchAutoRefresh` makes. An earlier version listened for the enclosing `<et2-tabbox>`'s
`sl-tab-show` instead and so covered only tabs - the technique the legacy nextmatch
(`et2_extension_nextmatch.ts`) still uses, and which `Et2Historylog` had inherited from the legacy
history log it replaced. `Et2Historylog` has its own `lazy` (defaulting to **true**) and its own
copy of `_whenLazyVisible()`, since it is not an `Et2Nextmatch` subclass; it was widened the same
way in the same change, so a history log hidden by anything other than a tab panel now defers too -
and for it that covers the row template as well as the entries.

One consequence worth knowing when testing: `IntersectionObserver` callbacks are delivered with the
browser's rendering steps, which a backgrounded browser tab does not run - so a lazy nextmatch shown
while its browser tab is in the background waits for that tab to be foregrounded. Harmless in use
(nobody is looking, and autorefresh pauses on the same condition by design), but it makes an
automated repro on a `document.hidden` tab look stuck; see the note on forcing a frame with a
screenshot in `doc/ai/testing.md`.

**`num_rows => 0` on its own defers nothing — pair it with `lazy="true"`.** Under the legacy widget
an app could ship a nextmatch with no rows and load it on demand by dispatching a bubbling `show`
event at it (Admin's `group_list()` did exactly that, for the group list behind its tree).
`Et2Nextmatch` has no such listener, and `Nextmatch.php` sets `total` to `null` when `num_rows` is
0 — which `firstUpdated()` reads as "no data was sent yet", so without `lazy` it fetches
immediately anyway. `lazy` is not tab-specific: it defers until the nextmatch is actually being
displayed, so it covers a list hidden by `disabled`/`hidden` or by an app toggling its own views,
not just one on an unopened tab.

Usage: add `lazy="true"` to the `<et2-nextmatch>` tag. If the app also ships rows/`total` with the
initial page load (skip this if it doesn't, e.g. via a settings key like Tracker's own
`get_comment_rows`'s `num_rows`), set that to `0` (or otherwise suppress the server-side prefetch) too
- `lazy` only gates the *client* fetch fallback; a server that ships `total` server-side still takes
the immediate `storeRows()` branch in `firstUpdated()` and defeats the point. Don't set the
server-side prefetch to 0 without also setting `lazy="true"` - some apps' legacy comments about
"popup nextmatch needs num_rows set, client won't fetch" describe a real gap in the *old*
`et2_extension_nextmatch` widget's popup handling, not `Et2Nextmatch`'s `reload()`, which fetches
correctly in a popup as soon as `_whenLazyVisible()` resolves.

## Reference: settings allow-list

`Et2Nextmatch.settings` only keeps an explicit allow-list of keys from `$content['nm']`
(`Et2Nextmatch.ts`'s `ALLOWED_SETTINGS`); anything else sent by the app's PHP is silently dropped from
`.settings`, so legacy app code reading `nm.options.settings.<key>` for an arbitrary app-specific key
will get `undefined` after conversion even though the server sent it. In every conversion inspected so
far, the **`$content['nm']` array shape built server-side did not need to change** — the conversion was
template + app JS/TS only — but that's an observed outcome for four apps, not a guarantee; check
`ALLOWED_SETTINGS` if the app relies on a setting that isn't in that list.

**Do not read this backwards.** `ALLOWED_SETTINGS` governs what survives into the *client-side*
`.settings` object, and says nothing at all about settings the server consumes and never sends. The
clearest example is `no_filter`/`no_filter2`/`no_cat`, which are absent from the list *and* fully live —
`Nextmatch.php` reads them server-side while building the filterbox, and dropping them because "they're
not in `ALLOWED_SETTINGS` so they must be dead" silently changes which filters the app offers. See
[the filterbox and `filter-template.php`](#reference-the-filterbox-and-filter-templatephp) below before
deleting any `$content['nm']` key on allow-list grounds.

## Reference: the filterbox and `filter-template.php`

Where an app's filters actually come from under `Et2Nextmatch`, and the trap in the middle of it.

- **The filterbox needs no app markup.** `Et2Nextmatch._ensureFilterbox()` creates its own
  `<et2-filterbox slot="filter">` and appends it to the nearest ancestor exposing a `filter` slot —
  in practice `<egw-app>`, whose `EgwFrameworkApp._filterTemplate()` renders the drawer (plus the
  clear-filters and column-selection buttons) for any app whose page contains an `et2-nextmatch`.
  `getNextmatch()` queries `et2-nextmatch` first, so this works for converted apps.
- **Its contents are generated server-side, from the app's own row template.**
  `Nextmatch.php::beforeSendToClient()` (~line 314) builds a `filter_template` URL pointing at
  `api/filter-template.php/$app/templates/$template_set/$rows.xet?...`, unless the app set
  `filterTemplate`/`filter_template` itself. `api/filter-template.php` then regex-reads that `.xet` and
  emits a filter template containing: a searchbox (`et2-searchbox`, or the `rag.search` template where
  the app supports RAG), a "Sorting" select built from every `et2-nextmatch-sortheader`, the standard
  `cat_id`/`filter`/`filter2` controls (`et2-select-cat` for `cat_id` unless `cat_is_select` is passed),
  an `<et2-details summary="Column Filters">` holding the filtering headers — with ids rewritten to
  `col_filter[$id]`, because `et2-details` creates no namespace — and a favorites section if
  `favorites` was passed.
- **`no_filter`/`no_filter2`/`no_cat` are live server-side settings.** `Nextmatch.php` appends
  `&filter=`/`&filter2=`/`&cat_id=` to that URL *only* when the corresponding disable flag is empty, so
  each one suppresses its filter from the generated filterbox. Note the asymmetric name: `cat_id`'s flag
  is `no_cat`, not `no_cat_id`. They are missing from `ALLOWED_SETTINGS` because they never travel to the
  client at all — not because they stopped working. Conversely, **deleting `no_cat` is what makes a
  category filter appear**; `admin/inc/class.admin_categories.inc.php:607` does exactly that on purpose,
  with the reasoning in a comment on the line (`unset($content['nm']['no_filter']); // completely remove
  no_filter, so it shows up in the filter-template`). Only drop one of these when the app genuinely has
  something to offer in that slot — removing `no_filter` from an app with no `filter` options gives the
  user an empty select.
- **Only the `FilterMixin` headers feed Column Filters**: `et2-nextmatch-header-filter`,
  `et2-nextmatch-header-account`, `et2-nextmatch-header-entry`, `et2-nextmatch-header-custom`. Plain
  `et2-nextmatch-header` and `et2-nextmatch-sortheader` contribute a column label only — sortheaders
  feed the Sorting select instead. So the choice of header widget in the row template is also the choice
  of whether that column is filterable.
- **A toolbar filter is a separate, additional control, not the filterbox entry.** Addressbook, InfoLog
  and Timesheet each put an `et2-select-cat` in their `slot="main-header"` toolbar *and* get a category
  filter in the drawer from the mechanism above; both exist at once. Don't infer from "there's one in the
  toolbar" that the drawer has none — that misreading is what this section exists to prevent.
- **A nextmatch in a popup gets no filterbox at all.** `Nextmatch::beforeSendToClient()` returns
  early for `Api\Etemplate::$request->output_mode === 2`, before both the `filterTemplate` computation
  and the `no_search`/`no_filter`/`no_filter2`/`no_cat` suppression — correct while the legacy widget
  drew its own header bar, silently filter-less once converted. Put the controls the popup needs in
  the nextmatch's own `header` slot (they land in its namespace, so they read `$content['nm'][...]`,
  and `nm.getWidgetById()` finds them, which is how per-fetch `sel_options` still reach them). Note
  `EgwApp.changeNmFilter()` can not drive them: it resolves the nextmatch through `<egw-app>`, which
  a popup window has none of. See [Filters and the filter drawer](#filters-and-the-filter-drawer).
- **To replace the generated filterbox entirely**, slot a template as `slot="filter"`. Calendar is the
  only app currently doing this (`calendar/templates/default/filter.xet`), and it did so before its own
  conversion - so an app arriving with one of these needs no filter-drawer work at all. Filters can also be grouped
  under headings via `data="groupName:..."` on a nextmatch header. `Et2Filterbox.readNextmatchFilters()`
  — which collects the four filtering header tags client-side — is the other path, used when no
  filter-template is in play.

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
  instance** if the app has a template left unconverted — historically the Home favorite-portlet
  variant (Filemanager: `scheduleChangeViewButtonUpdate()` crashed on `nm.updateComplete.then(...)` —
  `updateComplete` is LitElement-only; the portlet templates are converted now, but the guard it needed
  is still in `filemanager.ts`). Grepping for `typeof nm\.` isn't a complete check; any
  assumed-modern-only property/method access on `nm` is a candidate.
- **Don't guess at renamed settings.** A removed widget property doesn't always have an obviously-named
  replacement (e.g. `nm.settings.foldertree` doesn't exist; the correct property for the current
  folder is `nm.activeFilters.selectedFolder`) — verify the property exists on `Et2Nextmatch.ts` before
  shipping.
- Auto-refresh pause/resume around long-running requests has no equivalent — call this out explicitly
  when converting an app that relies on it, rather than assuming it's covered.
- **`app.css` never reaches the rows.** Rows render in the datagrid's shadow root, and only the row
  template's `<et2-styles>` are adopted there - there is no `app.css` fallback. Any row styling an app
  kept in `app.less`/`app.css` silently stops applying once it converts: no error, everything still
  *loads*, and a subtle loss (bold vs. not) is easy to miss at a glance (Tracker: `tracker_unseen`/
  `tracker_seen` bold state, several priority-color classes, `tracker_overdue`, `private`/`planned`
  italics). Before considering an app's row CSS done, grep its `app.less` (and the mobile skin's) for
  every class used inside the row template and move those rules to `rows.less`. Drop any page
  container id scope (e.g. `#tracker-index .some-row-class`) on the way: that id only exists in the
  light DOM, and the shadow root already provides the isolation. Bare `<et2-styles src="...">` values
  resolve relative to the row template's own `.xet` file, so a mobile template's `rows.css` is the one
  next to it.
- **Greyed-out "no longer in effect" rows use the shared `rowInactive` class** instead of a rule of
  their own: have `get_rows()` add it next to the app's class (`'revoked rowInactive'`,
  `'rowDeleted rowInactive'`, `'policy_disabled rowInactive'`) and drop the app's grey/italic rule.
  Keep the app's class - actions' `enableClass`/`disableClass` and other code still key on it. The rule
  lives in `Et2Nextmatch.row.styles.ts`, so it only exists for `<et2-nextmatch>` rows, not legacy ones.
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
- **A converted list that shares its page with another widget wants `layout="stack"` on its
  template.** `Et2Datagrid` takes the full height it is offered and pushes its neighbour off the
  page, which shows up as a second, outer scrollbar (or a footer toolbar that looks simply missing).
  `layout="stack"` lays the template's direct children out as a flex column so the grid shrinks to
  fit — but note `grow="1"`, the attribute that marks which child takes the leftover space, does
  **not** reach the DOM from a `.xet` today, so the growing child still needs one CSS rule; see
  [Layout and template structure](#layout-and-template-structure) for the rule and the upstream fixes.
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
- **A toolbar control that mirrors an `nm` filter (details/no-details toggle, view-mode select, ...)
  must be explicitly synced from `nm.activeFilters` inside `et2_ready()` — nothing does this
  automatically.** If the app's filter-changed handler is guarded by "only act if `nm` and the widget
  argument are both truthy" and `et2_ready()` calls it with no arguments just to "initialize" the
  display, that call silently no-ops instead of syncing anything (Timesheet's `details` toggle called
  `this.filter2_change()` with no args at load, so the toggle's `.value` and the row-detail CSS custom
  properties were never set from the real, already-restored `filter2` filter). The generic `EgwApp`
  fallback (`checkNmFilterChanged`, itself only reachable a tick later via the deferred
  `nmFilterChange`) only catches this if it detects a genuine value mismatch after the fact, which
  produces a "works after one click" symptom instead of being correct from first paint. Infolog's
  `et2_ready()` (`infolog/js/app.ts:75-86`) is the reference pattern: read the real value off
  `nm.activeFilters.<key>` and pass it explicitly to both the CSS/style updater and the toolbar
  widget's own `.value` setter, synchronously, before the page is shown as ready.
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
- **A filter-changed callback that `.focus()`es a date field "to help the user start typing" can
  silently corrupt whatever filter state was just applied.** Timesheet's `filter_change()`
  (`timesheet/js/app.ts`) and Infolog's (`infolog/js/app.ts:234-257`) both reach this same shape:
  when the `filter`/time-range select transitions to a value that needs a custom date range, they
  open the filter drawer and call `.focus()` on the (still empty, at that point) start-date field.
  Focusing an empty `Et2Date`/flatpickr field can make it silently pick today and fire its own
  `change` event; `Et2Filterbox.handleFilterChange()` reacts to that by collecting every widget's
  current value and pushing the whole lot into `nm.applyFilters()` — clobbering real dates a
  favorite (or any other non-empty filter apply) had *just* set, since that correct value hadn't
  necessarily reached the drawer's own widget yet when the focus fired. It's a genuine race, not a
  one-off: `nmFilterChange`'s listener and `Et2Filterbox`'s own listener are both bound to the same
  `et2-filter` event with no guaranteed order, so whether the focus call runs before or after the
  drawer has synced its widgets from the new state depends on registration order and timing, not
  anything the callback controls. Fixed for both Timesheet and Infolog by checking
  `nm.activeFilters.<field>` (the authoritative, already-correct value at that point) before
  deciding to focus at all, and by tying the focus to `nm.updateComplete` instead of a bare
  `setTimeout`. That closes the *data* corruption (`nm.activeFilters` stays correct), but a smaller,
  separate issue remains in both apps: the filter drawer's own Start/End widgets can still show a
  stale value in this same sequence even though the underlying query data is right — that's
  `Et2Filterbox`'s own widget sync not landing a value into its own widget in this specific
  reset-then-restore path, not a `filter_change()` timing problem, and is unfixed. More generally:
  **when converting an app, audit every existing
  filter-changed callback (`filter_change()`, `nmFilterChange()` overrides, anything hung off
  `checkNmFilterChanged()`) for side effects — `.focus()`, `.set_disabled()`, or any other call that
  touches a widget — that assume the *current* widget-tree state is already settled.** Before
  conversion, such a callback usually only ran in response to genuine user interaction (the widget
  the user just touched already has the right value). After conversion, the same callback can now
  also fire as a reaction to a programmatic state change (a favorite, "No filters", a saved view)
  where the rest of the widget tree may not have caught up yet - a case that either didn't exist or
  behaved differently under the legacy nextmatch's own filter-sync plumbing.

## Appendix: removing the legacy widget

Notes for whoever deletes `et2_extension_nextmatch*.ts`.

### Legacy-only interfaces: `et2_INextmatchHeader` / `et2_INextmatchSortable`

Both interfaces stay in `et2_extension_nextmatch.ts` and **are deleted along with it** - they are
the legacy widget's contracts with its headers, and `Et2Nextmatch` drives neither. Verified, not
assumed:

- `Et2Nextmatch.ts` never mentions either interface, and never calls `implements()` or
  `iterateOver()` at all. It finds its sort headers by tag name
  (`querySelectorAll("et2-nextmatch-sortheader")`) and duck-types `setSortmode()`.
- `setNextmatch()` has exactly one caller in the whole tree, in `et2_extension_nextmatch.ts`
  itself. Every `setNextmatch()` implementation on an `Et2Nextmatch/Headers/*` widget, and the
  `nextmatch` field each keeps, exists only so that header still works inside a legacy
  `<nextmatch>`.
- `et2_INextmatchSortable` has no consumer outside the legacy widget whatsoever.

**They were deliberately not moved to a neutral module**, because moving them buys nothing. Seven
of the eight `api/js` files that import them use them only in an `implements` clause, and the
project's Babel pipeline (`@babel/preset-typescript`) erases an import that survives only in type
positions - those files emit no import statement at all, so they never depended on the legacy
module at runtime. (The same is true in reverse: `et2_extension_nextmatch.ts`'s own
`import {Et2Filterbox}` is a type annotation plus a type *assertion*, so it is erased too.)

The one exception was `Et2Filterbox`, whose `originalWidgets="replace"` branch used the `const` as
a **value** - and that single import was enough to pull the entire ~4600-line legacy module in
wherever a filterbox loads. Since `implements()` takes a plain string (see
`et2_core_inheritance.ts`), that is now written as `widget.implements("et2_INextmatchHeader")` with
no import, which is the whole of the real decoupling here. The registry entry it looks up is
registered by `et2_extension_nextmatch.ts`, which `etemplate2.ts` always loads.

**When the legacy widget is deleted**, delete with it: both interfaces and their
`et2_implements_registry` entries; the `implements et2_INextmatchHeader` /
`implements et2_INextmatchSortable` clauses and `setNextmatch()` implementations on
`Headers/Header.ts`, `Headers/FilterMixin.ts`, `Headers/FilterHeader.ts`,
`Headers/AccountFilterHeader.ts`, `Headers/EntryHeader.ts`, `Headers/SortableHeader.ts` and
`Et2Favorites.ts`; `Et2Filterbox`'s `implements("et2_INextmatchHeader")` check (note that the
`"replace"` case then falls through to `"delete"`, which is the intended end state - the widgets
it finds by tag name are header widgets by construction, so the check only ever did real work for
widgets scraped out of the legacy header bar); and `Et2Filterbox`'s local
`LegacyNextmatchInternals` type with the `header` / `template_promise` guards that use it.

What does **not** go is `Et2Nextmatch/NextmatchInterfaces.ts` - see below.

### `NextmatchInterface`: what the widgets around a nextmatch may rely on

`api/js/etemplate/Et2Nextmatch/NextmatchInterfaces.ts` holds `NextmatchInterface` and
`NextmatchActiveFilters`, the shape a header, filter or favourite widget sees. Both
`et2_nextmatch` and `Et2Nextmatch` declare `implements NextmatchInterface`, so anything added to
it has to exist on both.

This replaced `et2_nextmatch` type annotations in `Headers/Header.ts`, `Headers/FilterMixin.ts`,
`Et2Favorites.ts` and `Et2Filterbox.ts`. Those annotations were not merely a needless import -
they were **wrong**: `Et2Filterbox` genuinely drives both widgets, duck-typing `getDOMNode()`,
`getChildren()` and `updateComplete`, and carried six `@ts-ignore`s papering over the mismatch.
Three of those are now gone, along with two real pre-existing errors (`activeFilters.sort` did not
exist on the legacy `ActiveFilters` type, which this replaced).

**Re-export it with `export type`, not a plain `export`.** `NextmatchInterface` and
`NextmatchActiveFilters` are types, so Babel strips them and the compiled module has no runtime
export of either name; `et2_extension_nextmatch.ts` re-exporting them as values fails the rollup
build with *"'NextmatchInterface' is not exported by ... NextmatchInterfaces.ts"*. `npm run
typecheck` does **not** catch this - tsc is happy, because in TypeScript's view the names do exist.
Only a real `npx rollup -c` finds it, which is worth remembering for any future type-only module.

Two things to know if you extend it:

- `NextmatchActiveFilters` is a **type alias, not an interface**, on purpose. Only aliases get
  TypeScript's implicit index signature, and without it the two implementations - one typed with
  named filter keys, one returning a plain `Record<string, any>` - cannot both satisfy it without
  a cast.
- `options` is on the interface but marked `@deprecated`. Both widgets have it (the webComponent
  via `Et2Widget`'s compatibility getter, which rebuilds it on every access and logs a
  deprecation trace), and a couple of callers still index it by name. Prefer real properties.

### `ExposeMixin` and the row API it uses

`api/js/etemplate/Expose/ExposeMixin.ts` (the gallery behind `Et2VfsMime`, `Et2Link`,
`Et2LinkList`, `Et2ImageExpose`, `Et2DescriptionExpose`) finds its nextmatch by walking the composed
DOM up through shadow roots to `<et2-nextmatch>`; the widget tree can't be used, because row widgets
have no widget-tree parent. It remembers the nextmatch for the life of the gallery
(`_gallery_nextmatch`), since a reload can detach the row it was opened from. The gallery is still
restricted to Filemanager on purpose. It no longer imports the legacy widget or dataview.

It replaced the legacy internals with this public API on `Et2Nextmatch`/`Et2Datagrid`:

| Legacy internal | New API |
|---|---|
| `nm.controller.getRowByNode(node)` (+ `entry.controller.getDepth()`) | `nm.getRowByNode(node)` → `{id, depth}` or `null`. `depth` is 0 for a row of the nextmatch's own grid and counts up per level of expanded child grid, which is what the old `getDepth() > 0` check meant. |
| `nm.controller._indexMap` | `nm.getLoadedRowIds()` / `Et2Datagrid.getLoadedRowIds()` → row ids by index, `null` for indexes not loaded. Row *content* still lives in egw's UID cache (`egw.dataGetUIDdata()`); this is only the index → uid mapping. |
| `nm.controller._gridCallback(start, end)` | `nm.loadRowRange(start, end)` / `Et2Datagrid.loadRowRange()` - loads an index range the user has *not* scrolled to, without moving the grid, and resolves when the range is complete (or when fetching stops making progress). Unlike the legacy callback it is awaitable, so the caller reads rows that actually arrived instead of whatever happened to be cached. |
| `nm.controller._grid.getTotalCount()` | `nm.totalCount` (already existed). |
| `nm.update_in_progress` | `nm.isLoading`. |

Known rough edge: `set_slide()` drops the last row of a paged-in range, so the final image of a large
folder can be missing (untouched legacy gallery code).
