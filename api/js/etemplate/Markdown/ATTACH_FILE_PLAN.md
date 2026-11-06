# Markdown format popup: "attach file" option

Extends the on-selection format bar (`.markdown-popup__bar`,
`api/js/etemplate/Markdown/Et2MarkdownEditMixin.ts:456-483`) with a file affordance that does three
things in one gesture:

1. gets the file into the VFS — either uploaded from the computer (`et2-vfs-upload`) or picked from
   the VFS (`et2-vfs-select`),
2. makes it a **Verknüpfung** on the entry, exactly the way the two buttons above "add comment" do,
3. inserts markdown at the caret that renders it — `![name](url)` for an image, `[name](url)` for
   anything else. the current selection should become name

## What already exists (verified)

The buttons the user is pointing at are `tracker/templates/default/edit.xet:51-52` (add comment) and
`:12-13` (edit comment):

```xml
<et2-hbox align="left" class="et2_link_to et2_toolbar" disabled="@no_reply">
    <et2-vfs-upload id="tracker:$cont[id]:comments/.new/" label="" multiple="true" display="list" inline="true"/>
    <et2-vfs-select Label="Link" multiple="true"
                    method="EGroupware\Api\Etemplate\Widget\Link::ajax_link_existing"
                    methodId="tracker:$cont[id]:comments/.new/" buttonCaption="" dialogTitle="Link"/>
</et2-hbox>
```

Both take the **same** `app:id:relpath` string. That is the one piece of configuration the new
option needs, and it is already an established template idiom.

| Piece | Where | What it gives us |
|---|---|---|
| `et2-vfs-upload` | `api/js/etemplate/Et2Vfs/Et2VfsUpload.ts:25` (extends `Et2File`) | `path` property; POSTs to `Vfs::ajax_upload`; `path` rides along in `resumableQuery():38` |
| `Vfs::process_uploaded_file()` | `api/src/Etemplate/Widget/Vfs.php:237-261` | `store_file($_REQUEST['path'] ?: $_REQUEST['widget_id'])`, responds with `{name, path, mime, mtime}` |
| upload completion | `Et2File.resumableFileComplete():430-495` | merges the response into `this.value[tempName]`, then fires `et2-file-complete` (`:494`) with `detail.file.tempName` |
| `et2-vfs-select` | `Et2VfsSelectButton.ts:255` | `method` + `methodId`; `sendFiles():175` calls the method with `[methodId, paths, button]`; has a public `click()` (`:100`) |
| `Link::ajax_link_existing()` | `api/src/Etemplate/Widget/Link.php:367-435` | `Link::link_file($app, $id, $target)` — the actual Verknüpfung |
| download URL | `Vfs::download_url()` `api/src/Vfs.php:1355` | `/webdav.php` + path, `+`/space/`"` percent-encoded |
| renderer | `MarkdownDirective.ts:21-44` | `a` and `img` are already in `SUPPORTED_TAGS`; `href`/`src`/`alt`/`title` in `ALLOWED_ATTR`; `ALLOWED_URI` deliberately keeps relative `/…` alive |
| shadow DOM | `Et2Widget.getInstanceManager():1562-1579` | explicitly walks `getRootNode().host.getParent()`, so a vfs widget rendered **inside our shadow root** still finds `etemplate_exec_id` |

Writing a file into `/apps/<app>/<id>/…` **is** the attachment — `Link::list_attached()` scans that
directory — so `et2-vfs-upload` needs no extra linking call. Only the "pick an existing VFS file"
path needs `ajax_link_existing`, and that is what `method`/`methodId` are for. Same split the
tracker toolbar already has.

## UI

Two buttons in a third group of the popup bar, after a second `.markdown-popup__separator`:

```
[ Normal ▾ ]  B  I  S  <>  🔗  │  ☰ ☰ ☑  │  📎  📂
                                            ^   ^
                          et2-vfs-upload ---'   '--- et2-vfs-select
```

- `paperclip` — upload from the computer
- `folder-symlink` — pick from the VFS

Both icons verified present in `node_modules/bootstrap-icons/icons/`.

They are the real widgets, rendered directly in the bar with `label=""` / `buttonCaption=""` and
`noFileList`, so they are their own trigger — no proxy button, no `click()` plumbing. That mirrors
the tracker toolbar one-for-one, with one difference: **no `multiple`** on either widget. One
interaction attaches one file.

They live in the format popup and nowhere else. The popup only exists while there is a selection
(`Et2MarkdownEditMixin.ts:274`), and that is now load-bearing rather than a limitation: **the
selection is the link text**. You select the words you want the link to read as, then attach the file
behind them. With nothing selected there is no popup and no attach — deliberate, not an oversight.

Two buttons rather than one paperclip-with-a-menu because that is what the user is comparing to, and
because a menu inside a popup inside a shadow root is a stacking-context fight nobody needs. If the
bar turns out too wide in practice, collapsing them into one `et2-dropdown` (the block-style
dropdown at `:467-478` is the template) is a contained follow-up.

## Configuration: one new attribute

```ts
/**
 * Where files dropped in from the format popup go, as "app:id:relpath" - the same string the
 * et2-vfs-upload / et2-vfs-select toolbar pair takes.  Empty disables the file buttons.
 */
@property({type: String, attribute: "upload-path"})
uploadPath = "";
```

- Set in the template on the *host*, so `$cont[...]` is expanded normally before it reaches us:
  `<et2-htmlarea id="reply_message" mode="@tr_edit_mode" markdown="true"
   uploadPath="tracker:$cont[id]:">` — the **entry root**, per commit A below. An app that wants a
  subdirectory that later moves (tracker's `comments/.new/`) is opting into the rewrite problem and
  has to supply the rewrite; the generic default does not.
- Fall back to the host's `imageUpload` when `uploadPath` is unset and the host has one
  (`Et2HtmlArea.ts:251`), so `imageUpload="link_to"` fields get the feature for free — and `link_to`
  already resolves to the entry root, which is exactly what we want.
- Empty → render no file buttons at all. Never guess a path.
- **Unresolved id → no file buttons either.** `Vfs::get_vfs_path()` (`api/src/Etemplate/Widget/Vfs.php:507`)
  silently falls back to `/home/<user>/.tmp/<app>_<hash>/` when the id is empty or `"undefined"`.
  That fallback is the whole of problem #1, so the widget has to be able to *see* it: the host needs
  to know whether its `uploadPath` names a saved entry. Cheapest honest way is a server-side resolve
  at render time — `HtmlArea`/`Textarea` expand `uploadPath` and set a boolean the mixin reads —
  rather than the client parsing `app:id:relpath` and guessing what counts as a real id.

## Flow

```
click paperclip / folder icon
  └─ snapshot {start, end, text} of the source textarea   ← must happen on mousedown
      └─ upload finishes (et2-file-complete)  |  dialog closes (change)
          └─ the one file: {name, path, mime}
              └─ url   = Vfs-style download url for the *response* path
                 label = snapshot.text || name        ← the selection is the link text
                 md    = isImage(mime) ? `![label](url)` : `[label](url)`
              └─ insertAtSelection(snapshot, md)   ← same execCommand path as _applyMarkdownCommand
              └─ refresh link lists so the Verknüpfung shows up
```

### New pure function in `MarkdownCommands.ts`

Keeps the string work testable with no DOM, like every other command:

```ts
export function insertLink(value: string, start: number, end: number,
                           link: {text: string, url: string, image?: boolean}): CommandResult
```

- replaces the selection (or inserts at the caret) with `[text](url)` / `![text](url)`
- escapes `]` in `text` and `(`, `)`, space in `url` — a filename with a bracket in it must not
  produce broken markdown
- `text` is the snapshotted selection, falling back to the file name when it is empty (which can only
  happen if the selection was lost between click and completion)
- leaves the selection **after** the insert, not inside a placeholder: unlike `applyLink()` there is
  nothing for the user to type

### Applying it

Reuse `_applyMarkdownCommand()`'s tail verbatim — `minimalEdit()` → `execCommand("insertText")` →
write `this._host.value` → dispatch `input`/`change`. Factor that tail into a
`_writeMarkdownSource(result)` helper so the two callers cannot drift; that undo-preserving
`execCommand` dance (`Et2MarkdownEditMixin.ts:244-266`) is not worth having twice.

### Refreshing the Verknüpfung list

`tracker/js/app.ts:373-390` (`comment_add_vfs`) does the app-level refresh today: sweep every
`et2-link-list` in the instance manager and call `get_links()`. There is no generic "links changed"
event in the API — `et2-delete` (`Et2LinkList.ts:375`) is the only thing close, and it is a
deletion.

Recommendation: the mixin does the same sweep itself (`getInstanceManager().widgetContainer
.querySelectorAll("et2-link-list")`), **and** dispatches a bubbling `et2-link-changed` so an app can
hook in without patching the mixin. Introducing the event is cheap and it is the thing that was
missing when `comment_add_vfs` had to be written by hand.

## Where the file lives: the URL must outlive the path

A markdown link stores a **path**. Anything that changes that path breaks the rendered comment.
Three things change it:

| # | Move | When | `fs_id` | Scope |
|---|---|---|---|---|
| 1 | `/home/<user>/.tmp/<app>_<hash>/` → `/apps/<app>/<id>/` | first save of a **new** entry | **changes** — `Link::attach_file()` (`Link.php:1339`) uses `Vfs::copy_uploaded()`, a copy | every app |
| 2 | `comments/.new/` → `comments/<reply_id>/` | tracker comment save (`tracker_bo::comment_files()`) | preserved — `Vfs::rename()` is `UPDATE egw_sqlfs SET fs_dir,fs_name` | tracker only |
| 3 | user moves or renames it in filemanager | any time, forever | preserved | every app |

Only #2 is app-specific. #1 is generic and hits every app the moment someone attaches to an entry
that has not been saved yet. **#3 cannot be fixed by any rewrite hook** — nobody is listening when a
user drags a file in filemanager six months later. Only an indirection that does not name the path
survives it.

### Why `/apps/$app/markdown-attachments` cannot work literally

`Vfs\Links\StreamWrapper::check_extended_acl()` (`api/src/Vfs/Links/StreamWrapper.php:85-119`) splits
every path as `/apps/<app>/<id>/<rel_path>` and hands segment 3 to
`Api\Link::file_access($app, $id, ...)`. A literal folder called `markdown-attachments` **is an entry
id** as far as the wrapper is concerned: the app would be asked whether the current user may read
entry `"markdown-attachments"`, would say no, and every inline image would 403.

`/apps/` is not a normal directory tree — segment 3 is reserved for entry ids by construction, and
that is also what gives those files the entry's ACL for free. An entry-independent store therefore
has to live **outside `/apps/`**, as a new top-level directory on plain sqlfs: `/markdown/$app/...`.

### What a top-level store actually costs

- **ACL becomes posix, not the entry's.** `/templates/$app` is the existing precedent for an
  app-scoped shared dir (`api/setup/default_records.inc.php:220-228`: `mkdir 075`, `chgrp Admins`) —
  but that is admin-writable, world-readable. Ours must be writable by every user, so the store ends
  up world-readable and the URL becomes a **capability URL**: anyone with an account and the link can
  fetch the image, regardless of whether they may see the ticket. An unguessable path segment
  (`/markdown/$app/<uuid>/<name>`) plus a non-listable directory (`--x` for other) reduces that to
  "you need the link", which is how most chat systems do it — but it is strictly weaker than today,
  and for a private tracker queue that is a real change, not a technicality.
- **webdav.php needs to know about it.** Its `currentapp` closure (`webdav.php:28-40`) only maps
  `/webdav.php/(etemplates|apps/<app>|home/<user>/.tmp)/`; anything else runs as `filemanager`, so a
  user without filemanager rights gets denied on every inline image. The regex needs a
  `markdown/([A-Za-z0-9_-]+)` branch.
- **Nothing ever deletes these.** Delete the entry and the symlink goes; the bytes stay forever. A
  store like this needs a GC policy (or an accepted decision to leak), which the entry dir does not.
- **The Verknüpfung still works.** `Link::link_file($app, $id, $file)` (`Link.php:1357`) is a
  `Vfs::symlink()` into the entry dir — so stable bytes outside `/apps/` and a real attachment inside
  it are not in conflict. That part of the idea is sound and needs no new code.

### The five options

| | Where the bytes live | URL survives #1 | #2 | #3 | ACL | New infrastructure |
|---|---|---|---|---|---|---|
| **A** | entry subdir, rewrite on move | with a per-app hook | yes | no | entry | per-app rewrite hook |
| **B** | `/apps/$app/$id/` (entry root) | no (buttons hidden instead) | n/a — nothing moves | no | entry | none |
| **C** | entry dir, `vfs:` pseudo-scheme | yes | yes | yes | entry | entry context in every readonly renderer |
| **D** | `/markdown/$app/<uuid>/` + symlink | yes | yes | yes | **capability URL** | top-level dir, webdav regex, GC |
| **E** | entry dir, URL keyed by `fs_id` | only if #1 preserves the `fs_id` | yes | yes | entry | resolver endpoint, identity-preserving move in `attach_file()` |

**E is the chosen approach.** `fs_id` is stable across `Vfs::rename()`, resolving it through
`Sqlfs\StreamWrapper::id2path()` lands back on the file's *current* path so the normal ACL applies
unchanged, and it needs no new store and no GC.

**D** was the starting instinct, and it does work once the path moves out of `/apps/`. What it buys
is that #1, #2 and #3 all stop existing; what it costs is the ACL downgrade and a store nobody cleans
up. Not taken.

### Recommended split

**Commit A — no moving parts at all.** Resolve `uploadPath` to the *entry root*, `"$app:$id:"`, and
render the file buttons **only when that resolves to a saved id**. New entry → no buttons (tooltip:
save first). Nothing ever moves, so nothing ever dangles; the ACL is the entry's; the file is a
Verknüpfung because it is in the entry dir. This is correct in every app, today, with zero new
infrastructure — and it is the whole feature for the overwhelming majority of uses, which are
comments on tickets that already exist.

**Commit B — lift the restriction**, via the id-addressed URL (E). Separately revertable, and it can
be judged on its own once the editor side is in and usable. See below for what it actually involves.

Note that "pick from VFS" already has no problem at all in either commit: `ajax_link_existing`
symlinks the *original* path into the entry dir and the original never moves, so the URL we insert is
stable from the start. It is only the upload path that is in question.

## Files

| File | Change |
|---|---|
| `api/js/etemplate/Markdown/MarkdownCommands.ts` | + `insertLink()`, + escaping helpers |
| `api/js/etemplate/Markdown/Et2MarkdownEditMixin.ts` | + `uploadPath`, + `_markdownFileButtonsTemplate()`, + selection snapshot, + `_writeMarkdownSource()` extracted from `_applyMarkdownCommand()`, + link-list refresh |
| `api/js/etemplate/Markdown/Et2MarkdownEditMixin.styles.ts` | + sizing for the two vfs buttons in the bar so they match `et2-button-icon`'s footprint |
| `api/js/etemplate/Markdown/markdown.less` | + `img { max-width: 100%; height: auto; }` inside `.et2_markdown` — an inserted photo must not blow the field open. Remember `markdown.css` is a hand-regenerated checked-in artifact. |
| `doc/etemplate2-rng.php` + `doc/etemplate2/etemplate2.0.dtd` | + `uploadPath` on `et2-textarea` / `htmlarea`, the same 4-line hand edit as `markdownMode` |
| `tracker/templates/default/edit.xet` | + `uploadPath` on the markdown comment fields (separate repo) |
| `api/src/Etemplate/Widget/HtmlArea.php` + `Textarea.php` | expand `uploadPath` server-side and tell the client whether it resolved to a saved entry |
| `webdav.php` | commit B1: `by-id/<app>` branch in the `currentapp` regex, and resolve `fs_id` → `PATH_INFO` before `ServeRequest()` |
| `api/src/Vfs.php` | commit B1: `download_url_by_id($fs_id, $name)` beside `download_url()` |
| `api/src/Link.php` | commit B2a: identity-preserving move in `attach_file()` when the source is already in the VFS |

## Traps

- **`mousedown` on the bar already calls `preventDefault()`** (`:465`), which keeps the textarea
  selection alive — but the file dialog and the upload are both async and the user can click away in
  between. Snapshot `selectionStart`/`selectionEnd` when the interaction *starts*, and insert against
  the snapshot. Do not read the live selection in the completion handler.
- **`et2-vfs-upload` renders its own file-list popup.** Set `noFileList` (`Et2File.ts:868`) or you
  get a popup inside a popup.
- **`et2-file-complete` vs `change`.** `change` fires *after* the file has been removed from the
  list (`Et2File.ts:476-477`), so read the per-file event and pull `this.value[file.tempName]`.
  `Et2File.ts:490-494` documents this exact trap.
- **Failed uploads still fire.** `resumableFileComplete()` sets `file.warning` and takes the error
  branch without touching `value`; insert nothing when `path` is missing.
- **Duplicate filenames.** `Et2VfsUpload.conflict` defaults to `"ask"` (`:28`) and the server may
  hand back a renamed file — always build the URL from the **response** `path`, never from
  `file.name`.
- **Never write outside `/apps/` expecting the entry's ACL.** Segment 3 of `/apps/$app/$id/` *is* the
  entry id to `Vfs\Links\StreamWrapper`; a literal folder name there is checked as an entry and
  denied. See the options section.
- **A new top-level VFS path is invisible to webdav.php's `currentapp`** and silently requires
  filemanager rights. Only relevant to commit B option D, but it is not optional there.
- **`et2-ai` and the selection.** `Et2Ai._getSelectedText()` reads the same
  `selectionStart`/`selectionEnd`; the snapshot must not mutate them.
- **`markdown=false` must stay byte-identical.** The file buttons live inside
  `_markdownFormatPopupTemplate()`, which is only reached from `_markdownShellTemplate()`, which the
  hosts only call when `markdown` is true. No new code on the off path.
- **Do not add `data:` to `ALLOWED_URI`.** The comment at `MarkdownDirective.ts:39-42` explains why
  images-as-data-URIs are only safe because markdown-it's own `validateLink` restricts them to
  raster; an uploaded file must go through the VFS, never inline.

## Tests

Pure (`Markdown/test/MarkdownCommands.test.ts`):
- `insertLink()` over a selection, at offset 0 and at end-of-value
- the selection becomes the link text; an empty selection falls back to the file name
- image vs link form chosen from mime
- escaping: `]` in the text, space and `(` in the path

Widget (`Markdown/test/` + `Et2Textarea/test/`):
- `uploadPath` unset → no file buttons in the bar
- `uploadPath` set but unresolved (new entry) → no file buttons
- `uploadPath` set and resolved → both buttons render, `path` / `methodId` forwarded verbatim, and
  neither widget has `multiple`
- a stubbed `et2-file-complete` carrying `{name, path, mime: "image/png"}` inserts `![…](…)` into
  `value` and fires `change`
- a stubbed completion with no `path` inserts nothing
- selection snapshot survives a focus change between click and completion
- `markdown=false` renders exactly today's output (the standing regression contract)

Browser, on the dev instance:
1. Existing ticket, ascii queue → attach a PNG from disk → it appears inline in the split preview,
   and in the "Verknüpfungen"/link list, without a reload.
2. Same, "pick from VFS" → link created, `[name](url)` inserted, file *not* copied twice.
3. Save, reopen → **the image still resolves** (this is what proves commit B; verify it fails
   before it and passes after).
4. Ctrl+Z once after an insert → the markdown goes away, the typing before it does not.
5. HTML-mode queue → no popup, no file buttons, TinyMCE untouched.

## Commit B in detail (option E)

Two independent halves. B1 is small and buys #2 and #3; B2 is the one that buys #1 and is the only
part with any blast radius.

### B1 — the id-addressed URL

`/webdav.php/by-id/<app>/<fs_id>/<name>`

- `<app>` exists **only** so `webdav.php`'s `currentapp` closure (`webdav.php:28-40`) can pick the
  right app without a database round trip — it runs before `header.inc.php`. Add a
  `by-id/([A-Za-z0-9_-]+)` branch beside the existing `apps/([A-Za-z0-9_-]+)`.
- `<name>` is cosmetic: it gives the browser a filename and keeps the URL readable. **Never trusted**,
  never used to resolve anything.
- After `header.inc.php` and before `$webdav_server->ServeRequest()`, resolve
  `Sqlfs\StreamWrapper::id2path($fs_id)` and rewrite `$_SERVER['PATH_INFO']` to the result. Not found
  → 404.
- **Do not** require the resolved path to still be under `/apps/<app>/`. Following the file wherever
  it went is the entire point of #3; if a user moved it into their home, sqlfs posix rights now apply
  instead of the entry's, which is correct — the ACL always belongs to where the file actually is.
- **The security property to hold on to:** resolution produces a *path*, which is then served through
  the normal Vfs stack, so the stream wrapper's ACL check is completely unchanged. Enumerating
  `fs_id`s gets an attacker exactly the files they could already read by path. Worth an explicit test.
- `CalDAV::managed_id2path()` (`api/src/CalDAV.php:1953`) is *not* reusable — it is `base64(path)`,
  so it breaks on every one of the three moves. Mentioned only to forestall the question.

### B2 — preserving the file's identity across the first save

This is the part I mis-sized when recommending E, so it is worth being exact.

`Link::attach_file()` (`Link.php:1339`) calls `Vfs::copy_uploaded(..., $check_is_uploaded_file=false)`,
which stream-copies into the entry dir and **mints a new `fs_id`**. Swapping in `Vfs::rename()` does
not fix it: `Vfs\StreamWrapper::rename()` only performs a real rename when the two resolved URLs
share a **scheme**, and `/home/...` resolves to `sqlfs://` while `/apps/...` resolves to `links://`.
Different schemes → it falls through to `stream_copy_to_stream()` + `unlink()`, i.e. exactly the copy
we were trying to avoid.

The two are nevertheless the *same storage*: `Vfs\Links\StreamWrapper` extends
`Vfs\Sqlfs\StreamWrapper` via `LinksParent`, both are rows in `egw_sqlfs`, and `?storage=db` only
moves the bytes between the DB and the files dir — it never touches the identity row. A move between
them is a metadata-only `UPDATE fs_dir, fs_name` that the code refuses to take purely on a scheme
string comparison.

Two ways to land it, in order of preference:

**B2a (preferred) — fix it locally in `Link::attach_file()`.** When `$file['tmp_name']` is a `vfs://`
URL (only the vfs-upload path produces that; every `$_FILES` caller keeps a local temp path), check
`Vfs::is_writable()` on the entry dir and then perform the move through
`Vfs\Sqlfs\StreamWrapper::rename()` with both sides normalised to `sqlfs://`, falling back to today's
`copy_uploaded()` if anything about that does not hold. Contained to one function, one branch,
reachable only by uploads that are already in the VFS. There is precedent for reaching into the sqlfs
wrapper from here — `Link::delete_attached()` (`Link.php:1389`) already uses
`Sqlfs\StreamWrapper::id2path()`.

**B2b — fix it generally in `Vfs\StreamWrapper::rename()`**, by treating two sqlfs-backed wrappers as
the same filesystem. Strictly the better fix — moving a file into an entry currently copies every
byte and discards the file's identity and history for *every* caller, not just ours — but it is core
VFS surgery affecting every move in the product, and the ACL interaction (the links wrapper's
`check_extended_acl()` must still gate the destination) needs care. Worth doing, not worth coupling
to this feature.

**If B2 is deferred**, commit A's rule simply stays: no file buttons until the entry is saved. Note
that this is a real gap rather than a cosmetic one — the existing `et2-vfs-upload` toolbar *does*
work on an unsaved entry (it parks in `.tmp` and the save commits it), so the markdown editor would
be strictly worse than the buttons next to it until B2 lands.

### Tests specific to commit B

- `by-id` resolves to the current path after a `Vfs::rename()` (simulating tracker's comment move)
- `by-id` on a deleted file → 404
- **`by-id` on a file the user may not read → denied, exactly as the path would be** (the property
  that makes the whole scheme safe)
- `attach_file()` with a `vfs://` source preserves `fs_id`; with a real upload it still copies
- an unsaved entry: attach, save, reload → the image still resolves
