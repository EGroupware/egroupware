# Mail: test-coverage audit and gap-closing plan

## Status: audit complete (2026-09-09); priority 1 (bulk move/copy/delete) client-side + deferred-work queue done; priority 2 (JMAP/shim path) - jmap.ts's mailbox CRUD, label/flag setters, thread-keyword aggregation, filter/sort, saveDraft, WS push-payload, and attachment upload/resolve done, plus the shim's filterToQuery()/buildSort() IMAP-search translation and the real-JMAP-facing Mailbox.php/Email.php layer (with a real bug found+fixed in Mailbox.php, see Progress log)

Full-codebase scan of `mail/js/*.ts`, `mail/src/*.php`, `mail/inc/*.php`, `api/src/Mail.php` and
`api/src/Mail/*.php` (including the `Jmap/` shim + real-JMAP layer), cross-referenced against every
existing test in `mail/tests/`, `api/tests/Mail/`, and `mail/js/test/`. Triggered by ralf asking to
scan the whole mail codebase for coverage gaps, after a string of real regressions this session
(answered/forwarded status icon, `.eml` export byte-fidelity, bodyless-message auto-index) each
found in an area with little or no existing test coverage.

### Priority order for closing gaps (ralf, 2026-09-09)

1. **Bulk move/copy/delete** - explicitly first, see below.
2. **The JMAP (and shim) path and whatever it calls take precedence over the classic
   `Api\Mail` class.** `Api\Mail` (`api/src/Mail.php`) is the pre-JMAP IMAP business object -
   still load-bearing for non-JMAP-native accounts (Dovecot without the shim, real external IMAP),
   but not where new test-writing effort should go first. When a gap exists on both sides of the
   same feature (e.g. bulk delete has both a classic `Api\Mail`/`mail_ui.inc.php` path and a
   JMAP/shim path), close the JMAP/shim side first.
3. **The send/SMTP side** - `Api\Mail\Jmap\Transport.php` (JMAP-over-SMTP transport, used by
   notifications/mail-merge/cron - already flagged as highest standalone priority below, given its
   own documented prior production bug), `mail/src/Send.php`, `mail/src/ApiHandler.php`'s REST send
   path, `Api\Mail\Smtp.php`. Roughly bundled with/adjacent to priority 2, not a separate later
   phase.
4. **`mail/js/app.ts` and its handler-class "subclasses"** (`mail/src/Ui/*Handler.php` - the
   `Ui.php` delegates: `MessageActionHandler`, `MessageDisplayHandler`, `AttachmentHandler`,
   `BodyHandler`, `FolderHandler`, `ImportHandler`, `SmimeHandler`, `ProfileHandler`, `Tree`). Given
   `app.ts`'s near-zero coverage and its size (~9000 lines/175 methods), this is necessarily an
   ongoing, incremental effort rather than one pass.

## What's already solid (don't re-audit unless something regresses)

- `mail/js/attachmentIndex.ts` (bodyless-message auto-index) - thorough, including the
  `MailApp.retryAttachmentIndexForRow()` wiring (2026-09-09).
- `MailJmap.fetchRawSourceBytesBase64ByBlobId()`/`fetchRawSourceBytesBase64()` and the shim's
  `appendRawMessage()`/`fetchRawMessage()`/`uploadBytes()`/`readUploadedBlob()` - byte-fidelity
  fixed+tested (2026-09-09), see the `.eml` export bug fix.
- `MailCompose.flagSourceMessagesAfterSend()`/`bootstrapReply()`/`mergeForwardAttachments()`'s
  `sourceMessagesToFlag` bookkeeping, and the shim's `writableKeywords()` `$answered`/`$forwarded`
  mapping (2026-09-09 answered/forwarded status-icon fix).
- `Api\Mail\Jmap\Imap.php`'s core dispatch (`Mailbox/query`, `Mailbox/get`, `Mailbox/set`,
  `Thread/get`, `Email/set` keyword patches) - `mail/tests/JmapTest.php`,
  `JmapShimMailboxGetTest.php`, `JmapShimMailboxSetTest.php`, `JmapShimThreadTest.php`.
- `Api\Mail\Smime`/S/MIME resolve+decrypt (`api/tests/Mail/Smime*Test.php`), attachment naming
  (`AttachmentNameTest.php`), address parsing (`ParseAddressListTest.php`,
  `AddressListTest.php`), `Credentials.php` (AES/3DES round-trip + cache staleness),
  `CustomLabels.php`, `BodyDecoding.php`, `FolderHelpers.php`.
- `mail/js/attachmentDownload.ts`, `mail/js/jmap-jam-websocket.ts`, `mail/js/folderTree.ts`'s
  `buildFolderLevel()`/`buildErrorNode()`.
- PGP signature verification core (`PgpSignatureVerification.test.ts`,
  `PgpSignatureArmorExtraction.test.ts`) - the extracted byte-level helpers specifically, not the
  higher-level `MailJmap` dispatch/cache methods around them (see gaps below).

## Gaps, by priority area

### 1. Bulk move/copy/delete (client-side + deferred-work queue done 2026-09-09)

Prior real bugs in exactly this area: optimistic delete/move removing rows before the JMAP result
was actually awaited (fixed b9bffc171d), and "delete all matching" racing the shim's own
post-response deferred IMAP move (mitigated with an on-shutdown log + push-on-fatal +
optimistic-clear, no hard guard yet).

- **Done**: `mail/js/jmap.ts`'s `moveMessages()`, `copyMessages()`, `deleteMessages()`,
  `moveAllMatching()`, `copyAllMatching()`, `deleteAllMatching()`, `purgeFolder()`,
  `queryAllIds()`, `destroyIds()` - 26 tests, `mail/js/test/MailJmapBulkMoveCopyDelete.test.ts`.
  Covers: mailboxIds full-replace (move) vs PatchObject add-only (copy) patch shapes, grouping by
  source mailbox into separate `Email/set` calls, the "already in Trash -> destroy directly, don't
  move-into-itself" regression check (the exact 2026-08-27 bug), the shim-only `mailboxId` arg
  (`isLocal` token) vs real-JMAP (omitted), `notDestroyed`/`notUpdated` error surfacing,
  `queryAllIds()`'s pagination (multi-page accumulation + stops on an empty page even if the
  reported total isn't reached yet - would otherwise be a plausible infinite-loop/early-truncation
  risk for "select all matching filter" bulk actions), and >page-size (500) selections spanning
  multiple `destroy` calls with no id dropped/duplicated. Also documented (not fixed) a found
  quirk: `moveMessages([])`/`copyMessages([])` throw the same "cross-account move/copy not
  supported" error an actual cross-account mismatch would, for a plain empty selection too -
  flagged to ralf, not silently changed.
- **Done**: `api/src/Mail/Jmap/Imap.php`'s deferred-work queue
  (`queueDeferredWork()`/`runDeferredWork()`) and `chunkIds()` - the machinery emailSet()'s
  move/destroy IMAP execution queues onto, run only after the JMAP response is already sent. 7
  tests, `mail/tests/JmapShimDeferredWorkTest.php`. Covers: chunk-size boundaries (including the
  exact-50 edge), queued closures running in order then the queue clearing (no stale re-run), and
  - the one with a documented prior real bug behind it (2026-09-07, "a bare message alone wasn't
  enough to even confirm a deferred move had failed at all") - one closure throwing does NOT abort
  a later queued closure.
- **Still open**: `emailSet()`'s own move/copy/destroy IMAP-execution branches (the `$imap->copy()`/
  `$imap->store()`/`$imap->expunge()` calls themselves, lines ~1962-2033) call
  `self::imapServer($accountId)` internally and have no injection seam - same documented limitation
  `JmapShimMailboxGetTest.php`/`JmapShimThreadTest.php` already note for their own methods. Needs
  either a live DB-backed account + mocked `Horde_Imap_Client_Socket`, or extracting an `$imap`-
  parameterized helper the way `appendRawMessage()`/`fetchRawMessage()` already are (see that
  extraction's own precedent, `mail/tests/JmapShimRawMessageByteFidelityTest.php`) - not done yet.
  `emailSet()`'s own args-*parsing* (which patch shape means move vs copy vs an invalid keyword,
  digit-id validation for destroy) IS reachable via the accountId="0" demo fixture (it runs before
  the accountId==="0" early return) and is NOT yet covered either - a cheaper next slice than the
  full IMAP-execution mock.
- `mail/js/app.ts`: `callFlagMessages()`, `tryJmapDelete()`, `deleteMessages()` (plural),
  `deleteMessagesShowResult()`, `undeleteMessages()`, `tryJmapPurgeFolder()`, `move2Folder()`,
  `copy2Folder()` - still untested (lower priority per the priority order above - JMAP/shim layer
  first, done above).
- Classic `Api\Mail::deleteMessages()`/`moveMessages()` (`api/src/Mail.php`) - still untested,
  lowest priority per the priority order above.

### 2. JMAP (and shim) path

- **Done (2026-09-09)**: `mail/js/jmap.ts`'s folder/mailbox CRUD - `createMailbox`,
  `renameMailbox`, `moveMailbox`, `deleteMailbox`, `setMailboxSubscribed`, `getAllMailboxes`,
  `resolveMailboxId` - 22 tests, `mail/js/test/MailJmapMailboxCrud.test.ts`. Covers: create's
  top-level-vs-nested parentId resolution, rename/move/delete's success-vs-failure cache
  invalidation (a failed op must NOT invalidate the mailboxId cache), move's own top-level
  destination case, subscribe/unsubscribe, `resolveMailboxId`'s "empty path never queries JMAP at
  all" contract, and `getAllMailboxes`'s three-way error contract (JmapUserError rethrown,
  everything else swallowed to `null` for classic-fallback callers) - the get/query chained-via-
  `$ref()` result-reference call shape needed its own dedicated fake (see that test file's own
  comment) since a plain single-call fake silently passes with the real chaining broken.
- **Done (2026-09-09)**: `mail/js/jmap.ts`'s label/custom-flag surface - `setLabel`, `setMdnFlag`,
  `clearLabels`, `setCustomFlag`, `clearLabelsForAll`, `toggleForAll` - 14 tests,
  `mail/js/test/MailJmapLabelsAndFlags.test.ts`. Covers: built-in vs. case-insensitive custom-label
  keyword resolution, custom-flag mutual exclusivity (setting one clears every other + `$flagged`;
  unsetting only clears itself + `$flagged`, doesn't touch siblings), `clearLabels`'s full
  built-in+custom keyword set, and `toggleForAll`'s per-message toggle semantics (concurrently
  queries both "has it"/"doesn't have it", adds to the latter, removes from the former - not a bulk
  "set for everyone"). Found (during writing, not a bug - just a test-authoring trap worth noting
  for next time) that `keywordPatch()`'s "unset" value is the JMAP PatchObject sentinel `null`, not
  `false` - several first-draft assertions had to be corrected. Also confirmed `ensureToken()`'s own
  unreachable-account path can fall through to `popupCheckCert()`, which needs `egw.link()`/
  `egw.open_link()` stubbed even in an otherwise-minimal fake `egw` - same requirement
  `MailJmapMailboxCrud.test.ts`'s `getAllMailboxes()` tests already found.
- **Done (2026-09-09)**: `mail/js/jmap.ts`'s status-icon/label computation and thread-keyword
  folding - `keywordsToRowFlags()` (the exact logic central to the answered/forwarded fix,
  previously with no direct unit test at all) and `aggregateThreadKeywords()` - 19 tests,
  `mail/js/test/MailJmapThreadKeywordAggregation.test.ts`. Covers: status-icon priority
  (forwarded > answered > unseen > none), `$seen` clearing the unseen class/icon, custom-flag/
  built-in-label/custom-label keyword resolution (including case-insensitive custom-label
  matching), the labelTags "2 or more" display threshold, `$seen`'s AND-fold vs. every other
  keyword's OR-fold across thread members (including a member with no `keywords` map at all, and
  an empty-members edge case), and one end-to-end case feeding the aggregate straight into
  `keywordsToRowFlags()` (a thread where only one of several members was forwarded still shows
  the forward icon on the collapsed row).
- **Done (2026-09-09)**: `mail/js/jmap.ts`'s search/filter/sort-to-JMAP translation -
  `buildFilter`, `buildTokenizedFilter`, `flaggedFilter`, `buildSort` - 30 tests,
  `mail/js/test/MailJmapFilterAndSort.test.ts`. Covers: every sort-column mapping + ASC/DESC
  default, the flagged-filter OR-of-6-keywords shape, every status-filter keyword mapping
  (unseen/answered/seen/label1-5/custom-label case-insensitive resolution), date-range's +1-day
  "before is exclusive, our enddate is inclusive" adjustment, the app-header flagFilter being
  ANDed as an independent condition from the status filter (both can be active simultaneously),
  larger/smaller size parsing, per-cat_id text-search field dispatch (quick/quickwithcc/single-
  field/body/text), and the tokenizer's quoted-phrase/AND/OR/+/- syntax. The single-vs-AND-wrapped
  result-collapsing contract (exactly 1 condition returned bare, 2+ wrapped) is exercised
  throughout rather than as its own separate test.
- **Done (2026-09-09)**: `mail/js/jmap.ts`'s `saveDraft()` (and, along the way, the
  `resolveComposeContext()` private helper it shares with `sendNewEmail()`) - 9 tests,
  `mail/js/test/MailJmapSaveDraft.test.ts`. Covers: the reimport-and-replace semantics (create the
  new draft first, only then best-effort-destroy the previous copy - a cleanup failure, thrown or
  merely a non-throwing `notDestroyed` response, must never fail the save itself, which already
  succeeded), identity resolution by the profileID's own `ident_id` suffix (not just "the first
  identity"), the shim-only destroy `mailboxId` extension, and `resolveComposeContext()`'s own
  "no matching identity"/"no Drafts folder" failure branches. Found (characterization, not fixed):
  unlike `destroyIds()`'s bulk-delete counterpart, the old-draft cleanup call never actually
  inspects the response for `notDestroyed` - only a thrown/rejected request is caught, so a
  server-reported (non-throwing) failure to destroy is silently treated as success.
- **Done (2026-09-09)**: `mail/js/jmap.ts`'s WebSocket push-payload building -
  `buildWsPushPayload`/`buildEmailPush`/`buildMailboxPush`/`buildEmailDeletePush` - 14 tests,
  `mail/js/test/MailJmapWsPushPayload.test.ts`. Covers: add/update envelope shapes for both
  emails and mailboxes, the destroyed-email wildcard-folder delete envelope, the
  destroyed-mailbox "only if its path was already cached" gate, a resolved-to-null folder (a
  destroyed-mailbox race) being filtered out of the final payload rather than producing a broken
  entry, Mailbox/changes never being called when only Email changed (and vice versa), and the
  "must use EGroupware's own account_id, never the JMAP accountId param" id-shape distinction
  called out in the method's own docblock as something a naive edit could silently break.
  `folderId2path()` itself (a separate chained-`$ref()` lookup) was stubbed directly rather than
  faked at the requestMany() level, keeping these tests focused on the envelope-building logic.
- **Done (2026-09-09)**: `mail/js/jmap.ts`'s attachment upload/resolve methods -
  `uploadAttachment`, `downloadBlobUrl`, `reuploadAttachmentForAccount`, `isLocalAccount`,
  `uploadVfsAttachment`, `fetchAttachmentsMetadata`, `getAttachmentViewUrl`/
  `revokeAttachmentViewUrls`, `resolveOutgoingInlineImages` - 26 tests,
  `mail/js/test/MailJmapAttachmentUploadResolve.test.ts`. Covers: `uploadAttachment()`'s
  content-type re-slicing (only when the blob's own type doesn't already match) and its
  `JmapUserError`-wrapping of a raw upload failure; `downloadBlobUrl()`/`getAttachmentViewUrl()`'s
  `withKnownType()` enforcement of the requested mime type onto the created object URL (the
  "image/svg+xml" -> "image/svg xml" query-string decode bug this exists to work around);
  `reuploadAttachmentForAccount()`'s download-from-source/upload-to-target flow returning the
  TARGET account's new blobId, not the source one; `isLocalAccount()`'s token passthrough
  including the no-token case; `getAttachmentViewUrl()`'s per-rowId object-URL tracking and
  `revokeAttachmentViewUrls()`'s cleanup; and `resolveOutgoingInlineImages()`'s no-blob-urls
  fast path, cached-upload reuse (never re-uploads the same blob: URL twice, including within one
  call), a missing Blob being left untouched rather than dropped, and one image's upload failure
  not blocking or losing any other image in the same body. `fetchAttachmentsMetadata()`'s
  server-side JMAP-fallback branch (real, non-shim accounts) and `uploadVfsAttachment()`'s
  WebDAV fetch-failure path are covered via the shared `requestMany()`/`fetch()` fakes; the shim's
  own `emailGet()`-based `attachments` fetch path (`token.isLocal`) is not separately re-tested
  here since it already goes through the same `emailGetViaCacheableGet()` machinery covered
  elsewhere.
- `mail/js/jmap.ts` (rest still untested - S/MIME/PGP methods deliberately skipped, a concurrent
  session is actively working in that area, see priority-3's own note): S/MIME encrypt
  (`smimeEncryptBody`, `resolveSmimeSignedAttachments`), PGP dispatch/cache methods
  (`findPgpPart`, `peekPgpSignature`, `peekPgpEncrypted`, `pgpEncryptBody` - as opposed to the
  already-tested lower-level byte helpers).
- **Done (2026-09-09)**: `api/src/Mail/Jmap/Imap.php`'s Email/query filter/sort translation -
  `findInMailbox`/`filterToQuery`/`applyCondition`/`buildSort`/`keywordToFlag` - 28 tests,
  `api/tests/Mail/Jmap/ImapFilterQueryTest.php`. These are pure static functions with no IMAP/DB
  dependency (only `emailQuery()` itself, which calls `imapServer()->search()`, needs a live
  connection - not attempted), so tested directly, asserting on
  `Horde_Imap_Client_Search_Query`'s own `(string)` IMAP search command rendering: every leaf
  condition (subject/from/to/cc/body/text/minSize/maxSize/after/before/hasKeyword/notKeyword),
  `inMailbox` being excluded from the search criteria itself (resolved separately via
  `findInMailbox()`), AND/OR combination, NOT of a single leaf, and NOT of an AND compound
  distributing via De Morgan's laws into an OR of negated leaves (Horde has no query-level
  negation). `buildSort()`'s ascending/descending/unrecognised-property/no-criteria branches, and
  `keywordToFlag()`'s standard-keyword-vs-passthrough mapping, also covered.
- `api/src/Mail/Jmap/Imap.php`: `emailQuery()`/`emailGet()`'s own real-account (non-"0") IMAP
  search/fetch EXECUTION (as opposed to the query-translation logic above, now covered) - still
  needs a live or properly mocked `Horde_Imap_Client_Socket` connection, not attempted.
  `resolveSmime()`/`resolveTnef()`'s IMAP-fetch half, `emailSubmissionSet()` (the shim's own send
  path - sends via SMTP and appends/deletes real IMAP messages, same "needs a live/mocked IMAP
  connection" blocker as `emailSet()`'s own IMAP-execution branches in priority 1; also has a
  multipart/signed body-preservation branch that's PGP/S-MIME-adjacent, left alone while a
  concurrent session is active there).
- **Done (2026-09-09)**: `Api\Mail\Jmap\Mailbox.php`'s folder-path <-> Mailbox-id resolution -
  `getMailboxId()`/`folderId2path()` - 10 tests, `api/tests/Mail/Jmap/MailboxFolderResolutionTest.php`.
  Both only ever call `$this->jmap->jmapCall()`, so a minimal fake session (a `Base` anonymous
  subclass implementing `jmapCall()`) was enough to test the request-shape/response-parsing logic
  without a real HTTP/IMAP connection. **Found and fixed a real bug** while writing this:
  `getMailboxId()`'s per-segment `#parentId` back-reference was `resultOf => (string)$key` - the
  CURRENT segment's own about-to-be-assigned call id, not the PRECEDING segment's id - a
  self-reference RFC 8620 §3.7 forbids (`resultOf` must name an *earlier* method call). This meant
  any multi-segment folder path lookup against a real JMAP server (Stalwart, acc_id=1) would never
  actually resolve its `#parentId` reference correctly - affecting `Email.php`'s create/move/import
  paths, `Mail.php`'s send path, and `ImportHandler.php`, all of which call `getMailboxId()` for
  real-JMAP accounts. Fixed to `resultOf => (string)($key - 1)`; a 3-segment regression test (not
  just 2) added specifically because a naive "always reference call 0" fix would also have passed
  a 2-segment-only test. Also documents (not fixed, pre-existing and cosmetic) that
  `folderId2path()`'s "normalize to uppercase INBOX" rule only fires for the first name appended
  overall (the leaf being queried), not for an INBOX ancestor reached while walking up a deeper
  path - and that its function-static per-folderId cache is shared process-wide, across every
  `Mailbox` instance, not per-instance.
- **Done (2026-09-09)**: `Api\Mail\Jmap\Email.php`'s convenience wrappers - `emailGet`/
  `emailQuery`/`emailImport`/`emailDestroy`/`emailSetKeywords`/`emailMove`/`getStates`/
  `getChanges` - 30 tests, `api/tests/Mail/Jmap/EmailTest.php`. Same fake-session approach as
  `MailboxFolderResolutionTest.php`. Covers: `emailQuery()`'s AND-filter construction (inMailbox
  first, then given conditions) and that a not-found folder never issues the actual `Email/query`
  call; `emailImport()`'s keywords defaulting to an empty `stdClass` (not `[]` - JMAP distinguishes
  `{}` from `[]`); `emailMove()`'s full mailboxIds REPLACE (vs `emailImport()`'s own add-only
  patch) and that an empty id list is a complete no-op (never even resolves the target folder);
  `emailSetKeywords()`'s same-patch-to-every-id application; `getStates()`'s chained
  Mailbox/query+Email/get `#inMailbox` back-reference; and `getChanges()`'s
  Mailbox-state-only/Email-state-only/both/neither call-set selection, response-keying by each
  call's own id, and sessionState propagation. Also **documents (not fixed) dead code** found
  while writing this: `getChanges()`'s `$mailbox` parameter, for anything other than the literal
  "inbox", triggers an extra `getMailboxId()` JMAP round-trip whose result is then never used
  anywhere else in the method (the change-tracking queries are always global, never
  folder-scoped) - harmless in practice since every real caller only ever passes the default
  `"INBOX"`, but a wasted round-trip if that ever changed.
- The real-JMAP/Stalwart-facing layer otherwise still has **essentially zero** tests:
  `EmailSubmission.php` (`set()`'s RFC 8621 §7.4 `onSuccessUpdateEmail`/`onSuccessDestroyEmail`
  handling), `Identity.php` (`synthesize()`'s signature-merge/HTML-to-text fallback logic - needs
  `Mail\Account::identities()`/`Accounts::id2name()`, both DB-backed, and the latter is unreliable
  in PHPUnit CLI per this doc's own project memory, so lower priority until that's worked around).

### 3. Send/SMTP side

- **`Api\Mail\Jmap\Transport.php`** - highest standalone priority here: only an `assertInstanceOf`
  smoke test exists (`AccountSmtpTransportTest.php`). `sendJmap()`'s full pipeline (MIME re-parsing,
  To/Cc/Bcc-vs-recipients reconciliation including the Bcc-inference-by-diff branch, attachment
  blob upload) and `resolveMailboxesAndIdentities()` (Drafts/Sent-by-role lookup) are untested -
  the latter has its own comment documenting a real prior bug ("Identity not found", found live
  2026-09-03).
- `mail/src/Send.php`: only trait-composition shape and two pure helpers
  (`resolveEmailAddressList()`, `convertHtmlToText()`) are tested (`SendRefactorTest.php`) - the
  actual `send()` MIME-building/mailbox-routing flow is untested.
- `mail/src/ApiHandler.php`: only the identity-PATCH REST endpoint is tested
  (`REST/MailAccountPatchTest.php`). `post()` (REST send, routes to `sendViaJmap()` or classic
  `Send`), `viewEml()` (raw-.eml REST endpoint - same byte-fidelity risk class as the bug just
  fixed elsewhere), `getVacation()`/`updateVacation()`, `check_access()` untested.
- `mail/js/compose.ts`'s send-side S/MIME/PGP wiring: `trySendViaJmap()`'s smimeType derivation
  from the toolbar widgets, and the Mailvelope/PGP `pgpArmored` passthrough - untested. A wrong-mode
  bug here would silently send mail unsigned/unencrypted.
- `Api\Mail\Smtp.php::mailbox_address()` (5-way switch on `mail_login_type`) - untested, small and
  deterministic, cheap win whenever picked up.
- Classic `Api\Mail::appendMessage()` (message-import/append, the pre-JMAP counterpart of the now
  byte-fidelity-tested shim `appendRawMessage()`) - untested, lower priority per the priority order.

### 4. `mail/js/app.ts` and its `mail/src/Ui/*Handler.php` delegates

`MailApp` (`app.ts`) has near-zero real coverage: only `composeMessage()` (partially),
`mobileView()`/`openMessage()`/`flag()`/`deleteMessage()` (singular, mobile-view path), and
`retryAttachmentIndexForRow()` are exercised by any test, all via the
`Object.create(MailApp.prototype)` + minimal-state-injection pattern (proven 3x now - see those
test files for the template to reuse). Standout untested areas, given the file's size a full
method-by-method list isn't practical:

- Message display orchestration: `preview()`, `renderMessageInto()` (only the small
  `retryAttachmentIndexForRow()` slice extracted from it is covered), `markOpenedMessageRead()`,
  `resolveAttachmentViewUrls()`, `resolveExternalImages()`.
- Bulk flag/delete: see priority 1 above.
- Compose popup wiring: `openComposePopupUrl()`, `openComposePopupUrlPost()`,
  `bootstrapComposePopup()` (recently modified by a concurrent session for PGP support - untested
  either way).
- Folder tree UI (~20 methods): `addFolder`/`jmapAddFolder`, `renameFolder`/`jmapRenameFolder`,
  `moveFolder`/`jmapMoveFolder`, `deleteFolder`/`jmapDeleteFolder`, `changeFolder`,
  `subscribeFolder`/`unsubscribeFolder`, `folderTreeSubselect`, `folderTreeAutoload`,
  `refreshFolderLevel`, `folderManagement*`.
- Search/filter UI: `searchFolder`, `searchtypeChange`, `refreshFilter2Options`,
  `refreshFilterOptions`, `refreshFlagFilterOptions`, `checkNmFilterChanged`, `changeNmFilter`.
- Attachments UI: `attachmentsBlockActions()`, `setupViewAttachmentActions()`.
- Drag-and-drop: `registerForDrag()`, `dragAttachment()`.

Delegate classes (`mail/src/Ui/*Handler.php`), all untested beyond `ProfileHandler::quotaDisplay()`
and `AttachmentJmap`'s address/attachment-block helpers (already well covered):
`MessageActionHandler.php` (real mailbox-mutating logic, 220+-line methods - overlaps priority 1),
`MessageDisplayHandler.php` (S/MIME-passphrase handling, JMAP-vs-classic dispatch), `BodyHandler.php`,
`FolderHandler.php`, `ImportHandler.php`, `SmimeHandler.php`, `Tree.php`, `AttachmentHandler.php`.

## Lower priority (classic `Api\Mail`-side, per priority order above)

Kept for completeness, but explicitly deprioritized until the above is in better shape:

- `api/src/Mail.php`: folder management (~40 methods), MIME/attachment parsing (`getStructure`,
  `getMessageBody`, TNEF decode, `getdisplayableBody` - the single largest gap in this class, and
  the most encoding/charset-risk-prone), file-upload validation (`checkFileBasics()`), IMAP
  connection lifecycle. Flagging: only 1 of ~13 `flagMessages()` branches tested
  (`FlagMessagesTest.php`'s `unflagged` case).
- `Api\Mail\Account.php`: `write()`/`delete()`/`check_access()`/identity CRUD (`identities()`,
  `read_identity()`, `save_identity()`, `delete_identity()`) - security/data-integrity relevant,
  zero direct tests. Worth pulling forward out of "lower priority" if an identity/ACL bug surfaces.
- `mail/inc/class.mail_acl.inc.php`: `setACL()`/`getACL()`/`deleteACL()`/`update_acl()` - only the
  admin-permission gate is tested, not the ACL mutations themselves.
- `mail/inc/class.mail_sieve.inc.php` (vacation/filter script builder, zero coverage),
  `class.mail_hooks.inc.php`, `class.mail_integration.inc.php` (the actual target of
  `Compose::ajax_integrateSent()`'s cross-app integration - the byte-fidelity of getting the `.eml`
  there is now fixed+tested, but the integration logic itself isn't), `class.mail_zpush.inc.php`
  (only one narrow session-state regression test covers the whole ActiveSync pipeline).
- Small/cheap/isolated wins whenever convenient, regardless of priority order:
  `Api\Mail\RowIdParts.php` (lazy-resolving value object, trivial to fully cover),
  `Api\Mail\Avatar.php`, `Cache.php`, `Notifications.php`.

## Progress log

- 2026-09-09: audit complete (this doc created). Starting on bulk move/copy/delete (priority 1).
- 2026-09-09: bulk move/copy/delete - `mail/js/jmap.ts` client-side methods (26 tests,
  `MailJmapBulkMoveCopyDelete.test.ts`) and the shim's deferred-work queue/`chunkIds()` (7 tests,
  `JmapShimDeferredWorkTest.php`) done. `emailSet()`'s own IMAP-execution branches still open (see
  priority-1 entry above for why - needs a live/mocked IMAP connection, not attempted yet).
- 2026-09-09: started priority 2 (JMAP/shim path) - `mail/js/jmap.ts`'s mailbox CRUD (22 tests,
  `MailJmapMailboxCrud.test.ts`) and label/flag setters (14 tests,
  `MailJmapLabelsAndFlags.test.ts`) done.
- 2026-09-09: paused for a live-reported regression (Reply-To silently dropped on send) - full
  header audit + fix (Reply-To/Priority/read-receipt, then a follow-up Thread-Topic/Thread-Index/
  List-Id reply-propagation regression found while investigating) added real test coverage for
  `draftEmailProperties()`, `fetchForReply()`, `bootstrapReply()`, and the shim's
  `buildMailerFromEmailProperties()`/`emailFromFetch()` along the way - not tracked as its own
  priority-2 line item above since it was bug-driven, not audit-driven, but the coverage is real
  and future audits of this doc should account for it.
- 2026-09-09: back to priority 2 - `mail/js/jmap.ts`'s `keywordsToRowFlags()`/
  `aggregateThreadKeywords()` (19 tests, `MailJmapThreadKeywordAggregation.test.ts`),
  search/filter/sort translation (`buildFilter`/`buildTokenizedFilter`/`flaggedFilter`/`buildSort`,
  30 tests, `MailJmapFilterAndSort.test.ts`), and `saveDraft()` (9 tests,
  `MailJmapSaveDraft.test.ts`) and WebSocket push-payload building (`buildWsPushPayload`/
  `buildEmailPush`/`buildMailboxPush`/`buildEmailDeletePush`, 14 tests,
  `MailJmapWsPushPayload.test.ts`) done. Deliberately skipping the S/MIME encrypt and PGP
  dispatch/cache methods for now - a concurrent session is actively working in that exact area
  (confirmed by a live "PGP/S-MIME signatures must not verify unless the key/cert claims the
  sender's address" fix landing mid-session). Rest of priority 2 (the shim's real-account
  `emailQuery`/`emailGet`/`resolveSmime`/`resolveTnef`/`emailSubmissionSet`, and the entire
  real-JMAP-facing layer) still open.
- 2026-09-09: `mail/js/jmap.ts`'s attachment upload/resolve methods (26 tests,
  `MailJmapAttachmentUploadResolve.test.ts`) done - `uploadAttachment`/`downloadBlobUrl`/
  `reuploadAttachmentForAccount`/`isLocalAccount`/`uploadVfsAttachment`/
  `fetchAttachmentsMetadata`/`getAttachmentViewUrl`/`revokeAttachmentViewUrls`/
  `resolveOutgoingInlineImages`. Full JS suite: 146 files, 1982 tests, all passing (up from
  145/1956). Remaining priority-2 work: the shim's real-account `emailQuery`/`emailGet` IMAP
  translation, `resolveSmime()`/`resolveTnef()`'s IMAP-fetch half, `emailSubmissionSet()`, and
  the entire real-JMAP-facing layer (`Api\Mail\Jmap\Email.php`, `EmailSubmission.php`,
  `Identity.php`, `Mailbox.php`).
- 2026-09-09: the shim's Email/query filter/sort translation (`findInMailbox`/`filterToQuery`/
  `applyCondition`/`buildSort`/`keywordToFlag`, 28 tests, `ImapFilterQueryTest.php`) done - pure
  functions, no live IMAP connection needed. Confirmed `ImapBuildMailerTest.php`'s pre-existing
  `testVfsPathAttachmentIsReadDirectlyFromVfs` S3/VFS-stream-wrapper failure reproduces identically
  standalone, unrelated to this addition. Deliberately not touching `emailSubmissionSet()` (has a
  PGP/S-MIME-adjacent multipart/signed branch, and needs a live SMTP/IMAP connection anyway - a
  concurrent session is still active on PGP/Autocrypt work, 2 more commits landed this session).
  Remaining priority-2 work: `emailQuery()`/`emailGet()`'s own real-account IMAP search/fetch
  EXECUTION, `resolveSmime()`/`resolveTnef()`'s IMAP-fetch half, `emailSubmissionSet()`, and the
  entire real-JMAP-facing layer (`Api\Mail\Jmap\Email.php`, `EmailSubmission.php`, `Identity.php`,
  `Mailbox.php`).
- 2026-09-09: `Mailbox.php`'s `getMailboxId()`/`folderId2path()` (10 tests,
  `MailboxFolderResolutionTest.php`) done. Found+fixed a real bug in the process:
  `getMailboxId()`'s chained `#parentId` back-reference self-referenced its own call id instead of
  the preceding segment's, breaking real-JMAP (Stalwart) nested-folder lookups - see the
  priority-2 entry above for the full explanation. Remaining real-JMAP-facing layer work:
  `EmailSubmission.php`, `Identity.php`'s `synthesize()`.
- 2026-09-09: `Email.php`'s convenience wrappers (30 tests, `EmailTest.php`) done - same
  fake-session approach as `MailboxFolderResolutionTest.php`, no live connection needed. Documents
  (doesn't fix) a harmless dead-code path in `getChanges()`'s `$mailbox` parameter. Remaining
  priority-2 work: `emailQuery()`/`emailGet()`'s own real-account IMAP search/fetch EXECUTION,
  `resolveSmime()`/`resolveTnef()`'s IMAP-fetch half, `emailSubmissionSet()`, and
  `EmailSubmission.php`/`Identity.php` in the real-JMAP-facing layer.
