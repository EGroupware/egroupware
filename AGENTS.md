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
- A `.css` file that has a `.less` file of the same name next to it (eg. `mail/templates/default/
  app.less` / `app.css`) is compiled output, checked into git for convenience - never hand-edit it.
  Edit the `.less` source, then regenerate the `.css` with `lessc <file>.less <file>.css` and commit
  both together in the same commit. Before trusting a recompile, diff it against the currently
  checked-in `.css` first: a plain `lessc` run should reproduce it byte-for-byte when the `.less`
  hasn't changed yet - if it doesn't, the two are already out of sync (eg. from a past hand-edit of
  the `.css` only), and blindly overwriting the `.css` would silently delete real, already-shipped
  rules. Port the missing content into the `.less` source first (keeping it byte-identical once
  compiled), then make your actual change on top of that.

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

The list below is capped at the 10 most recently updated projects, most-recent first. After any
update to a project's own doc, move its bullet to the top of this list (creating one if it's new),
and drop the 11th entry if that pushes the list over 10 - dropping it here does not touch the doc
file itself. The full set of project docs, including everything trimmed off this list, always
lives in `doc/ai/projects/` - check there directly for anything not shown here.

- `doc/ai/projects/mail-zpush-jmap-folders.md` - scoping the real fix for ActiveSync
  (`mail_zpush.inc.php`) breaking on any Stalwart-migrated account: it implements no JMAP-specific
  methods at all, so `Api\Mail`'s folder-management methods it calls (`getFolderStatus()`,
  `folderExists()`, `createFolder()`/`renameFolder()`/`deleteFolder()`, `getFolderObjects()`,
  `reopen()`) fall through to raw `Horde_Imap_Client_Socket` calls neither `Imap\Jmap` nor
  `Imap\Stalwart` override - found live via egroupware.org's own Stalwart migration breaking
  ActiveSync's "Ping" mechanism entirely. Full method-by-method map of all 33 `Api\Mail` methods
  `mail_zpush` calls: every message-level operation is already properly JMAP-native (dispatches to
  a private `jmap<Method>()` helper or an inline guard) - the gap is narrowly confined to 7-8
  folder-management methods. Proposes adding JMAP-native `Imap\Jmap` overrides for exactly those
  (`getMailboxes`/`getStatus`/`mailboxExist`/`openMailbox`/`createMailbox`/`renameMailbox`/
  `deleteMailbox`/`subscribeMailbox`/`listSubscribedMailboxes`), following the same `Mailbox/get`/
  `Mailbox/set` template `getACL()`/`setACL()` already established - a bounded, single-class fix,
  not part of [[mail-jmap-imap-inversion]]'s much larger Phase 2. A narrow, account-specific
  stop-gap already shipped and is confirmed working live (`96c1f784ae`/`5280f53e10`); this doc scopes
  the general fix every other Stalwart-migrated customer still needs. Not started.
- `doc/ai/projects/mail-jmap-imap-inversion.md` - corrected (2026-10-29) a wrong 2026-08-26 Phase-2-
  scoping claim that `mail_zpush.inc.php` was "not a real consumer" of unguarded raw-IMAP calls - that
  screening only checked its own direct `icServer->` references, missing that it's a heavy consumer
  of `Api\Mail` methods that call `icServer->` two or three layers down (see
  [[mail-zpush-jmap-folders]], found via the same live outage). Phase 1 (restructuring already-JMAP-
  native code into the new `Api\Jmap`/`Mail\Jmap` object model) stays committed+pushed from
  2026-08-26; Phase 2 (making `Api\Mail` itself JMAP-shaped) stays scoped-but-not-started - still no
  easy slice found among its own obvious candidates, independent of today's correction.
- `doc/ai/projects/sql-fragment-type-safety.md` - closing GHSA-vx89-j2x7-5wp2's vulnerability
  *class* (not just its two entry points, already patched separately): `Api\Db` let a caller splice
  raw SQL into a `$where`/`$cols`/`$data` array via an integer array key, indistinguishable from an
  attacker-influenced array that merely happens to contain one. New `Api\Db\SqlFragment` makes "this
  is deliberately raw SQL" an explicit value type; `Api\Db::requireSqlFragmentObjects()` is a
  per-instance opt-in that then throws `Db\Exception\InvalidArgument` for a non-`SqlFragment` value
  at an integer key. Infrastructure done (commit `a46f539449`); calendar fully converted as the pilot
  (commit `196de24beb`, 61+11+2 sites across `calendar_so`/`calendar_boupdate`/`calendar_uilist`,
  `requireSqlFragmentObjects(true)` enabled on calendar's own cloned `Db` instance) - also fixed one
  core-framework instance of the same bug found along the way (`Db::replaceAsUpdate()`). The
  per-instance *clone* (vs. the shared global connection) is a **temporary** stopgap for the
  transition period - the doc keeps a list of every clone site to revert once
  `requireSqlFragmentObjects()` becomes the default (opt-out) for `Api\Db` generally. Master only, no
  26 backport (the actual vulnerability is already closed on both branches via the entry-point
  patches). Next: `Api\Storage`/`Api\Storage\Base` (in progress) - the shared SO-layer base class many
  apps extend; a plain grep for the append idiom was already proven insufficient on calendar (raw
  fragments also show up as bare inline array-literal elements and under other variable names), so
  this needs the same per-call-site reading, not just a pattern search.
- `doc/ai/projects/smallpart-s3-videos.md` - design for adding S3 storage to smallPART/ViDoTeach
  videos (ticket #89091 item 4), currently plain files served directly by nginx with zero PHP in the
  serving path - a requirement that rules out a literal copy of `Stylite\Vfs\S3\StreamWrapper`
  (every VFS read goes through its stream wrapper for a cache-miss download, incompatible with
  nginx's static `alias`). Reuses `stylite/src/Vfs/S3/StorageTrait.php` as-is (same storage config,
  multi-storage redundancy, automatic >64MB multipart upload) with a `smallpart-video/<video_hash>`
  key prefix in the same bucket as VFS/backups. New columns on `egw_smallpart_videos`
  (`video_s3_flags`, `video_aes_key`, `video_s3_lru`, `video_s3_lock_started`) drive an async
  migrate-to-S3 job and a daily local-cache-eviction job. The "is this video's file still local"
  check moves into the student UI's pre-playback path (two call sites: server-side in
  `videoSrc()`/`readVideo()` for a normal page load, a new ajax method for the client-side
  already-loaded-metadata video-switch path found in `app.ts`'s `ltiVideoSelection()`) instead of an
  nginx `error_page`/`X-Accel-Redirect` fallback, so zero nginx config changes are needed anywhere.
  Concurrent cold-miss downloads are guarded by a DB compare-and-swap claim (`DOWNLOADING` bit +
  stale-lock timeout) rather than filesystem locking, since Hosting's `$files_dir` is NFS shared
  across pods/k8s nodes where `O_EXCL`/`flock()` have known reliability gaps; the actual download is
  staged to a temp path and `rename()`d into place atomically so nginx never serves a partial file.
  Design/discussion phase only, no code written yet - see the doc's "Open questions" for what's still
  undecided (an LRU-fallback column vs. `filemtime()`, re-upload orphaned-key cleanup, a possible
  new-upload eviction grace period).
- `doc/ai/projects/et2-nextmatch-conversion.md` - per-app migration from the legacy
  `et2_extension_nextmatch` widget (`<nextmatch>`) to the `Et2Nextmatch` web component
  (`<et2-nextmatch>`). Covers the template-rename checklist, the legacy-widget-API-to-`Et2Nextmatch`
  replacement table for app JS/TS, lifecycle timing pitfalls, and the `columnselection_pref` ->
  `columnPreferenceName` audit/fix for apps already converted. Admin is now converted (all nine of
  its lists, desktop + mobile), which added: a nextmatch in a *popup* gets no filterbox at all
  (`Nextmatch::beforeSendToClient()` returns early for `output_mode === 2`), `num_rows => 0` no
  longer defers the initial fetch, `class="hide"` no longer survives `set_disabled(false)`, a `<row
  class="$field">` direct binding fails where `$row_cont[field]` works, and a list sharing its page
  with another widget needs `layout="stack"` - whose `grow="1"` companion does not survive `.xet`
  parsing, because `transformAttributes()` only reflects attributes a widget declares. Unconverted
  templates now fall back to `Et2Nextmatch` automatically (`api/etemplate.php`, opt out with
  `legacy="true"`) - a stopgap, not a conversion.
- `doc/ai/projects/nextmatch-action-ajax-conversion.md` - moving nextmatch context-menu actions off
  the full eTemplate submit they silently fall through to (no `onExecute`/`url`/`egw_open` ->
  `nm_action: "submit"` -> `index()` re-runs -> a brand new nextmatch, losing scroll, selection and
  row state), plus picker dialogs replacing the actions that render one sub-menu entry per row of
  user data (categories, distribution lists, addressbooks, share targets). Phases 0-6 DONE
  (addressbook, infolog, tracker, calendar, timesheet, filemanager, projectmanager; the generic
  overflow dialog and its `…` affordance; the category and distribution-list dialogs; the
  `open_popup` bucket). Phase 7 is empty and the rest is blocked on `et2-nextmatch-conversion.md`,
  because the legacy `nm_action()` dispatcher is deliberately NOT touched - an app is only
  reachable once its list template uses `<et2-nextmatch>`. Master only, no 26 backport; spans 4
  repos (tracker, projectmanager and records are separate). Read it for the measured per-app
  inventory (three passes, because a source scan alone gives both false positives and negatives),
  the `onExecute`-inheritance lever that converts a whole dynamic submenu in one line, the
  submenu-vs-dialog rule and per-app category cardinality, and the 2026-09-23 in-browser
  verification run - which found three bugs no code-reading pass would have: `csv_export` sticking
  in the stored nextmatch value and silently freezing the session's cached query (so "select all"
  and `delete_list` used page-load-time filters), popup inputs resolved against the whole template
  instead of their own popup (infolog's Start date shares an id with a filter, so setting it
  cleared it), and calendar's endpoint living on `calendar_uilist` with no `menuaction` declared.
- `doc/ai/projects/knowledgebase-app.md` - design of a brand-new `knowledgebase` app to supersede
  the deprecated `phpbrain` (Knowledge Base) and `wiki` apps, built on `Api\Storage`/
  `Api\Storage\Tracking`/`Api\Categories`/`Api\Acl` rather than either legacy app's bespoke
  persistence/ACL/history. Covers the 3-pane (category tree / nextmatch list / document view)
  UI, the new document/comment/rating/related-document schema, the 3-tier document>category>owner
  `Api\Acl` model (designed specifically so both legacy apps' data can be imported), dual
  Markdown/HTML content support reusing the existing `Et2MarkdownEditMixin`/`Et2HtmlArea` widgets
  (no new editor needed), history via the shared `egw_history_log`/`<historylog>` widget instead
  of wiki's full-copy-per-revision storage, and migration mappings from both legacy apps. Design
  phase, no code written yet - deferred for later phases: multi-category-per-document, public/
  anonymous access, and phpbrain's FAQ-style question-intake pipeline.
- `doc/ai/projects/push-fallback-longpoll.md` - giving the swoole-less push
  fallback (shared hosting / tarball-in-docroot installs with no `swoolepush`
  daemon) a low-latency, PHP-FPM-friendly delivery path (bounded long-poll +
  SSE) and real client-side auto-detection, instead of the old static
  server-config flag. Phases 1-4, 6 and 7 done (Phase 5, the importexport
  progress-bar fix, not started); several live regressions found and fixed
  post-rollout. See the doc for full architecture, phase-by-phase detail, and
  the regression write-ups.
- `doc/ai/projects/pdf-player-pdfjs-update.md` - updated
  `api/js/etemplate/CustomHtmlElements/pdf-player.ts` (used by smallpart/ViDoTeach to page through a
  PDF like a video) from the abandoned `@bundled-es-modules/pdfjs-dist@2.5.207-rc1` wrapper to
  Mozilla's real `pdfjs-dist`, pinned `~5.4.624` (native ESM, zero `eval()`) - **not** the newest
  `6.3.289`: that version (and `5.5.207`+) crashes every render with
  `TypeError: ...getOrInsertComputed is not a function`, a JS `Map` method not yet implemented by
  *any* shipping browser (checked directly against Playwright's bundled Chromium 141/Firefox 142) -
  found immediately by the test harness built first (same "test harness before the bump" approach
  as the celtic/lti update), which is exactly why that harness existed. Also required adding
  `"skipLibCheck": true` to the repo's `tsconfig.json` (pdfjs-dist's own `.d.ts` files need
  TypeScript 5.7+ lib definitions; this repo pins TS `^4.9.5` repo-wide - `skipLibCheck` only skips
  checking `.d.ts` files, not this repo's own source). Fixed a real dead-code bug in `pdf-player.ts`'s
  own `src` property getter/setter along the way (infinite recursion / TypeError, never reached in
  production since `et2_video.ts` only ever uses the `src` attribute). All 7 harness tests + the
  full `api` jstest group (2502 tests) green on both browsers - but the harness did NOT catch a
  real live bug: a red error toast on boulder.egroupware.org right after deploy
  ("Setting up fake worker failed"), root-caused to (1) `workerSrc` being a bare/page-relative path
  instead of an `egw.webserverUrl`-based absolute one, and (2) many web servers (confirmed:
  boulder's nginx) not mapping `.mjs` to a JS content-type, which no server-config fix is viable
  for across self-hosted installs we don't control. Fixed deployment-agnostically by having
  `pdf-player.ts` `fetch()` the worker as text and re-serve it to pdf.js as an explicitly-typed
  `Blob` URL (`ensureWorkerSrc()`), plus adding `blob:` to smallpart's own CSP `script-src`
  (`smallpart/src/Hooks.php`'s `csp_frame_src` hook) since executing a Blob as a module falls back
  to `script-src` when no `worker-src` is set. Verified live with nginx's `.mjs` bug deliberately
  left in its broken state, to prove the fix carries no server-config dependency. See the doc's
  "Live bug found post-deployment" section for the full trace. Also documents (not fixed) a
  pre-existing render-cancellation gap that can throw pdf.js's own "Cannot use the same canvas"
  error on fast repeated page turns, and a small/larger effort estimate for optionally also
  modernizing `pdf-player.ts` into this repo's Lit/`Et2Widget` web-component conventions (which it
  does not follow at all, same as its sibling `multi-video.ts`) - separate from and not required for
  the pdfjs-dist bump, not attempted.
- `doc/ai/projects/et2-historylog-conversion.md` - replacing the legacy `historylog` widget
  (`<historylog>`) with an `et2-historylog` web component built on `Et2Datagrid`, plus the filtering
  the legacy widget never had. DONE - the legacy widget is deleted. Covers why composition beats subclassing
  `Et2Nextmatch`, the per-row polymorphic value cell (the one thing `Et2Datagrid`'s fixed row
  template does not do natively), the deliberately narrow scope (sort locked, no selection, no
  actions, no values returned), the filter drawer echoing `Et2AppBox`, the zero-template-edit
  migration via `api/etemplate.php`'s `ADD_ET2_PREFIX_LEGACY_REGEXP` and why the server-side tag
  still needs dual registration, and four pre-existing `History::get_rows()` gaps found while
  reading (dead `colfilter`, ignored `search`, calendar's apparently-dead `filter` SQL fragment, and
  the pass-through `validate()` that must be allowlisted before any of it is honoured), plus what a
  diff needs that a virtualized shadow-DOM row takes away from it.
## Security and data handling

- Do not commit secrets, tokens, credentials, private keys, or production data.
- Do not put a real person's personal data - email addresses, names, message subjects/content, IP
  addresses, or similar - into test fixtures, code comments, commit messages, or docs, even when
  describing a real bug found against a real message/account (own accounts under your control, e.g.
  ralf@/rb@egroupware.org, are fine to reference). There is no consent to publish a third party's
  data this way, and unlike a private bug tracker this repo's history is public. If a real example
  is genuinely useful for understanding the bug, replace it with an anonymized/synthetic
  equivalent that demonstrates the same shape (a fake name/address like `sender@example.invalid`, a
  generated key/message fixture, a made-up subject line) - describe the fact pattern ("a sender
  using Content-Transfer-Encoding: base64 on the signature part"), not the identity behind it. This
  applies retroactively too: if you notice already-committed text like this (yours or someone
  else's), redact it in a new commit rather than leaving it - and flag to the user immediately if
  it turns out to already be pushed to a public remote, since a new commit alone doesn't remove it
  from history.
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
