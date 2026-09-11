# Mail Wizard: test harness, then JMAP/OAuth/discovery enhancements

## Status: Phase 1 and Milestone A done. Milestone A.1 done and live re-verified (2026-08-24) -
ralf repeatedly created real working JMAP/Stalwart accounts via the wizard against
`https://stalwart.egroupware.org`, each round surfacing a further bug fixed the same day (see
"Milestone A.1 remaining work" below for the full list and what's genuinely still open). Milestone
B (items 2+5) not started.

Continuation of [[mail-jmap-modernization]] - that project moved mail *usage* (rows, body,
flags, folders) onto JMAP for Stalwart, but never touched mail *account creation*. The Mail
Wizard (`admin_mail`, extended by `mail_wizard`) still only knows how to discover/configure
classic IMAP+SMTP(+Sieve) accounts, had zero test coverage, and has no path to create a
JMAP-only account (with or without OAuth) without ever touching IMAP.

## Goal

1. Add a test harness for the existing wizard logic first, so Phase 2's feature work has
   regression coverage as it lands.
2. Then extend the wizard with:
   - DNS discovery for JMAP/IMAP/SMTP via `(jmap|imap|smtp)._tcp.<domain>` SRV records.
   - Broader OAuth support (today hardcoded to Google/Microsoft domains).
   - Creating a JMAP-only account without ever touching IMAP, incl. OAuth if available.
   - A Stalwart-specific OAuth workaround so EGroupware users integrated with their own
     Stalwart instance aren't forced to manually log in to Stalwart a second time (Stalwart
     doesn't support an OAuth password grant).
   - Supporting other JMAP providers (e.g. FastMail), which may require splitting JMAP
     support into a general class and a Stalwart-specific subclass.

## Current architecture

- `admin_mail` (`admin/inc/class.admin_mail.inc.php`, plain class, no `extends`) is a
  step-chaining wizard, state threaded through a `$content` array with no formal state
  machine: `add()` → `autoconfig()` → `folder()` → `sieve()` → `smtp()` → `edit()`. Each
  step renders an Etemplate and decides the next step from `$content['button']`.
- `mail_wizard extends admin_mail` (`mail/inc/class.mail_wizard.inc.php`) only changes
  `const APP_CLASS` (`'admin.admin_mail.'` vs `'mail.mail_wizard.'`) and additionally
  force-loads `admin`'s lang file / CSS / JS in its constructor - everything else,
  including `edit()` and `mailboxes()`, is inherited unchanged (confirmed by
  `admin/tests/SmimeGenerateTest.php`'s docblock and by this project's own
  `mail/tests/MailWizardDifferentialTest.php`).
- Discovery today, tried in this order inside `autoconfig()`:
  1. `OpenIDConnectClient::providerByDomain()` (`api/src/Auth/OpenIDConnectClient.php`) -
     a hardcoded domain-regex table for Google and Microsoft only.
  2. An explicit host, if the user typed one.
  3. `mozilla_ispdb()` - Thunderbird/Mozilla ISPDB autoconfig XML, with an MX-based retry
     chain for hosted-email providers that don't register the customer's own domain.
  4. `guess_hosts()` - DNS `A`/`MX`-based hostname guessing
     (`imap.$domain`, `mail.$domain`, MX-target-derived hosts, Office365 detection).
  No SRV lookups exist anywhere in the repo today.
- IMAP/Sieve/SMTP connectivity is tested with raw Horde clients constructed inline
  (`Horde_Imap_Client_Socket`, `Horde\ManageSieve`, `Horde_Mail_Transport_Smtphorde`), not
  through EGroupware's own `Api\Mail\Imap`/`Api\Mail\Smtp` wrapper classes. There is
  currently no injection seam for the Sieve/SMTP clients (unlike `folder()`, which accepts
  a pre-built `Horde_Imap_Client_Socket` as an optional 4th parameter).
- OAuth (XOAUTH2) already works for IMAP and SMTP login via
  `Horde_Imap_Client_Password_Xoauth2`/`Horde_Smtp_Password_Xoauth2`, driven by
  `EGroupware\Api\Auth\OpenIDConnectClient extends Jumbojett\OpenIDConnectClient`. Per
  ralf: this is currently wired for Google/Microsoft only; for a general
  Stalwart-or-OAuth-capable JMAP/IMAP server the intended building block is also
  `api/src/Auth/OpenIDConnectClient`, and Stalwart itself needs a workaround so
  EGroupware users aren't forced to manually log in to an EGroupware-integrated Stalwart a
  second time (Stalwart doesn't support an OAuth password grant).
- Persistence: `Mail\Account::write($content, $user)` (`api/src/Mail/Account.php`) is the
  sole write path, called from `edit()`. Multi-user/"everyone" vs. single-user accounts
  are the SAME `edit()` method, branching on `Mail\Account::is_multiple()` (`account_id`
  `<= 0`, or an array containing `0` or more than one entry) - there is no separate
  bulk-creation method; the admin "manage users" list just lands in `edit()` with a target
  `account_id`/`called_for`.
- JMAP/Stalwart involvement in the wizard before Phase 2 Milestone A was essentially nil: one
  `acc_imap_type !== Mail\Imap\Jmap::class` preservation check inside `edit()`'s
  `normalizeAccountType()` (see below), no discovery/testing path at all. Milestone A (below)
  adds the first real JMAP discovery/connection path.
- REST (`mail/src/ApiHandler.php extends Api\CalDAV\Handler`) has no account-creation
  endpoint; `PATCH /mail/{id}` (`put()`, `{id}` is an `ident_id`) only **edits an
  existing** account, gated: admin always allowed; non-admin only if `acc_user_editable`
  and it's their own account (URL-derived owner matches the authenticated session).

## Phase 1 - test harness (implemented)

### Testability seam added to `admin_mail`

`guess_hosts()`/`mozilla_ispdb()` called global `dns_get_record()`/`file_get_contents()`
directly with no injection point. Added two thin `protected static` wrappers:

```php
protected static function dnsQuery(string $hostname, int $type) { return dns_get_record($hostname, $type); }
protected static function ispdbHttpGet(string $url) { return file_get_contents($url); }
```

Production call sites switched to `static::dnsQuery(...)`/`static::ispdbHttpGet(...)` -
pure rename, no behavior change. `dnsQuery()` is generic over `$type`, so a future SRV
phase (see below) can reuse it with `DNS_SRV` without a new seam. The recursive
`self::mozilla_ispdb(...)` call was deliberately left as `self::` (not `static::`) -
unrelated to this seam, and PHP's late static binding means `static::` inside that
recursive call still resolves through a test subclass correctly regardless (forwarding
calls preserve the "called class").

A test-only `TestableAdminMail extends admin_mail` (in
`admin/tests/AdminMailHostDiscoveryTest.php`) overrides both methods with fixture maps
(`[hostname][type] => result`, `[url] => result`); a lookup missing from the fixture map
throws, so a test can't silently pass because it forgot to stub a call the code path
actually makes.

### Extraction out of `edit()` for testability

Two pure, `is_multiple()`-driven array-transform blocks were pulled out of `edit()` into
named methods (behavior-preserving, call sites updated in place):

- `adminReadonlyFields()` - the exact field set a non-admin can't edit.
- `normalizeAccountType(array $content, bool $is_multiple)` - forces `acc_imap_type`/
  `acc_smtp_type` back to the plain classes for single-user accounts (with the
  `Mail\Imap\Jmap::class` carve-out), or copies `ident_email_alias` back to `ident_email`
  for multi-user accounts.

### New test files

| File | Base class | Covers |
|---|---|---|
| `admin/tests/AdminMailPureLogicTest.php` | `TestCase` | `fix_ssl_order()`, `oauth2content()`, `adminReadonlyFields()`, `normalizeAccountType()` |
| `admin/tests/AdminMailMailboxesTest.php` | `TestCase` | `mailboxes()` special-folder guessing, via a stubbed `Horde_Imap_Client_Socket` |
| `admin/tests/AdminMailHostDiscoveryTest.php` | `Api\LoggedInTest` | `guess_hosts()`, `mozilla_ispdb()`, via `TestableAdminMail`'s DNS/HTTP fixtures |
| `mail/tests/MailWizardDifferentialTest.php` | `Api\LoggedInTest` | `APP_CLASS` divergence, constructor smoke test, `mailboxes()` inheritance |
| `mail/tests/REST/MailAccountPatchTest.php` | `Api\RestBase` | `PATCH /mail/{id}` self-edit, cross-user 403, `accUserEditable` gating, admin bypass, 404 |

Explicitly out of scope for Phase 1 (documented in each file's class docblock):
`autoconfig()`/`sieve()`/`smtp()`/`add()`/`folder()`/`edit()` end-to-end (every one ends in
`Etemplate::exec()`, and `sieve()`/`smtp()` build their Horde clients inline with no
injection seam); the OAuth redirect/token-exchange flow (`oauthToken()`,
`oauthAuthenticated()`, `oauthFailure()`); `ajax_activeAccounts()`/
`ajax_smimeCreateKeypair()` direct invocation (both `die()` on an invalid
`etemplate_exec_id` via `Api\Etemplate\Request::csrfCheck()`, same limitation already
documented in `SmimeGenerateTest.php`).

### Known environment blocker for the REST test - RESOLVED 2026-08-23

The `500 {"message": "Could not resolve host: http"}` blocker described here was the same
double-scheme JMAP bootstrap bug fixed in `de56ed499a` (see [[jmap-mail-service-host-bug]]).
Confirmed resolved: re-running `mail/tests/REST/MailAccountPatchTest.php` no longer 500s.

All four unit-test files (`AdminMailPureLogicTest`, `AdminMailMailboxesTest`,
`AdminMailHostDiscoveryTest`, `MailWizardDifferentialTest` - 28 tests total) pass clean via
`vendor/bin/phpunit -c doc/phpunit.xml`.

With `EGW_ADMIN_PASSWORD` set, running the REST test surfaced a second, real bug (now
fixed, see below): `ApiHandler::put()`'s catch block swallowed the true HTTP status of any
exception, making every PATCH error look like `200 OK`. Once fixed, the true error surfaced:
`demo`'s test identity had a stale `mailLocalAddress` (`demo@example.org`) that didn't match
`acc_id=1`'s actual Stalwart domain (`boulder.egroupware.org`), so any identity write for
`demo` failed Stalwart's alias-domain lookup. Fixed by PATCHing `demo`'s `mailLocalAddress`
to `demo@boulder.egroupware.org` (a one-off test-data correction, not a code change). With
both fixed, `MailAccountPatchTest.php` passes clean (5 tests, 18 assertions):
`EGW_ADMIN_PASSWORD="..." vendor/bin/phpunit -c doc/phpunit.xml mail/tests/REST/MailAccountPatchTest.php`.

**Real bug fixed in `mail/src/ApiHandler.php`'s `put()`:** the catch block called
`self::handleException($e)` without `return`ing it (unlike the two other call sites in the
same file, which do), so any exception during `PATCH /mail/{id}` made `put()` return `null`.
`Api\CalDAV::PUT()` passed that straight to `http_status()`, producing a malformed
`"HTTP/1.1 "` header with no status code - the SAPI silently defaulted this to `200 OK`,
so every real error (500s, validation failures, etc.) looked like success to any REST
client, with the actual error visible only in the JSON body. One-line fix, pushed as
`b73b2ae61a`.

## Phase 2 - feature roadmap

Original 5 items, sequenced by ralf on 2026-08-23 into two milestones after discussion:

- **Milestone A** (in progress): items 1+3, scoped down to Stalwart only - get a personal JMAP
  account working end-to-end via the wizard against `https://stalwart.egroupware.org`.
- **Milestone B** (later, not started): items 2+5 together, validated against a FastMail test
  account ralf already created - tackled only after Milestone A works. Item 2's Fastmail-specific
  scoping/findings now live in their own doc, see [[fastmail-jmap-access]] (paused 2026-08-26,
  waiting on a manually-provisioned OAuth client_id).
- Item 4 (Stalwart OAuth-login workaround) turned out to already be implemented
  (`Mail\Jmap::passwordGrant()`/`oauthBaseUrl()`, used today by `Imap\Stalwart::accessToken()`
  for already-saved accounts) - Milestone A's job is to *use* it during wizard setup for live
  credential validation, not to design something new.

1. **DNS discovery via SRV records** - `_jmap._tcp.<domain>` (JMAP only for Milestone A; IMAP/
   SMTP SRV deferred, not needed for Stalwart), reusing the `dnsQuery()` seam (`DNS_SRV`).
   JMAP must be probed *before* IMAP for any candidate host (not just SRV-derived ones) since
   most JMAP servers also speak IMAP - IMAP-first probing would misclassify them. **No
   `_jmap._tcp` record exists for `egroupware.org` yet** - the SRV lookup is implemented and
   tested (fixture-based), but Milestone A's real-world verification exercises the manual
   host-entry fallback instead.
2. **Broader OAuth support** - generalize past the hardcoded Google/Microsoft domain-
   regex table in `OpenIDConnectClient::providerByDomain()`, likely via admin-configurable
   custom OAuth provider entries (endpoint/client id/secret) feeding the same
   `oauth2content()`/`oauthToken()` machinery already in `admin_mail`. Persistence should live
   on the mail account itself, possibly as an additional JSON blob (ralf, 2026-08-23) - exact
   shape to be designed as part of Milestone B.
3. **JMAP-only account creation (Milestone A, Stalwart-scoped)** - a wizard path that connects
   via JMAP directly (SRV or manual host) and skips IMAP/Sieve entirely, but **SMTP stays** -
   no JMAP-native mail submission exists yet, so `smtp()` runs unchanged, resulting in plain
   `EGroupware\Api\Mail\Smtp` (ralf, 2026-08-23: corrects this item's earlier text, which
   wrongly assumed JMAP-native submission would replace SMTP). `acc_imap_type` is hardcoded to
   `Mail\Imap\Stalwart::class` on JMAP success rather than generically detected - see item 5.
   Sieve support/config comes from the bootstrapped JMAP session's `accountCapabilities`
   (`urn:ietf:params:jmap:sieve`, RFC 9661), not a `ManageSieve` probe. **`Smtp\Stalwart` must
   never be auto-assigned as `acc_smtp_type`** - it's the admin-automation class for
   administrating a Stalwart server (user/alias/quota management via JMAP `Account/set`), not
   a personal SMTP transport; a wizard-created personal account only ever gets plain SMTP or a
   reported failure.
4. **Stalwart-integrated-login OAuth workaround** - already implemented, see above. Milestone
   A wires `Mail\Jmap::passwordGrant()` into the wizard's JMAP connection trial as a live login
   check; actual token exchange/caching for real usage continues to happen lazily via the
   already-shipped `Imap\Stalwart::accessToken()`, unchanged.
5. **Split general-JMAP vs. Stalwart-specific support (Milestone B)** - to support other JMAP
   providers (e.g. FastMail, ralf already has a test account), audit `api/src/Mail/Imap/Jmap.php`
   (currently the Stalwart-backed implementation) for Stalwart-only assumptions (push/webhook
   wiring, the #4 login workaround) and split into a general `Jmap` base + `Stalwart` subclass,
   consistent with [[mail-bo-decoupling]]'s extraction discipline (no wrapper unless a separate
   consumer needs it, re-check "no callers" case-insensitively for PHP method names). Milestone
   A deliberately hardcodes Stalwart detection instead of pre-empting this split.

### Milestone A implementation notes

- New injectable seam `jmapClient()` (mirrors `dnsQuery()`/`ispdbHttpGet()`) so
  `TestableAdminMail` can stub JMAP connectivity in tests instead of hitting a real server.
- Found and fixed a real pre-existing bug while implementing this: `normalizeAccountType()`'s
  JMAP carve-out compared `acc_imap_type` with an exact `!==` match against
  `Mail\Imap\Jmap::class`, so setting it to a *subclass* like `Mail\Imap\Stalwart::class` (as
  Milestone A does) would have been silently reset back to plain IMAP for a single-user
  account - never hit before since the existing acc_id=1 Stalwart account is multi-user.
  Originally fixed via `is_a()`, then changed to a plain `in_array()` allowlist in Milestone A.1
  (see below) once `is_a()` was found to have its own, worse problem.
- `folder()`'s original Milestone A behaviour (skip outright for JMAP, rely on the mail app's
  live JMAP folder handling) was superseded in Milestone A.1 below - it's now a JMAP-native
  step too, not skipped.

## Milestone A.1 (2026-08-24) - live-run follow-ups

Ralf ran the shipped wizard live against `https://stalwart.egroupware.org` (manual host entry,
still no `_jmap._tcp` SRV record) and it worked end-to-end - a real account (acc_id=86) was
created. That surfaced three follow-ups, refined over discussion into a larger scope:

1. **Mail app folder tree showed "Loading..." forever** for the new account - turned out to be a
   *separate*, genuine client-side bug, unrelated to any wizard-populated field: the live folder
   tree already works purely via runtime JMAP (`Mailbox/get`/`Mailbox/query`), independent of the
   wizard. Root cause: `mail/js/app.ts`'s `'mail-account'`/`'add'` push-notification handler had no
   `.catch()` anywhere in its promise chain, so any unhandled rejection in the deeper JMAP
   folder-bootstrap chain left the placeholder stuck forever with no visible error. Fixed by
   wrapping the handler body in try/catch and replacing the stuck placeholder with an error
   message on failure. **The actual underlying exception for account 86 was NOT root-caused** -
   this defensive fix makes failures visible, but live re-verification (see below) may still
   surface a real bug to fix once the error message is actually seen.
2. **JMAP-native folder AND sieve detection**, matching IMAP - `folder()`/`sieve()` stay part of
   the SAME visible step chain for JMAP as for IMAP (`autoconfig → folder → sieve → smtp → edit`),
   using JMAP-native detection instead of Horde IMAP `LIST`/`ManageSieve`:
   - New `admin_mail::jmapMailboxes()` queries `Mailbox/get` (`ids: null`) and maps the RFC 8621
     `role` property to `acc_folder_*`, mirroring `mailboxes()`'s Horde-attribute mapping (roleless
     folders like Templates/Ham fall back to common-name matching, same as the IMAP path).
   - `sieve()` no longer fully short-circuits to `smtp()` for JMAP - it still renders, with
     protocol/host/port shown read-only (copied from the mail-server step) and
     `acc_sieve_enabled` derived from the JMAP session's `urn:ietf:params:jmap:sieve` capability -
     user can turn it off, never on if undetected.
3. **Manual JMAP protocol selection** - `Mail\Account` gained `JMAP_HTTP=4`/`JMAP_HTTPS=6` next to
   the existing `SSL_NONE/STARTTLS/TLS/SSL` values, so a user can pick JMAP explicitly (not just
   rely on auto-detection) and set a custom port. Legacy `SSL_SSL(3)` is unified with `SSL_TLS(2)`
   - "nothing below TLS 1.2 makes sense or is even supported by PHP anymore" (ralf) - on read
   (`Account::ssl2secure()`, `smtpServer()`, `smtpTransport()`; also fixed a latent bug where
   `smtpServer()`'s switch didn't mask the verify bits at all) and on write
   (`normalizeAccountType()` now rewrites `3`→`2` unconditionally, single- or multi-user). JMAP's
   scheme (http vs. https) is now explicit end-to-end, not just at wizard-time: `Imap\Jmap::
   jmapUrl()` builds `http://`/`https://` from `acc_imap_ssl` for real (post-wizard) usage too -
   previously `Mail\Jmap` always defaulted to https regardless of any setting.
4. **Real TLS certificate verification**, uniformly for IMAP/SMTP/Sieve/JMAP - previously a
   complete no-op: Horde's `Horde\Socket\Client` (IMAP/SMTP/Sieve) hardcodes
   `verify_peer=false` unless a caller passes a `context` override (nothing in this codebase ever
   did), while curl (JMAP) verifies by default with no code path to disable it. Ralf's design: a
   **3-state field** in bits 3-4 of `acc_(imap|sieve|smtp)_ssl`, reusing the
   previously-defined-but-never-wired `SSL_VERIFY=8` constant as one of the three states so
   existing rows transition safely with zero migration:
   - `VERIFY_UNDECIDED=0` - every existing row today, never written by new code.
   - `VERIFY_ENABLED=8` (was `SSL_VERIFY`) - verification confirmed possible, enforced from now on.
   - `VERIFY_DISABLED=16` - verification failed (or was rejected), never enforced.
   - New accounts (wizard): each connection-test loop (`autoconfig()`'s IMAP trial, `tryJmap()`,
     `smtp()`'s trial, classic `sieve()`'s ManageSieve trial) probes strict verification right
     after a successful lenient connection and sets `VERIFY_ENABLED`/`VERIFY_DISABLED` directly -
     never leaves `VERIFY_UNDECIDED`. JMAP is the exception: curl already verifies by default, so
     `tryJmap()`/`Imap\Jmap::jmapClient()` try strict first and only retry leniently on a
     certificate-specific failure.
   - Existing accounts (`VERIFY_UNDECIDED`): silently upgraded exactly once, on the next real
     connection in normal usage - `Mail\Account::resolveVerification()` (a raw-socket probe via
     `probeCertVerification()`, persisted via a narrow direct column update, not a full
     `write()`/ACL-checked round-trip) is called from `Mail\Imap::login()`,
     `Account::smtpTransport()`, and `Sieve\ManageSieve::connect()` (new override); JMAP's
     one-time upgrade lives in `Imap\Jmap::jmapClient()`'s strict-then-lenient-retry logic instead,
     since curl's own default already IS the strict attempt.
   - **Not yet implemented**: a blocking "I understand the risk, disable certificate verification"
     confirmation checkbox for the wizard's failure case - currently the wizard resolves and
     proceeds automatically either way, just surfacing an informational message on failure. The
     plan called for requiring explicit confirmation before proceeding on failure; this was
     deferred given the scope already covered.

**Known pre-existing landmine found, not fixed**: `Mail\Account`/`Mail\Imap` both end with an
unconditional top-level `self::init_static()` call that runs once, the instant the class is
autoloaded, doing `self::$db = $GLOBALS['egw']->db`. If ANY bare (non-`Api\LoggedInTest`) PHPUnit
test in the whole suite is the first to reference either class before a real session bootstraps,
`self::$db` becomes permanently `null` for the rest of that PHPUnit process, breaking unrelated
later tests with `Call to a member function ... on null`. This bit an attempted new unit test file
for `Account::sslContext()`/`resolveVerification()` (deleted; see
[[feedback-bare-testcase-poisons-account-db]]) and is *latently* present in
`AdminMailPureLogicTest.php`'s `normalizeAccountType()` tests too (dormant only because none of
its current `$content` fixtures set an `acc_*_ssl` field). No general fix applied - would need
`self::$db` to become a lazy accessor instead of a cached static property, a separate refactor.

### Milestone A.1 live re-verification (2026-08-24) - bugs found and fixed

Ralf ran the wizard end-to-end against `https://stalwart.egroupware.org` repeatedly, each round
surfacing one more real bug (not the originally-suspected one) that got root-caused and fixed the
same day:

- **The real cause of the "Loading..."/SMTP-step hang**: NOT the missing `.catch()` (that was a
  real but secondary gap, already fixed above) - the actual hang was `edit()`'s folder-selectbox
  population unconditionally calling the classic `self::mailboxes(self::imap_client($content))`
  (a raw IMAP socket) regardless of `acc_imap_type`, so a JMAP account's `acc_imap_host`/port
  (pointing at a JMAP(S) endpoint, not an IMAP server) hung for the full IMAP connect-timeout
  before showing a generic "Error when communicating with the mail server". Three earlier fix
  attempts (shortening `probeCertVerification()`'s timeout, redesigning the wizard's own trial
  loops to the optimistic-verify pattern below, removing an eager check from
  `Account::smtpTransport()`) were reasonable given the evidence at the time but didn't address
  this - documented transparently since they're real, permanent changes, just not *the* fix.
  Fixed: `edit()` now branches on `acc_imap_type` and calls `jmapMailboxes()` instead of the
  classic path for a JMAP account. Confirmed fixed live ("the timeout is gone").
- **Optimistic certificate verification, redesigned per ralf's explicit correction**: "in general I
  would always optimistically use the cert-check approach, and only handle the validation
  failure" - the wizard's own IMAP/Sieve/SMTP/JMAP trial loops now attempt the real connection with
  strict verification first (when `VERIFY_UNDECIDED`) and retry the SAME attempt leniently only on
  an actual certificate-specific failure (`Mail\Account::isCertificateError()`) - no separate probe
  connection (the original design), which risked colliding with a real mail server's per-IP
  concurrent-connection limits. Live-confirmed: acc_id=1 (internal, self-signed) resolved to
  `VERIFY_DISABLED`, acc_id=42 (real cert) resolved to `VERIFY_ENABLED`.
- **`Mail\Jmap`'s sentinel-host bug**: introducing `Imap\Jmap::jmapUrl()` (for explicit http/https
  scheme selection) broke the *existing* acc_id=1 production account by turning its sentinel host
  value (`'mail'`, resolved server-side via `Api\Framework::getUrl('/jmap/')`) into the
  non-resolvable literal `https://mail`. Fixed by special-casing the sentinel values
  (`'mail'`/`'stalwart'`/`'internal.k8s.farm.egroupware.org'`) to pass through unchanged. Caught
  immediately by ralf live-testing; high-priority same-turn fix given it broke a production
  account.
- **`Sieve\Jmap::listScripts()` crashed the whole account save** with an uncaught
  `Cannot access offset of type string on string` the first time a freshly created JMAP account's
  save handler called `retrieveRules()` (as a connection-test convenience) and Stalwart's
  `SieveScript/get` response came back in a shape that isn't a decoded array. This is a
  `\TypeError` (extends `\Error`, not `\Exception`) - the method's own `catch (\Exception $e)`
  never caught it, contrary to its documented contract ("will not throw ... if there's no script
  currently"). Fixed by broadening to `catch (\Throwable $e)`.
- **Sieve step regression**: stepping back from `smtp()` to `sieve()` re-ran the JMAP-detection
  branch with `_jmap_account_capabilities` already `unset()` by the forward `case 'continue':`
  handler, showing a false "This JMAP server does NOT support Sieve". Fixed by no longer
  unsetting it - it's harmless scratch state, same as other fields already round-tripped through
  the wizard's `$content`.
- **Our own CSP blocked the browser's direct-JMAP-session fetch** for any account on a real
  external JMAP host (not one of the same-origin sentinel values) - `Mail\Imap\Stalwart::
  jmapBootstrap()`'s "browser talks to Stalwart directly" design needs that host in our
  `connect-src` allowlist, which nothing ever populated (confirmed live: even a bare
  `fetch('https://www.google.com/...', {mode:'no-cors'})` failed identically from the mail page -
  proof it was our own default `connect-src 'self'`, not a Stalwart-side CORS/reachability
  problem). Two-part fix: (1) `mail_hooks::csp_connect_src()` (new `csp-connect-src` hook,
  registered in `mail/setup/setup.inc.php`) returns the current user's real Stalwart hosts;
  `admin_mail::edit()`'s save handler self-heals the hook registration
  (`Api\Hooks::exists()`/`read(true)`, same pattern already used for `mail_import` in
  `mail_integration.inc.php`) so it's registered the instant an account is saved, no admin
  action needed. (2) Since the *page already open* at save-time still has the stale CSP header
  from its own original load, `mail/js/jmap.ts` listens for `securitypolicyviolation` events and,
  if the blocked origin matches one of the user's own JMAP accounts, does a single
  `sessionStorage`-guarded `window.location.reload()` to pick up the fresh header - reacting to the
  browser's own violation *event* specifically, not to the resulting fetch/session promise
  rejection, since the two aren't guaranteed to be ordered relative to each other (confirmed live:
  the promise-based version recorded the violation too late to act on it). Confirmed fixed live.
- **UI polish from ralf's live-testing feedback**, not originally scoped but done alongside the
  above since they came from the same test rounds: "Secure connection" label renamed to "Protocol
  (encryption)"; the `$ssl_types` dropdown (previously one array shared identically across
  IMAP/Sieve/SMTP, each verification state as a separate same-labelled entry) replaced with
  `sslTypes($protocolName, $withJmap)` - per-field labels, ordered JMAP(https)/TLS-SSL/StartTLS/
  JMAP(http)/no-encryption, no JMAP option for SMTP; verification state moved out of the dropdown
  entirely into a new "Disable certificate validation" checkbox (`mergeVerifyCheckbox()`/
  `splitVerifyCheckbox()` translate between it and the stored bitfield), placed after the port
  field in the wizard and `edit()` (desktop + mobile templates); the port field's own label moved
  from an inline `label=` attribute to a separate sibling widget, since the 3-item hbox (protocol
  select + port + new checkbox) pushed the inline label above the input and broke the row layout.

### Milestone A.1 - what's still actually open

- The blocking "I understand the risk, disable certificate verification" *confirmation* UX for a
  wizard-time verification failure is still not implemented - largely superseded in practice by
  the new checkbox above (the user can proactively disable-and-retry instead of being blocked),
  but that's a different interaction than what the original plan called for.
- DNS SRV discovery (`_jmap._tcp.<domain>`) remains implemented + fixture-tested only, never
  live-verified - ralf has no SRV record in his test domain and explicitly signed off on leaving
  it untested for now (2026-08-24: "trivial enough to leave untested... I don't have the DNS
  entries set currently").
- No new automated test coverage was added for any of the live-testing-round fixes above
  (`jmapMailboxes()`'s role mapping, `sieve()`'s visible-for-JMAP rendering, `sslTypes()`/the
  merge-split checkbox helpers, the `csp-connect-src` hook) - the existing wizard suite
  (`AdminMailPureLogicTest`/`AdminMailHostDiscoveryTest`/`AdminMailMailboxesTest`, 29 tests) still
  passes unmodified, but doesn't exercise any of this new code.
- Milestone B (items 2+5: broader OAuth support, general `Jmap`/Stalwart split for other
  providers) not started, as before.
