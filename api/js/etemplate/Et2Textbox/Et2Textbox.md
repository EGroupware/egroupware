## Examples

### Label ###

Use the `label` attribute to give the input an accessible label.

Add the `et2-label-fixed` class to force the label to have a fixed width. This helps line up labels and widgets into
columns without having to use a grid. See [/getting-started/styling/#fixed-width-labels](styling)

```html:preview
<et2-textbox label="Name"></et2-textbox>
```

### Prefix & Suffix ###

Use `prefix` and `suffix` slots to add content before or after the text

```html:preview
<et2-textbox>
    <sl-icon name="youtube" slot="prefix"></sl-icon>
    <sl-icon name="upload"></sl-icon>
</et2-textbox>
```

### Mask ###

Setting a mask limits what the user can enter into the field.

```html:preview
<et2-textbox label="Part Number" helpText="P[aa]-0000" mask="{P}[aa]-0000"></et2-textbox>
```