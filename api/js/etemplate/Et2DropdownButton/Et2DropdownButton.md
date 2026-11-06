## Examples

A split button: a [button](/components/et2-button) on the left that does the currently selected
thing, and an arrow on the right that opens the list of the other things it could do. Use it when
there is an obvious default action with a handful of near-identical variations - "Save" with
"Save & New" and "Save & Reset" behind it - and a row of separate buttons would be noise.

The choices come from `select_options`, the same shape a [select](/components/et2-select) takes.
In a template that is usually `<option>` children or server-side `sel_options`; in javascript it is
an array of `{value, label}`.

:::warning
The previews below assign `select_options` from a script, because there is no server here to
supply them. The assignment has to wait for `customElements.whenDefined()` - setting the property
on an element that has not upgraded yet would shadow the widget's own setter and the options would
never arrive.
:::

### Options

`change` fires when a menu entry is picked; `click` on the main button fires with whatever is
currently selected.

```html:preview
<et2-dropdown-button id="dropdown-example" label="Save &amp; New"></et2-dropdown-button>
<p>Selected: <span id="dropdown-output">save_new</span></p>
<script>
    const dropdown = document.getElementById("dropdown-example");
    const output = document.getElementById("dropdown-output");
    customElements.whenDefined("et2-dropdown-button").then(() =>
    {
        dropdown.select_options = [
            {value: "save_new", label: "Save & New"},
            {value: "save_reset", label: "Save & Reset"},
            {value: "save_close", label: "Save & Close"}
        ];
    });
    dropdown.addEventListener("change", () => {output.textContent = dropdown.value;});
</script>
```

Note that picking an option only changes the selection - it does not run the action. The main
button is what the user clicks to do the thing, which is why the label follows the selection.

### Icons on the options

An option can carry an `icon`, and a `color` that tints its row in the menu.

```html:preview
<et2-dropdown-button id="dropdown-icon-example" label="Export"></et2-dropdown-button>
<script>
    const iconDropdown = document.getElementById("dropdown-icon-example");
    customElements.whenDefined("et2-dropdown-button").then(() =>
    {
        iconDropdown.select_options = [
            {value: "csv", label: "Export as CSV", icon: "filetype-csv"},
            {value: "pdf", label: "Export as PDF", icon: "filetype-pdf"},
            {value: "print", label: "Print", icon: "printer"}
        ];
    });
</script>
```

### Icon only

`iconOnly` drops the text from the main button and shows the selected option's own icon instead.
Useful in a toolbar where the row of buttons has to stay narrow.

It needs something to be selected, or there is nothing to draw and the main button comes up empty.
Marking an option `default: true` is *not* enough on its own - that flag is only read when
`defaultPreference` is set (see below) - so give the widget a `value` as well.

```html:preview
<et2-dropdown-button id="dropdown-icononly-example" iconOnly></et2-dropdown-button>
<script>
    const iconOnlyDropdown = document.getElementById("dropdown-icononly-example");
    customElements.whenDefined("et2-dropdown-button").then(() =>
    {
        iconOnlyDropdown.select_options = [
            {value: "list", label: "List", icon: "list-ul"},
            {value: "grid", label: "Grid", icon: "grid-3x2-gap"},
            {value: "calendar", label: "Calendar", icon: "calendar3"}
        ];
        iconOnlyDropdown.value = "list";
    });
</script>
```

### Remembering the choice

`defaultPreference` names a preference to store the selection in. The stored value is preselected
on the next load, and every pick is written back - so the main button becomes "whatever you did
last time" rather than doing nothing useful until the user has opened the menu once. An option
marked `default: true` is the fallback for a user who has never picked anything.

```html
<et2-dropdown-button id="save_split" defaultPreference="save_new" onclick="app.myapp.save_action" onchange="app.myapp.save_action">
    <option value="save_new" default="save_new">Save &amp; New</option>
    <option value="save_reset">Save &amp; Reset</option>
</et2-dropdown-button>
```

This one is not a live preview: storing a preference is a round-trip to the server.

### Placement

`placement` moves the menu, for a button near the bottom or the right edge of a dialog. It defaults
to `bottom-end`.

```html:preview
<et2-dropdown-button id="dropdown-placement-example" label="Menu on top" placement="top-start"></et2-dropdown-button>
<script>
    const placementDropdown = document.getElementById("dropdown-placement-example");
    customElements.whenDefined("et2-dropdown-button").then(() =>
    {
        placementDropdown.select_options = [
            {value: "one", label: "First choice"},
            {value: "two", label: "Second choice"}
        ];
    });
</script>
```

### Disabled

`disabled` greys out both halves.

```html:preview
<et2-dropdown-button id="dropdown-disabled-example" label="Nothing to save" disabled></et2-dropdown-button>
<script>
    const disabledDropdown = document.getElementById("dropdown-disabled-example");
    customElements.whenDefined("et2-dropdown-button").then(() =>
    {
        disabledDropdown.select_options = [{value: "one", label: "First choice"}];
    });
</script>
```

`readonly` renders nothing at all - there is no value worth showing when the actions are gone.
