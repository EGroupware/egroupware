# Fast index render (`egw-app` renders `index.xet` before the server answers)

Status: experiment implemented and live-verified 2026-11-05 (addressbook, infolog, timesheet), uncommitted. Opt-in per app. First apps: addressbook, infolog, timesheet.
Mail and tracker are out of scope for now, see "Complications".

## Where the time goes today

`egw-app.firstUpdated()` -> `load(url)` does one `egw.request(ajax_exec)`. Nothing visible happens until all of this is serial:

1. PHP runs `<app>_ui::index()`, which includes the nextmatch's first `get_rows()`, and returns HTML (`<form id="app-index" class="et2_container">`) plus an `et2_load` command (`name`, `url`, `data`).
2. The response also pulls in the app's own `app.min.js` (`Framework::includeJS` from `Etemplate::exec()`).
3. `etemplate2.load()` awaits `langRequire` + `egw_ready`, creates an `Et2Template`, which fetches and parses the `.xet` (`templateCache` miss), builds the widget tree and waits for `loadFinished`.

Steps 2-3 (app JS, lang, `.xet` fetch) do not depend on step 1's data. Only the *data* does.

## Idea

When an app opts in, the framework page emits extra attributes on `<egw-app>`:
`index-template` (eg. `addressbook.index`), `index-url` (the server's cache-busted, preprocessed `.xet` url, same value `Etemplate::exec()` puts in `$load_array['url']`) and `index-lang` (the `langRequire` entry for the app).

`egw-app.firstUpdated()` then, **in parallel with the ajax request**:

- `langRequire`s the app's lang file and prefetches the app's `app.min.js` (`modulepreload`, emitted by PHP into the page head),
- builds a *preview*: a `<form class="et2_container" data-fast-index>` holding an `Et2Template` (`template` + `url`, empty content/sel_options arrays, **no etemplate2 instance, no `et2_ready`**), so app code never sees a half-initialised template,
- moves the `slot=` children (header/filter) to the egw-app the way `etemplate2._moveSlotted()` does.

When the real response arrives the preview is **kept on screen** while the real template loads underneath (hidden, same size). On the real template's `load` event the preview is removed in one frame. No flash of empty egw-app, and the second `.xet` parse is free (`Et2Template.templateCache`).

### Nice replacement rules

- Preview and real container ids differ (`<id>-preview`), preview widgets are `inert` (no clicks to dead handlers, no focus).
- `egw-app.waitForLoad()` / `_resizeChildren()` / `load()`'s child clearing ignore `[data-fast-index]`.
- If the ajax response is for a *different* template (eg. a favorite / `load=` url) or is an error, drop the preview immediately.
- If the real response is slower than ~N s the preview just stays (it is a read-only skeleton); it never blocks.
- Per-app opt-out/in switch: URL parameter `?fast_index=0|1` for A/B timing in the experiment.

### Template / widget enhancements we will need

The preview has no server content, so anything filled from `sel_options` is empty until the swap:

- `et2-select id="info_type"` etc. show only their `emptyLabel` - acceptable for the experiment.
- infolog types/status/filter, timesheet filter/status: candidates for a static option source (`StaticOptions` in `Et2Select`) or an `<option>` list in the `.xet`. Evaluate after measuring; only do it if the empty select is visibly wrong.
- nextmatch: preview renders header row + skeleton rows only (no `nm` content -> no dataprovider fetch). Verify `Et2Nextmatch` does not call the server with an empty `nm`.

## Measurement plan

Instance: nathan.egroupware.org via claude-in-chrome, under the dev queue. For addressbook, infolog, timesheet, each **cold** (empty cache, `Network.clearBrowserCache` / disabled-cache reload) and **hot** (second load, cache warm), 5 runs each, median:

- `performance.now()` at: first `<egw-app>` connected, first template widget in DOM (`et2-nextmatch` header painted), `egw_app_load_<name>` measure (needs debug level 4), first row painted, `load` event of the real etemplate.
- Resource waterfall: when `<app>.xet`, `app.min.js`, lang request and ajax_exec start/finish.
- Same script before and after, same session, A/B via `?fast_index=0/1` so the same build is compared.

Metric that matters: **time to first meaningful paint of the app's chrome (toolbar + table header)**, then time to rows.

## Complications (not in the experiment)

- **mail**: `index` is a splitter of tree + list + preview, the folder tree and account selection come from the server session. Needs a template with a stand-in tree; the nextmatch is a `mail`-specific one. Also JMAP/IMAP login happens in `index()`.
- **tracker**: private repo app, its index builds the header from per-queue sel_options and CF columns.
- Templates whose layout depends on server-computed `content` (column visibility via `@no_customfields` etc.): preview shows the default columns and may reflow on swap. Acceptable for list apps, a reason to keep it opt-in.
- Mobile (`templates/mobile/`): same attribute set, template url resolves through the same server code; verify separately.

## Opt-in mechanism

Experiment: a static map in `kdots_framework` (`addressbook`, `infolog`, `timesheet`) switchable by `?fast_index`. If it helps, decide the real mechanism (hook `fast_index` returning the template id, so non-core apps can opt in) - not before numbers exist.

## Result (2026-11-05, dev instance, `?fast_index` cookie 0 vs 1, `egw-app-*` performance marks)

Time after `egw-app` load start, warm cache, server idle (ajax_exec 150-600 ms):

| App | chrome without preview | preview shown | real content (preview on) |
|---|---|---|---|
| addressbook | 478-504 ms | 194-227 ms | 532-601 ms |
| infolog | 446-462 ms | 138-140 ms | 474-485 ms |
| timesheet | 224-238 ms | 182-203 ms | 286-313 ms |

Cold (hard reload, 16 MB transferred): addressbook 505 -> preview 188, real 506; infolog 663 -> preview 208, real 714.
On a loaded server (ajax_exec ~1 s) the gap grows: infolog preview 336 ms vs 1051-1176 ms.
`egw-app` itself only starts loading about 0.7-1.1 s into the page, that part is untouched.

Costs: real content arrives about 30-100 ms later with the preview on (it builds widgets at the same time).
Timesheet's server is so fast the gain is marginal.

## Lessons / bugs found on the way

- A nextmatch rendered without server data asked for rows with an empty `etemplate_exec_id`; the server answers an
  unknown id with a global redirect, so the framework reloaded in a loop (and stalled the server). `egw.dataFetch()`
  now does nothing without an exec id (test in `EgwData.test.ts`), the caller stays loading.
- `Et2Datagrid`'s empty state ("No entries to display") must not show in a preview; `Et2Nextmatch` passes
  `configurationLoading` for an `etemplate2` with `isPreview`.
- The preview is not registered in `etemplate2._byTemplate`, so `getById()`/`getByTemplate()` only find the real one.
- Slotted header widgets are moved to `egw-app` by a MutationObserver, `updateComplete` of the preview never waits for them.
- Testing: hard reloads and a stalled server (swap exhausted) looked like patch problems; check
  `egw.request` round trip time and `free -m` before blaming the change.

## Not done

- Preview selects (infolog type/status, timesheet filters, addressbook filter) show only their empty label.
- Left sidebox (favorites) appears at the swap, the content area width shifts then.
- Mobile, mail, tracker, tabs opened after login with a custom url.
- Real opt-in mechanism (hook) instead of the static map in `kdots_framework`.
- Preloading the app's `app.min.js` before the ajax response arrives.

## Log

- 2026-11-05: plan written, implemented, measured.
