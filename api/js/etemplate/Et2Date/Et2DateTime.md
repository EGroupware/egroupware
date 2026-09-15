## Examples

`et2-date-time` is [`et2-date`](/components/et2-date) with the clock turned on: one calendar popup
that picks a day *and* a time of day. Use it when both halves matter - an appointment, a deadline
with an hour on it. If only the day matters use `et2-date`; if only the time of day matters use
[`et2-date-timeonly`](/components/et2-date-timeonly). See
[Which date widget](/components/et2-date#which-date-widget) for the whole family.

Everything on the [`et2-date`](/components/et2-date) page - `value` being a property rather than an
attribute, clearing, `minDate`/`maxDate` - works here the same way.

### Setting a value

The stored value is `Y-m-dTH:i:00Z`; seconds are always zeroed. The field shows it using both the
`dateformat` and the `timeformat` preferences (`Y-m-d` and 24 hour on this site), so the same value
reads as `2026-03-17 14:35` here and `03/17/2026 2:35 pm` for a user who prefers that. Pick a
different moment from the calendar below and watch the two diverge.

```html:preview
<et2-date-time id="datetime-basic" label="Starts"></et2-date-time>
<p>Value: <span id="datetime-basic-out">-</span></p>
<script>
    const dateTimeBasic = document.getElementById("datetime-basic");
    const dateTimeBasicOut = document.getElementById("datetime-basic-out");
    const showDateTimeBasic = () => {dateTimeBasicOut.textContent = JSON.stringify(dateTimeBasic.value);};

    dateTimeBasic.addEventListener("change", showDateTimeBasic);
    customElements.whenDefined("et2-date-time")
        .then(() => dateTimeBasic.updateComplete)
        .then(() => {
            dateTimeBasic.value = "2026-03-17T14:35:00Z";
            showDateTimeBasic();
        });
</script>
```

### Minute resolution

Times snap to a multiple of `minuteIncrement`, which is 5 by default: the arrows that appear when
you hover the field step by it, and a time typed in between is rounded to it. Type `10:07` into the
first field and tab away - it becomes `10:05`. `freeMinuteEntry` turns that rounding off for the
typed value while the arrows keep stepping.

```html:preview
<et2-date-time id="datetime-rounded" label="Rounded to 5"></et2-date-time>
<et2-date-time id="datetime-free" label="Any minute" freeMinuteEntry></et2-date-time>
<script>
    const dateTimeRounded = document.getElementById("datetime-rounded");
    const dateTimeFree = document.getElementById("datetime-free");

    customElements.whenDefined("et2-date-time")
        .then(() => Promise.all([dateTimeRounded.updateComplete, dateTimeFree.updateComplete]))
        .then(() => {
            dateTimeRounded.value = "2026-03-17T10:00:00Z";
            dateTimeFree.value = "2026-03-17T10:00:00Z";
        });
</script>
```

### A coarser step

Set `minuteIncrement` yourself when a finer choice is meaningless - a room booking on the
half hour, say. The arrows then move 30 minutes at a time.

```html:preview
<et2-date-time id="datetime-half-hour" label="On the half hour" minuteIncrement="30"></et2-date-time>
<script>
    const dateTimeHalfHour = document.getElementById("datetime-half-hour");

    customElements.whenDefined("et2-date-time")
        .then(() => dateTimeHalfHour.updateComplete)
        .then(() => {dateTimeHalfHour.value = "2026-03-17T09:00:00Z";});
</script>
```

### Now, rather than today

The button under the calendar is labelled "Now" instead of "Today", and sets the current time along
with the current day. Open the picker to see it.

```html:preview
<et2-date-time label="When"></et2-date-time>
```
