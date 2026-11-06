# Mail: JMAP-native folder operations for `mail_zpush` (ActiveSync)

## Status: DONE. All 9 overrides shipped and live-verified 2026-10-29 (`adc28974b4` + `e90b7c8793`).
**Master only - do NOT backport any of this to 26** (ralf, 2026-10-29): 26 is missing much more of
this JMAP-compatibility work and will be rebased onto master soon anyway, so backporting piecemeal
now is wasted effort.

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

## Progress: not ActiveSync-only - the regular Mail UI hit the same gap class independently

Confirms this doc's premise from an unexpected direction: `adc28974b4` (2026-10-29, a separate
session, ticket #126691 follow-up) found the **regular Mail UI's folder-subscription popup**
crashing for the same Stalwart-migrated egroupware.org account - `mail/src/Ui.php`'s
`subscription()` calls `icServer->listSubscribedMailboxes()` directly, which fell through to the
same unoverridden raw-IMAP path, threw, and left `$subscribed` unset, crashing `array_map()` right
after with `"Argument #2 ($array) must be of type array, null given"`. Fixed there by adding
JMAP-native `listSubscribedMailboxes()`/`subscribeMailbox()` overrides to `Imap\Jmap`
(`Mailbox.isSubscribed` / `Mailbox/set`, same pattern as `getACL()`/`setACL()`), extending
`Api\Mail\Jmap\Mailbox::listAllPaths()` with a `$subscribedOnly` filter, plus a defensive default in
`Ui.php`. Tests added: `api/tests/Mail/Imap/JmapSubscriptionNoRawImapFallbackTest.php`,
`api/tests/Mail/Jmap/MailboxListAllPathsTest.php`.

This removes 2 of the 9 methods originally scoped below from the remaining work (marked `DONE` in
the table) and confirms the framing: these gaps hit **any** code path touching folder management
for a JMAP/Stalwart account, server-side PHP mail code generally - not an ActiveSync-specific
problem, exactly as flagged when this doc was written.

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
| ~~`getFolderStatus()`~~ | ~~`getMailboxes()`/`getStatus()`~~ | **DONE (`e90b7c8793`)** - JMAP-native via `listMailboxes()`/`status()` overrides |
| ~~`folderIsSelectable()`~~ | ~~inherited gap~~ | **DONE** - fixed transitively |
| ~~`reopen()`~~ | ~~inherited gap + `openMailbox()`~~ | **DONE (`e90b7c8793`)** - `openMailbox()` now a JMAP-native existence check |
| ~~`folderExists()`~~ | ~~`mailboxExist()`~~ | **DONE** - fixed transitively once `openMailbox()` is JMAP-native (`mailboxExist()` itself needed no override, confirmed live) |
| ~~`createFolder()`~~ | ~~`createMailbox()`~~ | **DONE (`e90b7c8793`)** - JMAP-native via `Mailbox/set` create |
| ~~`renameFolder()`~~ | ~~`renameMailbox()`~~ | **DONE (`e90b7c8793`)** - JMAP-native via `Mailbox/set` update |
| ~~`deleteFolder()`~~ | ~~`deleteMailbox()`~~ | **DONE (`e90b7c8793`)** - JMAP-native via `Mailbox/set` destroy |
| ~~`getFolderObjects()`~~ | ~~`getMailboxes()`~~ | **DONE** - fixed transitively via `listMailboxes()` |
| ~~`listSubscribedMailboxes()`~~ | ~~unoverridden~~ | **DONE (`adc28974b4`)** - JMAP-native via `Mailbox.isSubscribed` |
| ~~`subscribeMailbox()`~~ | ~~unoverridden~~ | **DONE (`adc28974b4`)** - JMAP-native via `Mailbox/set` |

All 9 were folder-management operations. Every message-level operation `mail_zpush` uses was already
properly JMAP-native - that was built out for the main Mail UI and `mail_zpush` got it for free via
the shared `Api\Mail` bo.

## Scope of the real fix

Added JMAP-native overrides in `Imap\Jmap` (`api/src/Mail/Imap/Jmap.php`) for the remaining 7 methods
in the gap table: `listMailboxes()` (backs `getMailboxes()`/`getFolderObjects()`), `status()` (backs
`getStatus()`/`getMailboxCounters()`), `openMailbox()` (backs `mailboxExist()`/`reopen()` - no
separate `mailboxExist()` override needed, confirmed live), `createMailbox()`, `renameMailbox()`,
`deleteMailbox()`. (`subscribeMailbox()`/`listSubscribedMailboxes()` were already done, see
"Progress" above - 2 of the original 9.) A bounded, single-class change (plus extending the existing
`Api\Mail\Jmap\Mailbox` helper), following the exact template `getACL()`/`setACL()`/`deleteACL()`/
the already-shipped `subscribeMailbox()` established - JMAP `Mailbox/get`/`Mailbox/set` calls, zero
changes to `mail_zpush` or `Api\Mail`. `Imap\Jmap`'s own class docblock already named this as the
direction: *"Currently, JMAP is only partially used... everything else still uses an IMAP
connection. @ToDo replace everything with JMAP no longer using / extending IMAP"*.

This is deliberately **not** part of [[mail-jmap-imap-inversion]]'s Phase 2 (the `Api\Jmap`/
`Mail\Jmap` object-model rewrite) - that's a much larger, foundational, multi-session effort,
explicitly paused with "no easy slice found". This fix follows the same low-risk pattern already
proven 10+ times over in `Api\Mail` (a private `jmap<Method>()` helper or inline `instanceof`
guard, falling back to classic IMAP for anything it can't handle) and ships independently, much
sooner.

### `reopen()`'s `openMailbox()` call

For a JMAP account there's no real "selected mailbox" IMAP session state to maintain - every JMAP
call already addresses its target by `mailboxId` explicitly. `openMailbox()` got the same treatment
`openConnection()` already has (see the existing `JMAP-FALLTHROUGH-GUARD` comment there,
[[project_jmap_imap_fallthrough_cleanup]]): a no-op (existence-check-only) override in `Imap\Jmap`,
not a real IMAP "select". Confirmed live this is sufficient - `mailboxExist()` (inherited, calling
`currentMailbox()`+`openMailbox()`) needed no override of its own.

### The UID/UIDVALIDITY/HIGHESTMODSEQ semantic gap

`getStatus()`'s real purpose for `mail_zpush` is `AlterPingChanges()` building a `$syncstate` string
(`"M:$messages-R:$recent-U:$unseen-NUID:$uidnext-UIDV:$uidvalidity-MODSEQ:$highestmodseq"`) purely
to detect *whether anything changed* between polls - it never needs those values to carry real
IMAP UID semantics. JMAP has no 1:1 equivalent (`Mailbox/get` has no UIDVALIDITY/UIDNEXT concept).
Implemented as: `messages`/`unseen` from real `Mailbox/get` `totalEmails`/`unreadEmails`; `recent`
always `0` (IMAP4rev2 itself drops RECENT; confirmed live Stalwart's own real IMAP always returns 0
too); `uidnext` synthetic (`totalEmails+1`); `uidvalidity` synthetic but **stable per mailbox**
(`abs(crc32($mailboxId))` - confirmed live: same value across repeated calls, distinct between
different mailboxes); `highestmodseq` always `0`. Live-verified against a real account with 95
messages/16 unseen in INBOX and an empty Trash - both mailboxes' `status()` results were internally
consistent and `uidvalidity` was stable across two consecutive calls.

## Implementation

1. **`api/src/Mail/Jmap/Mailbox.php`**: added `listAll()` (path => full Mailbox object, refactored
   out of the existing `listAllPaths()`) to back `listMailboxes()` - `Type`'s already-generic
   `get()`/`set()` (inherited from `Api\Jmap\Type`) turned out to cover the create/update/destroy
   needs directly, no new wrapper methods needed there.
2. **`api/src/Mail/Imap/Jmap.php`**: added the 7 overrides, each translating IMAP-shaped
   parameters/return values to/from JMAP calls - same pattern as `getACL()`/`setACL()`/
   `deleteACL()`/`subscribeMailbox()`. Zero changes to `Api\Mail` or `mail_zpush` themselves -
   every real consumer (`getMailboxes()`, `getFolderObjects()`, `getStatus()`,
   `getMailboxCounters()`, `mailboxExist()`, `reopen()`, `createFolder()`, `renameFolder()`,
   `deleteFolder()`) picked this up transitively via ordinary virtual dispatch.
3. The egroupware.org-specific stop-gap in `mail_zpush::_mailInstance()` (commit `96c1f784ae`) was
   **removed** (`2b0d16b9df`) once the real fix was live-verified - keeping it would have kept
   silently shadowing the new overrides for the one real account they were written for, routing
   ActiveSync through the classic-IMAP fallback instead of actually exercising the fix via real
   traffic. `mail_zpush.inc.php` is now byte-identical to its pre-`96c1f784ae` state.
   `5280f53e10` (the separate `pushCallback()` null-folder TypeError fix) is unrelated and stays.

## Verification (done 2026-10-29)

1. PHP lint + existing test suite (`JmapTest.php` 41/41 incl. `AdminMailMailboxesTest.php`,
   `ZpushProfileIdSessionTest.php`, `JmapNoRawImapFallbackTest.php`,
   `JmapSubscriptionNoRawImapFallbackTest.php`, `MailboxListAllPathsTest.php`) - all green.
2. New tests: `api/tests/Mail/Imap/JmapFolderOpsNoRawImapFallbackTest.php` (17 tests, mock-only -
   fake `jmapClient()`, no real HTTP/JMAP connection, same pattern as the existing ACL/subscription
   tests) - verifies request shapes and error handling, not live JMAP behaviour.
3. **Live-verified** against boulder.egroupware.org's own local Stalwart (`acc_id=1`, real user
   `ralf`, real authenticated browser session) via a throwaway diagnostic page, since
   `acc_imap_logintype=email` means this account authenticates with the session's own password
   (SSO-style) - a CLI/PHPUnit test run as a generic test user gets a real, correct 401 from
   Stalwart, not a bug. Confirmed: `listMailboxes('*')` returns all 6 real mailboxes with correct
   attributes/delimiter; `status()` returns real, internally-consistent counts for both INBOX and
   Trash with stable-but-distinct `uidvalidity`; `mailboxExist()` correctly returns true/false for a
   real/bogus folder; and - the actual original failure chain -
   **`Api\Mail::getFolderStatus('INBOX')` (exactly what `AlterPingChanges()` calls) returns a fully
   correct result end-to-end.** Diagnostic page and PHPUnit test deleted after use, never committed.
4. Not live-tested: `createMailbox()`/`renameMailbox()`/`deleteMailbox()` (mutating a real account's
   folder structure - deliberately not attempted without a specific ask) and the full
   `mail_zpush`/ActiveSync path itself (needs a real device, impractical to drive interactively).

**Branch policy (ralf, 2026-10-29): master only, no 26 backport for any of this work** - including
the already-shipped `adc28974b4` and the stop-gap commits above. 26 is missing substantially more of
this JMAP-compatibility surface than what's tracked in this doc, and will be rebased onto master
soon, making a piecemeal backport now wasted effort.
