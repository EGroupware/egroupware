# Mail: mail/js/app.ts naming cleanup (backlog)

## Status: scoped (2026-08-21); custom-labels, quota/vacation/filter-refresh, message-actions
(delete/flag/move/copy/save/header/integrate), folder-CRUD, sieve/vacation, ACL-dialog,
subscription, and folder-management/tree-lock groups renamed (2026-08-21), rest not started

Ralf asked for this to be tracked as a future cleanup, explicitly *not* to reorder ahead of the
folder-tree JMAP migration ([[mail-folder-tree-jmap]]) or other in-progress mail work. On
2026-08-21 he asked to continue with it, so this doc now carries a real inventory instead of a
placeholder. **No renames have been performed yet** — this is planning only.

## Goal

`mail/js/app.ts` (`MailApp`, ~7200 lines) has two naming inconsistencies accumulated over time:

- A `mail_` prefix on many methods (`mail_changeFolder`, `mail_refreshFolderStatus`,
  `mail_emptySpam`, ...) — redundant once it's already a method on `MailApp`, and inconsistently
  applied (plenty of methods have no prefix at all).
- Inconsistent casing — snake_case (`openstart_tree`, `acl_common_rights`, `edit_sieve`,
  `set_smimeFlags`, ...) alongside camelCase elsewhere.

Ralf wants the `mail_` prefix dropped and naming made consistently camelCase throughout the file.

## Method inventory

179 methods enumerated directly on `MailApp` (excludes nested closures/callbacks like
`setTimeout(function(){...})`, inline `callback(...)` helpers, etc., which aren't class methods).

Call-site counts below come from a whole-repo `grep -w` for the bare method name across
`*.ts`/`*.js`/`*.php`/`*.xet` (excluding `mail/js/app.ts` itself, since every method is
self-referenced there at minimum for its own declaration/internal calls). **A `0` external-hit
count means "no OTHER file references the bare name" — it does NOT prove the method is dead**:
it's still called from within `app.ts` itself (e.g. via `this.methodName(...)` elsewhere in the
same file, or wired into a widget via `jQuery.proxy(this.methodName, this)` at setup time), and a
plain grep can't rule out reflection lookups. **Re-grep at actual rename time** — this table is a
scoping aid, not a substitute for checking before each individual rename.

### Bucket A — must NOT rename (inherited / framework contract)

These share a name with a method the framework calls generically across every app's `app.ts`
(`api/js/jsapi/egw_app.ts` defines/declares the same name), or with something the framework calls
by fixed name via reflection (`EgwFramework.ts`'s `this.getApp(app.name).refresh(...)`). Renaming
any of these breaks the override contract silently — TypeScript won't catch it, since the base
class method still exists under the old name and the subclass would just stop overriding it.

| Method | Why it's fixed |
|---|---|
| `destroy` | `EgwApp.destroy()` override |
| `et2_ready` | `EgwApp.et2_ready()` override — etemplate2 lifecycle hook |
| `push` | `EgwApp.push()` override — push-notification handler |
| `observer` | `EgwApp.observer()` override — egw_data observer contract |
| `refresh` | `EgwApp.refresh` contract — called generically as `getApp(name).refresh(...)` by `kdots/js/EgwFramework.ts` and directly by nextmatch/datagrid code |
| `action` | `EgwApp.action()` override |
| `_do_action` | `EgwApp._do_action()` override |
| `_set_Window_title` | `EgwApp._set_Window_title()` override |
| `getWindowTitle` | `EgwApp.getWindowTitle()` override |
| `mailvelopeGetCheckRecipients` | `EgwApp.mailvelopeGetCheckRecipients()` override |
| `checkNmFilterChanged` | `EgwApp.checkNmFilterChanged()` override |
| `changeNmFilter` | `EgwApp.changeNmFilter()` override |

### Bucket B — already camelCase, no rename needed (~30)

`checkET2`, `notifyNew`, `setCompose`, `showAllHeader`, `attachmentsBlockActions`,
`renderMessageInto`, `renderPopupMessage`, `setupViewAttachmentActions`, `resolveExternalImages`,
`uploadForImport`, `vfsUploadForImport`, `vacationFilterStatusChange`, `loadIframe`,
`prepareMailvelopePrint`, `mailvelopeDisplay`, `mailvelopeCompose`, `togglePgpEncrypt`,
`folderManagement`, `mobileView`, `smimeSigBtn`, `smimePassDialog`,
`smimeAttachmentsCheckerInterval`, `getPreviewPaneState`, `modifyMessageSubjectDialog`,
`onclickCompose`, `addressbookSelect`, `toggleDetails`, `vfsSaveCallback`,
`saveAttachmentHandler`, `displayAttachment`.

Note: `folderManagement` is fully spelled out while three related methods abbreviate it
(`folderMgmt_onSelect`, `folderMgmt_deleteBtn`, `mail_folderMgmtDeleteOne`) — worth deciding
whether the cleanup also unifies `Mgmt` → `Management` for consistency (see Bucket D notes).

### Bucket C — `mail_` prefix to strip (~95 methods)

Call-site risk: **high** = referenced from `.xet` templates and/or PHP (string-based JS calls,
`onExecute`/action bindings) — a missed rename here is invisible to `tsc` and fails silently at
runtime. **low** = only referenced from other `.ts`/`.js`, or no external hits found (verify at
rename time per the caveat above).

| Current | Proposed | xet hits | php hits | ts/js hits | Risk | Notes |
|---|---|---|---|---|---|---|
| ~~`mail_getCustomLabels`~~ | `getCustomLabels` | 0 | 0 | 5 | low | **done 2026-08-21** |
| ~~`mail_getCustomLabelId`~~ | `getCustomLabelId` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_isCustomLabel`~~ | `isCustomLabel` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_getLabelIds`~~ | `getLabelIds` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_isLabel`~~ | `isLabel` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_updateCustomLabelStylesheet`~~ | `updateCustomLabelStylesheet` | 0 | 0 | 2 | low | **done 2026-08-21** |
| `mail_rebuildActionsOnList` | `rebuildActionsOnList` | 0 | 1 | 0 | **high** | PHP-string call site |
| `mail_isValidRowId` | `isValidRowId` | 0 | 0 | 0 | low | private |
| `mail_fetchCurrentlyFocussed` | `fetchCurrentlyFocussed` | 0 | 0 | 0 | low | |
| `mail_open` | ⚠️ **do not call it `open`** | 0 | 1 | 0 | **high** | **Collision**: `EgwApp.open(_action,_senders)` is a base-class method (Bucket A). Stripping the prefix naively would silently turn this into an accidental override. Needs a different name, e.g. `openMessage`. |
| `mail_openAsHtml` | `openAsHtml` | 0 | 1 | 0 | high | |
| `mail_openAsText` | `openAsText` | 0 | 1 | 0 | high | |
| `mail_compose` | `compose` | 7 | 37 | 10 | **high** | Widely referenced — plan this rename carefully |
| `mail_disablePreviewArea` | `disablePreviewArea` | 0 | 0 | 0 | low | |
| `mail_display` | `display` | 0 | 0 | 0 | low | |
| `mail_preview` | `preview` | 3 | 3 | 4 | high | |
| `mail_setMailBody` | `setMailBody` | 0 | 0 | 0 | low | |
| ~~`mail_refreshFolderStatus`~~ | `refreshFolderStatus` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_refreshQuotaDisplay`~~ | `refreshQuotaDisplay` | 0 | 0 | 3 | low | **done 2026-08-21** |
| ~~`mail_setQuotaDisplay`~~ | `setQuotaDisplay` | 0 | 1 | 3 | high | **done 2026-08-21** |
| ~~`mail_callRefreshVacationNotice`~~ | `callRefreshVacationNotice` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`mail_refreshVacationNotice`~~ | `refreshVacationNotice` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`mail_searchtype_change`~~ | `searchtypeChange` | 0 | 1 | 0 | high | **done 2026-08-21**, also fixed trailing snake_case |
| ~~`mail_refreshFilter2Options`~~ | `refreshFilter2Options` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`mail_refreshFilterOptions`~~ | `refreshFilterOptions` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`mail_refreshCatIdOptions`~~ | `refreshCatIdOptions` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`mail_queueRefreshFolderList`~~ | `queueRefreshFolderList` | 0 | 0 | 0 | low | **done 2026-08-21** |
| `mail_CheckFolderNoSelect` | `checkFolderNoSelect` | 0 | 7 | 0 | **high** | also fixes bad capitalization; 7 PHP call sites |
| `mail_setFolderStatus` | `setFolderStatus` | 0 | 3 | 1 | high | |
| `mail_setLeaf` | `setLeaf` | 0 | 1 | 0 | high | |
| `mail_removeLeaf` | `removeLeaf` | 0 | 1 | 0 | high | |
| `mail_reloadNode` | `reloadNode` | 0 | 3 | 0 | high | |
| `mail_refreshMessageGrid` | `refreshMessageGrid` | 0 | 0 | 0 | low | |
| ~~`mail_getMsg`~~ | `getMsg` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_setMsg`~~ | `setMsg` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_delete`~~ | `deleteMessage` | 0 | 1 | 0 | high | **done 2026-08-21** (used `deleteMessage`, not bare `delete`, for clarity/distinctness from `deleteMessages`) |
| ~~`mail_callDelete`~~ | `callDelete` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_reduceCounterWithoutServerRoundtrip`~~ | `reduceCounterWithoutServerRoundtrip` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_splitRowId`~~ | `splitRowId` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_tryJmapDelete`~~ | `tryJmapDelete` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_deleteMessages`~~ | `deleteMessages` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_deleteMessagesShowResult`~~ | `deleteMessagesShowResult` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`mail_retryForcedDelete`~~ | `retryForcedDelete` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`mail_undeleteMessages`~~ | `undeleteMessages` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_emptySpam`~~ | `emptySpam` | 0 | 2 | 0 | high | **done 2026-08-21** |
| ~~`mail_emptyTrash`~~ | `emptyTrash` | 0 | 2 | 0 | high | **done 2026-08-21** |
| ~~`mail_changeProfile`~~ | `changeProfile` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_changeFolder`~~ | `changeFolder` | 2 | 0 | 1 | high | **done 2026-08-21** |
| ~~`mail_checkAllSelected`~~ | `checkAllSelected` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_doActionCall`~~ | `doActionCall` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_getActiveFilters`~~ | `getActiveFilters` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_flag`~~ | `flag` | 0 | 18 | 0 | **high** | **done 2026-08-21**, 18 PHP call sites |
| ~~`mail_refreshRows`~~ | `refreshRows` | 0 | 0 | 1 | low | **done 2026-08-21** |
| ~~`mail_patchRow`~~ | `patchRow` | 0 | 0 | 1 | low | **done 2026-08-21** |
| ~~`mail_callFlagMessages`~~ | `callFlagMessages` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_buildJmapQuery`~~ | `buildJmapQuery` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_flagMessages`~~ | `flagMessages` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_displayHeaderLines`~~ | `displayHeaderLines` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_header`~~ | `header` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`mail_mailsource`~~ | `mailSource` | 0 | 1 | 0 | high | **done 2026-08-21**, fixed casing |
| ~~`mail_save`~~ | `save` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`mail_save2fm`~~ | `save2Fm` | 0 | 1 | 1 | high | **done 2026-08-21** (ralf confirmed keeping the `2` shorthand) |
| ~~`mail_integrate`~~ | `integrate` | 0 | 5 | 0 | high | **done 2026-08-21** for the 3 real `app.mail.mail_integrate` action bindings in mail_ui.inc.php; left the 2 unrelated `tempnam()` prefix-string uses of the literal `"mail_integrate"` in mail_compose.inc.php/mail_integration.inc.php untouched (not method calls) |
| ~~`mail_getFormData`~~ | `getFormData` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_move2folder`~~ | `move2Folder` | 0 | 2 | 0 | high | **done 2026-08-21** (ralf confirmed keeping the `2` shorthand) |
| ~~`mail_move`~~ | `move` | 0 | 1 | 6 | high | **done 2026-08-21** |
| ~~`mail_trySetMdnFlag`~~ | `trySetMdnFlag` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_callMove`~~ | `callMove` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_copy`~~ | `copy` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`mail_callCopy`~~ | `callCopy` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`mail_AddFolder`~~ | `addFolder` | 0 | 1 | 1 | high | **done 2026-08-21**, fixed casing |
| ~~`mail_tryJmapAddFolder`~~ | `tryJmapAddFolder` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_RenameFolder`~~ | `renameFolder` | 0 | 1 | 1 | high | **done 2026-08-21**, fixed casing |
| ~~`mail_tryJmapRenameFolder`~~ | `tryJmapRenameFolder` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_MoveFolder`~~ | `moveFolder` | 0 | 1 | 2 | high | **done 2026-08-21**, fixed casing; distinct from `move2Folder` |
| ~~`mail_tryJmapMoveFolder`~~ | `tryJmapMoveFolder` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_DeleteFolder`~~ | `deleteFolder` | 0 | 1 | 1 | high | **done 2026-08-21**, fixed casing |
| ~~`mail_tryJmapDeleteFolder`~~ | `tryJmapDeleteFolder` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_tryJmapSetSubscribed`~~ | `tryJmapSetSubscribed` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_subscriptionSubselect`~~ | `subscriptionSubselect` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_subscriptionLoad`~~ | `subscriptionLoad` | 0 | 1 | 1 | high | **done 2026-08-21**, private |
| ~~`mail_seedSubscriptionValue`~~ | `seedSubscriptionValue` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_recordSubscriptionChange`~~ | `recordSubscriptionChange` | 0 | 0 | 0 | low | **done 2026-08-21**, private |
| ~~`mail_subscriptionSave`~~ | `subscriptionSave` | 2 | 0 | 0 | high | **done 2026-08-21** |
| ~~`mail_folderManagementLoad`~~ | `folderManagementLoad` | 0 | 1 | 0 | high | **done 2026-08-21**, private |
| ~~`mail_buildRootFolderData`~~ | `buildRootFolderData` | 0 | 0 | 2 | low | **done 2026-08-21**, private |
| ~~`mail_refreshFolderLevel`~~ | `refreshFolderLevel` | 0 | 0 | 1 | low | **done 2026-08-21**, private |
| `mail_print` | `print` | 0 | 1 | 0 | high | |
| `mail_prepare_print` | `preparePrint` | 0 | 0 | 0 | low | also fixes snake_case |
| `mail_display_print` | `displayPrint` | 0 | 0 | 0 | low | also fixes snake_case |
| `mail_prev_print` | `prevPrint` | 0 | 0 | 0 | low | also fixes snake_case |
| ~~`mail_folderMgmtDeleteOne`~~ | `folderManagementDeleteOne` | 0 | 1 | 0 | high | **done 2026-08-21** (ralf confirmed expanding Mgmt→Management) |

### Bucket D — snake_case (no `mail_` prefix) to fix (~35 methods)

| Current | Proposed | xet hits | php hits | ts/js hits | Risk | Notes |
|---|---|---|---|---|---|---|
| `register_for_drag` | `registerForDrag` | 0 | 0 | 0 | low | |
| `drag_attachment` | `dragAttachment` | 0 | 1 | 0 | high | |
| `spamfolder_enabled` | `spamfolderEnabled` | 0 | 1 | 0 | high | |
| `archivefolder_enabled` | `archivefolderEnabled` | 0 | 1 | 0 | high | |
| ~~`sieve_enabled`~~ | `sieveEnabled` | 0 | 2 | 2 | high | **done 2026-08-21** |
| `acl_enabled` | `aclEnabled` | 0 | 2 | 6 | high | |
| `updateFilter_data` | `updateFilterData` | 0 | 0 | 0 | low | |
| `address_click` | `addressClick` | 12 | 0 | 0 | **high** | 12 `.xet` bindings |
| `integrate_checkAppEntry` | `integrateCheckAppEntry` | 0 | 0 | 1 | low | |
| ~~`sieve_focus_radioBtn`~~ | `sieveFocusRadioBtn` | 3 | 0 | 0 | high | **done 2026-08-21** |
| ~~`sieve_vac_all_aliases`~~ | `sieveVacAllAliases` | 2 | 0 | 0 | high | **done 2026-08-21** |
| ~~`sieve_refresh`~~ | `sieveRefresh` | 0 | 2 | 0 | high | **done 2026-08-21** |
| ~~`acl_common_rights_selector`~~ | `aclCommonRightsSelector` | 2 | 0 | 0 | high | **done 2026-08-21** |
| ~~`acl_common_rights`~~ | `aclCommonRights` | 22 | 0 | 0 | **high** | **done 2026-08-21** |
| ~~`edit_sieve`~~ | `editSieve` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`edit_vacation`~~ | `editVacation` | 2 | 1 | 0 | high | **done 2026-08-21** |
| ~~`subscription_refresh`~~ | `subscriptionRefresh` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`edit_subscribe`~~ | `editSubscribe` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`subscribe_folder`~~ | `subscribeFolder` | 0 | 0 | 1 | low | **done 2026-08-21** |
| ~~`unsubscribe_folder`~~ | `unsubscribeFolder` | 0 | 1 | 1 | high | **done 2026-08-21** |
| ~~`foldertree_subselect`~~ | `folderTreeSubselect` | 2 | 0 | 0 | high | **done 2026-08-21**, also fixed `foldertree`→`folderTree` |
| ~~`edit_acl`~~ | `editAcl` | 0 | 6 | 0 | high | **done 2026-08-21** |
| ~~`acl_folderChange`~~ | `aclFolderChange` | 2 | 0 | 0 | high | **done 2026-08-21** |
| ~~`acl_run_recursive`~~ | `aclRunRecursive` | 0 | 1 | 0 | high | **done 2026-08-21** |
| ~~`acl_save`~~ | `aclSave` | 4 | 1 | 0 | high | **done 2026-08-21** |
| ~~`acl_delete_row`~~ | `aclDeleteRow` | 2 | 1 | 0 | high | **done 2026-08-21** |
| ~~`edit_account`~~ | `editAccount` | 0 | 3 | 0 | high | **done 2026-08-21** |
| ~~`lock_tree`~~ | `lockTree` | 0 | 2 | 0 | high | **done 2026-08-21** |
| ~~`unlock_tree`~~ | `unlockTree` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`openstart_tree`~~ | `openStartTree` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`openend_tree`~~ | `openEndTree` | 0 | 0 | 0 | low | **done 2026-08-21** |
| ~~`vacation_change_account`~~ | `vacationChangeAccount` | 2 | 0 | 0 | high | **done 2026-08-21** |
| `clearIntevals` | `clearIntervals` | 0 | 1 | 0 | high | also fixes a typo (`Intevals`→`Intervals`), not just casing |
| ~~`folderMgmt_onSelect`~~ | `folderManagementOnSelect` | 1 | 0 | 0 | high | **done 2026-08-21** (expanded Mgmt→Management) |
| ~~`folderMgmt_deleteBtn`~~ | `folderManagementDeleteBtn` | 1 | 0 | 0 | high | **done 2026-08-21** (expanded Mgmt→Management) |
| `spam_actions` | `spamActions` | 0 | 7 | 0 | high | |
| `spamTitan_setActionTitle` | `spamTitanSetActionTitle` | 0 | 0 | 0 | low | (only mixed-case fix, no real snake_case) |
| `set_smimeAttachmentsMobile` | `setSmimeAttachmentsMobile` | 0 | 0 | 0 | low | |
| `set_smimeAttachments` | `setSmimeAttachments` | 0 | 1 | 0 | high | |
| `set_smimeFlags` | `setSmimeFlags` | 0 | 4 | 0 | high | |
| `smime_clear_flags` | `smimeClearFlags` | 0 | 0 | 0 | low | |
| `smime_certAddToContact` | `smimeCertAddToContact` | 0 | 2 | 0 | high | |
| `set_predefined_addresses` | `setPredefinedAddresses` | 0 | 1 | 0 | high | |
| `print_for_compose` | `printForCompose` | 0 | 1 | 0 | high | |
| ~~`nm_cache`~~ | `nmCache` | 0 | 0 | 0 | low | **done 2026-08-21** |

## Collision / high-attention flags

1. **`mail_open` → do NOT rename to bare `open`.** `EgwApp.open(_action, _senders)` already
   exists on the base class (Bucket A). A naive strip would silently turn `MailApp`'s method into
   an accidental override of unrelated base-class behavior — no compiler error, a real runtime bug.
   Pick something else, e.g. `openMessage`.
2. **`mail_save2fm` / `mail_move2folder`** — the `2` (as in "to") is a style choice ralf should
   confirm: keep the digit (`save2Fm`, `move2Folder`) or spell out `saveToFilemanager` /
   `moveToFolder`.
3. **`folderManagement` vs. `folderMgmt_*` / `mail_folderMgmtDeleteOne`** — inconsistent
   abbreviation of "management" already exists independent of this cleanup; worth deciding
   whether to unify while renaming these three.
4. Several of the highest blast-radius methods (`mail_compose`, `acl_common_rights`, `acl_save`,
   `acl_delete_row`, `address_click`) are also the ones most recently touched by the ACL-dialog UX
   overhaul and mail-composition work this session — coordinate so a rename PR doesn't collide
   with still-in-flight work on those files ([[project_mail_acl_dialog_ux]],
   [[project_mail_jmap_acl_plan]]).

## Recommended approach

**Incremental, grouped by feature area, not one giant rename.** Reasons:

- Blast radius is uneven: some methods (bucket "low" risk) are pure internal TS-to-TS calls that
  `tsc` will catch if missed; others (`acl_common_rights` with 22 `.xet` hits, `mail_flag` with 18
  PHP hits, `mail_compose` with 54 total hits) are wide, string-based, compiler-invisible surface.
  Splitting by feature area (ACL, folder CRUD, delete/flag/move actions, sieve/vacation, S/MIME,
  subscription, print, custom labels) keeps each PR reviewable and keeps the high-risk `.xet`/PHP
  cross-checks scoped to what that PR actually touches.
- A single 179-method rename touching ~7200 lines plus dozens of `.xet`/PHP call sites would be
  an unreviewable diff and a single bad merge conflict away from breaking master.
- Natural groupings from the tables above (already roughly delimited by section in the source
  file): custom labels; open/preview/compose/display; quota/vacation/filter refresh; delete/flag/
  move/copy actions; folder CRUD (`AddFolder`/`RenameFolder`/`MoveFolder`/`DeleteFolder` +
  their JMAP try-helpers); sieve; ACL dialog; subscription; folder management; print; mailvelope;
  S/MIME; misc UI (predefined addresses, compose-click, details toggle).
- Do the Bucket A/B audit (this doc) once up front (done), then land each group as its own commit/
  PR so `git blame` stays useful and CI catches any group's regressions in isolation before the
  next group starts.

## Related

- [[mail-folder-tree-jmap]] — touched `mail/js/app.ts` during the folder-tree migration; new
  methods added there (`mail_folderTreeAutoload` etc. — note: not found in the current inventory
  above, may have been renamed/removed since, or lives in `folderTree.ts` instead — re-verify
  before assuming it still needs the `mail_` prefix treatment) followed the existing `mail_`
  convention deliberately at the time, to stay consistent with surrounding code until this cleanup
  happens.
- [[project_mail_acl_dialog_ux]], [[project_mail_jmap_acl_plan]] — actively touched several of the
  ACL methods flagged above; coordinate timing (see flag #4).
