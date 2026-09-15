## Examples

`et2-date-time-today` is a read-only date+time that shows only the half you are likely to care
about: the time if it happened today, the date if it did not. It buys back a column's worth of
width in a list without hiding anything - the time of an older entry is still there as a tooltip.

For the age of a moment rather than the moment, see
[`et2-date-since`](/components/et2-date-since); for a plain read-only timestamp,
`et2-date-time_ro`. [Which date widget](/components/et2-date#which-date-widget) compares the whole
family.

### Today, and not today

Both of these hold a full date and time. The first one is today, so only its time is shown; the
second is not, so its date is - with a two-digit year, and its time moved into the tooltip. Hover
the second one.

```html:preview
<p>Today: <et2-date-time-today id="today-now"></et2-date-time-today></p>
<p>Older: <et2-date-time-today id="today-old"></et2-date-time-today></p>
<script>
    // The user's own clock, with a Z on the end - what the server would have sent
    const todayAgo = (ms) => new Date(Date.now() - ms - new Date().getTimezoneOffset() * 60 * 1000).toJSON();

    document.getElementById("today-now").value = todayAgo(0);
    document.getElementById("today-old").value = todayAgo(45 * 24 * 60 * 60 * 1000);
</script>
```

### It is a preference, not a rule

Users who would rather always see the full thing set the `date_time_today` preference to "full",
and every one of these widgets then formats exactly like the `et2-date-time_ro` beside it below.
Nothing in the template changes, so do not work around the shortening by choosing a different
widget - that only takes the choice away from the users who want it.

```html:preview
<p>et2-date-time-today: <et2-date-time-today id="today-compare"></et2-date-time-today></p>
<p>et2-date-time_ro, which is what date_time_today="full" gives:
    <et2-date-time_ro id="today-compare-ro"></et2-date-time_ro></p>
<script>
    const todayCompareValue = new Date(Date.now() - 45 * 24 * 60 * 60 * 1000 - new Date().getTimezoneOffset() * 60 * 1000).toJSON();

    document.getElementById("today-compare").value = todayCompareValue;
    document.getElementById("today-compare-ro").value = todayCompareValue;
</script>
```

### Nothing to show

An empty value renders nothing, rather than today's date or a placeholder - an entry that has never
been modified should leave the column blank.

```html:preview
<p>[<et2-date-time-today></et2-date-time-today>]</p>
```
