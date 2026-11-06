## Examples

Copies a piece of text to the clipboard. Unlike the rest of the buttons on this page it is not an
eTemplate input widget - it never submits, has no `id`-based behaviour, and returns no value. It
is a convenience next to something the user would otherwise have to select and copy by hand: a
share link, an ID, a generated password.

### Copying a fixed value

`value` is the text that ends up on the clipboard. Click the button and paste somewhere to check.

```html:preview
<et2-button-copy value="https://example.org/share/abc123"></et2-button-copy>
```

### Copying from another element

`from` takes the `id` of an element to read instead, which keeps the two from drifting apart.
It copies that element's text content; append `[attribute]` or `.property` to copy one of those
instead - `from="my-field.value"` is how you copy what a user typed.

```html:preview
<span id="copy-source-example">Copied from the text beside the button</span>
<et2-button-copy from="copy-source-example"></et2-button-copy>

<et2-textbox id="copy-input-example" value="...or from an input"></et2-textbox>
<et2-button-copy from="copy-input-example.value"></et2-button-copy>
```

### Labels

The tooltip is the only text the button has. `copyLabel`, `successLabel` and `errorLabel` set the
three states, and `feedbackDuration` (milliseconds) is how long the success or error icon stays up
before it turns back into the copy icon.

```html:preview
<et2-button-copy
        value="2026-000123"
        copyLabel="Copy ticket number"
        successLabel="Ticket number copied"
        feedbackDuration="3000"
></et2-button-copy>
```

### Custom icons

The three states are slots, so each can be replaced.

```html:preview
<et2-button-copy value="Copied!">
    <sl-icon slot="copy-icon" name="clipboard-heart"></sl-icon>
    <sl-icon slot="success-icon" name="clipboard-heart-fill"></sl-icon>
    <sl-icon slot="error-icon" name="emoji-frown"></sl-icon>
</et2-button-copy>
```

### Disabled

:::warning
`disabled` on a copy button does not grey it out - it takes it off the page, because a copy button
is not an input widget and so keeps `Et2Widget`'s `:host([disabled]) {display: none}`. The two
buttons below are a normal one and a disabled one; you will only see one of them. If you want a
visibly-unavailable button, use [et2-button](/components/et2-button) with `noSubmit`, or hide this
one deliberately with `hidden` so the template says what it means. See
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden).
:::

```html:preview
<et2-button-copy value="Enabled"></et2-button-copy>
<et2-button-copy value="Disabled" disabled></et2-button-copy>
```
