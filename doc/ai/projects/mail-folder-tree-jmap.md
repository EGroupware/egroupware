# Mail: folder-tree JMAP migration + persisted expand state

## Status: planned, not started (2026-08-14)

Continuation of [[mail-jmap-modernization]] - that project explicitly deferred "Folder/mailbox
administration" as "a separate, larger concern, not started". This doc is that concern, scoped, plus
a combined feature request ralf wants pursued at the same time.

## Goal

1. **Move folder-tree reading and node-autoloading from server-side PHP to client-side + JMAP**
   (real JMAP for Stalwart, `JmapShim` for plain-IMAP accounts) - same pattern already proven for
   row-listing/body-rendering/bulk-actions in [[mail-jmap-modernization]].
2. **Only keep the tree *base* server-side**: the list of mail accounts/profiles (root nodes) -
   that's EGroupware config data (`Api\Mail\Account`), not IMAP-derived, so it has no JMAP
   equivalent and no reason to move. Everything under an account node (the actual folder structure)
   moves client-side.
3. **Persist folder-tree expand/collapse state per user**, so the tree looks the same on next login
   as when they left it - a long-open feature request, worth doing together with (1)/(2) since the
   client will already own node expand/collapse state once the tree is client-side, making this a
   cheap addition rather than a new subsystem.

## Hard constraint: admin-impersonation of another user's mailbox stays server-side, always

Folder/ACL management isn't only ever "the logged-in user browsing their own mailbox" -
`mail/inc/class.mail_acl.inc.php` also lets an **admin manage folders/ACLs for an arbitrary other
user's mailbox** (triggered via buttons in the `admin_mail` app), using an admin-privileged IMAP
connection (`Mail\Imap::$isAdminConnection`, an explicit `$account_id` passed to
`Mail\Account::read($acc_id, $account_id)`, gated by
`isset($GLOBALS['egw_info']['user']['apps']['admin'])`). That privileged connection's credentials
must never reach client-side JavaScript - handing them to the browser so it could talk JMAP directly
would mean any admin session's JS has the means to authenticate as an arbitrary other user's
mailbox, an obvious privilege-escalation/credential-exposure hole (same category as
`feedback-no-session-id-as-token` in the AI memory system - a real session/credential handed to
client JS as if it were an ordinary bootstrap token).

**Consequence for scope**: this migration applies only to the *end-user's own mailbox, browsing
their own account, in their own client-side session*. Concretely:
- `mail_acl.inc.php` (ACL editing, including the admin-for-arbitrary-user path) is **out of scope
  entirely** - stays classic PHP/IMAP, regardless of which app UI triggers it. It already has its
  own separate ajax endpoints (`ajax_folders`, `ajax_setACL`, `ajax_deleteACL`) independent of
  `mail_tree`/`mail_ui`'s own tree-browsing endpoints, so this doesn't require threading a
  conditional through shared code - it's already a structurally separate class.
- The existing `ajax_jmapBootstrap()` (`mail_ui`) already only ever opens the *current* user's own
  IMAP/JMAP connection (`Mail\Account::read($icServerID)->imapServer()` with no admin flag) - it has
  no code path today that could hand out a JMAP session for an admin-impersonated account. Keep it
  that way: never add an `account_id`-for-someone-else parameter to it or to the client-side tree
  fetch. `Mail\Imap\Stalwart` already reinforces this at the connection layer too - an admin
  connection explicitly skips the JMAP access-token path (`Imap/Stalwart.php:154`,
  `if ($this->isAdminConnection || !($access_token = ...))`), falling back to classic IMAP.
- If any *other* admin-context screen is later found to reuse `mail_tree`/`mail_ui`'s own-mailbox
  tree-browsing code for a foreign account (not confirmed to exist today, but worth checking before
  assuming it's clean), that call path must keep working through the classic PHP route - it cannot
  be assumed to always mean "the current session's own mailbox" without verifying first.

## Why this has to happen before further folder-related decoupling

[[mail-bo-decoupling]] identified two groups that overlap entirely with what this migration would
replace:

- `mail_ui`'s "folder ajax handlers" (`ajax_tree_autoloading`, `ajax_foldertree`, `ajax_reloadNode`,
  `ajax_setFolderStatus`, `ajax_addFolder`, `ajax_renameFolder`, `ajax_MoveFolder`,
  `ajax_deleteFolder`, `ajax_foldersubscription`, `ajax_folderMgmtTree_autoloading`,
  `ajax_folderMgmt_delete`, `ajax_compressFolder`, `ajax_emptySpam`, `ajax_emptyTrash`) - the
  largest single domain group in that plan.
- `Api\Mail`'s "folder management" group (`getFolderObjects`, `getFolderArrays`, `getFolderStatus`,
  `createFolder`, `renameFolder`, `deleteFolder`, `getQuotaRoot`, `_getNameSpaces`,
  `getSpecialUseFolders`, ...).
- `mail_tree.inc.php` (tree-node structure building) - not even in that decomposition doc yet, but
  squarely in scope here.

Decoupling that code into clean, tested PHP components now - only to delete or drastically rewrite
most of it once this migration lands - would be wasted effort, and would add test coverage for a
shape of the code that's about to disappear. **Do this migration first; decouple whatever thin
server-side remainder is left afterward** (expected to be small: just the account-list tree base).

No hard ordering requirement against [[mail-bo-decoupling]]'s Phase 2 (mail_ui ajax-handler
regrouping starting with S/MIME/Import) - those domains don't overlap with folder-tree work, so
either can be picked up first.

## Scope: what moves client-side

- **Tree structure listing** (`mail_tree::getTree()` and its node-children logic) → client-side
  `MailJmap`, via `Mailbox/query` (real JMAP already supported; `JmapShim::mailboxQuery()` already
  exists for the local shim - see [[mail-jmap-modernization]]'s row-listing section for the existing
  registered-fetch-callback pattern this can likely reuse/mirror for a "mailbox" prefix).
- **Node autoloading** (`ajax_tree_autoloading`, `ajax_folderMgmtTree_autoloading`) → client-side,
  a JMAP query scoped to the expanding parent.
- **Folder status/counters refresh** (`ajax_reloadNode`, `ajax_setFolderStatus`) → client-side +
  JMAP `Mailbox/get` (unread/total counts are standard JMAP Mailbox properties).
- **Folder CRUD** (`ajax_addFolder`, `ajax_renameFolder`, `ajax_MoveFolder`, `ajax_deleteFolder`,
  `ajax_foldersubscription`) → JMAP `Mailbox/set` for real JMAP; **`JmapShim` has no `Mailbox/set`
  or `Mailbox/get` at all today** (confirmed by reading `mail/src/JmapShim.php` - it dispatches only
  `Mailbox/query`, `Email/query`, `Email/get`, `Email/set`, `Email/import`), so the local-shim side
  of this is a real implementation gap, not just a client-side change - mirrors the amount of new
  server-side shim work the original row-listing/body-rendering phases each needed.
- **Not yet clear if these move**: `ajax_compressFolder`, `ajax_emptySpam`, `ajax_emptyTrash` - these
  are closer to the existing bulk-message-action JMAP paths (`deleteAllMatching()`/`purgeFolder()`,
  already JMAP-native per [[mail-jmap-modernization]]) than to folder-tree structure - likely already
  mostly covered or a small extension of that existing code, needs checking before assuming they're
  in scope here.

## What stays server-side

- The account/profile list itself (tree "base") - `Api\Mail\Account` config data, not IMAP-derived.
- `mail_acl.inc.php` entirely - see the hard constraint above.
- Anything with no JMAP equivalent, if a real gap turns up during implementation (to be confirmed,
  not assumed up front).

## New feature: persisted tree expand/collapse state

- **Precedent to build on**: `<profileID>_LastFolder` egw preference (commit `86b82d18bf`, "Mail:
  fix broken 'remember last opened folder' via egw preferences") - written client-side in
  `MailApp.mail_changeFolder()`, read back server-side in `mail_ui::index()` to seed the initial
  `selectedFolder`. Same shape, different granularity.
- **Proposed**: a `<profileID>_ExpandedFolders` (or similar) preference holding the set of expanded
  node paths/ids, written client-side on every node expand/collapse toggle, read back once at
  tree-init to auto-expand matching nodes as they're created.
- **The tricky part**: the tree is lazily autoloaded - a node's children don't exist client-side
  until it's expanded once. Restoring a deep expand path (e.g., `INBOX > Project > 2026`) means
  triggering each level's autoload in sequence as the previous level's children arrive, not just
  setting a bunch of "expanded" flags on nodes that don't exist yet.
- Consider debouncing the preference write - expanding several nodes in quick succession shouldn't
  fire a preference write per click.

## Open questions / needs investigation before implementation

- Real JMAP (Stalwart) `Mailbox/set` support for create/rename/delete/subscribe - assumed available
  per RFC 8621, not yet live-verified against Stalwart specifically.
- `JmapShim`'s `Mailbox/get`/`Mailbox/set` need to be implemented from scratch for the local-shim
  path - scope that against Horde's `Horde_Imap_Client_Socket` mailbox primitives
  (`createMailbox()`/`renameMailbox()`/`deleteMailbox()`/`subscribeMailbox()`, already used by
  `Api\Mail`'s classic folder methods) the same way `Email/set` already wraps the message-level ones.
- Exact fate of `ajax_compressFolder`/`ajax_emptySpam`/`ajax_emptyTrash` - check against existing
  JMAP-native bulk-delete/purge paths before assuming new work is needed.
- Where `mail_tree.inc.php` ends up - likely deleted or drastically shrunk once tree structure is
  client-side; anything of its logic still needed (e.g. `sortByMailbox`-style folder ordering) should
  land in the client-side TS, not stay as unused PHP.

## Related

- [[mail-jmap-modernization]] - the parent project; row-listing/body-rendering/bulk-actions already
  JMAP-native, folder/mailbox administration was the one explicitly-deferred piece this doc covers.
- [[mail-bo-decoupling]] - the folder-management group in `Api\Mail` and the folder-ajax-handlers
  group in `mail_ui` should not be decoupled/extracted until this migration lands; everything else
  in that plan (S/MIME, Import, address-list/body-decoding/custom-labels/folder-string-helpers
  already done) is unaffected and can proceed independently.
