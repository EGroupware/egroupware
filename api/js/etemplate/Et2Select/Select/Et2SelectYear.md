## Examples

Years around the current one. It is an [et2-select-number](/components/et2-select-number) whose `min`
and `max` are counted from this year rather than being absolute, so the list is generated in the
browser and stays current without anyone maintaining it.

### Basic

The default range is three years back to two years forward. The value is the four-digit year as a
string.

```html:preview
<et2-select-year id="year-basic" label="Year"></et2-select-year>
<p>Value: <span id="year-basic-output">?</span></p>
<script>
    const year = document.getElementById("year-basic");
    const yearOutput = document.getElementById("year-basic-output");
    year.value = "" + new Date().getFullYear();
    const showYear = () => {yearOutput.textContent = JSON.stringify(year.value);};

    year.addEventListener("change", showYear);
    customElements.whenDefined("et2-select-year")
        .then(() => year.updateComplete)
        .then(() => year.updateComplete)
        .then(showYear);
</script>
```

### A different range

`min` and `max` are offsets from this year, so negative is the past. A field for a birth year wants
a long range backwards and nothing ahead.

```html:preview
<et2-select-year id="year-range" label="Founded" min="-20" max="0"></et2-select-year>
```
