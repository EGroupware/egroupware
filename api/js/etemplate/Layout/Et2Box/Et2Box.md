`et2-box` is the plain wrapper. It has no visual appearance of its own - no border, no label, no
padding - it just puts its children in a flex row and gets out of the way. Use it to keep a few
widgets together so they can be shown, hidden, aligned or styled as one unit.

If you want a guaranteed direction and a gap between the children, use
[`et2-hbox`](/components/et2-hbox) or [`et2-vbox`](/components/et2-vbox) instead. They are this same
widget with the layout fixed.

## Examples

### Grouping widgets

Children are laid out in a row, in source order, and stretched to the same height.

```html:preview
<et2-box>
    <et2-button label="Save"></et2-button>
    <et2-button label="Apply"></et2-button>
    <et2-button label="Cancel"></et2-button>
</et2-box>
```

### Hiding a group

`hidden` on the box takes the whole group out of the layout, which is the usual reason to wrap
widgets in a box in the first place - one attribute instead of one per widget.

`disabled` does exactly the same thing here. A box has nothing to grey out, so it falls back to the
base widget rule of `display: none`, unlike an input, where the two mean different things - see
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden). Use
`hidden` and say what you mean.

```html:preview
<et2-box id="box-hide-example">
    <et2-description value="Shipping address"></et2-description>
    <et2-textbox placeholder="Street"></et2-textbox>
</et2-box>
<et2-button id="box-hide-toggle" label="Toggle the box"></et2-button>
<script>
    const box = document.getElementById("box-hide-example");
    document.getElementById("box-hide-toggle").addEventListener("click", () =>
    {
        box.hidden = !box.hidden;
    });
</script>
```

### Aligning the contents

`align` on the box moves all of its children to that end of the available width. Without it they
start at the left.

```html:preview
<et2-box align="left" style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-description value="left"></et2-description>
</et2-box>
<et2-box align="center" style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-description value="center"></et2-description>
</et2-box>
<et2-box align="right" style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-description value="right"></et2-description>
</et2-box>
```

### Aligning one child

`align` on a *child* moves just that child, by giving it an automatic margin on the other side. This
is how you get "everything left, one thing far right" without a spacer widget.

Note that it also reorders: a child with `align="right"` is moved to the end of the row and one with
`align="left"` to the front, whatever their position in the source.

```html:preview
<et2-box style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-description value="Order #4711"></et2-description>
    <et2-button align="right" label="Delete"></et2-button>
</et2-box>
```

### Images do not stretch

Children get `flex: 1 1 auto` so they share the width, but `img` and `et2-image` are held at their
natural size. Otherwise an icon next to a text field would end up half the row wide.

```html:preview
<et2-box style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-image src="image"></et2-image>
    <et2-textbox placeholder="Subject"></et2-textbox>
</et2-box>
```

### An id opens a namespace

A box with an `id` is a namespace: the ids of the widgets inside it are looked up *below* that key in
the content array, not at the top level. This is useful when a sub-template or a repeating structure
has its own slice of content.

It is also the most common way to accidentally blank out a whole section of a template. If
`$content['address']` does not exist, every widget in the box below reads from an empty array - `@`
references go falsy, `autorepeat` grids stop repeating - and nothing is logged.

```xml
<!-- street reads $content['address']['street'], not $content['street'] -->
<et2-box id="address">
    <et2-textbox id="street"></et2-textbox>
    <et2-textbox id="city"></et2-textbox>
</et2-box>
```

If you only need the box for layout or to show and hide a group, leave the `id` off and use `class`
or a parent widget to address it.
