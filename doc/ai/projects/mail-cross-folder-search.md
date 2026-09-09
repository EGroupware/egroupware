# Mail: cross-folder ("all folders") search

## Status: UI implemented (2026-09-09), two live-tested bugs found+fixed same day,
REST API exposure deferred as a second step

Follow-up to [[mail-rest-jmap-lite]], which deferred "search across all folders" as a Phase 2 item.
ralf wanted this scoped for the interactive mail UI first ("thought more for the UI then the REST
API"); REST exposure (either a query param on `.../folders/<folderId>/emails` or a new all-folders
endpoint) is an explicit second step, not done here.

## Research findings (live-tested 2026-09-09)

**Real JMAP (Stalwart) genuinely supports it, for free.** `Email/query` with NO `inMailbox` condition
at all was live-tested against acc_id=1 (real account, browser console, `window.app.mail.jmap.clients['1']`)
via `client.requestMany((t) => ({result: t.Email.query({accountId, filter: {text: 'a'}, limit: 10,
calculateTotal: true})}))` - it returned 9 matches; a follow-up `Email/get` on those ids confirmed
`mailboxIds` genuinely spanning 4 different real mailboxes (`a`, `b`, `d`, `e`). No server-side
JMAP work needed at all for the Stalwart side - just omit `inMailbox` from the filter.

**The plain-IMAP/JmapShim backend does NOT support it, and this project deliberately does not build a
fallback.** IMAP's `SEARCH` command is single-mailbox only; the one extension that could change that
(RFC 7377 `MULTISEARCH`) is (a) not advertised by the Dovecot test account checked live (its real
`CAPABILITY` response has `ESEARCH`/`ESORT`/`SEARCHRES`/`CONTEXT=SEARCH` - all single-mailbox
extensions - but no `MULTISEARCH`), and (b) not actually implemented in this codebase's vendored Horde
IMAP client library even if a server did advertise it (`vendor/egroupware/imap-client/lib/Horde/Imap/
Client/Socket.php`'s own docblock mentions RFC 7377 only aspirationally/copied from upstream Horde -
`search()` has no multi-mailbox form anywhere in its actual implementation). Per ralf: "if not
supported by the mail-server, we should not offer it, the performance is most likely horrible" - a
naive PHP-side "loop over every folder, one IMAP SEARCH per folder" fallback would repeat exactly the
"eager scan of hundreds of folders" anti-pattern [[mail-folder-tree-jmap]] already explicitly rejected
once for the folder tree, for a much slower/wider search operation. **Not pursuing a Horde-library-level
MULTISEARCH implementation now** either - most Dovecot installs don't enable that plugin; revisit only
if a real account's server is found to actually advertise it.

## UI design

**New search-type**: `'all'` added to `Api\Mail\Ui::$searchTypes` (`mail/src/Ui.php`), alongside the
existing subject/from/to/body/text/etc. options - a single-select dropdown entry, not an orthogonal
toggle. Its text-matching semantics mirror quicksearch (tokenized subject/from/to), just without the
`inMailbox` folder constraint.

**Capability gating - server-side, not client-side.** `Ui::supportsAllFoldersSearch(): bool` (`$this->
mail_bo->icServer instanceof ImapJmap`) - the exact same real-JMAP-vs-shim check already used
elsewhere in this file (`supportsKeywords()`). The `'all'` entry is `unset()` from `$searchTypes`
before it ever reaches the client (both at initial page render and in `ajax_refreshFilters()`, the
per-profile-switch refresh) whenever the active account isn't real-JMAP - so a shim-backed account's
search-type dropdown never even shows the option, no client-side hide/disable logic needed. `getRows()`
(`mail/js/jmap.ts`) still double-checks `token.isLocal` defensively (a stale cached dropdown after a
profile switch without a full page reload could otherwise slip through) and just returns an empty
result rather than attempting anything.

**"No search text ⇒ empty results, never a full-account query"** - ralf: "if no search-pattern is
given, [do]n't try[] to query all mails". `getRows()` short-circuits to `{rows: [], total: 0}` before
issuing any JMAP call at all when `cat_id === 'all'` and the search box is empty - a real safety
guard, not just a UX nicety, since an "all folders, empty filter" `Email/query` would otherwise
(per RFC 8620's empty-filter-matches-everything semantics) mean "every message in the account".

**Row addressing, fixed properly rather than compromised.** `mailboxId` was previously a single
query-level value baked into every row's `row_id` (`account_id::profileID::mailboxId::email.id`) and
used once for a whole-query "is this a Sent/Drafts-like folder, so show the recipient not the sender"
role lookup - both assumptions break once a result set spans multiple real folders. Fixed (per ralf's
explicit choice, "fix it properly"):
- `Email/get`'s `properties` now additionally requests `mailboxIds` when searching all folders.
- Each row's *own* real mailbox (`Object.keys(email.mailboxIds)[0]`) is used for its `row_id` and for
  its own role lookup, instead of the single query-level `mailboxId` - so move/delete/flag-toggle and
  any row-id-addressed action keep working correctly on cross-folder results, the same as ordinary
  single-folder browsing.
- Role+display-name are resolved together, once per **distinct** mailbox actually present in a page of
  results (typically a handful), via a new small cached lookup (`mailboxRoleAndName()` /
  `mailboxRoleAndNameCache`) - kept deliberately separate from the existing `mailboxRole()`/
  `mailboxRoleCache` (used elsewhere for the single-folder case and thread-reply role detection) rather
  than widening that method's shape for its two existing, unrelated call sites.

**No folder column - the folder is prefixed onto the existing address string instead.** Per ralf: "an
other option would be to prefix the address with the folder e.g. 'Inbox: Ralf Becker
(egroupware.org)' / 'Subject' ... we don't need a 3rd row or extra column". `email2row()` gained an
optional `folderLabel` parameter (the row's own mailbox's JMAP `name`, e.g. "Inbox") - only the
single unified `address` column (the grid's "From" column, already `showRecipient`-aware) gets
`"<folderLabel>: "` prefixed onto it; `fromaddress`/`toaddress`/etc. stay exactly as before.

## FIXED (2026-09-09): stale "All folders" selection survived an account switch

Live-verified by ralf across his own multiple accounts (acc_id=1 Stalwart works nicely; switching to a
Dovecot/shim account showed nothing, silently). Root cause: the search-type ("All folders" included)
gating only ever ran at initial page load and inside `Ui::ajax_refreshFilters()` - but switching
accounts via the folder tree goes through a **different** entrypoint, `Ui::ajax_changeProfile()`
(`mail/src/Ui.php`), which never refreshed the client's `cat_id` dropdown options at all. So the
"All folders" selection (and its very presence in the list) simply carried over unchanged from
whichever account was previously active - `getRows()`'s own `token.isLocal` guard then silently
returned zero rows on the new (unsupporting) account, with no indication why.

Fixed by having `ajax_changeProfile()` also push the newly-active account's (correctly gated)
`$searchTypes` via `app.mail.refreshCatIdOptions()` - the exact same call `ajax_refreshFilters()`
already made. No new client-side logic needed: `refreshCatIdOptions()` (`mail/js/app.ts`) already
resets the dropdown to quicksearch whenever the current selection isn't present in the new options
list, which is exactly the "a profile switch should just fall back to quicksearch" behavior ralf
asked for.

Considered, and rejected for now: always offering "All folders" and surfacing an account-specific
error when unsupported instead of dynamically hiding it. Once the actual account-switch wiring gap is
fixed, the dynamic-hide approach gives a strictly better experience (a user switching often between a
Stalwart and a Dovecot account never sees an option that can't work, rather than hitting a recurring
error) for the same amount of code - the error-based approach isn't needed unless a real remaining gap
in the dynamic-hide wiring turns up later.

**Verified live end-to-end** (browser console + real clicks against boulder.egroupware.org, temporary
`console.log` debug output confirming the server side, removed again after): repeated account-switch
cycles (1 ↔ 42) each correctly computed `supportsAllFoldersSearch()` and sent the right `$searchTypes`
list every single time - the server side has no remaining bug. Two things worth knowing when
re-verifying this by hand, both cosmetic/timing, not correctness bugs:
- `Et2Select`'s own `getRenderableOptions()` (`api/js/etemplate/Et2Select/Et2Select.ts`) deliberately
  renders only the *currently selected* option into the DOM until the dropdown has actually been
  *opened* once (`_optionsActivated`) - a pre-existing performance optimization, not something this
  feature touches. Inspecting `cat_id`'s rendered `<sl-option>` DOM (e.g. via devtools) before ever
  opening it will show just one option even though the underlying `select_options` data is already
  fully correct - only actually clicking the dropdown open (like a real user) proves what's really
  there.
- The `ajax_changeProfile()` round trip (network + the full response's other actions, e.g. grid
  actions) takes a brief moment; checking `cat_id`'s state immediately/synchronously after firing the
  switch can catch it mid-flight. A short pause (as any real user clicking through the UI naturally
  has) is enough for it to settle correctly.

**Confirmed correct in ralf's own real environment too (2026-09-09), same day**, via a second,
file-based debug pass (`file_put_contents()` into `/tmp/mail-changeprofile-debug.log` inside the
`egroupware` container, since `error_log()` goes nowhere reachable here - `catch_workers_output` is
off in this dev container's php-fpm pool, so worker stderr is discarded; removed again after). ralf's
own repro (start on the Dovecot account, reload the page, switch to the Stalwart account) showed the
exact same result as the scripted test above: `supportsAllFoldersSearch()` computed `1` correctly, and
a direct console check of `cat_id.select_options` on his real browser confirmed `"all"` was genuinely
present in the data. What made it LOOK broken: switching to the Stalwart account took **~20 seconds**
server-side in his environment - well past how long anyone would naturally wait before checking, and
apparently long enough that the dropdown's pending Lit re-render hadn't visibly flushed yet even after
it was in fact scheduled; opening devtools (to inspect `select_options`) seems to have nudged it into
actually painting. Opening the dropdown again immediately after that showed "Alle Ordner" correctly.
**This session's search-type fix itself is correct and needed no further change** - but the ~20s
latency turned out to be a real, separate, now-fixed bug in its own right, see next section (ralf
correctly diagnosed the mechanism himself from the browser's network console before this was
confirmed in code).

## FIXED (2026-09-09), same day: ~20s account-switch stall to Stalwart (unrelated to search, found via
testing this feature)

ralf's own diagnosis, verbatim, from the browser network console: "that's for sure trying to open an
IMAP connection and failing for stalwart with protocol/port JMAP/443" - also noting the Dovecot/shim
switch itself cost ~2.5s and "should NOT / does NOT need do anything with the IMAP connection".
Confirmed exactly right, with the precise mechanism: `Api\Mail::storeActiveProfileIDToPref()`
(`api/src/Mail.php`) - called from `mail_ui::changeProfile()` with `$_testConnection` hardcoded
`true` - had **no** `instanceof Mail\Imap\Jmap` guard at all, unlike every sibling method in this
file (`openConnection()` right above it already had one, added 2026-08-24 for the identical
underlying symptom - see [[project_jmap_imap_fallthrough_cleanup]], 14th site now). Its unconditional
`getCurrentMailbox()` call falls through `mailboxExist()` -> `openMailbox()` -> Horde's real raw
socket `_connect()`, which for a JMAP(S) account tries to speak IMAP protocol against
`acc_imap_host:443` and hangs waiting for an IMAP greeting banner that an HTTPS/JMAP endpoint never
sends, until the ~20s connection read-timeout. Fixed with the same guard shape as the other 13 sites
in that file; also removed a stale `file_put_contents()` TEMPORARY diagnostic inside
`openConnection()`'s own (already-working) guard, dated 2026-08-24 and marked "remove once confirmed"
- it had been, repeatedly, including by this exact investigation finding the 14th sibling site.

**Live-verified**: switching TO the Stalwart account went from ~20000ms to ~91ms; switching TO the
Dovecot/shim account also dropped from ~2.5s to ~0.9s (that account's own residual cost is legitimate
real IMAP round-trips already inside `openConnection()` itself for a classic account -
`examineMailbox()`/`getHierarchyDelimiter()`/`getSpecialUseFolders()`'s real `LIST` - not a
fallthrough bug, not touched by this fix).

## Not yet verified live

- The actual "All folders" search-type end-to-end in the browser (dropdown appears only for
  Stalwart accounts, empty-search guard, results genuinely spanning folders with the folder-prefixed
  address, and - importantly - that a row action like flag/move/delete on a cross-folder result
  correctly targets that row's own real folder, not whichever folder happened to be selected in the
  tree). Unit/type-level checks (`tsc --noEmit`, the full `mail/js/test/MailJmap*.test.ts` suite -
  2294/2294 passing on Chromium, 1 unrelated pre-existing Firefox-only `Et2Datagrid` failure) all
  pass, but nothing here has been clicked through in a real browser yet.
- Whether `Mailbox/query filter:{parentId:null}`-style semantics matter here at all - they don't;
  this feature never touches `Mailbox/query`, only `Email/query`'s `inMailbox` omission, which is
  the specific behavior that WAS live-verified above.

## Deferred to the REST API step (not started)

How `doc/ai/projects/mail-rest-jmap-lite.md`'s `GET .../folders/<folderId>/emails` endpoint (or a new
all-folders endpoint) should expose this - same Stalwart-only constraint applies, same "no search text
⇒ empty/rejected" guard likely applies. Not designed yet.

## Related

[[mail-rest-jmap-lite]], [[mail-jmap-modernization]], [[mail-folder-tree-jmap]], [[mail-threaded-view]]
(the other place `mailboxId`-per-row vs. one-shared-mailboxId came up)
