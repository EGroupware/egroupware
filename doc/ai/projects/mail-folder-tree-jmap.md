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
  `ajax_foldersubscription`) → JMAP `Mailbox/set` for real JMAP.
  - **Done (2026-08-15)**: `JmapShim::mailboxSet()` implements RFC 8621 §2.5 create/update
    (rename/move/(un)subscribe)/destroy against Horde's `createMailbox()`/`renameMailbox()`/
    `deleteMailbox()`/`subscribeMailbox()` - the local-shim gap identified below is closed.
    `Mailbox/get` is still only a real-JMAP thing (via `Api\Mail\Jmap`, not this shim) - nothing
    in the local-shim client path has needed folder metadata beyond what `Mailbox/query` already
    gives, so it wasn't added speculatively; add it if/when a concrete caller needs it.
    Unlike `emailSet()`, every create/update/destroy entry is individually try/caught into its
    own `SetError` (folder ops fail per-item - "already exists", "not empty" - in ways a batch
    of independent edits shouldn't all abort for). `onDestroyRemoveEmails` is honoured (a
    non-empty mailbox is rejected with `mailboxHasEmail` unless the client explicitly opts in).
    Test: `mail/tests/JmapShimMailboxSetTest.php` (mocks `Mail\Imap`, no live IMAP needed).
  - **Admin-impersonation support, built in from the start** (ralf's requirement):
    `JmapShim::imapServer()`/`hordeMailbox()`/`mailboxSet()` all take an optional `$calledFor`
    (account_id to impersonate), mirroring `Mail\Account::read($acc_id,
    $called_for)->imapServer($called_for ? (int)$called_for : false)` exactly.
    `hordeMailbox($imap, $path, $calledFor)` resolves under the impersonated user's
    `getUserMailboxString($calledFor)` root, joined with the "others" namespace's own delimiter
    (not necessarily the same as the admin's personal one) - mirrors `mail_acl::edit()`'s own
    root resolution. **Security boundary**: `dispatch()` (the client-facing entry point behind
    `mail/jmap.php`) never passes `$calledFor` - it is NEVER derived from client-supplied
    request data, only from a future trusted server-side PHP caller that has already run its own
    admin-permission check (mirroring `mail_acl::_require_admin_permission()`).
    This caller shape isn't hypothetical - admin >> Manage users >> (edit a mail account)
    already reaches `mail_acl.inc.php` exactly this way today, for ACL editing specifically: a
    hook-registered toolbar button (`mail_hooks::emailadmin_edit()`'s `'mail_acl'` action) links
    to menuaction `mail.mail_acl.edit` with an explicit `acc_id`+`account_id`, gated by
    `_require_admin_permission()`. The same hook wires up `'mail_vacation'` ->
    `mail_sieve.editVacation` identically (`$this->mail_admin`/`$this->is_admin_vac` gating,
    `$this->account->imapServer($this->is_admin_vac ? $account_id : false)`) for admin-set
    vacation notices. If a folder-CRUD admin screen is ever built, that same
    hook + `$_GET['account_id']` + admin-permission-check wiring is the template to follow -
    `mail_acl.inc.php` itself just doesn't do folder CRUD (create/rename/delete/subscribe), only
    ACL grants, so no caller reaches `mailboxSet()`'s `$calledFor` branch yet.
  - Original gap analysis (now closed, kept for history): **`JmapShim` has no `Mailbox/set` or
    `Mailbox/get` at all today** (confirmed by reading `mail/src/JmapShim.php` - it dispatches
    only `Mailbox/query`, `Email/query`, `Email/get`, `Email/set`, `Email/import`), so the
    local-shim side of this is a real implementation gap, not just a client-side change - mirrors
    the amount of new server-side shim work the original row-listing/body-rendering phases each
    needed.
- **Resolved (2026-08-15)**: `ajax_emptySpam`/`ajax_emptyTrash` are already covered - `app.ts`'s
  `mail_emptySpam()`/`mail_emptyTrash()` already try a JMAP fast path first (`MailJmap.purgeFolder()`,
  paginated `Email/set` destroy) and only fall back to the classic `ajax_*` methods on
  failure/inapplicability, same interception shape [[mail-jmap-modernization]] already uses for
  move/delete/flag. So these two are permanent classic-fallbacks, not folder-tree-structure code -
  **not blocked on this migration at all**, and could be decoupled into
  [[mail-bo-decoupling]]-style handler classes independently, any time. **Done (2026-08-15)**:
  both moved to `mail/src/Ui/MessageActionHandler.php` as `emptySpam()`/`emptyTrash()` (thin
  `mail_ui` wrappers kept for the menuaction-dispatched `ajax_*` names) - see that doc's Phase 2
  progress notes.
  `ajax_compressFolder()` (manual "compress this folder" = mark-\Deleted-then-EXPUNGE) has **no**
  JMAP fast path anywhere client-side, and probably never can - JMAP's `Email/set` destroy is
  immediate/one-phase, so IMAP's two-phase delete model has no JMAP equivalent to translate to. Ralf
  decided (2026-08-15) to remove this feature outright rather than migrate or keep it classic: it's
  "an old IMAP-inspired workflow", no known users, everyone works with a Trash folder now. See
  removal tracked separately (not part of this migration).

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

## Open questions - resolved (2026-08-15)

- **Real JMAP (Stalwart) `Mailbox/set` support** - confirmed via Stalwart's own docs/source
  (`crates/jmap/src/mailbox/set.rs`): implemented, including quota enforcement (`object_quota`) and
  ACL validation for shared mailboxes. Nothing in this codebase calls it yet, but the server-side
  capability itself is real, not just assumed-per-RFC.
- **`JmapShim`'s actual gap is narrower than "Mailbox namespace"**: `Mailbox/get` is already
  implemented and in production use (`Api\Mail\Jmap`'s folder id↔path resolution calls it today),
  and `Mailbox/query` already exists (`JmapShim::mailboxQuery()`). Only `Mailbox/set` (create/
  rename/delete/subscribe) is a real gap - still needs scoping against Horde's
  `Horde_Imap_Client_Socket` mailbox primitives (`createMailbox()`/`renameMailbox()`/
  `deleteMailbox()`/`subscribeMailbox()`) the same way `Email/set` already wraps the message-level
  ones, but it's one method family, not the whole namespace.
- **`ajax_compressFolder`/`ajax_emptySpam`/`ajax_emptyTrash` fate** - see the corrected note in
  "Scope: what moves client-side" above: `emptySpam`/`emptyTrash` are already properly covered
  (client-side JMAP fast path + classic fallback, not blocked here); `compressFolder` has no JMAP
  path and is being removed outright as an unwanted legacy feature, not migrated.
- **Where `mail_tree.inc.php` ends up** - read in full (610 lines); splits cleanly:
  - Deleted entirely, replaced by client-side JMAP querying: `getTree()`, `setOutStructure()`,
    `treeLeafNoConnectionArray()`, `nodeHasChildren()`, `getNodeLevel()`, `isAccountNode()` - all just
    walk classic `getFolderArrays()`/`_getNameSpaces()` IMAP data into Et2Tree node shape.
  - Survives, needs shrinking: `getAccountsRootNode()` - builds the account/profile root nodes (the
    tree "base" that already stays server-side per "What stays server-side" below), but currently
    also self-nests via `setOutStructure()`; needs a smaller return contract once children are
    attached purely client-side.
  - Pure formatting, unrelated to tree structure: `getIdentityName()` - no IMAP calls at all, just
    formats an account's display label from the `identLabel` preference bitmask. Candidate to move to
    `Mail\Account` independently of this migration, not part of "delete on migration".
  - Needs re-scoping: `getInitialIndexTree()` currently glues account-roots + first-level branches
    together for sidebox init; shrinks to just the surviving account-root call once branch-fetching
    moves client-side.

## Related

- [[mail-jmap-modernization]] - the parent project; row-listing/body-rendering/bulk-actions already
  JMAP-native, folder/mailbox administration was the one explicitly-deferred piece this doc covers.
- [[mail-bo-decoupling]] - the folder-management group in `Api\Mail` and the folder-ajax-handlers
  group in `mail_ui` should not be decoupled/extracted until this migration lands; everything else
  in that plan (S/MIME, Import, address-list/body-decoding/custom-labels/folder-string-helpers
  already done) is unaffected and can proceed independently.
