# Mail Wizard: test harness, then JMAP/OAuth/discovery enhancements

## Status: Phase 1 (test harness) implemented AND fully validated (2026-08-23). Phase 2 Milestone A code implemented and unit-tested (2026-08-23, 90 tests green: 84 unit + 6 REST); live verification against `https://stalwart.egroupware.org` still pending (needs ralf's real credentials/action, see below). Milestone B (items 2+5) not started.

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
  account ralf already created - tackled only after Milestone A works.
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

- `folder()`'s Horde-based special-use-folder guessing needs a real IMAP socket it won't have
  for JMAP - skipped outright for JMAP accounts (autoconfig() goes straight to `sieve()`),
  relying on the mail app's existing JMAP folder-role handling at usage time rather than
  wizard-time guessing.
- New injectable seam `jmapClient()` (mirrors `dnsQuery()`/`ispdbHttpGet()`) so
  `TestableAdminMail` can stub JMAP connectivity in tests instead of hitting a real server.
- Found and fixed a real pre-existing bug while implementing this: `normalizeAccountType()`'s
  JMAP carve-out compared `acc_imap_type` with an exact `!==` match against
  `Mail\Imap\Jmap::class`, so setting it to a *subclass* like `Mail\Imap\Stalwart::class` (as
  Milestone A does) would have been silently reset back to plain IMAP for a single-user
  account - never hit before since the existing acc_id=1 Stalwart account is multi-user.
  Fixed via `is_a($content['acc_imap_type'] ?? '', Mail\Imap\Jmap::class, true)`.
