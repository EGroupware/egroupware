## Examples

Timezones, grouped by continent. The list comes from the server and is cached for the page.

Timezone names are long, so options here wrap onto a second line instead of being cut off.

### Basic

Normally the widget is written with no options and the server fills the list in. The list is hundreds
of entries long, so `search` is worth turning on wherever it is used. The options below are written
out by hand because this page has no server to ask.

```html:preview
<et2-select-timezone id="tz-basic" label="Timezone" value="Europe/Berlin"></et2-select-timezone>
<p>Value: <span id="tz-basic-output">?</span></p>
<script>
    const tz = document.getElementById("tz-basic");
    const tzOutput = document.getElementById("tz-basic-output");
    tz.select_options = [
        {value: "Europe/Berlin", label: "Europe/Berlin"},
        {value: "Europe/London", label: "Europe/London"},
        {value: "America/New_York", label: "America/New_York"},
        {value: "America/Vancouver", label: "America/Vancouver"},
        {value: "Pacific/Auckland", label: "Pacific/Auckland"}
    ];
    const showTz = () => {tzOutput.textContent = JSON.stringify(tz.value);};

    tz.addEventListener("change", showTz);
    customElements.whenDefined("et2-select-timezone").then(() => tz.updateComplete).then(showTz);
</script>
```

In an eTemplate that is one line, and the list arrives with the template:

```xml
<et2-select-timezone id="tz" label="Timezone" search="true"/>
```

By default the server sends only the timezones that are in common use. The full IANA list, including
the historic and deprecated names, is behind the widget's legacy type option and is worth asking for
only where an existing value might be one of them.
