## Examples

One entry in a menu. It is what the context menu built by the action system renders each action
as, and what [et2-ai](/components/et2-ai) and the markdown editor's style dropdown use for their
own menus - so most of the time you get menu items without writing one. When you do write one, it
has to live inside an `sl-menu`; on its own it has nothing to be an item of.

### A menu

`value` is what the menu reports as selected. Listen for `sl-select` on the menu, not on the item.

```html:preview
<sl-menu id="menu-example" style="max-width: 20em;">
    <et2-menu-item value="open">Open</et2-menu-item>
    <et2-menu-item value="edit">Edit</et2-menu-item>
    <sl-divider></sl-divider>
    <et2-menu-item value="delete">Delete</et2-menu-item>
</sl-menu>
<p>Picked: <span id="menu-output">nothing yet</span></p>
<script>
    const menu = document.getElementById("menu-example");
    const out = document.getElementById("menu-output");
    menu.addEventListener("sl-select", (event) => {out.textContent = event.detail.item.value;});
</script>
```

### Icons and shortcuts

The `prefix` slot takes an icon, the `suffix` slot the keyboard shortcut - that is how the context
menu lays out both.

```html:preview
<sl-menu style="max-width: 20em;">
    <et2-menu-item value="save">
        <et2-image slot="prefix" src="save"></et2-image>
        Save
        <span slot="suffix">Ctrl+S</span>
    </et2-menu-item>
    <et2-menu-item value="print">
        <et2-image slot="prefix" src="printer"></et2-image>
        Print
        <span slot="suffix">Ctrl+P</span>
    </et2-menu-item>
</sl-menu>
```

### Checkboxes

`type="checkbox"` turns the item into something that can be ticked, with `checked` for the initial
state. The menu ticks it for you - do not toggle it again in your own handler, or the two
cancel out and the tick never appears to move. By the time `sl-select` reaches you, the item is
already in its new state, so read `event.detail.item.checked` rather than inverting it.

```html:preview
<sl-menu id="menu-checkbox-example" style="max-width: 20em;">
    <et2-menu-item type="checkbox" value="details" checked>Show details</et2-menu-item>
    <et2-menu-item type="checkbox" value="deleted">Show deleted</et2-menu-item>
</sl-menu>
<p>Showing: <span id="menu-checkbox-output">details</span></p>
<script>
    const checkMenu = document.getElementById("menu-checkbox-example");
    const checkOut = document.getElementById("menu-checkbox-output");
    checkMenu.addEventListener("sl-select", () =>
    {
        const on = Array.from(checkMenu.querySelectorAll("et2-menu-item[type=checkbox]"))
            .filter(item => item.checked).map(item => item.value);
        checkOut.textContent = on.join(", ") || "nothing";
    });
</script>
```

### Submenus

Nest an `sl-menu` in the `submenu` slot.

```html:preview
<sl-menu style="max-width: 20em;">
    <et2-menu-item value="open">Open</et2-menu-item>
    <et2-menu-item value="export">
        Export as
        <sl-menu slot="submenu">
            <et2-menu-item value="csv">CSV</et2-menu-item>
            <et2-menu-item value="pdf">PDF</et2-menu-item>
        </sl-menu>
    </et2-menu-item>
</sl-menu>
```

### Disabled vs hidden

A menu item is the exception to the usual eTemplate rule. Everywhere else `disabled` on a
non-input widget takes the widget off the page entirely (see
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden)), but a
disabled menu entry has to stay visible and greyed: that an action exists but is not available
right now is exactly what the menu is there to say. So `et2-menu-item` undoes the inherited rule,
and `hidden` is the flag that actually removes an item.

Both are below. "Edit" is disabled and still there; "Delete" is hidden and is not.

```html:preview
<sl-menu style="max-width: 20em;">
    <et2-menu-item value="open">Open</et2-menu-item>
    <et2-menu-item value="edit" disabled>Edit</et2-menu-item>
    <et2-menu-item value="delete" hidden>Delete</et2-menu-item>
</sl-menu>
```

The action system drives both from an `EgwAction`: `enabled` becomes `disabled` and `visible`
becomes `hidden`, so an action's `hideOnDisabled` flag is what decides which of the two you get.
