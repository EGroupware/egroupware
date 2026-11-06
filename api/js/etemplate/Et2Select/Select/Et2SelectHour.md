## Examples

The 24 hours of a day. The list is built in the browser, so it needs nothing from the server.

The labels follow the user's time format preference: `00` to `23` on a 24 hour clock, `12 am` to
`11 pm` on a 12 hour one. The value does not - it is always the 24 hour number, so a template does
not have to care which preference is set.

### Basic

```html:preview
<et2-select-hour id="hour-basic" label="Starts at" value="9"></et2-select-hour>
<p>Value: <span id="hour-basic-output">?</span></p>
<script>
    const hour = document.getElementById("hour-basic");
    const hourOutput = document.getElementById("hour-basic-output");
    const showHour = () => {hourOutput.textContent = JSON.stringify(hour.value);};

    hour.addEventListener("change", showHour);
    customElements.whenDefined("et2-select-hour").then(() => hour.updateComplete).then(showHour);
</script>
```

### A range of hours

`multiple` gives an array, for picking the hours something applies to rather than one moment.

```html:preview
<et2-select-hour id="hour-multiple" label="Office hours" multiple></et2-select-hour>
<script>
    document.getElementById("hour-multiple").value = ["8", "9", "10", "11"];
</script>
```
