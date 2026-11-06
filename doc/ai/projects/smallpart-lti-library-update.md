# smallPART (ViDoTeach) - celtic/lti library update

## Goal

Update the `celtic/lti` library smallpart's LTI Tool Provider is built on (was pinned `^4.4.1`,
installed `4.10.3`) to the current `5.x` line, without silently breaking LTI 1.0/1.3 launches from
real platforms (Moodle, etc.). **Done** - now on `v5.4.6`, installed and all adapter code fixed; see
"Status" below. Phase 1 (a regression test harness covering our own adapter code) was built and
verified *before* touching the library version, exactly so the actual bump would have something
concrete to run against - it worked: every fatal error the version bump caused was caught by an
existing test, with no new tests needed to find the breakage.

## Why 5.x is not a drop-in replacement

Per the library's own [Updating wiki page](https://github.com/celtic-project/LTI-PHP/wiki/Updating)
(v5.0.0 was "Updates for PHP 8.1+", first released as `v5.0.0-rc1`, now at `v5.4.6`):

* Constants become enums under `ceLTIc\LTI\Enum`, eg. `Util::LTI_VERSION1P3` / `Util::LTI_VERSION1`
  -> `LtiVersion::V1P3` / `LtiVersion::V1`; `Tool::ID_SCOPE_*` -> `IdScope::*`; `Util::LOGLEVEL_*` ->
  `LogLevel` enum.
* Several consumer-era classes/methods are removed entirely (`ToolConsumer`, `ConsumerNonce`,
  `Context::fromConsumer()`, `DataConnector::escape()`/`quoted()`, etc.) - not used by smallpart's
  code, checked by grep.
* Methods gain strict type hints that a subclass overriding them must match.

**Concretely, in this codebase:** `src/LTI/DataConnector.php`'s `loadPlatform()`/`savePlatform()`
directly assigned `LTI\Util::LTI_VERSION1P3`/`LTI_VERSION1` to `$platform->ltiVersion` - the line
that broke first. Separately, and more broadly: *every* overridden library method across
`DataConnector.php` (`loadPlatform`, `savePlatform`, `loadPlatformNonce`, `savePlatformNonce`,
`deletePlatformNonce`, `loadUserResult`, `saveUserResult`) and `Tool.php` (`onLaunch`,
`onContentItem`) was declared with no parameter/return type hints - v5's base classes now declare
strict types on all of these, and PHP treats an untyped override of a typed parent method as a
**fatal class-load error** ("Declaration ... must be compatible"), not a warning. `Session.php`
was unaffected - it only reads properties, never overrides a library method.

## Test harness (done)

`smallpart/tests/LTI/`:

* `LtiFixtures.php` - shared trait. Builds *real* `ceLTIc\LTI\Platform`/`UserResult`/our
  `Tool`/`DataConnector` objects via their public API (no mocking of the library's own classes), so
  a library-side signature change surfaces as a fixture-construction failure. The one seam: `Tool`'s
  `messageParameters` is injected directly via `ReflectionProperty` rather than driving a real
  signed OAuth1/JWT request through the library's own verification pipeline - that pipeline is the
  library's own responsibility and out of scope for this harness (see "Not covered" below).
* `ConfigTest.php` - `Config::read()`/`readById()` pure lookup/matching logic (our own code, no
  library objects).
* `DataConnectorTest.php` - the highest-value file: `loadPlatform()`/`savePlatform()`/nonce
  round-trip, all through real `Platform`/`PlatformNonce` objects. This was, as predicted, the first
  file to need edits once the library version actually bumped - see "Status" below.
* `ToolTest.php` - constructor profile setup (`Profile\Item`/`Message`/`ResourceHandler`), plus
  `onLaunch()`'s failure path (exception -> `ok=false`+`reason`, does not `exit()`).
* `SessionTest.php` - `Session`'s pure accessors (`getIssuer()`, `isInstructor()`,
  `getFrameAncestor()`, `getCustomData()`) plus `create()`'s account-matching/auto-creation/group-
  sync happy path and both config-lookup failure exceptions.

All 33 tests pass as of 2026-09-18 (`vendor/bin/phpunit -c doc/phpunit.xml smallpart/tests/LTI`,
`EGW_ADMIN_PASSWORD` env needed for `DataConnectorTest`/`SessionTest`'s admin-account setup).

### Gotchas found while building the harness

* **`DataConnector::loadPlatform()`'s LTI 1.0 branch reads `$_POST['tool_consumer_instance_guid']`
  directly**, not `$platform->consumerGuid` - only true when `$platform->getRecordId()` is unset
  AND `$platform->platformId` is empty. A real request always has this in `$_POST` (standard LTI 1.0
  launch parameter), so production is unaffected, but it makes this method not a pure function of
  its argument. Flagged, not fixed (out of scope for this harness; a good candidate for a small
  follow-up cleanup, orthogonal to the library version bump).
* **`Config::readByOauthKey()` is dead code with a real bug**: it checks
  `!empty($config['oauth_key'])` (the whole outer config array) instead of `$data['oauth_key']`
  (the current entry), so it can never match anything. Zero callers anywhere in the codebase
  (verified by grep) - left alone rather than "fixed", since fixing unreachable code isn't
  meaningful and this task is scoped to the library update, not an unrelated bug sweep.
* **`savePlatform()` truncates `platformId` to 28 chars** for both the new `Api\Config` storage key
  and the `Platform::setRecordId()` value (matching `Config::save()`'s own "issuer name shortend to
  32 char" truncation), while storing the *full* `platformId` in `$data['iss']`. `Config::read()`'s
  exact-match lookup therefore still needs the untruncated issuer; only the record-id is short.
* **A `Platform` fixture needs `ltiVersion` explicitly set** before calling `savePlatform()` -
  `Platform::initialize()` leaves it `null`, and `substr(null, 0, 3)` (PHP 8.1+ passing null to a
  non-nullable string param) silently yields `''` rather than throwing, so an omitted `ltiVersion`
  produces a config entry stored under the empty-string version instead of a visible error.
* **PHPUnit 12 in this repo does not run bare `@dataProvider` docblock annotations** (confirmed:
  `SessionTest::testIsInstructor` needed the `#[DataProvider('roleProvider')]` attribute instead -
  see `api/tests/CacheTest.php`/`Vfs/PathHelpersTest.php` for the established pattern). Some older
  smallpart tests (`CommentTestReply.php` etc.) still use the docblock form; unclear if those
  data providers currently run at all - not investigated further, out of scope here.
* A course can only become `course_closed=1` via an *update* (`Bo::save(['course_id' => ..., ...])`),
  never at creation - `Bo::save()` auto-subscribes the owner only on create, and that auto-subscribe
  path (`checkSubscribe()`) unconditionally refuses an already-closed course.

## Not covered by the harness (documented gap - verify manually/live after the version bump)

* **The real HTTP entry point** (`index.php` -> `Tool::handleRequest()`'s OAuth1/JWT signature
  verification, the OIDC login redirect dance, `onLaunch()`'s success path which ends in `exit()`,
  and `Tool::contentSelected()`/`onContentItem()` which are only reachable through that dispatch or
  also end in `exit()`). Testing this in-process would mean either refactoring production code to
  remove the `exit()` calls (out of scope - "make focused, minimal diffs") or a subprocess-based
  harness (curl/Guzzle against a real running instance, similar to `api/tests/RestBase.php`'s
  pattern) - judged not worth the effort for this pass given the adapter-level coverage above
  already isolates the exact surface the library update touches. If pursued later, the missing
  piece is generating a validly-signed LTI 1.3 `id_token` (RS256, matching a registered platform's
  JWKS) or an OAuth1-signed LTI 1.0 POST from PHPUnit.
* **`Session::checkSetLocale()`'s preference-write path** (locale mismatch -> `Api\Preferences::add()`
  + `save_repository()`) - not covered; low risk (doesn't touch any library object/constant), skipped
  to keep the harness focused on the library-update surface.
* **Live verification against a real LMS** (Moodle test instance, or smallpart's existing live
  integrations) after the actual version bump - the harness catches API-shape breakage, not spec-
  compliance regressions in the library's own request-verification code.

## Status: done (2026-09-18)

`celtic/lti` is now `v5.4.6` (was `v4.10.3`), pulled in transitively via `smallpart/composer.json`'s
constraint (`^5.4`, smallpart commits `2aaf65d`/`800fd79`). `firebase/php-jwt` moved `v6.0.0 ->
v7.1.1` along with it (celtic/lti v5 requires `^7.0`) - as a side effect this also cleared the one
pre-existing security advisory `composer audit` reported against the old `firebase/php-jwt` v6.

### The non-obvious part: getting composer to see the new constraint at all

Editing `smallpart/composer.json` locally did **nothing** by itself - `composer update celtic/lti`
kept reporting "Nothing to modify in lock file". Root cause: `egroupware/smallpart` (like every
`egroupware/*` app) is installed by composer as a real git-VCS package (constraint `self.version` /
`dev-master`), and composer resolves its `require` section from **the remote GitHub branch tip**
recorded for that package, not from the live working-copy `composer.json` on disk - confirmed by
`composer update egroupware/smallpart celtic/lti --dry-run`, which only updated the lock's smallpart
reference to match a commit already reachable on `origin/master`, and only started proposing a
`celtic/lti` upgrade once the composer.json edit was an actual commit on the pushed remote branch
(2 local commits, `git log`-verified fast-forward, pushed with explicit go-ahead - see repo's
shared-working-copy/no-auto-push note). Local `composer.json` edits to an `egroupware/*` app are
therefore invisible to composer's resolver until pushed; this generalizes to any future
third-party-dependency bump declared in an app's own `composer.json`.

Also needed `--ignore-platform-reqs` on the update command: this box's `composer.json` declares
`config.platform.php: "8.2"` as the minimum-supported floor, but several *already-locked, unrelated*
packages (`lcobucci/clock` 3.6.0, `simplesamlphp/simplesamlphp` v2.5.2, `phpunit/phpunit` 12.5.23)
require PHP >=8.3/8.4 and were already inconsistent with that floor before this task touched
anything - re-validating the whole graph (which any `composer update`, even scoped, triggers) surfaced
that pre-existing tension. Not fixed (out of scope, unrelated to LTI) - `celtic/lti` v5.4.6 itself
only needs PHP >=8.1 and `firebase/php-jwt` v7 only needs ^8.0, both well within the declared 8.2
floor, so the ignored platform check didn't paper over anything LTI-related.

### Fixes applied (smallpart commit `800fd79`)

* `DataConnector.php`: `LTI\Util::LTI_VERSION1P3`/`LTI_VERSION1` -> `LtiVersion::V1P3`/`::V1`
  (`ceLTIc\LTI\Enum\LtiVersion`); `savePlatform()`'s `substr($platform->ltiVersion, 0, 3)` derivation
  of EGroupware's own `'1.3'`/`'1.0'` storage strings replaced with an explicit enum comparison (the
  enum's case values, `'1.3.0'`/`'LTI-1p0'`, don't `substr()` down the same way the old string
  constants apparently did). All 7 overridden `DataConnector` methods (`loadPlatform`,
  `savePlatform`, `loadPlatformNonce`, `savePlatformNonce`, `deletePlatformNonce`, `loadUserResult`,
  `saveUserResult`) gained the base class's now-declared parameter/return types.
* `Tool.php`: `onLaunch()`/`onContentItem()` gained `: void` return types.
* `tests/LTI/DataConnectorTest.php`: its own 4 `LTI\Util::LTI_VERSION1P3` fixture/assertion usages
  updated to the enum too (the harness's own code isn't exempt from the same break).

All 33 `smallpart/tests/LTI/` tests pass against v5.4.6 (`EGW_ADMIN_PASSWORD` env needed), and
`smallpart/tests/BoTest.php` (75 tests, unrelated to LTI) still passes, confirming no collateral
damage. Every fatal error encountered while doing this was pre-empted by an *existing* harness test
- none needed writing during the actual bump.

### Still open (see "Not covered by the harness" above - unchanged, not attempted)

The real HTTP entry point (signed OAuth1/JWT request handling), `checkSetLocale()`'s preference-write
path, and a live-LMS verification pass remain unverified against v5.4.6. Recommended before calling
this fully shipped, especially the live-LMS pass - the harness only proves the adapter code's *shape*
matches the new library API, not spec-level behavioral compatibility.
