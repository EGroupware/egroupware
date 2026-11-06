## Examples

### Basic

The value is a colour string; the trigger swatch shows it and opens the picker.

```html:preview
<et2-colorpicker value="#1E90FF"></et2-colorpicker>
```

### Clearing the colour

"No colour" is a legitimate answer, so the widget adds a clear button of its own next to the
trigger once there is something to clear. Clearing sets the value to an empty string and fires
`change`, the same as picking a colour does.

```html:preview
<et2-colorpicker id="colorpicker-clear" value="#2E8B57"></et2-colorpicker>
<p>Value: <span id="colorpicker-clear-output">?</span></p>
<script>
    const picker = document.getElementById("colorpicker-clear");
    const pickerOutput = document.getElementById("colorpicker-clear-output");
    const showColor = () => {pickerOutput.textContent = JSON.stringify(picker.value);};

    picker.addEventListener("change", showColor);
    customElements.whenDefined("et2-colorpicker").then(() => picker.updateComplete).then(showColor);
</script>
```

### Swatches

`swatches` is a semicolon-separated list of colours offered under the picker. Give it the palette
the entries actually use - category colours, status colours - so users pick from the set instead of
inventing a new shade of green.

```html:preview
<et2-colorpicker value="#E74C3C"
                 swatches="#E74C3C; #E67E22; #F1C40F; #2ECC71; #3498DB; #9B59B6; #34495E"></et2-colorpicker>
```

### Format

`format` decides how the value is written: `hex` (the default), `rgb`, `hsl` or `hsv`. The widget
keeps the format toggle inside the picker switched off, so the value stays in the format the
template asked for.

```html:preview
<et2-colorpicker format="hex" value="#3498DB"></et2-colorpicker>
<et2-colorpicker format="rgb" value="#3498DB"></et2-colorpicker>
<et2-colorpicker format="hsl" value="#3498DB"></et2-colorpicker>
```

### Opacity

`opacity` adds an alpha slider, and the value grows a fourth component (`#3498DB80`, or
`rgba(...)`). Only use it where whatever consumes the colour understands transparency.

```html:preview
<et2-colorpicker opacity value="#3498DB80"></et2-colorpicker>
```

### Inline

`inline` drops the dropdown and renders the picker itself, for a sidebar or a settings panel where
the colour is the point of the page rather than one field among many. There is no trigger swatch in
this mode, and so no clear button either - offer another way to say "no colour" if the field needs
one.

```html:preview
<et2-colorpicker inline value="#9B59B6"></et2-colorpicker>
```

### Size

`size` matches the colour swatch to the inputs beside it: `small`, `medium` (default) or `large`.

```html:preview
<et2-colorpicker size="small" value="#3498DB"></et2-colorpicker>
<et2-colorpicker size="medium" value="#3498DB"></et2-colorpicker>
<et2-colorpicker size="large" value="#3498DB"></et2-colorpicker>
```

### Disabled

`disabled` greys the swatch out and stops the picker from opening.

```html:preview
<et2-colorpicker value="#3498DB" disabled></et2-colorpicker>
```
