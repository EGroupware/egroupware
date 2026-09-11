# Continuous integration for an EGroupware app

If your app lives in its own repository, it can call EGroupware's test workflow to get a full
EGroupware instance built, your app installed into it, and your app's tests run — without copying
any of that setup into your repository.

> **This is EGroupware's own CI tooling, shared because it is useful.** It is not a product with a
> stability guarantee: its inputs and behaviour can change without notice, and a change can break
> your build. If that matters to you, pin `uses:` to a tag or commit sha rather than `@master` and
> move deliberately.

## The minimal setup

Add `.github/workflows/app-testing.yml` to your app's repository:

```yaml
name: My App Testing

on:
  push:
    branches:
      - master
  pull_request:
  workflow_dispatch:

permissions:
  contents: read

jobs:
  test:
    uses: EGroupware/egroupware/.github/workflows/testing.yml@master
    with:
      # The app(s) to check out into the tree. "dir" is the directory the app installs
      # as, needed when it differs from the repository name. "ref" defaults to master;
      # head_ref/ref_name below tests the branch that triggered the run.
      extra-apps: >-
        [{"repo": "myorg/myapp", "dir": "myapp",
          "ref": "${{ github.head_ref || github.ref_name }}"}]

      # Run only your app's tests. Without this, EGroupware's entire suite runs too,
      # which takes far longer and tells you nothing about your change.
      test-scope: extra-apps

      # ---- optional: use only if you need them ----

      # Which of the extra-apps to run tests for. Defaults to all of them - set it when
      # you check out another app as a dependency and don't want its suite to run.
      # test-apps: myapp

      # Which EGroupware revision to test against. Defaults to master. Pin a release
      # tag to check your app against a release.
      # egw-ref: '26.1'

      # A different EGroupware repository, eg. your own fork.
      # egw-repo: myorg/egroupware

      # Organisation owning the extra-apps repositories. Only needed when
      # authenticating with a GitHub App, see "Private repositories" below.
      # extra-apps-owner: myorg

    # ---- optional: only for private repositories ----
    # secrets:
    #   # Your app's repository alone:
    #   extra-apps-token: ${{ secrets.GITHUB_TOKEN }}
    #   # Or, to reach a second private repository, a GitHub App installed on both:
    #   extra-apps-app-id: ${{ secrets.MY_APP_ID }}
    #   extra-apps-private-key: ${{ secrets.MY_APP_PRIVATE_KEY }}
```

A public repository needs no credentials at all — it is cloned anonymously.

## What it does

1. Checks out EGroupware and installs its composer dependencies.
2. Clones the repositories in `extra-apps` into the tree.
3. Starts MariaDB and EGroupware in docker and installs every app present, including yours.
4. Runs the tests, then tears the containers down.

Your tests are found automatically, so nothing needs registering:

- **PHP**: `<app>/tests/**/*Test.php`, run with PHPUnit.
- **JavaScript**: `<app>/js/**/*.test.ts`, run with web-test-runner in Chromium and Firefox.

A PHP test that needs a logged-in EGroupware session should extend `EGroupware\Api\AppTest`. That
class is not autoloadable, so require it explicitly — every app in the tree does this:

```php
require_once realpath(__DIR__.'/../../api/tests/AppTest.php');
```

## Inputs

| input | default | meaning |
|---|---|---|
| `extra-apps` | none | JSON array of `{repo, dir, ref}` to check out. `dir` defaults to the repository name, `ref` to `master`. |
| `test-apps` | all of `extra-apps` | Space-separated subset whose tests actually run. |
| `test-scope` | `all` | `all` also runs EGroupware's own suite; `extra-apps` runs only your app's tests. |
| `egw-repo` | `EGroupware/egroupware` | Which EGroupware repository to test against. |
| `egw-ref` | `master` | Branch, tag or sha of it. |
| `extra-apps-owner` | none | Organisation owning the `extra-apps` repositories, for GitHub App authentication. |

Secrets: `extra-apps-token`, or `extra-apps-app-id` plus `extra-apps-private-key`.

## Private repositories

`GITHUB_TOKEN` is limited to the repository containing the workflow. So:

- **Your app only** — pass the built-in token, nothing to set up:

  ```yaml
      secrets:
        extra-apps-token: ${{ secrets.GITHUB_TOKEN }}
  ```

- **Your app plus another private repository** — the built-in token cannot reach the second one.
  Use a GitHub App installed on both (set `extra-apps-owner`, and pass `extra-apps-app-id` and
  `extra-apps-private-key`), or a fine-grained token with read access to both as
  `extra-apps-token`. The App token is minted inside the workflow, because such a token is revoked
  when the job that created it ends and so cannot be handed over by a calling workflow.

## Depending on other apps

Apps that ship with EGroupware — `api`, `infolog`, `projectmanager` and so on — are already in the
tree. Nothing to do.

If your app needs another app that is *not* part of EGroupware, add it to `extra-apps` and leave it
out of `test-apps`, so it is installed without its suite being run:

```yaml
      extra-apps: >-
        [{"repo": "myorg/otherapp", "dir": "otherapp"},
         {"repo": "myorg/myapp", "dir": "myapp",
          "ref": "${{ github.head_ref || github.ref_name }}"}]
      test-apps: myapp
```

The `depends` you declare in `setup.inc.php` cannot be used for this: it names an app, not a
repository, so there is no way to work out where to clone it from. It is also not enforced at
install time — `setup-cli.php --update <app>` installs a named app without checking its
dependencies — so whether you really need another app present is a question about what your code
does, not about what that declaration says.

## Running it regularly

Your app is tested against whatever EGroupware master is at the time the run happens. Running on
push and pull request tells you about *your* changes; it says nothing about an EGroupware change
that breaks you tomorrow. A scheduled run covers that:

```yaml
on:
  schedule:
    - cron: '23 5 * * 1'   # Mondays, off the hour: scheduled runs are contended at :00
```

Pick a cadence that matches how much EGroupware drift you want to absorb at once. Note that GitHub
disables scheduled workflows in a repository with no activity for 60 days.

## Notes

- On a pull request the workflow tests the head of the branch, not the merge result.
- If your app has its own `composer.json`, its dependencies are installed into your app's own
  `vendor/` directory. Commit a `composer.lock` if you want that resolution to be reproducible.
- `uses: ...@master` follows EGroupware's master, including changes to the CI setup itself. See the
  note at the top.
