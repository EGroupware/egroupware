# Et2Customfields + Nextmatch Customfields Header Migration Plan

## Scope

- Allowed edit scope for this task:
  - `api/js/etemplate/Et2Customfields/`
  - `api/js/etemplate/Et2Nextmatch/`
- No generated `.js` edits.

## Implementation approach

- Use **composition/controller** for shared customfield filtering and visibility state.
- Keep widgets/headers thin and delegate filtering logic to the controller.
- Store new customfield column visibility in a clear, structured format.
  - Datagrid column prefs remain the primary persistence path.
  - A legacy preference string is still written for compatibility with apps that inspect it.

## Steps (gated)

1. Baseline legacy behaviour capture (filtering + allowed fields + header visibility behaviour). **Done below.**
2. Add baseline legacy tests under clearly separated legacy path. **Done.**
3. Implement shared `Et2Customfields` controller + lightweight webcomponents.  Single field, list of fields with name/widget, list of filters.  Individual child components for each field should be in the lightDOM, either by making the whole widget in the lightDOM or creating & slotting. **Done for controller, list, list-row, and filter-state container.**
4. Add tests for new widgets matching baseline filtering contracts. **Done.**
5. Implement Nextmatch customfields header webcomponent using controller. **Done.**
6. Integrate with Datagrid column selection:
   - whole customfields column on/off
   - per-customfield on/off
   **Done.**
7. Add header/datagrid integration tests (non-overlapping with widget tests). **Done.**
8. Run targeted tests + report residual risks. **Done.**
9. Implement full customfield field type-to-widget mapping with options / settings. **In progress; TS implementation and mapper tests done, component browser verification pending generated JS rebuild.**

## Verification checklist per step

- Step 1: behavior matrix reflects legacy code paths. **Done.**
- Step 2: baseline tests pass and are clearly marked legacy. **Done.**
- Step 3: controller behavior covered by unit tests. **Done.**
- Step 4: new-widget tests match intended contracts. **Done.**
- Step 5: header can expose/apply per-field visibility. **Done.**
- Step 6: column selection updates both column hidden state and per-field state. **Done.**
- Step 7: integration tests pass for full + per-field toggles. **Done.**
- Step 8: targeted commands executed and reported. **Done.**
- Step 9: field rendering uses customfield type-specific widgets and applies options/settings. **In progress.**

## Step 1 baseline legacy behavior capture

Legacy source paths:

- `api/js/etemplate/et2_extension_customfields.ts`
- `api/js/etemplate/et2_extension_nextmatch.ts`

### Field metadata and values

| Area | Legacy behavior |
| --- | --- |
| Metadata source | Widget-local modifications (`modifications[widgetId]`) are read first. Global customfield settings (`modifications["~custom_fields~"]`) are used as fallback. |
| Global merge | Global values fill missing attributes. Global `fields` are not allowed to overwrite local `fields`; global `customfields` do not overwrite non-empty local `customfields`. |
| Default customfields id | A plain `<customfields>` widget defaults to id `custom_fields`. |
| Value key format | Customfield values use `#name` keys. The unprefixed name is used for visibility maps. |
| Row value lookup | If content has an entry for the widget id, only keys beginning with the customfield prefix are copied into widget value. Otherwise values are read directly from content entries named `#field`. |

### Filtering and visibility

| Input state | Legacy behavior |
| --- | --- |
| `fields` as string | Comma-separated names are converted to `{name: true}`. |
| Empty/missing `fields` | Legacy treats this as no explicit restriction: fields default visible, then `exclude`, tab, type-filter, and default-tab rules can limit visibility. |
| Explicit `fields` map | Existing truthy entries are respected, then narrowed by type-filter, `exclude`, tab, and default-tab rules. |
| `exclude` | Comma-separated unprefixed field names are forced hidden. |
| `type_filter="previous"` | Reuses the previous customfields widget type filter. The resolved filter becomes the new previous filter for later widgets. |
| `type_filter` string | Split by comma. |
| `type_filter` match | Fields with empty/missing `type2` or `type2 === "0"` are allowed. Otherwise `type2` may be a comma-separated string or array; any overlap with the filter allows the field. |
| `tab="panel"` | Uses the current tab panel id. A panel id matching `cf-default`, `cf-default-private`, or `cf-default-non-private` activates default-tab behavior and clears the explicit tab. |
| Field `tab` with no default-tab match | Field is visible only when `field.tab === widget.tab`. |
| Explicit widget `tab` with existing `fields` | Fields whose `field.tab` differs from `widget.tab` are forced hidden. |
| `cf-default` | Private and non-private fields are allowed, subject to existing visibility. |
| `cf-default-private` | Private fields are allowed; non-private fields are hidden when they were explicitly visible. |
| `cf-default-non-private` | Non-private fields are allowed; private fields are hidden. |
| `set_visible(fields)` | Accepts unprefixed names. Existing rows are shown or hidden and `options.fields[name]` is updated to the same boolean. |

### Allowed filter fields

| Context | Legacy behavior |
| --- | --- |
| `customfields-filters` | Non-select fields are skipped unless the field type is an installed app type. |
| Select filters | Field types starting with `select` are allowed. |
| App entry filters | Field type is allowed when `egw().link_app_list()` contains the type. |
| Filemanager | Explicitly excluded from app-entry filter allowance even when present in app list. |
| Filter widget defaults | `customfields-filters` sets `emptyLabel` to `all`, clears `needed`, enables `multiple`, and removes `rows`. |

### Nextmatch customfields header

| Area | Legacy behavior |
| --- | --- |
| Widget type | `nextmatch-customfields` extends the legacy customfields list widget. |
| Column width | Header table is forced to width `100%`. |
| Metadata source | If attributes do not include `customfields`, the header reads local modifications first and then global `~custom_fields~`. |
| Header render | Each customfield row renders a `nextmatch-sortheader` with id `#field` and label from the field label. Legacy filter/account/entry header creation is commented out, so all customfields now sort only in this path. |
| Global visibility refresh | On `loadFields()`, global `~custom_fields~.fields` replaces `options.fields` when present. |
| Empty `fields` on header | Created fields are made visible and recorded as `{field: true}`. |
| Hidden fields on header | If `options.fields` is non-empty and a field is false or missing, the field row is hidden. |
| Header `set_visible(fields)` | Applies visibility locally, then propagates the same unprefixed visibility map to other customfields list widgets in the same nextmatch. |
| Column caption | Column chooser caption is always translated `Custom fields`. |
| Header column name | `_getColumnName()` returns `headerId_#field_#field2` for visible fields. Empty `fields` means all fields are visible. |
| Global field state update | `_getColumnName()` writes every known field's boolean visibility into local modifications for the header id, or global `~custom_fields~` when local data is missing. |

### Legacy Nextmatch preference and column-selection behavior

| Area | Legacy behavior |
| --- | --- |
| Preference read | Nextmatch preferences are read before row template parsing. Visible entries beginning with `#` are converted into `~custom_fields~.fields` with unprefixed names. |
| Negated preferences | When preferences are negated, customfield visibility booleans are inverted from the visible list membership. |
| Display preferences | A customfields column is represented by the header widget id plus following `#field` entries. Matching entries set `widget.options.fields[field] = true`; missing fields are hidden. |
| Whole customfields column visibility | The single nextmatch customfields column is visible only when the column id is selected and the resolved field map is not empty. It is hidden when there are no customfield definitions. |
| Column chooser apply | Applying selection turns all customfields off, then turns on selected `#field` entries after the customfields column item. The widget `set_visible()` call updates both header and row customfields widgets. |
| Preference write | Saving column preferences stores the customfields column by widget id for the server-side column list, and stores each visible customfield separately as `#field`. |
| Size preference | Width/size preferences are saved against the customfields widget id, not per customfield. |

## Current implementation status

- Composition/controller decision finalized and implemented in `Et2CustomfieldsController`.
- The earlier `mode` switch has been removed from the controller/base widgets. Visibility now follows the clarified rule:
  fields default visible unless `fields`, `exclude`, `typeFilter`, `tab`, or default-tab rules limit them.
- Legacy baseline tests are in `api/js/etemplate/Et2Customfields/test/LegacyCustomfieldsVisibility.test.ts`
  with helper `api/js/etemplate/Et2Customfields/test/legacyVisibilityHelper.ts`.
- Legacy baseline coverage includes:
  - Visibility/filtering rules.
  - Non-Nextmatch customfield type-to-widget setup behavior for text, numeric, serial, radio, select,
    select-account, app-backed link-entry, filemanager, filter-mode skips, and button list skips.
- `et2-customfields-list` renders child field widgets in light DOM via `createRenderRoot()`.
- `et2-customfields-list` is also used as the Datagrid row renderer so selected `#field` values use the same readonly widget formatting as other list contexts.
- `Et2CustomfieldsHeader` implementation is complete:
  - Uses `Et2CustomfieldsController`.
  - Exposes `getCustomfieldSelectionItems()`, `getCustomfieldVisibility()`, and `setCustomfieldVisibility()`.
  - Renders visible customfields as nested `et2-nextmatch-sortheader` widgets with `#field` sort ids.
  - Preserves visibility overrides while hydrating metadata from template modifications.
- Datagrid integration is complete for the current contract:
  - Column selection lists nested customfields.
  - Selecting a child customfield keeps the parent customfields column visible.
  - Selected per-field state is applied to the header and row renderers.
  - Structured preferences store selected customfield names as `customFields: string[]` on the customfields column entry.
  - Legacy preference string writing is retained for compatibility.
- Targeted verification passed:
  - `npx web-test-runner --config web-test-runner.config.mjs api/js/etemplate/Et2Customfields/test/LegacyCustomfieldsVisibility.test.ts api/js/etemplate/Et2Customfields/test/Et2CustomfieldsController.test.ts api/js/etemplate/Et2Customfields/test/Et2CustomfieldsWidgets.test.ts api/js/etemplate/Et2Nextmatch/test/CustomfieldsHeader.test.ts api/js/etemplate/Et2Nextmatch/test/Et2DatagridColumnState.test.ts api/js/etemplate/Et2Nextmatch/test/ColumnSelection.test.ts api/js/etemplate/Et2Nextmatch/test/Et2Datagrid.test.ts`
  - Firefox: 73 passed, 0 failed.
  - Chromium: 73 passed, 0 failed.

## Component roles and legacy template usage

Legacy `et2_extension_customfields.ts` registered one implementation for
`customfields`, `customfields-list`, and `customfields-filters`. The webcomponent
migration intentionally splits that legacy implementation into role-specific
classes because edit rendering, nextmatch list rendering, row rendering, and
filter rendering now have different performance and compatibility constraints.

`rg --glob '*.xet' "customfields|customfields-list|customfields-filters|nextmatch-customfields"` shows these roles:

| Class / tag | Template usage found | Reason for existing |
| --- | --- | --- |
| `Et2Customfields` / `<customfields>` | Edit, print, and mobile view templates, including app-specific single customfield placements such as `id="#FieldName"` and generic edit tabs using `type_filter`. | Editable customfield container. It creates real child Et2 widgets, preserves `#field` value ids, and must expose child widgets through light DOM for legacy lookup, validation, and onchange paths. |
| `Et2CustomfieldsList` / `<customfields-list>` | Nextmatch row/body templates such as addressbook, calendar, infolog, projectmanager, resources, timesheet, tracker, plus some mobile read-only displays. | Read-only customfield display outside the datagrid row fast path. It still creates child Et2 widgets where list contexts need widget behavior and formatting. |
| `Et2CustomfieldsList` / `<et2-customfields-list>` in Datagrid rows | Authored as `<customfields-list>` in row templates and kept as the read-only list widget by `Et2RowProvider`. | Datagrid row rendering uses the same readonly widget formatting as normal list contexts. |
| `Et2CustomfieldsFilters` / `<customfields-filters>` | Legacy widget type exists in `et2_extension_customfields.ts`; no broad `.xet` usage was found in the current scan. | Filter-only customfield controls. It renders selectbox-style filters for select/app-backed fields and skips unsupported filter types. |
| `Et2CustomfieldsBase` | Internal superclass only. | Shared eTemplate hydration, row value lookup, light compatibility API, and visibility controller wiring for the concrete widgets. It should not grow rendering behavior. |
| `Et2CustomfieldsController` | Internal pure state helper used by widgets and `Et2CustomfieldsHeader`. | Owns normalization and visibility decisions so widgets/header stay aligned without sharing rendering code. It should remain DOM-free and widget-free. |
| `Et2CustomfieldsHeader` / `<nextmatch-customfields>` | Nextmatch header templates in addressbook, calendar, projectmanager, resources, timesheet, tracker, and tests. | Column header/chooser integration. It renders sort headers for visible customfields and coordinates per-field column visibility with datagrid preferences. |

Overlap guidance:

- `Et2Customfields` and `Et2CustomfieldsList` both create child widgets, but differ by editability and authored template role.
- `Et2CustomfieldsList` displays list values for both normal list contexts and Datagrid rows.
- `Base` and `Controller` should not duplicate business logic: `Base` adapts eTemplate widget lifecycle/data sources, while `Controller` computes normalized visibility state.

## Step 9 detailed sub-plan: field type-to-widget mapping with options/settings

### 9.1 Reconfirm legacy field-to-widget contract

- Re-read `api/js/etemplate/et2_extension_customfields.ts` field creation code and the existing legacy baseline helper. **Done.**
- Extract a table of legacy mappings for at least:
  - `text`, `url`, `email`, `date`, `date-time`, `time`, `int`, `float`, `percent`, `serial`
  - `select`, `select-account`, `select-cat`, `radio`, `checkbox`, `bool`
  - app-backed editable link-entry and readonly link types
  - `filemanager`
  - unsupported or custom types **Done below.**
- Capture the attributes/options legacy applies per type:
  - `label`, `noLang`, `readonly`, `statustext` / help text
  - `select_options`, `options`, `values`, `rows`
  - `multiple`
  - app/type-specific settings for account, category, links, files, and dates
  **Done below.**
- Decide which legacy-only behaviour remains intentionally unsupported in the webcomponent path and document it before implementation. **Done below.**

Legacy mapping findings:

| Customfield type | Legacy widget mapping / attributes |
| --- | --- |
| `text` | Editable maps to `textbox`; `rows > 1` maps to `textarea`; `len` maps to `size`, and `maxlength` when `rows == 1`; readonly maps to `description`. |
| `passwd` | Uses password textbox attributes: `type=password`, `viewable`, `plaintext`, `suggest`, `autocomplete`; field values can override those defaults. |
| `serial` | Maps to readonly `textbox`. |
| `int` | Maps to `number` with `precision=0`. |
| `float` | Maps to `number`; `len` maps to `size`. |
| `select` | Maps to select widget; `rows > 1` enables `multiple`; `field.values["@"]` becomes `searchUrl`. |
| `select-account` | Same as select plus `empty_label=Select` and optional `account_type`. |
| `date` | Keeps date widget and sets `data_format` from `field.values.format` or `Y-m-d`. |
| `date-time` | Keeps date-time widget and sets `data_format` from `field.values.format` or `Y-m-d H:i:s`. |
| `htmlarea` | Applies config, toolbar collapsed by default, width from `len`, height from `rows`; editable legacy widgets may be wrapped with `et2-ai`. |
| `radio` | Maps to `radiogroup`; empty-key option becomes widget label; remaining `field.values` become `options`. |
| `checkbox` | Preserves `ro_true` / `ro_false`; readonly non-edit/list contexts use the field label as `ro_true`. |
| `button` | Only rendered for editable `customfields`; skipped in list/filter contexts. Multiple button values create multiple legacy widgets. |
| app-backed types | Editable/filter contexts map to `link-entry` with `onlyApp`; readonly list/row contexts map to `link` with `app`; `field.values` become `searchOptions.filter` where search is used. |
| `filemanager` | Maps to upload/select file UI in editable `customfields`; list/filter paths keep only simple upload-style metadata. Deprecated `mime` / `max_file_size` map to `accept` / `maxFileSize`. |
| `url` | Keeps URL widget; list contexts set label to the field label. |
| unsupported/custom type | Legacy tries `et2-${type}` first, then legacy registry. Webcomponent path should use registered `et2-${type}` when available, otherwise fall back to `et2-description`. |

Step 9 implementation boundary:

- Implement direct widget mapping, common attribute normalization, options/settings propagation, and filter selectbox rendering.
- Use `Et2CustomfieldsList` for Datagrid row rendering.
- Do not port legacy multi-widget button and full editable filemanager compound UI in this pass; document as residual risk unless needed by tests.
- Do not add AI wrapping for textarea/htmlarea customfields in this pass unless the current component path already has an established helper for it.

### 9.2 Introduce a mapper module

- Add a small focused mapper under `api/js/etemplate/Et2Customfields/`, for example `Et2CustomfieldWidgetMapper.ts`. **Done.**
- Public contract:
  - Input: field name, field definition, current value, render context (`field`, `list`, `filters`, `row`), and readonly state.
  - Output: widget tag name plus normalized property/attribute object.
- Keep mapper pure and independently unit-testable. **Done.**
- For `Et2Customfields*` widgets, generally use the matching `et2-${type}` widget and match the requested readonly state. **Done for `et2-customfields`, `et2-customfields-list`, and `et2-customfields-filters`.**
- Prefer registered readonly widgets (`et2-*_ro`) when readonly rendering is requested and a readonly variant exists. **Done.**
- Fall back to `et2-description` only when the type is unsupported or no safe widget mapping exists. **Done.**
- Datagrid rows now use `Et2CustomfieldsList`, so row rendering uses the normal readonly widget mapping. **Done.**
- Preserve `#field` ids for generated child widgets. **Done.**
- Use the generated child widget's own `label` property for field labels instead of rendering a separate surrounding label where the child widget can own the label. **To do.**
  - This is especially important for editable `<customfields>` and list widget rendering so accessibility, readonly variants, and generated Et2 widget conventions stay consistent.
  - Datagrid row customfields now use readonly child widgets, so select-style fields can display labels instead of stored values.

Current mapper implementation:

- Added `api/js/etemplate/Et2Customfields/Et2CustomfieldWidgetMapper.ts`.
- Added mapper coverage in `api/js/etemplate/Et2Customfields/test/Et2CustomfieldWidgetMapper.test.ts`.
- Mapper test verification passed:
  - `npx web-test-runner --config web-test-runner.config.mjs api/js/etemplate/Et2Customfields/test/Et2CustomfieldWidgetMapper.test.ts`
  - Firefox: 5 passed, 0 failed.
  - Chromium: 5 passed, 0 failed.

### 9.3 Apply customfield options/settings

- Normalize customfield option sources in one place: **Done for mapper-supported values.**
  - `field.values`
  - `field.select_options`
  - `field.options`
  - legacy comma/string option forms if still emitted by setup code
- Map common option metadata to the target widgets: **Done for supported direct mappings.**
  - select/radio choices
  - multi-select state
  - account/category select settings
  - link-entry app target
  - file/path related settings
  - numeric/date formatting settings where current Et2 widgets support them
- Ensure values remain keyed by `#field` at the customfields boundary. **Done.**
- Do not derive field definitions from row values. **Preserved.**

### 9.4 Update list and filter widgets

- Replace the ad-hoc `_fieldWidgetType()` and `_fieldWidgetTemplate()` logic in `Et2CustomfieldsList` with mapper output. **Done in TS source.**
- Keep `et2-customfields-list` child widgets in light DOM. **Preserved.**
- Keep Datagrid row customfields aligned with `et2-customfields-list` readonly widget rendering. **Done.**
- Implement `et2-customfields-filters` rendering in this step. **Done in TS source.**
  - Render filter controls as selectboxes, following the legacy `customfields-filters` widget behavior. **Done for select/app-backed mapper output.**
  - Use the mapper with `filters` context to normalize the field definitions into select-style controls. **Done.**
  - Apply legacy filter defaults: `emptyLabel="all"`, `needed=false`, `multiple=true`, no `rows`. **Done.**
  - Skip non-select fields unless the field type is an installed app type; keep filemanager excluded. **Done.**
- Added editable rendering to `et2-customfields` TS source using the same mapper and light-DOM child widget approach.
- `et2-customfields` now skips unsupported/skipped mapper results entirely instead of rendering empty label/value rows.
- Generated JS is intentionally not edited; browser component tests importing extensionless component modules will use stale generated JS until a normal build regenerates it.
- Webcomponent authoring standards check completed:
  - Public customfield elements now include `@summary` docblocks and documented CSS parts.
  - Component styles use the documented `static styles = [...]` form.
  - No `public` class fields/methods are used.
  - `et2-customfields`, `et2-customfields-list`, and `et2-customfields-filters` intentionally render in light DOM so generated child widgets remain visible to legacy eTemplate lookup/validation/event paths; this is documented as the compatibility exception to the usual shadow-DOM styling convention.
  - Verified with `rg -n "public |static get styles|@customElement|@summary|@csspart|part=|_lightDomStylesTemplate" api/js/etemplate/Et2Customfields/*.ts`.

### 9.5 API compatibility and cleanup

- Mark legacy compatibility methods that keep snake_case names as deprecated in source docs. **To do.**
  - `Et2CustomfieldsBase.set_visible()` is kept for legacy widget callers and should delegate to `setCustomfieldVisibility()`.
  - Any future snake_case compatibility shims should get the same `@deprecated` treatment and point to the camelCase replacement.
- Clarify `Et2CustomfieldSelectionItem` naming and contract. **To do.**
  - It resembles `SelectOption` from Et2Select only superficially because both have labels.
  - It is not a select option: it represents customfield column/visibility state with `name`, `label`, and `visible`.
  - Do not add `value`, nested option fields, or Et2Select-specific semantics unless the type is intentionally replaced with a real select option contract.
- Reduce controller helpers by tightening normalization before controller construction. **To do after generated widget mapping stabilizes.**
  - Prefer canonical unprefixed field names before calling the controller.
  - Prefer `fields` as a normalized object internally; string parsing can stay at `transformAttributes()` / header adapter boundaries.
  - Prefer normalized `customfields` objects keyed by canonical name; array/object-entry compatibility should be documented if still required by server/template data.
  - Keep `typeFilter="previous"` handling only if legacy template scans or tests still require it.
- Document any helper that remains in `Et2CustomfieldsController` with its compatibility reason. **To do.**
  - `_normalizeCustomfields()` / `_canonicalFieldName()` / `_lookupAlias()` currently exist because legacy/server data can arrive as object maps, arrays, or entries where the outer key differs from `field.name`.
  - `_normalizeExplicitFields()` and `_normalizeExclude()` exist because legacy widgets accept CSV strings and unprefixed maps.
  - `_normalizeTypeFilter()` exists for legacy `type_filter` and `type_filter="previous"` behavior.
  - `_hasPrivateFlag()` exists because PHP-origin customfield metadata can represent `private` as string, array, boolean, or empty value.
  - `_resolveDefaultTabVisibility()` exists for `cf-default`, `cf-default-private`, and `cf-default-non-private` tab behavior.

### 9.6 Tests

- Extend `Et2CustomfieldsController.test.ts` only if new normalization belongs in the controller.
- Add focused mapper tests for:
  - simple text/numeric fallback to description
  - select/radio options mapping
  - account/category/link/file app-backed cases
  - unknown type fallback
  - help text/status text propagation
  - readonly widget preference when a `_ro` element is registered
  - editable versus readonly tag selection for `Et2Customfields*` widgets
- Extend `Et2CustomfieldsWidgets.test.ts` for light-DOM rendered widget properties and stable child updates. **Done in source; requires generated JS rebuild before browser test reflects TS changes.**
- Add filter widget tests for selectbox rendering, legacy filter defaults, allowed field type filtering, and filemanager exclusion. **Done in source; requires generated JS rebuild before browser test reflects TS changes.**
- Keep Datagrid row-renderer tests focused on row text rendering and unchanged by widget mapper changes.

### 9.7 Verification

- Run targeted tests after implementation:
  - `npx web-test-runner --config web-test-runner.config.mjs api/js/etemplate/Et2Customfields/test/LegacyCustomfieldsVisibility.test.ts api/js/etemplate/Et2Customfields/test/Et2CustomfieldsController.test.ts api/js/etemplate/Et2Customfields/test/Et2CustomfieldsWidgets.test.ts`
  - Add any new mapper/filter test files to that command.
- Run the Nextmatch customfield integration tests if list/filter behavior touches row or header contracts:
  - `api/js/etemplate/Et2Nextmatch/test/CustomfieldsHeader.test.ts`
  - `api/js/etemplate/Et2Nextmatch/test/Et2Datagrid.test.ts`
- Report any unsupported customfield types/settings explicitly.

Current Step 9 verification:

- Passed:
  - `npx web-test-runner --config web-test-runner.config.mjs api/js/etemplate/Et2Customfields/test/LegacyCustomfieldsVisibility.test.ts api/js/etemplate/Et2Customfields/test/Et2CustomfieldsController.test.ts api/js/etemplate/Et2Customfields/test/Et2CustomfieldWidgetMapper.test.ts`
  - Firefox: 28 passed, 0 failed.
  - Chromium: 28 passed, 0 failed.
- Passed source bundle smoke checks:
  - `npx esbuild api/js/etemplate/Et2Customfields/Et2Customfields.ts --bundle --format=esm --outfile=/tmp/Et2Customfields.js`
  - `npx esbuild api/js/etemplate/Et2Customfields/Et2CustomfieldsList.ts --bundle --format=esm --outfile=/tmp/Et2CustomfieldsList.js`
  - `npx esbuild api/js/etemplate/Et2Customfields/Et2CustomfieldsFilters.ts --bundle --format=esm --outfile=/tmp/Et2CustomfieldsFilters.js`
- Passed standards verification bundle checks:
  - `npx esbuild api/js/etemplate/Et2Customfields/Et2Customfields.ts --bundle --format=esm --outfile=/tmp/Et2Customfields.js`
  - `npx esbuild api/js/etemplate/Et2Customfields/Et2CustomfieldsList.ts --bundle --format=esm --outfile=/tmp/Et2CustomfieldsList.js`
  - `npx esbuild api/js/etemplate/Et2Customfields/Et2CustomfieldsFilters.ts --bundle --format=esm --outfile=/tmp/Et2CustomfieldsFilters.js`
  - `npx esbuild api/js/etemplate/Et2Customfields/Et2CustomfieldsList.ts --bundle --format=esm --outfile=/tmp/Et2CustomfieldsList.js`
- Re-ran after cleanup:
  - `npx esbuild api/js/etemplate/Et2Customfields/Et2Customfields.ts --bundle --format=esm --outfile=/tmp/Et2Customfields.js`
  - `npx esbuild api/js/etemplate/Et2Customfields/Et2CustomfieldsFilters.ts --bundle --format=esm --outfile=/tmp/Et2CustomfieldsFilters.js`
- Re-ran after standards cleanup:
  - `npx web-test-runner --config web-test-runner.config.mjs api/js/etemplate/Et2Customfields/test/LegacyCustomfieldsVisibility.test.ts api/js/etemplate/Et2Customfields/test/Et2CustomfieldsController.test.ts api/js/etemplate/Et2Customfields/test/Et2CustomfieldWidgetMapper.test.ts`
  - Firefox: 28 passed, 0 failed.
  - Chromium: 28 passed, 0 failed.
- Pending after normal generated JS rebuild:
  - `api/js/etemplate/Et2Customfields/test/Et2CustomfieldsWidgets.test.ts`
  - Nextmatch integration tests if rebuilt widget behavior changes rendered row/header interactions.
