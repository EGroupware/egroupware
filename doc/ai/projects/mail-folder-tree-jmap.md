# Mail: folder-tree JMAP migration + persisted expand state

## Status: Phase 1 (lazy per-level JMAP tree loading) implemented (2026-08-16)

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
  - **Done (2026-08-16), lazy per-level, not a whole-subtree eager fetch** - ralf's explicit
    direction: some users have hundreds of folders across many levels, so the tree loads one
    level at a time on expand, exactly like the classic `ajax_foldertree` behaviour it replaces
    (an *earlier* draft of this plan had this backwards - eager whole-account fetch - corrected
    before implementation). Chain: `JmapShim::mailboxQuery()` gained a second mode (list every
    direct child of a parent, a real one-level Horde `listMailboxes()` call, when no `name`
    filter is given - the existing single-name-resolution mode `MailJmap.mailboxId()` needs is
    untouched) + new `JmapShim::mailboxGet()` (RFC 8621 §2.6, explicit ids looked up
    individually - **never** a `'*'` full-account scan, which would defeat the whole point for
    large accounts) → client `MailJmap.getMailboxChildren(profileID, parentId)` (`mail/js/jmap.ts`,
    batches `Mailbox/query`+`Mailbox/get` via a result-reference, mirroring `getRows()`'s
    `Email/query`+`Email/get` pattern) → new pure module `mail/js/folderTree.ts`'s
    `buildFolderLevel()` (converts one level to mail's `Et2Tree` node shape - `id`/`text`/
    `tooltip`/`item`/`child`, matching `mail/src/Tree.php`'s override of the base widget's field
    names) → wired into `mail_ui`'s `Et2Tree` instance as `MailApp.mail_folderTreeAutoload()`
    (`mail/js/app.ts`), falling back per-node to the classic `ajax_foldertree` menuaction on any
    JMAP failure. Tests: `mail/tests/JmapShimMailboxGetTest.php` (mocks `Mail\Imap`),
    `mail/js/test/BuildFolderLevel.test.ts` (web-test-runner).
  - **`Et2Tree.ts` (the shared, cross-app tree widget) gained a real capability, not a
    mail-specific workaround**, per ralf's explicit direction ("we need to add two things into
    et2-tree"): its `autoloading` property now accepts a Javascript callback function
    (`(item) => Promise<{item: [...]}>`) as an alternative to the existing menuaction/URL string
    - `handleLazyLoading()` branches on `typeof this.autoloading`, everything else (including
      every other app's existing string-based usage) is unchanged. Confirmed first that
      Shoelace's own `sl-tree-item` has no built-in callback mechanism to piggy-back on (purely
      event-driven: a `lazy` flag + `sl-lazy-load` event, consumer populates children itself),
      so this had to be a real (small, additive) `Et2Tree.ts` change, not something already
      latent in the underlying web component.
  - JMAP's `Mailbox` object has no "has children" hint at all (checked the full field list) -
    real JMAP (Stalwart) mailboxes default to `child: true` (assume expandable); the local shim
    gets a real hint from Horde's `listMailboxes()` `children` option (`\HasChildren`/
    `\HasNoChildren`, same attribute classic `mail_tree.inc.php`'s `nodeHasChildren()` reads),
    falling back to the same "assume expandable" default when a server doesn't support
    LIST-EXTENDED. Either way this is safe, not a compromise: `Et2Tree`'s own
    `handleItemLazyLoad()` already self-corrects (clears the flag) if a first expand comes back
    with zero children - a guaranteed-leaf folder just briefly shows an expand affordance.
  - Tree-node ids stay `profileID::canonical/path` (matching what `mail_changeFolder()`/
    `MailJmap.getRows()`'s own folder-path parsing already expect) - **never** derived from the
    raw JMAP Mailbox id, which is opaque and server-assigned for real JMAP/Stalwart accounts
    (can't be reconstructed into a path at all). Each node keeps its raw JMAP id in a separate
    `jmapId` field purely so *its own* later expand can pass it straight back as
    `getMailboxChildren()`'s `parentId` (see `mail/js/folderTree.ts`'s `FolderTreeNode` docblock).
  - Found and fixed a real, pre-existing bug in `Et2Tree.ts::refreshItem(_id, data)` while
    building this: its docblock promises "if data is provided, use it directly instead of
    re-fetching", but the implementation had that branch entirely commented out, always
    re-fetching via the server regardless of `data` - not just unused/dead code: three live PHP
    call paths (`mail_ui::ajax_reloadNode()`, the subscription screen, the move-folder handler)
    already compute a full tree result and pass it through `mail_reloadNode()` →
    `refreshItem(id, data)`, where it was silently discarded in favour of a shallower re-fetch.
    Fixed as real logic (not a plain uncomment, since the old dead branch itself re-discarded
    `data` too) - a correctness fix for those three existing callers, not just new-code
    enablement.
- **Node autoloading** (`ajax_tree_autoloading`, `ajax_folderMgmtTree_autoloading`) → client-side,
  a JMAP query scoped to the expanding parent. **Partially superseded by the above** - regular
  index-tree autoloading (`ajax_foldertree`, the live index template's actual `autoloading`
  target - `ajax_tree_autoloading` itself turned out to only be referenced by legacy/mobile
  subscribe templates, not the live one) now has its JMAP-native replacement; the folder
  *management* dialog's autoloading (`ajax_folderMgmtTree_autoloading`) is untouched, still
  classic.
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
  - **Client-side wiring done (2026-08-16)** - no server-side changes needed at all for this part
    (`mailboxSet()`/real JMAP `Mailbox/set` already covered everything). `mail/js/jmap.ts` gained
    five thin `MailJmap` methods (`createMailbox`/`renameMailbox`/`moveMailbox`/`deleteMailbox`/
    `setMailboxSubscribed`), all resolving ids via the *existing* `mailboxId()` per-path cache
    (not requiring a tree node to already carry a cached JMAP id) so the fast path works for
    every node, including ones never touched by Phase 1's lazy loading. `mail/js/app.ts`'s six
    existing tree actions (`mail_AddFolder`/`mail_RenameFolder`/`mail_MoveFolder`/
    `mail_DeleteFolder`/`subscribe_folder`/`unsubscribe_folder`) each gained a
    `mail_tryJmapXxx()` fast-path helper mirroring the exact `mail_tryJmapDelete()` pattern
    already established for message bulk actions (`Promise<any> | null`, `??` fallback to the
    unchanged classic ajax call) - refreshing the affected tree level(s) via a new shared
    `mail_refreshFolderLevel()` helper (factored out of `mail_folderTreeAutoload()`) on success,
    rather than reloading the page. Rename/move/delete also invalidate the affected
    `mailboxId()` cache entries (the node itself and every descendant path cached under its old
    location) so a later row-fetch never resolves a stale id.
  - **`Et2Tree::refreshItem(_id, data)`'s bug (see Phase 1 above) actually got fixed this time** -
    the earlier note claiming it was fixed during Phase 1 was wrong (the fix was designed for an
    eager whole-tree-fetch draft and got dropped when the design pivoted to lazy per-level
    loading before shipping; the code was still the original broken version). It's a real
    prerequisite for `mail_refreshFolderLevel()`'s data-push approach here, so it's actually fixed
    now, not just documented as such.
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

- **The `mail.subscribe` popup** (`mail_ui::subscription()`) - the last classic-PHP-rendered piece
  of folder handling: a full-account subscription manager showing every folder in a multi-select
  checkbox tree (pre-checked = subscribed), diffing the submission on Save/Apply.
  - **Implemented (2026-08-16), full scope** - ralf confirmed both the initial tree load *and* the
    save/apply step should go JMAP-native, not just persistence. `MailJmap.getMailboxTree()`: one
    `Mailbox/get{ids: null}` call (the "get everything" mode `mailboxGet()` already supported but
    flagged during Phase 1 as secondary - this is its intended use case). `mail/js/folderTree.ts`
    gained `buildFolderTree()` (nests the *entire* flat list recursively by `parentId`, unlike
    `buildFolderLevel()`'s one-level scope, so `child`/`item` reflect real children, not the
    lazy-loading "assume expandable" default) sharing a new `buildNode()` helper with
    `buildFolderLevel()`. `mail/templates/default/subscribe.xet`'s Save/Apply buttons wired to a
    single `app.mail.mail_subscriptionSave` handler, mirroring `acl.xet`/`acl_save()`'s exact
    precedent (same handler for both, `_widget.id` disambiguates). `mail/js/app.ts` gained
    `mail_subscriptionLoad()` (new `et2_ready()` case `'mail.subscribe'`; reads `profileId` via
    `getArrayMgr('content').getEntry('profileId')` - it's a plain, widget-less content key per
    `mail_ui::subscription()`, so `getValues()`, which only walks the *widget* tree, can never see
    it) and `mail_subscriptionSave()` (diffs the tree's current `.value` selection against the
    remembered original id set, applies each change via the existing
    `MailJmap.setMailboxSubscribed()`, refreshes the opener's tree top level, then
    close-or-resubmit exactly like `acl_save()`). Both fully no-op back to the classic path on any
    failure (JMAP load failure leaves the server-rendered tree untouched;
    `mail_subscriptionSave()` returns `true` whenever the JMAP load never ran, and falls back to
    `this.et2._inst.submit()` - the same classic save/diff path, safe regardless of where the
    tree's *options* came from - on any save-time failure too).
  - **Deliberate, documented scope reduction: namespace-root (un)subscribe protection dropped.**
    Classic code excludes IMAP namespace-root pseudo-folders ("Other Users" etc.) from the
    save-diff via `Api\Mail::_getNameSpaces()` (UW-IMAP-specific quirks, no JMAP equivalent
    concept - real JMAP/Stalwart models shared mail via ACL/`myRights` on ordinary Mailbox
    entries, not a separate namespace tier). Reimplementing this blind, with no live
    namespace-having server to verify against, was judged not worth the risk for a
    (un)subscribing-a-namespace-root action that's harmless (no data loss), just not meaningful.
    Every mailbox JMAP reports is toggleable in the new tree, namespace roots included.
  - Tests: `mail/js/test/BuildFolderLevel.test.ts` gained a `buildFolderTree()` describe block
    (pure nesting/id-construction logic, no network involved - same posture as the rest of that
    file).
  - **Done (2026-08-17)**: smoke-tested on the dev-box, committed+pushed (`e769d2c355`).

## What stays server-side

- The account/profile list itself (tree "base") - `Api\Mail\Account` config data, not IMAP-derived.
- `mail_acl.inc.php` entirely - see the hard constraint above.
- Anything with no JMAP equivalent, if a real gap turns up during implementation (to be confirmed,
  not assumed up front).

## New feature: persisted tree expand/collapse state

- **Precedent it built on**: `<profileID>_LastFolder` egw preference (commit `86b82d18bf`, "Mail:
  fix broken 'remember last opened folder' via egw preferences") - written client-side in
  `MailApp.mail_changeFolder()`, read back server-side in `mail_ui::index()` to seed the initial
  `selectedFolder`. Same shape, different granularity.
- **Done (2026-08-16), as a generic `Et2Tree.ts` capability, not mail-specific glue** - per
  ralf's explicit direction, the second of the "two things to add into et2-tree": a new
  `openStatePreference` property (`"app.prefName"`, empty = feature off). When set: on first
  render (and again whenever the property itself is assigned/changed later, via `updated()` -
  needed because mail sets it imperatively from `app.ts` right after `getWidgetById()`, which can
  run after the widget's own `firstUpdated()` already fired), it reads the preference (a JSON
  array of node ids) and marks matching already-present nodes `open`; on every `sl-expand`/
  `sl-collapse` (the existing `handleItemExpand()`/`handleItemCollapse()`) it collects every
  currently-open node id and writes them back, debounced (~300ms via a plain `setTimeout`).
  Wired to a single tree-wide `'mail.ExpandedFolders'` preference (not per-profile, despite the
  `_LastFolder` precedent's per-profile keying) - node ids already carry `profileID::path`, and
  the one tree instance shows every account's subtree simultaneously, so one flat list covers
  all of them; a per-profile key would need swapping in/out on profile-switch for no benefit.
- **The tricky part, solved**: the tree is lazily autoloaded - a node's children don't exist
  client-side until it's expanded once, so restoring a deep expand path (e.g.
  `INBOX > Project > 2026`) can't just set a bunch of "expanded" flags on nodes that don't exist
  yet. Solved by re-running the restore pass every time a lazy-load merge brings new nodes into
  the tree (`handleItemLazyLoad()`'s completion callback), not just once at first render - each
  pass only marks nodes already present, and `_optionTemplate()`'s own existing expandState-driven
  eager lazy-load dispatch is what actually drives the *next* level's fetch once a matching node
  gets marked open, continuing the cascade level by level as each fetch resolves.
- Debounced, per the original concern about a write-per-click - see above.

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
  - **Done (2026-08-17)**: `getInitialIndexTree()` now just returns `getTree()`'s account-root list
    unchanged - the "glue on the active account's first-level branches eagerly" half was removed
    entirely, closing the one remaining server-side eager-fetch this migration had left in place.
    Triggered by a real production incident: an account with severely elevated per-command IMAP
    latency (root cause still under separate infra investigation - unrelated to this migration)
    made the *active* account's eager top-level-folder fetch (dozens of sequential round-trips for
    an account with ~19 top-level folders) stall the entire `mail_ui::index()` page render, since
    it was the one account still fetched synchronously rather than lazily. Fix confirmed safe by
    reading `Et2Tree.ts::_optionTemplate()` (`api/js/etemplate/Et2Tree/Et2Tree.ts:1249`): any node
    rendered `open=1`+childless+autoloadable already self-triggers a synthetic `sl-lazy-load`
    dispatch on render - the same mechanism the persisted-expand-state feature above relies on - so
    the active account (already marked open by `getAccountsRootNode()`'s `$openActiveAccount`
    logic) now picks up its own top-level folders via the ordinary client-side
    `mail_folderTreeAutoload()` lazy path, exactly like every other account, with no client-side
    change needed. Tradeoff: the active account's folders now appear via a brief loading moment
    after render instead of instantly - accepted as a strict improvement over the alternative
    (an unbounded page-load stall whenever that account's IMAP connection is slow/unreachable).

## Surfacing real JMAP errors to the user (2026-08-17)

The above incident also surfaced a related, longstanding gap: the "JMAP fast-path + classic-
fallback" pattern used throughout `mail/js/jmap.ts`/`mail/js/app.ts` never distinguished "JMAP is
unreachable" (silent classic-fallback is correct) from "JMAP was reached and returned a real error"
(silently falling back too meant the user got zero feedback, even though the fallback would often
fail for the exact same underlying reason). Fixed:

- New `MailJmap.JmapUserError` class + `describeJmapError()`/`describeSetError()` helpers
  (`mail/js/jmap.ts`) - classify a caught jmap-jam rejection (which already throws a real,
  inspectable `{type, description}` object, or an array of them from `requestMany()`, whenever the
  JMAP response itself is `["error", ...]`) into a human message, or `null` for a plain network/
  eligibility failure (kept as today's silent-fallback signal, unchanged).
- Applied uniformly across every `MailJmap` method with the `catch (e) { console.error(...); return
  null/false; }` shape - a real error now `throw`s `JmapUserError` instead of returning null/false.
- The five mailbox-CRUD methods (`createMailbox`/`renameMailbox`/`moveMailbox`/`deleteMailbox`/
  `setMailboxSubscribed`) also now check `Mailbox/set`'s per-item `notCreated`/`notUpdated`/
  `notDestroyed` (previously silently discarded even on an otherwise-successful response) and
  throw a `JmapUserError` built from the real `SetError` detail. `updateKeywords()`/`destroyIds()`/
  `deleteMessages()`'s inline destroy branch (message actions) similarly upgraded from a generic
  translated string to the real per-item error detail.
- Root-level folder-tree fetch (`mail_folderTreeAutoload()`, `mail/js/app.ts`): a `JmapUserError`
  now builds an error leaf (`folderTree.ts`'s new `buildErrorNode()`, mirroring
  `mail_tree.inc.php::treeLeafNoConnectionArray()`'s exact field shape/icons) instead of silently
  falling back to `ajax_foldertree`.
- Row-fetch path (`fetchRows()`/`refreshRows()`) and every `mail_tryJmapXxx()` action wrapper (new
  shared `MailApp.mail_handleJmapError()` helper) now call `egw.message(text, 'error')` on a real
  `JmapUserError` - and, for the action wrappers, skip the classic fallback in that case (a
  definitive server answer isn't a reason to retry via a different path that would likely fail the
  same way) - except `mail_subscriptionSave()` (the subscribe-popup's save), which deliberately
  still runs the classic submit afterward since that's a diff-and-reconcile step, not a repeat of
  the same action.
- Not touched: `fetchBody()`/`repairAddressField()` (their "failure" return value is a meaningful
  result - `{special: true}` - not a bare null/false, so they don't fit this pattern without a
  caller-side redesign) and `resolveInlineImages()`/`downloadAttachment()`/`fetchRawHeader()`
  (already-established different shapes, not enumerated in this pass).

## FIXED (2026-09-04): renaming a shared-namespace subfolder ("user/...") failed and the folder disappeared from the tree

New regression report (ralf): "Renaming mail subfolder under user doesn't work and the folder is
no longer displayed (it's visible at the mailaccount itself)".

**Root cause**: `Jmap\Imap::hordeMailbox()` (canonical path -> real IMAP name) already branches on
`isNamespaceRootPath()` to use the "others" namespace's own delimiter for a "user/..."/"shared/..."
path, since a server's other-users/shared namespace delimiter can genuinely differ from its
personal one (fixed `96d3d0e353`, "Fix namespace-root selection guard and shared-namespace
delimiter"). `canonicalPath()` - the REVERSE direction (real IMAP name -> canonical path, used to
build every `Mailbox/get` node's own `id`/`parentId`) - never got that same fix: it unconditionally
used the 'personal' delimiter. On a server where the two differ, a raw shared-namespace mailbox
name (eg. `user.otheruser.Sub`, others delimiter `.`) never had its delimiter replaced with `/` at
all, leaving it completely unsplittable by `splitPath()` (no `/` to split on) - `mailboxNode()`
then computed `parentId = null` for it, so the folder-tree showed it as a top-level node directly
under the account root ("visible at the mailaccount itself") instead of nested under "user/...".
Renaming it then failed too: the id the client resolved from that wrong tree position decodes back
via the same broken flat path, and by the time `hordeMailbox()` tries to reconstruct the real IMAP
name for the actual `RENAME` command, `isNamespaceRootPath()` no longer matches (the first
"/"-segment is the whole dotted string, not literally "user") - picking the wrong ('personal')
delimiter for the target name Horde is asked to rename to.

**Fix**: added `canonicalPath()`'s missing mirror-image branch - a new `namespacePrefix()` helper
(parallel to the existing `namespaceDelimiter()`) reads the "others" namespace's own raw prefix
(eg. "user."), and `canonicalPath()` now checks the RAW mailbox name against that prefix (before
any translation - `isNamespaceRootPath()` itself only works on an already-canonical path, the thing
this function is building) to pick the matching delimiter, exactly mirroring `hordeMailbox()`'s own
`isNamespaceRootPath()` branch.

**Verified**: added `testCanonicalPathUsesOthersDelimiterForSharedNamespace` to
`mail/tests/JmapShimMailboxGetTest.php` (extending `mockImap()` to support a namespace `'name'`
prefix override) - confirmed it actually catches the regression by reverting just the `Imap.php`
fix locally first (failed with `'user.otheruser.Sub'` where `'user/otheruser/Sub'` was expected,
matching the exact reported symptom), then restored the fix and re-ran clean. Full existing suite
(`JmapShimMailboxGetTest` 28 tests, `JmapShimMailboxSetTest` 17 tests) still green - no regression
to the existing `hordeMailbox()`/`canonicalPath()` round-trip coverage or namespace-root visibility
tests.

## Related

- [[mail-jmap-modernization]] - the parent project; row-listing/body-rendering/bulk-actions already
  JMAP-native, folder/mailbox administration was the one explicitly-deferred piece this doc covers.
- [[mail-bo-decoupling]] - the folder-management group in `Api\Mail` and the folder-ajax-handlers
  group in `mail_ui` should not be decoupled/extracted until this migration lands; everything else
  in that plan (S/MIME, Import, address-list/body-decoding/custom-labels/folder-string-helpers
  already done) is unaffected and can proceed independently.

## `mail/src/Tree.php` removed, switched to `Api\Etemplate\Widget\Tree`'s own constants (2026-09-08)

ralf: "We should be able to remove mail/src/Tree by using the current Api/Etemplate/Widget/Tree
constants in mail's code too, not redefining them." `mail/src/Tree.php` was a 14-line subclass
redefining the base widget's `ID`/`LABEL`/`TOOLTIP`/`CHILDREN`/`AUTOLOAD_CHILDREN` constants as
`id`/`text`/`tooltip`/`item`/`child` instead of `value`/`label`/`title`/`children`/`hasChildren` -
not just redundant duplication as it first looked: `Et2Tree.ts` genuinely supports BOTH naming
conventions with per-field fallbacks, and mail had deliberately used the `id`/`item`/`child` side
consistently across both the classic PHP tree builder (`mail_tree.inc.php`) and the JMAP-native
client-side tree code (`folderTree.ts`'s `buildNode()`/`buildErrorNode()`, several direct-field
accesses in `app.ts`) so PHP-provided root nodes and JS-built child nodes could splice into the
same tree structure without special-casing.

**Widget-level prerequisite first** (`871c5c319a`): several `Et2Tree.ts` methods only ever checked
ONE of the two conventions - `setLabel()`/`getLabel()`/`getSelectedLabel()`/`hasChildren()`, the
private `_search()`/`_deleteItem()` recursion (used by `getNode()`/`deleteItem()`), and
`handleItemLazyLoad()`'s reset-on-empty-result logic - all fixed to fall back to the other
convention, matching the pattern `_optionTemplate()`/`applyOpenState()` already used. Purely
additive (ralf: "We should NOT remove the fallback names in the widget, just mail app should use
the new ones!" - ie. the widget keeps supporting both indefinitely; only mail's own files switch).

**The actual switch** (`e212190835`): `mail_tree.inc.php`/`mail_ui.inc.php` now `use
EGroupware\Api\Etemplate\Widget\Tree;` instead of the mail-specific one; `folderTree.ts`'s
`FolderTreeNode` interface/`buildNode()`/`buildErrorNode()` and every matching field access in
`app.ts` (`.text`→`.label`, `.tooltip`→`.title`, `.item`→`.children`, `.child`→`.hasChildren`,
`.id`→`.value` on tree nodes specifically, NOT on unrelated JMAP `Mailbox.id` or DOM-id params)
switched to match. `folderTreeAutoload()`'s own return-wrapper key also changed from `{item:
data}` to `{children: data}` - not just cosmetic, since `Et2Tree.handleLazyLoading()` does
`Object.assign(node, result)`, so the wrapper key IS the literal field written onto the node.

**2 real latent bugs surfaced and fixed along the way** in `mail_tree.inc.php`: `treeLeafNoConnectionArray()`
built its error-leaf array with literal `'id'`/`'text'`/`'tooltip'` keys instead of the `Tree::`
constants (silently relied on the old constant values matching); `setOutStructure()`'s missing-parent
check used a literal `isset($insert['item'])` instead of `Tree::CHILDREN` - both would have produced
a broken/inconsistent node once the constant's value changed out from under them.

**Verified**: full `mail` jstest group (197/197), `addressbook` (5/5 - exercises `Tree::groups()`'s
base-convention usage, confirming the widget-level fix didn't disturb it), `Et2Tree.test.ts` (3/3),
`mail` PHPUnit suite (157 tests, same 6 pre-existing unrelated REST-connection errors as the known
baseline), `tsc --noEmit` clean (diffed line-for-line against pre-change output - zero new errors
anywhere touched), and a real PHPUnit-harness check (`Api\LoggedInTest`-based, not a bare CLI
script - see [[feedback_accounts_singleton_broken_in_phpunit_cli]]) confirming
`mail_tree::getAccountsRootNode()` now actually returns `value`/`label`/`children` keys, not
`id`/`text`/`item`. Both commits pushed to master.

## `mail_tree` fully audited, dead code removed, renamed to `EGroupware\Mail\Ui\Tree` (2026-09-08)

ralf asked for a full method-by-method usage audit before moving/renaming the class further -
every method, every real caller, and under what conditions (JMAP/IMAP) each is reachable. Findings:
`mail_tree` has no `ajax_*` methods and no `$public_functions` - it's a pure internal PHP helper,
only ever reachable from `mail_ui.inc.php`'s own PHP (plus `getIdentityName()`, called externally
from `ComposeMessageBuilder`). Nothing inside it branches on JMAP vs IMAP - `getAccountsRootNode()`
lists every configured account regardless of backend.

**`getTree()`'s `$_parent` handling was provably dead**: its only 2 real callers
(`mail_ui::subscription()`/`folderManagement()`) always passed `$_parent = null`, and
`isAccountNode(null)` always evaluates false - so the "single node loader" branch, the "account
called for open" sub-branch, and the final "structs children of account root node" reshaping
block were all unreachable regardless of any future caller. `isAccountNode()` itself then had zero
remaining callers.

**Follow-up question from ralf that changed the scope**: "what is the fallback condition [for
`getTree()`]? If it's only about the JMAP shim not working properly, it makes no sense to keep."
Traced precisely: the fallback fires only when `MailJmap.getRootFolders()`/`getMailboxChildren()`
return `null`, whose own docblocks say this means "no usable JMAP access-token (server unreachable,
MFA, ...)" - and `ProfileHandler::jmapBootstrap()`'s own docblock states plainly "every account is
JMAP-eligible... returning null here means the account/server is genuinely unreachable right now".
For plain-IMAP/shim accounts, `localBootstrap()` does no connectivity check at all - the actual
failure surfaces later, inside `getMailboxChildren()`'s own JmapShim dispatch, which is a thin
wrapper directly over the same `Api\Mail`/IMAP connection classic code uses; any error carrying
real JMAP semantics is thrown as `JmapUserError` and shown directly, never routed into this
fallback - only a bare connection-level failure returns `null`. So the fallback could never
actually succeed where JMAP genuinely failed, exactly the same reasoning that already justified
dropping the classic fallback from every *other* JMAP surface (folder-tree browsing, folder CRUD,
message actions - see the dead-code-sweep commits above). Decided: drop it here too.

**Scope after that decision**: `getTree()`/`setOutStructure()`/`nodeHasChildren()`/
`isAccountNode()`/`getNodeLevel()`/`treeLeafNoConnectionArray()` all lost their last real caller and
were removed entirely (`getAccountsRootNode()`'s own call to `setOutStructure()` turned out to be a
no-op for its actual input - a single-segment `path` pops to an empty `$components` in
`array_pop()`, so the whole parent-walking loop never executed for this caller - inlined to the 2
lines it actually did: `unset($baseNode['path']); $roots[TreeWidget::CHILDREN][] = $baseNode;`).
`mail_ui::subscription()`/`folderManagement()` no longer seed a server-side tree at all;
`subscriptionLoad()`/`folderManagementLoad()` (`mail/js/app.ts`) now show a real error leaf
(`buildErrorNode()`) on failure instead of relying on a classic tree that no longer exists.

`getIdentityName()` (+ its `IDENT_*`/`ORG_NAME_EMAIL` constants) moved into `ComposeMessageBuilder`
per ralf's explicit call - it's an identity-display-name formatter, not tree-structure logic, and
was already used from there (`createMessage()`'s own `From:` header) as much as from `mail_tree`.

The 3 survivors (constructor, `getAccountsRootNode()`, `getInitialIndexTree()`) were renamed from
`mail_tree` to `EGroupware\Mail\Ui\Tree`, matching the `mail/src/Ui/*Handler` convention (ralf:
"as it's purely internally use place it in / rename it to Mail/Ui/Tree") - `git mv` first (pure
rename commit, `5926fbce29`), then the content/dead-code-removal commit (`159c7e3bde`).
`$leafImages` pruned to the 2 entries still actually read.

**Verified**: full `mail` PHPUnit suite (157 tests, same 6 pre-existing baseline REST-connection
errors), a real PHPUnit-harness check confirming the old `mail_tree` class no longer exists and
`Tree::getAccountsRootNode()`/`getInitialIndexTree()` still work correctly, full `mail` jstest group
(197/197), `tsc --noEmit` clean, `php -l` clean on every touched file.

## Full `mail_ui` audit (2026-09-08): 2 more confirmed-dead spots + a real UI-bug fix

ralf asked for the same kind of full method/caller/JMAP-vs-IMAP audit done above for `mail_tree`,
now for `mail_ui.inc.php` itself (~60 methods: 15 in `$public_functions`, ~29 `ajax_*`, ~16
internal-only). Most of it is genuinely still needed and backend-agnostic - `mail_bo`/`Api\Mail`
already abstracts JMAP vs IMAP, nothing in `mail_ui` branches on it directly. Confirmed
`ajax_flagMessages`/`ajax_deleteMessages`/`ajax_copyMessages` are NOT dead classic fallbacks: they
still carry a real, JMAP-unreplicated feature - "select all matching the current filter" bulk
operations (`MessageActionHandler::flagMessages()`'s `$_messageList === 'all'` branch builds a real
IMAP/JMAP-shim search query) - confirmed via `MailApp.handleJmapError()`'s own docblock, which
explicitly keeps a real classic fallback for a genuine JMAP failure on these, unlike the folder-CRUD
methods below (whose own docblocks say "there's no classic fallback").

**Found dead**: `mail_ui::smimeExportCert()`/`smimeExportCsr()` (+ `SmimeHandler::exportCert()`/
`exportCsr()`/`accountId()`) - zero real callers anywhere; the admin S/MIME cert UI
(`admin/templates/*/mailaccount.xet`'s export buttons) goes through `admin_mail::smimeExportFile()`
directly now. `ajax_addFolder()`'s call site was structurally unreachable - its `tryJmapAddFolder()`
JS wrapper has no code path that ever returns the "fall back to classic" `null` signal.

**Found reachable only via a real (minor) UI bug**: `ajax_renameFolder()`/`ajax_MoveFolder()`/
`ajax_deleteFolder()`'s wrappers (`tryJmapRenameFolder`/`tryJmapMoveFolder`/`tryJmapDeleteFolder`)
each return `null` specifically for a bare account-root id ("an account root can't be
renamed/moved/deleted") - but `MailApp.checkFolderNoSelect()` (the shared enabled-check for every
tree context-menu action) only excluded "NoSelect"-flagged nodes, not a healthy account root. So
right-clicking Rename/Move/Delete on an *account* (not a folder) could still reach these classic
methods - not a deliberate fallback, a UI gap. Fixed `checkFolderNoSelect()` to also disable those 3
specifically for an account-root node (`action.id` check) - `Add`/`Subscribe`/`Unsubscribe`/
`Folder Management` stay enabled there, each a real, supported account-root operation.

**Cleanup**: renamed the survivors from `tryJmapAddFolder`/etc to `jmapAddFolder`/etc ("try" no
longer applies once there's nothing to fall back to); removed `mail_ui`'s 4 `ajax_*` delegations and
`FolderHandler`'s corresponding method bodies (490 lines - its only caller); removed
`SmimeAccountIdTest.php` (only tested the now-dead `accountId()` guard). `FolderHandler` keeps
`folderSubscription()`/`setFolderStatus()` - genuinely still classic-only (the mobile subscribe
template has no JMAP tree at all; `setFolderStatus()` already no-ops for JMAP accounts).

**Verified**: full `mail` PHPUnit suite (175 tests after the 3 removed `SmimeAccountIdTest` cases,
same 6 pre-existing baseline errors), a live PHPUnit-harness check confirming every removed method
is gone and the survivors are intact, full `mail` jstest group (197/197), `tsc`/`php -l` clean.
Pushed together with the `mail_tree` rename commits above.

## `mail_ui` renamed to `EGroupware\Mail\Ui` (2026-09-08), same day

ralf: "please move/rename the mail_ui class to mail/src/Ui.php including all references to it" -
the same rename Compose/Merge/Tree already went through, now for the main UI class itself. Much
bigger blast radius than any previous rename: ~30 files across mail, api, filemanager, and (outside
this repo entirely - see below) tracker.

**Unlike Compose/Merge/Tree, this one keeps a compat stub** at the old
`mail/inc/class.mail_ui.inc.php` path (`class mail_ui extends EGroupware\Mail\Ui {}`) - ralf's
explicit call: "we need to change the index url in mail/setup/setup.inc.php and have a stub
mail_ui to not fail hard, if the user has not updated." `mail_ui` has a much larger external
surface (menuaction dispatch from any not-yet-rebuilt cached JS bundle, third-party/EPL app code,
saved bookmarks/links) than the previous renames - static properties/methods are inherited by the
stub, not duplicated, so eg. `mail_ui::$icServerID` stays in sync with the real class automatically.

**A genuinely broken intermediate state hit CI** between the pure-rename commit (`f118b34314`,
file moved but class still literally named `mail_ui`) and the follow-up content-conversion commit:
the classic autoloader could no longer find `mail_ui` at its expected old path once the file
moved, breaking `AttachmentJmap.php`'s `\mail_ui::$mimeTypesHandledOnlyByMail` reference - "Class
mail_ui not found" in CI, on a commit a concurrent session in this shared checkout happened to
push in between. Fixed by getting the full rename (incl. the stub) committed and pushed promptly
(`e3bb8fe399`).

**2 real bugs found via live testing while doing this**:
1. `Ui.php`'s `get_tree_actions()` had `EGroupware\Api\Header\UserAgent::mobile()` written without
   a leading backslash (every other reference in the file correctly used the short `Api\Header\
   UserAgent` form via the existing `use EGroupware\Api;` import) - under the new `namespace
   EGroupware\Mail;` that resolved relative to the current namespace instead of the global root
   ("Class EGroupware\Mail\EGroupware\Api\Header\UserAgent not found").
2. A 13th [[project_jmap_imap_fallthrough_cleanup]] site: `Api\Mail\Imap\Jmap` never overrode
   `getNameSpaceArray()`, so any caller (`Compose::ajax_searchFolder()`, used by
   `Ui::importMessage()`'s folder-picker dropdown) fell through to `Horde_Imap_Client_Socket`'s raw
   NAMESPACE command - real IMAP protocol against what's actually the JMAP(S) endpoint for a
   Stalwart account ("Mail server closed the connection unexpectedly"). Initially misdiagnosed as
   transient (matching an earlier, unrelated transient blip seen the same session) - ralf caught
   the actual pattern: consistently fails for the JMAP account (acc_id=1), consistently works for
   the classic Dovecot one (acc_id=42). Fixed with the same override pattern `hasCapability()`
   already uses in the same class - RFC 8621 has no IMAP-NAMESPACE concept, so a JMAP account
   always just gets a flat, personal-only result. Also removed `importMessage()`'s own redundant
   eager `sel_options['FOLDER'] = $compose->ajax_searchFolder(0,true)` call once ralf asked "I dont
   understand why it even tries to connect, before I uploaded the message" - the `FOLDER` field's
   `et2-select` already lazy-loads its options client-side via its own `searchUrl` attribute,
   identical to `compose.xet`'s folder select (which has never had an eager `sel_options` in
   `Compose.php` at all) - the eager call forced a live IMAP connect on every render of the form,
   even before a file was selected, and was what surfaced the fallthrough bug above in the first
   place.

**One file outside this repo's git tracking**: `tracker/inc/class.tracker_bo.inc.php` calls
`mail_ui::resolve_inline_image_byType()` by that exact name - `tracker/` is entirely gitignored
(`.gitignore:57`), a separate EPL repo present on disk in this deployment but not part of this git
repo at all (an earlier research pass in this same session had wrongly concluded it was "this same
repo, a different app" - corrected back). Updated the file on disk (`use EGroupware\Mail\Ui;` +
`Ui::resolve_inline_image_byType()`) since it's a real, needed fix for this deployment, but it
cannot be committed via this repo - whoever maintains the separate tracker/EPL repo needs to apply
the same change there directly.

**Verified**: full `mail` PHPUnit suite + `api/tests/Mail/` (only the known pre-existing REST-
connection errors + one pre-existing, unrelated VFS-fixture-permission failure - confirmed via
stale Aug-24 file permissions on the test fixture directory, not caused by this), a live
PHPUnit-harness check of the stub/static-property-inheritance/both menuaction dispatch forms
(`app.ClassName.method` and the `ClassName::method` direct-static form used by
`ajax_refreshVacationNotice`/`ajax_changeProfile`)/the new `getNameSpaceArray()` override, full
`mail` jstest (197/197 - one test assertion fixed along the way: the backslash in a menuaction URL
gets percent-encoded by the browser's own fetch/URL machinery, harmless since PHP decodes it back
server-side, but the test was checking the raw un-decoded string), `tsc` clean (diffed against
baseline, zero new errors anywhere), `php -l` clean on every touched file. Commit `e3bb8fe399`,
pushed.

## Client-side JMAP folder search (2026-09-08), same day

Continuation of the `getNameSpaceArray()` fix above: fixing the crash wasn't enough, since
`Compose::ajax_searchFolder()` (the classic folder-search endpoint every `FOLDER`/`folder`
et2-select field's `searchUrl` pointed at) has no JMAP data source at all - `Api\Mail::
getFolderObjects()` is IMAP-only. ralf: "The most easy approach would be to also do that
client-side directly against the JMAP server or the shim", refined by "I believe we did something
similar for the folder-tree, by allowing a function instead of an url" (confirming the
`Et2Tree.autoloading`-style `string | function` pattern) and "it would be nice if that also
detects a string starting with app. so it can be directly wired into the template" (the existing
`onExecute="javaScript:app.X.Y"` action-string convention, minus the prefix).

**A second wrong-exception-type bug**, found investigating a "still failing on the JMAP account"
report: `Api\Mail::getFolderObjects()`'s `listSubscribedMailboxes()` call was wrapped in
`catch(Exception $e)` - inside `api/src/Mail.php`'s own `namespace EGroupware\Api;`, `Exception`
resolves to *this namespace's own* `Api\Exception` class, not the global `\Exception` -  so it
never actually caught `listSubscribedMailboxes()`'s real failure mode, the global-namespace
`Horde_Imap_Client_Exception` thrown by the (then still-unguarded) IMAP fallthrough. Same root
cause already documented+fixed once in this file (`importMessageToMergeAndSend()`'s own
`catch(\Throwable $e)`) - fixed the same way at this second site. Only the one call site actually
hit was fixed; a grep found 5 more occurrences of the identical broken pattern elsewhere in the
file, left alone (out of scope for this pass).

**`SearchMixin.ts`'s `searchUrl`** (`api/js/etemplate/Et2Select/SearchMixin.ts`) widened to
`string | ((search, options) => Promise<SelectOption[]>)`, plus a `"app.appname.method"` string
convention resolved via `egw().applyFunc()` (which also lazy-loads/instantiates that app's own JS
object if needed) - both checked in `remoteSearch()` before the existing URL/JSON-file branches.
Verified via a before/after `tsc --noEmit` diff (one new, already-pervasive `Property 'egw' does
not exist on Et2WidgetWithSearch` error, not a new class of error) and the full `api`+`mail` jstest
groups (1332 + 197 passed).

**`MailApp.searchFolder()`** (`mail/js/app.ts`) + two new building blocks: `MailJmap.
getAllMailboxes()` (`mail/js/jmap.ts` - one flat `Mailbox/query`+`Mailbox/get` pass, no `parentId`
scoping, unlike the tree's own lazy per-level `getMailboxChildren()`) and `buildMailboxPaths()`
(`mail/js/folderTree.ts` - resolves every mailbox's own canonical `/`-joined path + translated
label from that flat list via an id->mailbox map, matching `buildFolderLevel()`/`buildNode()`'s
own path-segment/role-label conventions so a search result's value lines up with the tree's own
node ids). Wired into `compose.xet` (both `default` and `mobile`), `predefinedAddressesDialog.xet`,
and `importMessage.xet` via `searchUrl="app.mail.searchFolder"` - `moveFolder.xet`'s identical old
`searchUrl` was left alone, since that whole template turned out to be dead (no server-side
reference to its `mail.moveFolder` template id anywhere, superseded by
[[project_mail_copy_folder_usage]]'s client-side quick-submenus).

**Account selection for `importMessage.xet`** (ralf: "with multiple accounts at least it's hard to
understand/select a specific mail-account you want to import to"): added a `mailaccount`
et2-select (one row per *account*, not per identity like `compose.xet`'s own - which identity
sends is meaningless for "which account to file this message into"), sel_options built the same
`Mail\Account::search(true, false)` + `is_imap(false)` (no live connect) way `Compose::
ajax_getComposeToolbarData()` already builds its own account list, labelled the same way the
folder tree's own account root nodes are (`EGroupware\Mail\Ui\Tree::getAccountsRootNode()`'s
`Compose::getIdentityName(Mail\Account::identity_name(...))` chain). `MailApp.
importMessageAccountChanged()` resets `FOLDER` to the new account's own Drafts folder (falling
back to INBOX) on change, since `FOLDER`'s value now needs to carry an account prefix again
(`noPrefixId` dropped for this one template - every other `searchUrl="app.mail.searchFolder"`
caller keeps it, since none of them have their own account picker). Hides the whole selector row
when only one account is configured (ralf's ask) - nothing to choose, so showing it would just be
a confusing, always-disabled-feeling extra field.

Then ralf: "it would be nice if that also detects... the current active account and folder" -
`MailApp.importMessageInit()` (new `et2_ready()` `'mail.importMessage'` case) preselects both from
`window.opener.app.mail.getActiveFilters().selectedFolder` (same established `window.opener.
app.mail.*` reuse pattern already used elsewhere in this file), falling back to the server's own
default (current account's Drafts folder) when there's no opener or nothing selected there yet.

**Two more real bugs found live testing this** (ralf: "the folder is still empty"):
1. `Ui::importMessage()`'s own `FOLDER` default line had a stray `(array)` cast -
   `$content['FOLDER'] = (array)(...)` - wrapping a plain string value in a one-element array,
   silently breaking the (non-`multiple`) `FOLDER` widget's value outright. Pre-existing since the
   original classic `class.mail_ui.inc.php` (confirmed via `git log`, long before this session's
   rename) - previously harmless-by-accident, since the widget always had a full eagerly-fetched
   option list to (apparently) paper over it; broke visibly only once that eager fetch was removed
   (see the `getNameSpaceArray()` section above) and there were no options left to hide it. Fixed
   by dropping the cast - while there, also fixed the adjacent `preg_match($draft, "/::/")` (args
   swapped - `$draft` used as the pattern, `"/::/"` as the subject; always false/warns for a
   typical folder name, which happened to always route to the correct branch anyway, so this was
   harmless, just fixed in passing since it's the same statement).
2. Even with a correct scalar value, `FOLDER` still showed nothing: it has no eagerly-fetched
   `sel_options` at all (by design, to avoid a live IMAP round trip on every render - see the
   `getNameSpaceArray()` section above), so there was no label to show for whatever value it had,
   preselected or the server's own default alike. Fixed in `importMessageInit()`: resolve the
   selected folder's own label via the same `getAllMailboxes()`/`buildMailboxPaths()` pair
   `searchFolder()` uses, and seed it as the widget's one initial `select_options` entry.

**Verified**: `tsc --noEmit` diffed against baseline (zero new errors), full `mail` jstest group
(197/197) re-run after each incremental change, `php -l` clean. Not yet live-verified in a real
browser against boulder.egroupware.org - ralf reported the empty-folder-display bug from live
testing there, which is the only live-testing feedback so far; the underlying cause (the
`(array)` cast + missing label) is fixed but not yet re-confirmed live.
