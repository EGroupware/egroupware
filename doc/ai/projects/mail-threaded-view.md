# Mail: threaded/conversation view

## Status: Phase 1 core work done (2026-08-22) - engine + bulk-action expansion + UI toggle landed, entirely feature-flagged off

Ralf asked for a plan for an optional "group messages by thread" view in the mail list, using
`Et2Nextmatch`'s existing hierarchical-row support (as used by filemanager/infolog/addressbook),
JMAP threads where available, IMAP `THREAD` where available, and disabling the feature entirely
where neither is available. This doc started as research + design; Phase 1 implementation is now
under way, gated behind `ProfileHandler::THREADING_ENABLED` (currently `false`) so it's completely
inert in production until finished.

### What's landed so far (2026-08-22)

- `ProfileHandler::THREADING_ENABLED` (`mail/src/Ui/ProfileHandler.php`) - the single master
  switch ralf asked for: off by default, and doubles as the "this account's backend doesn't
  support thread grouping yet" gate (Phase 1 is real-JMAP-only, so `jmapBootstrap()` ANDs it with
  "not a local/IMAP-shim account" - a plain-IMAP account reports unsupported regardless of this
  flag until Phase 2 exists). Exposed to the client as `token.supportsThreading` per profile.
- `MailJmap.getRows()` (`mail/js/jmap.ts`) gained a `query.threaded` parameter - when true and the
  profile's token reports `supportsThreading`, it delegates to a new `getThreadedRows()`: collapses
  the folder's messages into one representative Email per JMAP thread
  (`Email/query`'s `collapseThreads`), then batches `Thread/get` + a narrow `Email/get(keywords)`
  for that page's threads (chained via the `/list/*/emailIds` wildcard result-reference,
  live-verified against Stalwart) purely to compute each closed thread row's aggregate. A
  single-message "thread" (the common case in most inboxes) skips all of that and renders via the
  ordinary `email2row()` - zero extra cost over today's flat list.
- `MailJmap.fetchRows()`'s previously-inert `_queriedRange.parent_id` branch now recognizes a
  thread-parent row's `row_id` (`...::thread:<threadId>`) and calls a new `getThreadMemberRows()`
  to answer an expand with the thread's plain member messages (RFC 8621 threads are flat, so
  there's never a second level to expand) - any other/legacy `parent_id` still answers empty as
  before.
- Aggregation: `keywordsToRowFlags()` (extracted, pure refactor, out of `email2row()`'s existing
  flags/css/status-icon logic - both it and the new `emails2threadRow()` now share it) +
  `aggregateThreadKeywords()` (AND-folds `$seen` across every member so a thread only looks read
  once every message is; OR-folds every other keyword - flagged/answered/forwarded/labels/custom
  flags - so any member having it is enough to show on the closed row). Renders through the exact
  same `class`/`flags`/`status_icon` vocabulary `email2row()` already produces, so **no new CSS is
  needed** - confirms the design doc's prediction above.
- New test coverage in `mail/js/test/MailJmap.test.ts` (7 cases): single-vs-multi-message thread
  row shape, the AND/OR aggregation rules, the `supportsThreading:false` fallback-to-flat-list
  safety gate, and `fetchRows()`'s `parent_id` expand branch (both the real thread-row case and the
  still-empty non-thread case). Full mail JS test group (129 cases) and `tsc --noEmit` both clean
  (one pre-existing, unrelated `IegwAppLocal` import error aside - confirmed present on HEAD before
  this work, not caused by it).

### Deliberately not done yet (tracked here, not silently skipped)

- **No UI toggle.** There is no way to actually turn `query.threaded` on from the running app yet -
  no checkbox, no preference, no nm setting. This was a deliberate scope cut for this pass: with
  `THREADING_ENABLED` off, wiring a visible toggle would mean shipping new (even if disabled/hidden)
  DOM into `index.xet` while another session's mail-app work was still in flight - ralf's own
  framing ("off by default, to not disturb the new mail app's testing") argued for zero visible
  surface change over a "flag exists but hidden" compromise. Confirmed via the actual
  `Et2Nextmatch.ts` source that no PHP nm setting is even required for row expansion to work
  (`_isExpandableNextmatchRow()` falls back to a plain `rowData.is_parent === true` check with no
  settings-gate at all) - so this cut costs nothing on the engine side, it's purely "no way to
  flip it on yet outside a test/console".
- **No "Thread" JMAP push handling.** Flag-change pushes still target a plain message row id, not
  a thread row - a live push against a threaded view would refresh nothing today. Tracked in this
  doc's "Unseen rollup" section already; not revisited since Phase 1's UI isn't reachable yet
  anyway.

### Bulk-action expansion (2026-08-22, same day) - landed

Ralf's decision from above (move/delete/flag/mark-read on a selected thread row applies to every
member message, always with the same "are you sure" treatment a real multi-select would get, even
when only one thread row is checked) is now implemented:

- `MailJmap.expandThreadRowIds(ids : string[])` (`mail/js/jmap.ts`) - the actual expansion. Reads
  each id's already-cached row data (`egw.dataGetUIDdata()`) to spot a thread-parent row
  (`is_parent && thread_id`, no regex on the id string needed), then batches `Thread/get` +
  `Email/get(id)` per thread (profileID/mailboxId come straight out of the thread row's own
  `row_id`, no folder-path lookup needed - unlike `getThreadMemberRows()`, which only ever has a
  folder path to start from) to produce real member row ids in the exact
  `accountId::profileID::mailboxId::emailId` shape `messageReference()` already expects. A plain
  id (the overwhelming majority, always 100% of them today) passes straight through with no
  network call at all. Falls back to leaving a thread id unchanged on any resolution failure -
  `messageReference()`'s own existing try/catch and `handleJmapError()` already know what to do
  with an id they can't use, so this doesn't need its own separate error UX.
- Wired into all five `messageReference()`-mapping call sites in `mail/js/app.ts` -
  `tryJmapDelete()`, `tryJmapMove()`, `tryJmapCopy()`, `flagMessages()`'s explicit-selection
  branch, and `trySetMdnFlag()` (this last one only for consistency; an MDN flag write is always a
  single previewed message, which can't be a thread-parent row in the first place - opening one
  expands it instead of showing a body preview). Each converts a synchronous `try { ids.map(...) }
  catch { return null }` into an async `.then()` chain ending in the same downstream
  `.catch(handleJmapError)` - a parse failure now flows through that shared error handler instead
  of an immediate `null` return, which still reaches the same classic-ajax fallback either way, one
  microtask later (verified: no test regressions, and the change is confirmed behaviourally inert
  while every id in a selection is a plain message id, i.e. always today).
- `MailApp.checkAllSelected()` (`mail/js/app.ts`) gained a new gate, ahead of its existing
  "select-all-matching-filter" dialog logic: `expandedSelectionCount(_elems) > _elems.length` (a
  synchronous check - reads each selected row's already-cached `thread_count` directly, no round
  trip, since counting doesn't need real member ids, only how many there are) triggers a plain
  Yes/Cancel "this affects N messages in the selected thread(s)" confirmation before proceeding.
  Deliberately **not** implemented by extending the existing "ALL messages in the current view"
  dialog/messaging - that dialog answers a genuinely different question (did you mean literally
  every message matching the current filter, not just what's loaded), and reusing its wording for
  "your selection expands to N messages" would have been actively misleading. The two dialogs'
  action-dispatch switch statements were identical, so that dispatch logic was extracted into a
  shared `dispatchMailAction()` used by both (plus the third, no-confirmation-needed, fallthrough
  path) rather than duplicated a third time.
- Test coverage: 5 new cases for `expandThreadRowIds()` in `mail/js/test/MailJmap.test.ts`
  (pass-through with no thread rows, real expansion, in-place expansion alongside ordinary ids,
  fallback on no-token, and a malformed-row-data guard). `MailApp`'s side of this
  (`expandedSelectionCount()`/`dispatchMailAction()`/`checkAllSelected()`'s new gate) has no direct
  unit test - matching this codebase's existing precedent, there is no test harness for `app.ts`
  methods at all yet (`mail/js/test/` only ever exercises `jmap.ts`/pure logic), so building one
  from scratch for this alone would be disproportionate; covered instead by the full mail JS test
  suite staying green (134 cases) plus `tsc --noEmit` clean (same one pre-existing, unrelated
  `IegwAppLocal` error as before).

### UI toggle (2026-08-22, same day) - landed, icon-based, Thunderbird-style

Ralf asked for the same mechanism the existing "sneak preview" toggle uses, but as a two-icon
toggle button (flat-list icon vs. a threaded/hierarchy icon) rather than a text dropdown - modeled
on Thunderbird's own threaded-view toolbar button:

- `<et2-button-toggle id="threaded" icon="diagram-2" offIcon="list-ul" ...>` in
  `mail/templates/default/index.xet`, right next to the existing `id="details"` sneak-preview
  toggle - the same widget class already used for that toggle (and several other apps' details/
  unread toggles), just with two distinct Bootstrap Icons instead of one dimmed/undimmed icon.
  Ships with `style="display:none"` baked in - see below for why that's safe to leave as the
  template's own default rather than something JS has to set up on first render.
- **Not the literal `filter2` mechanism** - `filter2`/`ShowDetails` only works via
  `Et2Nextmatch.ts`'s small hard-coded `FILTER_VALUE_SETTINGS` seeding allowlist
  (`filter`/`filter2`/`cat_id`/`search`), which isn't extensible from app code. The generic,
  app-usable equivalent mail already relies on for `selectedFolder` - nextmatch's
  `extra_attributes` seeding - works identically for any name, so `'threaded'` was added
  alongside `'selectedFolder'` in `mail_ui.inc.php`'s `extra_attributes` array, with its own
  parallel persisted-preference seed (`mail.ShowThreaded`, mirroring `ShowDetails` exactly -
  including that preference's existing, apparently-vestigial nature: nothing in this codebase
  ever *writes* `ShowDetails` either, only reads it as an initial value, so the toggle's state is
  session-scoped via nextmatch's own filter state, same as sneak-preview already is).
- `MailApp.toggleThreaded()` (mirrors `toggleDetails()` exactly) and a `checkNmFilterChanged()`
  sync branch (mirrors the existing `filter2`/`details` sync) - both trivial, following the
  established pattern precisely.
- `MailApp.updateThreadingToggle(supported)` - the one genuinely new piece, since this toggle
  (unlike sneak-preview) needs to be hidden entirely for any account that can't actually support
  it. Called from `MailJmap.getRows()` every time a profile's token resolves (cheap - a cached
  boolean already sitting on the token, no extra round trip), so it self-corrects on every fetch
  and every account switch. Resets the filter back off if a profile stops supporting it while the
  toggle was left on (switching from a hypothetical Phase-2-supporting account to a non-supporting
  one).
- `_filters.threaded` (the `'1'`/`''` convention every nextmatch filter widget uses) reaches
  `MailJmap.fetchRows()`'s query-building exactly like `filter2` does, coerced to the real boolean
  `JmapGetRowsQuery.threaded` already expected.
- Two new lang phrases (en+de): the toggle's `statustext` ("Group messages by thread") and the
  bulk-action confirmation dialog's message text (added when that landed, lang entries were
  missing until now).

**A second real bug found and fixed while live-testing this** (same "manually flip
`THREADING_ENABLED` to `true` locally, test against real Stalwart, flip back before committing"
process the row-building engine itself was verified with) - clicking the toggle hung the entire UI
behind an unresolvable "still waiting?" dialog. Root cause: all three `Thread/get` -> `Email/get`
`requestMany()` chains in `jmap.ts` (`getThreadedRows()`, `getThreadMemberRows()`,
`threadMemberRowIds()`) only returned the *dependent* invocation from the callback (`{members}`/
`{emails}`), not the `Thread.get` invocation it referenced via `.$ref()` - the exact same jmap-jam
`requestMany()` contract violation already documented and fixed once before in this file, for
`getMailboxChildren()`'s `Mailbox/query` -> `Mailbox/get` chain (see the comment/test block right
above "MailJmap.getRootFolders()" in `mail/js/test/MailJmap.test.ts`). Unlike that earlier case
(which surfaced as a clean per-item JMAP error - "missing field `resultOf`"), this one just hung
the WebSocket transport indefinitely with no error at all, which is what actually made it visible
(a stuck confirmation dialog, not a console error) - worth remembering as a *second* failure mode
for the same underlying mistake. Fixed by returning both invocations at all three call sites, with
new regression tests (`mail/js/test/MailJmap.test.ts`, "Thread/get -> Email/get chains - jmap-jam
requestMany() invocation shape") that assert on the exact property the bug was missing - the prior
`createThreadedFakeClient()`-based tests couldn't have caught this class of bug at all, since that
fake always answers with its canned fixture regardless of what the callback actually returned.

Live-verified end-to-end against real Stalwart (profile/acc_id=1) with `THREADING_ENABLED`
temporarily flipped `true` locally (never committed): toggle appears/disappears correctly per
profile, clicking it switches the list into collapsed thread rows with the expand caret rendering
correctly (confirmed via `Et2Nextmatch`'s existing `is_parent` fallback, no template change needed
for that part), expanding a thread row fetches its real member messages, and toggling back off
restores the flat list - all matching the design doc's predictions.

Remaining Phase 1 gap: none identified. Phase 2 (IMAP `THREAD` via `JmapShim`) and Phase 3
(live-update/push refinement) are still not started, per the phasing above.

Continuation of [[mail-jmap-modernization]] and [[mail-folder-tree-jmap]] - both established the
precedent this design leans on hardest: **mail's row-listing is already 100% client-side JMAP**
(real Stalwart JMAP, or the local `JmapShim` over plain IMAP for non-JMAP accounts), with no
server-side `get_rows()` at all (`mail_ui.inc.php:592`: *"no 'get_rows' callback: rows are fetched
client-side via direct JMAP access... there is no server-side fallback path anymore"*). Threading
should be built the same way: client-side in `mail/js/jmap.ts`, not as a classic PHP `get_rows()`
hierarchy.

## Goal

1. Add an optional "Group by thread" toggle to the mail list. When on, each row in the top-level
   list represents a *thread* (a JMAP RFC 8621 Thread, or an IMAP-THREAD-derived equivalent) rather
   than a single message.
2. A thread row aggregates state from its member messages - most importantly **unseen**: if any
   message in a collapsed thread is unseen, the thread row itself renders as unseen (bold/dot),
   exactly like a folder in the tree shows bold/badge when it has unseen children.
3. Expanding a thread row reveals its individual messages, using `Et2Nextmatch`'s existing
   parent/child row-expansion contract (the same one filemanager uses for directories, infolog for
   sub-tasks, addressbook for grouped contacts).
4. The toggle must not be offered (or must be inert) on any account/server that can't actually
   support thread grouping - JMAP without `Thread` support and IMAP without `THREAD=*` alike.

## Why the framework's hierarchy contract is a good fit here (and where it isn't)

`Et2Nextmatch` (the current, actively-developed widget - `api/js/etemplate/Et2Nextmatch/Et2Nextmatch.ts`;
the legacy `et2_extension_nextmatch.ts` is EOL, only used by `.old.xet` templates) supports exactly
one hierarchy shape, documented in `Et2Nextmatch.md:45-75` and used today by:

- filemanager (`filemanager_ui.inc.php:400-405`, directories as parents, `is_dir` as the parent
  flag)
- infolog (`infolog_ui.inc.php:405-406`, sub-tasks, `info_anz_subs` as the parent flag)
- addressbook (`addressbook_ui.inc.php:276-277`, grouped/duplicate views, `group_count`)

The contract: three settings on the nextmatch (`row_id`, `parent_id`, `is_parent`[`_value`]).
A row with a truthy `is_parent` field gets an expand affordance
(`Et2Nextmatch.ts:2599 _isExpandableNextmatchRow()`). Expanding it renders a **separate embedded
`<et2-datagrid class="nextmatch-subgrid">`** (`Et2Nextmatch.ts:2628`) with its own
`Et2DatagridDataProvider`, which fetches its one flat page of child rows by sending
`parent_id: <the parent row's row_id value>` as part of the queried range
(`Et2NextmatchDataProvider.ts:227-241`, `createChildProvider()`). This is **exactly one level**
- there's no nested-children-array model, and RFC 8621 threads are themselves flat
("a Thread is simply a flat list of Emails" - `jmap-rfc-types/lib/jmap-mail.ts:204-221`), so the
one-level contract is a precise fit, not a compromise. No cycle risk either, for the same reason.

Two things the framework does **not** give us, confirmed by the Et2Nextmatch research and by
`Et2Nextmatch.md:64` ("child totals are not rolled up into the root total"):

- **No built-in aggregation.** `is_parent`/`group_count`/`info_anz_subs` are all just booleans or
  counts computed by the app; nothing rolls up e.g. "any child is unseen" onto the parent
  automatically. We have to compute the collapsed thread row's `unseen`-ness (and any other
  aggregate - flagged, attachment, participant list) ourselves, in the code that builds the
  thread's row, before nextmatch even sees it.
- **No persistence of expand/collapse state** (`Et2Nextmatch.ts:422-424`, in-memory `Set`/`Map`
  only). Unlike the folder tree (which now persists expand state per [[mail-folder-tree-jmap]]),
  thread rows will re-collapse on every full list reload. That's actually the right default for
  mail (a freshly reloaded inbox with expanded threads scattered through it would be confusing),
  so this is not a gap worth closing - flagging it only so it isn't mistaken for an oversight later.

The important consequence for *this* codebase specifically: because there is no PHP `get_rows()`
anymore, the "parent fetch" and "child-on-expand fetch" both have to be served by
`MailJmap.fetchRows()` (`mail/js/jmap.ts:1009`), the same function already registered via
`egw.dataRegisterFetch('mail', this.jmap.fetchRows, this.jmap)` (`app.ts:197`). `Et2Nextmatch`
doesn't care whether the fetch callback is PHP or client JS - it just calls
`egw().dataFetch()` with `queriedRange.parent_id` set for child fetches - so this reuses existing,
proven plumbing rather than inventing a new one. `fetchRows()` needs a new branch: if
`_queriedRange.parent_id` is present, treat it as "fetch this thread's member messages" instead of
"fetch this folder's message page".

## Per-backend design

### Tier 1: real JMAP (Stalwart) - best case, close to free

RFC 8621 already gives us everything:
- `Email/query` takes a `collapseThreads: boolean` filter - confirmed typed and ready to use in the
  vendored client library (`jmap-jam`'s `node_modules/jmap-jam/dist/index.d.ts:28-30`), but **never
  called anywhere in this codebase today** (`api/src/Mail/Jmap.php`, `mail/js/jmap.ts` - zero hits
  for `collapseThreads` outside `node_modules`).
- `Email.threadId` exists on every Email object but is **never requested** in either row-fetch path
  today: `MailJmap.getRows()`'s `Email/get` properties list (`jmap.ts:254-261`) omits it, and so
  does the push-changes path (`api/src/Mail/Jmap.php:1001,1010`, `getChanges()`). Trivial one-line
  additions in both places.
- `Thread/get` (`{id, emailIds: [...]}`, date-ordered) is fully typed in `jmap-jam` and **never
  called**. This is what expanding a thread row uses to enumerate its members.
- Stalwart's push (`StateChange`) payload can include a `Thread` entry
  (`api/src/Mail/Imap/Jmap.php:758-760`, comment only) - implying real `Thread/get`/`Thread/changes`
  support server-side - but `Api\Mail\Jmap::getChanges()` only processes `Mailbox`/`Email` changes
  today, not `Thread`. The push-payload-shape question is still unverified (see "Live Stalwart
  verification" below), but **`collapseThreads`/`Thread/get` themselves are now confirmed working
  correctly against the real server** - live-checked 2026-08-22, see that section for the exact
  queries and results.

Row building, concretely:
1. Top-level fetch (`getRows()`): add `collapseThreads: true` to the `Email/query` call
   (`jmap.ts:246-253`) when threading is on, and add `threadId` to the `Email/get` properties list.
   This gives one representative Email per thread, already windowed/paginated by Stalwart exactly
   like today's flat list (position/limit/calculateTotal semantics are unchanged - collapsing
   happens before windowing per RFC 8621).
2. For that page of representative Emails, batch a `Thread/get` for their distinct `threadId`s in
   the same JMAP request (via a result reference, the same technique `getRows()` already uses to
   chain `Email/query` -> `Email/get` in one round trip, `jmap.ts:244-267`), to learn each thread's
   full `emailIds` list (member count, needed for "3 messages" style UI).
3. Aggregating **unseen** (and optionally flagged/answered) requires the `keywords` of every member
   email, not just the representative one - `Thread/get` doesn't return keywords. So this needs one
   more batched `Email/get` call for the union of all `emailIds` across all threads on the page,
   requesting only `['id', 'threadId', 'keywords']` (cheap - no bodies/addresses). All three calls
   (query -> thread-get -> narrow email-get) can still be one JMAP HTTP request via chained result
   references.
4. Build the thread row (a new `emails2threadRow()` alongside the existing `email2row()`,
   `jmap.ts:3152`) using the representative Email's subject/from/date/etc. for display, but with
   `row_id` set to a synthetic id (e.g. `thread:<threadId>`, to disambiguate from a plain message
   uid), `is_parent: emailIds.length > 1`, and `class`/flag fields computed by folding the
   "narrow" keyword fetch across all member ids (any missing `$seen` -> add `unseen` to the row's
   css class, mirroring `email2row()`'s existing `$seen` handling at `jmap.ts:3170-3227` and the
   existing `tr.unseen { font-weight: bold }` rule at `mail/templates/default/app.css:73`- no new
   CSS needed).
5. On expand: `fetchRows()` sees `_queriedRange.parent_id === 'thread:<threadId>'` (or however we
   encode it), skips `Email/query` entirely, and does `Thread/get` (or reuses step 2's result if
   still cached for that thread) + a full `Email/get` (all normal display properties) for its
   `emailIds`, returned as an ordinary flat page through the *same* `email2row()` used for the
   non-threaded list - child rows in the sub-grid look/behave exactly like normal message rows.

### Tier 2: IMAP `THREAD` extension (Dovecot etc., via `JmapShim`)

Confirmed: `Api\Mail\Imap` (`api/src/Mail/Imap.php:74`) directly extends
`Horde_Imap_Client_Socket`, and the vendored library (`vendor/egroupware/imap-client`) has full
RFC 5256 support already wired up:
- `Horde_Imap_Client_Base::thread()` (`Base.php:2396`) - public, capability-checked, returns a
  `Horde_Imap_Client_Data_Thread`.
- `Socket::_thread()` (`Socket.php:2706-2777`) checks `CAPABILITY` for `THREAD=ORDEREDSUBJECT` /
  `THREAD=REFERENCES` / `THREAD=REFS` and sends the real `THREAD`/`UID THREAD` command when
  supported.
- If the server lacks the capability: for `ORDEREDSUBJECT` specifically, Horde has a **client-side
  fallback** (`Socket/ClientSort.php:129-164`, subject-normalize + date grouping - not a real
  reference-chain, just RFC 5256's own weaker algorithm done locally). For `REFERENCES`/`REFS`
  without server support, it throws `Horde_Imap_Client_Exception_NoSupportExtension` - **no
  fallback**, by design of the vendored library.
- Capability detection already has an established idiom to mirror:
  `Api\Mail\Imap::hasCapability()`/`supportsCapability()` (`Imap.php:952-1005`, `:1371-1374`),
  used today for e.g. `SUPPORTS_KEYWORDS` throughout `mail_ui.inc.php` (`:628`, `:1414`, etc). A new
  check (e.g. `hasCapability('THREAD=REFERENCES')` or `queryCapability('THREAD')` for the full
  algorithm list) follows the exact same pattern - no new mechanism needed.

Gap: `JmapShim.php` (the thing that makes a plain IMAP account speak JMAP to the client) currently
fakes `threadId = uid` in `Email/import`'s response only (`JmapShim.php:1552`) and implements
none of `collapseThreads`, `Thread/get`, or `Thread/changes`. This tier's real work is:
- `JmapShim::emailQuery()`/wherever it answers `Email/query`: when `collapseThreads` is requested,
  call `$icServer->thread(['criteria' => Horde_Imap_Client::THREAD_REFERENCES, ...])` (with
  whatever search/sort the query already specifies) instead of a plain search, derive a **stable
  synthetic threadId** per Horde thread-group (e.g. the lowest UID in the group, prefixed), and
  return one representative message per group, windowed the same way (see perf note below).
- Add a `Thread/get` emulation: given a synthetic threadId, look up the cached Horde thread
  structure and return `{id, emailIds: [uids in that group]}`.
- **Pagination/performance risk, flagged, not yet resolved**: unlike JMAP's `collapseThreads` (a
  server-side, presumably-indexed operation on Stalwart), IMAP `THREAD` returns the *entire*
  thread structure for the searched mailbox in one shot - there's no IMAP-level position/limit
  windowing over thread groups. For a large mailbox this means `JmapShim` must compute the full
  thread map, sort it into collapsed representative order, and apply `position`/`limit` windowing
  itself in PHP. This needs a caching strategy (e.g. keyed on mailbox + `UIDVALIDITY` +
  `HIGHESTMODSEQ` if `CONDSTORE` is available, invalidated on new mail/expunge) to avoid re-running
  `THREAD` on every page turn - not designed yet, needs its own spike before implementation.

### Tier 3: no thread support at all - feature disabled

If an account has neither real JMAP thread support nor any IMAP `THREAD=*` capability (and we
decide not to rely on Horde's weak `ORDEREDSUBJECT` client-side fallback - see Open Questions), the
"Group by thread" toggle must not be offered. This follows the established bootstrap-flag pattern
in `mail/src/Ui/ProfileHandler.php::jmapBootstrap()` (already sends `isLocal`, `enableWsPush` flags
consumed client-side in `jmap.ts`, e.g. around `jmap.ts:36-41`, `:290-297`) - add a
`bootstrap['supportsThreading']` (or a tri-state: `'full' | 'basic' | false`, see Open Questions)
computed once per account at bootstrap time, and gate the toggle's visibility in the mail list
template (`mail/templates/default/index.xet`) the same way other content-driven show/hide works
elsewhere in this app (`disabled="!@fieldname"`, e.g. `compose.xet:93`).

## Unseen (and other) rollup onto the collapsed thread row

No framework help exists for this (confirmed above), so it's ordinary application logic:
- Compute it once, server-round-trip-side, when building each thread row (`emails2threadRow()` in
  Tier 1; the `JmapShim` equivalent in Tier 2) - fold `keywords` across every member id and set the
  row's `class` string as if it were a single message, reusing exactly the same class vocabulary
  `email2row()` already produces (`unseen`, `flagged`, `replied`, `forwarded`, label/customFlag
  classes - `jmap.ts:3170-3227`) so **zero new CSS** is needed
  (`mail/templates/default/app.css:73-113`, `index.xet:124/185`'s `class="$row_cont[class]"`
  binding).
- MVP rollup set: `unseen` (the explicit ask), plus trivially-available-for-free `flagged` (any
  member flagged) and a message count badge ("(3)"). Treat "any member forwarded/replied" as a
  stretch goal, not required for v1 - lower value, and replied/forwarded rollup is a little more
  ambiguous UX-wise (does it mean "the whole thread was replied to" or "the last message was"?).
- Live updates: today's push handling (`app.ts:633-738`, `push()`) reacts to `Flags`/`FlagsSet`/
  `FlagsClear` events with an in-place row refresh keyed by message id
  (`nm.refresh(pushData.id, Et2DatagridUpdateTypes.UPDATE_IN_PLACE)`, `app.ts:717-736`). With
  threading on, a flag change on one *member* message needs to refresh its *thread's* row, not a
  (now nonexistent, since it's collapsed) row keyed by that message's own id. This needs a lookup
  from message id -> currently-displayed thread row id (maintained client-side while a threaded
  list is on screen) so the push handler can redirect the refresh target. Also: if Stalwart's push
  really does emit `Thread` state-change entries (per the `Imap/Jmap.php:758-760` comment),
  `Api\Mail\Jmap::getChanges()` should eventually process those directly instead of relying purely
  on redirected `Email` events - worth revisiting once Tier 1's live-Stalwart verification (Open
  Questions) confirms what's actually emitted in practice.

## Bulk actions on collapsed thread rows

Real design question, not yet resolved: today's selection/bulk-action code (move, delete, flag,
mark-read - `mail_ui.inc.php`'s action definitions, `jmap.ts`'s `queryAllIds()`
(`jmap.ts:2878`, used for select-all) and `emailSet()`/delete calls) all operate on message ids
directly. When a thread row is selected in its **collapsed** form and the user invokes e.g.
"mark read" or "move to folder", there are two defensible behaviors:
1. **Gmail-style**: the action applies to every message in the thread (expand the thread row's
   `row_id` into its full `emailIds` set before executing the action).
2. **Literal**: the action applies only to the representative message actually shown (surprising
   for delete/move - a user would reasonably expect a bulk-selected thread to fully leave the
   folder).

Recommendation: (1), matching mainstream conversation-view mail clients, but this needs an explicit
decision before implementation, since it touches every existing bulk-action code path that accepts
a row-id list (`mail_ui.inc.php` action definitions; `jmap.ts`'s selection-based methods) - each
call site needs an "expand thread row ids to member message ids" step. This is the single largest
piece of cross-cutting work in the whole feature, larger than the row-fetching/aggregation logic
itself, and should get its own design pass once the basic threaded list is working.

## Sorting

`collapseThreads` composes with the existing `Email/query` `sort` argument unchanged per RFC 8621
(collapsing happens after the full sort, each thread's list position is that of the first
not-yet-seen Email in sorted order) - no UI/sort-control changes needed for Tier 1. Tier 2's
IMAP-`THREAD`-based emulation will need to replicate this "position by most-relevant member"
ordering manually when building its representative-row list, since IMAP `THREAD`'s own output
order is thread-structural, not date-sorted.

## Preference / toggle placement

A per-list toggle (not a hidden preference) in the same family as the existing "sneak preview"
filter toggle (`filter2` in `jmap.ts`'s row-fetch query building) - visible in the mail list's
filter row, persisted as a user preference, hidden entirely (not just disabled) when
`bootstrap.supportsThreading` is false/absent. MVP scope: one global toggle per account (affects
every folder equally). A per-folder override (e.g. force-off in Sent/Drafts, where "conversation"
grouping is less useful since every row is authored by the user) is a plausible v2 refinement, not
required for v1 - flagging so it isn't assumed out of scope permanently.

## Decisions (ralf, 2026-08-22)

1. **Horde's `ORDEREDSUBJECT` fallback: ignored entirely.** Tier 2 (IMAP `THREAD`) will only ever
   be considered "supported" for real `THREAD=REFERENCES`/`THREAD=REFS` server capability. An
   IMAP/JmapShim account with neither that nor JMAP thread support is treated exactly like "no
   thread support" - toggle hidden. Rationale (ralf): the fallback's grouping quality is poor
   (subject+date only) and its performance is probably *worse* than a real per-server `THREAD`
   command, so there's no scenario where reaching for it is the right tradeoff.
2. **Phase 1 = JMAP/Stalwart only.** Any non-JMAP-native account (i.e. anything going through
   `JmapShim`) is treated as "threading not supported" for the whole first phase, regardless of
   its actual IMAP `THREAD` capability - not just deferred as a follow-up detail, but explicitly
   out of scope for phase 1's capability check. `JmapShim`/IMAP `THREAD` becomes phase 2, see
   below.
3. **Bulk actions on a collapsed thread apply to every member message** (move/delete/flag/
   mark-read) - matches mainstream conversation-view clients, resolves the "biggest cross-cutting
   open question" above in favor of option (1).
4. **Whole-thread bulk actions always get the multi-select confirmation treatment**, even when
   only one thread row is selected - since a collapsed thread row expanding into N messages is,
   for safety purposes, indistinguishable from the user having explicitly multi-selected those N
   messages. Concretely: delete/move confirmation dialogs (and any other bulk-action confirmation)
   must count and warn about the *expanded* message count, not "1 row selected" - this needs
   checking against however `mail_ui.inc.php`/`jmap.ts` currently decide when to show a
   confirmation (likely a `count(selected) > 1` check today that would need to become "count(
   expanded message ids) > 1" instead).
5. **Global vs per-folder toggle**: still open, low-stakes, decide at implementation time -
   leaning toward global-only for v1 per the earlier reasoning above (Sent/Drafts grouping being
   less useful is a minor UX nit, not worth a design fork before v1 ships).

## Live Stalwart verification (2026-08-22) - confirmed, Phase 1's core mechanics work as designed

Verified directly against the real Stalwart server (profile/`acc_id=1`, the non-`isLocal` JMAP
profile per [[mail-jmap-modernization]]'s test-account notes), by driving the already-authenticated
`JamWebSocketClient` instance live in the browser (`window.app.mail.jmap.clients['1']`) rather than
standing up a separate PHP CLI session - no app code was touched, this was pure read-only JMAP
traffic through the existing session.

- **`Email/query` with `collapseThreads: true` works correctly and folds as expected**: an
  uncollapsed query returned 59 emails across 51 distinct `threadId`s (8 real 2-message thread
  groups found by grouping on `threadId`); the same query with `collapseThreads: true` reported
  `total: 51` - exactly `59 - 8`, confirming Stalwart is doing real per-thread collapsing, not a
  no-op.
- **`Thread/get` works correctly and matches independently-observed grouping**: calling
  `Thread/get` for three of the `threadId`s found above returned exactly
  `{id, emailIds: [...]}` pairs matching the two-message groups already seen from plain
  `Email/get`'s `threadId` field (e.g. thread `"gf"` -> `emailIds: ["yuaaaagf", "yuaaaagg"]`) - no
  `notFound` entries.
- A false alarm along the way, corrected same session: an early ad-hoc script chaining
  `Email/query` -> `Email/get` via `.requestMany(b => ...)` / `$ref()` appeared to return 59 emails
  instead of the expected 5. Root cause was a mistake in the throwaway script, not app code -
  the script wrote the result-reference key as `'#ids'` itself, but `JamWebSocketClient` already
  prepends the `#` when serializing a `$ref()` (`mail/js/jmap-jam-websocket.ts:301-302`), so the
  double-hashed key was silently ignored by Stalwart. Calling the real
  `MailJmap.getRows()` directly (`window.app.mail.jmap.getRows(...)`, which uses the correct
  `ids: ids.$ref('/ids')` form already) returned exactly the requested page size, and re-running
  the failing script with only that key fixed did too. **No bug exists in `getRows()` or
  `requestMany()`** - the planned `Email/query` -> `Email/get` -> `Thread/get` chaining for this
  feature can rely on the same mechanism with confidence.
- **Not verified**: whether Stalwart's push (`StateChange`) payloads actually include `Thread`
  entries in practice (only a code comment claimed this, `Imap/Jmap.php:758-760`) - didn't attempt
  to trigger a live state change to observe push traffic. Left as-is for Phase 3 (see phasing
  below); Phase 1/2 don't depend on it since flag-change pushes can be redirected without needing
  native `Thread` push events (see "Unseen (and other) rollup" section above).

## Suggested phasing

1. **Phase 1: Tier 1 only (Stalwart/real JMAP)**, `JmapShim`/plain-IMAP accounts hard-gated to
   "threading unsupported" for this whole phase (decision 2, above) - no conditional IMAP
   `THREAD`-capability check yet, just `bootstrap.supportsThreading = (backend is real JMAP)`.
   Includes the full bulk-action expansion (decisions 3+4) from the start, since ralf's answer
   makes bulk actions part of the core UX, not a deferred nice-to-have - "expand thread row ids to
   member message ids, then treat exactly like an equivalent multi-select" needs to land in the
   same phase as the row-building/aggregation/expand-collapse UI work, touching every existing
   bulk-action call site (`mail_ui.inc.php` action definitions, `jmap.ts`'s selection-based
   methods) plus whatever confirmation-count logic decision 4 identifies.
2. **Phase 2: `JmapShim` + real IMAP `THREAD=REFERENCES`/`THREAD=REFS`** - the windowing/caching
   spike (IMAP `THREAD` has no native position/limit pagination, unlike JMAP's `collapseThreads`),
   then wiring `JmapShim`'s `Email/query`/`Thread/get` emulation on top of Horde's already-present
   `thread()` support. `THREAD=ORDEREDSUBJECT`-only servers stay permanently unsupported (decision
   1), so this phase's capability check is specifically for `THREAD=REFERENCES`/`THREAD=REFS`.
3. **Phase 3: live-update/push refinement** - redirect flag-change pushes to the right thread row;
   consume real `Thread` push events if the live Stalwart verification confirms they're emitted
   usefully.

## Testing

Mirror the pattern already established for [[mail-folder-tree-jmap]]/[[mail-jmap-modernization]]:
PHP tests for any new `JmapShim` thread emulation (mocking `Mail\Imap`, following
`mail/tests/JmapShimMailboxGetTest.php`'s shape), TS tests for the new row-building function
(`mail/js/test/`, following the existing `email2row()`-adjacent test coverage), and a manual
live-Stalwart verification pass before calling Tier 1 done - the same "live-verified against
Stalwart" step used for the JMAP ACL work.
