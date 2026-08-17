# Mail: `Api\Mail`/`mail_ui` decomposition for testability

## Status: Phase 1 complete, Phase 2 complete except Api\Mail's "Folder management" group (started 2026-08-14)

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
- **When a new handler class needs "the current mailbox", give it the narrowest thing that actually
  represents that, not the whole owning object.** Session/connection state (which profile is active,
  the connected `Api\Mail` instance) is currently duplicated and scattered - `Api\Mail::getInstance()`
  has its own `self::$instances[$profileID]` registry, `mail_ui` separately tracks a static
  `$icServerID` "current profile" of its own (`mail/inc/class.mail_ui.inc.php:74`). Consolidating
  that into one real session/connection object is its own (larger, not-yet-scoped) project - but
  *until* it exists, new handler classes should still default to depending on the narrowest slice of
  it they actually need (eg. "the active `Api\Mail` instance") rather than the whole `mail_ui` object,
  where that's practical, so they're one signature change away from sitting on top of a real session
  class later instead of needing a redesign. `ImportHandler` (constructor-injected with all of
  `mail_ui`, see Phase 2 progress below) is the shape to improve on, not necessarily copy, next time
  this pattern comes up - it needed more than just `mail_bo` (`createRowID()` too), so a narrower
  dependency wasn't a clean fit there, but check first rather than defaulting to "just take
  `mail_ui`".

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

## Phase 2: `mail_ui` ajax-handler regrouping - in progress (started 2026-08-14)

Per [[mail-folder-tree-jmap]], the "Folder ajax handlers"/"Row-id helpers" `mail_ui` groups and the
"Folder management" `Api\Mail` group are blocked pending that migration - skip them here.

- [x] **S/MIME ajax handlers** → `mail/src/Ui/SmimeHandler.php` (`ajaxAttachmentsChecker`,
  `ajaxAddCertToContact`, `accountId`, `exportCert`, `exportCsr`). `mail_ui`'s `ajax_*`/
  `smimeExportCert`/`smimeExportCsr` method *names* stay (menuaction dispatch resolves handlers by
  `mail_ui::methodName`), now one-line delegations to a lazily-instantiated `smimeHandler()` getter
  (mirrors the client-side `MailApp.compose`/`.jmap` lazy-getter pattern, per user direction: "we
  already have an established pattern to move methods into sub-classes ... separate class with a
  getter instantiating it on demand"). `smimePassphraseFormHtml()` was deliberately NOT moved -
  unlike the rest of this group it's coupled to `mail_ui`'s own instance state
  (`$this->mail_bo`/`$this->get_email_header()`), so it stays with the body-rendering code it's part
  of. `accountId()` (was private `smimeAccountId()`) guards an admin-impersonation `$_GET
  ['account_id']` parameter for `exportCert()`/`exportCsr()` - the pre-existing security test for it
  (`mail/tests/SmimeAccountIdTest.php`) got simpler as a side effect: it no longer needs a
  reflection-based test-only subclass to reach a private method, since the extracted method is
  naturally public on its own class.
- [x] **Import handlers** (partial) → `mail/src/Ui/ImportHandler.php` (`importMessageToFolder`,
  `importMessageFromVFS2DraftAndDisplay`). Turned out to be **more coupled than the original table
  assumed** - unlike S/MIME, this group needs `mail_ui`'s already-connected `mail_bo` for folder
  existence checks, hierarchy-delimiter lookups, and `appendMessage()`, so `ImportHandler` takes the
  owning `mail_ui` as a constructor dependency (`__construct(mail_ui $ui)`) - the same pattern
  `mail_tree` already used, not the zero-dependency static-class shape Phase 1 achieved. This is a
  real organizational win (smaller `mail_ui.inc.php`, the import domain in one file) but **not** a
  testability win the way the pure classes were - it still can't be unit-tested without a full
  `mail_ui`+`mail_bo`+IMAP fixture. `importMessage()` was deliberately NOT moved - it's a
  dual-purpose entry point (renders the upload-form Etemplate OR processes a submitted upload) and
  the form-rendering half is genuinely `mail_ui`'s job; only its internal call to
  `importMessageToFolder()` was repointed. `importMessageFromVFS2DraftAndEdit()` turned out to be
  **fully dead code** (zero callers anywhere, case-insensitively confirmed, not even the .eml
  mime-handler hook in `mail_hooks.inc.php` - that routes through `...AndDisplay` directly) and was
  deleted rather than moved.
- [x] **Message action ajax handlers** (done, except `ajax_saveModifiedMessageSubject`) →
  `mail/src/Ui/MessageActionHandler.php` (`saveMessage`, `ajax_sendMDN`→`sendMDN`,
  `ajax_flagMessages`→`flagMessages`, `ajax_deleteMessages`→`deleteMessages`,
  `ajax_copyMessages`→`copyMessages`). The three big ones were originally left behind (each a large,
  100-200 line, heavily-branched legacy method calling back into *other* mail_ui internals) but
  turned out extractable once those other internals stopped being blockers:
  `self::ajax_setFolderStatus()` was always fine to call as `$this->ui->ajax_setFolderStatus()`
  (that method itself stays on `mail_ui`, blocked pending [[mail-folder-tree-jmap]], but *calling*
  it cross-class isn't blocked); `self::generateRowID()`/`self::$delimiter` are already public
  static on `mail_ui`, callable as `mail_ui::generateRowID()`/`mail_ui::$delimiter` from anywhere;
  `self::get_actions()` was the one real blocker - `private`, so a call from a class that doesn't
  extend `mail_ui` would fatal - **widened to package-default visibility** (it's pure UI
  context-menu-array construction, nothing security-sensitive, and already called from 5 places
  including a `new mail_ui(false)` instance in `ajax_changeProfile()`, so this isn't a new exposure).
  `ajax_saveModifiedMessageSubject()` stayed - it depends on `AttachmentJmap::fetchMessageBytesJmap()`/
  `AttachmentJmap::replaceMessageJmap()` and belongs with "Attachment/body-fetch ajax handlers", not
  this group - the original table's grouping put it in the wrong bucket.
  **Near-miss caught again**: `ajax_copyMessages()` (kept as a thin wrapper) is called internally
  from `ajax_spamAction()` via `$this->ajax_copyMessages(...)` in 3 places - only found by grepping
  the moved method names against the whole file afterward, same discipline as the
  `resolve_inline_images()` near-miss in the previous group. Still correct here since the wrapper
  was kept (not removed), but worth remembering as a recurring risk: internal self-calls are easy to
  miss when scanning only for *external* callers before deciding a method's fate.
  - **`ajax_emptySpam`/`ajax_emptyTrash` added later (2026-08-15)** → `emptySpam()`/`emptyTrash()`,
    once [[mail-folder-tree-jmap]]'s investigation confirmed these two are permanent classic
    fallbacks behind an already-existing client-side JMAP fast path (`MailJmap.purgeFolder()`),
    not folder-tree-migration-blocked code - see that doc's "Resolved" note. Same
    `mail_ui`-constructor-injection shape as the rest of this class; both kept thin `mail_ui`
    wrappers (menuaction-dispatched by `app.ts`'s classic-fallback calls). No internal near-misses
    this time - case-insensitive audit of both names across the whole repo found only the two
    kept wrappers and the client-side callers.
- [x] **Account/session/profile ajax handlers** (partial) → `mail/src/Ui/ProfileHandler.php`
  (`ajax_jmapBootstrap`→`jmapBootstrap`, `jmapLocalBootstrap`→`localBootstrap`,
  `ajax_enablePush`→`enablePush`, `quotaDisplay`). These four were the pleasant surprise of this
  group - already written (by an earlier phase of [[mail-jmap-modernization]]) to take an explicit
  profile id rather than depend on `mail_ui`'s connected `mail_bo`, specifically so they'd have no
  side effect on the session's "active profile" state - so this class needs no `mail_ui` instance at
  all, just two read-only references to `mail_ui`'s own public static properties (`$icServerID` as a
  fallback default, `$delimiter` for folder-id parsing). Zero-dependency like Phase 1, not
  constructor-injected like ImportHandler/MessageActionHandler. `quotaDisplay` had no external
  callers and was removed from `mail_ui` outright (2 internal call sites repointed); the other three
  are menuaction-dispatched, so `mail_ui` keeps thin wrappers.
  `changeProfile`/`ajax_changeProfile`/`gatherVacation`/`ajax_refreshVacationNotice`/
  `ajax_refreshFilters`/`ajax_refreshQuotaDisplay` stayed - each either mutates `mail_ui`'s own
  instance state directly (`$this->searchTypes`, `$this->statusTypes`, `self::$icServerID` itself)
  or explicitly constructs a full `mail_ui` instance internally (`ajax_changeProfile()` does
  `new mail_ui(false)`, `ajax_refreshVacationNotice()` does `new mail_ui()`) - there was no clean way
  to pull them out without just relocating the same full coupling. Tests:
  `mail/tests/ProfileHandlerQuotaDisplayTest.php` (new).
- [x] **Attachment/body-fetch ajax handlers** (partial) → `mail/src/Ui/AttachmentJmap.php` +
  `mail/src/Ui/BodyHandler.php`. The pleasant surprise here, same shape as Account/session/profile:
  a whole sub-cluster of this group was *already* written to bypass `mail_bo` entirely and talk
  directly to `Mail\Account::read()->imapServer()`/`JmapShim` with an explicit account id - the
  project's own "JMAP-dispatch helpers are backend-agnostic, no `$icServer` coupling" principle
  (see [[mail-jmap-modernization]]) applied to `mail_ui`, not just `Api\Mail`. Moved, all zero
  `mail_ui`-instance-dependency:
  - `Mail\Ui\AttachmentJmap`: `createAttachmentBlock`, `resolveWinmailJmap`, `resolveAttachmentsJmap`,
    `jmapAttachmentsToLegacy` (private), `fetchBlobBytes`, `fetchMessageBytesJmap`,
    `resolveSubjectJmap`, `replaceMessageJmap`, `fetchAttachmentJmap`, and the pure-parsing body of
    `ajax_parseAddressList` (as `parseAddressList`).
  - `Mail\Ui\BodyHandler`: `resolve_inline_images`, `resolve_inline_image_byType`.
  - None had external callers except `resolve_inline_image_byType` - called from tracker's
    `tracker_bo` (a separate repo) by that exact name, so `mail_ui` keeps a thin wrapper for it;
    everything else was removed from `mail_ui` outright and every call site (both the ~12 in
    `mail_ui` itself and 4 in `mail_compose.inc.php`) repointed directly.
  - **Near-miss caught before commit**: `getdisplayableBody()` (which stayed in `mail_ui`) had two
    *internal* calls to `resolve_inline_images()` that the external-caller sweep alone didn't
    surface - only found by grepping the group's removed method names against `mail_ui.inc.php`
    itself afterward. Lesson: after moving a group, re-audit the *whole file* for the removed names,
    not just the call sites already known about going in.
  - The classic-fallback methods in this group all kept working via the same `$jmapResult ??
    $classicResult` pattern already in place, now calling `AttachmentJmap::method(...)` instead of
    `self::method(...)`/`$this->method(...)`.
  - Two pre-existing tests used `ReflectionMethod` against private `mail_ui` methods
    (`mail/tests/JmapAttachmentsToLegacyTest.php`, testing `jmapAttachmentsToLegacy`) - repointed to
    `AttachmentJmap::class`. New: `mail/tests/AttachmentJmapParseAddressListTest.php`.
  - The remaining classic-fallback methods all genuinely need `$this->mail_bo`/`changeProfile()` and
    were not attempted in that pass - see the second batch below, done in a follow-up session.
- [x] **Attachment/body-fetch ajax handlers, batch 2** → `mail/src/Ui/AttachmentHandler.php`
  (`resolveAttachmentsBlock`, `vfsSaveMessages`, `vfsSaveAttachments`, `ajax_vfsOpen`→`vfsOpen`,
  `ajax_vfsSave`→`vfsSave`, `getdisplayableBody`, `ajax_saveModifiedMessageSubject`→
  `saveModifiedMessageSubject`, `ajax_fetchMessageDetails`→`fetchMessageDetails`) - the
  `mail_ui`-constructor-injection shape, same as ImportHandler/MessageActionHandler.
  `vfsSaveMessages` kept a `mail_ui` wrapper (menuaction-dispatched directly, and also called via
  `ExecMethod2()` from `filemanager_ui.inc.php`); the `ajax_*`-prefixed ones kept wrappers too
  (all menuaction-dispatched). `resolveAttachmentsBlock`/`vfsSaveAttachments`/`getdisplayableBody`
  had no external callers and were removed from `mail_ui` outright.
  - **Pre-existing bug discovered, not fixed**: `getdisplayableBody()`'s inline-image resolution
    passes `$this->mailbox`/`$this->uid`/`$this->partID` - grepping the whole file found these are
    never assigned anywhere on `mail_ui`, so they're always `null` and inline `cid:` image
    resolution on this code path has likely never worked. Kept exactly as-is (now
    `$this->ui->mailbox`/etc., still always null) - flagged in the new class's docblock rather than
    silently carried forward unremarked or silently "fixed" as a drive-by change.
  - `mail_ui::get_actions()` needed no further visibility changes this round (already widened to
    package-default in the Message-action batch); nothing in this batch called it.
  - Case-insensitive audit of all 8 moved/removed names across the whole repo found no other
    external callers - the batch stayed clean and this near-miss check turned up nothing new for
    once.
  - Remaining (done in the next batch, below): `getAttachment`, `displayImage`, `download_zip`,
    `get_load_email_data`, `tryJmapNativeSpecialCase`, `loadEmailBody` - the biggest and most
    security-sensitive (S/MIME passphrase handling in `tryJmapNativeSpecialCase`) methods in this
    group.
- [x] **Attachment/body-fetch ajax handlers, batch 3 (final)** → `mail/src/Ui/MessageDisplayHandler.php`
  (`displayImage`, `getAttachment`, `download_zip`, `get_load_email_data`, `tryJmapNativeSpecialCase`
  → private, `smimePassphraseFormHtml` → private, `get_email_header`, `showBody`, `loadEmailBody`) -
  the `mail_ui`-constructor-injection shape, same as ImportHandler/MessageActionHandler/
  AttachmentHandler. This closes out the "Attachment/body-fetch ajax handlers" group entirely.
  - `displayImage`/`getAttachment`/`download_zip`/`loadEmailBody` are all menuaction-dispatched by
    exact name (`$public_functions`), so `mail_ui` keeps thin wrappers for all four.
  - `get_load_email_data`/`tryJmapNativeSpecialCase` have no menuaction entry, but
    `get_load_email_data` does have one real in-repo caller outside `mail_ui` itself:
    `mail/profile.php` (a standalone profiling script) called it directly on a `mail_ui` instance.
    Per this doc's discipline (in-repo call sites get repointed, not wrapped "just in case"),
    `mail/profile.php` now constructs `MessageDisplayHandler` directly instead of a wrapper being
    kept on `mail_ui`. The other internal caller (inside what's now `ajax_spamAction()`'s per-item
    loop) was repointed to `$this->messageDisplayHandler()->get_load_email_data(...)`.
  - `get_email_header()`/`showBody()` moved too - once this group is extracted, grepping the whole
    repo found no callers left outside it, so leaving them on `mail_ui` would just be dead weight.
  - `mail_ui::attachmentHandler()` was widened from `private` to package-default visibility (same
    reasoning as `get_actions()` in the Message-action batch): `get_load_email_data()` needs it to
    reach `getdisplayableBody()` cross-class.
  - **Correction to a previous claim**: the batch-2 entry above said `getdisplayableBody()`'s
    `$this->mailbox`/`$this->uid`/`$this->partID` reads were "never assigned anywhere, always null" -
    that was wrong. Reading `get_load_email_data()` (this batch) shows it sets exactly those three
    properties (`$this->ui->mailbox = $mailbox` etc.) right before calling `getdisplayableBody()` -
    the earlier grep was scoped to the file *as it stood after batch 2's own extraction*, and missed
    that the real assignment lived in the method still waiting to be read in this batch. Both
    `AttachmentHandler`'s and this doc's batch-2 entry have been corrected. Lesson: "grepped this
    file and found nothing" isn't equivalent to "grepped this method's actual callers" - the
    difference matters most exactly when the caller hasn't been extracted (or even read) yet.
  - Case-insensitive audit of all 9 moved/removed names across `api/`, `mail/`, `calendar/`,
    `addressbook/`, `infolog/`, `tracker/`, `admin/`, `filemanager/` found no other external callers,
    and a same-file self-call grep after the move found no internal near-misses this round either.
  - Tests: existing 150-assertion `api/tests/Mail`+`mail/tests` suite still green; no new dedicated
    unit tests added (this group is IMAP/S/MIME/JMAP-connection-coupled end to end, same as batches 1
    and 2 - not unit-testable without a live/fake backend, consistent with those batches' approach).

- [x] **Folder ajax handlers** (2026-08-17, once [[mail-folder-tree-jmap]] fully landed) →
  `mail/src/Ui/FolderHandler.php` (`ajax_tree_autoloading`→`treeAutoloading`,
  `ajax_foldersubscription`→`folderSubscription`, `ajax_foldertree`→`folderTree`,
  `ajax_reloadNode`→`reloadNode`, `ajax_setFolderStatus`→`setFolderStatus`,
  `ajax_addFolder`→`addFolder`, `ajax_renameFolder`→`renameFolder`,
  `ajax_MoveFolder`→`moveFolder`, `ajax_deleteFolder`→`deleteFolder`,
  `ajax_folderMgmtTree_autoloading`→`folderMgmtTreeAutoloading`,
  `ajax_folderMgmt_delete`→`folderMgmtDelete`) - the `mail_ui`-constructor-injection shape, same as
  ImportHandler/MessageActionHandler/AttachmentHandler/MessageDisplayHandler. Every one of these 11
  methods is dispatched by exact name from client JS or a `.xet` template `autoloading=` attribute
  (none were removable), so `mail_ui` keeps a one-line `ajax_*` wrapper for every one - the largest
  "all-wrappers-kept" group so far. `folderHandler()` widened to package-default (like
  `attachmentHandler()`) so `MessageActionHandler` can reach `setFolderStatus()` cross-class - its 2
  call sites (previously `$this->ui->ajax_setFolderStatus(...)`) repointed to
  `$this->ui->folderHandler()->setFolderStatus(...)`. Two internal self-calls
  (`ajax_foldersubscription`→`ajax_reloadNode`, `ajax_folderMgmt_delete`→`ajax_deleteFolder`) moved
  with their callers and now call each other directly inside `FolderHandler` (`$this->reloadNode(...)`/
  `$this->deleteFolder(...)`). `findNode` (was in "Row-id helpers", see below) turned out to be
  genuinely dead code - its only reference anywhere in the repo was its own recursive self-call, no
  external caller at all (not even a template attribute) - deleted outright rather than moved, same
  discipline as `encodeFolderName` in Phase 1. No new unit tests (same posture as
  ImportHandler/MessageActionHandler - fully `mail_ui`+`mail_bo`+IMAP-coupled, not independently
  testable); existing 183-assertion `api/tests/Mail`+`mail/tests` suite stays green.
  - **Deliberately NOT done**: the rest of "Row-id helpers" (`createRowID`/`generateRowID`/
    `generateJmapRowID`) - ralf's choice: `generateRowID` alone has 15+ call sites across many
    files (`mail_compose`, `mail_hooks`, `ApiHandler`, `Storage/Merge.php`, `MessageActionHandler`,
    `MessageDisplayHandler`) for a purely organizational move that adds no testability (both are
    already `static`/pure and trivially testable in place). Left on `mail_ui` exactly as-is.

## Next up

`mail_ui`'s ajax-handler regrouping (Phase 2) is now **fully complete**: S/MIME, Import, Message
action, Account/session/profile, Attachment/body-fetch, and Folder ajax handlers are all done (most
partially - see their notes above for what deliberately stayed on `mail_ui` and why; Row-id helpers
deliberately left untouched). Remaining: the higher-risk `Api\Mail` groups -

- **Header/search, body/attachment IMAP fetch** - need the `$icServer` coupling question answered
  first, see "Recommended approach" above.
- **`Api\Mail`'s "Folder management" group** (`createFolder`/`renameFolder`/`deleteFolder`/
  `getFolderObjects`/`getFolderArrays`/`getFolderStatus`/`getMailBoxCounters`/`_getNameSpaces`/
  `getSpecialUseFolders`/`getQuotaRoot`) - no longer blocked ([[mail-folder-tree-jmap]] fully
  landed 2026-08-17), but a harder follow-up than the `mail_ui` group above: it would be the first
  time this project's constructor-injection pattern applies to `Api\Mail` itself rather than
  `mail_ui`, and two of its static properties (`self::$specialUseFolders`, `self::$profileDefunct`)
  are also read/written by two methods staying behind (`_getSpecialUseFolder()`, `folderExists()`)
  - resolvable by leaving those property *declarations* on `Api\Mail` and having the new class
    reference them as `Mail::$specialUseFolders`/`Mail::$profileDefunct` (PHP allows any class to
    touch another class's public static property by name), not by moving the declarations. Not
    started.

## Scale

| File | Lines | Methods |
|---|---|---|
| `api/src/Mail.php` | 8337 | ~142 |
| `mail/inc/class.mail_ui.inc.php` | 3370 | ~74 |
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
| **Folder management (existence/CRUD/listing)** | `createFolder`, `renameFolder`, `deleteFolder`, `getFolderObjects`, `getFolderArrays`, `getFolderStatus`, `getMailBoxCounters`, `_getNameSpaces`, `getSpecialUseFolders`, `getQuotaRoot` | High - real IMAP calls via `$icServer`, heavy internal caching keyed by `ImapServerId` | **No longer blocked** ([[mail-folder-tree-jmap]] fully landed 2026-08-17) but still High/not started - none of these 10 have external callers outside `mail/` (no tracker dependency), but 2 static properties (`self::$specialUseFolders`, `self::$profileDefunct`) are also touched by `_getSpecialUseFolder()`/`folderExists()`, which stay on `Api\Mail` - see "Next up". |
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
| **Folder ajax handlers** | `ajax_tree_autoloading`, `ajax_foldersubscription`, `ajax_foldertree`, `ajax_reloadNode`, `ajax_setFolderStatus`, `ajax_addFolder`, `ajax_renameFolder`, `ajax_MoveFolder`, `ajax_deleteFolder`, `ajax_folderMgmtTree_autoloading`, `ajax_folderMgmt_delete` | **Done** (2026-08-17) → `mail/src/Ui/FolderHandler.php`, taking `mail_ui` (not `Api\Mail` directly) as a constructor dependency, same shape as ImportHandler/MessageActionHandler; every method kept a one-line `mail_ui::ajax_*` delegation (all 11 are dispatched by exact name from client JS or a template `autoloading=` attribute - none removable). `ajax_compressFolder` (was here) was removed outright earlier, not migrated - see [[mail-folder-tree-jmap]] ("Resolved" note - unwanted legacy IMAP-only workflow). `ajax_emptySpam`/`ajax_emptyTrash` (were here) turned out not to be folder-tree code at all - moved to `mail/src/Ui/MessageActionHandler.php` earlier (see "Message action ajax handlers" below). |
| **Message action ajax handlers** | `ajax_flagMessages`, `ajax_deleteMessages`, `ajax_copyMessages`, `ajax_sendMDN`, `ajax_saveModifiedMessageSubject`, `saveMessage` | **Done**, except `ajax_saveModifiedMessageSubject` which actually belongs with "Attachment/body-fetch ajax handlers" (depends on that group's `fetchMessageBytesJmap`/`replaceMessageJmap`) - all 5 others → `mail/src/Ui/MessageActionHandler.php`. `ajax_flagMessages`/`ajax_deleteMessages`/`ajax_copyMessages` (the ones originally left behind, see below) turned out extractable once `AttachmentJmap`'s methods became public and `mail_ui::get_actions()`'s visibility was widened from `private` to package-default. |
| **Attachment/body-fetch ajax handlers** | `getAttachment`, `ajax_resolveWinmail`, `resolveWinmailJmap`, `resolveAttachmentsBlock`, `resolveAttachmentsJmap`, `jmapAttachmentsToLegacy`, `fetchBlobBytes`, `fetchMessageBytesJmap`, `fetchAttachmentJmap`, `ajax_fetchAttachments`, `createAttachmentBlock`, `download_zip`, `ajax_vfsOpen`, `ajax_vfsSave`, `vfsSaveMessages`, `vfsSaveAttachments`, `displayImage`, `get_load_email_data`, `tryJmapNativeSpecialCase`, `loadEmailBody`, `getdisplayableBody`, `resolve_inline_images`, `resolve_inline_image_byType`, `ajax_fetchMessageDetails`, `ajax_parseAddressList`, `ajax_saveModifiedMessageSubject` (moved here from "Message action ajax handlers" - depends on this group's `fetchMessageBytesJmap`/`replaceMessageJmap`) | **Done** - see Phase 2 progress notes below. Three passes: the zero-dependency JMAP fast-path sub-cluster (10 methods, `Mail\Ui\AttachmentJmap`/`Mail\Ui\BodyHandler`), a second batch of classic-fallback methods (`resolveAttachmentsBlock`, `vfsSaveMessages`/`vfsSaveAttachments`/`ajax_vfsOpen`/`ajax_vfsSave`, `getdisplayableBody`, `ajax_saveModifiedMessageSubject`, `ajax_fetchMessageDetails` → `Mail\Ui\AttachmentHandler`), and the final batch (`getAttachment`, `displayImage`, `download_zip`, `get_load_email_data`, `tryJmapNativeSpecialCase`, `loadEmailBody`, `get_email_header`, `showBody` → `Mail\Ui\MessageDisplayHandler`). |
| **S/MIME ajax handlers** | `ajax_smimeAttachmentsChecker`, `ajax_smimeAddCertToContact`, `smimeAccountId`, `smimeExportCert`, `smimeExportCsr`, `smimePassphraseFormHtml` | **Done** → `mail/src/Ui/SmimeHandler.php`, except `smimePassphraseFormHtml` (stayed, coupled to `mail_ui` instance state - see Phase 2 progress notes). |
| **Import handlers** | `importMessage`, `importMessageToFolder`, `importMessageFromVFS2DraftAndEdit`, `importMessageFromVFS2DraftAndDisplay` | **Done** (partial) → `mail/src/Ui/ImportHandler.php`, constructor-injected with `mail_ui` (not zero-coupling like S/MIME - see Phase 2 progress notes). `importMessage` stayed (dual template+ajax entry point); `importMessageFromVFS2DraftAndEdit` was dead code, deleted. |
| **Account/session/profile ajax handlers** | `changeProfile`, `ajax_jmapBootstrap`, `jmapLocalBootstrap`, `ajax_enablePush`, `ajax_changeProfile`, `ajax_refreshVacationNotice`, `gatherVacation`, `ajax_refreshFilters`, `ajax_refreshQuotaDisplay`, `quotaDisplay` | **Partially done** → `mail/src/Ui/ProfileHandler.php` got `ajax_jmapBootstrap`→`jmapBootstrap`, `jmapLocalBootstrap`→`localBootstrap`, `ajax_enablePush`→`enablePush`, `quotaDisplay` - the four that were already written to take an explicit profile id rather than depend on `mail_ui`'s connected `mail_bo`, so this class needs no `mail_ui` instance at all (zero-dependency like Phase 1, not constructor-injected like ImportHandler/MessageActionHandler - see Phase 2 progress notes). The other six stayed - each either mutates `mail_ui`'s own instance state directly or explicitly constructs a full `mail_ui` instance internally. |
| **Row-id helpers** | `createRowID`, `generateRowID`, `generateJmapRowID` (`findNode` was also here - **deleted**, see below) | **Decided against moving** (2026-08-17) - `generateRowID` alone has 15+ call sites across many files for a purely organizational move that adds no testability (already `static`/pure, trivially testable in place). Stays on `mail_ui` exactly as-is. `findNode` turned out to be genuinely dead code (zero callers anywhere, not even from outside its own recursion) and was deleted outright rather than moved, while working through this group. |

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
3. **Treat header/search and body/attachment IMAP-fetch as a later, separate project** - genuinely
   needs `$icServer` (or a narrower "IMAP connection" interface) as an injectable dependency to be
   testable at all, which is a bigger design question (and overlaps directly with the
   already-scoped-but-not-started `Api\Mail\Imap\Jmap` composition-over-inheritance redesign in
   [[mail-jmap-modernization]] - coordinate rather than duplicate).
   **Folder management specifically is blocked on something more concrete than "later"**: ralf's
   planned folder-tree JMAP migration ([[mail-folder-tree-jmap]]) will replace most of `Api\Mail`'s
   folder-management group and all of `mail_ui`'s folder ajax-handlers group outright - decoupling
   that code now would be wasted work. Do not start on the "Folder management" `Api\Mail` group or
   the "Folder ajax handlers"/"Row-id helpers" `mail_ui` groups until that migration lands.
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
