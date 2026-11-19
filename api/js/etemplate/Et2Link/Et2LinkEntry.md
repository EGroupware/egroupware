## What it is for

Picks **one** entry from any application: an application selector
([`<et2-link-apps>`](/components/et2-link-apps/)) next to a search box
([`<et2-link-search>`](/components/et2-link-search/)). Choose the application, type, pick a result -
the application selector then folds away, because the entry already says which application it is
from.

It is an input widget, so it has a value and submits like any other field. That distinguishes it
from [`<et2-link-to>`](/components/et2-link-to/), which uses one of these internally but creates real
links instead of submitting a value. InfoLog's "Project" and "Parent" fields are plain
`<et2-link-entry>` fields, not links.

Made readonly, it is replaced by [`<et2-link-entry_ro>`](/components/et2-link-entry_ro/). See
[Choosing a link widget](/components/et2-link/#choosing-a-link-widget) for the rest of the family.

:::warning
None of the examples below are live. Searching sends `Link::ajax_link_search` to the server, and the
list of applications to choose from needs a current application to default to - neither exists on
this documentation site, so the widget would render a search box that never finds anything.
Everything below describes the behaviour against a running EGroupware.
:::

## Examples

### Any application

With nothing else set, the user picks the application first.

```html
<et2-link-entry id="info_contact"></et2-link-entry>
```

### One application

`onlyApp` fixes the application and hides the selector, leaving just the search box. Most real uses
look like this - the field means something specific, so only one application makes sense.

```html
<et2-link-entry id="pm_id" onlyApp="projectmanager" placeholder="None"></et2-link-entry>
```

`applicationList` is the middle ground: a comma separated set of applications to offer instead of
all of them. `appIcons` shows the applications as icons rather than names.

### Value

Without `onlyApp` the value is a `{app, id}` object, because the application is part of what the
user chose. With `onlyApp` it is the bare ID string - the application is already known from the
template. Setting a value accepts either, plus the `<app>:<id>` string form:

```js
const entry = this.et2.getWidgetById("info_contact");
entry.value = {app: "addressbook", id: "42"};
entry.value = "addressbook:42";     // same thing
```

A value can carry a `title` too, and then the widget shows it immediately instead of asking the
server for it - useful when you already have it.

### Linking an arbitrary URL

`url` in the application selector is not a real application: picking it swaps the search box for a
plain URL input, and the value becomes `{app: "url", id: <the URL>}`. There is nothing to search
for, so nothing is sent to the server.

### Hooking into the search

`searchOptions` is an object passed straight through to the server with every search, for extra
filtering. `query` is a callback invoked with the request before it is sent - return false to abort
it - which is how InfoLog keeps an entry from being offered as its own parent:

```html
<et2-link-entry id="info_id_parent" onlyApp="infolog" query="app.infolog.parent_query"></et2-link-entry>
```

### Only entries linked to another entry

A `linked` filter in `searchOptions` limits the search to entries linked to one other entry - the
InfoLogs of a project, say. It names that entry as `"app:id"`, and is set from JavaScript, as the
entry usually depends on what is being edited:

```js
const parent = <Et2LinkEntry>this.et2.getWidgetById("info_id_parent");
parent.searchOptions = {filter: {linked: "projectmanager:" + projectId}};
```

`linked` can also be a list of entries, to offer what is linked to any of them - a project and
some of its sub-projects, for example. An entry of the list that does not exist, or the user has no
access to, adds nothing:

```js
parent.searchOptions = {filter: {linked: ["projectmanager:" + projectId, "projectmanager:" + subProjectId]}};
```

Assign a new object to change or remove the filter, the widget does not notice a change made
inside the one it already has. Without a project, leave `linked` out: a filter naming no entry, or
one the user has no access to, finds nothing rather than everything.

The server looks up which entries are linked and hands their ids to the application's own link
search, to search only those with whatever the user typed - see `Api\Link::query()`. Currently
only InfoLog's search supports that. Any other application ignores the filter and offers all its
matches, as it does without it.

The filter narrows what the search offers. It is not checked when the value is saved, and an entry
already selected stays selected when the filter changes.

### Change events

The widget fires a plain bubbling `change` event when an entry is selected or cleared, so
`onchange` in a template works as usual.

```html
<et2-link-entry id="pm_id" onchange="app.infolog.submit_if_not_empty" onlyApp="projectmanager"></et2-link-entry>
```
