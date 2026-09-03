# Legacy Widget → Webcomponent Migration Status

Generated 2026-09-02. Snapshot of every legacy `et2_widget_*.ts` / `et2_extension_*.ts` file
(under `api/js/etemplate/` and the app-specific `*/js/et2_widget_*.ts` files), matched against
the current set of Lit-based `Et2*` webcomponents (`api/js/etemplate/Et2*/`, registered as
`et2-*` custom elements).

## How to read the "State" column

| State | Meaning |
|---|---|
| **1a – shim** | A webcomponent exists. The `et2_*.ts` file's class is trivial — it only extends/re-exports the webcomponent for backwards compatibility (old `type="..."`/class-name still resolves, but there's no real legacy code left to remove other than the file itself). |
| **1a – generated** | Same as 1a-shim, but the file itself has been deleted (2026-09-03). The trivial re-export is synthesized at build/type-check/test time from a single manifest — see [`rollup-legacy-widget-shim.mjs`](./rollup-legacy-widget-shim.mjs) (wired into `rollup.config.js`), its `.d.ts` sibling kept alongside for `tsc`/IDEs, and [`webtest-legacy-widget-shim.mjs`](./webtest-legacy-widget-shim.mjs) (wired into `web-test-runner.config.mjs`) for the browser-test dev server. `et2_widget_dialog.ts` stayed a real 1a-shim file — it has actual behaviour beyond a bare re-export. |
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
— see `ADD_ET2_PREFIX_LEGACY_REGEXP` (unconditional) and `ADD_ET2_PREFIX_REGEXP`
(`box`/`hbox`/`vbox`/`vfs-select`, skipped only for templates with `<overlay legacy="true">`, of
which there are exactly two live ones: `api/templates/default/show_replacements.xet` and
`smallpart/templates/default/student.index.xet`). When a type is on one of these lists, its
legacy `et2_registry` entry is provably unreachable at runtime and it can be treated the same as
a 1a case, **provided none of its aliases are an `old-*` cutover-staging name** — those are
deliberately excluded from the rewrite (an explicit "give me the old behaviour" escape hatch) and
several are genuinely still used (`old-box` in 6 templates, `old-int` in smallpart), which is why
`box`/`hbox`/`number` stay real legacy files despite their base names being auto-rewritten
everywhere else. **Also note:** a plain `grep -r` in this checkout silently skips every
`.gitignore`d app directory (`smallpart/`, `kanban/`, `tracker/`, `stylite/`, `rocketchat/`,
`records/`, `projectmanager/`, and more) even though they're present on disk — any "0 usage"
claim in this doc was re-verified with `find ... | xargs grep`, which doesn't have that blind
spot; anyone re-running these checks should do the same, and also exclude `*.old.xet` backup
files (created by this file's own `--in-place` CLI conversion mode, never live-served).

---

## Core widgets — `api/js/etemplate/`

| Widget type(s) | File(s) | Webcomponent exists? | State | Notes / evidence |
|---|---|---|---|---|
| `ajax_select`, `ajax_select_ro` | `et2_widget_ajaxSelect.ts` | No | 2-not-migrated | Full legacy impl, no `et2-ajax-select` tag. |
| `audio` | `et2_widget_audio.ts` | No | 2-not-migrated | Full legacy impl, no `et2-audio` tag. |
| `barcode` | `et2_widget_barcode.ts` | No | 2-not-migrated | Full legacy impl, no `et2-barcode` tag. |
| `countdown` | `et2_widget_countdown.ts` | No | 2-not-migrated | Full legacy impl, no `et2-countdown` tag. |
| `entry`, `contact-value`, `contact-account`, `contact-template`, `infolog-value`, `tracker-value`, `records-value` | `et2_widget_entry.ts` | No | 2-not-migrated | Full legacy impl; none of these types have webcomponent equivalents. |
| `grid` | `et2_widget_grid.ts` | No | 2-not-migrated | Full legacy layout-grid impl (~1264 lines); `et2-datagrid` is unrelated (nextmatch row rendering, not the `<grid>` layout widget). Still imported by `et2_extension_nextmatch.ts` and `et2_widget_hbox.ts`. |
| `historylog` | `et2_widget_historylog.ts` | No | 2-not-migrated | Real impl (744 lines), no `et2-historylog` tag. |
| `hrule` | `et2_widget_hrule.ts` | No | 2-not-migrated | Small real impl, no `et2-hrule` tag. |
| `html` | `et2_widget_html.ts` | No | 2-not-migrated | Real impl, no plain `et2-html` tag. |
| `itempicker` | `et2_widget_itempicker.ts` | No | 2-not-migrated | Real impl (393 lines), no `et2-itempicker` tag. |
| `placeholder-select` | `et2_widget_placeholder.ts` | No | 2-not-migrated | Real impl, no `et2-placeholder-select` tag. |
| `placeholder-snippet` | `et2_widget_placeholder.ts` | No | 2-not-migrated | Real impl (subclasses `et2_placeholder_select` in the same file), no webcomponent. |
| `progress` | `et2_widget_progress.ts` | No | 2-not-migrated | Real impl, no `et2-progress` tag. |
| `radio` | `et2_widget_radiobox.ts` | No | 2-not-migrated | Real impl, no `et2-radio` tag. |
| `radio_ro` | `et2_widget_radiobox.ts` | No | 2-not-migrated | Real impl, no `et2-radio_ro` tag. |
| `radiogroup` | `et2_widget_radiobox.ts` | No | 2-not-migrated | Real impl, no `et2-radiogroup` tag. |
| `script` | `et2_widget_script.ts` | No | 2-not-migrated | Small real impl (CSP-safe inline-script executor via `new Function()`), no `et2-script` tag. |
| `vfs` | `et2_widget_vfs.ts` | No | 2-not-migrated | Real base impl (file-attribute display); no plain `et2-vfs` tag exists (only prefixed sub-widgets, see below). |
| `video` | `et2_widget_video.ts` | No | 2-not-migrated | Real impl; uses the unrelated `multi-video`/`pdf-player` custom elements internally as helpers, but there is no `et2-video` widget-type replacement. |
| `button`, `buttononly`, `old-button`, `old-buttononly` | `et2_widget_button.ts` | Yes (`et2-button`) | 1b-kept-dependency | Real, large impl. Imported by `et2_extension_nextmatch.ts` and `et2_widget_itempicker.ts`. `old-button`/`old-buttononly` aliases are already in place for a future cutover of the plain `button` type to the webcomponent. |
| `file` | `et2_widget_file.ts` | Yes (`et2-file`) | 1b-kept-dependency | Real, ~780-line impl. `et2_widget_vfs.ts`'s `et2_vfsUpload extends et2_file` (registers `vfs-upload`); also imports `et2_vfsSize` from `et2_widget_vfs.ts` in the other direction. |
| `textbox`, `hidden` | `et2_widget_textbox.ts` | Yes (`et2-textbox`) | 1b-kept-dependency | Real, large impl. Imported by legacy `et2_widget_number.ts` (`et2_number extends et2_textbox`). |
| `textbox_ro` | `et2_widget_textbox.ts` | Yes (`et2-textbox_ro`) | 1b-kept-dependency | Imported by legacy `et2_widget_number.ts` (`et2_number_ro extends et2_textbox_ro`). |
| `vfs-size` | `et2_widget_vfs.ts` | Yes (`et2-vfs-size`) | 1b-kept-dependency | `et2_vfsSize extends et2_description`, real impl. Imported by legacy `et2_widget_file.ts`. |
| `vbox`, `box`, `old-box` | `et2_widget_box.ts` | Yes (`et2-box`, `et2-vbox`) | 1c-orphaned | `et2_box` real impl; `et2-box`/`et2-vbox` (`Layout/Et2Box/Et2Box.ts`) are independent. `box`/`vbox` are preprocessor-rewritten almost everywhere, but `old-box` (the explicit legacy escape hatch, never rewritten) is genuinely used in 6 live templates incl. `home/templates/default/index.xet` — file must stay. |
| `details` | `et2_widget_box.ts` | Yes (`et2-details`) | 1c-orphaned | `et2_details extends et2_box`, real impl; `Layout/Et2Details/Et2Details.ts` is independent. Preprocessor-rewritten unconditionally (own dedicated regex) — moot anyway since the file stays for `old-box`. |
| `hbox`, `old-hbox` | `et2_widget_hbox.ts` | Yes (`et2-hbox`) | 1c-orphaned | Real impl (`Layout/Et2Box/Et2Box.ts`'s `Et2HBox` is independent). `hbox` is preprocessor-rewritten *except* for `<overlay legacy="true">` templates — `smallpart/templates/default/student.index.xet` is one and uses bare `<hbox>` — file must stay. |
| `htmlarea_ro` | `et2_widget_html.ts` | Yes (`et2-htmlarea_ro`) | 1c-orphaned | `Et2HtmlAreaReadonly` is independent. No legacy importer of `et2_html` (for this type) besides the bulk bundle. Not in the preprocessor's rewrite list at all (only bare `htmlarea` is) — file stays regardless since `html`/`htmlarea` (other types in the same file) are still fully legacy. |
| `iframe` | `et2_widget_iframe.ts` | Yes (`et2-iframe`) | 1c-orphaned | Real impl (191 lines); `Et2Iframe/*` independent. **Fixed 2026-09-03**: was genuinely just forgotten - added to `ADD_ET2_PREFIX_LEGACY_REGEXP`, and `Et2Iframe/Et2Iframe` was missing from `etemplate2.ts`'s webcomponent bulk-import (so `customElements.define("et2-iframe", ...)` never ran). Along the way, found and fixed real bugs surfaced by giving it its first live traffic: `Et2Iframe.ts` called a non-existent `.attribute()` DOM method (should be `.setAttribute()`) and wrote to the deprecated `.options` getter instead of its own reactive properties; `mail/js/app.ts`'s body-loading code (`loadMessageBody`/`preparePrint`/mailvelope integration, ~8 call sites) assumed `widget.getDOMNode()`/`document.querySelector()` reach a real `<iframe>` directly, which doesn't hold once the real iframe lives inside `Et2Iframe`'s shadow root - centralized into a `getBodyIframe()`/`realIframeNode()` helper. Live-verified against a real HTML newsletter body. `et2_widget_iframe.ts` is now unreachable the same way `toolbar`/`portlet` were - a candidate for the same deletion treatment as a follow-up. |
| `int`, `integer`, `float`, `old-int` | `et2_widget_number.ts` | Yes (`et2-number`) | 1c-orphaned | `et2_number extends et2_textbox`, real impl; `Et2Number` independent. `int`/`integer`/`float` are preprocessor-rewritten unconditionally, but `old-int` (the legacy escape hatch) is genuinely used by `smallpart/templates/default/student.index.xet` — file must stay. |
| `int_ro`, `integer_ro`, `float_ro` | `et2_widget_number.ts` | Yes (`et2-number_ro`) | 1c-orphaned | Same as above, readonly variant; moot anyway since the file stays for `old-int`. |
| `portlet` | `et2_widget_portlet.d.ts` (generated) | Yes (`et2-portlet`) | 1a-generated | Was: real impl (435 lines). Zero live `.xet` usage anywhere (only hit was a stale `.old.xet` backup) and not preprocessor-rewritten either — genuinely dead. Deleted 2026-09-03, folded into the same generated-shim mechanism as the 1a batch (only needed to satisfy `etemplate2.ts`'s bulk-import side effect — nothing imports the class itself). |
| `searchbox` | `et2_widget_textbox.ts` | Yes (`et2-searchbox`) | 1c-orphaned | `Et2Searchbox extends Et2Textbox` (webcomponent) is fully independent; `et2_extension_nextmatch.ts` already uses the new `Et2Searchbox` webcomponent directly via `loadWebComponent()`, not this legacy class. Preprocessor-rewritten unconditionally — moot anyway since the file stays for `textbox`/`textbox_ro`. |
| `toolbar` | `et2_widget_toolbar.d.ts` (generated) | Yes (`et2-toolbar`) | 1a-generated | Was: real 911-line impl. `toolbar` is preprocessor-rewritten unconditionally (8 live `.xet` files), no `old-toolbar` escape hatch exists. Deleted 2026-09-03. |
| `vfs-mode` | `et2_widget_vfs.ts` | Yes (`et2-vfs-mode`) | 1c-orphaned | `Et2VfsMode` independent. **Not in the preprocessor's rewrite list** (unlike its sibling `vfs-upload`) despite 2 live `.xet` files using `<vfs-mode>` — same likely-oversight pattern as `iframe`. File stays regardless since `vfs`/`vfs-size` (other types in the same file) are still fully legacy. |
| `vfs-upload` | `et2_widget_vfs.ts` | Yes (`et2-vfs-upload`) | 1c-orphaned | `Et2VfsUpload extends Et2File` (webcomponent), fully independent. Preprocessor-rewritten unconditionally — moot anyway since the file stays for `vfs`/`vfs-size`/`vfs-mode`. |
| `checkbox` | `et2_widget_checkbox.d.ts` (generated) | Yes (`et2-checkbox`) | 1a-generated | Was: entire file `class et2_checkbox extends Et2Checkbox {}`, marked `@deprecated`. |
| `date`, `date_ro`, `date_duration`, `date_duration_ro`, `date_range` | `et2_widget_date.d.ts` (generated) | Yes (`et2-date`, `et2-date_ro`, `et2-date-duration`, `et2-date-duration_ro`, `et2-date-range`) | 1a-generated | Was: trivial `@deprecated` subclasses of `Et2Date/*`. |
| `dialog`, `legacy_dialog` | `et2_widget_dialog.ts` | Yes (`et2-dialog`, `legacy-dialog`) | 1a-shim | File's own doc comment: "Just a stub that wraps Et2Dialog"; only compat glue remains. Kept as a real file — has actual behaviour (custom constructor, attribute-registry generation, its own `customElements.define`), not a bare re-export. |
| `diff` | `et2_widget_diff.d.ts` (generated) | Yes (`et2-diff`) | 1a-generated | Was: entire file `class et2_diff extends Et2Diff {}`. |
| `—` (no `et2_register_widget` call) | `et2_widget_htmlarea.d.ts` (generated) | Yes (`et2-htmlarea`) | 1a-generated | Was: `class et2_htmlarea extends Et2HtmlArea {}`. |
| `—` (no `et2_register_widget` call) | `et2_widget_image.d.ts` (generated) | Yes (`et2-image`, `et2-appicon`, `et2-avatar`, `et2-lavatar`) | 1a-generated | Was: pure `@deprecated` type re-exports to `Et2Image`, `Et2AppIcon`, `Et2Avatar`, `Et2LAvatar`. |
| `—` (no `et2_register_widget` call) | `et2_widget_link.d.ts` (generated) | Yes (`et2-link`, `et2-link-to`, `et2-link-apps`, `et2-link-entry`, `et2-link-entry_ro`, `et2-link-string`) | 1a-generated | Was: pure `@deprecated` type re-exports to the `Et2Link/*` family. |
| `—` (type alias, no `et2_register_widget` call) | `et2_widget_selectAccount.d.ts` (generated) | Yes (`et2-select-account`, `et2-select-account_ro`) | 1a-generated | Was: pure `@deprecated` type re-exports. |
| `—` (no `et2_register_widget` call) | `et2_widget_selectbox.d.ts` (generated) | Yes (`et2-select`, `et2-select_ro`) | 1a-generated | Was: `class et2_selectbox extends Et2Select {}` + a type re-export for the readonly variant. |
| `—` (type alias, no `et2_register_widget` call) | `et2_widget_tabs.d.ts` (generated) | Yes (`et2-tabbox`, `et2-tab`, `et2-tab-panel`) | 1a-generated | Was: pure `@deprecated` type re-export. |
| `—` (type aliases, no `et2_register_widget` call) | `et2_widget_taglist.d.ts` (generated) | Yes (`et2-select`, `et2-select-account`, `et2-email-tag`, `et2-category-tag`, `et2-thumbnail-tag`, `et2-select-state`) | 1a-generated | Was: 6 pure `@deprecated` type re-exports (account/email/category/thumbnail/state taglist variants). |
| `—` (no `et2_register_widget` call) | `et2_widget_template.d.ts` (generated) | Yes (`et2-template`) | 1a-generated | Was: `class et2_template extends Et2Template {}`, empty body. |
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
| 1a — generated | 13 |
| 1b — kept for a dependent | 6 |
| 1c — orphaned legacy | 14 |
| 2 — not yet migrated | 34 |
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
- A same-named **`.d.ts` file** (a real file, still on disk) gives `tsc`/IDEs a real declaration to
  resolve, so `import {et2_checkbox} from "./et2_widget_checkbox"` still type-checks.

`et2_widget_toolbar.ts` and `et2_widget_portlet.ts` are the two exceptions: nothing ever imported
those classes by name (only `etemplate2.ts`'s bulk `import './et2_widget_toolbar'` side-effect
line, now deleted along with the file), so they needed no manifest entry, no shim, and no `.d.ts` -
they're just gone, with `Et2Toolbar`/`Et2Portlet` staying registered via their own real, unrelated
importers (`etemplate2.ts` directly imports `Et2Toolbar`; `home/js/Et2Portlet*.ts` import
`Et2Portlet`).

| Deleted file | Legacy type(s) it provided | Replaced by |
|---|---|---|
| `et2_widget_checkbox.ts` | `checkbox` | `Et2Checkbox` (`Et2Checkbox/Et2Checkbox.ts`) |
| `et2_widget_date.ts` | `date`, `date_ro`, `date_duration`, `date_duration_ro`, `date_range` | `Et2Date/*` |
| `et2_widget_diff.ts` | `diff` | `Et2Diff` (`Et2Diff/Et2Diff.ts`) |
| `et2_widget_htmlarea.ts` | (subclass only, no `et2_register_widget`) | `Et2HtmlArea` (`Et2HtmlArea/Et2HtmlArea.ts`) |
| `et2_widget_image.ts` | (type re-exports only) | `Et2Image`, `Et2AppIcon`, `Et2Avatar`, `Et2LAvatar` |
| `et2_widget_link.ts` | (type re-exports + `et2_link_list` class) | `Et2Link/*` family |
| `et2_widget_portlet.ts` | `portlet` | `Et2Portlet` (`Et2Portlet/Et2Portlet.ts`) |
| `et2_widget_selectAccount.ts` | (type re-exports only) | `Et2SelectAccount`, `Et2SelectAccountReadonly` |
| `et2_widget_selectbox.ts` | (subclass + type re-export) | `Et2Select`, `Et2SelectReadonly` |
| `et2_widget_tabs.ts` | (type re-export only) | `Et2Tabs` (`Layout/Et2Tabs/Et2Tabs.ts`) |
| `et2_widget_taglist.ts` | (type re-exports only) | `Et2Select`, `Et2SelectAccount`, `Et2Email`, `Et2SelectCategory`, `Et2SelectThumbnail`, `Et2SelectState` |
| `et2_widget_template.ts` | (subclass only, no `et2_register_widget`) | `Et2Template` (`Et2Template/Et2Template.ts`) |
| `et2_widget_toolbar.ts` | `toolbar` | `Et2Toolbar` (`Et2Toolbar/Et2Toolbar.ts`) |

`et2_widget_dialog.ts` looks like it should be on this list too, but **isn't** - it stayed a real
file (still 1a-shim, not 1a-generated) because it has actual behaviour beyond a bare re-export
(custom constructor, attribute-registry generation, its own `customElements.define`).
