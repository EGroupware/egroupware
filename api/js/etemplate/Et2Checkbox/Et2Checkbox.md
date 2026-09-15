## Examples

### Label

The label is the checkbox's own content, not an attribute.

```html:preview
<et2-checkbox>Send a confirmation</et2-checkbox>
```

### Checked

`checked` sets the initial state.

```html:preview
<et2-checkbox checked>Send a confirmation</et2-checkbox>
```

### Submitted values

A checkbox submits `true` or `false` by default. Set `selectedValue` and `unselectedValue` to submit
something else - useful when the field behind it stores a code rather than a boolean.

```html:preview
<et2-checkbox id="notify-example" selectedValue="Y" unselectedValue="N" checked>Notify me</et2-checkbox>
<p>Submits: <span id="notify-output"></span></p>
<script>
    const box = document.getElementById("notify-example");
    const out = document.getElementById("notify-output");
    const show = () => {out.textContent = JSON.stringify(box.value);};

    box.addEventListener("change", show);
    customElements.whenDefined("et2-checkbox").then(() => box.updateComplete).then(show);
</script>
```

### Indeterminate

A checkbox has a third state for "not answered", distinct from unchecked. Set the value to
`Et2Checkbox.INDETERMINATE` (the string `***undefined***`) to get it; its `value` is then
`undefined` rather than either of the two above.

Only that one explicit value turns it on. Every other value that is neither `selectedValue` nor
`unselectedValue` is treated as a plain truthy or falsy check, because eTemplate content has always
set checkboxes from all kinds of loose values and they must keep meaning checked/unchecked.

```html:preview
<et2-checkbox id="indeterminate-example" value="***undefined***">Not answered yet</et2-checkbox>
<script>
    const box = document.getElementById("indeterminate-example");
    customElements.whenDefined("et2-checkbox")
        .then(() => box.updateComplete)
        .then(() => {box.indeterminate = true;});
</script>
```

### Disabled and readonly

`disabled` means "not right now" and can be turned off again from javascript. `readonly` shows the
value without allowing any change and submits nothing. See
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden).

```html:preview
<et2-checkbox checked disabled>Disabled</et2-checkbox>
<et2-checkbox checked readonly>Readonly</et2-checkbox>
```
