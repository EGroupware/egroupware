## Examples

0% to 100% in steps of ten. It is an [et2-select-number](/components/et2-select-number) with `min`,
`max`, `interval` and `suffix` preset, so the list is generated in the browser and needs nothing from
the server.

### Basic

The value is the bare number as a string - the `%` is part of the label only.

```html:preview
<et2-select-percent id="percent-basic" label="Completed" value="30"></et2-select-percent>
<p>Value: <span id="percent-basic-output">?</span></p>
<script>
    const percent = document.getElementById("percent-basic");
    const percentOutput = document.getElementById("percent-basic-output");
    const showPercent = () => {percentOutput.textContent = JSON.stringify(percent.value);};

    percent.addEventListener("change", showPercent);
    customElements.whenDefined("et2-select-percent").then(() => percent.updateComplete).then(showPercent);
</script>
```

### Finer steps

The inherited `interval` still applies, so a field that wants 5% steps does not need a different
widget.

```html:preview
<et2-select-percent id="percent-fine" label="Completed" interval="5"></et2-select-percent>
```
