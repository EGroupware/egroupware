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
