## Examples

Day of the month, 1 to 31. The list is built in the browser and is always the full 31 days - it does
not know which month you mean, so February still offers the 30th.

It is a [et2-select-number](/components/et2-select-number) with `min` and `max` fixed, so everything
on [et2-select](/components/et2-select) applies here too.

### Basic

The value is the day number as a string.

```html:preview
<et2-select-day id="day-basic" label="Day of month" value="15"></et2-select-day>
<p>Value: <span id="day-basic-output">?</span></p>
<script>
    const day = document.getElementById("day-basic");
    const dayOutput = document.getElementById("day-basic-output");
    const showDay = () => {dayOutput.textContent = JSON.stringify(day.value);};

    day.addEventListener("change", showDay);
    customElements.whenDefined("et2-select-day").then(() => day.updateComplete).then(showDay);
</script>
```

### Several days

`multiple` gives an array of day numbers - "on the 1st and the 15th" is two entries, not two widgets.

```html:preview
<et2-select-day id="day-multiple" label="Due on" multiple emptyLabel="Any day"></et2-select-day>
<script>
    document.getElementById("day-multiple").value = ["1", "15"];
</script>
```
