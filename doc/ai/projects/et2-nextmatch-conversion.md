# Converting an app from `et2_extension_nextmatch` to `Et2Nextmatch`

A checklist for moving one app's list view from the legacy `nextmatch` widget
(`api/js/etemplate/et2_extension_nextmatch.ts`, tag `<nextmatch>`) to the `Et2Nextmatch` web
component (`api/js/etemplate/Et2Nextmatch/Et2Nextmatch.ts` + `Et2Datagrid.ts`, tag
`<et2-nextmatch>`). For widget usage (attributes, row bindings, styling, row expansion), see the
generated component docs for
[`et2-nextmatch`](https://etemplate.egroupware.org/components/et2-nextmatch/) and
[`et2-datagrid`](https://etemplate.egroupware.org/components/et2-datagrid/) — this document does not
repeat that reference material.

Apps converted so far: Addressbook, Infolog, Filemanager, Mail, Timesheet, Tracker. Apps still on the
legacy widget: Calendar, Admin, Importexport, Aiassistant, Preferences, Home. Related in-flight/reference
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

Timesheet's conversion covers the main index (desktop + mobile skin) only. `index.rows.xet` (the
Home-favorite-portlet variant, rendered through `timesheet_favorite_portlet.inc.php`) is deliberately
left on the legacy widget, same shared-framework-code reason as Addressbook's and Filemanager's portlet
views above — convert it together with a matching pass over other apps' favorite-portlet row
templates, not as a one-off.

Tracker's conversion covers the main index (desktop + mobile skin), the admin Escalations list
(`escalations.xet`), and the comments/replies list embedded in the edit popup (`edit.xet`'s
`tracker.edit.comments`/`tracker.edit.comment_row`, id `replies` — desktop only, the mobile edit
template renders comments as a static loop with no nextmatch at all). `index.rows.xet` (the
Home-favorite-portlet variant, rendered through `tracker_favorite_portlet.inc.php`, same `tracker.index.rows`
template id as the real index but a separate file) is deliberately left on the legacy widget, same
shared-framework-code reason as the other apps' portlet views above. The comments nextmatch also uses
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

## Status

In progress. The checklist below is validated against five real conversions (see the reference
sections for the evidence each item is based on). Expect it to grow as more apps convert.

## Known gap: `ExposeMixin` doesn't recognize `Et2Nextmatch` at all

`api/js/etemplate/Expose/ExposeMixin.ts` - the gallery/lightbox mixin used by `Et2VfsMime.ts`
(filemanager's file-thumbnail widget), `Et2Link.ts`, `Et2LinkList.ts`, `Et2ImageExpose.ts`, and
`Et2DescriptionExpose.ts` - imports the **legacy** `et2_nextmatch` class from
`et2_extension_nextmatch.ts` purely to find the containing grid so the gallery can sync navigation
to it (`find_nextmatch()`, line ~443, checked from 5 call sites at lines 475, 788, 864, 882, 945).
This needs two separate fixes, not one:

1. **Detection is broken for `Et2Nextmatch`.** `find_nextmatch()`'s check is
   `current.instanceOf(et2_nextmatch)`. `et2_nextmatch` (legacy) extends `et2_DOMWidget`;
   `Et2Nextmatch` (`api/js/etemplate/Et2Nextmatch/Et2Nextmatch.ts:92`) extends `Et2Widget(LitElement)`
   - two unrelated class hierarchies. `ClassWithInterfaces.instanceOf()`
   (`et2_core_inheritance.ts`) falls through to `this instanceof et2_nextmatch` for anything that
   isn't a string or the `Et2Widget` mixin itself, which is always `false` for a real `Et2Nextmatch`
   instance. So `find_nextmatch()` can never find a modern grid - it always returns `null` for one,
   same as if there were no containing grid at all.
2. **Even with detection fixed, the code that runs once `nm` is found doesn't work either.** Every
   consumer (`read_from_nextmatch()` at line 559, plus the call sites above) reaches into
   `nm.controller` and legacy-only internals: `.controller.getRowByNode()`, `.controller._indexMap`
   (already flagged with no public replacement in the
   [legacy API replacement table](#reference-legacy-api-replacement-table) below),
   `.controller._grid.getTotalCount()`, `.controller._gridCallback()`. `Et2Nextmatch` has none of
   these - see that same table for the real replacements (`nm?.totalCount`, the
   `hasRow`/`getLoadedRows` gap, etc.). Fixing `instanceOf()` alone would swap a silent
   "gallery just doesn't sync to the grid" no-op for a hard `TypeError` on `nm.controller` being
   `undefined`.

**This is not purely a future concern - it's live today.** `find_nextmatch()` already
self-restricts to filemanager only (its own comment: "At the moment only filemanger nm would work
as gallery, thus we disable other nestmatches ... but filemanager", enforced via a
`nextmatch.dom_id.match(/filemanager/, 'ig')` check). Filemanager's main index and tile view are
already converted to `Et2Nextmatch` (see the app list above). `dom_id` itself still resolves fine on
`Et2Nextmatch` (`Et2Widget.ts:466` provides an equivalent getter), but because `instanceOf()` never
even gets that far, gallery-to-grid sync for filemanager's own thumbnail gallery is presently dead
code - the one app this feature was written for is the one app that broke it, simply by being
converted.

**Untangling this also pays off independently of fixing the feature**: `et2_extension_nextmatch.ts`
is the ~4600-line legacy widget-registration file at the root of a real circular-import TDZ hazard
(see the `et2_core_inheritance.ts`/`Et2Widget.ts` fix history) - it's only in `Et2Link`'s import
graph at all because of this one `instanceOf()` check. Once `ExposeMixin.ts` no longer needs it,
`Et2Link` and every other Expose consumer's dependency graph loses that edge entirely, not just the
detection bug.

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
| `nm.controller._indexMap` (check whether a uid is currently loaded/rendered in *this* nextmatch instance, e.g. before deciding to `refresh()` it from a push notification) | **No public replacement exists.** Closest option: `(nm.shadowRoot?.querySelector("et2-datagrid") as Et2Datagrid)?.rows` — the live, kept-in-sync row list (`{id, data}[]`), reached the same way `Et2Nextmatch`'s own private `_datagrid` getter does. This walks past a `private`-in-TS-only boundary via a real DOM query, not a sanctioned API — flag it to the `Et2Nextmatch` maintainer as a gap (a public `hasRow(uid)`/`getLoadedRows()` would be cleaner) rather than treating it as fully idiomatic (Addressbook's `CRM.ts`). |
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
  only app currently doing this (`calendar/templates/default/filter.xet`). Filters can also be grouped
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
