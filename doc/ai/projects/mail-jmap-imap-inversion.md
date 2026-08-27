# Mail: invert `Mail\Imap`/`Mail\Jmap` - JMAP-shaped base, IMAP as an adapter

## Status: Phase 1 committed+pushed 2026-08-26 (`d3545c57f8`). Phase 2 scoping started, no code yet.

## Phase 2 scoping: both obvious "quick win" candidates are dead ends (2026-08-26)

Before writing any Phase 2 code, screened the two highest-call-volume unmigrated methods
(`getCurrentMailbox()` 19 calls, `getMailboxes()` 12 calls) as candidate first slices. Neither
holds up:

- **`getCurrentMailbox()`/`getMailboxes()` have zero external callers.** Every call site is inside
  `api/src/Mail.php` itself or `api/src/Mail/Imap.php`/`Imap/Cyrus.php` (admin-provisioning, already
  out of scope). Migrating them would only swap internal plumbing inside `Mail.php` - it removes no
  UI-facing dependency on `Api\Mail`, which is the entire point of the decided "direct call-site
  rewiring" mechanism. Not a real slice.
- **The methods that *do* have external callers sit one layer up** - `getFolderStatus()`/
  `getMailBoxesRecursive()`/`getFolderArrays()`, called from `mail/src/Ui/FolderHandler.php`. But
  `FolderHandler.php`'s own docblock says these are **classic-fallback-only paths**: the folder-tree
  JMAP migration ([[project_mail_folder_tree_jmap]]) already made browsing/create/rename/move/
  delete/subscribe JMAP-first client-side for *both* backends (real-JMAP over HTTP and the
  IMAP-shim over `mail/jmap.php`) - these PHP methods only run when JMAP isn't reachable at all.
  Worse, the actual logic here is exactly the "hardest to map" territory this doc already flagged -
  delimiter-based path splitting, `_getNameSpaces()`/`getFolderPrefixFromNamespace()` namespace
  handling, recursive subfolder subscribe-cascades on rename/move/delete - real IMAP-hierarchy work,
  not a mechanical restructuring, on a low-traffic fallback path. Low ROI for a first slice.

**Where real remaining server-side PHP traffic actually lives**, now that folder-tree browsing AND
message-list browsing are both JMAP-first client-side for every backend: `mail_compose`
(attach/send), `Storage/Merge.php` (mail-merge send), `mail_zpush.inc.php` (ActiveSync),
`mail/src/ApiHandler.php` (REST API), `mail_integration.inc.php` (tracker/infolog/calendar
mail-linking, [[project_tracker_mail_dependency]]), `mail_hooks.inc.php`. Screened all 6
(2026-08-26/27):

- **`mail_zpush.inc.php`** (6 hits, all `icServer->ImapServerId` property reads, no method calls) and
  **`ApiHandler.php`** (zero `mail_bo`/`icServer` calls at all - `Mail::getInstance()` only feeds
  `Api\Mailer` for sending) and **`mail_hooks.inc.php`** (1 property-read site) are not real
  consumers of the raw-IMAP call sites - out of scope, nothing to migrate.
- **`mail_compose.inc.php`** (~24 sites, every compose exercises this - highest real traffic),
  **`mail_integration.inc.php`** (~13 sites, tracker/infolog/calendar linking), and
  **`Storage/Merge.php`** (7 sites, smallest/most contained) are all real, but **every one of them
  is dominated by message-body/header/attachment fetch calls** (`getMessageRawBody`/
  `getMessageHeader`/`getMessageBody`/`getAttachment`/`getMessageAttachments`) - exactly `fetch()`'s
  IMAP linear part-ID addressing vs JMAP's structured body-parts tree, already named above as the
  single hardest unsolved semantic-mapping gap. None of them reduce mainly to Mailbox querying (the
  thing Phase 1 actually solved) the way `FolderHandler.php` did.

**Conclusion: Phase 1's low-hanging fruit is genuinely exhausted.** There is no remaining consumer
where "just wire up already-built Phase 1 code" works - every real candidate needs the
message-get/fetch JMAP mapping designed first (JMAP `Email/get` with `bodyProperties`/
`bodyValues`/`bodyStructure` vs IMAP `fetch()`'s `$_partID`/`BODY[...]` addressing) as shared infra,
not restructuring. `Storage/Merge.php` (smallest, most contained, only 2 of its 7 sites are
fetch-shaped) would be the cheapest place to prove that mapping out once designed, before touching
`mail_compose`/`mail_integration`. **Not started - this is a real design problem needing its own
scoping session, not a continuation of Phase 1's "already-JMAP-native, zero new semantics"
restructuring approach.** Paused here pending ralf's input.

## Phase 1 summary

`Api\Jmap`/`Api\Jmap\Type`/`Api\Jmap\Http` (generic) + `Api\Mail\Jmap\{Http,Mailbox,Email,Thread,
Quota}` (real-JMAP, absorbing the old `api/src/Mail/Jmap.php` client, now deleted) +
`Api\Mail\Jmap\Imap` + `Imap\{Mailbox,Email,Thread,Quota}` (promoted from `mail/src/JmapShim.php`
via `git mv`, logic preserved near-verbatim) all exist and are wired up. `Imap\Jmap`/`Imap\Stalwart`/
`Smtp\Stalwart` construct the new `Api\Mail\Jmap\Http` instead of the old client. All real call
sites across `api/src/Mail.php`, `Imap/Jmap.php`, `Smtp/Stalwart.php`, `mail_ui.inc.php`,
`MessageDisplayHandler.php`, `AttachmentJmap.php` keep working via backward-compatible passthrough
methods (`emailGet`/`emailQuery`/`getMailboxId`/`getQuota`/etc kept flat on the session, not just
nested under `->email`/`->mailbox`) - see "Two real bugs/gaps found during implementation" below.
Verified against the existing test suite (219/230 relevant tests pass; all 11 failures confirmed
pre-existing/environment-only, none touch changed code - see that section).

## Two real bugs/gaps found during implementation, not anticipated in the design

1. **`Api\Jmap\Type`'s generic `get`/`query`/`set` omitted the required RFC 8620 §5.1 `accountId`
   parameter** - every real-JMAP call through the generic default path would have been malformed
   against Stalwart. Fixed before any call site depended on it.
2. **Nesting convenience methods under `->email`/`->mailbox`/`->quota` broke ~20 real existing call
   sites** that call `jmapClient()->emailGet(...)`/`getMailboxId(...)`/`getQuota()`/etc directly on
   the session object. Rather than rewrite every call site (`api/src/Mail.php`, `Imap/Jmap.php`,
   `mail/src/Ui/AttachmentJmap.php`, ...), added thin passthrough methods on `Api\Mail\Jmap\Http`
   delegating to the per-type objects - preserves the old flat API surface with zero call-site
   changes, while the per-type objects remain the "real"/architecturally-correct interface for new
   code. Also found and added 2 missed generic utility statics (`filterConditions()`, `boolPatch()`,
   used by `Smtp\Stalwart` for non-mail Group/Individual JMAP filters) to `Api\Jmap` itself.

## Deliberate simplification: `JmapShim`'s logic preserved near-verbatim, not deeply restructured

`Api\Mail\Jmap\Imap` (the promoted `JmapShim`) keeps ~95% of its ~55 methods as unchanged static
methods (`mailboxGet`/`mailboxQuery`/`mailboxSet`/`emailGet`/`emailQuery`/`emailSet`/`emailImport`/
`threadGet`/`quotaGet`/every MIME/S-MIME/TNEF/address-parsing helper) - a real, deliberate
risk-management call given this code's size, intricacy (real admin-impersonation security boundary
via `$calledFor`, deep Horde MIME handling) and existing test coverage. Only a thin new instance
layer was added: a constructor holding `$accountId`/`$calledFor`, `$types` (satisfying `Api\Jmap`'s
lazy-accessor contract), and 4 per-type classes (`Imap\Mailbox`/`Email`/`Thread`/`Quota`) that are
thin adapters translating `Type`'s generic contract into calls on the (unchanged) static methods.
`dispatch()` and `mail/jmap.php` (the live browser-facing endpoint) are completely untouched and
still work exactly as before. Also corrected during implementation: `imapServer()` already memoized
its connection via a `static` per-request cache - the "connection re-fetched per call" framing in
this doc's earlier draft was inaccurate; the real payoff here is architectural (reachable from
server-side PHP via the new class hierarchy, satisfies the `Type` contract), not a performance fix.

## What Phase 1 deliberately did NOT do

Per the decided scope: `getCurrentMailbox`/`getMailboxes`/`fetch`/`search`/`openMailbox`/etc (the
~16 unguarded raw-IMAP methods `api/src/Mail.php` still calls directly on `$icServer`) are
untouched - still real IMAP, still called the old way. `Api\Mail`'s own ~230 `icServer->` call
sites are NOT migrated - this phase only promoted code that was already JMAP-native into the new
object model. That's the next, much larger phase, not started.

Continuation of [[mail-jmap-modernization]] and [[project_jmap_imap_fallthrough_cleanup]]. Those
found the same underlying problem from two angles - `Api\Mail\Imap\Jmap extends Imap` inherits a
real raw-socket IMAP client's methods unguarded (6 confirmed hangs/bugs so far), and `Api\Mail`
(`mail_bo`) is composed with `$icServer` and calls raw Horde primitives on it directly, with its own
separate JMAP-native short-circuit (`jmap<Method>()` gated by `instanceof Mail\Imap\Jmap`) bolted on
per-method. `mail-jmap-modernization.md` already scoped a narrower fix - decouple `Imap\Jmap` from
`Imap` via composition + `__call()` proxy - and concluded it's "a real future project, but
multi-session and foundational", not started. Ralf's proposal here is a level up from that: instead
of just decoupling the `Jmap` subclass from `Imap`, make **`Api\Mail` itself** depend only on a
JMAP-shaped interface, regardless of backend, so `Mail\Imap`'s whole `Horde_Imap_Client_Socket`
inheritance chain can eventually go away.

## The proposal

- A `Mail\Jmap` **abstract session** class holds account/auth context and lazily exposes one object
  per JMAP data type: `$jmap->mailbox`, `$jmap->email`, `$jmap->thread`, `$jmap->quota`, etc.
- Those per-type accessors return instances of per-type **abstract** classes (`Mail\Jmap\Mailbox`,
  `Mail\Jmap\Email`, `Mail\Jmap\Thread`, `Mail\Jmap\Quota`, ...) declaring RFC 8620 §5's standard
  method contracts - `get(ids, properties)`, `query(filter, sort)`, `set(create, update, destroy)`,
  `queryChanges()`/`changes()` where applicable. Unlike a generic dispatcher, **each backend
  implements its own concrete subclass with real, direct backend-specific code** - the shared part
  across backends is the method *contract* (a consistent, typed API `Api\Mail` codes against), not
  a shared implementation.
- Two concrete sessions, each composing (not inheriting) its own transport:
  - `Mail\Jmap\Http extends Mail\Jmap` (real JMAP, Stalwart) - composes today's
    `api/src/Mail/Jmap.php` HTTP client (`RestClientTrait`-based, session bootstrap, OAuth token
    handling already working). `Mail\Jmap\Http\Mailbox`/`Email`/etc. implement `get`/`query`/`set`
    by calling its `jmapCall()` (already does batched RFC 8620 §3.3 method-call execution). Its
    existing convenience methods (`emailGet()`, `emailQuery()`, ...) become these classes' method
    bodies, not a separate parallel API.
  - `Mail\Jmap\Imap extends Mail\Jmap` (plain IMAP, no real JMAP server) - **holds the open real IMAP
    connection itself** (composes a `Horde_Imap_Client_Socket`/`Mail\Imap`-shaped connection object,
    obtained once per session rather than per-call) - the raw IMAP client doesn't disappear from the
    codebase, it just stops being something everything else inherits from and becomes purely an
    implementation detail of this one adapter. `Mail\Jmap\Imap\Mailbox`/`Email`/etc. implement
    `get`/`query`/`set` with direct IMAP calls against that held connection - **exactly what
    `EGroupware\Mail\JmapShim`'s functions already do** (`mail/src/JmapShim.php`, 2768 lines,
    `mailboxGet()`/`mailboxQuery()`/`mailboxSet()`/`emailGet()`/`emailQuery()`/`emailSet()`/
    `emailImport()`/`threadGet()`/`quotaGet()` already cover this exact method surface, dispatched
    by RFC method name against a raw `Horde_Imap_Client_Socket` passed in as a parameter each call).
    The refactor is mechanical: promote each static function to an instance method on the matching
    per-type class, replacing the passed-in `Horde_Imap_Client_Socket $imap` parameter with the
    owning session's one held connection - a real consolidation (one connection per session instead
    of fetched fresh via `Account::read()->imapServer()` on every call), not just a rename. Existing
    tests (`JmapShimMailboxGetTest.php`/`JmapShimMailboxSetTest.php` pattern) carry over.
- **Migration mechanism, decided (2026-08-26): direct call-site rewiring, not a facade.** `Api\Mail`
  does **not** grow a `$this->jmap` property that its own existing methods start using internally
  (that would just be emulating the old `Api\Mail` interface on top of the new classes - ralf's own
  words: "not by emulating Api\Mail on top of the new Api\Jmap class"). Instead, UI-facing mail-app
  code (`mail_ui.inc.php`, `mail/src/Ui/*.php` handlers) gets its own reference to the new session
  object directly (most likely vended by `Mail\Account`, the same place `imapServer()` already vends
  connections today) and calls `$this->jmap->mailbox->query(...)`/`$this->jmap->email->get(...)`
  straight from the call site, replacing whatever `$this->mail_bo->someMethod()` call was there
  before. `Api\Mail`'s old method stays exactly as-is (still IMAP-only) until its **last** caller has
  migrated, then gets deleted outright - never adapted/wrapped. This gives a clean, one-directional
  path off `Api\Mail` at each call site, and (see "Migration mechanics" below) means the 113/74
  property-access risk never needs a compatibility shim at all - it only exists inside old `Api\Mail`
  methods that stay untouched until deletion.

This also finally gives `mail/jmap.php` (the browser-facing HTTP endpoint, today calling
`JmapShim::dispatch()` directly, bypassing `mail_ui`/`Api\Mail` entirely per `JmapShim`'s own
docblock) and server-side PHP mail code **one single implementation** of "JMAP over IMAP" instead
of two independent surfaces that happen to overlap. `mail/jmap.php` would become a thin router onto
the same `Mail\Jmap\Imap` session class server-side code uses directly.

## Longer-term: not just a mail-internal cleanup

Ralf's framing (2026-08-26): a clean session + lazy per-type-object model with a shared
`get`/`query`/`set` contract isn't inherently mail-specific - the same pattern is exactly what JMAP's
own extension mechanism uses for other data types (JMAP for Calendars - `draft-ietf-jmap-calendars`,
building on JSCalendar/RFC 8984; JMAP for Contacts - `draft-ietf-jmap-contacts`, building on
JSContact/RFC 9553).

**Important correction (ralf, 2026-08-26):** the reusable value here is **not** "a generic outbound
JMAP HTTP client EGroupware could use to *consume* someone else's calendar/contacts JMAP server" -
given EGroupware's own structure (it owns/stores calendar and addressbook data itself, unlike mail
where Stalwart is an external server), there's no real use case for that. The reusable value is the
**server** side: the exact same session/per-type-object/`get`-`query`-`set` pattern `Mail\Jmap\Imap`
uses to expose plain-IMAP mail data as JMAP (mirroring `JmapShim`'s current role) is the template
for EGroupware exposing its **own** calendar/addressbook data as real JMAP types someday - i.e.
`mail/jmap.php`/`JmapShim`'s eventual role (a genuine multi-capability JMAP `Session` endpoint
reachable by any JMAP client, not only EGroupware's own JS) is the actual future payoff, not a
client-side one.

**Namespace placement, decided (2026-08-26): generic `Api\Jmap` base.** Mirrors RFC 8620's own
layering (Core, data-type-agnostic) vs RFC 8621 (Mail, a specific extension) rather than being
speculative future-proofing - `Api\Jmap`/`Api\Jmap\Type` (session + per-type `get`/`query`/`set`
contract) is the generic home, `Api\Mail\Jmap\Imap`/`Api\Mail\Jmap\Http` (or wherever the HTTP
transport ends up, see open question 2) are this project's concrete, mail-specific implementations.
Leaves room for future `Api\Calendar\Jmap\...`/`Api\Addressbook\Jmap\...` siblings (as JMAP *server*
adapters over EGroupware's own storage, not HTTP clients) without a rename.

This doc otherwise stays scoped to mail (that's the concrete, sized, ready-to-plan problem) -
Calendar/Addressbook JMAP support is a distinct future project once someone actually scopes it, not
a commitment made here.

## Phase 1 scope, decided (2026-08-26): restructure already-JMAP-native code, migrate zero new semantics

Ralf's call: not `mail_compose` ("2nd or 3rd phase, but nothing to start with"), not the "6 hottest
methods" (several of those are still genuinely unguarded, real IMAP-semantics work). **The low-hanging
fruit / actual starting point is code that already talks JMAP today**, just scattered across two
disconnected surfaces - restructure it into the new `Api\Jmap`/`Mail\Jmap` object model first, with
**zero new IMAP-to-JMAP semantic mapping**, before touching anything that's still genuinely raw IMAP.

Two existing, working, already-tested sources to promote:
- **`Imap\Jmap`'s own overridden methods** (real JMAP, Stalwart) - `store`/`copy` (Email set/move),
  `hasCapability`, `emailId2uidByPath`, `splitRowID`, `resolveSmimeJmap`/`resolveTnefJmap`,
  `getACL`/`setACL`/`deleteACL` (already has a working IMAP-rights translation to copy the pattern
  from), `enablePush`/`pushAvailable`, and `jmapClient()` itself (11 call sites today building JMAP
  requests inline after fetching the client - these become clean `$jmap->email->set(...)`-shaped
  calls instead). Becomes `Mail\Jmap\Http`'s per-type classes.
- **`JmapShim`'s existing functions** (JMAP-emulated over plain IMAP) - `mailboxQuery`/`mailboxGet`/
  `mailboxSet`/`emailQuery`/`emailGet`/`emailSet`/`emailImport`/`threadGet`/`quotaGet` already cover
  exactly this method surface, dispatched by RFC method name. Becomes `Mail\Jmap\Imap`'s per-type
  classes (see "The proposal" above for the mechanical promotion-to-instance-methods refactor).

This closes a real, currently-existing gap as a side effect: `JmapShim` is reachable **only** via the
browser HTTP endpoint (`mail/jmap.php`) today - server-side PHP mail code has no way to call it at
all, hence the "two independent surfaces that happen to overlap" problem noted above. After Phase 1,
both the browser and server-side PHP go through the same `Mail\Jmap\Imap` class.

**What Phase 1 deliberately does NOT touch:** `getCurrentMailbox`, `getMailboxes`, `fetch`, `search`,
`openMailbox`, and everything else in the "unguarded" list above stay exactly as they are - still
real IMAP, still called via `$icServer`/`Api\Mail`, still working. Those are real semantic-mapping
problems (see "Hardest methods" above) for later phases, deliberately deferred so Phase 1 stays a
pure, low-risk restructuring of code that's already proven correct.

## Why this is bigger than the already-scoped composition idea

The composition proposal in [[mail-jmap-modernization]] only decouples `Imap\Jmap` from `Imap` -
it doesn't touch `Api\Mail`'s own ~230 raw `icServer->` calls at all, so `Api\Mail` would still be
written against IMAP semantics either way. This proposal subsumes it: once `Api\Mail` itself is
rewritten against `Mail\Jmap`'s shape, `Api\Mail\Imap\Jmap`'s composition-vs-inheritance question
becomes moot for most call sites - what's left needing real IMAP shrinks to whatever
`Mail\Jmap\Imap` needs internally, and that's a single, well-tested, already-partially-built class
(`JmapShim`) instead of ~230 scattered call sites across `Api\Mail`.

## Sizing (full-repo research, 2026-08-26, 3 parallel forks)

**Total `icServer->` method-call sites found: ~230** (195 in `api/src/Mail.php`, ~35 in
`mail/src/Ui/*.php` + `mail_ui.inc.php`/`mail_tree.inc.php`), ~29-43 distinct methods (own +
inherited-Horde), plus **113 property-access sites** (not method calls - `ImapServerId`,
`acc_folder_*`, `acc_imap_*`, ... via `Mail\Imap`'s public properties/magic `__get()`) that would be
a serious blocker for a `__call()`-only *facade/proxy* approach (as found for the smaller-scoped
composition idea). **Not a blocker for the direct-call-site-migration approach decided above** - old
`Api\Mail` methods (and their embedded property accesses) are left untouched, not proxied, until
their last caller migrates away and the whole method gets deleted. Still worth knowing the number:
it's the honest size of "how much of `Api\Mail` still needs touching eventually."

**Concentration** - just 6 methods account for ~43% of `Mail.php`'s 195 calls:

| Method | Calls | JMAP-safe today? |
|---|---|---|
| `store()` | 20 | yes (`Imap\Jmap` overrides it) |
| `getCurrentMailbox()` | 19 | no - called from ~15 distinct methods, the single biggest reimplementation target ("what folder am I in" underlies almost everything) |
| `getMailboxes()` | 12 | no |
| `jmapClient()` | 11 | yes - already JMAP-native, confirms most real traffic already goes this way |
| `fetch()` | 9 | no - raw Horde, no wrapper at all |
| `openMailbox()` | 7 | mixed (2 inside the existing `openConnection()` guard, 4 outside it) |

Only 8 of ~29 distinct methods are genuinely JMAP-safe today (`store`, `copy`, `jmapClient`,
`hasCapability`, `emailId2uidByPath`, `splitRowID`, `resolveSmimeJmap`, `resolveTnefJmap`), but
because they're high-volume that's already ~28% of call *sites* by count - the unguarded remainder
(`getCurrentMailbox`, `getMailboxes`, `fetch`, `search`, `openMailbox`×4, `listSubscribedMailboxes`,
`subscribeMailbox` incl. `FolderHandler.php`'s 9 sites, `examineMailbox`×2, `getMailboxCounters`,
`expunge`, `renameMailbox`, `mailboxExist`, `listUnSubscribedMailboxes`, `getStorageQuotaRoot`,
`getStatus`, `getNameSpaceArray`/`getNameSpaces`, `getDelimiter`, `deleteMailbox`, `createMailbox`,
`append`, `queryCapability` in `mail_tree.inc.php`) is materially larger than the "6 known
fallthrough sites" framing suggested - those 6 guard specific *callers*, not the underlying methods,
which remain unguarded everywhere else they're called directly.

**Non-mail-app consumers of `Api\Mail`/`icServer`** (not audited line-by-line, but confirmed live and
real, so in scope for full coverage, not just the browse-grid UI which already bypasses this
server-side path via client-side JMAP): `api/src/Storage/Merge.php` (mail-merge send, see recent fix
`9f4d766bc6`), `mail/inc/class.mail_compose.inc.php`, `mail_hooks.inc.php`, `mail_integration.inc.php`
(shared by tracker/infolog/calendar mail-linking, see [[project_tracker_mail_dependency]]),
`mail_zpush.inc.php` (ActiveSync), `mail/src/ApiHandler.php` (REST API), `Ui/BodyHandler.php`.

**Hardest methods to map cleanly onto JMAP semantics** (no 1:1 translation, need real redesign not a
wrapper):
- `search()`/`getSortedList()`'s pipeline (`Mail.php` ~2960-3040) - raw IMAP SEARCH criteria vs
  JMAP's structured `Email/query` filter+sort object.
- `fetch()` + its 9 call sites - IMAP's linear MIME part-ID addressing (`$_partID`,
  `BODY[HEADER]`/`BODYSTRUCTURE`) vs JMAP's structured body-parts tree (`bodyProperties`,
  `fetchAllBodyValues`) - every `$_partID`-based call site needs rethinking, not a method-name swap.
- Namespace/mailbox-hierarchy handling (`getNameSpaces`/`getNameSpaceArray`) - already solved
  client-side in TypeScript ([[project_mail_folder_tree_jmap]]), not reusable server-side PHP.
- Quota (`getStorageQuotaRoot`) - IMAP QUOTA extension vs JMAP's `quotas` capability, different shape.
- ACL already has a working bridge to copy the *pattern* from (`Jmap.php`'s
  `jmapRightsToImap`/`imapRightsToJmap`, see [[project_mail_jmap_acl_plan]]) - good template for how
  each IMAP-semantic method gets its own translation designed individually rather than assuming a
  generic wrapper works.

**Special cases needing an explicit decision, not folded into the generic design:**
- **Sieve** (`getExtensions`/`setRules`/`getVacation`/etc., 11 methods, all proxied via `__call()`
  to `Sieve\Logic` today) - no JMAP equivalent exists anywhere (JMAP has no Sieve/filter-rules
  extension this codebase uses). Needs to stay a separate, explicitly-IMAP-only interface, composed
  alongside `Mail\Jmap` rather than folded into it.
- **Admin-provisioning subclasses** (`Imap\Cyrus`, `Imap\Dovecot`, `Imap\Dbmailuser`,
  `Imap\Dbmailqmailuser` - create/delete-account, quota-set, not message access) are a structurally
  different concern with no JMAP equivalent (cf. [[feedback_smtp_stalwart_admin_only]] - Stalwart's
  own REST-admin API is the analogous mechanism there). Stays separate.
- **`api/src/Auth/Mail.php:67`** - mail-server-based login authentication (`auth_type=mail`) calls
  `$imap->login()` directly, outside the mail app entirely. Whatever ends up owning `login()` needs
  to keep this working for accounts still using plain-password IMAP auth.

## Existing library survey - build on the hand-rolled client, don't adopt a dependency

Checked for an external PHP JMAP library worth anchoring `Mail\Jmap` on before building further:
- **`sebastiankrupinski/jmap-client-php`** - the only serious candidate (typed request objects,
  request bundling, RFC 8621 Mail coverage, used by Nextcloud Mail). **AGPL-3.0-only license** -
  incompatible with this codebase's GPL-2.0-or-later without real legal review; not a casual dependency
  to take on. Worth studying its typed-object-model *shape* as an ergonomics reference only.
- Everything else found is either abandoned (`zend-jmap`, archived Oct 2024), the wrong direction
  (JMAP *server* toolkits, not clients - `audriga/jmap-openxport`, Group-Office), or simply doesn't
  exist (Stalwart only publishes a Rust client; jmap.io's own curated list has zero PHP client
  libraries). The PHP JMAP ecosystem is genuinely thin, not under-researched.

**Verdict: continue building on `api/src/Mail/Jmap.php`** (already working, tested, live-verified
against Stalwart - `RestClientTrait`-based HTTP, session bootstrap, OAuth token grant/refresh) as
the real-JMAP transport underneath `Mail\Jmap\Http`, no new dependency.

## Decided (2026-08-26)

1. **Phase 1 scope**: restructure already-JMAP-native code (`Imap\Jmap`'s overrides + `JmapShim`),
   zero new semantic mapping. See dedicated section above.
2. **Namespace**: generic `Api\Jmap`/`Api\Jmap\Type` base, `Api\Mail\Jmap\...` for mail-specific
   concrete implementations. See "Longer-term" above.
3. **Migration mechanism**: direct call-site rewiring (UI-facing code calls the new session object
   directly), not an `Api\Mail`-shaped facade/emulation layer. See "The proposal" above. Consequence
   for the property-access risk below: no compatibility shim is needed at all - old `Api\Mail`
   methods (and their embedded property accesses) stay untouched and working until their last caller
   migrates, then get deleted outright, never adapted.

## File/namespace layout, decided (2026-08-26)

**Generic core** (new `api/src/Jmap/` directory):
- `api/src/Jmap.php` → `EGroupware\Api\Jmap` - abstract session base: account/auth context, lazy
  per-type accessors (`->mailbox`, `->email`, ...), declares an abstract `call($method, array $args)`.
- `api/src/Jmap/Type.php` → `EGroupware\Api\Jmap\Type` - abstract per-type base declaring the RFC
  8620 §5 `get`/`query`/`set`/`changes` contract, **with a default implementation** that just calls
  `$this->jmap->call("$this->typeName/$method", $args)` - correct as-is for any real-JMAP-shaped
  backend (there's nothing backend-specific about "proxy a JMAP method call over HTTP" per type), so
  this default is genuinely reusable, not a premature abstraction.
- `api/src/Jmap/Http.php` → `EGroupware\Api\Jmap\Http extends Api\Jmap` - concrete generic
  real-JMAP-over-HTTP session. Absorbs today's `api/src/Mail/Jmap.php`'s **Core-layer, type-agnostic**
  parts: `jmapCall()` (batched RFC 8620 §3.3 execution), `bootstrap()`/`sessionUrl()`, OAuth
  `passwordGrant()`/`refreshToken()`, `downloadBlob()`/`uploadBlob()` (Core blob transfer, not
  mail-specific), push-subscription CRUD (Core, not mail-specific), `getChanges()`/`getStates()`
  (Core `/changes` mechanism, not mail-specific) - more of the existing file turns out to be generic
  than first assumed, once checked against what RFC 8620 Core actually defines vs. what RFC 8621
  Mail adds. Implements `call()` via `jmapCall()`, so `Api\Jmap\Type`'s default `get`/`query`/`set`
  works unmodified for anything using this session.

**Mail-specific** (`api/src/Mail/Jmap/` - new subdirectory of the existing `api/src/Mail/` app):
- `api/src/Mail/Jmap/Mailbox.php`/`Email.php`/`Thread.php`/`Quota.php` → `EGroupware\Api\Mail\Jmap\
  {Mailbox,Email,Thread,Quota} extends Api\Jmap\Type` - what's left of today's `api/src/Mail/Jmap.php`
  after the generic split above (the actually-mail-specific parts: `emailGet()`/`emailQuery()`/
  `emailDestroy()`/`emailSetKeywords()`/`emailMove()`/`emailImport()`/`getMailboxId()`/`getQuota()`)
  becomes these classes' bodies - thin in most cases, since `Type`'s default `get`/`query`/`set`
  already does the real work via `Api\Jmap\Http::call()`; these mostly just declare `typeName` plus
  any Email/Mailbox-specific filter-building/convenience helpers.
- `api/src/Mail/Jmap/Imap.php` → `EGroupware\Api\Mail\Jmap\Imap extends Api\Jmap` - the promoted
  `JmapShim`. **`git mv mail/src/JmapShim.php api/src/Mail/Jmap/Imap.php` first** (preserves blame,
  [[feedback_git_mv_before_rewrite]]), content-rewrite (static functions → instance methods using
  the held connection) as a separate follow-up commit. Moves from `mail/src/` to `api/src/Mail/`
  because `Api\Mail` (`api/`) can't depend on the `mail` app.
- `api/src/Mail/Jmap/Imap/Mailbox.php`/`Email.php`/`Thread.php`/`Quota.php` → `EGroupware\Api\Mail\
  Jmap\Imap\{Mailbox,Email,Thread,Quota} extends Api\Mail\Jmap\{Mailbox,Email,Thread,Quota}` -
  **override** `get`/`query`/`set` with real IMAP calls against `Imap`'s held connection (promoted
  from `JmapShim`'s `mailboxGet()`/`mailboxQuery()`/`mailboxSet()`/`emailGet()`/etc.) - this is
  where per-type, per-operation code is genuinely unavoidable, unlike the HTTP side.

`api/src/Mail/Jmap.php` (today's single file) ends up fully absorbed - its generic half moves to
`Api\Jmap\Http`, its mail-specific half becomes `Api\Mail\Jmap`'s per-type class bodies. The 21
existing `jmapClient()`-returning-`new Mail\Jmap(...)` call sites need updating to construct
`Api\Jmap\Http` (or, more likely, get the whole session via `Mail\Account`'s factory instead of
constructing it inline) once this split happens - part of Phase 1's own scope, not a separate step.
