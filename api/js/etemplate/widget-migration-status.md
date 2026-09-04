# Legacy Widget → Webcomponent Migration Status

Generated 2026-09-02. Snapshot of every legacy `et2_widget_*.ts` / `et2_extension_*.ts` file
(under `api/js/etemplate/` and the app-specific `*/js/et2_widget_*.ts` files), matched against
the current set of Lit-based `Et2*` webcomponents (`api/js/etemplate/Et2*/`, registered as
`et2-*` custom elements).

## How to read the "State" column

| State | Meaning |
|---|---|
| **1a – shim** | A webcomponent exists. The `et2_*.ts` file's class is trivial — it only extends/re-exports the webcomponent for backwards compatibility (old `type="..."`/class-name still resolves, but there's no real legacy code left to remove other than the file itself). |
| **1a – generated** | Same as 1a-shim, but the file itself has been deleted (2026-09-03). The trivial re-export is synthesized at build/type-check/test time from a single manifest — see [`rollup-legacy-widget-shim.mjs`](./rollup-legacy-widget-shim.mjs) (wired into `rollup.config.js`), a `.d.ts` in [`legacy-shims/`](./legacy-shims/) for `tsc`/IDEs (only where a real consumer still imports the class by name - `toolbar`/`portlet` needed none), and [`webtest-legacy-widget-shim.mjs`](./webtest-legacy-widget-shim.mjs) (wired into `web-test-runner.config.mjs`) for the browser-test dev server. `et2_widget_dialog.ts` stayed a real 1a-shim file — it has actual behaviour beyond a bare re-export. |
| **1b – kept for a dependent** | A webcomponent exists, but the legacy file still holds real implementation code, because at least one *other still-legacy* file imports/extends it. |
| **1c – orphaned legacy** | A webcomponent exists (as a fully independent re-implementation), the legacy file still holds real implementation code, but nothing else in the legacy tree imports it any more — only `etemplate2.ts`'s bulk bundle import keeps it alive/registered. Safe-ish to remove once nothing renders `type="<old-name>"` any more. |
| **2 – not migrated** | No webcomponent equivalent exists yet. |
| **n/a – infrastructure** | Not a widget itself (base class, controller, interface, free-function helper). Listed for completeness with a note on what still depends on it. |

Widget-type resolution ([`et2_createWidget()`](./et2_core_widget.ts)) prefers the legacy
`et2_registry` entry over a same-named webcomponent when both exist for a given `type=` — so a
webcomponent's existence does **not** by itself mean the legacy path is dead; it only becomes
dead once the legacy type name is removed from the `et2_register_widget()` call (see the
`button`/`old-button` pattern below, which is already prepared for that cutover).

**The `api/etemplate.php` preprocessor** rewrites many legacy tag names to their `et2-*`
webcomponent equivalent server-side, before the browser (and `et2_createWidget()`) ever sees them
— see `ADD_ET2_PREFIX_LEGACY_REGEXP` and `ADD_ET2_PREFIX_REGEXP` (`box`/`hbox`/`vbox`/`vfs-select`).
Both are now **unconditional** — `<overlay legacy="true">`, which used to skip the second one, was
removed 2026-09-03. `api/templates/default/show_replacements.xet` was the last live template using
it, and turned out
to need zero XET changes to drop it, since the thing it was actually protecting
(`old-box`'s auto-repeat, see below) was never affected by the flag in the first place — `old-box`
was, and still is, unconditionally excluded from both regexes regardless. When a type is on one of
these lists, its legacy `et2_registry` entry is provably unreachable at runtime and it can be
treated the same as a 1a case, **provided none of its aliases are an `old-*` cutover-staging name**
— those are deliberately excluded from the rewrite (an explicit "give me the old behaviour" escape
hatch) and some are genuinely still used (`old-box` in 6 templates, `old-int` in smallpart), which
is why `box`/`number` stay real legacy files despite their base names being auto-rewritten
everywhere else. `old-hbox`, unlike `old-box`/`old-int`, had zero usage anywhere and `et2_hbox` had
no auto-repeat-style special case — with `legacy="true"` gone repo-wide, `hbox` became fully
unreachable and was moved to the 1a-generated treatment (2026-09-03, see table above).
**Also note:** a plain `grep -r` in this checkout silently skips every
`.gitignore`d app directory (`smallpart/`, `kanban/`, `tracker/`, `stylite/`, `rocketchat/`,
`records/`, `projectmanager/`, and more) even though they're present on disk — any "0 usage"
claim in this doc was re-verified with `find ... | xargs grep`, which doesn't have that blind
spot; anyone re-running these checks should do the same, and also exclude `*.old.xet` backup
files (created by this file's own `--in-place` CLI conversion mode, never live-served).
**Also note (2026-09-03):** "is `<X>` preprocessor-rewritten" is not the same question as "is
`et2_registry["X"]` reachable" — `Et2Widget.createElementFromNode()`
(`Et2Widget/Et2Widget.ts:979-980`) lets a widget's `type=` **attribute** win over its tag name
when picking a constructor, so `type="X"` on some *other*, already-`et2-`-prefixed tag (most
commonly `<et2-textbox type="integer">`) reaches `et2_registry["X"]` exactly like a bare `<X>`
tag would, without ever touching the bare-tag rewrite regex. Found via `int`/`integer`/`float`
(fixed, see below); worth re-checking for any other "unreachable" claim in this doc that was only
verified against bare tag names.

---

## Core widgets — `api/js/etemplate/`

| Widget type(s) | File(s) | Webcomponent exists? | State | Notes / evidence |
|---|---|---|---|---|
| `ajax_select`, `ajax_select_ro` | `et2_widget_ajaxSelect.ts` | No | 2-not-migrated | Full legacy impl, no `et2-ajax-select` tag. |
| `audio` | `et2_widget_audio.ts` | No | 2-not-migrated | Full legacy impl, no `et2-audio` tag. |
| `barcode` | `et2_widget_barcode.ts` | No | 2-not-migrated | Full legacy impl, no `et2-barcode` tag. |
| `countdown` | `et2_widget_countdown.ts` | No | 2-not-migrated | Full legacy impl, no `et2-countdown` tag. |
| `entry`, `contact-value`, `contact-account`, `contact-template`, `infolog-value`, `tracker-value`, `records-value` | `et2_widget_entry.ts` | No | 2-not-migrated | Full legacy impl; none of these types have webcomponent equivalents. |
| `grid` | `et2_widget_grid.ts` | No | 2-not-migrated | Full legacy layout-grid impl (~1264 lines); `et2-datagrid` is unrelated (nextmatch row rendering, not the `<grid>` layout widget). Still imported by `et2_extension_nextmatch.ts`. |
| `historylog` | `et2_widget_historylog.ts` | No | 2-not-migrated | Real impl (744 lines), no `et2-historylog` tag. |
| `hrule` | `et2_widget_hrule.ts` | No | 2-not-migrated | Small real impl, no `et2-hrule` tag. |
| `html` | `et2_widget_html.ts` | No | 2-not-migrated | Real impl, no plain `et2-html` tag. **Checked 2026-09-03 as a possible 1a-generated candidate: no.** Unlike `int`/`textbox`/etc., `html`/`htmlarea_ro` were never added to *any* preprocessor rewrite list at all (only sibling `htmlarea` is) - not a blind spot, just never migrated; dozens of live templates (`mail/display.xet`, `infolog/index.xet`, `tracker/edit.xet`, ...) use bare `<html>` and genuinely hit legacy `et2_html`. On top of that, `smallpart/js/overlay_plugins/et2_smallpart_overlay_html.ts` has a real structural subclass (`extends et2_html`, inheriting the real jQuery rendering) - shimming would silently swap its behaviour, not preserve it. Good candidate for an actual `Et2Html`/`Et2HtmlReadonly` webcomponent implementation (not a shim) as a future project - see the `htmlarea_ro` embedded-`<script>` note below for the one behavioural gap that'd need resolving first. |
| `itempicker` | `et2_widget_itempicker.ts` | No | 2-not-migrated | Real impl (393 lines), no `et2-itempicker` tag. |
| `placeholder-select` | `et2_widget_placeholder.ts` | No | 2-not-migrated | Real impl, no `et2-placeholder-select` tag. |
| `placeholder-snippet` | `et2_widget_placeholder.ts` | No | 2-not-migrated | Real impl (subclasses `et2_placeholder_select` in the same file), no webcomponent. |
| `progress` | `et2_widget_progress.ts` | No | 2-not-migrated | Real impl, no `et2-progress` tag. |
| `radio` | `et2_widget_radiobox.ts` | No | 2-not-migrated | Real impl, no `et2-radio` tag. |
| `radio_ro` | `et2_widget_radiobox.ts` | No | 2-not-migrated | Real impl, no `et2-radio_ro` tag. |
| `radiogroup` | `et2_widget_radiobox.ts` | No | 2-not-migrated | Real impl, no `et2-radiogroup` tag. |
| `script` | `et2_widget_script.ts` | No | 2-not-migrated | Small real impl (CSP-safe inline-script executor via `new Function()`), no `et2-script` tag. |
| `vfs` | *(deleted, no `.d.ts`)* | Yes (`et2-vfs-path`, `readonly="true"`) | 1a-generated | Was: real impl (clickable path breadcrumb bound to a stat-array value, apps/-path link-title lookup, default-action row wiring, `et2_IDetachedDOM`). **Implemented 2026-09-04**: rather than a new dedicated webcomponent, extended the existing `Et2VfsPath` (`Et2Vfs/Et2VfsPath.ts` - the editable path input already used standalone, eg. `filemanager.index.app-toolbar`) since its `readonly` mode already rendered the same clickable breadcrumb, including the apps/templates special-casing + async `link_title()` lookup, essentially unchanged from legacy. Added: a `fileInfo` property (sibling to `value`, which stays a plain path string for the class's own rendering) holding the full stat-array/row-value when `set_value()`/`setValue()` is given one - needed since real consumers (eg. `filemanager.ts`'s `select_clicked(event, widget)`) read `widget.value.is_dir`/`.path`/`.name`, updated to `widget.fileInfo.*`; `et2_IDetachedDOM` (getDetachedAttributes/getDetachedNodes/setDetachedAttributes) for nextmatch/datagrid row virtualization parity; a `set_value()` alias (legacy convention, needed for `Et2Widget.transformAttributes()`'s content-array auto-population, which specifically looks for that name); default click-to-open behaviour (`egw().open()`) when `readonly` and no explicit `onclick` is set, matching legacy's fallback - deliberately dropped the default-action/row-action-manager wiring (Enter-key trigger) as out of scope, per explicit direction (only the "editable" complexity needed avoiding, not the click-to-open one). New preprocessor block (`api/etemplate.php`, dedicated regex since it's a tag+attribute rewrite, not a simple prefix) converts bare `<vfs ...>` to `<et2-vfs-path readonly="true" ...>`. Live-verified: toolbar's existing editable use unaffected (`readonly=false`, untouched code path); a synthetic readonly instance renders the breadcrumb, keeps `fileInfo` correctly split from `value`, doesn't mutate `value` on click, and calls `egw().open()` with the right path/mime for both a directory segment and the final file segment. Real usages found: `filemanager/{config,select}.xet`, `ranking/templates/{default,mobile}/result.route.xet`, `stylite/templates/default/filemanager.file.versions.xet` (all `id="$row"`/`id="${row}"` nextmatch-row bindings); `api/templates/default/vfsSelectUI.xet` turned out to be dead code (no PHP controller or JS app object exists for it, only the `.xet`+`.old.xet` pair) - left as-is, harmless either way. `et2_widget_vfs.ts`'s own `et2_vfsUpload._addFile()` had an internal, easy-to-miss programmatic dependency (`et2_createWidget('vfs', ...)`, the exact same class of bug as the button/buttononly case) - fixed to create `'vfs-path'` instead. Also fixed `et2_vfsUpload.getDOMNode()`'s row-lookup, which read `sender.value.path` - now checks `sender.fileInfo` first, since a readonly `Et2VfsPath`'s own `getValue()`/`value` no longer carry the full object. |
| `video` | `et2_widget_video.ts` | No | 2-not-migrated | Real impl; uses the unrelated `multi-video`/`pdf-player` custom elements internally as helpers, but there is no `et2-video` widget-type replacement. |
| `button`, `buttononly`, `old-button`, `old-buttononly` | `legacy-shims/et2_widget_button.d.ts` (generated) | Yes (`et2-button`) | 1a-generated | Was: real, 460-line impl. **Checked 2026-09-04**: `button`/`buttononly` (plus `timestamper`/`button-timestamp`/`dropdown_button`) are unconditionally preprocessor-rewritten by a *third*, separately-located `api/etemplate.php` block (~line 452, found by grepping the raw string "button" - the two named `ADD_ET2_PREFIX*` constants don't cover it), so bare `<button>`/`<buttononly>` tags in templates were already never reaching the legacy class. `background_image` is resolved server-side too (picks `et2-button-icon` instead when appropriate, then strips the attribute) - not a client-side gap. `ro_image` has zero client-side implementation anywhere (`Et2Button`/`Et2ButtonIcon`), but its only two real usages repo-wide are a test fixture and the deprecated `etemplate` demo app - assessed as a narrow, accepted gap, not a blocker. `old-button`/`old-buttononly` had zero usage anywhere (unlike `old-box`/`old-int`). No inheritance dependents (`extends et2_button` : none). Two genuine runtime blockers found and fixed: `et2_extension_nextmatch.ts`'s and `smallpart/js/et2_widget_cl_measurement_L.ts`'s `et2_createWidget("buttononly", {...})` calls (no `et2-buttononly` custom element exists for `et2_createWidget()`'s registry-miss fallback to find) - both changed to `et2_createWidget("button", {..., noSubmit: true})`; `et2_widget_itempicker.ts`'s `this.button_action.click = fn` override (worked on legacy et2_button only because its own click wiring dynamically re-reads `this.click` on every native click; `Et2Button`/`ButtonMixin` has no such indirection, so the override would silently never fire) - changed to `addEventListener("click", fn)`, which works for either implementation. `tracker/js/app.ts`'s `iterateOver(..., et2_button)` instanceof-filter (looking for an "expand button") already matches nothing in production - `tracker/templates/default/escalations.xet` has no such button, and even if it did, real widgets are instances of `Et2Button` directly, never of the never-instantiated shim subclass - left as-is, not this deletion's concern to fix (same pattern as `et2_selectbox`'s already-shimmed equivalent filter in the same file). Live-verified against a real form (InfoLog edit: Save/Apply/Cancel/Delete all render as real `Et2Button`/`ET2-BUTTON`, Save round-trips a real `et2_process` POST, the delete confirmation dialog's Yes/No buttons work, deletion succeeds). Deleted 2026-09-04. |
| `file` | `legacy-shims/et2_widget_file.d.ts` (generated) | Yes (`et2-file`) | 1a-generated | Was: real, ~780-line impl (Resumable.js chunked upload, drag-and-drop, progress UI, mime/size validation). **Checked 2026-09-04**: `mail/js/compose.ts`, `et2_extension_customfields.ts`, `Et2Link/Et2LinkTo.ts` all turned out to be false positives (comment/CSS-class-string/CSS-selector, not real imports - the `Et2LinkTo.ts` `::slotted(.et2_file)` rule was already dead before this change too, since `Et2File` never applied that class). `filemanager/js/filemanager.ts`'s real `iterateOver(..., et2_file)` instanceof-filter already matches nothing (same already-dead pattern as other widgets' filters this session - `<file>` is unconditionally preprocessor-rewritten, and modern `Et2File` doesn't implement the legacy-only `remove_file()` this filter's callback calls). Full parity check (fork, this session) confirmed `Et2File`/`Et2File.ts` (940 lines) covers every real legacy behaviour, and found one genuine pre-existing gap: `handleBrowseFileClick()` only checked `disabled`, never `readonly` (legacy's `set_readonly()` explicitly unbound the browse click) - **fixed 2026-09-04**, also found and closed the same gap for drag-and-drop (`resumableFileAdded()`, which Resumable wires up via `assignDrop()` with no readonly check of its own). Deleted alongside `et2_widget_vfs.ts`'s `et2_vfsUpload` (see `vfs-upload` below), which was the only remaining dependent. |
| `textbox` | `legacy-shims/et2_widget_textbox.d.ts` (generated) | Yes (`et2-textbox`) | 1a-generated | Was: real, large impl. `et2_widget_number.ts` (formerly a dependent) was deleted 2026-09-03. Checked every app-level importer found earlier (`stylite`, `ranking`, `smallpart`, `filemanager`, `timesheet`): all were either false positives (CSS class strings, an unrelated same-named property in `et2_extension_nextmatch.ts`, comment-only mentions), `import type`-only, or (ranking) a real import used solely in TS casts - Babel-erasable either way. The one non-erasable usage, `filemanager/js/filemanager.ts`'s `iterateOver(..., et2_textbox)` instanceof-filter in the old `select_clicked()` handler, already matches nothing: the live `select.xet` declares that `id="path"` field as `<et2-vfs-path>`, not a textbox, and the very next line's plain `getWidgetById("path")` fallback is what actually does the work. Bare `<textbox>` was already preprocessor-rewritten unconditionally (like `int`/`integer`/`float`), no `type="textbox"` attribute-blind-spot instance exists anywhere, and nothing external still `extends et2_textbox` (that was `et2_number`, now gone). Deleted 2026-09-03. |
| `hidden` | `legacy-shims/et2_widget_textbox.d.ts` (generated) | Yes (`et2-hidden`) | 1a-generated | **Fixed 2026-09-03**: `<et2-textbox type="hidden">` still built (and merely CSS-hid) a full Shoelace `sl-input` - wasteful and never intended, same underlying issue as `int`/`integer`/`float` misusing `et2-textbox`. Added a genuine `Et2Hidden` webcomponent (`Et2Textbox/Et2Hidden.ts`, `Et2InputWidget(LitElement)` - no label/help-text/form-control chrome, just a real `<input type="hidden">` in its shadow DOM) and taught the preprocessor to rewrite both the bare `<hidden>` tag (added to `ADD_ET2_PREFIX_LEGACY_REGEXP`) and `<et2-textbox type="hidden">` (new dedicated regex pass, alongside the `int`/`integer`/`float` one) to it. Deleted alongside `textbox`/`textbox_ro`/`searchbox` 2026-09-03. |
| `textbox_ro` | `legacy-shims/et2_widget_textbox.d.ts` (generated) | Yes (`et2-textbox_ro`) | 1a-generated | Was: imported by legacy `et2_widget_number.ts` (`et2_number_ro extends et2_textbox_ro`), which was deleted 2026-09-03. Bare `<textbox_ro>` isn't a real tag (readonly is driven by the `readonly` attribute, not a separate tag name) and no `type="textbox_ro"` instance exists anywhere. Deleted alongside `textbox`. |
| `vfs-size` | *(deleted, no `.d.ts`)* | Yes (`et2-vfs-size`) | 1a-generated | Was: `et2_vfsSize extends et2_description`, real impl. `Et2VfsSize` (`Et2Vfs/Et2VfsSize.ts`) already existed independently (Shoelace `sl-format-bytes`-based, already live/tested in `mail`/`filemanager`) - the only blocker was `et2_widget_file.ts:617`'s `et2_vfsSize.prototype.human_size(...)` static-method call (a pre-existing `// TODO: Stop using et2_vfsSize` already flagged this in `Et2File.ts`, where the equivalent warning message had been silently disabled for the same reason). **Fixed 2026-09-04**: extracted the same algorithm as a standalone `humanFileSize()` export from `Et2VfsSize.ts`, used by both `et2_widget_file.ts` and `Et2File.ts` (which also got its disabled "file too large" warning restored). Considered rebuilding `Et2VfsSize` to extend `Et2Description` instead (matching legacy's inheritance, gaining `et2_IDetachedDOM` "for free") but decided to leave the working, tested, already-live component as-is. Deleted 2026-09-04. |
| `vbox`, `box`, `old-box` | `et2_widget_box.ts` | Yes (`et2-box`, `et2-vbox`) | 1c-orphaned | `et2_box` real impl; `et2-box`/`et2-vbox` (`Layout/Et2Box/Et2Box.ts`) are independent - **but not a full replacement**, see "`old-box` auto-repeat" below. `box`/`vbox` are preprocessor-rewritten almost everywhere, but `old-box` (the explicit legacy escape hatch, never rewritten) is genuinely used in 6 live templates incl. `home/templates/default/index.xet` — file must stay. |
| `details` | `et2_widget_box.ts` | Yes (`et2-details`) | 1c-orphaned | `et2_details extends et2_box`, real impl; `Layout/Et2Details/Et2Details.ts` is independent. Preprocessor-rewritten unconditionally (own dedicated regex) — moot anyway since the file stays for `old-box`. |
| `hbox`, `old-hbox` | `legacy-shims/et2_widget_hbox.d.ts` (generated) | Yes (`et2-hbox`) | 1a-generated | Was: real impl (`Layout/Et2Box/Et2Box.ts`'s `Et2HBox` is independent, including its align-cell behaviour - via CSS `:host([align])`/`::slotted([align])` selectors instead of JS-computed wrapper divs). `hbox` was previously blocked by `smallpart/templates/default/student.index.xet`'s `<overlay legacy="true">`; once that was gone (2026-09-03, see above) no other blocker remained: `old-hbox` had zero usage anywhere, and unlike `old-box`, `et2_hbox` has no `getType()`-branching special case at all. Deleted 2026-09-03; only real consumer (`smallpart/js/et2_widget_videooverlay.ts`) used it as a type annotation/cast only. |
| `htmlarea_ro` | `et2_widget_html.ts` | Yes (`et2-htmlarea_ro`) | 1c-orphaned | `Et2HtmlAreaReadonly` is independent. No legacy importer of `et2_html` (for this type) besides the bulk bundle. Not in the preprocessor's rewrite list at all (only bare `htmlarea` is) — file stays regardless since `html`/`htmlarea` (other types in the same file) are still fully legacy, and now also since `html`'s own real subclass dependent (see above) blocks the file structurally. Same class as `html` (`et2_register_widget(et2_html, ["html","htmlarea_ro"])` - literally one class, no branching), so redirecting `html` to `et2-htmlarea_ro` looks tempting, **but not verified safe**: `Et2HtmlAreaReadonly` renders via lit's `unsafeHTML()` (no script execution), while `et2_html.loadContent()` runs content through `egw_seperateJavaScript()` to extract and re-execute embedded `<script>` blocks. Whether any live `html`-typed field's *data* (not template) actually relies on that is unverified - would need checking before ever touching this. |
| `iframe` | `legacy-shims/et2_widget_iframe.d.ts` (generated) | Yes (`et2-iframe`) | 1a-generated | Was: real impl (191 lines). **Fixed 2026-09-03**: was genuinely just forgotten - added to `ADD_ET2_PREFIX_LEGACY_REGEXP`, and `Et2Iframe/Et2Iframe` was missing from `etemplate2.ts`'s webcomponent bulk-import (so `customElements.define("et2-iframe", ...)` never ran). Giving it its first live traffic surfaced two real bugs, fixed along the way: `Et2Iframe.ts` called a non-existent `.attribute()` DOM method (should be `.setAttribute()`) and wrote to the deprecated `.options` getter instead of its own reactive properties, and a third one found live-testing admin (`Et2Iframe.__getIframeNode()` crashing when called before the component's first render, from `transformAttributes()` during initial widget-tree construction). `mail/js/app.ts`'s body-loading code (`loadMessageBody`/`preparePrint`/mailvelope integration, ~8 call sites) assumed `widget.getDOMNode()`/`document.querySelector()` reach a real `<iframe>` directly, which doesn't hold once the real iframe lives inside `Et2Iframe`'s shadow root - centralized into a `getBodyIframe()`/`realIframeNode()` helper. Live-verified against a real HTML newsletter body and admin. Deleted 2026-09-03 - no `old-iframe` escape hatch existed, and its only real consumers (`calendar/js/app.ts`, `smallpart/js/app.ts`) used `import type` only. |
| `int`, `integer`, `float`, `old-int` | `legacy-shims/et2_widget_number.d.ts` (generated) | Yes (`et2-number`) | 1a-generated | Was: real impl (`et2_number extends et2_textbox`, min/max/step/precision/validator/`createInputWidget`). `old-int` was live in `smallpart/templates/default/student.index.xet`, fixed 2026-09-03 - zero usage anywhere after. Bare `<int>`/`<integer>`/`<float>` were already preprocessor-rewritten unconditionally, but that check missed a second path: `Et2Widget.createElementFromNode()` (`Et2Widget/Et2Widget.ts:979-980`) lets a `type=` **attribute** win over the tag name, so both `<et2-textbox type="integer">` (~30 templates, incl. `calendar/templates/default/edit.xet`'s `quantity` field) and a redundant leftover `type="float"` on an already-`<et2-number>` tag (7 instances, schulmanager/stylite) still routed to legacy `et2_number` via `et2_registry`. **Fixed and deleted 2026-09-03**: two new preprocessor passes strip/rewrite both cases (`api/etemplate.php`, next to the bare-tag rewrite). With all escape hatches closed and no remaining real (non-type-only) consumer, deleted; `calendar/js/app.ts`'s `import type` updated to the shim path. |
| `int_ro`, `integer_ro`, `float_ro` | `legacy-shims/et2_widget_number.d.ts` (generated) | Yes (`et2-number_ro`) | 1a-generated | Was: readonly variant of the above, same file. Deleted alongside it 2026-09-03. |
| `portlet` | *(deleted, no `.d.ts`)* | Yes (`et2-portlet`) | 1a-generated | Was: `et2_widget_portlet.ts`, real impl (435 lines). Zero live `.xet` usage anywhere (only hit was a stale `.old.xet` backup) and not preprocessor-rewritten either — genuinely dead. Deleted 2026-09-03. Nothing ever imported the class by name (only `etemplate2.ts`'s bulk-import side effect, also removed), so no `.d.ts` was needed. |
| `searchbox` | `legacy-shims/et2_widget_textbox.d.ts` (generated) | Yes (`et2-searchbox`) | 1a-generated | Was: `et2_searchbox extends et2_textbox`, real impl; `Et2Searchbox extends Et2Textbox` (webcomponent) fully independent. `et2_extension_nextmatch.ts`'s `this.et2_searchbox` property was a same-named-property false positive, not a real reference to this class. Bare `<searchbox>` was already preprocessor-rewritten unconditionally. Deleted alongside `textbox`. |
| `toolbar` | *(deleted, no `.d.ts`)* | Yes (`et2-toolbar`) | 1a-generated | Was: `et2_widget_toolbar.ts`, real 911-line impl. `toolbar` is preprocessor-rewritten unconditionally (8 live `.xet` files), no `old-toolbar` escape hatch exists. Deleted 2026-09-03. Nothing imported the class by name, so no `.d.ts` was needed. |
| `vfs-mode` | *(deleted, no `.d.ts`)* | Yes (`et2-vfs-mode`) | 1a-generated | Was: real impl (permission-string rendering, `text_mode()`). **Fixed 2026-09-03**: `vfs-mode` wasn't in `ADD_ET2_PREFIX_LEGACY_REGEXP` (unlike sibling `vfs-upload`) despite live usage (`filemanager/templates/default/home.rows.xet`) - same forgotten-entry pattern as `iframe`, fixed by adding it to the rewrite list, giving `Et2VfsMode` its first real traffic. **Deleted 2026-09-04**: zero external importers of the class by name anywhere (only a doc-comment mention in `Et2VfsMode.ts` itself, which already claims full port parity including sticky-bit handling) - now that the bare-tag rewrite is unconditional, nothing keeps the legacy class alive. |
| `vfs-upload` | *(deleted, no `.d.ts`)* | Yes (`et2-vfs-upload`) | 1a-generated | Was: `et2_vfsUpload extends et2_file` (legacy), real impl. **2026-09-04**: `_addFile()`'s own internal `et2_createWidget('vfs', ...)` call (a real, easy-to-miss programmatic dependency on the now-deleted `vfs` type) had to be fixed to `'vfs-path'` when `vfs` was deleted - same class of bug as the button/buttononly `et2_createWidget()` call sites. **Deleted 2026-09-04**: zero external importers of the legacy class by name anywhere; `Et2VfsUpload extends Et2File` (`Et2Vfs/Et2VfsUpload.ts`, 169 lines, already tested) is a full, independent replacement - actually *more* feature-complete than legacy (adds overwrite/rename/ask conflict-resolution UI legacy never had). Deleting this class removed the last `extends et2_file` anywhere in the codebase, which is what freed `et2_widget_file.ts`/`file` too (see above) - `et2_widget_vfs.ts` itself is now gone entirely, since nothing else in it (`et2_vfsName`/`et2_vfsMime`/`et2_vfsUid`/`et2_vfsGid` were all already pure `@deprecated` type re-exports with zero external importers) needed to stay. |
| `checkbox` | `legacy-shims/et2_widget_checkbox.d.ts` (generated) | Yes (`et2-checkbox`) | 1a-generated | Was: entire file `class et2_checkbox extends Et2Checkbox {}`, marked `@deprecated`. |
| `date`, `date_ro`, `date_duration`, `date_duration_ro`, `date_range` | `legacy-shims/et2_widget_date.d.ts` (generated) | Yes (`et2-date`, `et2-date_ro`, `et2-date-duration`, `et2-date-duration_ro`, `et2-date-range`) | 1a-generated | Was: trivial `@deprecated` subclasses of `Et2Date/*`. |
| `dialog`, `legacy_dialog` | `et2_widget_dialog.ts` | Yes (`et2-dialog`, `legacy-dialog`) | 1a-shim | File's own doc comment: "Just a stub that wraps Et2Dialog"; only compat glue remains. Kept as a real file — has actual behaviour (custom constructor, attribute-registry generation, its own `customElements.define`), not a bare re-export. |
| `diff` | `legacy-shims/et2_widget_diff.d.ts` (generated) | Yes (`et2-diff`) | 1a-generated | Was: entire file `class et2_diff extends Et2Diff {}`. |
| `—` (no `et2_register_widget` call) | `legacy-shims/et2_widget_htmlarea.d.ts` (generated) | Yes (`et2-htmlarea`) | 1a-generated | Was: `class et2_htmlarea extends Et2HtmlArea {}`. |
| `—` (no `et2_register_widget` call) | `legacy-shims/et2_widget_image.d.ts` (generated) | Yes (`et2-image`, `et2-appicon`, `et2-avatar`, `et2-lavatar`) | 1a-generated | Was: pure `@deprecated` type re-exports to `Et2Image`, `Et2AppIcon`, `Et2Avatar`, `Et2LAvatar`. |
| `—` (no `et2_register_widget` call) | `legacy-shims/et2_widget_link.d.ts` (generated) | Yes (`et2-link`, `et2-link-to`, `et2-link-apps`, `et2-link-entry`, `et2-link-entry_ro`, `et2-link-string`) | 1a-generated | Was: pure `@deprecated` type re-exports to the `Et2Link/*` family. |
| `—` (type alias, no `et2_register_widget` call) | `legacy-shims/et2_widget_selectAccount.d.ts` (generated) | Yes (`et2-select-account`, `et2-select-account_ro`) | 1a-generated | Was: pure `@deprecated` type re-exports. |
| `—` (no `et2_register_widget` call) | `legacy-shims/et2_widget_selectbox.d.ts` (generated) | Yes (`et2-select`, `et2-select_ro`) | 1a-generated | Was: `class et2_selectbox extends Et2Select {}` + a type re-export for the readonly variant. |
| `—` (type alias, no `et2_register_widget` call) | `legacy-shims/et2_widget_tabs.d.ts` (generated) | Yes (`et2-tabbox`, `et2-tab`, `et2-tab-panel`) | 1a-generated | Was: pure `@deprecated` type re-export. |
| `—` (type aliases, no `et2_register_widget` call) | `legacy-shims/et2_widget_taglist.d.ts` (generated) | Yes (`et2-select`, `et2-select-account`, `et2-email-tag`, `et2-category-tag`, `et2-thumbnail-tag`, `et2-select-state`) | 1a-generated | Was: 6 pure `@deprecated` type re-exports (account/email/category/thumbnail/state taglist variants). |
| `—` (no `et2_register_widget` call) | `legacy-shims/et2_widget_template.d.ts` (generated) | Yes (`et2-template`) | 1a-generated | Was: `class et2_template extends Et2Template {}`, empty body. |
| `—` | `et2_widget_description.ts` | n/a | n/a-infrastructure | No `et2_register_widget` call. 441-line real impl, now serves only as the base class `et2_widget_vfs.ts` extends for `vfs-size`/`vfs-mode`. The `et2-description` webcomponent is an unrelated, separate Lit implementation. |
| `—` | `et2_widget_dynheight.ts` | n/a | n/a-infrastructure | jQuery resize-helper utility, no `et2_register_widget` call. Still used by legacy `et2_extension_nextmatch.ts`. |

### Nextmatch / customfields family

| Widget type(s) | File(s) | Webcomponent exists? | State | Notes / evidence |
|---|---|---|---|---|
| `nextmatch_header_bar` | `et2_extension_nextmatch.ts` | No | 2-not-migrated | No `et2-nextmatch-header-bar` tag; concept appears absorbed into `Et2Nextmatch`'s own layout. Still referenced for real by `etemplate2.ts` (`instanceOf` check). |
| `customfields`, `customfields-list`, `customfields-filters` | `et2_extension_customfields.ts` | Yes (`et2-customfields`, `et2-customfields-list`, `et2-customfields-filters`) | 1b-kept-dependency | Real, 1170-line impl. Imported by legacy `et2_widget_historylog.ts` and `et2_extension_nextmatch.ts`. |
| `nextmatch` | `et2_extension_nextmatch.ts` | Yes (`et2-nextmatch`) | 1c-orphaned | `Et2Nextmatch` is a fully independent new implementation (Et2Datagrid/Et2RowProvider/Et2NextmatchDataProvider/Et2NextmatchActionController). No other legacy widget file imports `et2_nextmatch`, but `etemplate2.ts` core (not just the bulk import) still special-cases it in `refresh()` alongside a separate pass for `et2-nextmatch`. |
| `nextmatch-header` | `et2_extension_nextmatch.ts` | Yes (`et2-nextmatch-header`) | 1c-orphaned | `Headers/Header.ts` documents itself as "the webComponent counterpart of legacy `et2_nextmatch_header`". |
| `nextmatch-customfields` | `et2_extension_nextmatch.ts` | Yes (`et2-nextmatch-header-customfields`) | 1c-orphaned | Still referenced via `instanceOf`/casts from `Et2Nextmatch/ColumnSelection.ts` — a webcomponent-side file, not a legacy dependent. |
| `nextmatch-sortheader` | `et2_extension_nextmatch.ts` | Yes (`et2-nextmatch-sortheader`) | 1c-orphaned | `Headers/SortableHeader.ts` documents itself as the webComponent counterpart. |
| `—` (no importers found anywhere) | `et2_extension_itempicker_actions.ts` | n/a | n/a-infrastructure (dead code) | Exports `itempickerDocumentAction()`. Repo-wide grep found **zero** callers/importers, not even in `etemplate2.ts`'s bulk bundle — orphaned dead code, not active infrastructure. |
| `—` (no `et2_register_widget` call) | `et2_extension_nextmatch_actions.js` | n/a | n/a-infrastructure (shared) | Free functions (`nm_action`, `nm_open_popup`, `fetchAll`). Used by both legacy (`et2_extension_nextmatch_controller.ts`) **and** modern app code (`infolog/js/app.ts`, `calendar/js/app.ts`, `resources/js/app.ts`, `api/js/jsapi/egw_app.ts`). |
| `—` (no `et2_register_widget` call) | `et2_extension_nextmatch_controller.ts` | n/a | n/a-infrastructure (legacy-only) | Data/row controller for legacy nextmatch. Only imported by `et2_extension_nextmatch.ts`; `Et2Nextmatch` uses its own `Et2NextmatchActionController`/`Et2NextmatchDataProvider` instead. |
| `—` (no `et2_register_widget` call) | `et2_extension_nextmatch_rowProvider.ts` | n/a | n/a-infrastructure (legacy-only) | Only imported by `et2_extension_nextmatch.ts`. Superseded on the webcomponent side by `Et2Datagrid/Et2RowProvider.ts`. |

---

## `old-box` auto-repeat — a real feature gap, not just an unconverted template

Checked 2026-09-03 whether `Et2Box`/`Et2HBox`/`Et2VBox` (`Layout/Et2Box/Et2Box.ts`) have any
equivalent to `et2_box`'s `type="old-box"` auto-repeat feature, before considering `box`/`hbox`
for the same generated-shim treatment as the 1a batch. They don't, confirmed at two levels:

- **`Et2Box.ts` itself**: a plain `<slot>`-based flex wrapper. No `loadFromXML` override, no
  array-manager awareness, no repeat/loop logic of any kind.
- **The base `Et2Widget.loadFromXML()`** (`Et2Widget/Et2Widget.ts:932`, what `et2-box`/`et2-vbox`
  and plain `box`/`vbox` all actually run) - a plain linear loop that creates each child element
  exactly once. No `$`-in-id detection, no array-manager row-switching.

So the mechanism (`et2_widget_box.ts:56-125`) only exists inside `et2_box`'s own
`type === "old-box"` special case: the *last* direct child whose `id` contains `$` **and** whose
tag is `box`/`grid`/`et2-box`/a registered custom element is treated as a repeat template — for
every remaining content-array row beyond the widget's other static children, every array manager's
"current row" is pointed at that index and a fresh copy of the template child is instantiated.
Converting `old-box` to `et2-box` without this would not degrade gracefully - the templated child
would render **once**, with a literal unexpanded `${row}`-style id and no row data, silently
dropping the rest of the list.

**Where it's genuinely used** (the *only* thing keeping `et2_widget_box.ts` alive - `box`/`vbox`
themselves are preprocessor-rewritten everywhere else):

| Template | Repeated child |
|---|---|
| `home/templates/default/index.xet` (`id="portlets"`) | `<et2-portlet id="$row_cont[id]" .../>` — the home page's portlet list. Source has its own comment: `<!-- Box wrapper needed to get box to auto-repeat -->`. |
| `api/templates/default/show_replacements.xet` (3x: `placeholders`, `common`, `user`) | Now `<et2-box id="${row}"><et2-template template="..."></et2-template></et2-box>` — one repeat group per placeholder category. Was still `<box id="${row}">` (and the file still had `<overlay legacy="true">`) as of this section being written; both were dropped the same day, live-verified via addressbook's "Show replacements" dialog before and after - see the `api/etemplate.php` preprocessor note above. Confirms the repeat mechanism doesn't care whether the repeated child ends up a legacy `et2_box` or a real `Et2Box` instance. |
| `stylite/templates/default/link_search.search.xet` (`id="apps"`) | `<et2-box id="${row}"><template id="@app" .../></et2-box>` — one box per app in the link-search dialog. |

`timesheet/templates/default/timer.xet`'s two `old-box` usages (`specific_timer`, `overall_timer`)
are self-closing with no children at all - they use `old-box` as a plain leaf widget, unrelated to
auto-repeat.

**Likely correct migration path** (not attempted - real template-redesign work, left as a future
project): this framework's actual modern answer to "repeat N widgets from content-array data" is
`<grid>` (native per-row content-array iteration) or `<et2-nextmatch>`/`<et2-datagrid>` for richer
cases, not `<box>`. `old-box`'s auto-repeat looks like a lightweight workaround for cases where a
full `<grid>` felt like overkill. Adding repeat support to `Et2Box` itself would mean baking this
legacy, array-manager-specific mechanism into a generic layout primitive - the more likely fix is
converting these three templates' repeat groups to use `<grid>` (or equivalent) instead.

---

## App-specific widgets

No app currently ships its own webcomponent directory — all `Et2*` webcomponents live under
`api/js/etemplate/`. So for these files the question is only "has a generic/shared webcomponent
absorbed this app-specific type" — in every case checked, the answer is no.

| Widget type(s) | File(s) | Webcomponent exists? | State | Notes / evidence |
|---|---|---|---|---|
| `calendar-daycol` | `calendar/js/et2_widget_daycol.ts` | No | 2-not-migrated | No matching tag. |
| `calendar-event` | `calendar/js/et2_widget_event.ts` | No | 2-not-migrated | No matching tag. |
| `calendar-planner` | `calendar/js/et2_widget_planner.ts` | No | 2-not-migrated | No matching tag. |
| `calendar-planner_row` | `calendar/js/et2_widget_planner_row.ts` | No | 2-not-migrated | No matching tag. |
| `calendar-timegrid` | `calendar/js/et2_widget_timegrid.ts` | No | 2-not-migrated | No matching tag. |
| `kanban-board` | `kanban/js/et2_widget_kanban_board.ts` | No | 2-not-migrated | No matching tag. |
| `kanban-card` | `kanban/js/et2_widget_kanban_card.ts` | No | 2-not-migrated | No matching tag. |
| `gantt`, `projectmanager-gantt` | `projectmanager/js/et2_widget_gantt.ts` | No | 2-not-migrated | No matching tag. |
| `smallpart-cl-measurement-L` | `smallpart/js/et2_widget_cl_measurement_L.ts` | No | 2-not-migrated | No matching tag. |
| `smallpart-color-radiobox` | `smallpart/js/et2_widget_color_radiobox.ts` | No | 2-not-migrated | No matching tag. |
| `smallpart-comment` | `smallpart/js/et2_widget_comment.ts` | No | 2-not-migrated | No matching tag. |
| `smallpart-videobar` | `smallpart/js/et2_widget_videobar.ts` | No | 2-not-migrated | No matching tag (`multi-video` custom element is an unrelated video-conferencing element, not a widget-type replacement). |
| `smallpart-videooverlay` | `smallpart/js/et2_widget_videooverlay.ts` | No | 2-not-migrated | No matching tag. |
| `smallpart-videooverlay-slider-controller` | `smallpart/js/et2_widget_videooverlay_slider_controller.ts` | No | 2-not-migrated | No matching tag. |
| `—` (no `et2_register_widget` call) | `calendar/js/et2_widget_view.ts` | n/a | n/a-infrastructure | `et2_calendar_view extends et2_valueWidget`: shared base (owner/start_date/end_date, loader div, date helper) for the 5 calendar widgets above. Still actively extended by all of them plus `calendar/js/app.ts`. |
| `—` (no `et2_register_widget` call) | `smallpart/js/et2_videooverlay_interface.ts` | n/a | n/a-infrastructure | Defines `OverlayElement`/`PlayerMode` types and the `et2_IOverlayElement` interface. Still imported by `et2_widget_videooverlay.ts`, `et2_widget_videooverlay_slider_controller.ts`, and all of `smallpart/js/overlay_plugins/*`. |

`stylite/docker/{src,dist}/kanban/js/et2_widget_kanban_board.ts` and `et2_widget_kanban_card.ts`
are byte-identical mirrors of `kanban/js/*` (build/vendor copies) — not separate widgets.

---

## Framework infrastructure (not itemized as widgets)

These files are the base architecture every legacy widget above still extends, or the low-level
grid-rendering engine legacy nextmatch is built on. They are not "a widget type" on their own, so
they aren't given 1a/1b/1c/2 states, but they cannot be removed until **all** legacy widgets that
depend on them (i.e. everything marked 1b/1c/2 above) are gone:

- **`et2_core_widget.ts`, `et2_core_baseWidget.ts`, `et2_core_DOMWidget.ts`,
  `et2_core_inputWidget.ts`, `et2_core_valueWidget.ts`, `et2_core_editableWidget.ts`,
  `et2_core_arrayMgr.ts`, `et2_core_common.ts`, `et2_core_inheritance.ts`,
  `et2_core_interfaces.ts`, `et2_core_legacyJSFunctions.ts`,
  `et2_core_phpExpressionCompiler.ts`, `et2_core_xml.ts`** — base classes / template-parsing
  infrastructure for the whole legacy widget tree.
- **`et2_dataview.ts`, `et2_dataview_controller.ts`, `et2_dataview_controller_selection.ts`,
  `et2_dataview_interfaces.ts`, `et2_dataview_model_columns.ts`, `et2_dataview_view_aoi.ts`,
  `et2_dataview_view_container.ts`, `et2_dataview_view_grid.ts`, `et2_dataview_view_resizeable.ts`,
  `et2_dataview_view_row.ts`, `et2_dataview_view_rowProvider.ts`, `et2_dataview_view_spacer.ts`,
  `et2_dataview_view_tile.ts`** — legacy nextmatch/grid rendering engine. Used by legacy
  `et2_extension_nextmatch*.ts` and `et2_widget_historylog.ts`, but **also still imported by the
  webcomponent side** (`Expose/ExposeMixin.ts`, `Et2Nextmatch/ColumnSelection.ts`,
  `Et2Nextmatch/Et2NextmatchActionController.ts`) — this engine is shared infrastructure, not
  purely legacy-only, and needs its own follow-up look before removal.

---

## Summary

| State | Count (rows) |
|---|---|
| 1a — shim | 1 |
| 1a — generated | 27 |
| 1b — kept for a dependent | 1 |
| 1c — orphaned legacy | 6 |
| 2 — not yet migrated | 33 |
| n/a — infrastructure | 8 (one of which, `et2_extension_itempicker_actions.ts`, is outright dead code) |

Rows count distinct widget-type groupings, not files (a few files register several types with
different states, e.g. `et2_widget_html.ts`, `et2_widget_number.ts`, `et2_widget_vfs.ts`).

**Reading the numbers:** roughly a third of catalogued legacy widget types (`html`, `radio*`,
`entry`, `grid`, `historylog`, `itempicker`, `progress`, `script`, plus the entire calendar/kanban/
projectmanager/smallpart app-specific set) still have **no** webcomponent at all. Of the types
that *do* have a webcomponent, the 1c ("orphaned legacy") group is the largest — these are prime
cleanup candidates: the webcomponent is a complete, independent replacement, and nothing else in
the legacy tree depends on the old file any more, so removing type name from
`et2_register_widget()` (and eventually the file) should be low-risk once templates using the bare
type name are confirmed to render fine via the `et2-*` fallback in `et2_createWidget()`.

---

## Deleted `et2_widget_*.ts` files (search landed you here for a reason)

If you searched for one of these filenames and found no file - it's not missing, it was
deliberately deleted as part of the "1a — generated" cleanup (2026-09-03). It still "exists" at
build/type-check/test time:

- **`rollup.config.js`** synthesizes the trivial `class et2_X extends Et2Y {}` re-export on the fly
  via [`rollup-legacy-widget-shim.mjs`](./rollup-legacy-widget-shim.mjs)'s `SHIM_MANIFEST`, for any
  real code that still imports the old name (`api/js/etemplate/et2_extension_nextmatch.ts`,
  `filemanager/js/filemanager.ts`, `calendar/js/app.ts`, etc.).
- **`web-test-runner.config.mjs`** does the same for browser tests, via
  [`webtest-legacy-widget-shim.mjs`](./webtest-legacy-widget-shim.mjs) (same manifest, different
  plugin API - a dev-server `resolveImport`/`serve` hook instead of rollup's `resolveId`/`load`).
- A same-named **`.d.ts` file** under [`legacy-shims/`](./legacy-shims/) (a real file, still on
  disk) gives `tsc`/IDEs a real declaration to resolve - every real consumer's import path was
  updated to `.../legacy-shims/et2_widget_checkbox` etc. so it still type-checks. The `.d.ts`
  itself re-exports from the real webcomponent using a `../` prefix, since it now lives one
  directory deeper than the widget it replaces.

`et2_widget_toolbar.ts` and `et2_widget_portlet.ts` are the two exceptions: nothing ever imported
those classes by name (only `etemplate2.ts`'s bulk `import './et2_widget_toolbar'` side-effect
line, now deleted along with the file), so they needed no manifest entry, no shim, and no `.d.ts` -
they're just gone, with `Et2Toolbar`/`Et2Portlet` staying registered via their own real, unrelated
importers (`etemplate2.ts` directly imports `Et2Toolbar`; `home/js/Et2Portlet*.ts` import
`Et2Portlet`).

| Deleted file | Legacy type(s) it provided | Replaced by |
|---|---|---|
| `et2_widget_button.ts` | `button`, `buttononly`, `old-button`, `old-buttononly` | `Et2Button` (`Et2Button/Et2Button.ts`) |
| `et2_widget_checkbox.ts` | `checkbox` | `Et2Checkbox` (`Et2Checkbox/Et2Checkbox.ts`) |
| `et2_widget_date.ts` | `date`, `date_ro`, `date_duration`, `date_duration_ro`, `date_range` | `Et2Date/*` |
| `et2_widget_file.ts` | `file` | `Et2File` (`Et2File/Et2File.ts`) |
| `et2_widget_diff.ts` | `diff` | `Et2Diff` (`Et2Diff/Et2Diff.ts`) |
| `et2_widget_hbox.ts` | `hbox`, `old-hbox` | `Et2HBox` (`Layout/Et2Box/Et2Box.ts`) |
| `et2_widget_htmlarea.ts` | (subclass only, no `et2_register_widget`) | `Et2HtmlArea` (`Et2HtmlArea/Et2HtmlArea.ts`) |
| `et2_widget_iframe.ts` | `iframe` | `Et2Iframe` (`Et2Iframe/Et2Iframe.ts`) |
| `et2_widget_image.ts` | (type re-exports only) | `Et2Image`, `Et2AppIcon`, `Et2Avatar`, `Et2LAvatar` |
| `et2_widget_link.ts` | (type re-exports + `et2_link_list` class) | `Et2Link/*` family |
| `et2_widget_number.ts` | `int`, `integer`, `float`, `old-int`, `int_ro`, `integer_ro`, `float_ro` | `Et2Number`, `Et2NumberReadonly` (`Et2Textbox/Et2Number*.ts`) |
| `et2_widget_portlet.ts` | `portlet` | `Et2Portlet` (`Et2Portlet/Et2Portlet.ts`) |
| `et2_widget_selectAccount.ts` | (type re-exports only) | `Et2SelectAccount`, `Et2SelectAccountReadonly` |
| `et2_widget_selectbox.ts` | (subclass + type re-export) | `Et2Select`, `Et2SelectReadonly` |
| `et2_widget_tabs.ts` | (type re-export only) | `Et2Tabs` (`Layout/Et2Tabs/Et2Tabs.ts`) |
| `et2_widget_taglist.ts` | (type re-exports only) | `Et2Select`, `Et2SelectAccount`, `Et2Email`, `Et2SelectCategory`, `Et2SelectThumbnail`, `Et2SelectState` |
| `et2_widget_template.ts` | (subclass only, no `et2_register_widget`) | `Et2Template` (`Et2Template/Et2Template.ts`) |
| `et2_widget_textbox.ts` | `textbox`, `hidden`, `textbox_ro`, `searchbox` | `Et2Textbox`, `Et2Hidden`, `Et2TextboxReadonly`, `Et2Searchbox` (`Et2Textbox/*.ts`) |
| `et2_widget_toolbar.ts` | `toolbar` | `Et2Toolbar` (`Et2Toolbar/Et2Toolbar.ts`) |
| `et2_widget_vfs.ts` | `vfs`, `vfs-size`, `vfs-mode`, `vfs-upload` (plus `vfs-name`/`vfs-mime`/`vfs-uid`/`vfs-gid` type re-exports, no `et2_register_widget`) | `Et2VfsPath` (readonly mode, for `vfs`), `Et2VfsSize`, `Et2VfsMode`, `Et2VfsUpload` (`Et2Vfs/*.ts`) |

`et2_widget_dialog.ts` looks like it should be on this list too, but **isn't** - it stayed a real
file (still 1a-shim, not 1a-generated) because it has actual behaviour beyond a bare re-export
(custom constructor, attribute-registry generation, its own `customElements.define`).

**Checked 2026-09-03 who still needs it:** no core/default app does any more. `policy`,
`addressbook` and `admin` are all already migrated to `Et2Dialog` directly; `home/js/app.ts`'s
only match was dead code inside a commented-out block. The remaining real (non-type-only)
consumers are all outside the default app set: `smallpart`, `ranking`, `profitbricks` (separate,
gitignored repos - invisible to a plain `grep -r`, see the blind-spot note above) and
`schulmanager` (in-repo but not a default app). This file is now purely a third-party
compatibility shim, not something any app we maintain here still needs.
