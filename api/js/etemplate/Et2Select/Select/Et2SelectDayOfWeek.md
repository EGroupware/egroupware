## Examples

The days of the week, as a set. The list comes from the server, which orders it by the user's
"week starts on" preference and translates the day names.

Set `multiple`. A set of days is the whole point of the widget, and without it only one day can ever
be chosen, which the integer value below cannot express properly.

### Value

The days are flags - Sunday 1, Monday 2, Tuesday 4, and so on - and what is stored is the single
integer you get by adding the chosen ones together. Set the value as that integer and the widget
expands it back into the right days for you; the server adds them up again on the way out.

The list below is written out by hand so the example works here, where there is no server to ask.

```html:preview
<et2-select-dow id="dow-value" label="Repeats on" multiple></et2-select-dow>
<p>Value: <span id="dow-value-output">?</span></p>
<script>
    const dow = document.getElementById("dow-value");
    const dowOutput = document.getElementById("dow-value-output");
    dow.select_options = [
        {value: "2", label: "Monday"}, {value: "4", label: "Tuesday"},
        {value: "8", label: "Wednesday"}, {value: "16", label: "Thursday"},
        {value: "32", label: "Friday"}, {value: "64", label: "Saturday"},
        {value: "1", label: "Sunday"}
    ];
    // 2 + 8 + 32 - Monday, Wednesday and Friday
    dow.value = 42;
    const showDow = () => {dowOutput.textContent = JSON.stringify(dow.value);};

    dow.addEventListener("change", showDow);
    // Two waits: the first settles the options, the second the value they are expanded against
    customElements.whenDefined("et2-select-dow")
        .then(() => dow.updateComplete)
        .then(() => dow.updateComplete)
        .then(showDow);
</script>
```

### Summary options

Set `rows` to 2 or more and the server adds "all days", "working days" and "weekend" above the
individual days. Their values are the sums of the days they stand for, so they need no special
handling anywhere - 127 really is every day at once.

```html:preview
<et2-select-dow id="dow-rows" label="Repeats on" multiple rows="2"></et2-select-dow>
<script>
    document.getElementById("dow-rows").select_options = [
        {value: "127", label: "all days"}, {value: "62", label: "working days"},
        {value: "65", label: "weekend"},
        {value: "2", label: "Monday"}, {value: "4", label: "Tuesday"},
        {value: "8", label: "Wednesday"}, {value: "16", label: "Thursday"},
        {value: "32", label: "Friday"}, {value: "64", label: "Saturday"},
        {value: "1", label: "Sunday"}
    ];
</script>
```

In an eTemplate you write only the widget - the options arrive with it:

```xml
<et2-select-dow id="recur_data" multiple="true" rows="6"
                statustext="Days of the week for a weekly repeated event"/>
```
