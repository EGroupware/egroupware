## Examples

`et2-date-range` is a from/to pair in one widget - the "between these two dates" filter at the top
of a list. It has two quite different modes, and `relative` picks between them:

- absolute (the default): two [`et2-date`](/components/et2-date) fields, and the value is an object
  `{from, to}`.
- relative: one selector of named periods, and the value is the name - `"This week"`. The dates it
  means are worked out fresh every time, which is the point: a saved favourite holding `"This week"`
  still means this week next month.

See [Which date widget](/components/et2-date#which-date-widget) for the rest of the family.

Like the other editable date widgets, `value` is set as a property rather than an attribute: in a
template the value arrives from the server, and in plain HTML like the examples below it is set from
script once the widget is ready.

### Two dates

Both ends are ordinary date fields, so each is optional - a range with only a `from` is "everything
since". With neither end filled in the whole value is `null` rather than an object of empty strings.

```html:preview
<et2-date-range id="range-absolute" label="Between"></et2-date-range>
<p>Value: <span id="range-absolute-out">-</span></p>
<script>
    const rangeAbsolute = document.getElementById("range-absolute");
    const rangeAbsoluteOut = document.getElementById("range-absolute-out");
    const showRangeAbsolute = () => {rangeAbsoluteOut.textContent = JSON.stringify(rangeAbsolute.value);};

    rangeAbsolute.addEventListener("change", showRangeAbsolute);
    customElements.whenDefined("et2-date-range")
        .then(() => rangeAbsolute.updateComplete)
        .then(() => {rangeAbsolute.value = {from: "2026-03-01T00:00:00Z", to: "2026-03-31T00:00:00Z"};})
        // the value is read back out of the two date fields, so they have to have rendered it first
        .then(() => rangeAbsolute.fromElement.updateComplete)
        .then(showRangeAbsolute);
</script>
```

### A named period

`relative` swaps the two fields for a list of periods: today, yesterday, this and last week, this
and last month, the last three months, this and last year. The value is the name that was picked.

```html:preview
<et2-date-range id="range-relative" label="Period" relative></et2-date-range>
<p>Value: <span id="range-relative-out">-</span></p>
<script>
    const rangeRelative = document.getElementById("range-relative");
    const rangeRelativeOut = document.getElementById("range-relative-out");
    const showRangeRelative = () => {rangeRelativeOut.textContent = JSON.stringify(rangeRelative.value);};

    rangeRelative.addEventListener("change", showRangeRelative);
    customElements.whenDefined("et2-date-range")
        .then(() => rangeRelative.updateComplete)
        .then(() => {
            rangeRelative.value = "Last month";
            showRangeRelative();
        });
</script>
```

### What a named period actually covers

`absoluteValue` resolves the name against today's date and hands back the same `{from, to}` object
the absolute mode produces. That is what you query with; `value` is what you save. On an absolute
range the two are already the same thing, so this only matters in relative mode.

Every period is a whole one. "Last month" is the whole of last month, not the last thirty days, and
it ends on that month's own last day whether that is the 28th or the 31st. The one exception is
"Last 3 months", which runs to the end of the *current* month - it is three whole calendar months
counting this one, not three months ending last month.

Pick a different period to see what it resolves to.

```html:preview
<et2-date-range id="range-resolved" label="Period" relative></et2-date-range>
<p>value: <span id="range-resolved-value">-</span></p>
<p>absoluteValue: <span id="range-resolved-out">-</span></p>
<script>
    const rangeResolved = document.getElementById("range-resolved");
    const rangeResolvedOut = document.getElementById("range-resolved-out");
    const showRangeResolved = () => {
        document.getElementById("range-resolved-value").textContent = JSON.stringify(rangeResolved.value);
        rangeResolvedOut.textContent = JSON.stringify(rangeResolved.absoluteValue);
    };

    rangeResolved.addEventListener("change", showRangeResolved);
    customElements.whenDefined("et2-date-range")
        .then(() => rangeResolved.updateComplete)
        .then(() => {
            rangeResolved.value = "Last year";
            showRangeResolved();
        });
</script>
```

### Switching a saved range to fixed dates

Assigning a period name to a widget that is *not* relative resolves it once and fills the two date
fields in, which is how a saved "last month" favourite becomes dates the user can then adjust.

```html:preview
<et2-date-range id="range-converted" label="Last month, as dates"></et2-date-range>
<p>Value: <span id="range-converted-out">-</span></p>
<script>
    const rangeConverted = document.getElementById("range-converted");
    const rangeConvertedOut = document.getElementById("range-converted-out");

    customElements.whenDefined("et2-date-range")
        .then(() => rangeConverted.updateComplete)
        .then(() => {rangeConverted.value = "Last month";})
        .then(() => rangeConverted.fromElement.updateComplete)
        .then(() => {rangeConvertedOut.textContent = JSON.stringify(rangeConverted.value);});
</script>
```
