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
