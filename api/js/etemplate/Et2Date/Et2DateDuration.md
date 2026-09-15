## Examples

`et2-date-duration` is the odd one in the date family: it holds a *length* of time, not a point in
time. "2 hours", not "2 o'clock". There is no calendar behind it - it is a number with a unit
beside it, and the value it stores is just that number. See
[Which date widget](/components/et2-date#which-date-widget) for the widgets that do hold a moment.

Two separate properties decide what it means and what it looks like:

- `dataFormat` is the unit the *stored number* is in - `d` days, `h` hours, `m` minutes (the
  default), `s` seconds. Match it to the column behind the field and then leave it alone.
- `displayFormat` is the set of units the *user* may work in, largest first, eg. `dhm` or just `h`.
  Changing it never changes the stored number.

Like the other editable date widgets, `value` is a property rather than an attribute: in a template
the value arrives from the server, and in plain HTML like the examples below it is set from script.

### Minutes in, best unit out

With the defaults - data in minutes, display in days/hours/minutes - the widget picks the unit that
suits the number. 90 minutes is shown as 1.5 hours; the value stays 90.

```html:preview
<et2-date-duration id="duration-basic" label="Time spent"></et2-date-duration>
<p>Value: <span id="duration-basic-out">-</span></p>
<script>
    const durationBasic = document.getElementById("duration-basic");
    const durationBasicOut = document.getElementById("duration-basic-out");
    const showDurationBasic = () => {durationBasicOut.textContent = JSON.stringify(durationBasic.value);};

    durationBasic.addEventListener("change", showDurationBasic);
    customElements.whenDefined("et2-date-duration")
        .then(() => durationBasic.updateComplete)
        .then(() => {durationBasic.value = 90;})
        // the unit selector has to catch up before value can be read back
        .then(() => durationBasic.updateComplete)
        .then(showDurationBasic);
</script>
```

### One unit only

A single letter in `displayFormat` drops the unit selector - there is nothing to choose - and the
number is always in that unit. Handy when the field has a fixed meaning, like an estimate in hours.

```html:preview
<et2-date-duration id="duration-hours" label="Estimate" displayFormat="h"></et2-date-duration>
<p>Value: <span id="duration-hours-out">-</span></p>
<script>
    const durationHours = document.getElementById("duration-hours");
    const durationHoursOut = document.getElementById("duration-hours-out");
    const showDurationHours = () => {durationHoursOut.textContent = JSON.stringify(durationHours.value);};

    durationHours.addEventListener("change", showDurationHours);
    customElements.whenDefined("et2-date-duration")
        .then(() => durationHours.updateComplete)
        .then(() => {durationHours.value = 150;})
        .then(() => durationHours.updateComplete)
        .then(showDurationHours);
</script>
```

### An input per unit

Turning `selectUnit` off replaces the one number plus a selector with one number per unit in
`displayFormat`, read like a clock. Overfilling a field rolls over into the one to its left: put 90
in the minutes box and tab away.

In a template you write `selectUnit="false"` and the template reader turns the text into a boolean.
Plain HTML has no such reader - an attribute that is *present* means true for a boolean - so the
example sets the property from script instead.

```html:preview
<et2-date-duration id="duration-split" label="Duration" displayFormat="hm"></et2-date-duration>
<p>Value: <span id="duration-split-out">-</span></p>
<script>
    const durationSplit = document.getElementById("duration-split");
    const durationSplitOut = document.getElementById("duration-split-out");
    const showDurationSplit = () => {durationSplitOut.textContent = JSON.stringify(durationSplit.value);};

    durationSplit.selectUnit = false;
    durationSplit.addEventListener("change", showDurationSplit);
    customElements.whenDefined("et2-date-duration")
        .then(() => durationSplit.updateComplete)
        .then(() => {durationSplit.value = 135;})
        .then(() => durationSplit.updateComplete)
        .then(showDurationSplit);
</script>
```

### Days are working days

A day is `hoursPerDay` hours, 8 by default, not 24 - this is timesheet arithmetic, not calendar
arithmetic. Both fields below were set to the same 960 minutes and disagree about how many days
that is.

```html:preview
<et2-date-duration id="duration-8h" label="8 hour day" displayFormat="dh"></et2-date-duration>
<et2-date-duration id="duration-6h" label="6 hour day" displayFormat="dh" hoursPerDay="6"></et2-date-duration>
<script>
    const duration8h = document.getElementById("duration-8h");
    const duration6h = document.getElementById("duration-6h");

    customElements.whenDefined("et2-date-duration")
        .then(() => Promise.all([duration8h.updateComplete, duration6h.updateComplete]))
        .then(() => {
            duration8h.value = 960;
            duration6h.value = 960;
        });
</script>
```

### Empty is not zero

By default an empty field submits `"0"`: no duration and a duration of nothing are the same answer.
`emptyNot0` keeps them apart, so an untouched field submits an empty value and only a typed 0
submits zero. Use it where the difference is real - "not estimated yet" against "estimated at
nothing". Clear both fields below and compare.

```html:preview
<et2-date-duration id="duration-zero" label="Empty is 0" displayFormat="h"></et2-date-duration>
<et2-date-duration id="duration-empty" label="Empty is empty" displayFormat="h" emptyNot0></et2-date-duration>
<p>Values: <span id="duration-empty-out">-</span></p>
<script>
    const durationZero = document.getElementById("duration-zero");
    const durationEmpty = document.getElementById("duration-empty");
    const durationEmptyOut = document.getElementById("duration-empty-out");
    const showDurationEmpty = () =>
    {
        durationEmptyOut.textContent = JSON.stringify(durationZero.value) + " / " + JSON.stringify(durationEmpty.value);
    };

    durationZero.addEventListener("change", showDurationEmpty);
    durationEmpty.addEventListener("change", showDurationEmpty);
    customElements.whenDefined("et2-date-duration")
        .then(() => Promise.all([durationZero.updateComplete, durationEmpty.updateComplete]))
        .then(showDurationEmpty);
</script>
```

### Short unit labels

`shortLabels` swaps "Hours" for "h" in the selector, for the narrow column of a list.

```html:preview
<et2-date-duration id="duration-short" label="Spent" shortLabels></et2-date-duration>
<script>
    const durationShort = document.getElementById("duration-short");

    customElements.whenDefined("et2-date-duration")
        .then(() => durationShort.updateComplete)
        .then(() => {durationShort.value = 45;});
</script>
```
