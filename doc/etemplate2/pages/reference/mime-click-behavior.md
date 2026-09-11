## What happens when a user clicks a file

This is the single source of truth for **"if the user clicks a file of mime type X, what happens?"** across
filemanager and mail attachments - including how that answer changes depending on which apps are installed/enabled,
server configuration, and user preferences.

Nothing here is a fixed table of "mime X does Y" baked in at one point in time. Both apps make this decision at
**request time**, by evaluating the same handful of inputs through an ordered set of rules. This page documents those
rules and the exact code that implements them, so it stays correct as apps get enabled/disabled, Collabora gets
configured, or preferences change - re-derive the "what happens for mime X today" answer by walking the rules below
against the current inputs, rather than trusting a frozen example.

If you change any of the code cited below, update this page (and the tests it points to) in the same change.

### The governing inputs

Every rule below is a function of these five inputs. Nothing else affects the outcome.

| Input | What it means | Where it's read | Default |
|---|---|---|---|
| Collabora app enabled for this user | `$GLOBALS['egw_info']['user']['apps']['collabora']` is set | Server: `filemanager_hooks::getEditorLink()`, `filemanager/inc/class.filemanager_hooks.inc.php:271`. Client: `egw.user('apps')['collabora']`, `api/js/jsapi/egw_links.ts:850` (`isCollaborable()`) | off (app not installed/enabled) |
| Collabora server actually reachable | `EGroupware\Collabora\Bo::discover()` succeeds against the configured server | Server only, inside the `collabora` app's own `Hooks::getEditorLink()`/`isCollaborable()` (`collabora/src/Hooks.php:38-65`, `collabora/src/Bo.php:52-58`). A configured-but-unreachable server degrades silently to "no editor", same as not being configured at all. | n/a |
| `document_doubleclick_action` preference | filemanager preference, values `collabora` / `download` / `collabeditor` | `filemanager/inc/class.filemanager_hooks.inc.php:201-209` (defines it); read server-side in `filemanager_hooks::getEditorLink()` (`:271-277`) and client-side via `egw.preference('document_doubleclick_action', 'filemanager')` (`api/js/jsapi/egw_preferences.ts:411`, and directly in `mail/js/app.ts:2053`) | `'collabora'` **if the collabora app's files exist on disk** (`file_exists(EGW_SERVER_ROOT.'/collabora')`), else `'download'` - see [Known gotchas](#known-gotchas), this is evaluated independently of whether the *current user* has the app enabled |
| `collab_excluded_mimes` preference | comma-separated mime types the user has opted **out** of Collabora for | `filemanager/inc/class.filemanager_hooks.inc.php:182-192` (defines it, `default => 'application/pdf'`); applied in `filemanager_hooks::getEditorPrefMimes()` (`:285-295`) and client-side in `file_editor_prefered_mimes()` (`api/js/jsapi/egw_preferences.ts:409-427`) | `'application/pdf'` - **PDF is excluded from Collabora out of the box**, even on an install with Collabora fully configured and preferred |
| Desktop vs mobile | `egwIsMobile()` | Only checked by **mail's** attachment-actions dropdown, `mail/js/app.ts:2048` - filemanager's own click dispatch has no equivalent mobile-specific carve-out (see [Known gotchas](#known-gotchas)) | n/a |

Collabora's own mime-type support (which mime types it can actually open, eg. office documents but not a `.zip`) comes
from a **live discovery call** against the configured Collabora server (`Bo::discover()`), not a static list in this
codebase - so "is mime X Collabora-editable" can change without any EGroupware code or config changing, purely because
the Collabora server's own capabilities changed.

### Filemanager: clicking a row

Implemented in `filemanager_ui::open()`, `filemanager/js/filemanager.ts:1238-1272`. Rules are evaluated **in this
order**; the first one that matches wins:

1. **Directory** - mime is `httpd/unix-directory` (`Vfs::DIR_MIME_TYPE`) or the row is flagged `isDir` → navigate into
   the folder (`change_dir()`). Nothing below applies to directories.
2. **Gallery-previewable mime** - the mime matches the gallery regex (video `mp4`/`ogg`/`webm`, any `image/*` except
   `tif`/`x-xcf`/`pdf`, or any `audio/*` - `MIME_REGEX`, `api/js/etemplate/Expose/ExposeMixin.ts:29-33`) **and** the
   row has an `<et2-vfs-mime>` element → clicking delegates to that widget
   (`api/js/etemplate/Et2Vfs/Et2VfsMime.ts:104-140`), which opens the image/video lightbox or audio player
   (`ExposeMixin.expose_onclick()`, `api/js/etemplate/Expose/ExposeMixin.ts:768-790`).
   **Collabora is never consulted for these mime types, even if Collabora could also open them** - the gallery
   branch is checked and taken before any editor/Collabora check runs.
3. **Collabora-editable mime** - `isEditable()` (`filemanager/js/filemanager.ts:1855-1866`) is true when the mime is
   present in `file_editor_prefered_mimes()`'s live map, which itself requires **all** of: Collabora app enabled +
   Collabora server reachable + `document_doubleclick_action === 'collabora'` + mime not in
   `collab_excluded_mimes` → opens the Collabora editor popup directly.
4. **Everything else** - `egw.open({path, type: mime, download_url}, 'file', 'view', null, '_browser')`
   (`api/js/jsapi/egw_open.ts:235-268`) → routes through `mime_open()`
   (`api/js/jsapi/egw_links.ts:371-...`), which re-checks Collabora (consistently "no", since step 3 already
   ruled it out for this mime) and falls through to whatever `Link::get_mime_info()` has server-registered for the
   mime, or - for a plain file with no registered handler, which includes PDF under the default
   `collab_excluded_mimes` - a generic download/view URL opened in a new browser tab. Whether the browser then
   *displays* that URL (eg. its own built-in PDF viewer) or *downloads* it is entirely up to the browser, not
   EGroupware.

The user-facing preference help text (`filemanager/inc/class.filemanager_hooks.inc.php:203-204`) summarizes this
correctly: *"Images are always opened in the expose-view and emails with email application. All other mime-types are
handled by the browser itself."*

### Mail: clicking/acting on an attachment

Mail has **two independent click surfaces** for an attachment - they can disagree with each other, by design, so
check both when reasoning about "what happens":

**A. Clicking the attachment's filename** dispatches through the switch statement in
`AttachmentJmap::createAttachmentBlock()` (`mail/src/Ui/AttachmentJmap.php:129-219`), purely by mime type, with no
Collabora involvement at all:

- `message/rfc822` → opens mail's own message-display popup, not a download.
- `text/calendar` / `text/x-vcalendar` → opens the calendar app's "add event" popup (`Api\Link::get_registry('calendar', 'view_popup')`).
- `text/vcard` / `text/x-vcard` (including a `.vcf` mislabeled `text/plain`, re-sniffed from the filename extension
  at `:155-165`) → opens the addressbook app's "add contact" popup.
- Everything else (including PDF, office documents, images, zip, ...) → a plain `mail_ui::getAttachment` download
  link.
- S/MIME parts are skipped entirely (`Mail\Smime::isSmime()` check, `:67-70`).

**B. The attachment's actions dropdown** (the "Download ▾" / "Collabora ▾" split button next to each attachment) is
rebuilt **client-side**, overriding whatever `createAttachmentBlock()` computed server-side, in
`setupViewAttachmentActions()` (`mail/js/app.ts:1999-2062`):

- Collabora only appears in the dropdown at all when: the collabora app is enabled for the user **and** the client
  is not mobile (`!egwIsMobile()`) **and** `egw.isCollaborable(mime)` is true (same underlying
  app-enabled + server-reachable + preference + exclusion-list gate as filemanager's, via
  `file_editor_prefered_mimes()`).
- Even when Collabora is offered, it is only the **default** (top) action when
  `document_doubleclick_action === 'collabora'`; otherwise "Download" stays the default and Collabora is a secondary
  option in the dropdown.
- **Collabora is never offered on mobile, unconditionally** - `!egwIsMobile()` is checked before anything else, so
  no server-side config or preference can bring it back on a mobile client.

### Worked examples (default install config)

Illustrative only - re-derive from the rules above for any other configuration. Assumes Collabora is installed,
enabled for the user, and its server is reachable, with **every preference left at its shipped default**
(`document_doubleclick_action = 'collabora'`, `collab_excluded_mimes = 'application/pdf'`).

| Mime type | Filemanager click | Mail filename click | Mail actions dropdown |
|---|---|---|---|
| `image/jpeg` | Gallery lightbox (rule 2 - Collabora never reached) | Download link | Collabora offered (secondary; not applicable if excluded) |
| `application/pdf` | Download/view in browser (rule 4 - PDF is in the default exclusion list) | Download link | "Download" is default; Collabora **not** offered (excluded mime) |
| `application/vnd.openxmlformats-officedocument.wordprocessingml.document` (.docx) | Collabora editor (rule 3) | Download link | "Collabora" is default |
| `text/calendar` (.ics) | Download/view in browser (not gallery, not Collabora-registered by default discovery unless the server offers it) | Calendar "add event" popup | Download-oriented (Collabora unlikely to claim this mime) |
| `text/vcard` (.vcf) | Download/view in browser | Addressbook "add contact" popup | Download-oriented |
| `application/zip` | Download/view in browser | Download link | "Download" is default (zip is never Collabora-editable) |
| `httpd/unix-directory` (a folder) | Navigate into it | n/a (directories aren't attachments) | n/a |

With Collabora **not installed/enabled**, or with `document_doubleclick_action = 'download'`: every "Collabora"
outcome above becomes its non-Collabora fallback instead (download, or the calendar/vcard/message-rfc822 popups for
mail's filename click, which are mime-switch based and don't involve Collabora either way).

### Known gotchas

- **`document_doubleclick_action`'s default doesn't mean Collabora is actually available.** It defaults to
  `'collabora'` based on whether the collabora app's files exist *on disk for this install*
  (`filemanager/inc/class.filemanager_hooks.inc.php:207-209`), not on whether the *current user* has the app
  enabled. A user without collabora access can therefore have this preference stored as `'collabora'` while nothing
  Collabora-related is ever actually offered to them - the app-permission check inside
  `getEditorLink()`/`isCollaborable()` is what actually gates behaviour, and correctly falls back to download.
  Don't treat a `'collabora'` preference value alone as proof Collabora will be used.
- **PDF is excluded from Collabora by default**, even on a fully-configured, fully-preferred Collabora install
  (`collab_excluded_mimes` default = `'application/pdf'`, `filemanager/inc/class.filemanager_hooks.inc.php:187`).
  "Click a PDF → it downloads" is the expected out-of-the-box behaviour, not a bug - the user has to explicitly
  remove PDF from their exclusion list to change it.
- **Gallery mime types never reach Collabora in filemanager**, regardless of preference (see rule 2 above) - only
  non-gallery mime types (PDF, office documents, ...) can ever be routed to Collabora there.
- **Mail suppresses the Collabora action entirely on mobile** (`mail/js/app.ts:2048`, `!egwIsMobile()`), with no
  filemanager-side equivalent found in this codebase as of this writing - filemanager's own click dispatch
  (`filemanager/js/filemanager.ts:1238`) has no mobile-specific branch at all. If you find one, or add one, update
  this page.
- **Known bug, deliberately left unfixed and locked in by a regression test**: `createAttachmentBlock()`'s
  `$windowName` (and `$reg2`) locals aren't reset per attachment in its `foreach` loop - a plain-download attachment
  (eg. a `.zip`) immediately following a popup-type attachment (`.ics`/`.vcf`/`message.eml`) in the same message can
  silently inherit that previous attachment's popup window name. See
  `mail/tests/CreateAttachmentBlockTest.php::testDownloadAttachmentAfterPopupAttachmentInheritsStaleWindowName`.
- **`collabeditor`** is a third, legacy value for `document_doubleclick_action` (ODT-only "CollabEditor"), but as of
  this writing no app registers a `filemanager-editor-link` hook for it (only `collabora` does, in
  `collabora/setup/setup.inc.php:30`) - the rules above don't cover it, and it should be treated as effectively
  inert unless that changes.

### Keeping this page accurate

These rules are exercised by automated tests - if the code cited above changes, run these and update both the code
and this page together, not one without the other:

- `filemanager/tests/FilemanagerMimeTypeTest.php` - server-side mime handling in `get_rows()`, and the
  Collabora-app-disabled fallback for `getEditorLink()`.
- `mail/tests/CreateAttachmentBlockTest.php` - the filename-click mime switch in `createAttachmentBlock()`,
  including the known windowName-leak bug above.
- `mail/tests/AttachmentNameTest.php` - mime-to-filename-extension logic.
- `api/js/jsapi/test/EgwMisc.test.ts` (`describe('mime_icon', ...)`) - the shared mime-to-icon lookup.
- `api/js/etemplate/Et2Vfs/test/Et2VfsMime.test.ts` - the `<et2-vfs-mime>` widget's `isExposable()`/icon/tooltip
  behaviour.

Nothing in this codebase currently tests `setupViewAttachmentActions()` (mail's actions-dropdown construction,
`mail/js/app.ts:1999-2062`) or filemanager's `open()` dispatch (`filemanager/js/filemanager.ts:1238`) directly - both
were verified by reading the code for this page rather than by an automated test. That's a real coverage gap if
you're relying on this page instead of re-reading the code yourself.

**Why `open()` specifically resists a straightforward `web-test-runner` test** (investigated 2026-08-27, not just
assumed): `filemanager.ts` imports `etemplate2.ts` for the single `etemplate2.getById('filemanager-index')` call
inside `open()` itself, so it can't be swapped for a type-only import without changing that line too. `etemplate2.ts`
in turn statically imports ~90 widget files, and at least two of them run code at **module-evaluation time** (not
inside a method - unconditionally, the moment the module loads): `api/js/jsapi/egw_json.ts`'s
`egw.extend('json', egw.MODULE_WND_LOCAL, ...)` and `etemplate2.ts`'s own
`egw(window).registerJSONPlugin(...)` (`api/js/etemplate/etemplate2.ts:1926-1927`). A test file's own
`window.egw = ...` assignment - the pattern every other widget test in this repo uses - can't intercept this: ES
module `import` side effects are hoisted and always run before the importing module's own top-level statements,
*regardless of their textual order*, so by the time `etemplate2.ts`'s module-scope code runs, `window.egw` is still
whichever fallback `web-test-runner.config.mjs`'s shared `testRunnerHtml` installed (it has no `.extend()`), and the
call throws. Working around that with a fuller `egw` stub via a *dynamic* `import()` (which does run at the point
it's reached, not hoisted) gets further - the throw goes away - but then Chromium hangs to the test's 3-second
timeout, consistent with something deeper in that same ~90-file graph doing a real, unmocked async operation (a
`fetch()`/similar) that never resolves in a bare test page. No test anywhere else in this codebase imports a real
`EgwApp` subclass at runtime for exactly this reason - the one existing test that references `mail/js/app.ts`
(`mail/js/test/MailJmap.test.ts`) uses `import type` (erased at compile time, no runtime import at all) and fakes a
plain object instead, which only works because it's testing standalone exported functions that take an app-shaped
object as a parameter, not an instance method on the heavy class itself. `open()` is the first attempt in this repo
at testing a method directly on one of these app classes, and it's what surfaced this.

Two ways forward, neither attempted yet:
1. Change `filemanager.ts`'s `import {etemplate2} from ...` to `import type {etemplate2}` and read
   `(window as any).etemplate2.getById(...)` instead for that one call - behaviourally identical at runtime
   (`etemplate2.ts`'s own module-scope code already does `window['etemplate2'] = etemplate2` unconditionally, so the
   real app already relies on that global being present by the time anything calls `open()`), but it's a production
   code change made for testability, not a pure test addition.
2. Build a real `egw_core` + `egw_json` (+ whatever else the hang traces back to) test harness in an iframe, along
   the lines of `api/js/jsapi/test/EgwCoreHarness.ts`, so the full unmodified import chain can load. More upfront
   work, reusable for testing other `EgwApp` subclass methods later, but the Chromium hang means there's at least
   one more unmocked dependency to track down first.
