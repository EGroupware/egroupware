## Examples

`et2-date` is one day, picked from a calendar. No time of day: whatever time you put in, midnight
comes back out.

### Which date widget

The date family is seven widgets that all look similar and mean different things. Pick by what the
field actually holds, not by what it looks like.

| Widget | Editable | Holds |
|---|---|---|
| [`et2-date`](/components/et2-date) | yes | a single day |
| [`et2-date-time`](/components/et2-date-time) | yes | a day *and* a time of day |
| [`et2-date-timeonly`](/components/et2-date-timeonly) | yes | a time of day, no day |
| [`et2-date-duration`](/components/et2-date-duration) | yes | a *length* of time (2 hours), not a point in time |
| [`et2-date-range`](/components/et2-date-range) | yes | two days, or a named period like "This week" |
| [`et2-date-since`](/components/et2-date-since) | no | how long ago a moment was ("5 days") |
| [`et2-date-time-today`](/components/et2-date-time-today) | no | a moment, shown as a time if it is today and a date if it is not |

The first three are the same widget with the calendar or the clock turned off, so everything below
applies to all three. `et2-date-duration` only shares the family name - it is a number with a unit
beside it, and has no calendar at all.

Each editable one also has a read-only twin (`et2-date_ro`, `et2-date-time_ro`,
`et2-date-timeonly_ro`, `et2-date-duration_ro`) that renders text instead of an input. You rarely
write those yourself: when a template says `readonly="true"`, eTemplate swaps in the `_ro` widget
for you. See
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden).

### Setting a value

`value` is a property, not an attribute, on all of the editable date widgets - a `value="…"` in
plain HTML is ignored. In a template that makes no difference, because the template reader assigns
the property for you; in the examples on this page it means a small script.

The setter takes anything `new Date()` understands: the server's own `Y-m-dTH:i:00Z` strings, a
`Date`, or milliseconds since the epoch.

```html:preview
<et2-date id="date-basic" label="Start date"></et2-date>
<script>
    const dateBasic = document.getElementById("date-basic");

    customElements.whenDefined("et2-date")
        .then(() => dateBasic.updateComplete)
        .then(() => {dateBasic.value = "2026-03-17T00:00:00Z";});
</script>
```

### Value in, value out

What is displayed and what is submitted are two different strings. The field shows the date the way
the user's `dateformat` preference says (`Y-m-d` on this site), while `value` stays the
machine-readable `Y-m-dT00:00:00Z` no matter which preference is set. Pick a different day from the
calendar below and watch both.

Do not read that trailing `Z` as UTC. Throughout the date family the string is the time on the
user's own clock - put `14:35` into an [`et2-date-time`](/components/et2-date-time) and `14:35` is
what comes back out of it, wherever the user happens to be. The server, which knows the user's
timezone, is what turns it into an instant.

```html:preview
<et2-date id="date-value" label="Pick a day"></et2-date>
<p>Value: <span id="date-value-out">-</span></p>
<script>
    const dateValue = document.getElementById("date-value");
    const dateValueOut = document.getElementById("date-value-out");
    const showDateValue = () => {dateValueOut.textContent = JSON.stringify(dateValue.value);};

    dateValue.addEventListener("change", showDateValue);
    customElements.whenDefined("et2-date")
        .then(() => dateValue.updateComplete)
        .then(() => {
            dateValue.value = "2026-03-17T00:00:00Z";
            showDateValue();
        });
</script>
```

### Only the day survives

A value with a time in it is accepted and then flattened to midnight, because that is all an
`et2-date` can represent. If the time matters, the field wanted
[`et2-date-time`](/components/et2-date-time).

```html:preview
<et2-date id="date-strip" label="Set to 14:35"></et2-date>
<p>Value: <span id="date-strip-out">-</span></p>
<script>
    const dateStrip = document.getElementById("date-strip");
    const dateStripOut = document.getElementById("date-strip-out");

    customElements.whenDefined("et2-date")
        .then(() => dateStrip.updateComplete)
        .then(() => {
            dateStrip.value = "2026-03-17T14:35:00Z";
            dateStripOut.textContent = JSON.stringify(dateStrip.value);
        });
</script>
```

### Clearing

Empty, `0` and `"0"` all mean "no date" - eTemplate content arrives as any of the three - and all
clear the field rather than landing on 1970. Clearing this way deliberately does *not* fire
`change`, so a filter that resets itself to empty cannot echo back into whatever reset it.

```html:preview
<et2-date id="date-clear" label="Clearable"></et2-date>
<button type="button" id="date-clear-button">Clear</button>
<script>
    const dateClear = document.getElementById("date-clear");

    document.getElementById("date-clear-button").addEventListener("click", () => {dateClear.value = 0;});
    customElements.whenDefined("et2-date")
        .then(() => dateClear.updateComplete)
        .then(() => {dateClear.value = "2026-03-17T00:00:00Z";});
</script>
```

### Limiting which days can be picked

`min` and `max` grey out everything outside the range. Unlike `value` they are ordinary attributes,
so a template sets them directly. `minDate` and `maxDate` are the same two limits under the names
the calendar uses, and either spelling works. Try to reach February below.

```html:preview
<et2-date id="date-limits" label="March 2026 only"
          min="2026-03-01T00:00:00Z" max="2026-03-31T00:00:00Z"></et2-date>
<script>
    const dateLimits = document.getElementById("date-limits");

    customElements.whenDefined("et2-date")
        .then(() => dateLimits.updateComplete)
        .then(() => {dateLimits.value = "2026-03-17T00:00:00Z";});
</script>
```

A limit can also be changed later, which is how a pair of fields keeps itself in order - moving the
start date forward pushes the end date's minimum with it.

```html:preview
<et2-date id="date-from" label="From"></et2-date>
<et2-date id="date-to" label="To"></et2-date>
<script>
    const dateFrom = document.getElementById("date-from");
    const dateTo = document.getElementById("date-to");

    dateFrom.addEventListener("change", () => {dateTo.min = dateFrom.value;});
    dateTo.addEventListener("change", () => {dateFrom.max = dateTo.value;});
</script>
```

### Typing, and the arrows

The field is a normal text input as well as a calendar trigger: type a date in the format the field
displays - `2026-12-24` here - and it is parsed and the calendar follows along. Hover the field and
two arrows appear on its right hand end; they step a day at a time, and on an empty field the first
press lands on today rather than moving anywhere.

```html:preview
<et2-date label="Type it or step it"></et2-date>
```

### Placeholder and disabled

`placeholder` is shown while the field is empty. `disabled` takes the field out of play without
changing what it holds.

```html:preview
<et2-date label="Due" placeholder="No due date"></et2-date>
<et2-date label="Locked" disabled></et2-date>
```
