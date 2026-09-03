# Testing Guidelines

## General expectations

* Run the most relevant tests for the files you changed.
* Prefer targeted tests first, then broader suites when practical.
* Do not claim tests passed unless they were actually run.
* If tests cannot be run, explain why and identify the risk area.
* Avoid unrelated formatting or snapshot churn.
* Treat test execution as a completion gate: after code edits, run tests first, then report results.
* Do not infer test status from other commands (lint, setup checks, or unrelated failures). Determine status from the
  actual test command(s).
* When adding or modifying tests, document what the test is proving and how pass/fail is determined.

## Required post-change workflow

After editing code, follow this order:

1. Identify the smallest relevant automated test(s) for the changed behavior.
2. Run those test command(s).
3. If blocked (environment, missing service, permissions, etc.), record the exact blocker and command.
4. If there is no direct automated coverage for the changed area, state that explicitly and run the closest relevant
   tests available.
5. Report results in the final response using the format below (`Tests run` / `Not run`).

When no direct tests exist, avoid claiming full verification; call out residual risk and suggest the most relevant
manual check or follow-up test.

## Test documentation expectations

When writing or changing tests, include concise documentation (usually in test docblocks/comments) covering:

1. The behaviour/contract under test.
2. The setup strategy (how the scenario is constructed).
3. The pass criteria (exactly what must be true for success).
4. Any environment-sensitive constraints (for example, recurrence horizon or timezone assumptions) that could affect
   reliability.

This should be specific enough that a reviewer can tell whether a failure indicates a product bug, a test bug, or an
environment issue.

For sync-focused tests (for example CalDAV/iCal), document state transitions explicitly (`new`, `update`, `removal`)
and define pass criteria for each state.

## PHP tests

PHPUnit configuration lives at: `doc/phpunit.xml`
Run PHPUnit with that config:

`vendor/bin/phpunit -c doc/phpunit.xml`

For targeted tests, prefer running the smallest relevant test file or suite when possible:

`vendor/bin/phpunit -c doc/phpunit.xml path/to/TestFile.php`

For many tests we contact the server. Override the base EGroupware URL per environment instead of editing committed
config:

`EGW_URL="http://your-host/egroupware" vendor/bin/phpunit -c doc/phpunit.xml calendar/tests/CalDAV/YourTest.php`

* Tests that extend EGroupware\Api\LoggedInTest will run tests as the logged-in 'demo' user configured in `phpunit.xml`.
* Ask if you don't know the proper host.
* Make sure tests clean up after themselves, even if they fail.

### Developer installs: run PHPUnit inside the Docker container, not on the host

All developer installations are Docker-based. The host machine normally has PHP installed too, but it can NOT be
used to run these tests directly: `header.inc.php`'s db_host (eg. `db`) is a Docker-internal hostname the host
cannot resolve, and the host's copy of the source tree is not guaranteed to be the exact same paths the container
uses. Running `vendor/bin/phpunit` natively on the host hangs instead of failing cleanly - always run it inside the
already-installed, already-running app container instead:

`docker exec egroupware bash -c "cd /var/www/egroupware && vendor/bin/phpunit -c doc/phpunit.xml path/to/YourTest.php"`

(container name may differ per install - check `docker ps`). This works because that container already has a fully
configured, installed EGroupware instance with a working DB connection; `doc/phpunit_bootstrap.php` just connects
into it rather than provisioning a new one.

**Run one PHPUnit invocation at a time.** `doc/phpunit_bootstrap.php` unconditionally shells out to
`doc/rpm-build/post_install.php --install-update-app <app>` for every fixture app under
`api/tests/fixtures/apps/`, on every single run. That script's `--setup-cmd-database sub_command=create_db` step is
not safe for concurrent execution - running several `phpunit` invocations in parallel against the same container
races multiple installs against each other and can hang for an extremely long time (looks identical to `create_db`
processes accumulating in `ps aux` inside the container, each with a different randomly-generated `db_pass`). If
that happens, kill the stray `setup-cli.php`/`post_install.php` processes inside the container and retry
sequentially.

**If that same fixture-app install step is slow or fails even run alone**, it is very likely because
`post_install.php` defaults to checking domain `'default'` in the real `header.inc.php`'s `$GLOBALS['egw_domain']`
array - and that literal key usually does not exist (domains are keyed by their real name). Discover the real
default domain by reading `header.inc.php` directly (`grep "\$GLOBALS\['egw_domain'\]\[" header.inc.php` - the first
entry is conventionally the default one), then pass it via the `EGW_POST_INSTALL` environment variable, which
`post_install.php` parses as extra CLI args:

`docker exec -e EGW_POST_INSTALL="--domain your.real.domain" egroupware bash -c "cd /var/www/egroupware && vendor/bin/phpunit -c doc/phpunit.xml path/to/YourTest.php"`

This is a per-dev-box environment detail (not committed anywhere), so it is worth asking the developer once and
remembering it for the rest of the session rather than rediscovering it every time.

**CI is different and does not need any of the above.** `.github/workflows/testing.yml` spins up a brand-new
container/DB and runs the full installer from scratch on every run, since nothing persists between CI runs - there
is no pre-existing install to connect into, no stray domain-mismatch to work around, and no risk of colliding with
another concurrent test run.

### Admin-only functionality: dedicated admin test account

`demo` (the suite-wide default session, `$GLOBALS['EGW_USER']`/`['EGW_PASSWORD']`, normalized by
`doc/phpunit_bootstrap.php` from `EGW_NONADMIN_USER`/`EGW_NONADMIN_PASSWORD` in `phpunit.xml`,
default `demo`/`guest`) is intentionally a **non-admin** account. Do not grant it admin rights to
make an admin-only test pass - many other tests rely on `demo` genuinely lacking admin rights
(broken-ACL-check regression tests, permission-boundary tests, etc.), and would silently stop
testing anything if `demo` became an admin.

On an install where `demo` has a real, working mailbox (eg. a shared/"Everyone" mail account like
`acc_id=1`, which has no separate mail-password and forwards the current session password straight
through as the IMAP/SMTP/JMAP credential - see `doc/ai/projects/mail-wizard-jmap-oauth.md`), its
login password doubles as its live mail password. Override both via environment on such installs:
`EGW_NONADMIN_USER="..." EGW_NONADMIN_PASSWORD="..." vendor/bin/phpunit -c doc/phpunit.xml`.

Instead, `phpunit.xml` points admin-only tests at `sysop` - the real admin account every EGroupware
install already creates during setup, not a separate test-only account:

```xml
<var name="EGW_ADMIN_USER" value="sysop" />
<env name="EGW_ADMIN_PASSWORD" value=""/>
```

`sysop`'s password is generated fresh per install (in Docker installs, written to
`/var/lib/egroupware/egroupware-docker.install.log`), so it can't be committed to `phpunit.xml`.
It must be supplied via environment instead: `EGW_ADMIN_PASSWORD="..." vendor/bin/phpunit -c
doc/phpunit.xml`. `doc/phpunit_bootstrap.php` normalizes `getenv('EGW_ADMIN_PASSWORD')`/`$_ENV` into
`$GLOBALS['EGW_ADMIN_PASSWORD']`, so test code keeps reading it like any other `phpunit.xml` `<var>`.
CI extracts `sysop`'s password from the install log (as `SYSOP_PASS`, already used elsewhere in the
workflow to provision `EGW_TEST_USER`) and passes it straight through as `EGW_ADMIN_PASSWORD`.

**A previous version of this used a dedicated `demoadmin` test account instead of `sysop`.** That
collided with `demo` in several apps' generous name-matching lookups - eg.
`calendar_import_csv::parse_participants()` resolves a bare name like "demo" via
`importexport_helper_functions::account_name2id()`, which falls back to an `addressbook_bo::search()`
using a `%`-wildcard `LIKE` across `org_name,n_family,n_given,cat_id,contact_email`. `demo`'s email
(`demo@example.org`) and `demoadmin`'s (`demoadmin@example.invalid`) both match `LIKE '%demo%'`, and
with two candidates the wrong one (sorted by surname) could win - causing `calendar`, `infolog`, and
`projectmanager` tests that resolve participants/project-contacts by name to silently look up the
wrong account. Any future dedicated test account name must avoid being a prefix/substring of `demo`
(or of any other real account name) for the same reason.

**Why this exists:** `admin/inc/class.admin_cmd.inc.php::_check_admin()` (called by
`admin_cmd_edit_user`, `admin_cmd_edit_group`, `admin_cmd_delete_account`, `admin_cmd_account_app`,
`admin_cmd_change_pw` - but NOT `admin_cmd_acl`, `admin_cmd_config`, or `admin_cmd_edit_preferences`,
which never call it) verifies that whoever is logged in when the command object is *constructed*
(`$this->creator`, captured in the constructor) is a real admin, and throws
`Api\Exception\NoPermission\Admin` otherwise. Before 2026-08-15 this check had a `&&`/`||` logic bug
that made it a silent no-op for the common zero-argument call shape; once fixed, any test that
constructs one of the affected `admin_cmd_*` classes while logged in as `demo` now genuinely fails.

**How to use it**, in any test extending `LoggedInTest` (directly or via `AppTest`/`WidgetBaseTest`/
`CommandBase`/etc.):

```php
// admin_cmd_edit_user/_edit_group/etc. require the CURRENT session to be a real admin
$this->switchUser($GLOBALS['EGW_ADMIN_USER'], $GLOBALS['EGW_ADMIN_PASSWORD']);
$command = new \admin_cmd_edit_user(false, $account);
$command->comment = 'Needed for unit test ' . $this->getName();
$command->run();
$this->account_id = $command->account;
$this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']);
```

Gotchas, all found the hard way while fixing the 2026-08-14/15 security-fix test fallout:

* **Capture session-derived values *before* switching.** If the affected call's arguments reference
  `$GLOBALS['egw_info']['user']['account_id']`/`account_lid'` (eg. adding the current test user to a
  new group's members), read that value into a local variable *before* calling `switchUser()` to the
  admin account - otherwise you silently capture the admin's id instead of the original session's.
* **A later `admin_cmd_acl`/`admin_cmd_config`/`admin_cmd_edit_preferences` call in the same method
  does NOT need admin rights** (unaffected classes) and, if it needs to act as/target the original
  session's account, should run *after* switching back - not swept into the same admin-session bracket
  "for safety."
* **Tests using `expectException()`**: wrap the `admin_cmd_*` construction+`run()` in `try { ... }
  finally { $this->switchUser($GLOBALS['EGW_USER'], $GLOBALS['EGW_PASSWORD']); }` so the session is
  restored even though the call is expected to throw - a plain sequential switch-back line never
  executes in that case, leaving the session stuck as admin for whatever test runs next.
* **Static contexts** (`setUpBeforeClass()`): `switchUser()` is an *instance* method, unavailable
  without `$this`. Replicate its body directly - `\EGroupware\Api\LoggedInTest::tearDownAfterClass();
  static::load_egw($user, $password);` - but use the **fully-qualified base class name**, never
  `self::tearDownAfterClass()`. If the test class itself overrides `tearDownAfterClass()` (common, for
  its own fixture cleanup), `self::` resolves to *that* override (compile-time binding to the class
  where the code is written), silently deleting whatever fixtures you just created instead of just
  logging out.

Before changing PHP behaviour:

* Check for existing tests covering the same app or API area.
* Look for similar test patterns before adding new ones.
* Prefer regression tests for bug fixes.
* Recommend creating tests before making changes when the area has no test coverage.
* Be careful with tests that depend on database state, setup state, user permissions, or external services.
* Make sure the test tests one thing only.
* Add clear fail messages into the assertion calls.
* Check for usable test base classes and helpers in api/tests to reduce code duplication and get a working EGroupware
  session.

## Web component tests

Web component tests use `web-test-runner`.

Run the relevant web component tests with the project’s npm script when available:

`npm test`

Or, when using the direct runner:

`npx web-test-runner`

For targeted frontend work:

* Run tests close to the changed component first.
* Preserve existing test conventions.
* Avoid broad rewrites of tests unless the component behaviour changed significantly.

For writing/reviewing timing-sensitive tests around `Et2Datagrid`/`Et2Nextmatch`'s
`requestAnimationFrame`/`setTimeout`-driven virtualizer, scroll, and debounce behavior, see
`api/js/etemplate/Et2Datagrid/test/TestTimingNotes.md`.

## Browser / manual verification

When a checklist or task calls for verifying UI behavior "at mobile viewport" or "on mobile":

* Shrinking a desktop browser window's width is **not** a mobile test, even at phone-sized dimensions (e.g.
  390-500px). EGroupware selects its mobile template/skin server-side based on the request's `User-Agent` header at
  page load — a plain window resize only changes the viewport of the already-rendered desktop template's CSS. The
  desktop layout was never designed to reflow that narrow, so a resized-but-not-emulated window can show rows/columns
  collapsing or content disappearing that has nothing to do with the real mobile template and does not reproduce for
  actual mobile users.
* To test mobile for real: use a tool that does full device emulation (mobile `User-Agent` override, touch input,
  device pixel ratio) — e.g. the Browser pane's `resize_window` with the `mobile` preset — **and then reload the
  page**, so the server-side template selection re-runs against the emulated user agent. A resize alone, without a
  reload, leaves the already-fetched desktop template in place.
* A window-resize-only pass (no UA emulation, no reload) is not sufficient evidence to mark a "mobile viewport"
  checklist item verified. If only that kind of check was possible, say so explicitly rather than reporting the item
  as covered.

### Hidden/backgrounded tabs produce fake UI bugs - always verify tab visibility

Browser automation tools (Claude in Chrome, the Browser pane, CDP-driven tools generally) frequently keep their
controlled tab in a backgrounded state - `document.hidden === true` / `document.visibilityState === "hidden"` -
even while it still accepts clicks, JS execution, and screenshots. Chrome throttles or fully pauses several
browser-internal mechanisms for hidden tabs (`requestAnimationFrame` is paused entirely; `setTimeout` is
throttled/delayed), and this can produce convincing-looking "stuck" UI states that **do not reproduce on a
genuinely visible, focused tab** and are not real product bugs.

* **Before trusting any repro of a "stuck", "frozen", or "never resolves" UI symptom, check
  `document.hidden`/`document.visibilityState` on the automation tab first.** If it's hidden, the repro is not
  trustworthy evidence on its own - re-test on a tab you've confirmed is genuinely visible before concluding
  anything is (or isn't) broken.
* If you can't get a genuinely visible/focused tab through the automation tooling available to you, **say so and
  ask the user to focus a real browser tab and leave it focused** while you drive it, rather than proceeding on a
  hidden tab and reporting the result as verified. Don't silently accept a hidden tab as "good enough."
* `Et2Datagrid` (and its virtualized/scroll-driven rendering in general) is a repeat offender here: it has produced
  false leads from hidden-tab testing in more than one investigation - a fully-paused `requestAnimationFrame`-driven
  row-upgrade queue looking permanently stuck, and a `setTimeout`-based fetch-dispatch debounce appearing to
  "starve" forever - both looked identical to real bugs but did not reproduce once re-tested on a genuinely visible,
  focused tab. Treat any "stuck placeholder row" / "loading spinner never clears" report against this component with
  extra suspicion until visibility is confirmed - the underlying bug, if any, may be much narrower (or different)
  than what a hidden tab first suggests.

## When changing setup or schema code

For changes involving setup, database schema, migrations, or upgrade paths:

* Check app-specific setup files.
* Confirm whether upgrade logic is required.
* Consider both new installs and existing installations.
* Mention any install/update paths that were not tested.

## When changing shared API code

Shared framework code can affect many apps.

For changes under `api/`:

* Search for callers before editing behaviour.
* Run the most relevant PHP tests.
* Consider whether calendar, addressbook, mail, filemanager, setup, or admin behaviour may be affected.
* Document any cross-app risk in the final summary.

## Final response format

When reporting test results, include:

```
Tests run:
- <command or summary>

Not run:
- <reason>
```

Examples:

```
Tests run:
- vendor/bin/phpunit -c doc/phpunit.xml calendar/tests/SomeTest.php

Not run:
- Full PHPUnit suite; targeted test covered the changed behaviour.
```

```
Tests run:
- npm test

Not run:
- PHP tests; change was limited to web components.
```
