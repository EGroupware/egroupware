## Examples

`et2-date-timeonly` is [`et2-date-time`](/components/et2-date-time) with the calendar taken away:
a time of day and nothing else. Opening hours, an alarm, the hour a shift starts - anything that
repeats and so has no day attached. See
[Which date widget](/components/et2-date#which-date-widget) for the rest of the family.

### A time, with no day

The value still looks like a date, because it has to survive the same trip to the server as every
other date - but the day half is pinned to `1970-01-01` and only the `H:i` part carries meaning.
The field itself shows just the time, in the user's `timeformat` preference (24 hour on this site).

```html:preview
<et2-date-timeonly id="timeonly-basic" label="Opens at"></et2-date-timeonly>
<p>Value: <span id="timeonly-basic-out">-</span></p>
<script>
    const timeOnlyBasic = document.getElementById("timeonly-basic");
    const timeOnlyBasicOut = document.getElementById("timeonly-basic-out");
    const showTimeOnlyBasic = () => {timeOnlyBasicOut.textContent = JSON.stringify(timeOnlyBasic.value);};

    timeOnlyBasic.addEventListener("change", showTimeOnlyBasic);
    customElements.whenDefined("et2-date-timeonly")
        .then(() => timeOnlyBasic.updateComplete)
        .then(() => {
            timeOnlyBasic.value = "1970-01-01T09:15:00Z";
            showTimeOnlyBasic();
        });
</script>
```

### The day is thrown away

Handing it a real date is fine - a stored value that once had a day on it, or a `Date` built from
"now". The day is discarded and only the time is kept, so the value that comes back out is always
on 1970-01-01.

```html:preview
<et2-date-timeonly id="timeonly-dropped" label="Set from 2026-03-17 14:35"></et2-date-timeonly>
<p>Value: <span id="timeonly-dropped-out">-</span></p>
<script>
    const timeOnlyDropped = document.getElementById("timeonly-dropped");
    const timeOnlyDroppedOut = document.getElementById("timeonly-dropped-out");

    customElements.whenDefined("et2-date-timeonly")
        .then(() => timeOnlyDropped.updateComplete)
        .then(() => {
            timeOnlyDropped.value = "2026-03-17T14:35:00Z";
            timeOnlyDroppedOut.textContent = JSON.stringify(timeOnlyDropped.value);
        });
</script>
```

### Stepping through the day

The arrows that appear when you hover the field move by `minuteIncrement` - 5 minutes by default,
like `et2-date-time`. A pair of these makes a from/to that is about a time of day rather than a
date range; for actual dates use [`et2-date-range`](/components/et2-date-range).

```html:preview
<et2-date-timeonly id="timeonly-from" label="From"></et2-date-timeonly>
<et2-date-timeonly id="timeonly-to" label="To" minuteIncrement="15"></et2-date-timeonly>
<script>
    const timeOnlyFrom = document.getElementById("timeonly-from");
    const timeOnlyTo = document.getElementById("timeonly-to");

    customElements.whenDefined("et2-date-timeonly")
        .then(() => Promise.all([timeOnlyFrom.updateComplete, timeOnlyTo.updateComplete]))
        .then(() => {
            timeOnlyFrom.value = "1970-01-01T08:00:00Z";
            timeOnlyTo.value = "1970-01-01T17:00:00Z";
        });
</script>
```
