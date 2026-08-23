# Mail Wizard: test harness, then JMAP/OAuth/discovery enhancements

## Status: Phase 1 (test harness) implemented (2026-08-23). Phase 2 (features) not started.

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
- JMAP/Stalwart involvement in the wizard today is essentially nil: one
  `acc_imap_type !== Mail\Imap\Jmap::class` preservation check inside `edit()`'s
  `normalizeAccountType()` (see below), no discovery/testing path at all.
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

### Known environment blocker for the REST test

`mail/tests/REST/MailAccountPatchTest.php` could not be live-executed against this
session's local EGroupware instance: even a bare `GET /<user>/mail` returns
`500 {"message": "Could not resolve host: http"}`, and `GET /mail/<id>` shows a full
trace through `Api\Mail\Smtp\Stalwart::domainId()` → `Api\Mail\Jmap` bootstrap → a curl
call to a URL whose host is literally the string `http` - a pre-existing, unrelated
misconfiguration of acc_id=1's JMAP/Stalwart endpoint on this dev box, not something
introduced by this change. The unit tests (all other files above) ran clean against the
same environment. Whoever runs the REST test next should fix that config first, then run
with `EGW_ADMIN_PASSWORD` set (needed for the `accUserEditable` toggling and admin-bypass
cases).

## Phase 2 - feature roadmap (not started)

Each item needs its own design/approval pass before implementation - none of these are
pre-approved by Phase 1 landing.

1. **DNS discovery via SRV records** - `(jmap|imap|smtp)._tcp.<domain>`, reusing the
   `dnsQuery()` seam (add `DNS_SRV` handling). Slots in as a new, higher-priority
   discovery step in `autoconfig()`'s existing priority chain (before Mozilla ISPDB,
   after OAuth-domain-match), and needs an equivalent for JMAP discovery specifically
   since `autoconfig()` today only ever discovers IMAP.
2. **Broader OAuth support** - generalize past the hardcoded Google/Microsoft domain-
   regex table in `OpenIDConnectClient::providerByDomain()`, likely via admin-configurable
   custom OAuth provider entries (endpoint/client id/secret) feeding the same
   `oauth2content()`/`oauthToken()` machinery already in `admin_mail`.
3. **JMAP-only account creation** - a wizard path that discovers/tests JMAP directly (via
   the SRV lookup in #1 or a JMAP well-known/session-endpoint probe) and skips
   IMAP/Sieve/SMTP entirely, persisting `acc_imap_type = Mail\Imap\Jmap` with a
   JMAP-native submission path instead of a separate SMTP step.
4. **Stalwart-integrated-login OAuth workaround** - avoid requiring a manual second login
   at an EGroupware-integrated Stalwart instance, since Stalwart doesn't support an OAuth
   password grant. Needs a concrete design session on the exact trust mechanism (e.g.
   server-to-server assertion, shared secret, EGroupware acting as the OIDC provider
   Stalwart trusts) before any code is written.
5. **Split general-JMAP vs. Stalwart-specific support** - to support other JMAP providers
   (e.g. FastMail), audit `api/src/Mail/Imap/Jmap.php` (currently the Stalwart-backed
   implementation) for Stalwart-only assumptions (push/webhook wiring, the #4 login
   workaround) and split into a general `Jmap` base + `Stalwart` subclass, consistent with
   [[mail-bo-decoupling]]'s extraction discipline (no wrapper unless a separate consumer
   needs it, re-check "no callers" case-insensitively for PHP method names).
