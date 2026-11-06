## Choosing a link widget

A *link* joins two entries across applications - a contact attached to an InfoLog task, a file
attached to a project. One end of a link is always an application name plus that application's own
ID for the entry, written `<app>:<id>`. See
[Linking system widgets](/reference/linking-system/) for the group as a whole.

All ten widgets in the family are built on that one idea, and differ only in how many links they
show at once and whether the user can change them:

| Widget | What it is for |
| --- | --- |
| [`<et2-link>`](/components/et2-link/) | Show **one** entry as a clickable title |
| [`<et2-link-entry_ro>`](/components/et2-link-entry_ro/) | The same display, used automatically when `<et2-link-entry>` is readonly |
| [`<et2-link-string>`](/components/et2-link-string/) | Show **all** links of an entry, comma separated on one line |
| [`<et2-link-list>`](/components/et2-link-list/) | Show **all** links of an entry as a list, with delete and a context menu |
| [`<et2-link-to>`](/components/et2-link-to/) | **Create** links: pick an entry, a VFS file, or upload a file |
| [`<et2-link-entry>`](/components/et2-link-entry/) | **Pick** one entry - application selector plus search. This is the input inside `<et2-link-to>` |
| [`<et2-link-apps>`](/components/et2-link-apps/) | Just the application selector out of `<et2-link-entry>` |
| [`<et2-link-search>`](/components/et2-link-search/) | Just the entry search out of `<et2-link-entry>` |
| [`<et2-link-add>`](/components/et2-link-add/) | Not a link at all - a button that opens another application's "add entry" dialog |
| [`<et2-link-paste-dialog>`](/components/et2-link-paste-dialog/) | Pick files out of the EGroupware file clipboard so they can be linked |

Two short rules cover most templates:

- To show what is already linked, use `<et2-link-string>` (one line, for a nextmatch row) or
  `<et2-link-list>` (a list, for a "Links" tab).
- To let the user add links, use `<et2-link-to>`, and give it the **same `id`** as the
  `<et2-link-list>` beside it so the two halves share one value. That pair is the whole "Links" tab
  in InfoLog, Timesheet, Calendar and friends.

## Examples

:::warning
A link's *title* lives on the server: given `addressbook:42` the widget calls
`egw.link_title()` to find out that entry 42 is "Fischer, Peter". This documentation site has no
server, so the examples below pass the title in with the link. In a real template you give only the
application and the ID and let the widget fetch the title.
:::

### One entry

`app` and `entryId` say which entry to show. Passing a `{app, id, title}` object as the value sets
all three at once, and a title supplied this way is used as-is instead of being looked up.

```html:preview
<et2-link id="link-example"></et2-link>
<script>
    const link = document.getElementById("link-example");
    customElements.whenDefined("et2-link").then(() =>
    {
        link.value = {app: "addressbook", id: "42", title: "Fischer, Peter"};
    });
</script>
```

Clicking it calls `egw.open()` for that entry. `linkHook` chooses which view you land in
(`view`, the default, or `edit` or `add`), and `extraLinkTarget` is passed on as the window target,
so `extraLinkTarget="_blank"` opens the entry in a new tab.

### An extra remark

Any other key in the value object is kept in the element's `dataset`, and `remark` - the comment a
user can put on a link - is rendered after the title. It is hidden by
`<et2-link-string>` and shown by `<et2-link-list>`.

```html:preview
<et2-link id="link-remark-example"></et2-link>
<script>
    const link = document.getElementById("link-remark-example");
    customElements.whenDefined("et2-link").then(() =>
    {
        link.value = {app: "addressbook", id: "42", title: "Fischer, Peter", remark: "Ordered twice"};
    });
</script>
```

### A link to an external URL

`url` is a pseudo-application: there is no such EGroupware application, the "entry ID" is the URL
itself, and clicking opens it in a new tab instead of going through `egw.open()`. Because no
application is registered for it there is no application icon to look up either, so it carries a
small inline one of its own.

```html:preview
<et2-link id="link-url-example"></et2-link>
<script>
    const link = document.getElementById("link-url-example");
    customElements.whenDefined("et2-link").then(() =>
    {
        link.value = {app: "url", id: "https://www.egroupware.org", title: "www.egroupware.org"};
    });
</script>
```

### Breaking a long title

Titles are one line and get an ellipsis when they do not fit. `breakTitle` names a string the title
is allowed to wrap after - the widget puts a zero-width space behind every occurrence and protects
the remaining spaces and hyphens, so the title only ever breaks where you said it may.

```html:preview
<et2-link id="link-break-example" breakTitle=":" style="width: 12em"></et2-link>
<script>
    const link = document.getElementById("link-break-example");
    customElements.whenDefined("et2-link").then(() =>
    {
        link.value = {app: "infolog", id: "7", title: "Phone call: ask about the renewal"};
    });
</script>
```
