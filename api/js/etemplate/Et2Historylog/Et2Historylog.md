`et2-historylog` shows the changes recorded for one entry, newest first. Put it on a tab of an edit
dialog and give it the record's id and app; it configures itself from there.

```xml
<et2-historylog id="history"/>
```

```php
$content['history'] = [
    'id'             => $entry_id,
    'app'            => 'infolog',
    'status-widgets' => [
        'St'    => $this->bo->status[$content['info_type']],
        'Ow'    => 'select-account',
        'En'    => 'date',
    ],
];
```

[Overview](#overview)<br>
[Content](#content)<br>
[status-widgets](#status-widgets)<br>
[Filtering](#filtering)<br>
[What it deliberately does not do](#what-it-deliberately-does-not-do)<br>
[Attributes](#attributes)<br>
[Internals](#internals)

---

## Overview

The history log renders through [`et2-datagrid`](../Et2Datagrid/Et2Datagrid.md), so it gets
virtualized rows, column resizing, a column chooser and keyboard navigation for free. It is not a
nextmatch, and it is not a form control: it returns no value and never appears in a submit.

Rows come from `Api\Storage\History::get_rows()` through the same server endpoint a nextmatch uses
(`Nextmatch::ajax_get_rows()`), scoped to one record of one app. An app can override the row source
with a `get_rows` attribute, which is read server-side from the template and never from the client.

Initialization is deferred until the tab it sits on is first shown, so an unopened history tab costs
nothing.

## Content

`$content[<widget id>]` is the widget's whole configuration:

| Key | Meaning |
|---|---|
| `id` | The record whose history to show. Without it, the widget renders nothing and requests nothing. |
| `app` | Which app's history. |
| `status-widgets` | Which widget displays which field's values - see below. |
| `num_rows` | Page size. Optional. |
| `rows` / `total` | Rows the initial exec already carried, used instead of fetching the first page. |

Labels for the "Changed" column come from `$sel_options['status']` **inside the widget's own
namespace**, which `Etemplate\Widget\HistoryLog::beforeSendToClient()` arranges - the history log
needs its own `owner` options (all accounts) without clobbering the surrounding dialog's.

## status-widgets

A map of field code to how that field's values should be displayed. Every form is accepted:

```php
'status-widgets' => [
    'Ow'           => 'select-account',            // a widget name
    'parent'       => 'link:infolog',              // a widget name with legacy options
    'public'       => ['' => 'No', 1 => 'Yes'],    // select options
    'participants' => [                            // a multi-part value, rendered stacked
        'select-account',
        ['U' => 'Unknown', 'A' => 'Accepted'],
        'integer',
    ],
],
```

Three statuses exist without being declared, because the server can produce them for any app:

| Status | Rendered as |
|---|---|
| `~link~` | `et2-link` |
| `~file~` | `et2-vfs-path` - an attachment, UNIONed in from the VFS |
| `user_agent_action` | plain text |

Custom fields need no declaration either: `#<name>` statuses resolve through the shared
customfield-to-widget mapper, so a history value renders the way that field itself does.

A field with no usable widget - an app naming one from a bundle that is not loaded, or a typo -
renders its value as plain text and logs one warning, rather than breaking the row.

Long or multi-line values are stored by the server as a unified diff plus a marker. Those rows
render an [`et2-diff`](../Et2Diff/Et2Diff.md) spanning both value columns, which caps its own height
and offers a pop-out for the full text on hover - but only for a diff tall enough to be cut off.

A diff needs two things it cannot get inside the grid, and the history log supplies both.

Its markup goes into `et2-diff`'s own *light* DOM, because the CSS that colours it - the diff2html
library's, plus the overrides that hide the library's file header and recolour the +/- lines -
ships in the page's theme stylesheet. A document stylesheet never crosses a shadow boundary, and
here the diff is behind two, so the rules are lifted out of the theme and adopted into the shadow
roots that host a diff. Without that the diff arrives as unstyled text.

Its pop-out is `position: fixed`, and a fixed element is placed against the nearest ancestor that
establishes a containing block rather than against the viewport. The datagrid's virtualizer gives
every row a transform and its body `contain: layout`, both of which establish one, so a dialog
opened from inside a row is sized to that row - the panel collapses and its contents spill across
the grid. The cell therefore intercepts the click and hands the diff to `Et2Historylog.showDiff()`,
which opens the dialog in its own shadow root, above the virtualizer. It intercepts only when the
diff reports itself `overflowing`; otherwise the click is left alone and et2-diff ignores it too.

## Filtering

Filters live in a drawer, opened by the funnel button above the grid. The button fills in while
anything is filtered, so it is visible at a glance that the list is not showing everything.

| Filter | Wire | Notes |
|---|---|---|
| Changed field | `col_filter[status]` | Multi-select of the "Changed" column's **own** option list |
| User | `col_filter[owner]` | |
| Date | `col_filter[user_ts]` | Converted from user time to the server-time column |
| Search | `search` | Matches either value |

The changed-field filter offering the display column's own list is what makes attachments and links
filterable: `~file~` and `~link~` are values the rows genuinely carry, so "show me only
attachments" and "show me only status changes" both work without inventing a second name for them.
Attachments cannot be matched against a user, a date or a search term - they are not in the history
table - so those filters exclude them, and a changed-field filter includes them only when it lists
`~file~`.

The server accepts **only** those keys (`Etemplate\Widget\HistoryLog::$allowed_filters`). Anything
else is dropped rather than passed to the database, and `record_id`/`appname` are re-derived
server-side from the request's own content, so a client cannot ask for another entry's history.

The "User" column holds two widgets, and each renders its own field: a readonly
`et2-select-account` for `owner`, and an `et2-description` for `share_email`. A change made through
a share records who the share was made out to rather than an account, so there is no account to
show for those rows - the account hides itself (`hidden="$row_cont[share_email]"`) and the recorded
address is shown instead. Both widgets sit in an `et2-hbox`: a `<row>`'s direct children *are* its
cells, so two of them side by side would make a sixth cell in a five-column grid, which is silently
dropped.

A row whose `owner` is an account that no longer exists - or `0`, which attachment rows inherit
from a directory created by a system process - shows an empty User cell. There is no name to
resolve, and that is what the legacy widget did too.

## What it deliberately does not do

Worth knowing, because each of these is a nextmatch feature someone may expect:

- **No sorting.** Newest first, always. The server does not accept a client-supplied sort order.
- **No row selection.** There are no actions on a history row. Arrow keys still move a visible
  cursor for keyboard users, but nothing is ever selected.
- **No column selection and no column preferences.** Columns come from the `columns` attribute every
  time; the datagrid's column chooser is switched off.
- **No favourites, no print dialog, no letter search, no auto-refresh, no expandable rows.**

## Attributes

| Attribute | Default | Purpose |
|---|---|---|
| `columns` | `user_ts,owner,status,new_value,old_value` | Which columns to show. Unlisted ones are hidden, not removed - they stay available in the column chooser. |
| `status_id` | `status` | Id for the "Changed" column's widget. Move it if the surrounding dialog already has a widget called `status`; calendar does. Must not equal the history log's own id. |
| `lazy` | `true` | Wait for the tab to be shown before loading. |
| `auto-height` | `false` | Grow to fit rows instead of scrolling internally. |
| `get_rows` | `Api\Storage\History::get_rows` | Row source. Read server-side from the template only. |

## Internals

Three pieces, split by what varies:

- **`Et2Historylog`** owns the grid, the filters and the lifecycle. It implements
  `NextmatchInterface`, which is what lets `Et2Filterbox` drive it, and
  `NextmatchDataProviderHost`, which is what lets it reuse `Et2NextmatchDataProvider` - including
  that provider's row-cache keep-alive bookkeeping, without which egw's 5-minute sweep would evict
  rows the grid can still scroll back to.
- **`Et2HistorylogWidgetRegistry`** resolves `status-widgets` (plus custom fields and the built-in
  statuses) into `{tagName, attrs}` render specs. Specs rather than widget instances: the legacy
  widget built a live widget for every declared field *and* every custom field before the tab was
  looked at.
- **`Et2HistorylogValue`** and **`Et2HistorylogStatus`** are the cells that cannot be plain widgets.
  They exist because the datagrid renders a *fixed* row template while the history log needs a
  different widget per row - so the tag in the row template is fixed and the variation lives inside
  it. Both are bound with `id="$row"`, receiving the whole row rather than one field, because both
  need the row's `status` to decide what to show. The other three columns are ordinary widgets
  (`et2-date-time`, `et2-select-account`).

The row and filter structure is an ordinary eTemplate, `api/templates/default/historylog.xet`, read
by `Et2RowProvider` like any other datagrid row template.

Row cells find their owner by walking up shadow boundaries: row widgets have no widget-tree parent
(the datagrid clones a template rather than building child widgets) and rows live in the datagrid's
shadow DOM, so neither `getParent()` nor a plain `closest()` reaches it.
