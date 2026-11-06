## Examples

### Label

`label` is the text on the button.

```html:preview
<et2-button label="Save"></et2-button>
```

### Label as content

The label is really the button's default slot, so you can write it as content instead. Use this
when the text needs markup of its own - an attribute can only carry plain text.

```html:preview
<et2-button>Save <b>everything</b></et2-button>
```

Set one or the other, not both. The `label` setter writes into the first text node it finds, so a
button that has both ends up showing the attribute and keeping any elements around it, which is
rarely what was meant.

### Image

Use `image` to put an icon in front of the label. `image` and `noSubmit` below both come from
[ButtonMixin](/mixins/buttonmixin), which every button on this page shares.

```html:preview
<et2-button label="Print" image="printer"></et2-button>
<et2-button label="Approve" image="check"></et2-button>
```

A button with an image and no label is icon-only, and sized square. If that is all you want,
[Button Icon](/components/et2-button-icon) is the lighter-weight widget for it.

```html:preview
<et2-button image="printer"></et2-button>
```

:::tip
In an application, `image` takes any EGroupware image name - `save`, `addressbook/navbar`, a full
URL. This documentation site has no server to hand out the name-to-file map, so only names that
are also [Bootstrap Icons](https://icons.getbootstrap.com/) names render here. That is why every
example on this page uses one.
:::

An `id` matching one of a handful of well-known patterns gets an image (and a colour class) for
free, so the buttons at the bottom of a dialog look the same everywhere without anyone setting
`image`. `save`, `apply`, `cancel`, `delete`, `edit`, `copy`, `close` and `add` are among them; see
`default_background_images` in [ButtonMixin](/mixins/buttonmixin) for the full list. Setting
`image` yourself always wins.

### Variants

`variant` comes from Shoelace and picks the button's colour.

```html:preview
<et2-button label="Default"></et2-button>
<et2-button variant="primary" label="Primary"></et2-button>
<et2-button variant="success" label="Success"></et2-button>
<et2-button variant="neutral" label="Neutral"></et2-button>
<et2-button variant="warning" label="Warning"></et2-button>
<et2-button variant="danger" label="Danger"></et2-button>
<et2-button variant="text" label="Text"></et2-button>
```

`primary` is deliberately toned down compared to stock Shoelace, because eTemplate puts it on the
first button of every dialog and a wall of blue was too loud.

### Size

`size` is `small`, `medium` (the default) or `large`.

```html:preview
<et2-button size="small" label="Small"></et2-button>
<et2-button size="medium" label="Medium"></et2-button>
<et2-button size="large" label="Large"></et2-button>
```

### Prefix and suffix

Beyond `image`, the `prefix` and `suffix` slots take whatever you put in them.

```html:preview
<et2-button label="Download">
    <sl-icon slot="prefix" name="cloud-download"></sl-icon>
    <sl-icon slot="suffix" name="chevron-right"></sl-icon>
</et2-button>
```

### Submitting

By default clicking a button submits the whole eTemplate to the server, where the button's `id`
tells your code what was clicked. Nothing needs to be wired up for that; it is what a button is.

```html
<et2-button id="button[save]" label="Save"></et2-button>
```

```php
if (!empty($content['button']))
{
    switch(key($content['button']))
    {
        case 'save': ... 
    }
}
```

:::warning
That submit is the one thing these examples cannot show. There is no eTemplate and no server
behind this page, so `getInstanceManager()` finds nothing and the submit step is skipped -
the buttons below still fire their click handlers, they just never post anything.
:::

Set `noSubmit` for a button that should only run client-side code. Returning `false` from the
`onclick` handler stops the submit for that one click, which is how a confirmation prompt works.

```html:preview
<et2-button id="local-example" noSubmit label="Count clicks"></et2-button>
<span id="local-count">0</span>
<script>
    const button = document.getElementById("local-example");
    const count = document.getElementById("local-count");
    button.addEventListener("click", () => {count.textContent = String(1 + parseInt(count.textContent));});
</script>
```

### Skipping validation

`noValidation` submits without running client-side validation first. Use it on buttons that take
the user somewhere else - a Cancel, or a tab of a wizard - where refusing to leave because a
required field further down is still empty would be wrong.

```html
<et2-button id="button[cancel]" noValidation label="Cancel" image="cancel"></et2-button>
```

### Disabled and readonly

A button is an input widget, so `disabled` does what you expect: the button stays on the page,
greyed out, and can be enabled again from javascript. That is worth saying out loud, because on a
widget that is *not* an input - a box, a tab, and among this page's neighbours
[et2-button-copy](/components/et2-button-copy) and [et2-tag](/components/et2-tag) - `disabled`
removes the widget from the page entirely. See
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden).

`readonly` on a button means the same thing as disabled - there is no value to show - and is
implemented by setting the `disabled` attribute.

```html:preview
<et2-button label="Normal"></et2-button>
<et2-button label="Disabled" disabled></et2-button>
<et2-button label="Readonly" readonly></et2-button>
```

Add `hideOnReadonly` to get the older behaviour, where a readonly button is gone rather than
greyed. Both attributes are needed: on its own `hideOnReadonly` does nothing, since a button that
is not readonly has nothing to hide from.

```html:preview
<et2-button label="Still here" readonly></et2-button>
<et2-button label="Not here" readonly hideOnReadonly="true"></et2-button>
```

### Keyboard shortcuts

A button whose `id` matches `save` gets Ctrl+S and one matching `cancel` gets Escape, registered
globally while the button is on the page. Nothing to set up - it follows from the `id`, the same
way the default image does.

```html
<et2-button id="button[save]" label="Save"></et2-button>
```

