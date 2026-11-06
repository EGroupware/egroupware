## What it is for

The readonly form of [`<et2-link-entry>`](/components/et2-link-entry/). You rarely write this tag
yourself: eTemplate appends `_ro` to a widget's tag name when it is readonly, so
`<et2-link-entry readonly="true">` in a template loads this widget instead.

It is [`<et2-link>`](/components/et2-link/) - same class, same properties, same behaviour - under a
second tag name, so that a field the user picks an entry with turns into a display of that entry
rather than disappearing. Read that page for what the properties do; this one only shows how the
two tags line up.

See [Choosing a link widget](/components/et2-link/#choosing-a-link-widget) for the rest of the
family.

## Examples

:::warning
A link's title comes from the server, and this documentation site has none, so the example below
passes the title in with the link. In a real template the widget is given the entry and fetches the
title itself.
:::

### The readonly half of a link-entry field

InfoLog's "Project" field is `<et2-link-entry id="pm_id" onlyApp="projectmanager">`. On a readonly
template the same field arrives as this widget, with the ID the user picked:

```html:preview
<et2-link-entry_ro id="link-entry-ro-example" app="projectmanager"></et2-link-entry_ro>
<script>
    const entry = document.getElementById("link-entry-ro-example");
    customElements.whenDefined("et2-link-entry_ro").then(() =>
    {
        entry.value = {app: "projectmanager", id: "3", title: "2026-014: Website relaunch"};
    });
</script>
```

Because it is an `<et2-link>`, it is still clickable: it opens the entry with `egw.open()`, the
same as everywhere else links are displayed.

### No value

Given nothing to show - an empty field on a readonly template - it renders nothing at all, rather
than an empty box or a placeholder.

```html:preview
<et2-link-entry_ro id="link-entry-ro-empty" app="projectmanager"></et2-link-entry_ro>
<span>(nothing above this line)</span>
```
