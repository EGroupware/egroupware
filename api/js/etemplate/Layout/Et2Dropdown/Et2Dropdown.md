`et2-dropdown` is a panel that hangs off a trigger and floats over the page. It is the plumbing
behind menus and overflow buttons: it positions the panel, flips it when it would leave the viewport,
closes it on `Escape` or on a click outside, and puts focus back on the trigger afterwards.

It holds two things: whatever you put in the `trigger` slot, and the panel content in the default
slot. What goes in the panel is up to you - a menu, a small form, a bit of help text.

If all you need is "pick one of these values", use a [select](/components/et2-select) instead. A
dropdown has no value and submits nothing.

## Examples

### A trigger and a panel

Clicking the trigger opens the panel; clicking anywhere else closes it.

```html:preview
<et2-dropdown>
    <et2-button slot="trigger" caret>Options</et2-button>
    <et2-vbox>
        <et2-checkbox>Show completed</et2-checkbox>
        <et2-checkbox>Show private</et2-checkbox>
    </et2-vbox>
</et2-dropdown>
```

### A menu

The common case. An `sl-menu` of `sl-menu-item`s gives the familiar menu look, and the `sl-select`
event tells you which item was chosen. The dropdown closes itself on a selection unless you set
`stayOpenOnSelect`.

```html:preview
<et2-dropdown id="dropdown-menu-example">
    <et2-button slot="trigger" caret>Actions</et2-button>
    <sl-menu>
        <sl-menu-item value="edit">Edit</sl-menu-item>
        <sl-menu-item value="copy">Copy</sl-menu-item>
        <sl-menu-item value="delete">Delete</sl-menu-item>
    </sl-menu>
</et2-dropdown>
<p>chosen: <span id="dropdown-menu-output">nothing</span></p>
<script>
    const out = document.getElementById("dropdown-menu-output");
    document.getElementById("dropdown-menu-example").addEventListener("sl-select", (e) =>
    {
        out.textContent = e.detail.item.value;
    });
</script>
```

### Where the panel appears

`placement` is a preference, not a promise - the panel is moved if it would not fit. `distance` and
`skidding` nudge it away from the trigger and along it.

```html:preview
<et2-dropdown placement="right-start" distance="10">
    <et2-button slot="trigger">To the right</et2-button>
    <et2-description value="placement=right-start"></et2-description>
</et2-dropdown>
<et2-dropdown placement="top" distance="10">
    <et2-button slot="trigger">Above</et2-button>
    <et2-description value="placement=top"></et2-description>
</et2-dropdown>
```

### Opening on hover

`toggleOnHover` opens the panel when the pointer enters the dropdown and closes it when it leaves.
Only use it for panels the user reads rather than uses - moving the mouse towards a control inside
can leave the dropdown on the way and close it.

```html:preview
<et2-dropdown toggleOnHover>
    <et2-button slot="trigger">Hover over me</et2-button>
    <et2-description value="No click needed."></et2-description>
</et2-dropdown>
```

### Escaping a scrolling container

A dropdown inside an element with `overflow: auto` is clipped by it, because the panel is a child of
that element like everything else. `hoist` switches the panel to fixed positioning so it can hang
outside.

Open both dropdowns below. The first one's panel is cut off at the bottom edge of its box; the second
one's is not.

```html:preview
<div style="height: 7em; overflow: auto; border: 1px solid var(--sl-color-neutral-300); padding: var(--sl-spacing-small)">
    <et2-dropdown>
        <et2-button slot="trigger">Clipped</et2-button>
        <et2-vbox>
            <et2-description value="First line"></et2-description>
            <et2-description value="Second line"></et2-description>
            <et2-description value="Third line - cut off"></et2-description>
        </et2-vbox>
    </et2-dropdown>
</div>
<div style="height: 7em; overflow: auto; border: 1px solid var(--sl-color-neutral-300); padding: var(--sl-spacing-small)">
    <et2-dropdown hoist>
        <et2-button slot="trigger">Hoisted</et2-button>
        <et2-vbox>
            <et2-description value="First line"></et2-description>
            <et2-description value="Second line"></et2-description>
            <et2-description value="Third line - still visible"></et2-description>
        </et2-vbox>
    </et2-dropdown>
</div>
```

### Opening it from javascript

`open` reflects the state, and `show()` / `hide()` change it. `sl-show` and `sl-hide` fire on the
dropdown either way.

```html:preview
<et2-dropdown id="dropdown-api-example">
    <et2-button slot="trigger">Trigger</et2-button>
    <et2-description value="Panel"></et2-description>
</et2-dropdown>
<et2-button id="dropdown-api-button" label="Toggle from outside"></et2-button>
<p>open: <span id="dropdown-api-output">false</span></p>
<script>
    const dropdown = document.getElementById("dropdown-api-example");
    const out = document.getElementById("dropdown-api-output");
    const show = () => {out.textContent = dropdown.open;};

    dropdown.addEventListener("sl-show", show);
    dropdown.addEventListener("sl-hide", show);
    document.getElementById("dropdown-api-button").addEventListener("click", () =>
    {
        customElements.whenDefined("et2-dropdown")
            .then(() => dropdown.updateComplete)
            .then(() => dropdown.open ? dropdown.hide() : dropdown.show());
    });
</script>
```
