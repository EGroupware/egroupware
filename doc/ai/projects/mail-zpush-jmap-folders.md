# Mail: JMAP-native folder operations for `mail_zpush` (ActiveSync)

## Status: not started - scoped 2026-10-29, triggered by a live ActiveSync outage

## How this was found

egroupware.org's own domain was migrated to Stalwart on 2026-10-28. ActiveSync broke immediately:
`mail_zpush::AlterPingChanges()` (z-push's "Ping"/push-detection mechanism) failed on every single
poll with `Horde_Imap_Client_Exception: Error when communicating with the mail server` -
`Api\Mail::getFolderStatus()` was attempting a real raw IMAP handshake against
`stalwart.egroupware.org:443`, a JMAP-over-HTTPS port IMAP can never speak to at all. z-push's own
exponential device-backoff then made ActiveSync look completely dead (up to 2h blocked, repeating).

A narrow, literal stop-gap already shipped and is confirmed working live (commits `96c1f784ae`/
`5280f53e10`): `mail_zpush::_mailInstance()` detects this one exact `stalwart.egroupware.org:443`
combination and swaps in a classic `Mail\Imap` connection to `mail.egroupware.org:993` instead
(rewritten to the cluster-internal, Dovecot-director-proxied `mail:10143` by the existing
`/var/www/mail-overwrites.inc.php` mechanism where that applies) - confirmed via a raw IMAP `LOGIN`
that this proxies correctly through to Stalwart (`ID` response: `"name" "Stalwart"`), and via live
ActiveSync traffic successfully fetching/parsing a message afterward.

**That stop-gap only helps egroupware.org's own account.** It's an exact host:port string match,
deliberately not a general `Imap\Jmap`/`Imap\Stalwart` fallback - any other customer migrating to
Stalwart hits the identical class of failure with no workaround. This doc scopes the real fix.

## Correction to [[mail-jmap-imap-inversion]]'s Phase 2 scoping

That doc's 2026-08-26 Phase 2 scoping screened `mail_zpush.inc.php` and concluded it was "not a
real consumer of the raw-IMAP call sites - out of scope, nothing to migrate" (6 hits, all
`icServer->ImapServerId` property reads). That screening only grepped `mail_zpush.inc.php`'s own
direct `icServer->` references - it missed that `mail_zpush` is a heavy consumer of `Api\Mail`'s own
methods, several of which call `icServer->getMailboxes()`/`getStatus()`/etc. two or three layers
down, exactly like the rest of `Api\Mail`. Corrected there; full mapping below.

## Full method map: everything `mail_zpush` calls on `Api\Mail` (verified 2026-10-29)

`mail_zpush.inc.php` calls 33 distinct `Api\Mail` methods (plus two pure-static/local ones). Traced
each one's actual implementation to see whether it's already JMAP-native or falls through to a raw
`Horde_Imap_Client_Socket` call `Imap\Jmap`/`Imap\Stalwart` don't override.

### Already JMAP-safe (dispatches to a private `jmap<Method>()` helper, or an inline
`instanceof Mail\Imap\Jmap` guard, before ever touching the raw socket)

| `Api\Mail` method | JMAP path |
|---|---|
| `getHeaders()` | `jmapHeaders()` |
| `flagMessages()` | `jmapFlagMessages()` - covers every real flag; only falls through for delete/undelete (handled separately) or if id-resolution fails |
| `deleteMessages()` | `jmapDeleteMessages()` |
| `moveMessages()` | `jmapMoveMessages()` + `jmapCrossAccountTransfer()` for cross-account moves |
| `getMessageBody()` | `jmapGetMessageBody()` |
| `getMessageEnvelope()` | `jmapResolveUid()` + `jmapGetMessageHeader()` |
| `getMessageRawHeader()` | `jmapGetMessageRawHeader()` |
| `getMessageRawBody()` | `jmapGetMessageRawBody()` |
| `getMessageAttachments()` | `jmapGetMessageAttachments()` |
| `getAttachment()` | `jmapGetAttachment()` / `jmapGetAttachmentByCID()` |
| `appendMessage()` | inline guard |
| `getHierarchyDelimiter()` | inline guard |
| `openConnection()` | inline guard - correctly no-ops for JMAP accounts |
| `closeConnection()` | calls `icServer->disconnect()`, a literal empty no-op method in base `Imap` - harmless for any account type |
| `getTrashFolder()`/`getSentFolder()`/`getOutboxFolder()`/`getArchiveFolder()` | `_getSpecialUseFolder()`, JMAP-guarded - trusts the configured folder name instead of verifying/auto-creating via IMAP |
| `isSentFolder()`/`isOutbox()`/`isDraftFolder()`/`isTrashFolder()`/`isTemplateFolder()` | pure string comparison against the folders above |
| `getAccountIdentities()`, `_strtotime()`, `parseRawMessageIntoMailObject()` | pure DB/local logic, no server interaction at all |

### Genuine fallthrough gaps (no JMAP path exists - raw `Horde_Imap_Client_Socket` every time)

| `Api\Mail` method | Raw call(s) hit | Used by `mail_zpush` for |
|---|---|---|
| **`getFolderStatus()`** | `icServer->getMailboxes()` **and** `icServer->getStatus()` - neither overridden | `AlterPingChanges()` (ActiveSync Ping) - **the exact cause of the live outage above** |
| `folderIsSelectable()` | calls `getFolderStatus()` internally - inherits the same gap | gates every `reopen()` |
| `reopen()` | the gap above, **plus** its own direct `icServer->openMailbox()` call (2nd independent gap) | before almost every message operation |
| `folderExists()` | `icServer->mailboxExist()` | folder existence checks - also the cause of the (harmless but noisy) `"mailboxExist... openMailbox failed"` log spam seen for unrelated reasons since before this migration |
| `createFolder()` | `icServer->createMailbox()`/`subscribeMailbox()` | folder create |
| `renameFolder()` | `icServer->renameMailbox()` | folder rename |
| `deleteFolder()` | `icServer->subscribeMailbox()`/`deleteMailbox()` | folder delete |
| `getFolderObjects()` | `icServer->getMailboxes()`/`listSubscribedMailboxes()` | `GetFolderList()` |

Every gap is a **folder-management** operation. Every message-level operation `mail_zpush` uses is
already properly JMAP-native - this was built out for the main Mail UI and `mail_zpush` gets it for
free via the shared `Api\Mail` bo.

## Scope of the real fix

Add JMAP-native overrides in `Imap\Jmap` (`api/src/Mail/Imap/Jmap.php`) for exactly the methods in
the gap table: `getMailboxes()`, `getStatus()`, `mailboxExist()`, `openMailbox()` (reopen()'s 2nd
gap - see below), `createMailbox()`, `renameMailbox()`, `deleteMailbox()`, `subscribeMailbox()`,
`listSubscribedMailboxes()`. This is a bounded, single-class change (plus extending the existing
`Api\Mail\Jmap\Mailbox` helper), following the exact template `getACL()`/`setACL()`/`deleteACL()`
already established - JMAP `Mailbox/get`/`Mailbox/set` calls, not a rewrite of `mail_zpush` or
`Api\Mail`. `Imap\Jmap`'s own class docblock already names this as the direction: *"Currently, JMAP
is only partially used... everything else still uses an IMAP connection. @ToDo replace everything
with JMAP no longer using / extending IMAP"*.

This is deliberately **not** part of [[mail-jmap-imap-inversion]]'s Phase 2 (the `Api\Jmap`/
`Mail\Jmap` object-model rewrite) - that's a much larger, foundational, multi-session effort,
explicitly paused with "no easy slice found". This fix follows the same low-risk pattern already
proven 10+ times over in `Api\Mail` (a private `jmap<Method>()` helper or inline `instanceof`
guard, falling back to classic IMAP for anything it can't handle) and ships independently, much
sooner.

### `reopen()`'s `openMailbox()` call

For a JMAP account there's no real "selected mailbox" IMAP session state to maintain - every JMAP
call already addresses its target by `mailboxId` explicitly. `openMailbox()` should get the same
treatment `openConnection()` already has (see the existing `JMAP-FALLTHROUGH-GUARD` comment there,
[[project_jmap_imap_fallthrough_cleanup]]): a no-op override in `Imap\Jmap`, not a real
implementation.

### The UID/UIDVALIDITY/HIGHESTMODSEQ semantic gap

`getStatus()`'s real purpose for `mail_zpush` is `AlterPingChanges()` building a `$syncstate` string
(`"M:$messages-R:$recent-U:$unseen-NUID:$uidnext-UIDV:$uidvalidity-MODSEQ:$highestmodseq"`) purely
to detect *whether anything changed* between polls - it never needs those values to carry real
IMAP UID semantics. JMAP has no 1:1 equivalent (`Mailbox/get` has no UIDVALIDITY/UIDNEXT concept,
and per-mailbox change detection is really `Email/changes`' opaque state strings, not a `Mailbox`
property). Proposed: synthesize the sync-state string from `Mailbox/get`'s `totalEmails`/
`unreadEmails` plus the account-level JMAP `state` string already available via the existing
`getChanges()`/push-subscription plumbing - good enough to detect "something changed" (new/deleted
mail, and most flag changes affect `unreadEmails`), with the same "a flag-only change with no count
change might not trigger a sync" limitation classic STATUS-without-CONDSTORE already has today, not
a regression.

## Proposed implementation

1. **`api/src/Mail/Jmap/Mailbox.php`** (currently just `getMailboxId()`/`folderId2path()`/
   `listAllPaths()`): add `get(array $ids = null, array $properties = ['name','parentId',
   'totalEmails','unreadEmails','isSubscribed','myRights'])`, `create(string $name, ?string
   $parentId)`, `update(string $id, array $patch)`, `destroy(string $id)` - thin wrappers around
   `Mailbox/get`/`Mailbox/set`, mirroring `getACL()`'s existing `Mailbox/get` call shape exactly.
2. **`api/src/Mail/Imap/Jmap.php`**: add the 9 overrides listed above, each translating its
   IMAP-shaped parameters/return value to/from the `Jmap\Mailbox` helper's calls - same pattern as
   `getACL()`/`setACL()`/`deleteACL()` (translate IMAP-shaped args in, JMAP call, translate JMAP
   response back to the exact shape `Api\Mail` expects, so zero changes needed in `Api\Mail` or
   `mail_zpush` themselves).
3. No changes needed to `mail_zpush.inc.php` itself, nor to `Api\Mail` - this is purely filling in
   the missing `Imap\Jmap` overrides the existing per-method short-circuit pattern already expects.
4. Once this ships, the egroupware.org-specific stop-gap in `mail_zpush::_mailInstance()` (commits
   `96c1f784ae`/`5280f53e10`) can be removed - `Imap\Jmap` will handle the account correctly without
   it needing to fall back to a classic IMAP connection at all.

## Verification plan

1. PHP lint + existing test suite (`JmapTest.php`, `AdminMailMailboxesTest.php`,
   `ZpushProfileIdSessionTest.php`) stay green.
2. New tests for each new `Imap\Jmap` override, same pattern as the existing ACL tests.
3. Live-verify against egroupware.org's own Stalwart-migrated account once ported: `GetFolderList()`
   (folder tree), create/rename/delete a test folder from an ActiveSync client, and confirm
   `AlterPingChanges()` actually detects a real new-mail event end-to-end (send a test mail, confirm
   the device gets pushed/pulls it) - all without the `_mailInstance()` stop-gap in place, to prove
   it's no longer needed.
4. Remove the stop-gap (`96c1f784ae`'s `_mailInstance()` override and `5280f53e10`'s now-exercised-
   more-often `pushCallback()` null-folder guard can stay regardless, that one's unrelated/still
   correct) once (3) confirms the real fix covers it.
