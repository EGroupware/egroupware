## Examples

A yes/no list. The two options are built in the browser, so nothing is needed from the server.

Use it where a field really has two named states and the user should see both spelled out. For a
plain on/off flag, [et2-checkbox](/components/et2-checkbox) or
[et2-switch](/components/et2-switch) take less room.

### Basic

The value is the string `"1"` or `"0"`, not a boolean.

```html:preview
<et2-select-bool id="bool-basic" label="Confirmed" value="1"></et2-select-bool>
<p>Value: <span id="bool-basic-output">?</span></p>
<script>
    const bool = document.getElementById("bool-basic");
    const boolOutput = document.getElementById("bool-basic-output");
    const showBool = () => {boolOutput.textContent = JSON.stringify(bool.value);};

    bool.addEventListener("change", showBool);
    customElements.whenDefined("et2-select-bool").then(() => bool.updateComplete).then(showBool);
</script>
```

### Loose values

Anything you set is coerced to one of those two, so content that stores `true`, `"yes"` or `2` still
arrives on the right option instead of falling off the list. Only `"0"`, `"false"` and the empty
values count as no.

```html:preview
<et2-select-bool id="bool-loose" label="Set to true"></et2-select-bool>
<script>
    document.getElementById("bool-loose").value = true;
</script>
```
