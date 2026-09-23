# jQuery removal — usage inventory

Snapshot inventory of every file still referencing the literal `jQuery` identifier (this codebase's
fixed convention — jQuery is always accessed as `jQuery`, never aliased to `$`), across the whole
working directory: the main repo *and* every separate per-app EPL/stylite repo checked out alongside
it. Taken 2026-09-23 by grepping `*.js`/`*.ts`/`*.tsx`/`*.php`/`*.inc.php`/`*.xet`, excluding
`node_modules/`, `vendor/`, `.min.js`/`.min.ts`, and build-output dirs (`outdated*/`, `chunks/`, etc.).

This is a standing incremental-modernization target per `doc/ai/modernization.md` ("No new jQuery") —
fix opportunistically whenever you're already touching a listed function, don't hunt for these in
unrelated files. See `doc/ai/projects/jsapi-modernization.md` for the jQuery-removal swap table and
why removal in `api/js/etemplate`/`api/js/jsapi` was deliberately postponed there.

Raw scan: 27 apps had `jQuery` references, 204 file hits (167 candidate production usage, 26
test-harness/stub, 11 vendor library/plugin files). After review, the **114 real, in-scope usage
files sort into two usage-level tiers** below, plus a false-positive tier and an out-of-scope tier.

## Tier 1 — Heavy usage (widgets, framework core, or wraps a jQuery-based plugin)

Not simple swaps: either the app's widget/framework layer itself (deep coupling, many call sites),
or code that integrates a third-party library that is itself jQuery-based. Needs a real rewrite,
not a mechanical find/replace.

| App | Repo | Files | What |
|---|---|---|---|
| **api** | main | 69 | `js/jsapi` framework core, `js/egw_action`, the whole `js/etemplate` et2 widget/dataview family (`et2_core_*`, `et2_dataview*`, `et2_widget_*`, `et2_extension_*`), `login.js`, `historylog.rows.xet`, `src/Html.php`, `src/Json/Msg.php`. Removal deliberately postponed — see `doc/ai/projects/jsapi-modernization.md`. |
| **calendar** | main | 12 | et2 widgets (`et2_widget_view/planner/planner_row/daycol/timegrid/event.ts`, `View.ts`, `app.ts`), `sitemgr` week/planner PHP modules, `planner`/`export_csv_select` templates |
| **smallpart** | separate | 14 | et2 widgets + the question-type overlay plugins (`et2_smallpart_question_*.ts`) |
| **kanban** | separate | 4 | Custom widget implementation: `et2_widget_kanban_board.ts`, `et2_widget_kanban_card.ts`, `KanbanActions.ts`, `EditBoard.ts` — DOM-manipulation-heavy widget code, not app-level glue |
| **projectmanager** | separate | 1 | `js/et2_widget_gantt.ts` — wraps the `dhtmlx-gantt` library end-to-end (`jQuery.Deferred`, `jQuery.get`, `jQuery.extend` throughout); the widget's whole event/sizing model is jQuery-based |

**Subtotal: 100 files.**

## Tier 2 — Some usage, easy to replace — **DONE + live-verified (2026-09-23)**

Typical DOM manipulation in app-level code or `.xet` template `onclick`/`onchange` handlers —
`.show()`/`.hide()`/`.css()`/`.attr()`/`.focus()`/`jQuery.proxy()` one-liners. No widgets, no
jQuery-plugin integration. These were the "touch it, fix it" candidates per the standing
modernization rule, and all 14 files have now been converted to plain DOM/native JS (native
`querySelectorAll(...).forEach(...)`/`classList`/`.closest()`/`.bind()`/`fetch()`, `var`→
`const`/`let`, `function` expressions→arrow functions where they were being touched anyway).
Verified: `node --check` on the 3 `.js` files, `DOMDocument`-load well-formedness on all 11 `.xet`
files, and live-clicked in a real browser (boulder.egroupware.org) against addressbook's editname
popup and filemanager's superuser panel — both work, zero console errors.

**Bug found + fixed during live verification:** the first pass used `document.querySelector(...)`
(single element) as the literal swap for `jQuery(...)`, but jQuery's selector matches — and
`.css()`/`.hide()`/`.show()` acts on — **every** matching element, not just the first. This
codebase's legacy et2 grid rendering mirrors a widget's `class` attribute onto its wrapping `<td>`
too, so a class like `.superuser` or `table.editname` can genuinely match two elements (the widget
node and its cell wrapper) — `querySelector` silently updated only one of them, and in
`filemanager/templates/default/file.xet` the one it updated was an empty 0×0 wrapper, so the
"Superuser" button visibly did nothing (no console error either — a silent behavior bug, not a
crash). Fixed by switching every such call to `document.querySelectorAll(...).forEach(...)` across
all of Tier 2 (addressbook's 3 files, filemanager, managementserver), matching jQuery's actual
set-based semantics. Re-verified live after the fix — the superuser panel now shows/hides
correctly. **Lesson for any future jQuery→native swap in this codebase: always use
`querySelectorAll(...).forEach(...)`, never a bare `querySelector(...)`, unless the target is
looked up by a unique `id` or is provably a template's sole top-level element.**

**Pre-existing bug found AND fixed (2026-09-23), NOT caused by this work:** clicking "Vorschau"
(preview) in importexport's export/import dialogs threw `TypeError: Cannot read properties of null
(reading 'insertBefore')` inside `Et2Html.update`/lit-html internals, reproducing on a completely
fresh dialog. Root cause: `importexport/js/app.ts`'s `export_preview()`/`import_preview()` (part of
the earlier jQuery-removal pass on that file, unrelated to Tier 2) grabbed the preview panel's
`<et2-html id="preview-box" class="content">` via `preview.querySelector('.content')` — which
matches the `et2-html` itself, since its class is `content` — and mutated its children directly
with `replaceChildren()`/`insertAdjacentHTML()`/`textContent =`. `Et2Html` renders into the *light*
DOM via Lit (`createRenderRoot()` returns `this`), so that raw mutation corrupts Lit's internal
child-part bookkeeping; the crash hits the *next* time that widget's `.value` is set the proper way
(which the server response always does, via `setElementAttribute('preview-box', 'value', ...)`).
Fixed by setting the widget's `.value` through its own `set_value()` API instead of touching its
DOM. A second, related bug surfaced once the crash was gone: `preview.style.display = ''` (meant to
reveal the container) doesn't actually work — clearing an inline override doesn't help when nothing
else makes the element visible, and `preview_box` (an `et2-vbox`) computed to `display: none` with
no inline style at all. Changed to `preview.style.display = 'flex'` (explicit, matching what
jQuery's old `.show()` used to compute automatically). Both fixes live-verified end-to-end
(addressbook CSV export preview loads and closes correctly, zero console errors). **Confirmed
present in the `26` branch too** (`Et2Html.ts` and `importexport/js/app.ts` are byte-identical
between `master` and `26` before this fix) — 26 releases 2026-09-24, so this needs an explicit
decision on backporting.

| App | Repo | Files | What |
|---|---|---|---|
| **addressbook** | main | 3 | `templates/default/edit.xet`, `templates/mobile/{edit,view}.xet` — all just `jQuery('table...').css('display',...)`/`.focus()` |
| **importexport** | main | 3 | `templates/default/{import_dialog,export_dialog,export_csv_selectors}.xet` — `jQuery(this).parents(...).css(...)`, `jQuery('div.filters').show()` |
| **projectmanager** | separate | 2 | `templates/default/{export_elements_csv_selectors,export_csv_selectors}.xet` — `jQuery('div.filters').show()` |
| **webauthn** | separate | 1 | `js/login.js` — `jQuery.ajax(...)`, `jQuery('form').serialize()`, `jQuery('<input>').val().appendTo(...)` |
| **archive** | separate | 1 | `js/login.js` — `jQuery(() => ...)`, `.attr()`/`.appendTo()` form-building |
| **rocketchat** | separate | 1 | `js/realtimeapi.js` — `jQuery.proxy(...)` for 3 socket callbacks, trivial arrow-function swap |
| **filemanager** | main | 1 | `templates/default/file.xet` — `jQuery('.superuser').css(...)/.hide()` |
| **infolog** | main | 1 | `templates/default/export_csv_selectors.xet` — `jQuery('div.filters').show()` |
| **managementserver** | separate | 1 | `templates/default/trial.xet` — `jQuery('.progressBarHidden').show()` |

**Subtotal: 14 files.**

## Tier 3 — False positives

Grep hit, but not real jQuery usage. Kept here so a future scan doesn't re-flag them.

**Comment-only mentions** (every match on the line is inside a `//`/`*` comment, no real call):

| File | Note |
|---|---|
| `mail/js/compose.ts:766` | app's only hit — **mail drops out of scope entirely** |
| `kdots/js/EgwFrameworkApp.ts:1185` | app's only hit — **kdots drops out of scope entirely** |
| `notifications/js/app.ts:1039` | app's only hit — **notifications drops out of scope entirely** |
| `admin/js/app.ts` | 11/11 matching lines are comments; combined with `js/app.js` below, **admin drops out of scope entirely** |
| `policy/js/app.ts` | 2/2 lines are comments; combined with `js/app.js` below, **policy drops out of scope entirely** |
| `importexport/js/app.ts` | 2/2 lines are comments — real usage survives via Tier 2 templates |
| `projectmanager/js/app.ts` | 7/7 lines are comments — real usage survives via Tier 1/2 files |
| `rocketchat/js/app.ts` | 2/2 lines are comments — real usage survives via `realtimeapi.js` (Tier 2) |

**Stale, git-untracked build artifacts** (real jQuery code, but the file itself is dead — not
tracked in any repo (`git ls-files` confirms), dated 2021-07-19, superseded by a sibling `js/app.ts`
that has zero real jQuery usage of its own — see comment-only rows above for the three where
`app.ts` still has *some* jQuery mentions):

| File |
|---|
| `admin/js/app.js` |
| `policy/js/app.js` |
| `timesheet/js/app.js` |
| `resources/js/app.js` |
| `esyncpro/js/app.js` |
| `addressbook/js/app.js` *(app survives via Tier 2 templates)* |
| `importexport/js/app.js` *(app survives via Tier 2 templates)* |

General pattern worth remembering: **when an app has both `js/app.js` and `js/app.ts`, check
`git ls-files` on the `.js` before trusting a jQuery hit in it** — it may be a pre-TS-rewrite
leftover never cleaned off disk.

**Other stale dead files** (unrelated to the app.js/app.ts pattern):

| File | Note |
|---|---|
| `addressbook/templates/default/edit.old.old.xet` | unreferenced, superseded by `edit.xet` |
| `timesheet/js/app.old.js` | unreferenced, superseded by `app.js` (itself now excluded above) |

## Tier 4 — Apps out of scope

**Zero real usage left, purely as a consequence of the Tier 3 false positives above:**

`mail`, `kdots`, `notifications`, `admin`, `policy`, `timesheet`, `resources`, `esyncpro`

**Excluded by policy regardless of usage** (legacy, deprecated, third-party, or a vendored
duplicate of another in-scope app):

| App/path | Reason |
|---|---|
| `ranking` (separate repo) | old/legacy app, not an active removal target |
| `schulmanager` (separate repo) | third-party app |
| `wiki` (separate repo) | deprecated, master-only — see `project_wiki_app_deprecated` memory, never backport there |
| `etemplate` (separate repo, legacy etemplate1) | legacy et1 engine, superseded by `api/js/etemplate` (et2); not worth modernizing in place |
| `phpgwapi` (separate repo, legacy compat lib) | legacy compat shim; its one hit is inside a code comment anyway |
| `stylite/docker/src/{kanban,policy}/js/*` | byte-identical vendored copies of `kanban/`/`policy/` app source for the Docker build context — fixing the real apps and re-syncing covers these |

## Reference only (not part of the usage-tier classification)

### Vendor library code

This *is* jQuery/jQuery-UI/its plugins, not a usage site. Removing these means dropping/replacing
the dependency, not editing call sites.

- `api/js/jquery/jquery.noconflict.js`
- `api/js/jquery/barcode/jquery-barcode.js`
- `api/js/jquery/mousewheel/mousewheel.js`
- `api/js/etemplate/test/jquery.js`
- `api/js/egw_action/test/js/jquery.js`
- `api/js/egw_action/test/js/jquery-ui.js`
- `ranking/timer/js/jquery.simplemodal.js` *(separate repo)*
- `stylite/js/report/jqplot.js`, `stylite/js/report/jqplot.pieRenderer.js` *(separate repo, + a
  byte-identical duplicate pair under `stylite/docker/src/stylite/js/report/`)*

### Test-harness/stub references

Reference `jQuery` only to mock/stub it for unit tests of jQuery-dependent code — not production
usage.

`api/js/jsapi/test/` (17 files): `EgwFilesHarness.ts`, `EgwJsonHarness.ts`, `EgwCssStoreHarness.ts`,
`EgwOpenHarness.ts`, `EgwCalendar.test.ts`, `EgwJsStub.ts`, `EgwJson.test.ts`, `EgwDebug.test.ts`,
`EgwFiles.test.ts`, `EgwUtils.test.ts`, `EgwTooltipHarness.ts`, `EgwCoreHarness.ts`,
`EgwUtilsHarness.ts`, `EgwDebugHarness.ts`, `EgwTailHarness.ts`, `EgwDataHarness.ts`

Other stubs: `api/js/etemplate/CustomHtmlElements/test/pdf-player.test.ts`,
`api/js/etemplate/Et2Customfields/test/LegacyCustomfieldsVisibility.test.ts`,
`addressbook/js/test/AddressbookAppImportStub.ts`, `calendar/js/test/CalendarAppImportStub.ts`,
`filemanager/js/test/FilemanagerAppImportStub.ts`, `infolog/js/test/InfologAppImportStub.ts`,
`mail/js/test/MailAppImportStub.ts`, `notifications/js/test/NotificationsApp.test.ts`,
`projectmanager/js/test/ProjectmanagerAppImportStub.ts`, `stylite/js/test/StyliteAppImportStub.ts`
*(+ a docker-context duplicate)*

## Apps checked with zero hits

activesync, aiassistant, aitools, bookmarks, collabora, developer, emailadmin, example, guacamole,
home, hosts, invoices, knowledgebase, licenses, news_admin, openid, openwebui, phpbrain,
preferences, profitbricks, rag, records, registration, saml, setup, status, swoolepush, tracker,
untissync, usage, vidopro.

## Future idea (not started, blocked on Tier 1) — lazy-load `jQuery` proxy

Raised 2026-09-23: could `window.jQuery` become a `Proxy` that only loads the real library the
first time it's genuinely accessed (and logs that access)? Investigated as a **plan only** — nothing
to build yet, see why below.

- jQuery isn't a separate `<script>` today: `api/js/jquery/jquery.noconflict.js` does a static
  `import ".../jquery.min.js"` that rollup bundles as the literal first source file inside
  `egw.min.js` — the core jsapi bundle loaded on **every page**.
- Tier 1's heavy usage (the et2 widget core) lives in the same core bundle family
  (`etemplate2.js`), also loaded on virtually every app page — so on a typical page today, jQuery is
  likely touched almost immediately regardless.
- Hard constraint: jQuery is used **synchronously** everywhere here (inline
  `onclick="jQuery(...)"` template handlers, widget code expecting immediate DOM effects). A real
  on-demand load (`import()`/injected `<script>`) is async, so the first access can't be guaranteed
  ready in time — risking a silent break of whatever triggered it.
- This only becomes worth pursuing once Tier 1 (`api`, `calendar`, `smallpart`, `kanban`,
  `projectmanager`'s gantt widget) no longer depends on jQuery on the normal render path — until
  then, jQuery has to be present and synchronous on essentially every page anyway, so there's
  nothing to lazily defer. **Revisit after Tier 1 is addressed, not before.**
- If revisited: a two-phase shape sketched during discussion — Phase 1, a `Proxy` wrapping the
  already-eagerly-loaded real jQuery purely for access logging (zero behavior change, gathers real
  usage data); Phase 2 (only if Phase 1 data supports it), genuine on-demand loading via a
  synchronous XHR+eval fallback on first real access, to preserve the synchronous-call guarantee.
  Neither phase has been designed in detail or agreed on.

## Notes

- Several apps' `js/app.ts` rewrites still carry comments referencing former jQuery behavior
  ("native equivalent of jQuery's ...") even though the code itself no longer uses jQuery — a sign
  those files already went through modernization and just kept explanatory comments. A `jQuery`
  grep hit is not proof of a real dependency; always check whether the line is a comment first.
- Per `doc/ai/modernization.md`, none of this needs proactive cleanup — fix a file's jQuery only
  when it's already being touched for another reason, and don't turn an unrelated fix into a
  drive-by rewrite of the whole file.
