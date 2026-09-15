## Examples

An icon button is a [Button](/components/et2-button) with no label and no chrome - just the icon,
clickable. Everything about images, `noSubmit`, `disabled` and the id-based defaults is the same
as on a regular button, so it is only described [there](/components/et2-button).

### Icon

`image` picks the icon. Because there is no label to read, always give an icon button a `label`
too: it is not rendered, it is what a screen reader announces and what the tooltip shows.

```html:preview
<et2-button-icon image="printer" label="Print"></et2-button-icon>
<et2-button-icon image="trash" label="Delete"></et2-button-icon>
<et2-button-icon image="three-dots-vertical" label="More"></et2-button-icon>
```

### In a toolbar

Icon buttons are meant to sit together in a row, which is most of what they are used for - a
nextmatch header, a dialog toolbar, the buttons inside a tag.

```html:preview
<et2-hbox>
    <et2-button-icon image="type-bold" label="Bold"></et2-button-icon>
    <et2-button-icon image="type-italic" label="Italic"></et2-button-icon>
    <et2-button-icon image="type-underline" label="Underline"></et2-button-icon>
</et2-hbox>
```

### Colour

The button inherits its colour, so it takes on whatever it is sitting in rather than fighting it.

```html:preview
<div style="color: var(--sl-color-danger-600)">
    <et2-button-icon image="exclamation-triangle" label="Warning"></et2-button-icon>
    Something needs attention
</div>
```

### Disabled

```html:preview
<et2-button-icon image="printer" label="Print"></et2-button-icon>
<et2-button-icon image="printer" label="Print" disabled></et2-button-icon>
```
