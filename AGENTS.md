# AI Agent Instructions

This file is the canonical instruction source for AI coding agents working on this repository.

## Core principles

- Make focused, minimal diffs.
- Preserve existing architecture and coding style.
- Inspect nearby code and follow established patterns.
- Prefer modern standards and APIs where they fit the existing codebase.
- Do not rewrite whole files unless necessary.
- Do not introduce unrelated formatting churn.
- Present a plan and ask before making broad architectural changes.
- When uncertain, ask for clarification before making changes.

## Project context

## EGroupware project context

EGroupware is a large PHP/TypeScript/JavaScript web groupware application. The main repo contains many first-party apps
such as `api`, `admin`, `calendar`, `addressbook`, `mail`, `filemanager`, `infolog`, `timesheet`, `resources`, `setup`,
and others. Do not assume changes are isolated to one app without checking shared `api` and `setup` code.

### Repository shape

- Backend code is primarily PHP.
- Frontend code includes TypeScript, CSS, HTML, and build tooling.
- Shared backend framework code lives under `api/`.
- Frontend framework code lives under `kdots/` and `api/js/etemplate`.
- Database setup and upgrade logic lives under `setup/` and app-specific setup directories.
- Individual applications live in top-level directories such as `calendar/`, `addressbook/`, `mail/`, `infolog/`, and
  `timesheet/`.

Primary expectations:

- Respect EGroupware conventions.
- Maintain backwards compatibility unless the task explicitly says otherwise.
- Prefer incremental, reviewable changes.
- Avoid speculative abstractions.
- Develop a plan before making changes.
- Keep UI, API, and database changes aligned with existing patterns, but mention improvements to match modern best
  practices.

## Code change rules

- Read the relevant files first.
- Search for existing implementations before adding new ones.
- Identify the smallest safe change.
- Keep diffs small and app-scoped when possible.
- Before modifying app behaviour, check whether the pattern is implemented in another EGroupware app. Suggest
  refactoring when appropriate.
- Avoid changing shared `api/` behaviour unless the task requires it.
- For schema or setup changes, check app setup files and update paths.
- Preserve backwards compatibility for existing installations.
- Do not remove legacy compatibility code without explicit approval.
- Check whether tests, migrations, translations, or documentation need updates.
- Any new user-facing phrase (PHP `lang('...')`, JS `egw.lang('...')`, or `label=`/`value=`/`placeholder=`/etc.
  text in `.xet` templates) must be added to `$app/lang/egw_en.lang`, and translated in `$app/lang/egw_de.lang`
  when feasible. Files are tab-separated: `<phrase-lowercased>\t<app-name>\t<lang-code>\t<translation>` (translation
  keeps original casing/punctuation). Check both `en` and `de` files before adding, to avoid duplicate keys.
  - `common` is a pseudo app-name (not a real app) for phrases needed everywhere - most of `api/lang/*` uses it,
    but ANY app's lang file can add a row tagged `common` too (eg. `mail/lang/egw_en.lang` has
    `mail\tcommon\ten\tMail` for the app's own display name, so other apps can show "Mail" without redefining
    it). Before adding a phrase, grep for it tagged `common` across all lang files (not just `api/lang/`) - skip
    adding it again if found, regardless of which file it lives in.
  - Determine the *correct* app-name by checking which apps' lang files are actually guaranteed to be loaded for
    the code path the phrase lives in, not just which directory the source file is in. Check every class involved
    for an explicit `Api\Translation::add_app(...)` in its constructor - both directions matter, not just the one
    you're editing. Example (verified both ways): `admin_mail` is also reachable via the mail app's account wizard
    (`mail_wizard extends admin_mail`), and `admin_mail::__construct()` force-loads `mail`'s lang file
    (`Api\Translation::add_app('mail')`) - but `mail_wizard::__construct()` *also* force-loads `admin`'s
    (`Api\Translation::add_app('admin')`), so for this particular pair both lang files are cross-loaded regardless
    of entry point, and a phrase used by either class can go in either `admin/lang/*` or `mail/lang/*`. Don't
    assume a one-directional gap from checking only the class you're touching - check the other side too.
- When modifying a `.xet` file under an app's `templates/default/`, check for a `templates/mobile/` counterpart
  with the same template id (`<template id="...">`) and apply the equivalent change there too, and vice versa.
  These commonly drift independently - eg. a stale `autoloading=`/menuaction attribute cleaned up on one device's
  template but left behind on the other's, even though both share the same JS app class and `et2_ready()` logic.
- Do not make commits without explicit instructions.
- For major/user-visible features (not routine fixes/refactors), the commit message's first line must be
  `* <app-name>: <message>` (eg. `* mail: add S/MIME CSR export/import`), so it gets picked up by the automated
  release-changelog parser. Only the first line is parsed - keep it short and phrase it for end users, not
  developers (no internal class/method names, no implementation detail). Further lines are for developers and are
  not parsed.
- Do not modify generated JavaScript files, they're automatically built.

## Coding standards

See `doc/etemplate2/pages/tutorials/web-component-authoring.md` for information on coding standards and best practices
for webComponents.

For standing incremental-modernization rules (jQuery removal, preferred ajax patterns, PHP warning
hygiene, ...) that apply whenever you touch a section of code, see `doc/ai/modernization.md`.

## Testing

See `doc/ai/testing.md`.

Before finalizing:

- Run the most relevant available tests when practical.
- If tests cannot be run, state why.
- Mention any untested risk areas.

## Reviews

For code review behaviour, follow `doc/ai/review-checklist.md`.

## Ongoing/major project docs

Larger, multi-session efforts get a dedicated doc under `doc/ai/projects/` instead of living only in
session notes - check there before starting related work, and add one when starting a project of
similar scope.

- `doc/ai/projects/mail-jmap-modernization.md` - mail app's move to JMAP (client-side row-fetch/body
  rendering, server-side `Api\Mail` JMAP-native dispatch for Stalwart, local JMAP shim for plain
  IMAP accounts). Covers architecture, current status, deliberately-out-of-scope areas, and known
  gotchas.
- `doc/ai/projects/jsapi-modernization.md` - `api/js/jsapi`'s TS-typing port and follow-on
  factory-closure-to-class conversion (`egw.extend()`'s ~20 modules). Covers the enumerable-merge
  constraint that shapes every conversion, the `#private`-vs-TS-`private` field bug class, the
  dynamic-`this`/self-capture patterns, deliberately-out-of-scope files, postponed jQuery removal,
  and every preserved-not-fixed `KNOWN BUG`/`KNOWN QUIRK`.
- `doc/ai/projects/et2-nextmatch-conversion.md` - per-app migration from the legacy
  `et2_extension_nextmatch` widget (`<nextmatch>`) to the `Et2Nextmatch` web component
  (`<et2-nextmatch>`). Covers the template-rename checklist, the legacy-widget-API-to-`Et2Nextmatch`
  replacement table for app JS/TS, lifecycle timing pitfalls, and the `columnselection_pref` ->
  `columnPreferenceName` audit/fix for apps already converted.
- `doc/ai/projects/mail-bo-decoupling.md` - breaking `Api\Mail`/`mail_ui` apart into smaller,
  independently-testable components, to fix the "large heavily-coupled legacy class with no test
  coverage" problem shared by those two and `MailApp` (client-side). Phase 1 (4 low-risk `Api\Mail`
  groups) done; covers the full method inventory, per-group coupling/risk assessment, and the
  extraction discipline that emerged (no wrapper unless a separate-repo consumer needs it; delete
  confirmed-dead code; re-check "no callers" case-insensitively for PHP method names).
- `doc/ai/projects/mail-folder-tree-jmap.md` - planned migration of the mail folder-tree
  (listing/autoloading/CRUD) from server-side PHP to client-side + JMAP, plus persisting tree
  expand/collapse state per user. Covers why this must happen before decoupling the overlapping
  `Api\Mail`/`mail_ui` folder groups, and the hard constraint that admin-impersonation of another
  user's mailbox (`mail_acl.inc.php`) can never move client-side.
- `doc/ai/projects/infolog-storage-migration.md` - planned replacement of InfoLog's hand-rolled
  `infolog_so` SQL backend with the generic `Api\Storage` class, to get automatic `Api\DateTime`/
  timezone handling and built-in custom-field support instead of InfoLog's parallel
  implementations of both. Covers the full `infolog_so`/`infolog_bo` method inventory, the
  non-UI consumer map (CalDAV/REST, ActiveSync/z-push, cross-app callers), the all-day-across-
  timezones semantic gap that needs a product decision, and the phased plan (test harness first,
  then swap only what's behind `$this->so`, external contract unchanged until a later phase).

## Security and data handling

- Do not commit secrets, tokens, credentials, private keys, or production data.
- Do not weaken authentication, authorization, validation, escaping, or CSRF protections.
- Treat user input as unsafe.
- Preserve existing permission checks.
- In an `Etemplate`, any `$content` key with NO corresponding widget in the `.xet` template is read-only from the
  client's perspective: `Etemplate::exec()` sends the whole `$content` array to the browser regardless of widgets
  (so `this.et2.getArrayMgr('content').getEntry('key')` works client-side with no widget needed), but the client
  can only ever submit values back for keys that DO have a widget. Sensitive/authorization-relevant values (an
  internal row id, an "acting on behalf of user X" id, etc.) that must survive across submits should be passed via
  `$preserv` (the `exec()` parameter, commonly just `$content` itself) and deliberately given NO widget - adding a
  `<hidden id="...">` for one makes it client-writable on every subsequent submit, not just readable. Also apply
  this to any new ajax method (`ajax_*`): its `$_data` payload is a fully untrusted raw request, not merely
  "whatever the legitimate UI would send" - explicitly re-verify the caller is authorized for every id/account
  it references (found via review: a new `ajax_smimeCreateKeypair()` endpoint trusted a client-supplied
  `account_id`/`acc_id` with no ownership check at all).
- `Api\Mail\Credentials` can encrypt a stored credential (IMAP/SMTP/S-MIME password, etc.) with either a
  system-wide secret (`SYSTEM_AES`) or the owning user's OWN session password (`USER_AES`) - the latter is
  chosen automatically whenever the credential is written for the currently-logged-in user's own
  `account_id` (`Credentials::encrypt_openssl_aes()`). A `USER_AES`-encrypted credential can genuinely NOT
  be decrypted by anyone else, including an admin - `decrypt()` returns `Credentials::UNAVAILABLE` for any
  session that isn't that same user's. This is by design (a real security boundary), not a bug: if a
  credential an admin wrote for a user (`SYSTEM_AES`, admin-readable) later gets re-encrypted because the
  user touched it themselves (now `USER_AES`), the admin correctly loses access - surface that as "not
  available to you" in error messages, don't try to work around it.

## File downloads

Do NOT trigger a file download from JS by creating a synthetic `<a>` with a `download` attribute and
calling `.click()` on it. Confirmed unreliable: it can silently fail to send ANY request to the server at
all (empty network tab, no console error), observed specifically from inside an EGroupware popup window.

Use `Etemplate2.postSubmit(button)` instead (`api/js/etemplate/etemplate2.ts`) - it builds and submits a
real `<form method="POST">` targeting the template's own `EGroupware\Api\Etemplate.process_exec` endpoint,
which reliably reaches the server and lets it respond with the file directly. Existing usage examples:
`calendar/js/app.ts` (`category_report_submit()`, `postSubmit()` call near line 4342),
`infolog/js/app.ts` (`postSubmit()` near line 518), `api/js/etemplate/Et2Dialog/Et2Dialog.ts`.

Pattern:
- Client: give the trigger widget a plain, flat `id` (NOT `button[...]` bracket notation - that's a
  different, `_set_button()`-specific mechanism for the main save/apply/delete buttons), plus
  `noSubmit="true"` so et2's own default click-submit doesn't also fire, and
  `onclick="app.<app>.<method>"` where the handler calls `this.et2.getInstanceManager().postSubmit(_widget)`.
- Server: `postSubmit()` routes back to the SAME controller method that rendered the template (via the
  stored `etemplate_exec_id`, merging `$preserv` with the newly submitted/validated values - see
  `Etemplate::process_exec()`). That method checks `!empty($content['<that same flat id>'])` - a clicked
  flat-id widget's own value is submitted directly as `$content[id]`, NOT nested under
  `$content['button'][...]` - then emits the file (`Api\Header\Content::safe()` + `echo` + `exit()`)
  instead of falling through to the normal `$tpl->exec()` redraw.
- Bonus: because this reuses the same controller/menuaction as the rest of the form, all the usual
  server-trusted content (`$preserv`-carried ids, etc.) is already available - no separate URL-param
  threading needed for a GET-based download endpoint.

## Output expectations

When reporting work:

- Summarize what changed.
- Mention tests run.
- Mention known limitations or follow-up risks.
