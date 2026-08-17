# Et2Datagrid directory migration plan

## Goal

Move the reusable `Et2Datagrid` implementation out of `Et2Nextmatch/` into its own top-level widget directory:

```text
api/js/etemplate/Et2Datagrid/
```

`Et2Nextmatch` should remain a consumer of the datagrid, not the owner of the datagrid implementation. Keep the change as a file-organization/refactoring change with no intentional runtime behavior changes.

## Current ownership split

Move generic datagrid code to `Et2Datagrid/`:

- `Et2Datagrid.ts`
- `Et2Datagrid.styles.ts`
- `Et2Datagrid.types.ts`
- `Et2DatagridColumnManager.ts`
- `Et2DatagridColumnState.ts`
- `Et2RowProvider.ts`
- `Et2Datagrid.md`

Keep Nextmatch-specific code in `Et2Nextmatch/`:

- `Et2Nextmatch.ts`
- `Et2Nextmatch.styles.ts`
- `Et2Nextmatch.row.styles.ts`
- `Et2NextmatchDataProvider.ts`
- `Et2NextmatchColumnPreferences.ts`
- `Et2NextmatchActionController.ts`
- `Headers/`
- `ColumnSelection.ts`

Do not move `ColumnSelection.ts` in this migration unless a reviewer explicitly approves expanding the scope. It is still named `et2-nextmatch-columnselection` and depends on legacy nextmatch/dataview concepts.

## Preference helper split

Not needed. `Et2Datagrid.ts` does not import anything from `Et2NextmatchColumnPreferences.ts` — that backwards dependency was already removed (the `shouldPersistDatagridColumnPreferenceEvent()` function no longer exists anywhere in the codebase). No generic preference helper needs to be created.

`Et2NextmatchColumnPreferences.ts` still needs its datagrid type import updated to the new path:

```ts
import {Et2DatagridColumn} from "../Et2Datagrid/Et2Datagrid.types";
```

## Import update checklist

After moving files, update TypeScript imports in these areas:

- `Et2Nextmatch.ts`
  - `Et2Datagrid` from `../Et2Datagrid/Et2Datagrid`
  - datagrid types from `../Et2Datagrid/Et2Datagrid.types`
  - `Et2DatagridColumnSelectionItem` from `../Et2Datagrid/Et2DatagridColumnState`
  - `Et2RowProvider` from `../Et2Datagrid/Et2RowProvider`

- `Et2NextmatchDataProvider.ts`
  - datagrid provider/types from `../Et2Datagrid/Et2Datagrid.types`

- `Et2NextmatchColumnPreferences.ts`
  - `Et2DatagridColumn` from `../Et2Datagrid/Et2Datagrid.types`

- `Et2Datagrid.ts` after it has moved
  - keep local datagrid imports relative to `./`
  - update shared widget/style imports from `../...` as needed
  - keep imports to shared widgets such as `Et2Widget`, `Et2Template`, `Et2Dialog`, `Et2Customfields`, and `et2_core_arrayMgr` relative to the new location

- `Et2RowProvider.ts` after it has moved
  - keep datagrid type imports local to `./Et2Datagrid.types`
  - verify imports to shared eTemplate classes still resolve from the new directory

Do not add a side-effect import for `Et2Datagrid` in `api/js/etemplate/etemplate2.ts`. `Et2Datagrid` is not used standalone — it is only ever registered transitively via the existing `Et2Nextmatch` import, so no change is needed there.

Also check outside `api/js/etemplate/` entirely — some app-level TypeScript imports `Et2Datagrid`/`Et2Datagrid.types` directly rather than through `Et2Nextmatch`. Confirmed during implementation:

- `addressbook/js/CRM.ts` — imports `Et2Datagrid` and `Et2DatagridUpdateTypes`
- `filemanager/js/filemanager.ts` — imports `Et2DatagridUpdateType`/`Et2DatagridView`
- `mail/js/app.ts` — imports `Et2DatagridUpdateType`/`Et2DatagridUpdateTypes`

All three need their `../../api/js/etemplate/Et2Nextmatch/Et2Datagrid...` import paths updated to `../../api/js/etemplate/Et2Datagrid/Et2Datagrid...`. `npm run typecheck` is what surfaces these — a plain `rg` scoped to `api/js/etemplate` (as step 6 below does) will not catch them since they live in application directories.

## Test file moves

Examine current test files in `api/js/etemplate/Et2Nextmatch/test/`.  Make sure the tests themselves are in a file that matches both their purpose and their scope.  Move them if needed, creating new test files if needed named '<className>.<scope>.test.ts'.
Verify that all tests still pass.
Move tests that primarily cover generic datagrid behaviour into:

```text
api/js/etemplate/Et2Datagrid/test/
```

Confirmed moves (each imports `Et2Datagrid` directly, not `Et2Nextmatch`):

- `test/Et2Datagrid.test.ts`
- `test/Et2DatagridColumnManager.test.ts`
- `test/Et2DatagridColumnState.test.ts`
- `test/Et2DatagridColumnPreferences.test.ts` — verified generic; tests Et2Datagrid column preference behavior directly, no legacy Nextmatch migration functions involved
- `test/Et2Datagrid.rows.test.ts`
- `test/Et2Datagrid.selection.test.ts`
- `test/Et2Datagrid.templateHandlers.test.ts`
- `test/Et2Datagrid.rowHydration.benchmark.ts`
- `test/Et2Datagrid.tileView.test.ts` — currently an empty placeholder file (0 bytes); move it along with the others, but it still needs actual test content written at some point

Keep tests that cover Nextmatch behavior in:

```text
api/js/etemplate/Et2Nextmatch/test/
```

Examples to keep:

- `Et2NextmatchDataProvider.test.ts`
- `Et2Nextmatch.actions.test.ts`
- `Et2Nextmatch.filters.test.ts`
- `Et2NextmatchColumnPreferences.test.ts`
- `Et2Nextmatch.compat.test.ts`
- `Et2Nextmatch.refresh.test.ts`
- `CustomfieldsHeader.test.ts`
- `ColumnSelection.test.ts`

`Et2Datagrid.test.ts` contained two tests that actually exercised `Et2Nextmatch` directly (`new Et2Nextmatch()`, testing `_syncDatagridRowStylesheets()`/`addRowStylesheet()` — row-stylesheet handling is Nextmatch-specific, not generic datagrid behavior). These were extracted into a new `Et2Nextmatch.rowStylesheets.test.ts` in `Et2Nextmatch/test/` rather than moved with the rest of the file.

Update all moved test imports to match the new paths. Watch especially for imports currently shaped like:

```ts
import {Et2Datagrid} from "../Et2Datagrid";
import datagridStyles from "../Et2Datagrid.styles.ts";
import {Et2RowProvider} from "../Et2RowProvider.ts";
```

## Documentation

Move `Et2Datagrid.md` beside the component in `Et2Datagrid/`.

Then update any doc links that assume the document lives under `Et2Nextmatch/`. Search at least:

```sh
rg "Et2Datagrid|et2-datagrid|components/et2-datagrid" api/js/etemplate/Et2Nextmatch
```

Keep `Et2Nextmatch.md` in place, but update any local references if relative paths change.

Scope stays inside `api/js/etemplate/Et2Nextmatch/` (and the new `Et2Datagrid/`) for this migration. Generated doc manifests elsewhere (`doc/dist/custom-elements.json`, `doc/etemplate2/assets/custom-elements.json`, `doc/etemplate2/_data/components.json`) also reference `Et2Datagrid` paths, but they're auto-generated build output — leave them alone; they regenerate from a docs build.

## Generated JavaScript

Do not manually edit generated `.js` or `.js.map` files as part of the source move.

This repository contains generated JavaScript beside TypeScript sources, but project instructions say not to modify generated JavaScript manually. If generated artifacts are required for the final branch, run the normal build and let the build output update them in a separate, explicit step.

## Suggested implementation order

1. Create `api/js/etemplate/Et2Datagrid/` and `api/js/etemplate/Et2Datagrid/test/`.
2. Move the generic source and doc files listed above.
3. Update imports inside moved datagrid files.
4. Update imports in Nextmatch files that consume datagrid classes/types.
5. Move generic datagrid tests and update their imports.
6. Run search checks for stale paths, across the whole repo, not just `api/js/etemplate` — app-level code outside `api/js/etemplate` imports these classes directly too (see the confirmed list above):

   ```sh
   rg "Et2Nextmatch/Et2Datagrid|Et2Nextmatch/Et2RowProvider|Et2Nextmatch/Et2DatagridColumn" -g '*.ts' -g '*.md'
   ```

7. Run `npm run typecheck` and diff its output against a pre-move baseline — "Cannot find module" errors pointing at `Et2Nextmatch/Et2Datagrid*` reveal any consumer the `rg` search missed.
8. Run verification commands.

## Verification

Run TypeScript checking:

```sh
npm run typecheck
```

Run targeted web component tests:

```sh
npx web-test-runner --config web-test-runner.config.mjs \
  api/js/etemplate/Et2Datagrid/test/Et2Datagrid.test.ts \
  api/js/etemplate/Et2Datagrid/test/Et2DatagridColumnManager.test.ts \
  api/js/etemplate/Et2Datagrid/test/Et2DatagridColumnState.test.ts \
  api/js/etemplate/Et2Datagrid/test/Et2DatagridColumnPreferences.test.ts \
  api/js/etemplate/Et2Datagrid/test/Et2Datagrid.rows.test.ts \
  api/js/etemplate/Et2Datagrid/test/Et2Datagrid.selection.test.ts \
  api/js/etemplate/Et2Datagrid/test/Et2Datagrid.templateHandlers.test.ts \
  api/js/etemplate/Et2Nextmatch/test/Et2NextmatchDataProvider.test.ts \
  api/js/etemplate/Et2Nextmatch/test/Et2Nextmatch.filters.test.ts \
  api/js/etemplate/Et2Nextmatch/test/Et2Nextmatch.compat.test.ts \
  api/js/etemplate/Et2Nextmatch/test/Et2Nextmatch.refresh.test.ts \
  api/js/etemplate/Et2Nextmatch/test/Et2Nextmatch.rowStylesheets.test.ts
```

`Et2Datagrid.tileView.test.ts` is left out of this command since it currently has no test content.
`Et2Datagrid.rowHydration.benchmark.ts` is a benchmark, not an assertion-based test, and is not part of this command either.

## Acceptance criteria

- `Et2Datagrid` can be imported from `api/js/etemplate/Et2Datagrid/Et2Datagrid`.
- `Et2Nextmatch` imports and renders the moved datagrid with no behavior changes.
- Generic datagrid code no longer imports from `Et2Nextmatch/`.
- Nextmatch-specific provider, header, action, and legacy preference code remain in `Et2Nextmatch/`.
- No generated JavaScript is hand-edited.
- `npm run typecheck` passes.
- Targeted datagrid and Nextmatch web component tests pass, or any environment blocker is documented with the exact failed command.
