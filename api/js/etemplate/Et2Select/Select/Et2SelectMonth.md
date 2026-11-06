## Examples

The twelve months, January to December. The list is built in the browser and translated, so it needs
nothing from the server.

Everything on [et2-select](/components/et2-select) works here too.

### Basic

The value is the month number as a string, `"1"` for January through `"12"` for December.

```html:preview
<et2-select-month id="month-basic" label="Month" value="9"></et2-select-month>
<p>Value: <span id="month-basic-output">?</span></p>
<script>
    const month = document.getElementById("month-basic");
    const monthOutput = document.getElementById("month-basic-output");
    const showMonth = () => {monthOutput.textContent = JSON.stringify(month.value);};

    month.addEventListener("change", showMonth);
    customElements.whenDefined("et2-select-month").then(() => month.updateComplete).then(showMonth);
</script>
```

### Several months

`multiple` gives an array of month numbers - useful for "which months does this repeat in".

```html:preview
<et2-select-month id="month-multiple" label="Repeats in" multiple></et2-select-month>
<script>
    document.getElementById("month-multiple").value = ["3", "6", "9", "12"];
</script>
```
