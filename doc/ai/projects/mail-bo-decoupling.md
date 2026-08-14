# Mail: `Api\Mail`/`mail_ui` decomposition for testability

## Status: Phase 1 in progress (started 2026-08-14)

This doc captures an analysis of `api/src/Mail.php` (`Api\Mail`, the `mail_bo` business-object class)
and `mail/inc/class.mail_ui.inc.php` (`mail_ui`, the Etemplate app class), aimed at breaking both apart
into smaller, independently-testable components. Triggered by the observation that `MailApp`
(`mail/js/app.ts`, client-side) has the same problem and currently has **zero** test coverage as a
result - anything that needs it relies on live testing against `boulder.egroupware.org` instead of
`phpunit`/`web-test-runner`. See [[mail-jmap-modernization]] for the ongoing JMAP work these two
classes are also the backbone of - any extraction here should stay compatible with that project's
dispatch pattern (`jmap<MethodName>()` helpers, see below) rather than fight it.

### Extraction discipline (applies to every group below)

- **Compatibility wrappers are the exception, not the default.** The only external (separate-repo)
  consumers are tracker's mail-handler (calls a handful of `Api\Mail` methods by name) and
  client-side TS calling `mail_ui`'s `ajax_*` methods by name. A method not called from either of
  those gets its in-repo call sites updated directly to the new class and is removed from
  `Api\Mail`/`mail_ui` outright - no wrapper kept "just in case". Before deciding, grep the whole
  repo (including the separately-checked-out `tracker/` directory) for every call site.
- **While moving a method, clean it up**: strip commented-out debug code (`//error_log(...)` litter
  is pervasive from the former maintainer) and dead/defensive branches that can never actually
  trigger (found one: a `$test=="null"` string comparison in two different places - address-decoding
  utf8-repair and CSS utf8-repair - that `json_encode()` can never actually produce for a string
  input, since it returns `false`, not the literal string `"null"`, on encode failure).
- **If a method's only reference anywhere in the repo (including `tracker/`) is inside a disabled,
  commented-out line, it's dead code, not a method to extract** - delete it instead of moving it.
  Found `getMimePartCharset()`/`decodeMimePart()` this way (the only call was commented out with
  "RB: not sure what this is" - the user's own past comment on the same dead code).
- **Look for copy-paste-with-small-variations across the methods in the group being moved**, and
  consolidate into one parameterized private helper instead of preserving the duplication. First
  instance found: `decode_header()` and `convertAddressArrayToString()` each had their own
  identical "try `Horde_Idna::decode()` on the host, fall back to the raw host on failure, then
  `imap_rfc822_write_address()`" block - extracted into
  `AddressList::writeAddressAttemptingIdnaDecode()`.
- Add real unit test coverage for the extracted class as part of the same change, not as a follow-up.
- **A "zero callers" conclusion from a case-sensitive grep is not trustworthy for PHP method names -
  method calls (including string/array callables passed to `uasort()`/`call_user_func()`/etc.) are
  resolved case-insensitively.** A live smoke test caught a real regression this discipline note
  exists because of: `sortByAutoFolder()` was declared dead (a case-sensitive repo search found no
  call site, only two comments explaining why it was "avoided") and deleted - but a live real call
  site existed as `array($this,'sortByAutofolder')` (lowercase "f"), which had been silently working
  via PHP's case-insensitive method resolution the whole time. Fixed by re-auditing every moved/
  deleted method name with a case-insensitive search before trusting a "no callers" result again.

## Progress

- [x] **Address-list parsing** → `api/src/Mail/AddressList.php` (`parseAddressList`,
  `stripRFC822Addresses`, `convertAddressArrayToString`, `decode_header`, `decode_subject`).
  `parseAddressList()`/`decode_subject()` stay as thin `Api\Mail` wrappers (tracker's mail-handler
  calls both by name); the other three were removed from `Api\Mail` entirely and every in-repo call
  site (mail_compose, mail_ui, calendar, Contacts, Avatar, the Url widget) now calls `AddressList::`
  directly. Tests: `api/tests/Mail/AddressListTest.php` (new) +
  `api/tests/Mail/ParseAddressListTest.php` (pre-existing, still tests the wrapper).
- [x] **MIME/body-decoding utilities** → `api/src/Mail/BodyDecoding.php` (`htmlentities`,
  `getCleanHTML`, `normalizeBodyParts`, `getStyles`, `wordwrap`). None of the five were called from
  tracker, so all were removed from `Api\Mail` outright (no wrappers) and every in-repo call site
  (mail_compose, mail_ui, mail_zpush) now calls `BodyDecoding::` directly. `getMimePartCharset()`/
  `decodeMimePart()` were dead code (see discipline note above) and got deleted instead of moved.
  Tests: `api/tests/Mail/BodyDecodingTest.php` (new).
- [x] **Custom labels/keywords** → `api/src/Mail/CustomLabels.php` (`getCustomLabels`,
  `categoriesToCustomLabels`, `validateKeyword`, `customLabelId`, `isLabelKeyword`,
  `labelSearchCriterion`, `labelSearchFromStatus`). None called from tracker, so all removed from
  `Api\Mail` outright; every in-repo call site (mail_ui, JmapShim, `Api\Mail::prepareFlagsArray()`/
  `createIMAPFilter()` which stay put) now calls `CustomLabels::` directly. The public
  `Api\Mail::$customLabels`/`$customLabelsCache` statics stayed on `Api\Mail` (deployment-settable
  config, not internal state). Tests: pre-existing `api/tests/Mail/CustomLabelsTest.php`, updated to
  target the new class where it used reflection/direct static calls.
- [x] **Folder-naming/string helpers** → `api/src/Mail/FolderHelpers.php`
  (`decodeEntityFolderName`, `searchValueInFolderObjects`, `pathToFolderData`). None called from
  tracker, so all removed from `Api\Mail` outright; every in-repo call site (mail_ui, mail_tree) now
  calls `FolderHelpers::` directly. `encodeFolderName()` and `_encodeFolderName()` were genuinely
  dead code (zero callers anywhere, confirmed both case-sensitively and case-insensitively) and got
  deleted instead of moved, along with 4 dead `//$this->_encodeFolderName(...)` comments at
  `createFolder()`/`renameFolder()`/`deleteFolder()`. The `uasort()` comparators (`sortByMailbox`,
  `sortByDisplayName`, `sortByAutoFolderPos`, `sortByAutoFolder`) were extracted as static methods
  first, then **inlined as closures at their call sites in `Api\Mail` instead**, per user direction
  ("these things better go into a closure, than a separate method") - a comparator passed as a
  named-method callable has no real reuse benefit over an inline closure, and removing the name
  removes the exact bug class the case-insensitivity incident below exposed. Tests:
  `api/tests/Mail/FolderHelpersTest.php` (new, covers the 3 real methods only).

**Incident**: a live smoke test after Phase 1 threw `uasort(): ... does not have a method
"sortByAutofolder"` - `sortByAutoFolder()` (distinct from `sortByAutoFolderPos()`) had been declared
dead code and deleted, based on a case-sensitive repo search finding no call site (only two comments
explaining why calling it was "avoided" elsewhere). The real call site used
`array($this,'sortByAutofolder')` - lowercase "f" - which PHP resolves case-insensitively for method
callables, so it had been silently working. Fixed by restoring the logic as an inline closure at
that real call site, and re-auditing every one of Phase 1's moved/deleted method names with a
case-insensitive repo-wide search (`api/`, `mail/`, `calendar/`, `addressbook/`, `infolog/`,
`tracker/`) - no other mismatches found. Lesson folded into the discipline notes above.

## Phase 1 complete (2026-08-14)

All four low-risk `Api\Mail` groups from the original proposal are done: `AddressList`,
`BodyDecoding`, `CustomLabels`, `FolderHelpers` - 4 new pure, unit-tested classes, ~30 new
assertions, and `Api\Mail` itself is roughly 400 lines smaller (dead code deleted outright beyond
what moved: `getMimePartCharset`, `decodeMimePart`, `encodeFolderName`, `_encodeFolderName`, plus
assorted dead `//error_log(...)` comments and never-true defensive branches cleaned up along the
way; the 4 sort comparators became inline closures rather than surviving as named methods anywhere).
Next up, when resumed: `mail_ui`'s ajax-handler regrouping (see table above), then the higher-risk
`Api\Mail` groups (folder management, header/search, body/attachment IMAP fetch) - those need the
`$icServer` coupling question answered first, see "Recommended approach" above.

## Scale

| File | Lines | Methods |
|---|---|---|
| `api/src/Mail.php` | 9124 | ~142 |
| `mail/inc/class.mail_ui.inc.php` | 6266 | ~74 |
| `mail/js/app.ts` (`MailApp`, client-side, same problem, not analyzed in depth here) | 6620 | - |

## Why this is hard, not just tedious

- **`Api\Mail` has no formal state boundary.** `icServer`, `mailPreferences`, `htmlOptions`,
  `sessionData`, `accountid`, `profileID` etc. are dynamic (undeclared-typed, legacy `var $x`)
  properties set from several different entry points (`getInstance()`, `forceEAProfileLoad()`,
  session restore). A method can't be lifted out to a standalone class without first knowing exactly
  which of these it actually touches - and PHP won't tell you; it has to be read.
- **The `$icServer` object itself is deeply leaned on as a cache key**, not just a connection handle:
  74 occurrences of `$icServer->ImapServerId` as a cache-array key across this file alone (per the
  composition-vs-inheritance investigation in [[mail-jmap-modernization]]). Any component that owns
  its own cache needs to either take `ImapServerId` as an explicit key or accept `$icServer`/`Mail`
  itself as a collaborator - there's no clean narrower handle already in existence.
- **Some methods are dual-purpose by construction, not accident.** The `jmap<MethodName>()` /
  `<MethodName>()` pairing (documented in [[mail-jmap-modernization]]'s "Dispatch pattern" section) is
  itself a *deliberate* seam: the JMAP half is already backend-agnostic and mostly free of `$icServer`
  IMAP calls. Splitting `Api\Mail` should treat that pairing as a hint about where a boundary already
  exists, not something to flatten back together.
- **`mail_ui` mixes three unrelated concerns in one class** because EGroupware's menuaction routing
  requires a single class per app: Etemplate page rendering (`index()`, `displayMessage()`, template
  setup), a large bag of `ajax_*` JSON-RPC endpoints (most of the method count), and static helpers
  called both from JS-triggered ajax calls and from elsewhere in the app (`createAttachmentBlock()`,
  `generateRowID()`). Only the first genuinely has to stay on the class EGroupware's router dispatches
  to; the other two can move behind it.

## `Api\Mail` (mail_bo) - proposed component groups

Grouped from the full method inventory (see `grep -n "^\tfunction \|^\tpublic function \|..." api/src/Mail.php`
for the raw list). Each group below is a *candidate* extraction target, not a commitment - "coupling"
column is the rough blocker to extracting it as a standalone, `Api\Mail`-independent class.

| Group | Representative methods | Coupling to `Api\Mail` state | Extraction risk |
|---|---|---|---|
| **Address-list parsing** | `parseAddressList`, `stripRFC822Addresses`, `convertAddressArrayToString`, `decode_header`, `decode_subject` | None - pure string/array in, string/array out, already mostly `static` | **Lowest.** This is exactly the code the recent encoded-word-comma bug fix touched; `parseAddressList` already has isolated regression tests (`api/tests/Mail/ParseAddressListTest.php`). Formalizing it as `Mail\AddressList` (all-static, no `$icServer`) is close to a rename, not a rewrite. |
| **MIME/body decoding utilities** | `getCleanHTML`, `htmlentities`, `wordwrap`, `normalizeBodyParts`, `getStyles`, `getMimePartCharset`, `decodeMimePart` | None to low - already `static` or only touch a passed-in `Horde_Mime_Part`, not `$this` | **Low.** Same shape as address-list: mostly pure transforms on data already fetched by something else. |
| **Folder-type/naming helpers** | `isDraftFolder`, `isTrashFolder`, `isSentFolder`, `isOutbox`, `isTemplateFolder`, `getFolderByType`, `pathToFolderData`, `sortByMailbox`/`sortByDisplayName`/etc., `encodeFolderName`/`decodeEntityFolderName` | Low-medium - mostly string logic against folder names/preferences, a few call `folderExists()` which needs `$icServer` | **Low-medium.** Splitting the pure-string subset first, leaving the existence-checking variants as thin wrappers that delegate, is a safe first cut. |
| **Custom labels/keywords** | `getCustomLabels`, `categoriesToCustomLabels`, `validateKeyword`, `customLabelId`, `isLabelKeyword`, `labelSearchCriterion`, `labelSearchFromStatus` | Low - already mostly `static`, touches `Categories` (a different app's API), not `$icServer` | **Low.** Already has `api/tests/Mail/CustomLabelsTest.php`; formalizing the class boundary is mostly organizational. |
| **JMAP-dispatch helpers** | all `jmap<MethodName>()` privates (`jmapGetMessageHeader`, `jmapGetMessageBody`, `jmapFlagMessages`, `jmapMoveMessages`, ...) | Medium - depend on `$icServer instanceof Mail\Imap\Jmap`, but not on the wider IMAP connection state the classic half needs | **Medium, but arguably shouldn't move.** These already form their own conceptual unit (documented dispatch pattern); extracting them fully would mean re-plumbing every classic-method call site's `if (($r = $this->jmap...()) !== null) return $r;` header. Lower-value than the pure-utility groups above for the test-coverage goal, since they're already reasonably testable in place given a fake `Mail\Imap\Jmap`. |
| **Folder management (existence/CRUD/listing)** | `createFolder`, `renameFolder`, `deleteFolder`, `getFolderObjects`, `getFolderArrays`, `getFolderStatus`, `getMailBoxCounters`, `_getNameSpaces`, `getSpecialUseFolders`, `getQuotaRoot` | High - real IMAP calls via `$icServer`, heavy internal caching keyed by `ImapServerId` | **High.** A real `Mail\FolderManager` service is architecturally the right shape, but needs `$icServer` (or a narrower connection interface) injected, and touches the cache-invalidation methods (`resetFolderObjectCache`, `unsetCachedObjects`) that currently assume they're static methods on `Mail` itself. Do this only after the lower-risk groups prove the pattern. |
| **Header/list/search** | `getHeaders`, `getSortedList`, `createIMAPFilter`, `_getSortString`, `buildTokenizedSearch`, `parseSearchTokens` | High - `$icServer` IMAP search/fetch calls, feeds `getSortedList()`'s numeric-UID contract other callers assume (see [[mail-jmap-modernization]] on why `getSortedList()` couldn't just be swapped for JMAP everywhere) | **High.** Same caution as folder management, compounded by the numeric-UID assumption already documented as a constraint. |
| **Body/attachment fetch (classic IMAP halves)** | `getMessageBody`, `getMessageAttachments`, `getAttachment`, `getAttachmentByCID`, `getMultipartAlternative/Mixed/Related`, `getBodyPart`, `getTextPart`, `getStructure` | High - real IMAP fetches plus TNEF/S-MIME special-casing already threaded through `jmapResolveUid()`'s bail-to-classic trap (see [[mail-jmap-modernization]]) | **High.** The MIME-parsing sub-logic (walking a `Horde_Mime_Part` tree once already fetched) is more separable than the IMAP-fetch shell around it - a `Mail\BodyRenderer` that takes a `Horde_Mime_Part` and returns rendered HTML/text, independent of how that part was fetched, is plausible and would let the JMAP path (which already gets pre-parsed structure per RFC 8621, no `Horde_Mime_Part` walk needed) and the classic path share test coverage of the transform logic even though they can't share the fetch. |
| **Compose/send helpers** | `get_mailcontent`, `appendMessage`, `importMessageToMergeAndSend`, `parseFileIntoMailObject`, `parseRawMessageIntoMailObject`, `processURL2InlineImages`, `checkFileBasics` | High - `Mailer`, VFS, merge-print integration, several other apps' hooks | **High, lower priority** - explicitly out of scope for the current JMAP project too (`mail_compose.inc.php` audited and left classic, see [[mail-jmap-modernization]]); no reason to prioritize decoupling code that isn't being actively modified. |
| **Identity/account/session lifecycle** | `getInstance`, `__construct`, `restoreSessionData`, `saveSessionData`, `getAllIdentities`, `getAccountIdentities`, `getDefaultIdentity` | N/A - this *is* the state-management core everything else depends on | **Don't extract.** This is the thing other components would take as a constructor dependency, not a component itself. |
| **S/MIME** | `resolveSmimeMessage`, `_decryptSmimeBody` | Medium - `Horde_Crypt_Smime`, passphrase handling | Already fairly self-contained; lower priority since S/MIME is intentionally still classic-only per [[mail-jmap-modernization]]. |

## `mail_ui` - proposed component groups

`mail_ui` doesn't have the same "no formal state boundary" problem `Api\Mail` does (it's mostly a thin
Etemplate/ajax layer over `Api\Mail`), so splitting it is more about **routing responsibility to
dedicated handler classes**, keeping `mail_ui` itself as the thin dispatch target EGroupware's
menuaction router requires:

| Group | Representative methods | Notes |
|---|---|---|
| **Etemplate page rendering** | `index`, `subscription`, `displayHeader`, `displayMessage`, `showBody`, `folderManagement`, `get_actions`, `get_toolbar_actions`, `get_tree_actions`, `getDisplayToolbarActions` | Stays on `mail_ui` - genuinely needs to be the menuaction-routed class. |
| **Folder ajax handlers** | `ajax_tree_autoloading`, `ajax_foldersubscription`, `ajax_foldertree`, `ajax_reloadNode`, `ajax_setFolderStatus`, `ajax_addFolder`, `ajax_renameFolder`, `ajax_MoveFolder`, `ajax_deleteFolder`, `ajax_folderMgmtTree_autoloading`, `ajax_folderMgmt_delete`, `ajax_compressFolder`, `ajax_emptySpam`, `ajax_emptyTrash` | Candidate `Mail\Ui\FolderHandler`, taking `Api\Mail` as a constructor dependency; `mail_ui`'s `ajax_*` methods become one-line delegations (needed anyway since EGroupware's ajax dispatch resolves `mail_ui::ajax_foo` by name - can't move the method entirely, only its body). |
| **Message action ajax handlers** | `ajax_flagMessages`, `ajax_deleteMessages`, `ajax_copyMessages`, `ajax_sendMDN`, `ajax_saveModifiedMessageSubject`, `saveMessage` | Candidate `Mail\Ui\MessageActionHandler`. |
| **Attachment/body-fetch ajax handlers** | `getAttachment`, `ajax_resolveWinmail`, `resolveWinmailJmap`, `resolveAttachmentsBlock`, `resolveAttachmentsJmap`, `jmapAttachmentsToLegacy`, `fetchBlobBytes`, `fetchMessageBytesJmap`, `fetchAttachmentJmap`, `ajax_fetchAttachments`, `createAttachmentBlock`, `download_zip`, `ajax_vfsOpen`, `ajax_vfsSave`, `vfsSaveMessages`, `vfsSaveAttachments`, `displayImage`, `get_load_email_data`, `tryJmapNativeSpecialCase`, `loadEmailBody`, `getdisplayableBody`, `resolve_inline_images`, `resolve_inline_image_byType`, `ajax_fetchMessageDetails`, `ajax_parseAddressList` | The single biggest group by method count - candidate `Mail\Ui\AttachmentHandler` + `Mail\Ui\BodyHandler`, likely worth splitting into two given the size. `createAttachmentBlock()` is called from several other places in the app by name (per [[mail-jmap-modernization]]'s `is_winmail` composite-key note) - moving it needs a repo-wide call-site sweep, not just a `mail_ui` self-contained change. |
| **S/MIME ajax handlers** | `ajax_smimeAttachmentsChecker`, `ajax_smimeAddCertToContact`, `smimeAccountId`, `smimeExportCert`, `smimeExportCsr`, `smimePassphraseFormHtml` | Candidate `Mail\Ui\SmimeHandler` - already fairly self-contained. |
| **Import handlers** | `importMessage`, `importMessageToFolder`, `importMessageFromVFS2DraftAndEdit`, `importMessageFromVFS2DraftAndDisplay` | Candidate `Mail\Ui\ImportHandler`. |
| **Account/session/profile ajax handlers** | `changeProfile`, `ajax_jmapBootstrap`, `jmapLocalBootstrap`, `ajax_enablePush`, `ajax_changeProfile`, `ajax_refreshVacationNotice`, `gatherVacation`, `ajax_refreshFilters`, `ajax_refreshQuotaDisplay`, `quotaDisplay` | Candidate `Mail\Ui\ProfileHandler`. |
| **Row-id helpers** | `createRowID`, `generateRowID`, `generateJmapRowID`, `findNode` | Already static, low coupling - could move to `Mail\RowIdParts`/a sibling of `Api\Mail::splitRowID()` (the inverse operation, currently oddly far from it in the codebase - `splitRowID` lives on `Api\Mail`, `generateRowID` on `mail_ui`). |

## Recommended approach (if this goes ahead)

1. **Start with the "Lowest"/"Low" risk `Api\Mail` groups** (address-list parsing, MIME/body decoding
   utilities, custom labels, folder-naming string logic) - each is close to a pure-function extraction,
   each immediately gets real unit test coverage, and none requires touching `$icServer`/session state
   or any call site outside `Api\Mail` itself (keep the old method names as thin delegating wrappers so
   nothing calling `Mail::parseAddressList(...)` etc. needs to change). This alone would meaningfully
   grow test coverage of the parts of `Api\Mail` most likely to have subtle string/encoding bugs (like
   the encoded-word-comma one just fixed) without touching anything IMAP-connection-shaped.
2. **Do `mail_ui`'s ajax-handler regrouping next, one domain at a time**, starting with S/MIME or
   Import (smallest, most self-contained groups) as a template for the pattern, before attempting the
   large attachment/body group. Each `ajax_*` method's *body* moves to the new handler class; the
   `mail_ui::ajax_*` method itself becomes `return $this->attachmentHandler()->fetchAttachments($_rowid);`
   - required because EGroupware's ajax dispatcher resolves handlers by `mail_ui::ajax_methodName`, not
     by class-agnostic name.
3. **Treat folder-management, header/search, and body/attachment IMAP-fetch as a later, separate
   project** - genuinely needs `$icServer` (or a narrower "IMAP connection" interface) as an injectable
   dependency to be testable at all, which is a bigger design question (and overlaps directly with the
   already-scoped-but-not-started `Api\Mail\Imap\Jmap` composition-over-inheritance redesign in
   [[mail-jmap-modernization]] - coordinate rather than duplicate).
4. **`MailApp` (client-side) can follow the same "extract new/touched code into small standalone
   modules" pattern already established** by `mail/js/attachmentIndex.ts` (built standalone
   specifically to stay unit-testable, see [[mail-jmap-modernization]]) rather than a big-bang split -
   every future bug fix or feature in `MailApp` is an opportunity to peel off one more piece into a
   tested module, the same way `attachmentIndex.ts` and parts of `jmap.ts` already are.

## Explicitly not proposed

- A full parallel rewrite of either class. The dispatch-pattern precedent from the JMAP project
  (add a new path alongside the old one, fall back, never a flag-day cutover) applies here too -
  incremental extraction with identical external behavior, not a rewrite.
- Touching the "High" risk groups (folder management, header/search, IMAP body/attachment fetch)
  before the low-risk groups prove out the pattern and before the composition-vs-inheritance question
  for `Mail\Imap\Jmap` is resolved - doing it earlier risks solving the same coupling problem twice,
  differently, in the same codebase.
