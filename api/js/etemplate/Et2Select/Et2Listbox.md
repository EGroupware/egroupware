## Examples

A listbox shows its options all the time instead of hiding them behind a dropdown. That is its only
reason to exist, and it costs vertical space on every screen the template is used on - prefer
[et2-select](/components/et2-select) unless the reader really needs to see the whole list at once.

### Options

Options come from the server for most select widgets. Set them yourself by assigning an array of
`{value, label}` objects to `select_options`.

```html:preview
<et2-listbox id="listbox-basic"></et2-listbox>
<script>
    document.getElementById("listbox-basic").select_options = [
        {value: "1", label: "Low"},
        {value: "2", label: "Normal"},
        {value: "3", label: "High"},
        {value: "4", label: "Urgent"}
    ];
</script>
```

### Value

Clicking an option checks it and fires `change`. Without `multiple` the value is the single checked
option's value; the previously checked one is cleared for you.

```html:preview
<et2-listbox id="listbox-value" value="2"></et2-listbox>
<p>Value: <span id="listbox-value-output">?</span></p>
<script>
    const listbox = document.getElementById("listbox-value");
    const listboxOutput = document.getElementById("listbox-value-output");
    listbox.select_options = [
        {value: "1", label: "Low"},
        {value: "2", label: "Normal"},
        {value: "3", label: "High"}
    ];
    const showValue = () => {listboxOutput.textContent = JSON.stringify(listbox.value);};

    listbox.addEventListener("change", showValue);
    customElements.whenDefined("et2-listbox").then(() => listbox.updateComplete).then(showValue);
</script>
```

### Multiple

`multiple` lets every option be checked independently, and the value becomes an array. Nothing is
deselected on the user's behalf, so clicking is a toggle.

```html:preview
<et2-listbox id="listbox-multiple" multiple></et2-listbox>
<p>Value: <span id="listbox-multiple-output">?</span></p>
<script>
    const multi = document.getElementById("listbox-multiple");
    const multiOutput = document.getElementById("listbox-multiple-output");
    multi.select_options = [
        {value: "phone", label: "Phone"},
        {value: "email", label: "Email"},
        {value: "fax", label: "Fax"},
        {value: "post", label: "Post"}
    ];
    multi.value = ["email", "post"];
    const showMulti = () => {multiOutput.textContent = JSON.stringify(multi.value);};

    multi.addEventListener("change", showMulti);
    customElements.whenDefined("et2-listbox").then(() => multi.updateComplete).then(showMulti);
</script>
```

### Rows

`rows` limits the visible height to that many options and scrolls the rest. It is set as a property
rather than read off the attribute, so from a template write `rows="4"`, and from javascript assign
`widget.rows = 4` once the element has upgraded.

```html:preview
<et2-listbox id="listbox-rows"></et2-listbox>
<script>
    const rowsBox = document.getElementById("listbox-rows");
    rowsBox.select_options = [
        {value: "1", label: "January"}, {value: "2", label: "February"},
        {value: "3", label: "March"}, {value: "4", label: "April"},
        {value: "5", label: "May"}, {value: "6", label: "June"},
        {value: "7", label: "July"}, {value: "8", label: "August"}
    ];
    customElements.whenDefined("et2-listbox")
        .then(() => rowsBox.updateComplete)
        .then(() => {rowsBox.rows = 4;});
</script>
```

### Icons

An option's `icon` is rendered before its label, using the same image names as
[et2-image](/components/et2-image).

```html:preview
<et2-listbox id="listbox-icons"></et2-listbox>
<script>
    document.getElementById("listbox-icons").select_options = [
        {value: "phone", label: "Phone", icon: "telephone"},
        {value: "email", label: "Email", icon: "envelope"},
        {value: "post", label: "Post", icon: "printer"}
    ];
</script>
```
