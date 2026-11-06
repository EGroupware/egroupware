## Examples

The four priorities used across EGroupware entries: low, normal, high, and "undefined" for not
answered. The list is built in the browser and translated, so it needs nothing from the server.

Everything on [et2-select](/components/et2-select) works here too.

### Basic

Values are `"1"` low, `"2"` normal, `"3"` high and `"0"` undefined - note that undefined sorts last
in the list but is not the largest number.

```html:preview
<et2-select-priority id="priority-basic" label="Priority" value="2"></et2-select-priority>
<p>Value: <span id="priority-basic-output">?</span></p>
<script>
    const priority = document.getElementById("priority-basic");
    const priorityOutput = document.getElementById("priority-basic-output");
    const showPriority = () => {priorityOutput.textContent = JSON.stringify(priority.value);};

    priority.addEventListener("change", showPriority);
    customElements.whenDefined("et2-select-priority").then(() => priority.updateComplete).then(showPriority);
</script>
```

### As a filter

`multiple` with an `emptyLabel` is the usual shape in a
[nextmatch](/components/et2-nextmatch) filter, where "no priority chosen" means "all of them".

```html:preview
<et2-select-priority id="priority-filter" multiple emptyLabel="All priorities"></et2-select-priority>
```
