```html:preview
<et2-switch></et2-switch>
```

:::tip

There are multiple components for dealing with boolean yes / no.

* Et2Switch: This one
* [ButtonToggle](../et2-button-toggle): A button that shows an icon
* [Checkbox](../et2-checkbox): Classic checkbox
* [SwitchIcon](../et2-switch-icon): Switch between two icons

:::

## Examples

### Label

`label` names the switch and renders to its left, in the same position and with the same
`form-control-label` part as any other input widget. It is also the control's accessible name.

```html:preview
<et2-switch label="Notify me by email"></et2-switch>
```

Add `class="et2-label-fixed"` to give the label a fixed width, so a switch lines up in a column with
textboxes and selects rather than sitting at its own indent. Note `et2-switch` is `inline-block`, so
two switches in a row sit side by side - this example pairs one with a textbox to show the alignment.

```html:preview
<et2-switch class="et2-label-fixed" label="Notify me"></et2-switch>
<et2-textbox class="et2-label-fixed" label="Address"></et2-textbox>
```

### Checked

`checked` sets the initial state. The widget's value is a boolean, and `value` and `checked` are
two names for the same thing - setting either moves the switch.

```html:preview
<et2-switch label="On by default" checked></et2-switch>
```

```html:preview
<et2-switch id="switch-value-example" label="Subscribed"></et2-switch>
<p>Submits: <span id="switch-value-output"></span></p>
<script>
    const toggle = document.getElementById("switch-value-example");
    const out = document.getElementById("switch-value-output");
    const show = () => {out.textContent = JSON.stringify(toggle.value);};

    toggle.addEventListener("change", show);
    customElements.whenDefined("et2-switch").then(() => toggle.updateComplete).then(show);
</script>
```

### On and off labels

`toggleOn` and `toggleOff` write short state names onto the switch itself. They belong with
`class="et2SlideSwitch"`, which is the UI they were built for: the control widens, the sliding knob
is hidden, and the two names fill it as a two-state toggle.

```html:preview
<et2-switch class="et2SlideSwitch" toggleOn="Yes" toggleOff="No"></et2-switch>
<et2-switch class="et2SlideSwitch" toggleOn="On" toggleOff="Off" checked></et2-switch>
```

Without that class both names still render, but beside an ordinary switch rather than inside it, and
they overflow a control far narrower than the text - so use the two together or not at all.

Keep the names to a word. Even as a slide switch the control has a fixed minimum width and does not
grow to fit.

A `label` still belongs on the switch as well - "Yes"/"No" inside it does not say what is being
answered.

```html:preview
<et2-switch class="et2SlideSwitch" label="Two factor authentication" toggleOn="On" toggleOff="Off"></et2-switch>
```

### Size

```html:preview
<et2-switch size="small" label="Small"></et2-switch>
<et2-switch size="medium" label="Medium"></et2-switch>
<et2-switch size="large" label="Large"></et2-switch>
```

### Help text

`helpText` puts a line of explanation under the switch, for the times the name beside it does not
say what turning it on will actually do.

```html:preview
<et2-switch label="Public calendar" helpText="Anyone with the link can see your free/busy times"></et2-switch>
```

### Disabled and readonly

`disabled` means "not right now" and can be turned off again from javascript. `readonly` shows the
state without allowing any change and submits nothing. See
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden).

```html:preview
<et2-switch label="Disabled" checked disabled></et2-switch>
<et2-switch label="Readonly" checked readonly></et2-switch>
```

