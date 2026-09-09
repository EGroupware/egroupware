# `Api\Link`: support storing URLs against any app's entries

## Status: Done and tested (2026-09-09) — schema, backend, `et2-link*` widgets. Motivated
by the Knowledgebase app design ([[knowledgebase-app]]) needing to store external URLs per
document (phpbrain's `egw_kb_urls` equivalent) — but this is a general `Api\Link`
(`egw_links` table) enhancement useful to every app, not Knowledgebase-specific. Done
first, as its own piece of work, ahead of/independent of the Knowledgebase app itself.

## Goal

Let any app's entry hold arbitrary external URLs the same way it already holds file
attachments and cross-app links, via the existing `egw_links` mechanism rather than a new
per-app table — mirrors the existing `Link::VFS_APPNAME = 'file'` pseudo-app special case.

## Schema (done, applied)

`egw_links.link_id2` widened to `ascii(1024)` (comment: `"URL if link_app2='url'"`),
composite indexes no longer include `link_lastmod` (now its own standalone index), and the
two indexes that include `link_id2` truncate it to a 64-char prefix (`'link_id2(64)'`,
using the schema DSL's length-suffix syntax, confirmed supported by
`Api\Db\Schema::CreateIndex()`/`_in_index()`). Applied via `api_upgrade26_1_001()` in
`api/setup/tables_update.inc.php` (ralf).

## Backend code (done)

- `Link::URL_APPNAME = 'url'` (`api/src/Link.php`, alongside `VFS_APPNAME`/`DATA_APPNAME`)
  — the pseudo-appname: `Link::link($app, $id, Link::URL_APPNAME, $url, $remark)`.
- `Link::title()` (`api/src/Link.php`): added an early-return branch for
  `$app === self::URL_APPNAME` returning `$id` (the URL) as its own title. **Not
  optional** — `get_links(..., $cache_titles=true)` uses `title()` return value as an
  access filter (the "remove links, current user has no access, from result" loop);
  without this branch every url link would be silently dropped whenever `cache_titles` is
  requested, since `'url'` has no `$app_register` entry.
- `Link\Storage::_add2links()` (`api/src/Link/Storage.php`) — **the real gotcha**: this
  method drops *any* link whose other side isn't one of the current user's installed/
  accessible real apps (`!$GLOBALS['egw_info']['user']['apps'][$app]`), completely
  independent of `only_app`/`title()`/`cache_titles`. Since `'url'` is never a real
  installed app, every url link was being dropped here, before `title()` even runs.
  Fixed with a one-line exemption (`$linked_app !== 'url' && !...apps[$app]`) — hardcoded
  as the literal string rather than referencing `Link::URL_APPNAME`, since `Storage` is
  `Link`'s parent class (a so→bo constant reference would invert the existing layering,
  and no other pseudo-app constant is referenced from `Storage.php` either — `file`/
  `egw-data` never reach this code path, since attachments are stored in VFS, not as
  `egw_links` rows, so this gotcha is specific to the url case).

## Tests (done, passing)

`api/tests/Link/UrlTest.php` (`LinkUrlTest`, follows the existing `NegatedOnlyAppTest.php`
conventions — real DB via `AppTest`, a timesheet entry as the anchor, cleanup in
`tearDown`): store/retrieve round-trip, `title()` returns the URL itself, the
`cache_titles=true` regression above, a long-URL (1023 char) round-trip actually
exercising the widened column, and `unlink()`. All 5 pass
(`docker exec -u www-data egroupware bash -c "cd /var/www/egroupware && vendor/bin/phpunit -c doc/phpunit.xml api/tests/Link/UrlTest.php"`),
alongside the pre-existing `NegatedOnlyAppTest.php`/`SharingTest.php` (no regressions).

## Relationship to the Knowledgebase project

The Knowledgebase design ([[knowledgebase-app]] §2/§8/§9) treats this as a dependency for
its "external URLs" feature and the phpbrain `egw_kb_urls` migration. Backend is now ready
to consume; Knowledgebase's own not-yet-migrated URL data still needs some other holding
place until that app itself exists — exact mechanism deferred to migration-script time,
see that doc's §9.

## `et2-link*` widget support (done)

Full inventory of `api/js/etemplate/Et2Link/*.ts` (10 files) and how they relate:
`Et2Link` (single link, renders icon/title/remark, `_handleClick()` opens it),
`Et2LinkList`/`Et2LinkString` (list/compact display - both delegate per-row rendering to
`<et2-link>`, confirmed by reading their `render()`), `Et2LinkTo` ("choose an existing
entry, VFS file or local file, and link it to the current entry" - the "attach" widget,
with a toolbar of buttons for file-upload/VFS-select/clipboard-paste plus an
`<et2-link-entry>` search combo), `Et2LinkEntry` (combines the app-picker
`Et2LinkAppSelect` with the search combo `Et2LinkSearch`), `Et2LinkAdd` (unrelated to
attaching links - it's "pick an app, click + to open that app's add-new-entry popup"),
`Et2LinkPasteDialog` (VFS clipboard paste dialog), `LinkAction` (bulk "link N selected
rows to one target entry" contextmenu action).

ralf's preferred UX: add "URL" as an option in the existing app picker
(`et2-link-apps`/`Et2LinkAppSelect`) rather than a new separate button - selecting it
swaps the search combo for a plain URL input, then the existing (Link) button attaches it.
Turned out to need less than first planned, since both display widgets delegate to one,
and the existing (Link) button plumbing is already fully generic:

- **`Et2Link.ts`**: exports `LINK_URL_APPNAME = 'url'` (mirrors `Api\Link::URL_APPNAME`)
  and `LINK_URL_ICON` (see icon section below), shared by the other files below.
  - `_handleClick()`: branches on `this.app === LINK_URL_APPNAME` to `window.open(this
    .entryId, '_blank', 'noopener')` instead of `egw().open()`, which has nothing to look
    up for a pseudo-app.
  - `_thumbnailTemplate()`: renders `LINK_URL_ICON` via `<et2-image>` for a url link,
    instead of falling through to a nonexistent app icon.
  - Fixes `Et2LinkList` and `Et2LinkString` for free, since both just render an
    `<et2-link>` per row - confirmed no changes needed in either.
- **`Et2LinkAppSelect.ts`**: pushes one extra synthetic option (`value: 'url', label:
  lang('URL'), icon: LINK_URL_ICON`) after the real `link_app_list()`-derived options, in
  the *default* branch only (not the `onlyApp`/`applicationList`-restricted branches - those
  are explicit allow-lists set by the embedding app/template, adding a pseudo-app there
  would be surprising, so it's only ever offered in the normal unrestricted picker).
- **`Et2LinkEntry.ts`**: added an `<et2-url>` input alongside the existing
  `<et2-link-search>`, toggling `hidden` on whichever isn't the current app
  (`[hidden] { display: none; }`, matching the pattern `Et2LinkTo.ts` already uses for its
  own hidden children). New `handleUrlChange()` mirrors the existing
  `handleEntrySelect()`/`handleEntryClear()` contract exactly - sets `this.value = {app:
  LINK_URL_APPNAME, id: url, title: url}` and dispatches the same `change` event - so
  **`Et2LinkTo.ts` needed no changes at all**: `handleEntrySelected()`/
  `handleLinkButtonClick()` already treat `this.select.value` (the `<et2-link-entry>`
  value) as an opaque `{app, id, title}` object and forward it straight into
  `createLink()` → the existing `Widget\Link::ajax_link()` → `Api\Link::link()`, which
  was already fully generic (branches per-link on its own `app`/`id`, no per-app-type
  server code) - so the already-existing (Link) button/ajax path just works.
- **`handleAppChange()`** (`Et2LinkEntry.ts`): now resets `this.value` and, for the url
  case, focuses the newly-shown `<et2-url>` instead of the search combo.

**Icon** (`LINK_URL_ICON` in `Et2Link.ts`): no real app is registered for `'url'`, so
there's no app icon to look up - a small inline SVG (`data:image/svg+xml,...`, computed via
`encodeURIComponent()` at module load, no new asset file) showing just the text `http`,
stretched to fill a 24×24 box via SVG's `textLength`/`lengthAdjust="spacingAndGlyphs"` (a
standard, deterministic force-fit technique - not a guess at font metrics). First cut was
`http://` at `font-size 9`/`#555` - ralf smoke-tested the feature (works) but found that
icon too tiny to read. Actually rendered and visually compared several options (via a
throwaway local HTTP server + browser screenshot, since the extension blocks `file:`/
`data:` navigation directly) before settling on the shorter `http` (no `://`) at bold
`font-size 16`/`#000`/`textLength 23` - legible at actual ~20px icon size, unlike the
original.

**Verified**: `npm run --silent typecheck` shows zero *new* TypeScript errors (compared
line-by-line against the pre-existing ~4000 repo-wide error list) introduced by any of the
above; `npm run jstest -- api/js/etemplate/Et2Link/test/Et2Link.test.ts
api/js/etemplate/Et2Link/test/Et2LinkString.test.ts
api/js/etemplate/Et2Link/test/LinkAction.test.ts` - all 24 existing tests still pass in
both Firefox and Chromium. No new widget-level test was added (no existing test file for
`Et2LinkEntry.ts`/`Et2LinkAppSelect.ts`/`Et2LinkTo.ts` to extend, and these are DOM-heavy
UI interactions - flagged as residual risk, a manual check in a real browser is the
practical verification until/unless test coverage is added for these files specifically).

**Explicitly out of scope / untouched**:
- `Et2LinkSearch.ts` - untouched; still only used for real-app search, never sees `'url'`.
- `LinkAction.ts` - bulk-linking several selected entries to one URL "target" isn't a
  considered use case; would need its own separate design decision if ever wanted.
- `Et2LinkAdd.ts` - unrelated widget (see inventory above), nothing to change.

## Open

- Whether `link_id1` also ever needs widening — resolved: no, only real app-ids are ever
  stored there, never a URL (ralf).
- The `LINK_URL_ICON` SVG's exact look, once seen rendered live (see above).
