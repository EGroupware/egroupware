# `layout` attribute conversion

Convert `.xet` templates from a top-level `<grid>` (or a stack of loose boxes) to the `layout`
attribute on the top-level `<et2-template>`, so dialogs reflow with their popup / window instead of
being one fixed table.

Status: Tier A done (addressbook.edit on the naughty list), Tier B first batch done (2026-10-19); the rest of Tier B needs state or a decision, see the dispositions below. Tier C deferred.

## Background

- `layout="2-column" | "edit" | "stack"` on a container; see `doc/etemplate2/pages/getting-started/layout.md`
  and `api/js/etemplate/Et2LayoutController/Et2LayoutController.md`.
- CSS lives in `kdots/css/src/layouts/` (`2-column.less`, `edit.less`, `stack.less`, `grid-base.less`).
  `kdots.css` is compiled by hand with bare `lessc` (no npm script).
- Reference conversions: `timesheet/templates/default/edit.xet` and `infolog/templates/default/edit.xet`
  (`layout="edit"` on the main template, `layout="2-column"` on the Details tab).
- Already using a layout: `admin` (accesslog, acl, categories.index, customfields, remotes),
  `api/add_favorite`, `rag/index` (`stack`), `infolog.edit`, `timesheet.edit` (`edit`).
- Remember (AGENTS.md): a `templates/mobile/` counterpart with the same template id must get the
  equivalent change. New user-facing phrases go in the app's `lang/` files.

## Per-template procedure

1. Screenshot unchanged at preferred size (popups: wait for load + resize to finish).
2. Convert: remove the top-level grid, put `layout` on the top-level `et2-template`; adjust row classes;
   make rows/columns break logically and together. Check several widths. There must be a gap in width
   between the 2-col -> 1-col break and the labels-wrap break.
3. Verify in the browser.
4. Screenshot again at preferred size.
5. Screenshot full-size, narrow and mobile (mobile = UA emulation + reload, not just a resize).

## Survey method

`git ls-files '*.xet'` in the main repo and each app sub-repo (vendor, node_modules and tests excluded):
494 templates parsed, the last top-level template taken as the main one. Tiers below are from reading the
`.xet`; each candidate is then viewed in the browser before it goes on the approved list.

## Approval and ground rules (2026-10-15)

- Tier A approved. Tier B approved **except smallpart and phpbrain**. Tier C deferred.
- "Mobile" here means the desktop template checked at phone widths (iframe of that width) - no device emulation.
  The `templates/mobile/` twins are still converted separately.
- Rows disabled under conditions (`disabled="@..."`) must survive the conversion and be tested in the browser
  in both states before a template counts as done.
- Rows / fields that span the full width must still do so (`span="all"` / `span="end"`).
- Addressbook's name-parts overlay is deliberate and starts visible for a new entry - not a bug.

## Candidate list

Viewed in the browser so far (iframe harness, 800px wide): timesheet.edit (reference), tracker.edit,
addressbook.edit, calendar.edit, resources.edit, projectmanager.edit, admin.categories.edit,
news_admin.edit, admin.mailaccount wizard step. Everything else is classified from the `.xet` and gets
its first browser look as the "before" screenshot when it is converted.

### Tier A - main edit dialogs (header / tabs / owner row / footer in one top-level grid)

`layout="edit"` on the main template, `layout="2-column"` on the tab sub-templates that are label/field grids.
Each has a `templates/mobile/` twin that is also still a grid and gets the same treatment.

| Template | Notes |
|----------|-------|
| tracker `tracker.edit` (+ mobile) | Header, 2x2 queue/category/version/priority rows, tabs, created-by row, footer. Clear fit. |
| addressbook `addressbook.edit` (+ mobile) | Biggest one: name widget, organisation tab, many tabs. The name-parts overlay renders open and over the form in the iframe - check whether that is a harness artefact. `edit.Lehrpraxis.xet` is an untracked local custom file; leave alone. |
| calendar `calendar.edit` (+ mobile) | Start/duration/options rows, tabs, alarms grid. |
| resources `resources.edit` (+ mobile) | Small header + quantity/category rows + tabs. |
| projectmanager `projectmanager.edit` (+ mobile) | General tab is a two-column label/field block; header has ID + title. |

### Tier B - smaller edit dialogs (top-level `<grid>` of label/field rows plus Save/Apply/Cancel)

Likely `layout="edit"`, or `2-column` where there is no standard footer.

- admin: `categories.edit` (+ mobile), `customfield_edit`, `group.edit`, `mailaccount` (+ mobile), `token.edit`, `customtranslation`, `mainscreen_message`
- addressbook: `add_list_dialog`, `index.cat_add`, `contactform` (public form), `dupconfirmdialog`
- calendar: `add` (+ mobile), `meeting`, `notify_dialog`, `subscribe`, `cat_acl`, `edit.alarms`, `freetimesearch`
- timesheet: `editstatus`
- filemanager: `file` (+ mobile), `job`, `file_share_received`, `share_dialog`
- mail: `sieve.edit`, `sieve.vacation` (+ mobile), `sieve.emailNotification`, `acl` (+ mobile), `subscribe` (+ mobile), `pgpKeyAddToContact`, `smimeCertAddToContact`, `importMessage`, `checkmailbox`
- news_admin: `edit`, `cat`; bookmarks: `edit`, `import`, `export`; kanban: `edit`, `edit_column`, `edit_swimlane`
- records: `edit`, `admin.fields.edit`, `categories`; policy: `edit`, `admin_cmd`; openid: `client`; esyncpro: `policy`
- resources: `acl_edit`; projectmanager: `pricelist.edit`; tracker: `customfields`, `admin`
- aiassistant: `edit`; invoices: `edit`, `allowance`, `position`; phpbrain: `edit`
- smallpart: `course`, `question`, `material.edit`, `lti-config`; stylite: `filemanager.edit_share`, `placetel.sipUser`
- developer: `translations.edit`; api: `openapi-config`; preferences: `password`, `settings` (+ mobile)

### Tier C - settings, import/export and wizard dialogs

Top-level grids of label/field pairs with no standard footer (`config.xet` in most apps, importexport wizard
steps, export/import selectors). Probably `2-column` or `stack`. Proposed as a second pass after A and B so
the layouts have settled; roughly 60 templates.

### Possible new layout

- `form` / single-column label+field (1 column, never 2) for the narrow admin/config dialogs. Check first
  whether `stack` or `2-column` with `--column-min-width` already covers it.
- Tab sub-templates (addressbook.edit.general, tracker.edit.details, ...) use `2-column` like timesheet does -
  not a new layout.

### Not candidates

Nextmatch index/list templates (covered by the nextmatch conversion), `*.rows.xet`, print/view templates,
single-widget confirm/prompt dialogs, the `etemplate/` test templates, the home portlets.

## Findings while converting

- **A widget's `disabled="..."` greys it out; it does not hide it.** A grid row's `disabled="@x"` has to become `hidden="@x"` on
  every widget that was in the row (tracker status/resolution/completion row, start/due dates, PM and calendar "created / modified" rows).
  Verified in the browser for both states (new entry: hidden, existing entry: shown).
- **Fixed-width CSS from the grid era fights the layout.** `#resources-edit.et2_container {width:848px}` and
  `#tracker-edit_tr_creator {width:50%}` had to go (the first kept the dialog 848px wide at any window size, the second
  made the Created-by select half a cell).
- **A footer with several buttons holds the whole layout open.** `[class*="footer"]` has no `min-width: 0`, so its
  `min-content` (all buttons, a select, a checkbox in one line) became the width of the one grid track - at a phone width
  the whole dialog stayed 800px wide. `style="min-width:0"` on the footer fixes it (done in calendar.edit). Candidate
  for `edit.less` instead, so no template has to remember it.
- **Popups fit their layout after opening.** The registered `*_popup` sizes in the app hooks are out of scope for this
  project and were left alone; "preferred size" below is the popup's size after auto-resize has run (tracker 791x681,
  resources 791x594, projectmanager 791x563, calendar 791x547). The popup fit leaves projectmanager about 50px short
  of its content (the tab panel scrolls); not investigated.
- **A bare checkbox in a row of taller widgets creates a phantom grow row.** (FIXED: rows now come from the grid tracks, not child tops; workarounds can be dropped.) `scheduleGrowRowSizing()` finds the rows
  from each child's `getBoundingClientRect().top`; a checkbox is vertically centred, so it sits ~10px lower than the
  textbox or select beside it and counts as another row. The `grid-template-rows` list is then one track too long, the
  tabbox's 280px track lands on the next row and the footer / created-modified rows are drawn 280px tall or on top of
  the tabbox. Only dialogs with a growing child (a tabbox) are affected. Seen in admin.group.edit, calendar.edit
  (whole day) and resources.edit (Bookable - the huge "Created by / Last modified" rows). Workarounds used: a lone
  checkbox on its own line with `span="all"`, or the checkbox inside an `<et2-hbox>` (the box fills the row, so its
  top is the row's top; `align-items: center` on its `::part(base)` keeps the box centred). The real fix is in
  `Et2LayoutStrategies.ts` (use the grid row, not the top) - not touched, for you to decide.
- **Header labels need `class="et2-label-fixed"`** on the header's textbox, or "Title" / "Summary" / "Name" does not line
  up with the labels below it (the layout only adds the class to direct children, the textbox is inside the header box).
  Done on every converted header. Alternative: one rule in `edit.less` for `[class*="header"]`. `--label-width` on the
  template (`9em` for news_admin.edit and admin.group.edit, `11em` for openid.client) when a label is longer than the
  default 8em.
- **A failed validation message took part in the row.** The `egw-validation-feedback` lives in the widget's help-text,
  which was `flex-basis: 100%` in a form-control row that does not wrap (label-fixed). So a required field that failed
  gave up its width (the header Title in calendar / tracker / timesheet), or the message landed beside its input and
  covered a neighbour's label (timesheet Quantity / Pause time). Fixed in the widget, `Et2Widget.styles.ts`: the
  form-control is `position: relative` and the help-text `position: absolute; top: 100%`, with the existing label-fixed
  `left` offset. Verified on the timesheet, calendar and tracker edit dialogs; no `edit.less` or app CSS needed. Only
  textbox and number messages were seen; select / date / htmlarea messages were not triggered.
- **A layout template in an auto-sized `Et2Dialog` has no width.** The host is `container-type: inline-size`, so in a
  shrink-to-fit dialog it is 0px wide. `layout="stack"` with a tabbox as the only grow child also makes a popup fit to
  ~409x53 (the tabbox has no intrinsic height); `layout="edit"` (tabbox `min-height: 25em`) fits.
- **Forcing a single column: use `--column-min-width:100%`, not a big length.** The collapse breakpoints are container
  queries written with `var()` inside `calc()`, which browsers drop, so only the fixed 600px / 36em ones fire. A
  `--column-min-width:60em` therefore made the grid wider than its container (everything clipped at the right edge)
  anywhere between 600px and 60em. `100%` is always exactly one column. (First tried `minmax(0, 1fr)` in the collapse
  rule; it did not help because the collapse was never applied, and was reverted.)
- **A labelled `et2-textarea` is wrapped in an `et2-ai` automatically, so the layout gives `et2-label-fixed` to the wrapper and the textarea's own label keeps its natural width.** A template puts `class="et2-label-fixed"` on the textarea itself (done in developer.translations.edit; filemanager.file's Comment has the same misalignment, not changed yet). `Et2Textarea.styles.ts` gained the fixed-label rule for its own Shoelace label (`:host(.et2-label-fixed) .form-control__label`).
- **Template URLs and the browser cache.** Templates opened through the framework come with a modification-time query (`?1792444620`) and are fresh. Templates loaded by `et.load()` or by an Et2Dialog come from `api/etemplate.php/<app>/templates/default/<name>.xet?download=20745` (a fixed number) or with no query at all, and the browser keeps serving the old file after an edit. After editing one of those, `fetch(url, {cache: 'reload'})` that exact URL and reload the page.
- **Help text of a label-fixed widget wraps to the widget's width.** The label-fixed help-text offset was `left:`, which for a select's own in-flow help text shifted it right and clipped it ("...changing accessory"); it is `margin-left:` plus `width: auto` now (`Et2Widget.styles.ts`, and the one-column reset in `grid-base.less` resets the margin too). resources.edit's "Remove accessories before changing accessory of" is the Accessory of select's `helpText` (set by `resources_ui::edit()` only for a resource with accessories). Validation messages checked again in the required-fields test layout.
- resources.edit's Links tab: unchanged from the committed template. Its grid has `height="245"`, which spreads the three rows apart; tracker, timesheet and infolog Links tabs have no height on the grid and sit tight under the toolbar. Same before and after the conversion.
- A template is cached by name for the life of the page: after editing a template that opens in a dialog, reload the page.
- **Harness**: the iframe is first loaded 200px taller and shrunk after load (a popup is opened larger and fitted, and a
  transient overflow while a dialog is still building was enough to trigger the phantom row above).
- A legacy `customfields-types` widget does not render outside a `<grid>` (it shows the "deleted widget" placeholder chip) -
  admin.customfield_edit stays unconverted, see the naughty list.
- `<et2-vbox label=...>` does not draw a label, use the widget's own `label`.
- Screenshots: popup preferred size is read from a real popup (`framework.popups`), then the same URL is
  rendered in an iframe of that size (the Chrome window cannot be resized) and cropped.

## Converted

Screenshots are in `doc/ai/projects/layout-conversion/<template>-{before-pref,after-pref,after-full,after-narrow,after-mobile}.png`
(full = 1300px, narrow = 480px, mobile = 375px, all desktop skin; pref = the popup's size after auto-resize).

| Template | Status | Notes |
|----------|--------|-------|
| tracker.edit | done (default); mobile twin not done | main + Details tab; hidden dates / status rows verified |
| resources.edit | converted, on the naughty list | main + Details tab done; the Picture section still needs work |
| projectmanager.edit | main done; General tab on naughty list | dialogMeta hidden for new entries verified |
| calendar.edit | done (default); mobile twin not done | description tab `layout="stack"`; end date appears with "Use end date" |
| admin.categories.edit | done (default); mobile twin not done | Private / Application / Modified conditions verified both ways |
| admin.group.edit | done | Email hidden / shown verified |
| admin.token.edit | done | all seven hidden conditions verified on a new and an existing token |
| admin.mainscreen_message | done (`stack`) | modest change; html/plaintext toggle untouched |
| calendar.subscribe | done | `show_calendar_selection` could not be turned on from the UI, hidden state verified only |
| news_admin.edit | done | the htmlareas keep their own height; popup still scrolls at 845x693 |
| news_admin.cat | done | import rows hidden-state not verified (import is available on this instance); the "%s hours" labels were showing literally before and are gone |
| mail.importMessage | done (`stack`) | opened at 600x180, the grid scrolled at 600x123; stack lets the popup fit to 424x211. `edit` was not used: its popup fit asks for two columns (791px wide). No state-dependent rows to verify. No mobile twin. |
| invoices.position | done (`edit`) | new position (draft invoice) and an existing one (issued): description / origin hidden, fields greyed; the tooltips that lived on the old label descriptions (BT-xxx) moved onto the widgets |
| invoices.allowance | done (`edit`) | new allowance on a draft invoice; same BT-xxx tooltips kept |
| policy.edit | done (`edit`) | main dialog only; the policy tables inside the tabs are untouched. Opened with "Group Default" selected as the addressbook (`before-/after-*-group-default` shots): same header and Summary list as before; the Add policy select sits under its label and the Summary columns are narrow in both versions (not from the conversion) |
| projectmanager.pricelist.edit | done (`edit`); "scrolls although there is space" reported: reproduced at one column (480px wide, 791x572 popup, pl_id=1): the form needs ~665px because of the layout's 25em tabbox minimum, so it scrolls while the small tabs show blank space; none at two columns or in a taller window. Left as is; the optional `min-height:min-content` on its tabbox is decided together with invoices.edit | main dialog only, price table left as it is; `msg` row hidden state not triggered |
| aiassistant.edit | done (`edit`); API URL and key full width | used from the Admin menu / configuration; section headings go in an `et2-hbox span="all"` (a bare description as a layout child gets the fixed label and its text starts after the label column); the page is wider than the popup at every size, same as before |
| kanban.edit | done (`edit`), left as is for the popup case (it is normally opened full screen) | new board only (the Columns / Swimlanes tabs are disabled until it is saved); the page is wider than the iframe, as before |
| filemanager.job | done (`edit` + `2-column` General tab) | Name and Folder full width. "Not infolog": the server never produces it (only `infolog` is supported, anything else fails validation), so the template was loaded client-side with `app: ""` through `new etemplate2(div).load(...)`: Type / Owner / Responsible / Category hide, Creation date and Move file to stay - screenshot `filemanager.job-after-pref-notinfolog.png` |
| filemanager.file | done (`edit` main, General tab forced to one column with `--column-min-width:100%`, as before) | the legacy `vfs-size` became `et2-vfs-size`; Type and Directory are read-only textboxes (an `et2-description` with a `label` showed no value); checked on a directory, a file (Link hidden, Size shown) and a symlink (Link shown, Size hidden). The Permissions tab and the superuser overlay are unchanged |
| admin.mailaccount (the editor with tabs, opened as mail.mail_wizard.edit) | done (`edit` main; every tab forced to one column with `--column-min-width:100%` and a shared `--label-width:13em`, so all inputs start at the same x as the header's. IMAP and Folder were first two columns, but a 13em label in half a column squeezed the inputs and the pairs scrambled into three columns at 1300px; the IMAP-administration group compensates its padding with a smaller label width. The Encryption upload labels get 24em / the full row) | popup measured at 740x670 requested -> 791x757 preferred (was 748x607 with the Identity tab scrolling). Row `disabled=` became widget `hidden=`: `ident_email` / `ident_email_alias` (`select_ident_mail`), forwarding + its checkboxes (`no_forward_available`), account selector (`accounts`), admin toolbar (`admin_actions`). Both states of the first two checked in the browser by loading the template with custom content; with the real account the Aliases tab is disabled by the server (default SMTP class), so it is only checked that way. Left-hand captions without an input of their own (Authentication, Certificate, the three certificate uploads, Administration) are a plain `et2-description` with `width: var(--label-width)` (`flex` is ignored on it). `admin.mailaccount.smimecertgen` (a dialog) not touched. At 375px the "account editable by user" checkbox label is clipped by a few pixels |
| invoices.edit (main dialog only) | done (`edit`, `--column-min-width:32em`) | every header field is a direct child of the layout (no `et2-hbox` around Type / Category, the dates, number / currency), so pairs wrap with the window; Modified / Created are two `dialogMeta` rows that sit side by side. `32em` keeps the grid at two columns up to ~1370px (at the default 26rem a 1300px window got three and the pairs scattered) and the popup opens at 959x824 for a requested 900. The payments table is unchanged, a full-width legacy grid (`span="all" width="100%"`); its show/hide in `invoices/js/app.ts` `statusChange()` looked for a table row and now toggles the grid's own node (checked: hidden for Issued, shown for partially payed). Status is one cell and the two template selects + the Issue / Mail invoice buttons are a second cell (an `et2-hbox`), so with two columns they share a line and with one column the hbox drops under Status. Rows `disabled=` -> `hidden=`: billing period (code is one digit) and reference (code not 384 / 2); checked for codes 380 / 384 / 2 by loading the template with custom content. The 7 tab sub-templates are still nested grids; the label column of the Summation, tax-breakdown, Payment and Buyer (trade party) grids is widened from 100 to 190 so labels like "Calculated amount" and "OrderNo (confirmation)" stay on one line. The "Payments" caption and the grid are one `et2-vbox.payment`, shown and hidden together by `statusChange()`. The tabbox had a fixed height from a viewport formula (`tabs_height` in `Ui.php`, `updateTabsHeight()` in `app.ts`, written for the table layout), which made the Positions tab scroll on its own at every width; both are removed and the tabbox is `tabHeight="auto"`, so it takes the leftover height. Left as is: with many positions the form and the tab body can both scroll for a few pixels (the layout's 25em tabbox minimum). `style="min-height:min-content"` on the tabbox removes that (one scrollbar everywhere, header scrolls away with the form) but was taken back out; decide before committing. With / without shots: `invoices.edit-tabbox-with-*` / `-without-*` (30 positions, 959 and 480px: without = form and tab body both scroll, with = only the form) and `projectmanager.pricelist.edit-tabbox-with-480` / `-without-480` (without = the form scrolls and the tabbox leaves blank space, with = no scrolling, tabbox 252px instead of 350px). The user wants a with / without screenshot pair at that time (30-position harness, 959 and 480px). Single-column `--column-min-width:100%` was tried first and rejected: the popup's size probe resolves it against the window and asked for ~1800px |
| bookmarks.import | done (`edit`, `--column-min-width:14em`) | every widget spans the row; the requested 500x150 popup used to fit to 500x93 and scroll, it now fits to 645x300 with nothing scrolling. Notes kept as `help` descriptions. Widths 480 / 375 checked, 1300 not shot |
| bookmarks.export | done (same) | `bm_count` (selected bookmarks) shows its line and hides the "Categories to export" header, and the reverse for none; both checked by loading the template with custom content. Popup 645x254 (was 500x93 with a scrollbar) |
| bookmarks.edit | done (`edit` main, General and Details tabs `2-column` forced to one column; the Links tab template is untouched, links tabs stay as they are) | view (`edit` off) and edit modes both checked in the browser; the pencil next to Category (`edit_category`) submits the form and the PHP redirects to the bookmarks category editor: in view mode it navigates there (original template and converted behave the same), in edit mode it does nothing, in both versions. Left unchanged for now; a fix tried and backed out was `noSubmit="true"` plus `onclick="egw.open_link('preferences.preferences_categories_ui.index&cats_app=bookmarks&cats_level=True&global_cats=True', '_blank', '800x600'); return false;"`, which opened the right URL in a popup in both modes. Favicon image kept. Tab height `auto`, popup fits to 791x433 (view) / 453 (edit) instead of 758x379 with the tab scrolling |
| addressbook.add_list_dialog | done (`stack`, `class=""`) | a small Et2Dialog opened with `width: 400`; `edit` made the dialog fit to its two-column width (762px), `stack` keeps it one column. Checked by opening the same dialog call as `addressbook/js/app.ts` |
| addressbook.contactform | NOT converted (tried, reverted: the public form looked better as it was) | the template is back to the committed version |
| preferences.password | done (main dialog + Change password tab) | the Two factor auth tab keeps its `<grid id="2fa">`: that id is the data namespace the PHP reads (`$content['2fa']['code']`). SSO groupbox and the `nopasswordchange` hiding checked with custom content. The extra tabs (application passwords, tokens, WebAuthentication) come from other templates and were not touched |
| resources.acl_edit | done (`edit`, `--column-min-width:33em` so the popup fits the four permission columns: 987x418; permissions table kept as a grid) | the four permission columns are a real table with column headers; a legacy grid does not take `span="all"`, so it sits in an `et2-hbox span="all"`. The `no_private` state (Locations / rooms checkbox) was not toggled |
| developer.translations.edit | done (`edit`, every field spans the row) | tested via the template harness: the real popup renders blank for adding a new translation (editing an existing one works), not caused by the conversion. Textareas carry `class="et2-label-fixed"` (see the findings) |
| calendar.add | done (`edit`, `--label-width:5em; --column-min-width:30em`) | an Et2Dialog opened by `egw.openDialog`. The blank body was not the dialog sizing: `calendar/templates/default/app.less` (and `app.css`) had `#calendar-add_calendar-add { width: min-content; }` for the old table, and a layout host's min-content width is 0 (`container-type: inline-size`). Rule removed from the default theme (the mobile theme keeps its own). Duration and the End date sit in one cell so choosing "Use end date" does not add a row to a dialog whose height is already fixed. Dialog 904x229 (was 601x228). Title and Start / Duration checked, "Use end date" state checked |
| admin.customfield_edit | done (`edit`; settings tab `2-column`) | the legacy `customfields-types` widget is a plain `et2-select` now; `admin_customfields::edit()` supplies its options (`Customfields::getCfTypes()`) and defaults it to the first type, which the old widget did itself. `cf_type_change()` still hides Length / Rows / Options per type (checked text -> date). The popup does not resize to the layout (stays 600x543, as before) and the settings tab scrolls inside it; the layout collapses to one column below a 600px container, so Length / Rows and Order / Tab are stacked there |
| openid.client | done | both tabs; the icon-upload `onchange` now finds `app_icon` by id because its parent changed |

- **Validation message in other widgets** (select, date, htmlarea; checked with a layout of required fields next to each other): no row changes height. The date message was clipped to the widget (lit-flatpickr sets `overflow: hidden` on its host) and is fixed in `Et2Date.styles.ts`; the message now has `z-index: 1` in `Et2Widget.styles.ts`, or a following editor covered it. Still open: the textbox's message starts about 16px left of its input where select, date and htmlarea line up (the label-fixed `left` rule does not reach it; cause not found).

- Fine as grids, opened and looked at: admin.customtranslation, timesheet.editstatus, calendar.cat_acl, api.openapi-config (the screenshots were shown in the conversation). Not opened (need an event, board or participants): calendar.freetimesearch, calendar.notify_dialog, kanban.edit_column / edit_swimlane, records.admin.fields.edit (not installed here), tracker.customfields. `addressbook.index.cat_add` is not referenced by any code. `addressbook.dupconfirmdialog` is one row per duplicate (`${row}`) and stays a grid. Left alone by decision: mail sieve / acl, filemanager share, mail pgp / smime.

## Tier B dispositions (not yet converted)

Triaged from the source; none of these have had a browser pass yet.

| Template | Why it is not converted yet |
|----------|------------------------------|
| admin.customtranslation, timesheet.editstatus, calendar.cat_acl, calendar.freetimesearch, calendar.notify_dialog, mail.acl, mail.checkmailbox, invoices.position, policy.edit (acl rows), projectmanager.pricelist.edit, tracker.customfields, kanban.edit_column / edit_swimlane (`widget type=` rows) | A table of content-driven rows (`${row}` / `$row_cont`), not a form. The grid is the right tool, per `layout.md`. |
| calendar.meeting, calendar.add | Embedded in a mail view / small slot-footer dialog, no popup to fit. |
| mail.subscribe, preferences.password, preferences.settings | Already boxes, not a grid. |
| esyncpro.policy | Checklist of sentence-style options with `%s` unit labels and legacy `toggle_pw_required` JS; labels are sentences, not field names. Poor fit for `2-column`. |
| openapi-config, records.admin.fields.edit, resources.acl_edit, policy (tabs), tracker.admin | Tables of repeated rows (`${row}`) or an app page rather than a dialog. The grid stays. |
| mail.sieve.vacation / edit / emailNotification, mail.acl, mail.pgp / smime | Cannot be opened on this instance: they need an IMAP/Sieve server, the account here is JMAP (`editVacation` fatals with `imapServer() on null`). |
| records.edit, records.categories, policy.admin_cmd | Legacy tags (`<description>`, `<menulist>`) and a table of repeated rows (records), or a sub-template embedded in another dialog (admin_cmd). Not converted. |
| developer.translations.edit | Opens a blank page here, in a popup and in an iframe. |
| stylite.*, filemanager.file_share_received / share_dialog, addressbook.contactform / add_list_dialog / index.cat_add / dupconfirmdialog, bookmarks.*, admin.mailwizard.* (wizard steps, not the account editor), mail.checkmailbox | Not done: need a share / token / data I could not set up without writing to the database, are Et2Dialog dialogs (see calendar.add on the naughty list), or are multi-step wizards. Not rejected. |

## Naughty list

Templates that should convert but proved troublesome, or just look bad. Worked through together.

| Template | Problem |
|----------|---------|
| addressbook.edit (+ editname, general, home, details tabs) | Header holds phone numbers with a `tel_prefer` radio beside each, the name-parts overlay (`addressbook.editname`, shown by JS that hides `table.editname`), a search-mode footer (`showsearchbuttons`) and an org/city/state field order that swaps with `addr_format`. Needs a decision on how the preferred-phone radios and the overlay fit a layout before touching it. Not started. |
| projectmanager.edit.general | DECISION (2026-10-19): left as it is. A real table: label, project value, then the datasource value beside each; does not reflow below ~700px. |
| resources.edit (Details tab, Picture section) | Radios + upload + preview still do not line up well: the legacy `radio` widgets in a small grid, a "Picture" label hand-sized to `--label-width`, and a preview image that adds height (the popup scrolls). Needs a proper arrangement, probably replacing the legacy radios. Note: "Remove accessories before changing accessory of" is not new: `resources_ui::edit()` sets `accessory_label` for a resource that has accessories (resource 2 here) and the original template shows it beside the Accessory of select too; the earlier before shot was a resource without accessories. |
| Mobile twins (`templates/mobile/edit.xet` for tracker, resources, calendar, projectmanager, ...) | DECISION (2026-10-19): left alone unless there is a compelling reason. Different designs (button icons in a header slot, a search bar, ownership tab) and the mobile skin is chosen from the User-Agent, which I cannot emulate here. Desktop first; mobile needs a decision. |

