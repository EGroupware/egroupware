# Et2* Widgets: Extract Large Inline `css\`...\`` Blocks to `.styles.ts`

## Goal

Every `Et2*`/webcomponent widget with more than **15 lines** of inline CSS in a `css\`...\`` tagged
template literal (typically inside `static get styles()`/`static styles`) gets that block moved into
its own companion `X.styles.ts` file, imported back in, matching the convention already used by 17
files in the codebase (`Et2Datagrid.styles.ts`, `Et2File.styles.ts`, `Et2Toolbar.styles.ts`, etc.).
Files at 15 lines or fewer stay inline - not worth the extra file for a handful of rules.

This is a pure code-motion refactor, not a behaviour change: same `CSSResult`, same rules, just moved
to keep large widget files from being dominated by CSS text. **Separate commit(s) from the
[et2-property-decorator-migration.md](et2-property-decorator-migration.md) work** - unrelated concern,
don't bundle the two even for files that need both.

## Inventory

**48 files** in `api/js/etemplate/` have inline `css\`...\`` blocks over 15 lines. Line counts are
per-file totals (a few files have more than one `css\`` block, e.g. one in `styles` and one in a
static helper - counted together). Extracted mechanically via a script counting lines between each
`css\`` and its matching closing backtick, so treat as "roughly right," same caveat as the
property-migration doc's own numbers.

**20 of these 48 also appear in the property-decorator migration's file list** (marked below) - when a
file needs both, do the CSS extraction as its own commit, either just before or just after that file's
property conversion, so each diff stays reviewable as one thing.

| File | CSS lines | Also in property migration? |
|---|---|---|
| Et2Select/Et2Select.ts | 171 | yes |
| Et2Button/Et2ButtonToggle.ts | 132 | - |
| Et2Select/SearchMixin.ts | 119 | - |
| Layout/Et2Details/Et2Details.ts | 106 | - |
| Et2Button/ButtonMixin.ts | 94 | yes |
| Et2Dialog/Et2Dialog.ts | 77 | - |
| Et2Link/Et2LinkList.ts | 74 | yes |
| Et2Switch/Et2SwitchIcon.ts | 66 | - |
| Et2Portlet/Et2Portlet.ts | 65 | yes |
| Et2HtmlArea/Et2HtmlArea.ts | 64 | - |
| Et2Widget/Et2Widget.ts | 64 | yes |
| Layout/Et2Box/Et2Box.ts | 62 | - |
| Et2Switch/Et2Switch.ts | 61 | yes |
| Et2DropdownButton/Et2DropdownButton.ts | 59 | yes |
| Et2Date/Et2DateDuration.ts | 55 | - |
| Layout/Et2Groupbox/Et2Groupbox.ts | 53 | - |
| Et2Select/Tag/Et2Tag.ts | 49 | - |
| Et2Nextmatch/ColumnSelection.ts | 43 | yes |
| Et2Select/Tag/Et2EmailTag.ts | 43 | - |
| Et2Select/Et2Listbox.ts | 41 | - |
| Et2Link/Et2Link.ts | 40 | yes |
| Et2Customfields/Et2CustomfieldsList.ts | 39 | - |
| Et2Date/Et2Date.ts | 36 | yes |
| Et2Colorpicker/Et2Colorpicker.ts | 35 | - |
| Layout/Et2Tabs/Et2Tabs.ts | 34 | yes |
| Et2HtmlArea/Et2HtmlAreaReadonly.ts | 34 | - |
| Et2Avatar/Et2Avatar.ts | 30 | - |
| Et2Textbox/Et2Number.ts | 29 | - |
| Et2Favorites/Et2FavoritesMenu.ts | 29 | - |
| Et2Link/Et2LinkString.ts | 29 | - |
| Et2Checkbox/Et2Checkbox.ts | 28 | - |
| Et2Diff/Et2Diff.ts | 28 | - |
| Et2Select/Select/Et2SelectCategory.ts | 27 | - |
| Et2Favorites/Et2Favorites.ts | 26 | yes |
| Et2Avatar/Et2AvatarGroup.ts | 25 | yes |
| Et2Textarea/Et2Textarea.ts | 25 | yes |
| Et2Description/Et2Description.ts | 24 | yes |
| Et2Nextmatch/Headers/SortableHeader.ts | 24 | - |
| Et2Url/Et2InvokerMixin.ts | 24 | yes |
| Et2Customfields/Et2Customfields.ts | 23 | - |
| Et2InputWidget/Et2InputWidget.ts | 23 | yes |
| Et2Date/Et2DateTime.ts | 22 | yes *(also Batch 0 no-op delete there - unrelated reason)* |
| Et2Link/Et2LinkTo.ts | 22 | - |
| Et2Link/Et2LinkAdd.ts | 21 | - |
| Et2Link/Et2LinkEntry.ts | 21 | - |
| Et2Customfields/Et2CustomfieldsFilters.ts | 18 | - |
| Layout/Et2Tabs/Et2TabPanel.ts | 17 | yes |
| Et2Link/Et2LinkAppSelect.ts | 16 | yes |

**Already correctly extracted** (17 files, not touched by this project): `Et2Ai.styles.ts`,
`Et2Datagrid.styles.ts`, `Et2Email.styles.ts`, `Et2FileItem.styles.ts`, `Et2File.styles.ts`,
`Et2Filterbox.styles.ts`, `Et2Nextmatch.row.styles.ts`, `Et2Nextmatch.styles.ts`,
`Et2Template.styles.ts`, `Et2Toolbar.styles.ts`, `Et2TreeDropdown.styles.ts`, `Et2Tree.styles.ts`,
`Et2VfsPath.styles.ts`, `Et2VfsSelectRow.styles.ts`, `Et2VfsSelect.styles.ts`,
`Et2AppBox.styles.ts`, `Markdown.styles.ts`.

**Naming inconsistency, not blocking, worth a follow-up rename:** `Et2Avatar/cropperStyles.ts`,
`Et2Date/DateStyles.ts`, `Styles/colorsDefStyles.ts` are already separate style-only modules but don't
follow the `X.styles.ts` companion-file naming convention (they predate it or serve multiple
consumers). No content to extract, just a naming quirk - rename opportunistically if touching those
areas, don't make it its own task.

**Out of scope:** the dedicated style-library modules `Styles/bootstrap-icons.ts`,
`Styles/shoelace.ts`, `Et2Styles/Et2Styles.ts` - these *are* CSS by design (icon font / vendor
component styles), not a widget with an inline block to extract.

## Batching / order

No collision/base-class risk here like the property migration has - this is pure code motion, so
ordering is mostly about commit-size hygiene rather than risk:

1. **Biggest files first** (`Et2Select.ts` 171 lines down through the ~50-60 line range) - these are
   the ones actually cluttering their host file, so highest value per commit.
2. **The 20 files that overlap with the property-decorator migration** - sequence each one's CSS
   extraction commit adjacent to (immediately before or after) that same file's property-conversion
   commit, so anyone reviewing either project's commits sees a clean single-purpose diff, not a mix.
3. **Remainder** (18-49 line files) - batch a handful per commit by directory/family (e.g. all the
   `Et2Link/*` files together, all `Et2Select/Tag/*` together) since they're low-risk, mechanical, and
   reviewing them one-by-one would be noise.

## Verification, per file

1. `npm run typecheck` - a pure move shouldn't introduce new errors; confirm count is unchanged for
   that file.
2. Run the widget's `test/*.test.ts` if it has one - moving CSS must not change computed styles/layout
   the tests may assert on (e.g. visibility, computed dimensions).
3. Manual smoke test for widgets with no test coverage: render the widget in a real template, confirm
   it looks identical before/after (a quick visual diff is enough - this is a text move, not a rule
   change).
4. Grep the new `.styles.ts` file's export name against its host file's import to confirm nothing was
   left duplicated inline (the `Et2Select.ts` leftover-registration bug found during the property
   migration - see that doc's `@customElement` section - is a reminder that half-finished
   extractions/migrations leave detectable dead code if not double-checked).

## Steps

1. Biggest-first, one file per commit, for the top ~10-15 files where the win is most visible.
2. For the 20 overlap files, pair each with its property-decorator conversion commit (either order),
   keeping the two commits adjacent and single-purpose.
3. Batch the remaining files by directory/family, a handful per commit.
4. Delete this doc once the table above is empty (`et2select-searchmixin-removal.md` convention).
