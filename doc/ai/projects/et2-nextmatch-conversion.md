# Converting an app from `et2_extension_nextmatch` to `Et2Nextmatch`

A checklist for moving one app's list view from the legacy `nextmatch` widget
(`api/js/etemplate/et2_extension_nextmatch.ts`, tag `<nextmatch>`) to the `Et2Nextmatch` web
component (`api/js/etemplate/Et2Nextmatch/Et2Nextmatch.ts` + `Et2Datagrid.ts`, tag
`<et2-nextmatch>`). For widget usage (attributes, row bindings, styling, row expansion), see the
generated component docs for
[`et2-nextmatch`](https://etemplate.egroupware.org/components/et2-nextmatch/) and
[`et2-datagrid`](https://etemplate.egroupware.org/components/et2-datagrid/) — this document does not
repeat that reference material.

Apps converted so far: Addressbook, Infolog, Filemanager, Mail, Timesheet, Tracker, Home, Calendar,
ProjectManager. Apps still on the legacy widget: Admin, Importexport, Aiassistant, Preferences.
Non-core apps (Resources, News_admin, Smallpart, Schulmanager, Stylite, Kanban, ...) have never been
on this list at all and still need one. Related in-flight/reference docs in the same directory as the
widget source: `ColumnSelectionNotes.md`, `Et2DatagridDirectoryMigrationPlan.md`, `NestedExpansion.md`.

Home's conversion is the favourite portlet (`home/templates/default/favorite.xet` +
`Et2PortletFavorite.ts`), and with it every app's favourite-portlet row template — see
[The Home favourite portlet](#the-home-favourite-portlet) below, which is where the other apps'
portlet row templates are now covered.

Addressbook's conversion covers every *reachable* list view the app itself owns: the main index
(including its mobile skin), the org/duplicate grouped views, the CRM popup (`CRM.ts`), and the
contact-picker popup. `index.rows.xet`'s Home-favorite-portlet variant came later, with Home's own
conversion. One addressbook-owned template is still deliberately on the legacy widget:

- `display.xet` (the Sitemgr "display" module, `class.addressbook_display.inc.php`) — this is a CMS
  content-block view, only reachable when the `sitemgr` app is installed and a page/module is
  configured to embed it. On an instance without `sitemgr` installed there is no way to load or
  browser-verify this template at all (the menuaction silently redirects to Home instead of erroring),
  so it was converted once, found untestable, and reverted rather than ship an unverified change to a
  template with no test coverage. Convert it for real only alongside access to an instance that has
  `sitemgr` installed and a page configured to use the module.

Filemanager's conversion covers the main index (desktop + mobile skin), the tile view, the background
jobs list (`jobs.xet`), and the shares list (`shares.xet`). `home.rows.xet` (the Home favorite-portlet
variant) came later, with Home's own conversion.

Timesheet's conversion covers the main index (desktop + mobile skin). `index.rows.xet` (the
Home-favorite-portlet variant, rendered through `timesheet_favorite_portlet.inc.php`) came later, with
Home's own conversion.

Tracker's conversion covers the main index (desktop + mobile skin), the admin Escalations list
(`escalations.xet`), and the comments/replies list embedded in the edit popup (`edit.xet`'s
`tracker.edit.comments`/`tracker.edit.comment_row`, id `replies` — desktop only, the mobile edit
template renders comments as a static loop with no nextmatch at all). `index.rows.xet` (the
Home-favorite-portlet variant, rendered through `tracker_favorite_portlet.inc.php`, same `tracker.index.rows`
template id as the real index but a separate file) came later, with Home's own conversion. The comments nextmatch also uses
`lazy="true"` (see [Lazy loading a nextmatch that lives inside a tab](#lazy-loading-a-nextmatch-that-lives-inside-a-tab))
so it doesn't fetch until the Comments tab is actually activated.

Tracker had 4 `<et2-box class="action_popup prompt">` mass-action popups (`admin_popup`, `link_popup`,
`assigned_popup`, `group_popup` in `templates/default/index.xet`). 3 of them (`admin_popup`,
`assigned_popup`, `group_popup`) were converted to real `<et2-dialog>` elements per the action item
below — all verified live against `nathan.egroupware.org`: `admin_popup`'s Update (real field change
confirmed via `tr_modified`), `assigned_popup`'s Add/Delete (confirmed via `egw_tracker_assignee`
rows), and all three dialogs' Cancel buttons (close without submitting, dialog stays reusable —
`destroyOnClose="false"`). `group_popup`'s Ok button was also verified to submit the correct payload
and take the correct code path, but its actual database write is blocked on this instance by a
genuine, pre-existing, unrelated site configuration: `egw_config` has `tracker/field_acl` saved with
`"tr_group":0`, i.e. this instance's admin has deliberately set the `tr_group` field ACL to "nobody
may edit it" for the whole app, regardless of admin/technician status. `tracker_bo::readonlys_from_acl()`
short-circuits on `!$rights` before ever calling `check_rights()`, so `is_admin()`/`is_technician()`
being `true` is irrelevant once the field-level ACL itself is `0` — this is not a bug, just a config
value that predates (or intentionally disables) mass group-reassignment. Not fixed, not in scope.

The 4th, `link_popup` (a picker to link/unlink selected entries to one target entry via the generic
Link registry), was **deleted outright** instead of converted — see
[Before converting a `link_popup`-style action, check for the auto-added "Link" action](#before-converting-a-link_popup-style-action-check-for-the-auto-added-link-action)
below. Removed: the `<et2-dialog>` (never built, since the plain-box version was deleted directly),
the `'link'` entry in `tracker_ui`'s action-tree (under `change.children`), the `case 'link':` block in
`tracker_ui::action()` (which called `Link::link()`/`Link::unlink()` directly, bypassing per-entry
edit-rights checks that the generic action's `Widget\Link::ajax_link()` does perform via
`checkLinkAccess()` — a small permissions gap fixed as a side effect of the removal, not the reason for
it), and `'link'` from `index()`'s `in_array($multi_action, [...])` composite-action-string list. Live
re-verified after removal: the generic "Link" context-menu item still appears and links/unlinks
correctly (`egw_links` rows confirmed appearing/disappearing), `assigned_popup` Add/Delete still works
(same shared composite-action-string code path), and no new console errors.

Converting `assigned_popup` also surfaced a real sync bug between the filter drawer's `tr_tracker`
control and the toolbar's `tr_assigned` picker (the picker needs to know which tracker queue is active
to offer the right assignee list, but only the toolbar's own `tr_tracker` control used to update it —
the drawer's copy, which shares the same widget id, didn't). Fixed by adding a `case 'tr_tracker':` arm
to `app.ts`'s existing `checkNmFilterChanged()` — the generic handler that already fires for every
`col_filter` key change regardless of which physical control (toolbar or drawer) changed it, since both
write through the same shared id and the same `et2-filter` event. No new wiring was needed; the sync
bug was really just a missing case in code that already ran on every relevant change.

## ProjectManager

ProjectManager's conversion covers all three of its list views - the project list
(`projectmanager.list`), the element list (`projectmanager.elements.list`) and the pricelist
(`projectmanager.pricelist.list`) - in both the default and mobile skins.
`templates/default/list.rows.xet` (the Home favourite-portlet variant) came earlier, with Home's own
conversion. It is the first app converted that shows **several nextmatches on one page**, which is
where most of what follows comes from.

- **An app that keeps more than one nextmatch alive has to mark the inactive ones' filterboxes
  `hidden`, and nothing does that for it.** ProjectManager loads all four of its views at once and
  swaps which one is displayed, so three `Et2Nextmatch`es exist side by side. Each builds its own
  `<et2-filterbox>` and appends it to the nearest ancestor offering a `filter` slot
  (`_ensureFilterbox()`), which for all three is the same `<egw-app>` - and the drawer's body is a
  bare `<slot name="filter">`, so all three showed at once, stacked, with nothing saying which
  belonged to the list on screen.

  The app-side hook for "which nextmatch is current" already exists and ProjectManager already had
  it: `EgwFrameworkApp.getNextmatch` is a `@property({type: Function})` an app overrides, and Admin
  overrides it the same way (`admin/js/app.ts`, keyed off its tree selection). **That hook is not
  enough**, because it only feeds the drawer's label/icon, the column-selection button and the
  `filters` getter - not what is *in* the drawer. Admin never hit this at all: `admin.index` is
  still on the legacy `<nextmatch>`, which keeps its filters in its own header bar and never puts
  an `<et2-filterbox>` in the app's filter slot.

  **Use the `hidden` attribute, not CSS.** `EgwFrameworkApp.filters` is
  `querySelector("et2-filterbox:not([hidden],[disabled])")` - that selector *is* the contract for an
  app with several filterboxes, and it is also what the "Clear filters" button, the filter-set icon
  and `getFilterInfo()` read. Hiding the inactive boxes with `style.display` instead satisfies the
  eye and not the getter: verified live that the project list was on screen while `filters` resolved
  to the element list's box, so all three of those read the wrong filterbox. Note the filterbox
  needed `:host([hidden]) { display: none }` added (`Et2Filterbox.styles.ts`) before the attribute
  did anything visually - its existing author-origin `:host { display: block }` beats the UA's own
  `[hidden]` rule.

  **Re-run the sync when a filterbox appears, not only on the view switch.** A nextmatch does not
  create its filterbox until its filter template arrives, which is after the switch that should have
  hidden it - so a one-shot pass leaves any view that was never displayed unhidden. ProjectManager
  uses a `MutationObserver` on the `<egw-app>` node for this.

  **The drawer's row count goes stale across a view switch too.**
  `EgwFrameworkApp.handleSearchResults()` correctly only takes a count from the current nextmatch,
  but switching views produces no new search result - the list being shown already has its rows - so
  the heading keeps counting the view you came from ("Filters: 3 entries" over a 5-row project
  list). The app has to set `<egw-app>.rowCount` from the now-current nextmatch's `totalCount`
  itself.

- **A legacy widget the server rewrites via a `type` modification renders nothing inside a row, and
  registering the tag client-side is the fix.** ProjectManager's `<projectmanager-select-erole>` has
  no client-side implementation at all: `projectmanager_etemplate_widget` (an
  `Etemplate\Widget\Transformer`) maps its type to `et2-select`, which reaches the browser as a
  `type` entry in the modifications array. Both `et2_core_widget`'s `createElementFromNode()` and
  `Et2Widget.loadFromXML()` honour that entry *before* looking for a custom element, so outside a
  row it has always resolved to `et2-select` - but `Et2RowProvider._cloneElement()` builds a row by
  cloning elements **by tag name** and never consults modifications, so in a row the untouched tag
  reached `document.createElement()` as an unknown element: in the DOM, inert, rendering nothing,
  with no console warning. Registering the tag as a real custom element
  (`projectmanager/js/ProjectmanagerSelectErole.ts`, extending `Et2Select`) fixes the row case and
  changes nothing elsewhere, because the type modification still wins outside rows. Register a
  `<tag>_ro` variant alongside it - `_cloneElement()` swaps any row widget for one when it exists,
  and that is what a read-only row cell actually gets. **Grep an app's row templates for tags with
  no `customElements.define()` before considering it converted**; this failure mode is completely
  silent.

- **`<progress>`: the trailing `%` depends on the field.** Per the `<progress>` entry in the rename
  patterns below, the value binding goes in `value=`/`title=`. ProjectManager needed
  `title="$row_cont[pm_completion]%"` for the project list but `title="$row_cont[pe_completion]"`
  for the element list - `pm_completion` arrives as `"7"` and `pe_completion` as `"7%"`. Check the
  real row data rather than copying the sibling template; `value=` is unaffected either way, since
  the HTML float parser stops at the `%`.

- **`add_existing` was a real `action_popup` and was converted, not deleted.** Unlike the
  `link_popup` case below, `projectmanager_elements_ui::action()`'s `'add_existing'` links the
  *picked* entry to the project the list is showing, ignoring the selected rows entirely - the
  generic "Link" action does the opposite (links the selected rows to a picked target), so it is not
  a replacement. Converted to a real `<et2-dialog>` with an `<et2-box id="add_existing_popup">`
  inside it for the namespace and the buttons in the `footer` slot, exactly as Tracker's
  `admin_popup`. The app's own `app.less` had `#projectmanager-elements-list_add_existing_popup
  {display:none}` left over from the box version, which silently collapsed the new dialog's body to
  zero height - **grep the app's CSS for the popup's id when converting one of these.**

- **Three framework bugs this app's first review found, all of them things another conversion will
  hit too.** (1) An `egw_open` action whose spec names no app - `'egw_open' => 'edit-'`, meaning
  "take the app from the id", which is what a list holding rows from several apps uses - was a
  silent no-op: `Et2NextmatchActionController.executeEgwOpenAction()` bailed on `if(!type || !app)`,
  where the legacy `nm_action()` had no guard and `egw.open()` documents an empty app as supported
  (it splits an `"<app>:<id>"` id itself). (2) The column-selection dialog listed a column by
  `header.textContent`, preferred over `column.title` - fine until a header cell also holds a
  widget showing data, at which point a column called "Status" is offered as "6.7%", because the
  cell's rendered text is the column total sitting next to the sort header. (3)
  `Et2DatagridPrintController.syncPrintFlowHeight()` pinned the printed row block to
  `tbody.scrollHeight`, which counts the deliberate 250mm `padding-bottom` the print stylesheet adds
  as somewhere for the last row to overflow into - declaring 4 rows that occupy 212px as a 1157px
  block, roughly a page taller than they are, to be fragmented as though it had that much content.

- **The mobile elements and pricelist templates had the nextmatch inside a `<grid>`**, the blocker
  described in Calendar's section below. Both lifted to direct children of the index template,
  following Addressbook's converted mobile skin, which keeps its own trailing `legacy_actions` grid
  as a sibling.

## Calendar

Calendar's conversion covers its only list view, `calendar.list` (desktop + mobile skin).
`calendar/templates/default/list.rows.xet` (the Home favourite-portlet variant) came earlier, with
Home's own conversion. Things specific to this app, most of which generalise:

- **Take the `<et2-nextmatch>` out of any wrapping `<grid>` - it must be a direct child of the
  index template.** Legacy `<nextmatch>` sat happily in a grid cell; `Et2Nextmatch` can not. A grid
  is a real `<table>`, and a table cell does not constrain its child's height, so `Et2Datagrid` never
  gets a bounded box to scroll inside: it grows to fit every row (456575px for 4565 rows here), the
  page scrolls instead of the grid, and the virtualizer renders the whole result set as empty
  placeholder rows. The legacy `grid` widget can not adopt a web component as a child either -
  `Legacy widget grid[#] could not handle adding a child (ET2-NEXTMATCH)` in the console is the
  same problem announcing itself. Every already-converted app has the nextmatch as a direct child
  (`timesheet.index` is the clearest example); calendar's `calendar.list` and its mobile skin both
  had to lose their wrapper grid, with the `css`/`msg`/`plus_button_container` widgets that shared it
  becoming direct children carrying their own `disabled=` instead of relying on a `<row disabled=>`.
  **Check for this before assuming a conversion is done: the symptom is a list that does not scroll,
  which is easy to mistake for a styling problem.**
- **The app already had a hand-written filterbox, so the filter drawer needed no work at all.**
  `calendar/templates/default/filter.xet` is an `<et2-template slot="filter">` holding its own
  `<et2-filterbox>`, exec'd as a separate etemplate by `calendar_ui`, and `app.ts`'s
  `_setupFilterTemplate()` hands it the nextmatch once the list has loaded. `filter_template=""` on
  the nextmatch tag suppresses the generated one. All of that predates the conversion and kept
  working against `Et2Nextmatch` unchanged - `Et2Filterbox` handles either widget.
- **`header_right`/`header_left` templates move into the nextmatch's own `header` slot**, as
  `<et2-template id="<template.id>" slot="header"></et2-template>` children of `<et2-nextmatch>` -
  the same mechanism `Et2PortletFavorite.applyHeaderTemplate()` uses, and the alternative to the
  `slot="main-header"` route the reference section below describes. Note `<et2-template>` takes the
  template name in **`id`**, not `template`, and `getWidgetById('<that id>')` then still finds it, so
  existing app code that reaches for the header template by name keeps working (calendar's
  `filter_change()` disables `calendar.list.dates` unless the date range is "custom").
- **The delete/undelete `action_popup` boxes were dead markup and were deleted, not converted.**
  Both `delete_popup` and `undelete_popup` (the "this recurrence or the whole series?" prompts) were
  still in `list.xet`, styled hidden by `#calendar-list_delete_popup { display: none }` in `app.less`,
  with buttons wired to the legacy `nm_submit_popup`/`nm_popup_action` globals - but nothing reaches
  them any more: both the `delete` and `undelete` actions have had
  `onExecute => javaScript:app.calendar.cal_delete` for a long time, and that runs
  `et2_calendar_event.recur_prompt()` (a real dialog) plus an ajax call instead. Before converting an
  app's action popups, check the action tree for an `onExecute` that has already replaced them.
- **An `onExecute` handler that hands its action to the nextmatch has to be checked against the action
  manager it actually came from.** Calendar has two: `ical()` and `cal_fix_app_id()`, both replacing
  `nm_action(_action, ...)` with `nm.executeAction(id, {ids, all})`. `executeAction()` re-resolves the
  action *by id* from the nextmatch's own manager, which matters because `calendar_uiviews` builds a
  second action tree for the non-list views out of the same `calendar_uilist::get_actions()` array and
  swaps some `onExecute`s. `ical()` only ever runs from the other views (so `_action` is a foreign
  object, and re-resolving is exactly right); `cal_fix_app_id()` only ever runs from the list (so
  `_action` *is* the nextmatch's own object, and the url it patches onto `_action.data` survives into
  the execute). Get this backwards and a url rewrite silently goes nowhere.
- **Setting the sort separately from the filter apply is the "rows fetched but never shown" bug.**
  `filter_change()` used to call `nm.sortBy('cal_start', asc, false)` right after `update_state()` had
  called `nm.applyFilters(state.state)`. Under `Et2Datagrid` that mutates `_filters` while the apply's
  own fetch is in flight, changing the query signature, and the response is dropped as superseded with
  no re-fetch (see the pitfall entry below). Fixed by folding `sort: {id, asc}` into the same object
  passed to `applyFilters()`. **Any app whose filter-change handler also sorts needs this check.**
- **`app.ts` code hung off a legacy setting round-trip can be dead already.** Calendar assigned
  `nm.set_startdate`/`nm.set_enddate` to keep `state.first`/`state.last` in sync with what
  `get_rows()` computed. Those setters are only ever called through `etemplate2`'s `assign` JSON
  plugin (`widget['set_' + key](value)`), and **no PHP in the tree calls `Api\Json\Msg::assign()`** -
  so they had been dead for as long as that was true. Removed rather than ported. Worth grepping for
  `assign(` before porting any `set_<something>` the app defines on a widget rather than calls.
- **The app-level autorefresh preference key was wrong, and converting surfaced it.**
  `_set_autorefresh()` read `"nextmatch-" + nm.options.settings.columnselection_pref + "-autorefresh"`,
  but calendar sets no `columnselection_pref`, so the key was literally
  `nextmatch-undefined-autorefresh` and never matched what the column-selection dialog writes. Both
  `Nextmatch.php` and `Et2NextmatchAutoRefresh` use `columnselection_pref ?? template`; app code that
  reads the same preference must use the same fallback (`nm.settings.columnselection_pref ||
  nm.template`). Calendar's own timer now only covers the non-list views - the listview's nextmatch
  autorefreshes itself.
  - This surfaced a gap in `Et2NextmatchAutoRefresh`, **fixed generically rather than per-app**:
    legacy could stop the nextmatch's own poll while the listview sat hidden behind another calendar
    view (`nm._set_autorefresh(0)`), but the controller only paused on the `<egw-app>` tab's
    `hide`/`show` and on `document.visibilitychange` - neither of which fires when an app swaps
    between two of its *own* views in one tab, or for a nextmatch in an inactive `<et2-tab-panel>` or
    a collapsed section. `shouldRun` now also requires the host to actually be rendered
    (`getClientRects().length` - not `offsetParent`, which is also `null` for a visible
    `position: fixed` element), with a `ResizeObserver` on the host as the trigger, since that is the
    one observer that reports a 0x0 box for a `display: none` element **without** also firing for a
    grid merely scrolled out of the viewport, which should keep refreshing. The immediate
    catch-up refresh a resume does is now gated on a timer having genuinely been armed before
    (`hasRun`), so a grid that was simply not rendered yet at its first load does not stack an extra
    full reload on top of the load it just did. Tests: `Et2NextmatchAutoRefresh.test.ts`.
- **The sidebox "Documents" select was removed rather than repaired.** It had been dead in the list
  view since `d24ca39d09`: `sidebox_merge()` looked up `'document_' + widget.getValue()`, one action
  per document, which is how `Merge::document_action()` built them until that commit replaced the
  nested per-document menus with the file-selection dialog. There has been exactly **one** merge
  action with no children ever since, so the lookup returned `null` and the guard around it silently
  did nothing. Repairing it also could not restore the old behaviour - `_getMergeDocument()` always
  opens the dialog and has no pre-selection path, so the picked document could not be passed through
  and the select would have become a bare trigger. Rows already carry the generic "Insert in
  document" action, so the select, `sidebox_merge()`, and the `$sel_options['merge']` that fed it are
  all gone. Note this does remove "merge the visible timespan" from the non-list views, where that
  path did still work; the per-entry context-menu action is what remains.

  Two things worth carrying to the next app. `Merge::document_action()`'s docblock still describes
  the pre-2024 submenu-by-mime behaviour and its `$prefix`/`$default_doc` parameters are now unused,
  so surrounding code reads as if per-document action ids still existed - check any merge call site
  for that shape. And **don't reach into an `Et2Nextmatch`'s actions to run one.** The obvious port of
  `nm.controller._actionManager.getActionById(id)` is to walk the global registry the way
  `Et2NextmatchActionController.ensureActionManagers()` builds it (app manager -> a child named after
  the etemplate's `uniqueId` -> a child named after the widget's `id`). It works, but it hands app
  code a live, mutable `EgwAction` and silently returns `null` the day that nesting changes.
  `Et2Nextmatch` deliberately exposes no accessor for its action manager, and adding one was
  considered and **rejected** by the project owner - not wanting to make actions easier to mess with
  is the point, not an oversight. `Et2Nextmatch.executeAction()` is not the alternative either: it
  runs the framework's own default execute (`executeNextmatchAction()`, the `nm_action` switch) and
  deliberately skips the action's `onExecute`, so it is the right replacement for a url/submit action
  like `ical` and the wrong one for anything whose behaviour lives in a JS handler. Where an app
  genuinely has to drive such an action, pass a plain action-shaped literal describing the work -
  the shape `smallpartApp.mergeVideo()` uses - rather than fetching the real one.
- **A CSS rule that hides something inside a web component stops working once that widget moves its
  content into a shadow root.** `filter.xet` hid an `<et2-iframe>` - the fallback target
  `CalendarApp.linkHandler()` uses for calendar urls that are not one of the ajax views - with
  `#calendar-filter_calendar-filter iframe { display: none; }`. The real `<iframe>` now lives in
  `Et2Iframe`'s shadow root, which that light-DOM descendant selector can never reach, so a large
  empty box sat in the filter drawer. Fixed by giving the widget `disabled="true"` (what
  `admin.index`'s identical iframe does) rather than styling it. Note the fallback itself is
  separately broken and was left alone: `linkHandler()` looks the iframe up via
  `this.sidebox_et2.getWidgetById('iframe')`, but it lives in `calendar.filter`, a different
  etemplate, so the lookup returns null and the branch bails out.
- **An app whose filters are never empty needs its own `getFilterInfo`, or the filter button stays lit
  forever.** `EgwFrameworkApp._filterTemplate()` picks the icon by running the *filterbox's* value
  through `filterInfo()`, which shows `filter-circle-fill` if any value survives a plain truthiness
  check (`sort` and `search_type` are the only keys it drops). Calendar always has a date range
  (`filter`, never blank - a list of events covers some span, and `update_state()` re-derives one
  from the current dates whenever it is cleared) and a participation-status filter whose default
  value is the literal string `"default"`. Both count as "set", so the icon was lit on a fresh load
  and "Clear filters" could not turn it off. `EgwApp.getFilterInfo` exists for exactly this - it is
  bound onto the framework app by `et2_ready()` - so calendar's (which already existed, adjusting the
  tooltip) now drops `filter` unless it is `custom` and `status_filter` when it is `default` before
  delegating. **Worth checking for any app with a control that has no empty state.**

- **"Clear filters" returning an empty list is a measurement artifact, not a bug.** Checked twice
  (2026-09-14): the row count dips to 0 only while the reload it triggers is in flight, and settles
  back to the full count - polling for 14s after a clear shows it never even dips. Worth knowing
  because `EgwFrameworkApp`'s clear does `filters.value = {}` then `applyFilters()`, and calendar's
  `update_state()` re-derives `filter`/`status_filter` right afterwards, so a snapshot taken between
  those two can show anything. Take a settled reading, not a single one.
- **Two known-broken-but-unrelated things found while verifying, both left alone**:
  `<et2-description value="#%s" id="${row}[id]">` renders `46`, not `#46` - `Et2Description.set_value()`
  tests and substitutes into `_value` itself (`_value.replace(/%s/g, _value)`) where legacy
  `et2_description` used `this.options.value` as the format string, so the "value is a format string
  for the bound content" feature is simply gone on the web component, for every app. And
  `CalendarApp._sortable()` throws (`Sortable: el must be an HTMLElement, not null`) on any
  `update_state()` reached before `calendar.view` has loaded - e.g. navigating straight to
  `calendar.calendar_uilist.listview` - which leaves `state_update_in_progress` stuck `true` and makes
  every later state update a silent no-op. Neither is caused by, or fixed by, the conversion.

## The Home favourite portlet

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

## Status

In progress. The checklist below is validated against five real conversions (see the reference
sections for the evidence each item is based on). Expect it to grow as more apps convert.

## Legacy-only interfaces: `et2_INextmatchHeader` / `et2_INextmatchSortable`

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

## `NextmatchInterface`: what the widgets around a nextmatch may rely on

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

## `ExposeMixin`'s gallery, and the row API it needed (fixed 2026-09-14)

`api/js/etemplate/Expose/ExposeMixin.ts` - the gallery/lightbox mixin used by `Et2VfsMime.ts`
(filemanager's file-thumbnail widget), `Et2Link.ts`, `Et2LinkList.ts`, `Et2ImageExpose.ts`, and
`Et2DescriptionExpose.ts` - finds the containing grid so the gallery can sync navigation to it
(`find_nextmatch()`). It only ever recognised the legacy `et2_nextmatch` widget, so the whole
gallery-to-grid sync was dead code in filemanager - the one app the feature was written for - from
the moment filemanager converted. Two separate things had to change, and doing only the first would
have made it worse: detection returning a grid whose `controller` does not exist turns a silent
no-op into a `TypeError`.

**Detection now walks the composed DOM tree, not the widget tree.** `find_nextmatch()` used to climb
`getParent()` and test `instanceOf(et2_nextmatch)`, which is always `false` for an `Et2Nextmatch`
(the two widgets are unrelated class hierarchies). The widget tree is not usable here at all:
`Et2RowProvider`/`Et2DatagridRowRenderer` hydrate row widgets without ever calling `setParent()`, so
a row widget's `getParent()` is `null`. The walk now goes up `parentNode`, hopping to
`(node as ShadowRoot).host` at each shadow boundary, until it reaches an `<et2-nextmatch>` - which is
also the only way out of the row widget's own shadow root, something `closest()` cannot do.

**A row widget gets detached while its gallery is open.** Opening the gallery applies a mime
`col_filter` so the grid only holds media, and that reload can replace the very row the clicked
widget was rendered into. The widget keeps working (the gallery is driven from it), but it is out of
the DOM, so the walk above has nothing left to climb - and `expose_onclose()` would then never clear
the mime filter, leaving filemanager stuck showing only images. The legacy widget tree survived
detachment for free; the DOM does not, so the mixin remembers the nextmatch in
`_gallery_nextmatch` for the life of the gallery and falls back to it (guarded on `isConnected`),
clearing it in `expose_onclosed()`. **Confirmed live** on `nathan.egroupware.org`: without the
fallback the filter stayed applied after closing the gallery.

**The filemanager-only restriction was deliberately left in place** (`find_nextmatch()`'s own
comment: "At the moment only filemanger nm would work as gallery ... but filemanager", enforced via
a `dom_id.match(/filemanager/i)` check - `dom_id` resolves on `Et2Nextmatch` too, `Et2Widget.ts`).
Widening the feature to other apps is a product decision, not a side effect of fixing detection.

**New public API on `Et2Nextmatch`/`Et2Datagrid`**, replacing the legacy internals the downstream
code reached into (`nm.controller.*`). Each is the minimum needed for one of them:

| Legacy internal | New API |
|---|---|
| `nm.controller.getRowByNode(node)` (+ `entry.controller.getDepth()`) | `nm.getRowByNode(node)` → `{id, depth}` or `null`. `depth` is 0 for a row of the nextmatch's own grid and counts up per level of expanded child grid, which is what the old `getDepth() > 0` check meant. |
| `nm.controller._indexMap` | `nm.getLoadedRowIds()` / `Et2Datagrid.getLoadedRowIds()` → row ids by index, `null` for indexes not loaded. Row *content* still lives in egw's UID cache (`egw.dataGetUIDdata()`); this is only the index → uid mapping. |
| `nm.controller._gridCallback(start, end)` | `nm.loadRowRange(start, end)` / `Et2Datagrid.loadRowRange()` - loads an index range the user has *not* scrolled to, without moving the grid, and resolves when the range is complete (or when fetching stops making progress). Unlike the legacy callback it is awaitable, so the caller reads rows that actually arrived instead of whatever happened to be cached. |
| `nm.controller._grid.getTotalCount()` | `nm.totalCount` (already existed). |
| `nm.update_in_progress` | `nm.isLoading`. |

`Et2Datagrid._queueChunkRequest()` was extracted while doing this: `_requestChunkForRowIndex()`,
`loadMore()` and the new `loadRowRange()` all queue a page the same way.

**Side benefit, confirmed**: `ExposeMixin.ts` imported `et2_nextmatch` from
`et2_extension_nextmatch.ts` purely for that one `instanceOf()` check, and `ET2_DATAVIEW_STEPSIZE`
from `et2_dataview_controller.ts` for one number (now a local `GALLERY_PAGE_SIZE`). Both legacy
imports are gone, so `Et2Link`, `Et2LinkList`, `Et2ImageExpose`, `Et2DescriptionExpose` and
`Et2VfsMime` no longer reach the ~4600-line legacy widget-registration file at the root of the
circular-import TDZ hazard (see the `et2_core_inheritance.ts`/`Et2Widget.ts` fix history) through
this edge at all.

**Still rough**: `set_slide()`'s index bookkeeping drops the very last row of a paged-in range (its
`num -= 1` at the end), so the final image of a large folder can be missing from the gallery. That
is untouched legacy gallery code, not part of this fix.

## Known gap: `open_popup` actions whose popup markup isn't already an `<et2-dialog>`

`Et2NextmatchActionController.openActionPopup()` (`Et2Nextmatch/Et2NextmatchActionController.ts`,
~line 1468) — the modern replacement for the legacy `nm_action()`'s `case 'open_popup'` — finds the
popup element (`[id*='<action.id>_popup']`) and calls `.show()`/sets `.open = true`/calls
`.showModal()` on it, in that order, assuming the popup **is already an `<et2-dialog>`**. If none of
those exist on the element (e.g. a plain `<et2-box id="foo_popup" class="action_popup prompt">`,
shown/hidden purely via CSS - the pattern several apps use for their own custom multi-select popups,
predating `Et2Dialog`), `openActionPopup()` returns `false` silently, and execution falls through to
the `case "submit"` branch instead: it does a real, full form submit with whatever the popup's fields
currently hold (their untouched defaults, since the popup was never actually shown for the user to
fill in) - **not** an error, just a silent no-op-shaped submit for actions like Tracker's `admin`
(only fires if `$content['admin_popup']` is a non-empty array, which never happens here) or `group`/
`link` (both have `case` blocks in their apps' `::action()` keyed off a `_`-joined `$settings` suffix
that a real dialog submission would have supplied).

The **legacy** `nm_open_popup()` (`et2_extension_nextmatch_actions.js`, still used directly by apps
that override `onExecute` for one specific popup action, e.g. Tracker's `assigned`/`change_assigned`
and Infolog's `responsible`/`change_responsible`) does not have this limitation - on first use it
upgrades the plain div in place: strips the `.prompt`/`.action_popup` hiding classes, moves its
buttons into a real `Et2Dialog`'s footer slot, and calls `dialog.show()`. That upgrade path only runs
for the one action an app explicitly wires to `nm_open_popup` via a custom `onExecute` - actions left
on the framework's default execute (`Et2NextmatchActionController.initActions()`'s
`setDefaultExecute`) go through `openActionPopup()` instead and hit the gap above.

**Confirmed live** (2026-09-03, via `nm.executeAction('admin', {ids:['tracker::10'], all:false})`
against Tracker's real index on `nathan.egroupware.org`): the `admin_popup` `<et2-box>` never opened
(stayed `display:none`, never became an `<et2-dialog>`), and the followed-through submit was a
genuine no-op only because `tracker_ui::action()`'s `admin` case requires `$action` to be an array -
confirmed by `tr_modified` staying unchanged. Tracker's `group` and `link` actions are exposed to the
exact same gap (both `nm_action: 'open_popup'` with no custom `onExecute`, both backed by a plain
`<et2-box class="action_popup prompt">`) and were **not** further live-tested after this finding, to
avoid another real submit against production data. Infolog already has the identical exposure today,
independent of Tracker's conversion - `infolog/templates/default/index.xet` has `link_popup`,
`startdate_popup`, and `enddate_popup` as the same `<et2-box class="action_popup prompt">` pattern
with no custom `onExecute`, all wired through the same default-execute path.

**Fixed 2026-09-03** (commit `81b4c2d55c`): `openActionPopup()` now delegates to `nm_open_popup()` for
any popup that isn't already an `<et2-dialog>`, logging a one-time deprecation notice per popup id via
`et2_warnOnce()`. Both paths now behave identically and no longer silently fall through to a bad
submit - but note this is a **delegation, not a removal**: the framework's own default-execute path
now depends on the legacy `et2_extension_nextmatch_actions.js` file too, on top of the apps that
already called it directly from a custom `onExecute`. It does not reduce the codebase's dependency on
that file; if anything it adds a caller. Deleting `et2_extension_nextmatch_actions.js` eventually is a
real goal (confirmed directly by the project owner), but the intended path there is **not** a
framework-level rewrite of `openActionPopup()` - it's every app, as it converts to `Et2Nextmatch`, also
converting its own action-popup markup from the legacy `<et2-box class="action_popup prompt">` pattern
to a real `<et2-dialog>`, the same way `tracker.edit.comment_edit`'s dialog already is. Once every app
using `Et2NextmatchActionController` has real `<et2-dialog>` popups, the `openActionPopup()` delegation
becomes dead code for the "converted app" case (though `nm_open_popup()` the function still needs to
keep existing as long as any app remains on the *legacy* `et2_nextmatch` widget, since that widget's
own `nm_action()` calls it directly through a completely separate code path that `Et2NextmatchActionController`
never touches).

**Action item for every app's conversion checklist**: grep the app's own templates for
`class="action_popup prompt"` (or any other plain box/div toggled by CSS for a multi-select bulk-action
popup) alongside its `<nextmatch>`/`<et2-nextmatch>` tag, and convert those to real `<et2-dialog>`
elements as part of the SAME conversion commit — don't leave them as box popups the delegation above
happens to keep working. Tracker had 4 of these (`admin_popup`, `link_popup`, `assigned_popup`,
`group_popup` in `templates/default/index.xet`); 3 (`admin_popup`, `assigned_popup`, `group_popup`)
have been converted and live-verified, `link_popup` was deleted instead (see the Tracker paragraph
above and the subsection immediately below — **check for this case before converting any app's own
`link_popup`-style popup**). The button-wiring is the hard part, not the markup: each
box popup's buttons rely on `nm_open_popup()`'s runtime upgrade wrapping every `<et2-button>`'s onclick
to set the legacy `window.nm_popup_action`/`window.nm_popup_ids` globals before calling the button's
own handler (typically `onclick="nm_submit_popup(this)"`, which reads those same globals to build and
send the submit). A real `<et2-dialog>` written directly in the template skips that upgrade path
entirely (`openActionPopup()`'s already-a-dialog fast path only sets `.selectedIds` and calls
`.show()` — no button wrapping at all), so each button's own onclick must be rewritten to not depend
on those globals.

### Before converting a `link_popup`-style action, check for the auto-added "Link" action

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
converted to a `<et2-dialog>`:

- Confirm the app is actually in the Link registry (it almost certainly is, if it has its own link
  action at all) — `grep -n "search_link" <app>/setup/setup.inc.php`, or check live via
  `egw.link_get_registry('<app>', 'query')` in the browser console.
- Confirm live that right-clicking a row already shows a top-level "Link" item (with the link icon) —
  if the app's own action is nested under a submenu (Tracker's was under "Change"), the two coexist
  without colliding, so this is safe to check on an unconverted app too, before doing anything else.
- **Check for anything the app's own `case 'link':` does that the generic action does not**, before
  deleting it — Tracker's had nothing extra (no ACL check at all, in fact — see above), but another
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
- Infolog's `index.xet` still has the identical `link_popup` pattern (`link_popup`/`link_action[add]`/
  `link_action[delete]`, `infolog_ui.inc.php`'s `case 'link':`) and has not been checked against this
  yet — worth doing whenever Infolog's own conversion is revisited, independent of anything here.

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
   renders nothing at all, silently (ProjectManager's `<projectmanager-select-erole>`; see its
   section above for why a server-side type transformation does not save you here).

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
   this way — see `Et2Nextmatch.md` § Styling Rows for the fallback rule to `app.css`.

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
- Row `class` binding: `<row class="$row_cont[class] $row_cont[cat_id]">` can be simplified to the
  direct-binding form `<row class="$class $cat_id">` — both syntaxes work, but new/edited rows should
  use direct bindings (see `Et2Nextmatch.md` § Row value bindings).
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
  *widget's* `template` (set once from server attrs), not `columnPreferenceName` or any
  per-view-mode row-template id - Mail's row template varies per view (`mail.index.rows.vertical`)
  while its shipped default preference name does not (`nextmatch-mail.index.rows-autorefresh`,
  `mail/setup/default_records.inc.php:32`), so that default currently never matches and is
  effectively dead; fixing Mail's shipped key (or making the lookup view-independent) is an
  open follow-up, not blocking since Mail also uses push as its primary mechanism.
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
`_whenLazyVisible()` no-ops unless the nextmatch is inside an inactive `<et2-tab-panel>` (checked via
`closest("et2-tab-panel")` + the panel's own reflected `active` attribute), in which case it returns a
promise that resolves on the enclosing `<et2-tabbox>`'s `sl-tab-show` event once `event.detail.name`
matches the panel's `name`. This mirrors an existing precedent in the codebase for the identical
problem - `et2_widget_historylog.ts`'s `doLoadingFinished()` uses the same `sl-tab-show`/panel-name-match
technique to lazily load a History tab's content, just against the legacy `get_tab_info()` API instead
of a web-component ancestor.

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
- **The row-shadow-DOM `app.css` compatibility fallback** (used when the row template has no
  `<et2-styles>`) resolves the *containing template's own* template_set — derived from
  `closest("et2-template").getUrl()` — falling back to `default` only if the skin-specific file 404s.
  Don't assume it always loads `templates/default/app.css` regardless of the active skin; a
  mobile-skin conversion needs `templates/mobile/app.css` to actually be the one that loads. Bare
  `<et2-styles src="...">` values inside a row template resolve the same way, relative to that
  template's own file.
- **`app.css` selectors scoped to the index page's own container id (e.g. `#tracker-index
  .some-row-class`) silently stop matching anything once that app converts**, with no warning
  anywhere - the fallback above really does load that same file's rules into the datagrid's shadow
  root, but a shadow root is its own separate node tree with no `#app-index` ancestor in it at all
  (that id only exists in the light DOM), so an id-scoped selector can never match a row element
  post-conversion. This is easy to miss because everything still *loads* without error; the only
  symptom is that some row styling (read/unread bold, priority colors, italics, whatever the app used
  the class for) just silently stops applying, and a plain page glance can miss it entirely if the
  affected style is subtle (bold vs. not) rather than a layout break (Tracker: `tracker_unseen`/
  `tracker_seen` bold state, several priority-color classes, `tracker_overdue`, `private`/`planned`
  italics - all of `app.less`'s `#tracker-index { ... }`-wrapped block, one file, one rename away from
  fixed). Before considering an app's row-CSS unaffected by conversion, grep its `app.less`/`app.css`
  for a selector scoped to that page's own container id and check whether any of the classes it
  targets are used inside the row template - if so, drop the id scope (the shadow root already
  provides equivalent isolation, so nothing is lost by doing this) and recompile
  (`lessc app.less app.css`, checking the diff is purely the scope removal before overwriting).
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
