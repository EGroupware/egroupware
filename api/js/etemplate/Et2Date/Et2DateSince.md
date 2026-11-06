## Examples

`et2-date-since` shows how long ago something happened - "5 days", "3 hours" - rather than when it
happened. It is read-only: there is nothing to pick, and no value is submitted. Its natural home is
a "Last modified" column, where the age of a row is the interesting part and the exact timestamp is
not.

For the moment itself rather than its age, see
[`et2-date-time-today`](/components/et2-date-time-today), or
[Which date widget](/components/et2-date#which-date-widget) for the whole family.

### Elapsed time

`value` here *is* an attribute, because the widget only ever reads it. Give it anything the other
date widgets accept - the server's `Y-m-dTH:i:00Z` is the usual one - and it counts forward to now.

```html:preview
<et2-date-since id="since-fixed" value="2020-01-01T09:00:00Z"></et2-date-since>
```

Note what that `Z` does and does not mean. Everywhere in the date family the string is the time on
the *user's own clock*, not real UTC - set `14:35` on an [`et2-date-time`](/components/et2-date-time)
and `14:35` is what comes back out, wherever the user is. `et2-date-since` is the one that has to
turn that back into a real instant to subtract it from now, which it does with the user's timezone
offset. A value built from `new Date().toJSON()`, which really is UTC, is therefore an offset out -
hence the shift in the examples below.

### It picks its own unit

One number and one unit, chosen to suit the gap: seconds become minutes become hours become days,
months, years. Nothing is ever shown as "2 days, 4 hours" - the largest unit that fits wins, and the
rest is rounded away.

```html:preview
<p><et2-date-since id="since-scale-1"></et2-date-since> ago</p>
<p><et2-date-since id="since-scale-2"></et2-date-since> ago</p>
<p><et2-date-since id="since-scale-3"></et2-date-since> ago</p>
<p><et2-date-since id="since-scale-4"></et2-date-since> ago</p>
<script>
    // The user's own clock, with a Z on the end - what the server would have sent
    const sinceAgo = (ms) => new Date(Date.now() - ms - new Date().getTimezoneOffset() * 60 * 1000).toJSON();

    document.getElementById("since-scale-1").value = sinceAgo(45 * 1000);
    document.getElementById("since-scale-2").value = sinceAgo(90 * 60 * 1000);
    document.getElementById("since-scale-3").value = sinceAgo(5 * 24 * 60 * 60 * 1000);
    document.getElementById("since-scale-4").value = sinceAgo(400 * 24 * 60 * 60 * 1000);
</script>
```

### Forcing a unit

`units` limits which units may be used, as a string of `YmdHis` letters. `units="d"` keeps a whole
column in days no matter how old the rows are, so the numbers can be compared down the column
instead of each one carrying its own scale.

```html:preview
<p>Automatic: <et2-date-since id="since-auto"></et2-date-since></p>
<p>units="d": <et2-date-since id="since-days" units="d"></et2-date-since></p>
<p>units="H": <et2-date-since id="since-hours" units="H"></et2-date-since></p>
<script>
    const sinceUnitsValue = new Date(Date.now() - 400 * 24 * 60 * 60 * 1000 - new Date().getTimezoneOffset() * 60 * 1000).toJSON();

    document.getElementById("since-auto").value = sinceUnitsValue;
    document.getElementById("since-days").value = sinceUnitsValue;
    document.getElementById("since-hours").value = sinceUnitsValue;
</script>
```

### Nothing to show

An empty value renders nothing at all rather than "0 seconds" or a placeholder date, which is what
you want for a row that has never been touched.

```html:preview
<p>[<et2-date-since></et2-date-since>]</p>
```
