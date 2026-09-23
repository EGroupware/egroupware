# Nextmatch actions: submit -> ajax, and picker dialogs for unbounded option sets

Two related problems with nextmatch context-menu actions, found together:

1. **Most actions do a full eTemplate submit**, which tears down and rebuilds the whole
   template - nextmatch included. Everything the user had set up in the list (scroll
   position, selection, row heights/expanded cells, and in older releases the filters
   themselves) is lost on every "Close", "Change category", "Add to distribution list".
2. **Several actions render one sub-action per row of user data** (one menu entry per
   category, per distribution list, per addressbook, per share target, per tracker queue, per
   kanban board). With a handful that is fine; past a screenful it is a multi-level "More..."
   maze that is slower to use than the thing it replaced, and it bloats every `get_rows`
   response. Some of these are conceptually unbounded, others merely grow with the
   installation until one day they are too long - and nothing notices when that happens.

Status: **phases 0-2 done** (see section 5). Phase 3 (Proposals D + E) next.

---

## 1. The submit problem

### Mechanism (confirmed)

An action with no `onExecute`, no `url` and no `egw_open` gets nothing written into
`$action['data']['nm_action']` by `Nextmatch::egw_actions()`
(`api/src/Etemplate/Widget/Nextmatch.php`, ~line 1248 - it only sets `nm_action` for the
url/popup/egw_open cases). Client-side the gap is then filled in
`Et2NextmatchActionController.executeNextmatchAction()`:

```ts
if(typeof action.data.nm_action === "undefined" && (action as any).type === "popup")
{
    action.data.nm_action = "submit";      // "popup" is the default action type
}
```

`nm_action: "submit"` routes to `executeSubmitAction()` -> `etemplate2.submit()` ->
`Etemplate.process_exec` -> the app's `index()` runs again -> `$tpl->exec()` -> a brand new
et2 instance and a brand new nextmatch.

So the fall-through is silent: an action author who writes only `caption`/`icon`/`group`
gets a full-page-equivalent reload, and nothing in the action definition says so.

### Evidence (live, master, 2026-09-23)

Driving the real `egw_action` objects in the browser, with `etemplate2.submit()` wrapped so
nothing was written server-side:

* InfoLog's `close` action resolves to `action.data.nm_action === "submit"` and calls
  `etemplate2.submit()` with `{multi_action: "close", selected: [...], select_all, checkboxes}`.
* Before/after a real submit round-trip: the nextmatch widget instance is **replaced**
  (`nm_after !== nm_before`) and the datagrid scroll offset goes **3000 -> 0**.
* For comparison, the ajax path (`egw.refresh(msg, app, id, 'update')`) keeps the same
  widget instance and the scroll offset (2500 -> 2500).

That contrast is the whole justification for this project.

### What is and is not already fixed

`d3da314452` ("Addressbook: Fix filters from submit actions were overwritten with
defaults", also in `26`) added a session-state restore at the top of
`addressbook_ui::index()`: `$content['nm']` is seeded from
`Api\Cache::getSession('addressbook', 'index')` (written by `get_rows()`) instead of from
the hardcoded defaults. Verified live: `search` and `filter2` now survive an addressbook
action submit.

That is a per-app, per-filter band-aid for one symptom. It does **not** address the
teardown itself, and apps without it still fall through to their defaults. Treat it as
evidence that the symptom is real and recurring, not as a fix.

---

## 2. Inventory of submitting actions

### How this was measured

Three passes, because no single one is complete:

1. **Live browser dump** - walk each app's real `egw_action` manager and apply the exact
   client-side rule (leaf, `type === "popup"`, no `data.url`, no `data.egw_open`,
   `data.nm_action` undefined, `onExecute` still the controller's own default executor from
   `setDefaultExecute()`). This is production behaviour, nothing inferred.
2. **A PHP harness** - call each app's real `get_actions()` and run the result through the
   real `Nextmatch::egw_actions()`, then classify the resolved tree. Reaches apps whose list
   is awkward to open, and resolves everything the helpers generate.
3. **A source scan** - for the handful of apps that define actions inline in `index()` with no
   `get_actions()` at all.

A naive source scan on its own is **not** reliable here, and got three things wrong before the
other two passes corrected it: `filemanager` `share_mail` is a container whose children do
have handlers; `projectmanager_elements_ui` `erole` is `unset()` when `enable_eroles` is off
and becomes a container when it is on; `timesheet_add` inherits `egw_open` from its parent.

**Caveat on the numbers:** passes 1 and 2 reflect *this* instance - its installed apps, its
config, its categories, its ACL. Category/list/addressbook counts scale with the data;
config-gated actions (`erole`) can be absent entirely. The *set* of action ids is stable, the
counts are not.

### Runtime-verified (live browser)

| App | submit / total | Families |
| --- | --- | --- |
| addressbook | **180 / 421** | `cat/cat_add/*` + `cat/cat_del/*` (~150), `lists/to_list/*` (37), `lists/remove_from_list`, `lists/delete_list`, `move_to/*` (7), `shared_with/*` (5), `change_type/*`, `view_org`, `view_duplicates`, `merge`, `merge_duplicates`, `invoices/*`, `export/*` |
| tracker | **83 / 132** | `change/cat/*` (18), `change/resolution/*` (16), `change/tracker/*` (12), `change/completion/*` (11), `change/priority/*` (9), `change/status/*` (7), `change/version/*` (5), `change/seen`, `change/unseen`, `close`, `close_100_<res>`, `invoices/*` |
| infolog | **76 / 337** | `change/cat/*` (38), `change/status/*` (12), `change/type/*` (11), `change/completion/*` (11), `close`, `close_all`, `ical`, `invoices/*` |
| calendar | **7 / 32** | `status/*` (5), `timesheet/*`, `ical` |
| mail | **1 / 48** | only `copyto`, a container whose folder children load later - effectively clean |
| projectmanager (projects) | 2 | `delete`, `undelete` only - `cat` and `status` are already ajax |

### Harness-verified (34 classes)

Everything below is a plain `nm_action: "submit"` leaf. `select_all` and the `egw_copy`/
`egw_paste` clipboard pseudo-actions are excluded - they are handled client-side and are
**not** part of this problem.

| Class | Actions |
| --- | --- |
| `projectmanager_elements_ui` | `cat/cat_*` (33), `sync_all`, `delete` |
| `records_ui` | `status/status_*` (6), `delete` |
| `importexport_definitions_ui` | `copy`, `createexport`, `export`*, `delete` |
| `EGroupware\Developer\TranslationTools` | `import`, `current`, `all`, `move_to_api`, `delete` |
| `esyncpro_ui` | `policy/policy_*`, `wipe`, `delete` |
| `EGroupware\SmallParT\Questions` | `exempt`, `readd`, `delete` |
| `EGroupware\Invoices\Ui` | `downloadZIP-XML`*, `downloadZIP-PDF`*, `delete` |
| `news_admin_ui` | `update`, `delete` |
| `filemanager_ui` | `unlock`, `saveaszip`* |
| `EGroupware\Admin\Token` | `activate`, `revoke` |
| `EGroupware\SmallParT\Courses` | `copy_course`, `copy_no_participants` |
| `EGroupware\Aiassistant\Ui` | `separator`, `delete` |
| `bookmarks_ui`, `admin_customfields`, `admin_accesslog`, `filemanager_shares`, `EGroupware\Filemanager\Jobs`, `projectmanager_pricelist_ui`, `EGroupware\Aitools\Admin`, `EGroupware\Stylite\Calls` | `delete` |
| `EGroupware\Kanban\Ui\BoardList` | `copy` |

`*` = `postSubmit` download, see "Must stay a submit".

**Clean, nothing to do:** `timesheet_ui`, `admin_categories`, `admin_acl`, `mail_sieve`,
`resources_acl_ui`, `resources_ui`, `EGroupware\Rag\Ui`, `EGroupware\Kanban\Datasource`,
`EGroupware\Policy\Ui`, `EGroupware\Status\Ui`, `EGroupware\Stylite\Firewall`,
`EGroupware\Stylite\Vfs\S3\Config`, `EGroupware\SmallParT\Student\Ui`.

### Source-read only (actions defined inline, no `get_actions()`)

All `delete`-shaped, none runtime-verified: `admin/src/Groups.php`, `phpbrain`
(`publish`/`delete`, twice), `openid` (`Ui`, `User`), `webauthn/src/Register.php`,
`schulmanager_substitution_ui`, `stylite/src/Cti/Placetel/AdminUI.php`,
`records/inc/class.records_admin.inc.php`, `news_admin_gui`. Plus `EGroupware\Stylite\Calls`
`reimport`/`undelete`, which are conditional on a filter this run did not hit.

### A related, separate bucket: `nm_action => 'open_popup'`

These open a dialog - but the dialog's OK button then submits the **whole** template anyway to
get its values back, and if the popup element is missing `executeNextmatchAction()` falls
straight through to `case "submit"`. Same end result, more work to convert, so they are
scheduled separately (phase 5):

`projectmanager_elements_ui` `add_existing`; `resources_ui` `delete`, `restore`;
`importexport_definitions_ui` `change/owner`, `change/allowed`; `news_admin_ui`
`change/reader`; `infolog` `change/{startdate,enddate,responsible,link}`; `tracker`
`change/group`.

### Already correct

**timesheet is the reference implementation** and needs no work - including its category
action, which piggybacks on the status handler
(`$actions['cat']['onExecute'] = $actions['status']['onExecute'];`). Also fine: addressbook
`delete`, infolog `delete`/`delete_sub`, calendar's ajax actions, mail (all but the `copyto`
container), and `projectmanager_ui`'s `cat_*`/`status` (`app.projectmanager.change_status`,
with a comment in place saying it is deliberately ajax "so the list keeps its scroll position
instead of reloading to the top" - independent confirmation that this problem has already
been hit and worked around app by app).

Calendar has no category context-menu action at all; its `status/*` is participant status.

### Not actually broken (do not "fix" these)

* `select_all` in many apps - special-cased in `Et2NextmatchActionController` (~line 290),
  which installs its own `onExecute`.
* `egw_copy` / `egw_copy_add` / `egw_paste` - clipboard pseudo-actions added client-side.
* `mail/src/Ui.php` `CUSTOM_FLAGS` (`customFlag1`-`5`) - a constant merged into an action that
  *does* have an `onExecute`, not an action list. A naive scan flags it.
* `api/src/Html/htmLawed/htmLawed.php`, `stylite/src/Cti/Storage.php` `selects` - unrelated
  arrays that happen to contain a `caption` key.

### Must stay a submit

Downloads: addressbook `export/vcard` and `documents/*`, infolog `ical`, filemanager
`saveaszip`, importexport `export`, invoices `downloadZIP-*`. These use `postSubmit` (a real
`<form method="POST">`), which per AGENTS.md's "File downloads" section is the only reliable
way to get a file out of an EGroupware popup. `postSubmit()` also does **not** re-render - the
server answers with the file and the page stays put - so they are not part of this problem in
the first place.

---

## 3. The submit -> ajax conversion

Timesheet already has the shape; the scope here (30+ classes) says it belongs in the base
class rather than copy-pasted per app.

### `ajax_action()` goes on `EgwApp`, not in every `$app/js/app.ts`

`EgwApp` (`api/js/jsapi/egw_app.ts`) already owns everything the handler needs - `appname`,
`egw`, and an `nm` member typed `Et2Nextmatch | et2_nextmatch` - and already imports
nextmatch-action helpers (`fetchAll`, `nm_action`), so this is not a new concern for the file:

```ts
/**
 * Run a nextmatch action over ajax instead of submitting (and re-rendering) the template.
 * Wired up server-side with 'onExecute' => 'javaScript:app.<app>.ajax_action'.
 */
protected ajax_action(_action : EgwAction, _senders : EgwActionObject[])
{
    const nm = _action.parent?.data?.nextmatch || _action.data?.nextmatch || this.nm;
    const ids = _senders.map(s => s.id.split("::").pop());
    this.egw.request(_action.data?.menuaction || this.appname + "." + this.appname + "_ui.ajax_action",
        [_action.id, ids, (<Et2Nextmatch>nm)?.getSelection().all === true]);
}
```

Apps needing something different (extra checkbox values, a confirm, a per-action menuaction)
override it or pass their own - the same way `EgwApp` handles the rest of its overridable
hooks.

**The menuaction is the one thing the base class cannot derive.** The convention
`<app>.<app>_ui.ajax_action` covers addressbook/infolog/timesheet/calendar/tracker, but not
`EGroupware\Invoices\Ui::ajax_action`, `projectmanager_elements_ui::ajax_action` or
`EGroupware\SmallParT\Courses::ajax_action`. Read it from `_action.data.menuaction`, set once
server-side per app - `long_task` already uses `data.menuaction` exactly this way, so there is
precedent and no new plumbing. The convention stays as the fallback so most apps set nothing.

Two things to get right while doing this:

* The `nm : Et2Nextmatch | et2_nextmatch` union needs a cast per call - a known wart already
  recorded in `doc/ai/projects/app-ts-modernization.md`.
* `_action.parent?.data?.nextmatch` first, then `_action.data?.nextmatch`, then `this.nm`:
  the controller sets `action.data.nextmatch` on the *executed* action, but a child action
  reached through a submenu carries it on the parent, and `this.nm` is the fallback for an
  action fired outside a row context.

### Server

Delegate to the app's existing `action()` and answer with `egw.refresh`, never a redraw:

```php
$app = Api\Json\Push::onlyFallback() || $all_selected ? '<app>' : 'msg-only-push-refresh';
Api\Json\Response::get()->call('egw.refresh', $msg, $app, $selected[0], …);
```

Most apps already have a usable `ajax_action()` (infolog, addressbook, calendar,
projectmanager, timesheet, filemanager, smallpart, mail_sieve). The work is mostly **wiring
actions to it**, not writing it.

### The cheap lever

`Nextmatch::egw_actions()` inherits `onExecute` from a parent action to all its children
(`$inherit_attrs`, ~line 1237). One
`'onExecute' => 'javaScript:app.<app>.ajax_action'` on `$actions['cat']` converts every
category child at once - including ones built at runtime from a variable, which is most of
the volume. Timesheet already exploits this
(`$actions['cat']['onExecute'] = $actions['status']['onExecute'];`), and
`projectmanager_ui` does the same for its `cat`.

One gotcha in that inheritance: `egw_actions()` does
`if (!empty($action['default'])) unset($inherit_keys['onExecute']);`, so a container marked
`default` keeps its own `onExecute` instead of handing it down. None of the containers in
scope are `default`, but it is worth knowing before wondering why one did not inherit.

### Sharp edges


1. **Session-query dependency.** `remove_from_list`/`delete_list` read `$query['filter2']` and
   `unshare` reads `$query['filter']` out of
   `Api\Cache::getSession('addressbook', $session_name)` (`action()`, ~line 1396). That cache is
   written by `get_rows()` (~line 1864), so it *is* reachable from an ajax endpoint - but the
   key differs per template (`index` vs `select`), and `addressbook_ui::ajax_action()` currently
   hardcodes `'index'`. Pass the real session name.
2. **`select_all`.** The ajax endpoint must keep re-running `get_rows()` with
   `num_rows => -1` to expand the selection, and answer with `egw.refresh(msg, app)` (full
   reload, filters kept) rather than a per-id refresh.
3. **`delete_list` deliberately clears `filter2`** (`action()`, ~line 1479) - the list is gone,
   so the filter must go too. Today that state change rides along on the redraw; the ajax
   version has to push it to the client explicitly.
4. **`nm_action => 'open_popup'` actions** (infolog `startdate`/`enddate`/`responsible`/`link`,
   tracker `group`) open a dialog whose OK button submits the *whole* template to get the
   popup's values back. Converting these is a bigger job than the rest - schedule separately,
   or fold into the picker-dialog work below, which solves the same problem better.
5. **Two pre-existing bugs in `addressbook_ui::ajax_action()`** (~line 1357): its 4th parameter
   `$skip_notification` is passed into `action()`'s `$checkboxes` slot, and the user message
   says `'%1 event(s) %2'` (copy-pasted from calendar). The checkbox one will bite as soon as
   `move_to_*` (reads `$checkboxes['move_to_copy']`) or `shared_with_*` (reads
   `$checkboxes['writable']`) are routed through it. Fix while touching the file.
6. **`disableIfNoEPL`, `enableClass`, `confirm_mass_selection`** and friends are evaluated
   client-side and are unaffected by the transport - but `confirm`/`confirm_multiple` run
   *before* `onExecute`, so an app handler must not re-confirm.

---

## 4. The many-sub-actions problem, and picker dialogs

### The rule

> **Submenu** when the option set is a bounded, fixed enum the developer wrote down
> (infolog status/completion, tracker's percent list). These are ~10 entries, stable, and a
> submenu is the fastest possible UI for them - leave them alone, just make them ajax.
>
> **Bespoke picker dialog** when the option set is user data *and* the action wants verbs or
> multi-select (categories, distribution lists) - Proposals A-B.
>
> **Generic overflow dialog** for everything in between: data-driven lists that are bounded in
> principle but grow with the installation (tracker queues/versions/resolutions/statuses,
> kanban boards, esyncpro policies, content types). These need no bespoke UI, just a search
> box once they get long - Proposal D, which handles them automatically by size with no
> per-app work.

The existing mitigations are themselves evidence of the problem:
`Nextmatch::DEFAULT_MAX_MENU_LENGTH = 50` with automatic "More..." pagination, plus
`category_action()` silently switching to `category_hierarchy()` (nested submenus) past that
threshold. Both turn a long list into a *deep* list; neither gives you search, neither lets
you pick two categories in one go, and both still ship every entry to the client on every
`get_rows` response.

### The pattern to copy

`api/js/etemplate/Et2Link/LinkAction.ts` + `api/templates/default/link_action.xet` is already
exactly this: a shared, API-level action that opens an `Et2Dialog` with one picker widget and
a couple of verb buttons, then does its work over `jsonq()` and reports per-entry failures.
It is registered automatically for every nextmatch by
`EgwPopupActionImplementation._addLinkAction()` - note the deliberate **dynamic** `import()`
there, to avoid the `et2_core_widget` circular-import TDZ bug; a new sibling must do the same.

Addressbook's `add_new_list()` / `rename_list()` (dialog + `add_list_dialog.xet` + ajax) show
the same shape inside an app.

### Proposal A - Categories (all apps)

Replace the per-category children with **one** "Categories" action opening a dialog built on
`<et2-select-cat>` - already a tree dropdown with search and tagging
(`Et2SelectCategory extends Et2StaticSelectMixin(Et2TreeDropdown)`), so hierarchy, search and
optional multi-select come for free.

Effect in addressbook: ~150 action definitions -> 1. In infolog: 38 -> 1.

#### Cardinality varies per app, and the dialog has to follow it

**An entry can hold several categories in some apps and exactly one in others.** There is no
central registry of which is which: the *only* place cardinality is declared today is
`multiple="true"` on the `et2-select-cat` in each app's edit template, which `get_actions()`
cannot see. The server-side action handlers already diverge to match, and this divergence is
correct - do **not** try to unify it:

| Call site | Field | Cardinality | Today's action(s) | Today's handler |
| --- | --- | --- | --- | --- |
| addressbook x2 | `cat_id` | **multiple** (`multiple="true"`, comma-separated) | `cat_add_<id>` + `cat_del_<id>`, two separate submenus captioned "Add category"/"Delete category" | explodes/implodes the comma list |
| infolog | `info_cat` | single | `cat_<id>`, "Change category" | `$entry['info_cat'] = $settings` (replace) |
| timesheet | `cat_id` | single | `cat_<id>`, "Change category" | `$entry['cat_id'] = $settings` (replace); already ajax |
| projectmanager (`projectmanager_ui`) | `cat_id` | single | `cat_<id>`, "Change category" | replace; **already ajax** via `app.projectmanager.change_status` |
| projectmanager (`projectmanager_elements_ui`) | `cat_id` | single | `cat_<id>`, "Change category" | replace; still submits |
| records | status, under `records_fields::STATUS_PARENT` | single | `status_<id>`, captioned "Status" | a status enum that merely happens to be stored as a category |

So the dialog needs two shapes, chosen by the call site:

**Multiple** (addressbook): `<et2-select-cat multiple="true">`, buttons **Add** / **Remove** /
**Replace**. Add and Remove map onto the existing `cat_add_<id>`/`cat_del_<id>` handling
unchanged. Replace is new behaviour and was explicitly approved - it has no existing server
handler on the multi-category side, so it needs one (set `cat_id` to exactly the picked list,
rather than a `cat_del` of everything followed by a `cat_add`, which would write each contact
twice and fire two history entries).

**Single** (infolog, timesheet, both projectmanager lists, records): single-select
`<et2-select-cat>`, buttons **Set** / **Remove**. Set maps onto the existing `cat_<id>`
replace handler. **Remove is new capability, not a redesign**: today `category_action()`
emits no "None" entry at all, so there is currently *no way* to clear a category from the
list in these apps - the server handler already does the right thing for an empty
`$settings` (infolog's own `action()` even has the `lang('removed category')` branch for it),
it is simply unreachable from the UI. Reaching it costs nothing.

Records is the odd one out only in labelling: keep its "Status" caption, pass its
`STATUS_PARENT` through to the widget's `parentCat`, and offer **Set** only (clearing a
status is not meaningful there).

#### Delivery

Give `Nextmatch::category_action()` two new parameters: a mode that returns the single dialog
action instead of the children, and an explicit **`$multiple`** telling the dialog which shape
to use. `$multiple` has to be stated at the call site - there is nothing to infer it from.
Default it to `false` (the majority, and the safe shape: offering Add/Remove where only
replace is implemented would be a data-loss-shaped bug, whereas offering Set where multiple is
supported merely under-uses the field) and convert addressbook's two call sites explicitly in
the same commit.

Server side there is no generic save path (each app has its own save + ACL), so the endpoint
stays per-app. But the **first pass needs no server change at all**: the dialog can call the
app's existing `ajax_action()` once per picked id, reusing the untouched `cat_add_<id>` /
`cat_del_<id>` / `cat_<id>` handling. Batching into a single array-taking call is a follow-up
optimisation, not a prerequisite - and it is only meaningful for the multiple case anyway.

Keep the old submenu behaviour available until all 7 call sites are moved.

### Proposal B - Distribution lists (addressbook)

Replace `to_list/*` (37 children) + `remove_from_list` with **one** "Distribution lists"
dialog:

* `<et2-tree-dropdown>` fed by the existing `addressbook_ui::distribution_lists()` output -
  the same widget and the same option tree the `filter2` picker already uses, so no new
  server-side data assembly.
* Buttons: **Add to list**, **Remove from list**, and an inline **New list...** that reuses
  the existing `add_list_dialog.xet` path.

This also fixes a real usability wart: today `remove_from_list` silently operates on whatever
`filter2` happens to be, and errors with "You need to select a distribution list" if it is
empty. In a dialog the target list is explicit.

`rename_list` and `delete_list` act on the *filter*, not on the selection, so they stay as
separate actions - they just need the ajax conversion, not a picker.

### Proposal C - withdrawn: `move_to` and `shared_with` are Proposal D's job

An earlier draft proposed a bespoke picker for addressbook's `move_to` (7 addressbooks plus a
"Copy instead of move" checkbox child). It does not need one: at 7 entries it is not even over
D's threshold today, and on an installation with enough addressbooks that it is, D catches it
automatically. Same for `shared_with` (5 addressbooks plus a `writable` checkbox and
`unshare`).

What they *do* need is the checkbox refinement to Proposal D, below.


### Proposal D - a generic overflow dialog for ANY oversized submenu

Proposals A-B are bespoke: they know what a category or a distribution list *is*, and offer
verbs (Add/Remove/Replace) that only make sense for that field. But plenty of submenus are
neither a fixed enum nor conceptually unbounded - they are **bounded but data-driven, and grow
with the installation**:

| Submenu | Children come from | Grows with |
| --- | --- | --- |
| tracker `change/tracker` | `$this->trackers` | configured trackers/queues |
| tracker `change/{cat,version,resolution}` | `get_tracker_labels(...)` per tracker | admin config |
| tracker `change/status` | `get_tracker_stati($tracker)` | admin config |
| tracker `change/responsible` | accounts | users |
| kanban (in every app's menu) | a `Bo::search()` over boards | boards |
| esyncpro `policy` | configured policies | admin config |
| records `status` | categories under `STATUS_PARENT` | admin config |
| infolog `change/type`, addressbook `change_type` | content types | admin config |

On this instance tracker's are 5-18 each and perfectly usable. On a big installation they are
not, and nobody is going to notice the moment they crossed over. The existing safety valve -
`DEFAULT_MAX_MENU_LENGTH = 50` with automatic "More..." pagination - makes it *worse*, turning
a long list into a nested one with no search.

So: **when a container's children exceed a threshold, render it as a single menu entry that
opens a generic selection dialog instead of a submenu.** Automatic, API-level, no per-app work,
and it applies to apps that have not been converted yet.

#### It dispatches the real child action, so nothing else changes

This is the property that makes it safe and cheap. The dialog does not reimplement anything -
it lists the container's existing `EgwAction` children, and on OK calls
`chosen.execute(senders, target)`. Every child therefore keeps its own `onExecute`,
`nm_action`, `confirm`, `enabled`, `icon`, `hint` and `data.level` exactly as it has today.

Concretely that means it works for submit-based children *right now* and keeps working
unchanged after those children are converted to ajax by phases 1-2 - the two efforts do not
have to be sequenced against each other.

#### Three small changes, all in shared code

1. **`Nextmatch::egw_actions()`** - where it currently decides to paginate into "More...",
   instead set `$action['data']['nm_action'] = 'select_children'` on the container when the
   child count exceeds the threshold. `data[]` is the right place: the top-level `nm_action`
   key is in `$inherit_attrs` and gets stripped off the parent by the `array_diff_key()` at
   the end of the children branch, but `data['nm_action']` survives - which is exactly where
   `egw_actions()` already writes its own derived values.
2. **`EgwAction.appendToTree()`** - skip the child recursion for such a container, so the menu
   renders it as a plain leaf. Today `appendToTree(_tree, true)` unconditionally pulls the
   whole subtree in, which is the only reason a submenu appears at all (`action_links` only
   ever contains first-level ids). The children stay in the action manager and stay
   executable; they are just not drawn.
3. **`Et2NextmatchActionController.executeNextmatchAction()`** - a new
   `case "select_children"` that opens the dialog. `setDefaultExecute()` already installs the
   default executor on containers as well as leaves (`EgwAction.setDefaultExecute()` sets it
   on `this` before recursing), and `_buildMenuLayer()` already gives every enabled leaf an
   `onClick` that calls `execute()`, so a now-childless container fires through the normal
   path with no extra wiring.

#### The dialog

A single shared `api/templates/default/action_select.xet`, same plain-static-class + `.xet`
shape as `LinkAction`: one picker plus OK/Cancel. Flat children map onto an
`<et2-select search="true">`; hierarchical ones (`category_action()` switches to
`category_hierarchy()` above its own threshold) onto `<et2-tree-dropdown>`, or a flat list
indented by `data.level`, which the children already carry - `_buildMenuLayer()` reads
`link.actionObj?.data?.level` today for exactly that.

#### Modifier checkboxes have to come along

Some containers mix two kinds of child: the things you pick, and a checkbox that modifies what
picking one *does*. `move_to` has `move_to_copy` ("Copy instead of move") next to the
addressbooks; `shared_with` has `writable` next to them; `Api\Sharing`'s `share` has
`shareWritable` and `shareFiles`.

A dialog that only listed the pickable children would silently drop the modifier. So the
generic dialog renders **the checkbox children as checkboxes** alongside the picker.
`executeSubmitAction()` already collects every checkbox in the manager via
`getActionsByAttr("checkbox", true)`, so their values reach the server unchanged whether the
user ticked them in a menu or in the dialog - no server-side change needed.

This is also the better UI: a checkbox inside a context menu is a well-known usability wart,
and it is what the share-dialog mockup independently removes too.


#### Threshold, and how an app overrides any of it

Default threshold: **15**, about a screenful. `DEFAULT_MAX_MENU_LENGTH = 50` stays what it is -
a pagination threshold, a different thing - so this is a separate constant. Worth defaulting
lower on mobile, where a long submenu is worse still.

**The override must NOT be `onExecute`, and this is a trap worth spelling out.** For a
container *with children*, `Nextmatch::egw_actions()` inherits `onExecute` down to the
children and then strips it off the parent:

```php
$action['children'] = self::egw_actions($action['children'], …, array_intersect_key($action, $inherit_keys));
if (!empty($action['default'])) unset($inherit_keys['onExecute']);
$action = array_diff_key($action, $inherit_keys);   // parent loses onExecute
```

That is exactly what timesheet and `projectmanager_ui` rely on to convert a whole submenu in
one line - but it means an app that sets `'onExecute'` on the container to "take over the
dialog" silently gets the opposite: the handler lands on every child and the container keeps
none. (`'default' => true` is the one exception, and abusing it for this would change the
double-click action too.)

So every override lives under `'data'`, which `egw_actions()` only ever writes to and never
strips. Four levels, each a strict superset of the one before:

**1. Move or disable the threshold** - one integer:

```php
'data' => ['maxMenuLength' => 40],     // this menu is fine up to 40
'data' => ['maxMenuLength' => 0],      // always use the dialog, however few children
'data' => ['maxMenuLength' => false],  // never collapse, keep the submenu at any size
```

**2. Tune the generated dialog** - it still builds and dispatches everything itself:

```php
'data' => ['selectDialog' => [
    'widget'   => 'et2-select-cat',   // default: et2-select for flat, et2-tree-dropdown for hierarchical
    'multiple' => true,
    'title'    => 'Move to addressbook',
    'okLabel'  => 'Move',
]],
```

**3. Swap the template** - same dispatch, entirely your own markup. The dialog's value is
returned to the generic handler, which still resolves the pick to a child action:

```php
'data' => ['selectDialog' => ['template' => '/myapp/templates/default/my_picker.xet']],
```

**4. Replace it completely** - your JS gets called instead of the dialog, with the collapsed
container:

```php
'data' => ['selectDialog' => ['onExecute' => 'javaScript:app.myapp.pickThing']],
```

The contract for level 4 is deliberately tiny, because the children are still real actions:

```ts
pickThing(action, senders)
{
    // action.children are the real EgwActions - hidden from the menu, not removed
    const chosen = /* whatever UI you like */;
    chosen.execute(senders);   // identical to the user having clicked the submenu entry
}
```

`chosen.execute()` is the same call the generic dialog makes, so a custom picker inherits each
child's own `onExecute`/`nm_action`/`confirm`/`enabled` for free - it cannot accidentally
diverge from what the submenu did. And the generic opener should be exported as a helper so
level 4 can call it with its own options rather than reimplement it:

```ts
SelectChildrenAction.open(this.egw, action, senders, {title: …, widget: …});
```

**One flag drives the menu side.** Server-side the threshold decision writes
`data['nm_action'] = 'select_children'`; `EgwAction.appendToTree()` skips the child recursion
when it sees that, so the entry renders as a leaf. Levels 2-4 all still produce that flag -
they only change what happens *after* the click - so the menu looks the same in every case.

#### What it does not do

* **It does not reduce payload.** All children are still serialized into every `get_rows`
  response - that is the thing Proposals A and B fix by replacing children with a compact
  option list. D is a usability fix, A/B are usability *and* payload fixes.
* **It cannot express verbs.** One pick, one action. Add/Remove/Replace on categories, or
  Add-to/Remove-from on distribution lists, need the bespoke dialogs. So D does **not**
  replace A or B - it is the safety net for everything A and B do not cover.
* **Double confirmation** is possible if both the container and the chosen child define
  `confirm`: `EgwAction.execute()` runs `_check_confirm()` on the container before the dialog,
  and again on the child after. None of the containers in scope set `confirm` today, but the
  new case should suppress the container's.

### Proposal E - tell the user an action will ask them something

Shoelace draws the submenu chevron itself, from the presence of `slot="submenu"` in
`EgwMenuShoelace.itemTemplate()`. So today the menu distinguishes exactly two states: "has a
submenu" (chevron) and "does something immediately" (nothing).

There is no third state for "this opens a dialog to collect options first" - and there are
already plenty of those, with no indication at all: every `nm_action => 'open_popup'` action
(infolog `Start date`, `Due date`, `Delegation`, `Links`; tracker `Group`; resources
`Delete`/`Un-delete`; importexport `Owner`, `Allowed users`; news_admin `Read permissions`;
`projectmanager_elements_ui` `Add existing`), and all of Proposals A-D once they exist.

**Proposal D makes this a requirement rather than a nicety.** A container that shows a chevron
today will, after D, show no chevron - it becomes a plain leaf. Without a replacement
affordance the entry ends up telling the user *less* than it does now. D has to put something
back.

#### Use the ellipsis, not a chevron-like glyph

A trailing `…` meaning "this command needs more input before it happens" is the long-standing
convention in every desktop HIG (Apple, Microsoft, GNOME), so it needs no explanation. A
chevron - or anything resembling one - would be wrong here: a chevron means "there is more
menu, you are still in the menu", a dialog is a different kind of transition, and conflating
the two costs the chevron its current meaning. (The `slot="suffix"` an icon would use is also
already taken by keyboard shortcuts.)

#### Derive it, never declare it

Append it in `EgwMenuShoelace.itemTemplate()` from the item's resolved `data.nm_action`
(`select_children`, `open_popup`, and the Proposal A-C dialog actions), rather than letting
each action opt in. Derived, it cannot drift from what the action actually does, and no app
has to remember it.

**Do not put it in the caption.** EGroupware does that today and it has already caused
translation drift: `mail/src/Ui.php` uses `'caption' => 'Folder Management ...'`, so the lang
files carry the dotted and dotless forms as two independent keys - which have since been
translated differently:

| key | `de` |
| --- | --- |
| `folder management` | Ordner-Verwaltung |
| `folder management ...` | Ordner-Verwaltung ... |
| `subscribe folder` | Abonnieren |
| `subscribe folder ...` | Ordner abonnieren ... |
| `edit account` | Konto Einstellungen |
| `edit account ...` | Konto bearbeiten ... |

Same concept, two different German strings, decided by whether someone typed three dots. The
punctuation is also inconsistent in the source: mail uses `' ...'` (with a space) in some
captions and `'...'` in others, invoices and schulmanager use `'...'`.

Appending at render time removes the whole class of problem: the ellipsis stops being
translatable content.

#### What must NOT get one

The indicator is only worth having if it stays meaningful:

* **Confirmation is not input.** Both Apple's and Microsoft's guidelines are explicit that a
  command which only asks "are you sure?" gets no ellipsis. So `confirm`,
  `confirm_multiple` and `confirm_mass_selection` actions (addressbook `Delete`, infolog
  `Close`, `merge`) must not get one - otherwise it degrades into "something will happen",
  which is no information at all.
* **Opening a window is not input.** `popup`/`location`/`egw_open`, and any `onExecute` that
  just opens a management UI, navigate to or open something - they do not gather options for
  the action you picked. No ellipsis, however the action happens to be wired.

**Decision (2026-09-23): apply that rule uniformly, including to the borderline cases.** Mail's
`Edit account ...` opens the account editor, so it loses its ellipsis - and so do its
siblings, which are the same kind of thing: `Subscribe folder ...`, `Folder Management ...`,
`Edit folder ACL ...`, `Set predefined values for compose...` (all `onExecute` handlers that
open a popup), plus `schulmanager_ui`'s `Noten-Details...` / `Kontaktdaten...`. Consistency is
worth more than preserving individually-defensible exceptions: an affordance that appears on
some window-openers and not others tells the user nothing.

Net effect: **every hardcoded ellipsis currently in a context-menu caption goes away**, and
the affordance comes back only where it is derived from real behaviour.

#### One limitation, and the escape hatch

Deriving from `nm_action` only covers actions whose behaviour is *declared*. An action that
opens an options dialog from inside an arbitrary `onExecute` JS method is invisible to it -
mail's folder-tree actions above are exactly that shape. The rule above happens to disqualify
all of today's examples, but the next one might genuinely qualify, so pair the derived cases
with an explicit opt-in (`'promptsForInput' => true`) that the same render code honours. Do
not let it become the default route: if an action needs the flag, that is usually a hint its
behaviour should have been declared in `nm_action` in the first place.

#### Cleanup this enables

* Delete the duplicate dotted lang keys (`folder management ...`, `subscribe folder ...`,
  `edit account ...` in `mail/lang/egw_{en,de}.lang`), keeping the dotless ones.
* Rename the ellipsis-only keys to their dotless form, carrying the translations over:
  `edit folder acl ...`, `set predefined values for compose...` (mail), `from template...`
  (invoices).
* Drop the hardcoded `.'...'` in `addressbook_ui::distribution_lists()` (two places) - that
  one is a tree label rather than a menu caption, but it is the same convention applied by
  hand.
* `mail/src/Compose.php`'s `'Upload files...'` is a compose-toolbar button, not a context-menu
  action - out of scope for this change, leave it.

### Out of scope: the share dialog (EGW-CE #43584)

That ticket replaces the whole `share` submenu - Share link, Writable, Share files, folder,
the mail options, and Collabora's writable online link - with one dialog. **Out of scope
here**, and not blocked by anything in this project: every `share/*` child already has an
`onExecute` or is a checkbox, so none of them submit.

One earlier draft of this doc confused it with addressbook's `shared_with/*`. They are
different features: `shared_with_<owner>` grants **another user access to the contact** inside
their own addressbook (written to `$contact['shared'][]`) and does still submit, so it stays
in this project - handled by Proposal D like any other oversized submenu. `share/*` creates an
**anonymous share link** and is the ticket's subject.

---

## 5. Phases

| Phase | Scope | Rationale |
| --- | --- | --- |
| 0 | **DONE.** `EgwApp.ajax_action()` + the `data.menuaction` convention; `api/tests/Etemplate/Widget/NextmatchActionSubmitTest.php` (baselined); the console warning when an action resolves to `nm_action: "submit"` unasked | Everything else depends on this `api` version; stops the pattern silently coming back |
| 1 | **DONE.** addressbook (`lists/*`, `merge`, `merge_duplicates`, `move_to/*`, `shared_with/*`, `change_type/*`, `undelete`, `delete`) and infolog (`close`, `close_all`, `change/{type,status,completion}/*`, `undelete`) | The two reported cases. Core repo |
| 2 | **DONE.** tracker (the biggest single list), calendar, filemanager x3, projectmanager x2 | Highest-traffic remainder. Cross-repo: tracker and projectmanager are separate |
| 3 | **Proposal D** - generic overflow dialog (`egw_actions()` threshold + `appendToTree()` skip + `select_children` case + one shared `.xet` + the 4-level `data['selectDialog']` override + `SelectChildrenAction.open()` as a reusable helper), **plus Proposal E** - the `…` affordance in `EgwMenuShoelace.itemTemplate()` | Independent of 1-2 and of each app. E ships with D because D removes the chevron those entries have today. E's menu half reaches legacy apps too |
| 4 | **Proposal A** - `Nextmatch::category_action()` picker dialog (both cardinality shapes), then move the reachable call sites | Biggest single reduction |
| 5 | **Proposal B** - distribution-list dialog | Addressbook-specific, needs the phase 1 endpoint. `move_to`/`shared_with` need no bespoke dialog - Proposal D covers them |
| 6 | The `open_popup` bucket | Needs the dialog to return values without a template submit - a different job |
| 7 | The remaining reachable apps: importexport, esyncpro, smallpart (`questions`), news_admin (`index`), stylite (`placetel`), mail's `copyto` | Low traffic, mechanical |
| - | admin, records, invoices, kanban, bookmarks, aitools, aiassistant, developer, stylite `calls`, webauthn, openid, phpbrain, schulmanager, smallpart `courses` | **Blocked on `et2-nextmatch-conversion.md`** - not scheduled here |

Phase 0 lands in `api` alone and gates everything after it. Phase 3 (Proposal D) is otherwise
independent - it dispatches whatever the child action already does, so it neither waits for
nor blocks the ajax conversion. Phases 4-5 build on the endpoints phase 1 establishes.

Everything happens on a feature branch per repo, targeting master only.

### How to verify

A code-reading pass is not enough here - the whole point is runtime behaviour. For each
converted action, in a real browser:

1. Scroll the list well down and select rows.
2. Run the action.
3. Assert the nextmatch widget instance is **the same object** afterwards, the scroll offset
   is unchanged, and the filters/search are unchanged.

The action manager can be walked at runtime to list what still resolves to submit:
leaf actions with `type === "popup"`, no `data.url`, no `data.egw_open`,
`data.nm_action === undefined`, and whose `onExecute.functionToPerform` is the controller's
own default executor (the function shared by the majority of actions - the one installed by
`setDefaultExecute()`).

---

## 6. Scope, dependencies and decisions

### Decided (2026-09-23)

| # | Decision | |
| --- | --- | --- |
| 1 | **Target: master only.** Not backported to `26` - it is a behaviour change across many apps, and `d3da314452` already covers the worst user-visible symptom there | settled |
| 2 | **Scope: every app in the `EGroupware` and `EGroupwareGmbH` orgs**, subject to the Et2Nextmatch prerequisite below. All 20 separate app repos checked are in one of those two orgs | settled |
| 3 | **The legacy `nm_action()` dispatcher is NOT being touched.** Apps still on the `<nextmatch>` widget wait for their `Et2Nextmatch` conversion (`doc/ai/projects/et2-nextmatch-conversion.md`) instead | settled |
| 4 | **The inventory harness comes back as a permanent test with a baseline** (phase 0) | settled |
| 5 | **All work happens on a feature branch**, not directly on master | settled |
| 6 | **Proposal D threshold: 15.** A named constant; per-action override via `data['maxMenuLength']`, with three further levels of override up to replacing the dialog outright | settled |
| 7 | **Proposal A offers Replace** (multi-category) as well as Add/Remove, and **Remove** on the single-category apps | settled |
| 8 | The sharing-UI ticket is **EGW-CE #43584**. It turns out to cover `share/*` (anonymous share links), **not** addressbook's `shared_with/*` - so it does not overlap this project's scope at all | settled |

### The Et2Nextmatch prerequisite

`Et2Nextmatch` routes through `Et2NextmatchActionController.executeNextmatchAction()`. The
legacy `<nextmatch>` widget routes through `nm_action()` in
`api/js/etemplate/et2_extension_nextmatch_actions.js` - a separate, hand-maintained `.js`
source carrying the *same* default at its line 29 and its own `case 'submit':` at line 232.

Per decision 3 that file is left alone, so **an app is only reachable once its list template
uses `<et2-nextmatch>`**. `EgwAction.appendToTree()` and `EgwMenuShoelace.itemTemplate()` are
shared, so Proposal E and the menu half of Proposal D do reach legacy apps; the `ajax_action`
conversion and Proposal D's `select_children` dispatch do not.

**Reachable now** - list template already `<et2-nextmatch>`:

| App | What is waiting |
| --- | --- |
| addressbook | 180 actions, incl. both category menus and all list actions |
| tracker | 83 |
| infolog | 76 |
| calendar | 7 |
| projectmanager (projects + elements + pricelist) | `cat/cat_*` (33), `sync_all`, `delete` x3, `undelete` |
| filemanager (`filemanager_ui`, `shares.xet`, `jobs.xet`) | `unlock`, `delete` x2 |
| mail | `copyto` only |
| importexport (`definition_index.xet`) | `copy`, `createexport`, `delete` |
| esyncpro | `policy/policy_*`, `wipe`, `delete` |
| smallpart (`questions.xet`) | `exempt`, `readd`, `delete` |
| news_admin (`index.xet`) | `update`, `delete` |
| stylite (`placetel.sipUsers.xet`) | `delete` |

**Blocked on the Et2Nextmatch conversion** - list template still `<nextmatch>`:

| App | Template | What is waiting |
| --- | --- | --- |
| admin | `customfields.xet`, `accesslog.xet`, `tokens.xet`, `categories.index.xet`, `acl.xet`, `index.xet`, `cmds.xet`, `remotes.xet` (10 legacy templates in all) | `delete` x2, `activate`, `revoke`, plus `admin/src/Groups.php` |
| records | `index.xet`, `admin.fields.xet` | `status/status_*` (6), `delete` x2 |
| smallpart | `courses.xet` | `copy_course`, `copy_no_participants` |
| invoices | `index.xet` | `delete` (the `downloadZIP-*` stay `postSubmit`) |
| kanban | `list.xet` | `copy` |
| bookmarks | `list.xet` | `delete` |
| aitools | `prompts.xet` | `delete` |
| aiassistant | `list.xet` | `separator`, `delete` |
| developer | `translations.index.xet` | `import`, `current`, `all`, `move_to_api`, `delete` |
| stylite | `calls.xet` | `reimport`, `delete`, `undelete` |
| news_admin | `cats.xet` | (source-read) |
| webauthn | `tokens.xet` | `delete` |
| openid | `access_tokens.xet` | `delete` x2 |
| phpbrain | `maintain_articles.xet`, `maintain_questions.xet` | `publish`/`delete` x2 |
| schulmanager | 14 legacy templates, 0 converted | `delete` |

That is most of the old phase 5-6 long tail, and it now sits behind a different project. Worth
feeding back into `et2-nextmatch-conversion.md`: **admin is the highest-value conversion
target**, because it unblocks the most actions here and is the most-used of the blocked apps.

### This spans 21 repositories

Only `api`, `addressbook`, `infolog`, `calendar`, `filemanager`, `mail`, `timesheet`, `admin`
and `importexport` live in the core repo. Separate repos, each needing its own commit on its
own branch: `tracker`, `projectmanager`, `records`, `policy`, `smallpart`, `invoices`,
`esyncpro`, `stylite` (the `epl` repo), `kanban`, `news_admin`, `bookmarks`, `phpbrain`,
`openid`, `webauthn`, `schulmanager`, `developer`, `aitools`, `aiassistant`, `rag`,
`collabora`.

Phase 2 is cross-repo from the start, because **tracker - the single biggest list at 83
actions - is a separate repo**. Any shared-API change (phases 0, 3) lands in `api` first and
every per-app commit depends on that version, so the `api` change should go in on its own and
be verified before the per-app sweep starts.

### Phase 1 - as built

Both apps' actions now carry `'onExecute' => 'javaScript:app.<app>.ajax_action'`. Setting it on
a *container* (`to_list`, `move_to`, `shared_with`, `change_type`, and infolog's
`change/type|status|completion`) converts every generated child in one line, exactly as the
inheritance lever promises.

Deliberately not set on infolog's `change` container itself: it also holds the
`nm_action => 'open_popup'` children (`startdate`, `enddate`, `responsible`, `link`), and an
inherited `onExecute` runs *instead of* the default executor, so their popups would never open.
Set it on the individual sub-containers instead.

Four things this turned up:

* **`EgwApp.ajax_action()` had to grow a checkbox guard.** `egw_actions()` applies inherited
  attributes with `$action += $default_attrs` to *every* child, checkbox children included - so
  `move_to`'s "Copy instead of move" and `shared_with`'s "Share writable" inherited the handler
  and would have fired a real action on being ticked. The method now returns early for
  `_action.checkbox`, the same guard `executeNextmatchAction()` opens with.
* **Checkbox values had to be sent.** A submit passes them; the first version of
  `ajax_action()` did not, which would have silently broken "Copy instead of move",
  "Share writable" and infolog's "Do not notify". They now travel as a 4th argument, collected
  the same way the controller collects them.
* **Two pre-existing bugs in `addressbook_ui::ajax_action()`**, both fixed: its 4th parameter
  was declared `$skip_notification` and passed straight into `action()`'s `$checkboxes` slot
  (harmless only while no converted action read it - `move_to_*` and `shared_with_*` both do),
  and its messages said "event(s)", copy-pasted from calendar. It also only sent
  `Response::message()`, not `egw.refresh()`, so a converted action would not have updated any
  row.
* **`select_all` was broken in infolog's `ajax_action()`** - it passed `[]` as the query, so
  `action()`'s `get_rows()` ran with no filters at all, ie. every InfoLog the user can see. It
  now passes the query `get_rows()` cached in the session, the same one `index()` restores on a
  submit.

`app.addressbook.action` was deleted: its only case was `delete`, its 4th argument
(`no_notifications`) referenced an action addressbook does not have, and the inherited
`ajax_action` does the job.

Not converted, with reasons: `view_org`/`view_duplicates` switch the list to a different rows
template rather than acting on the selection - they are not `action()` operations and a redraw
is defensible; `cat/*` is Proposal A's job; `export/*` and `kanban` belong to other apps.

### Phase 2 - as built

* **tracker** had no `ajax_action()` at all - added one wrapping its existing `action()`. Wired
  `close`, `close_100_<resolution>`, `change/seen`, `change/unseen` and the five generated
  sub-containers (`tracker`, `version`, `priority`, `status`, `resolution`, `completion`).
  Again not on `change` itself, which holds the `open_popup` children `assigned` and `group`.
* **calendar** had an `ajax_action()` that only sent `Response::message()` - it refreshed
  nothing, so a converted action would not have updated a row. Now calls `egw.refresh()`.
  Its 4th parameter had to accept both shapes: `EgwApp.ajax_action()` sends the checkbox array,
  while the older recur-prompt path in `calendar/js/app.ts` sends a plain bool for
  "Do not notify". Both verified live.
* **filemanager**: `unlock` goes through the existing `app.filemanager.action` ->
  `filemanager_ui::action()`, the same route `delete` already used. `filemanager_shares` and
  `Filemanager\Jobs` had no ajax endpoint and got one each.
* **projectmanager**: `delete`/`undelete` reuse `app.projectmanager.change_status`, already
  wired to its `ajax_action()`. `projectmanager_elements_ui`'s `delete`/`sync_all` needed two
  new cases in its static `ajax_action()`.

Two traps worth recording:

* **`filemanager_shares extends filemanager_ui`, whose `ajax_action()` is `static`** - adding a
  non-static `ajax_action()` there is an instant PHP fatal ("cannot make static method non
  static"), and making it static would shadow the inherited VFS endpoint, which has a totally
  different signature. Named `ajax_delete()` instead, with the menuaction given explicitly via
  `data['menuaction']`.
* **`projectmanager_pricelist_ui`'s `delete` is dead** and was left that way: the class extends
  `projectmanager_pricelist_bo`, not the UI class that dispatches `$content['nm']['action']`, so
  the submit re-renders and deletes nothing. Making it work is new functionality, not a
  transport change. It stays in the test baseline with that note.

### A live bug found on the way: the `msg-only-push-refresh` sentinel

`egw.refresh()`'s 2nd argument doubles as a "message only, push will deliver the rest"
sentinel, but several apps pass that same value as its 5th argument (`_targetapp`) too:

```php
$app = Api\Json\Push::onlyFallback() || $all_selected ? 'infolog' : 'msg-only-push-refresh';
Api\Json\Response::get()->call('egw.refresh', $msg, $app, $id, $type, $app, null, null, $msg_type);
```

`refresh()` resolves `_targetapp` at `egw_message.ts:392` - *before* `this.message()` on 394 and
before the msg-only early-return on 397 - and kdots' `egw_appWindow()` does
`this.loadApp(appname).iframe`, which throws for a name that is not an app. So the user never
sees the result message at all; it is not just console noise.

Confirmed live against infolog's `delete` action, which predates this work. Fixed here for
addressbook and infolog by keeping the sentinel in the 2nd argument only and always passing the
real app name as the 5th. Grepping the tree for the sentinel afterwards showed the spun-off task's scope was too wide:
calendar's `ajax_action()` never called `egw.refresh()` at all, and both projectmanager ones
already pass `null` as `_targetapp`. **Only timesheet** was still affected, and a parallel
session has since fixed and live-verified it.

### Found, not fixed: `close` and `close_all` are the same thing

`infolog_ui::action()` does `list($action, $settings) = explode('_', $_action, 2)`, so
`close_all` arrives as action `close` with settings `all` - and `case 'close'` ignores
`$settings`, calling `$this->close($id, '', false, ...)`. That third argument is `$closesingle`,
and `false` means *also close the sub-entries*. So plain "Close" closes subs too, and the two
actions are indistinguishable. Pre-existing, unrelated to transport, and changing it would
change behaviour users may rely on - left alone deliberately.

### Phase 0's regression test - as built

`api/tests/Etemplate/Widget/NextmatchActionSubmitTest.php`. 41 target classes; 40 reachable,
the one exception recorded in its `UNREACHABLE` const (`EGroupware\Mail\Ui::get_actions()`
reads `$this->mail_bo->getArchiveFolder()`, which needs a live IMAP/JMAP profile - mail is
covered by the live browser check instead, where it has exactly one fall-through).

Three things the throw-away version got wrong, all fixed here and worth not re-learning:

* **`newInstanceWithoutConstructor()` silently skipped the 7 apps that matter most** -
  addressbook, infolog, calendar, tracker, projectmanager, news_admin, mail - because their
  `get_actions()` reads members the constructor sets. The test now really constructs them
  (checked side-effect free: they build a bo/Etemplate and read config/prefs; `calendar_ui`'s
  `manage_states()` only *reads* saved states). Mail is the only one left, and it is asserted.
* **Admin-only lists depend on who runs the test.** `admin_categories` and `esyncpro_ui` throw
  `NoPermission\Admin` for a non-admin user, so that exception is caught and skipped rather
  than recorded - otherwise the baseline would not be portable between instances.
* **Action paths carry instance data.** `cat/cat_add/cat_add_sub_2255/...` embeds category ids
  and a tree depth that vary per install. `collapse()` reduces every nested path to its parent
  family, truncating at the first digit-bearing segment.

The remaining environment sensitivity is handled by only hard-failing in **one** direction:
a *new* fall-through fails the test; a baseline entry that does **not** appear only prints a
notice (with `EGW_TEST_VERBOSE`). Addressbook's `lists/*` actions, for instance, only exist
`if (($add_lists = $this->get_lists(Acl::EDIT)))` - a user with no editable distribution lists
never builds them, and that must not be a failure.

Verified it actually catches a regression by adding a bare `['caption' => ...]` action to
timesheet (a currently-clean app) and confirming the test named it, then reverting.

---

The original notes on why this harness is the right guard:

The throw-away harness that produced section 2 (call every app's real `get_actions()`, run it
through the real `Nextmatch::egw_actions()`, classify each resolved leaf) is the right
regression guard: it fails when a new action falls through to `nm_action: "submit"` without
asking for it, which is the whole bug class.

Making it permanent needs three things it did not have as a throw-away:

* An **expected-set baseline** rather than a printout, so it goes red only on *new*
  fall-throughs while the known ones are being worked through.
* Not using `newInstanceWithoutConstructor()`, which silently skipped 7 classes whose
  `get_actions()` touches constructor-initialised members. Either construct properly where
  that is side-effect free, or assert the list of classes it could not reach, so the gap stays
  visible instead of looking like a pass.
* Assertions on action **ids and shapes, never counts** - categories, lists, trackers and
  boards are instance data.

It also has to encode the false-positive classes from section 2: `select_all`,
`egw_copy`/`egw_paste`, and the top-level-vs-`data['nm_action']` distinction that made the
first run of it wrong.

---

## Secondary benefit

`get_actions()` output is regenerated and shipped on **every** `get_rows` response
(`$query['actions'] = $this->get_actions(...)`). Addressbook currently ships 421 action
definitions per response, most of them one-per-category or one-per-list entries. Proposals A
and B remove ~190 of them outright. Worth measuring before/after, but it is a side effect,
not the motivation.
