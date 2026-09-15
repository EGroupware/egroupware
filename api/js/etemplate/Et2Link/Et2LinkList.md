## What it is for

Shows every entry linked to one entry as a list, one link per row, each with a delete button and a
context menu. It extends [`<et2-link-string>`](/components/et2-link-string/) and shares its value
handling, so everything that page says about naming the entry, `onlyApp`, `linkType`, `showDeleted`
and `limit` applies here too - the difference is the layout and that the user can act on the links.

This is the widget for a "Links" tab. Pair it with an
[`<et2-link-to>`](/components/et2-link-to/) that has the **same `id`**: the two halves then share one
value, `<et2-link-to>` creates links and `<et2-link-list>` picks them up and shows them.

```html
<et2-link-to id="link_to" span="all"></et2-link-to>
<et2-link-list id="link_to" span="all"></et2-link-list>
```

See [Choosing a link widget](/components/et2-link/#choosing-a-link-widget) for the rest of the
family.

## Examples

:::warning
As with `<et2-link-string>`, the list of links and their titles come from the server, so the
examples below hand the widget a ready-made list instead of naming an entry.
:::

### A list of links

Setting `value` to an array of `{app, id, title}` objects displays exactly those. `link_id` is the
ID of the link itself (not of either entry), and is what the delete button sends to the server.

```html:preview
<et2-link-list id="link-list-example"></et2-link-list>
<script>
    const list = document.getElementById("link-list-example");
    customElements.whenDefined("et2-link-list").then(() =>
    {
        list.value = [
            {app: "addressbook", id: "42", title: "Fischer, Peter", link_id: 1},
            {app: "infolog", id: "7", title: "Call customer back", link_id: 2, remark: "after the holidays"},
            {app: "timesheet", id: "19", title: "Support, 1.5h", link_id: 3}
        ];
    });
</script>
```

Hover a row to reveal its delete button.

### The context menu

Right-clicking a row opens a context menu, whose entries depend on what the row holds: put a comment
on the link, and for a file also show its file information, save it, mail it, or copy it into the
VFS. With two or more rows there is also "Save as Zip" for all of them at once. `readonly` suppresses
the whole menu.

:::warning
Not live: the menu items act through `egw.open()`, `Et2Dialog` and server requests, none of which
this documentation site has.
:::

### Deleting

Deleting is two different operations behind one button. For a saved entry the widget sends
`Link::ajax_delete` and only removes the row once the server confirms. For an entry that has no ID
yet - a new InfoLog the user is still typing - there is nothing on the server to delete, so the link
is simply dropped from the value that will be submitted.

The example above is the second case: it has no `entryId`, so its delete button really does remove
the row, with no server involved.

Every removal fires two events on the widget: `et2-before-delete` before, and `et2-delete` plus a
bubbling `change` after. `<et2-link-to>` listens for `et2-delete` so it can drop the link from what
it is holding for submission.

### Readonly

`readonly` removes the delete buttons and disables the context menu - the links are still clickable,
they just cannot be changed. Use it on a view template, or wherever the user has no edit rights.

```html:preview
<et2-link-list id="link-list-readonly-example" readonly></et2-link-list>
<script>
    const list = document.getElementById("link-list-readonly-example");
    customElements.whenDefined("et2-link-list").then(() =>
    {
        list.value = [
            {app: "addressbook", id: "42", title: "Fischer, Peter", link_id: 1},
            {app: "infolog", id: "7", title: "Call customer back", link_id: 2}
        ];
    });
</script>
```

### Reacting to changes

`onchange` is called whenever the list changes - the same hook the rest of eTemplate uses. The
widget also dispatches a bubbling `change` event, so a plain listener works just as well.

```js
this.et2.getWidgetById("link_to").addEventListener("change", () => this.recountLinks());
```
