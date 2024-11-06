```html:preview
<et2-button-toggle></et2-button-toggle>
```

:::tip

There are multiple components for dealing with boolean yes / no.

* [ButtonToggle](../et2-button-toggle): This one
* [Checkbox](../et2-checkbox): Classic checkbox
* [Switch](../et2-switch): Switch to turn something on or off
* [SwitchIcon](../et2-switch-icon): Switch between two icons

:::

## Examples

### Variants

Use the variant attribute to set the button’s variant, same as regular buttons

```html:preview
<et2-button-toggle variant="default" label="Default"></et2-button-toggle>
<et2-button-toggle variant="primary" label="Primary"></et2-button-toggle>
<et2-button-toggle variant="success" label="Success"></et2-button-toggle>
<et2-button-toggle variant="neutral" label="Neutral"></et2-button-toggle>
<et2-button-toggle variant="warning" label="Warning"></et2-button-toggle>
<et2-button-toggle variant="danger" label="Danger"></et2-button-toggle>
```

### Color

Buttons are designed to have a uniform appearance, so their color is not inherited. However, you can still customize
them by setting `--indicator-color`.

```html:preview
<style>et2-button-toggle { --indicator-color: purple;}</style>
<et2-button-toggle></et2-button-toggle>
```

### Custom icon

Use the `icon` property to set the icon used

```html:preview
<et2-button-toggle icon="bell" ></et2-button-toggle>
```

### Custom icons

Use the `onIcon` and `offIcon` properties to customise what is displayed

```html:preview
<et2-button-toggle onIcon="bell" offIcon="bell-slash"></et2-button-toggle>
```