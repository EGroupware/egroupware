## What it is for

Shows every entry linked to one entry, comma separated on a single line. It is the compact member of
the family - small enough for a nextmatch row - and it is readonly: there is no delete button and no
context menu. Use [`<et2-link-list>`](/components/et2-link-list/) when the user has to be able to
remove links, and see
[Choosing a link widget](/components/et2-link/#choosing-a-link-widget) for the rest of the family.

Each entry in the list is an [`<et2-link>`](/components/et2-link/), so everything that page says
about titles, icons and clicking applies here too. The comma comes from CSS
(`et2-link::part(title):after`), not from the markup.

## Examples

:::warning
Which entries are linked, and what they are called, are both answered by the server. This
documentation site has no server, so the examples below hand the widget a ready-made list. The
[usual usage](#the-usual-usage-let-the-server-answer) further down is the one you want in a
template.
:::

### A list you already have

`set_value()` accepts an array of `{app, id, title}` objects and displays exactly those, without
asking the server anything.

```html:preview
<et2-link-string id="link-string-example"></et2-link-string>
<script>
    const links = document.getElementById("link-string-example");
    customElements.whenDefined("et2-link-string").then(() =>
    {
        links.set_value([
            {app: "addressbook", id: "42", title: "Fischer, Peter", link_id: 1},
            {app: "infolog", id: "7", title: "Call customer back", link_id: 2},
            {app: "timesheet", id: "19", title: "Support, 1.5h", link_id: 3}
        ]);
    });
</script>
```

### The usual usage: let the server answer

In a template you do not list the links. You name the entry - `application` plus `entryId`, or a
value of `{to_app, to_id}` - and the widget fetches its links itself. That is what a nextmatch row
does, where the entry ID arrives with the row data:

```html
<et2-link-string id="${row}[filelinks]"></et2-link-string>
<et2-link-string application="infolog" entryId="$row_cont[info_id]"></et2-link-string>
```

:::warning
Not live: both forms end in a `Link::ajax_link_list` request, which this documentation site cannot
answer, so the widget would sit on its loading skeleton forever.
:::

The widget is deliberately lazy about that request. It waits until it is actually on screen before
asking, because a nextmatch page of rows would otherwise cost one link lookup plus one title lookup
per row for links nobody has scrolled to yet. It also ignores a placeholder entry ID that is still
`$row_cont[...]`, and discards the answer to a request that has been superseded because the row was
re-used for another entry meanwhile.

### Narrowing what is shown

`onlyApp` limits the list to links into one application (or a comma separated list of them) -
InfoLog uses it to show file links and Kanban links in separate columns. `linkType` filters on the
link sub-type, and `showDeleted` includes links that are flagged deleted and waiting to be purged.

```html
<et2-link-string application="infolog" entryId="7" onlyApp="filemanager"></et2-link-string>
```

### Limiting how many are fetched

`limit` caps how many application links are requested at once; it defaults to the user's `maxmatchs`
preference. Anything beyond it is not dropped silently - a `...` with a "%1 more..." tooltip is
appended. (File links are always fetched in full.)

```html
<et2-link-string application="infolog" entryId="7" limit="5"></et2-link-string>
```
