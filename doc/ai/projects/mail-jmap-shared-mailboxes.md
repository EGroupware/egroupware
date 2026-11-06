# Mail: "Other users" / shared-mailbox namespace for JMAP (Stalwart) accounts

## Status: Phase 1-3 (tree browsing + message listing) implemented (2026-10-29)

- Phase 1 (`cce71762b2`): `Jmap::bootstrap()` retains the Session's shared accounts
  (`$sharedAccounts`); `Imap\Jmap::getNameSpaceArray()` populates `others` from it.
- Phase 2, top level (`af2b71ff8e`): the client-side tree now shows a conditional "user"
  namespace root (only when the account actually has shared accounts) whose children list the
  shared account names - see `MailJmap.injectSharedAccountsRoot()`/`getSharedAccountPlaceholders()`
  in `mail/js/jmap.ts`.
- Phase 2b (`9fffdfe198`): expanding a shared-account placeholder now lists that account's REAL
  mailboxes (names, hierarchy, roles/icons, expand state) via the same lazy per-level fetch, by
  self-encoding the owning accountId into each node's own opaque id string
  (`MailJmap.getSharedAccountMailboxChildren()`) - no changes needed to Et2Tree, FolderTreeNode or
  app.ts's folderTreeAutoload(), which never need to know a foreign accountId is involved at all.
- Two real bugs found via live click-through testing (boulder.egroupware.org), both fixed and
  verified against real Stalwart data before landing:
  - `3e8f9e1faf`: a genuine pre-existing top-level mailbox literally named "user" (acc_id=89)
    rendered as a visible duplicate alongside the synthetic root - `injectSharedAccountsRoot()`
    now skips entirely when one already exists.
  - `3b30ea9e1d` + `69077a83a0`: a shared account's own folders are typically `isSubscribed:false`
    (the viewer never subscribed to someone else's mailbox) and were getting filtered out at BOTH
    the JMAP-query layer (`jmap.ts`) and a second, independent client-side re-filter
    (`app.ts`'s `buildFolderLevelData()` feeding `folderTree.ts`'s `buildFolderLevel()`) - found
    only by actually clicking through the real tree (a direct `getMailboxChildren()` call alone
    looked correct; the UI-level re-filter was the real remaining culprit). Both layers now
    deliberately never subscribedOnly-filter inside a shared-account branch.
  - Live-confirmed end-to-end (boulder.egroupware.org acc_id=1, "teachers@boulder..." shared
    account): expanding "user" → the shared account → its real folders (Posteingang with unread
    badge, Entwürfe, Gesendet, Papierkorb, Spam) all render correctly, translated, with correct
    expand arrows.
- Phase 3 (`28385579b0`, `d56f25db1d`): `getRows()` now reads a shared account's own folder -
  `resolveSharedAccountId()` resolves the "user/<name>/..." path's owner back to a real accountId
  and threads it through Email/query+get, mailboxId()/mailboxRole()'s own caches (disambiguated via
  a synthetic cacheProfileID), and resolveSmimeSignedAttachments(). Shared folders are selectable
  again (the earlier `noSelect` was specifically because nothing could be listed yet). "All folders"
  search deliberately stays scoped to the primary account always.
  - **Live-confirmed end-to-end**: clicking into "teachers@..."'s Posteingang in the real tree
    lists its actual message ("Reine Text Nachricht" from Ralf Becker) correctly.
  - **Live-confirmed safe boundary**: clicking that message to read it does NOT crash or silently
    act on the wrong data - the preview shows header metadata (already present in the row) but no
    body, and console shows a clean `markOpenedMessageRead(): failed ... JmapUserError: notFound`
    - exactly the designed degradation (markAsRead/fetchBody still assume this profile's own
    accountId, get a clean "not found" from the server, nothing worse).
- Phase 3b (`771a41ff25`), two more live-reported fixes after further click-through testing:
  - Clicking an owner node directly (eg. "default") silently reverted to whatever was previously
    selected instead of showing anything - it was `noSelect`, and `app.ts`'s `changeFolder()`
    refuses to select a `noSelect` node at all. Fixed both ways: the placeholder is selectable now,
    and `getRows()` redirects the bare "user/<name>" path to that account's own INBOX (mirrors how
    clicking a real account's own root node already lands on its INBOX) - live-confirmed: clicking
    "default" now correctly shows "No entries to display" (its genuinely empty INBOX), not the
    primary account's own inbox contents.
  - "noreply-" accounts (ralf: ACL-only groups with no real mailbox worth browsing into) are
    suppressed entirely - new `sharedAccountEntries()` is now the single source of truth for both
    the display-name list and the "does this account have anything worth a 'user' root" check, so
    an account shared with nothing but noreply- groups shows no root at all (same "no empty/dead
    root" rule as having nothing shared). Live-confirmed: boulder's own 3 noreply-* groups
    disappeared from the tree, the other 5 real shared accounts unaffected.
- Phase 3c (`7055a18edf`): a general JMAP-transport bug - a request exceeding the session's own
  `maxCallsInRequest` silently hangs forever instead of erroring - surfaced via this feature's own
  live testing (`info@egroupware.org`, one of the shared accounts listed above, has >16 top-level
  mailboxes) but is **not shared-mailbox-specific**. Full root-cause/fix/verification writeup lives
  in [[mail-jmap-jam-websocket]]'s own "Phase 7" section, since it's a `JamWebSocketClient`/
  `MailJmap.resolveHasChildren()` transport fix, not a shared-mailboxes one.
- **Not yet implemented, deliberately deferred**: reading/acting on an individual message inside a
  shared folder - `fetchBody()` and the dozen-plus other rowId-consuming methods in `jmap.ts`
  (fetchForReply(), verifyPgpSignature(), markAsRead, move/delete, compose-from-shared-context, ...)
  still assume this profile's own accountId. Live-confirmed this degrades safely today (see above),
  but making it actually work is a materially larger, separate increment - would need the same
  "parse row_id's SHARED_ACCOUNTS_ROOT_ID encoding, use the recovered accountId" treatment applied
  to each one individually, audited rather than batch-converted.

Follow-up to ticket #126691 (the Dovecot→Stalwart egroupware.org migration, see also
`adc28974b4` - the Mailbox.isSubscribed crash fix for `mail/src/Ui.php::subscription()`, already
shipped on master). Separate from that crash: ingo (and presumably other migrated staff) also lost
the "Other Users"/shared-mailboxes folders entirely once their account became a pure-JMAP
(`Mail\Imap\Jmap`) account - this doc scopes fixing that.

## Why it's missing - this is a real protocol gap, not an oversight

Classic IMAP's "Other Users" namespace is a pure server-side protocol trick: Dovecot/Cyrus just
answers `LIST "" "user/*"` with whatever is ACL-visible to the connected user. EGroupware never
enumerates other users itself for this - `Imap::getNameSpaceArray()` (api/src/Mail/Imap.php:1502)
just turns a real IMAP `NAMESPACE` reply into `personal`/`others`/`shared` **prefix strings**, and
`Imap::getUserMailboxString()` (api/src/Mail/Imap.php:1479) string-concatenates the `others` prefix
with a username. Nothing in `mail/js/folderTree.ts`/`jmap.ts` has any "other users" concept either -
confirmed zero hits for it - because classic accounts never needed one client-side: the IMAP server
did the work.

JMAP has no NAMESPACE concept at all. `Imap\Jmap::getNameSpaceArray()` was already fixed once
(2026-09-08, see [[mail-folder-tree-jmap]] "13th fallthrough site") purely to stop a crash (a raw
IMAP `NAMESPACE` command against a JMAP-only endpoint) - its `personal`-only replacement was never a
deliberate decision to drop shared-mailbox support, just a side effect of removing the crash.

Per RFC 9670 ("JMAP for Sharing"), a mailbox shared with you shows up as **a separate `accountId`
entry in your own JMAP Session's `accounts` map**, not merged into your primary account's
`Mailbox/get` results. `Imap\Jmap::getACL()`/`setACL()` (same file, ~line 656-816) already implement
the *owner* side of this (writing `Mailbox.shareWith` to grant access to others) - nothing exists for
the *recipient* side (discovering/listing what others have shared with you).

## Confirmed live (2026-10-29) - simpler than RFC 9670 reading suggested

RFC 9670 describes a `Principal/query` + explicit per-account "subscribe" discovery flow before a
shared account appears in the Session. **Verified directly against the real, already-migrated
egroupware.org Stalwart tenant** (via `window.app.mail.jmap.clients[acc_id].session` in the browser,
both on pole.egroupware.org acc_id=18 and boulder.egroupware.org acc_id=89 - same backend, same
result): no such explicit subscribe step is needed in practice. Stalwart already populates the
Session's `accounts` map purely from existing `Mailbox.shareWith` ACL grants. Ralf's own account
already sees 22 non-personal (`isPersonal: false`) shared accounts today (`info@egroupware.org`,
`birgit@egroupware.org`, `gerhard@egroupware.org`, `jobs@egroupware.org`,
`tracker@egroupware.org`, several `noreply-*@` mailboxes) with zero extra action taken - these are
presumably carried over 1:1 from the Dovecot-era ACLs by the migration tooling.

This means: **no new discovery/subscribe flow needs building.** The accounts map is already fetched
wherever a JMAP session is established - it's just discarded everywhere except the one `isPersonal`
entry.

**Not yet verified live** (blocked this session by an unrelated local-docker DNS issue on
boulder's acc_id=89 admin/SMTP connection, nothing to do with this feature): that a `Mailbox/get`
call against one of these foreign accountIds actually succeeds end-to-end through
`Mail\Jmap\Mailbox::listAllPaths($accountId)`. Strongly expected to work (a JMAP server that lists an
accountId in the Session but then refuses `Mailbox/get` against it would be non-compliant, and
`listAllPaths()` already has the `$accountId` parameter, unit-tested for exactly this), but should be
the first thing confirmed once implementation starts, not assumed.

## No new credential-exposure risk (unlike the admin-impersonation case in [[mail-folder-tree-jmap]])

That doc's "hard constraint" is about a *different* scenario - an admin managing a foreign user's
mailbox with privileged, separately-authenticated credentials that must never reach client JS. This
feature is not that: a shared account surfaces through the **same already-client-side bearer
token/session** the user's own primary account already uses (RFC 8620 multi-account - one session,
multiple accountIds). No new credential of any kind needs to reach the browser. Worth stating
explicitly so this isn't confused with that constraint during review.

## Design

### Phase 1 - backend retention (small, low-risk)

- `api/src/Jmap.php::bootstrap()` (lines ~225-233): currently loops `response['accounts']` and
  `break`s on the first `isPersonal` entry, discarding the rest. Retain the full map (or at least the
  non-personal subset) instead of discarding it.
- `Imap\Jmap::getNameSpaceArray()`: populate the (currently always-empty) `others` key from that
  retained list - one synthetic entry per shared account.
- New method alongside `listMailboxPaths()` (api/src/Mail/Imap/Jmap.php:713) that, for a given shared
  accountId, calls `Mail\Jmap\Mailbox::listAllPaths($accountId)` and prefixes the results
  `user/<ownerName>/...` to match classic IMAP's own path convention - so whatever legacy/fallback
  PHP code still reads `others`-namespace paths (mobile subscribe template, any other
  `getUserMailboxString()` caller) needs minimal changes.
- This phase matters for the classic/mobile fallback paths - it is **not** on the critical path for
  the main desktop tree (see Phase 2), since that's entirely client-side already.

### Phase 2 - client-side tree (the part end users actually see)

Explicit requirement (ralf, 2026-10-29): **keep the existing lazy, per-level JMAP tree-loading
behaviour** ([[mail-folder-tree-jmap]] Phase 1) unchanged - this is additive, not a rework. Concretely:

- After an account's JMAP session resolves client-side, check `session.accounts` for any
  `isPersonal: false` entries.
- **Only if that list is non-empty**, add one extra synthetic root node under that mail account (eg.
  "Other users" / `user` - exact label TBD, should match whatever classic currently shows, not be
  invented fresh). If there are none (the common case for most individual mailboxes), no extra node
  appears at all - no empty/dead UI for the common case.
- That synthetic root's children are one node per shared account (labelled by its `name`, eg.
  `info@egroupware.org`), lazily populated as any other tree node - still nothing fetched until the
  user actually expands "Other users" itself, then nothing fetched per shared account until expanded
  further.
- Each shared account's own subtree then uses the exact same lazy per-level `Mailbox/query`+`get`
  mechanism already used for the personal tree today, just parameterized with the foreign
  `accountId` instead of the primary one.
- **Open implementation question, not yet answered**: does the existing lazy per-level fetch code
  (`mail/js/folderTree.ts`/`jmap.ts`) thread `accountId` as a parameter already, or does it assume
  "the primary account" in enough places that this needs real plumbing rather than a one-line
  generalization? Needs a real read of that code before estimating Phase 2's size - don't assume
  either way.

### Phase 3 - subscribing to folders inside a shared account (smaller, can follow separately)

Once Phase 1/2 land, a user should presumably be able to subscribe/unsubscribe folders inside a
shared mailbox the same way as their own (the new `Imap\Jmap::subscribeMailbox()` override from
`adc28974b4` already accepts any mailbox path + implicitly works against whatever `accountId` the
caller's `getMailboxId()`/`Mailbox/set` target - needs checking whether that already generalizes to
a foreign accountId, or needs the accountId threaded through explicitly). Also needs checking: does a
`shareWith` grant's rights model even allow the recipient to change `isSubscribed` on someone else's
mailbox, or is that owner-only? Not yet verified - flag as a real open question, not an assumption
either way.

## Known, separate wrinkle worth keeping in mind (not blocking this feature)

`~/Desktop/stalwart-subscription-update-bug-report.md` (ralf's own report to Stalwart's vendor,
2026-08-19, Stalwart v0.16.18): `Mailbox/set` **updating** `isSubscribed` on an already-existing
mailbox updates the JMAP-visible property but never propagates to IMAP's `LSUB`/`\Subscribed` state
- only the value given at mailbox *creation* reaches IMAP. Shouldn't affect this feature or
`adc28974b4` (EGroupware's own JMAP-backed reads/writes are self-consistent, never touching IMAP
LSUB for `Imap\Jmap` accounts) - but worth checking whether it's been fixed upstream before assuming
JMAP/IMAP subscription state parity anywhere a mailbox might also be touched by a real IMAP client.

## Out of scope

- `mail_acl.inc.php`'s admin-impersonation-of-another-user's-mailbox path - unrelated, stays classic,
  same reasoning as [[mail-folder-tree-jmap]]'s own hard constraint.
- Granting/revoking shares (the *owner* side) - already implemented (`getACL()`/`setACL()`).
- Any fix to the Stalwart-side `isSubscribed`-update-doesn't-reach-IMAP bug above - that's the
  vendor's bug to fix, not ours.
