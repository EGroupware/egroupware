`et2-vbox` is [`et2-box`](/components/et2-box) stacking its children vertically instead of in a row.
Everything on the Et2Box page - `align` on the box, `align` on a child, `id` opening a namespace -
applies here unchanged.

The one behaviour that is specific to the vbox: children are set to `flex-grow: 0`, so they keep
their natural height and the leftover space stays at the bottom rather than being shared out. That is
almost always what you want for a stack of labelled fields, and it is the thing to override when it
is not.

## Examples

### Stacking widgets

```html:preview
<et2-vbox style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-textbox label="Street"></et2-textbox>
    <et2-textbox label="City"></et2-textbox>
    <et2-textbox label="Country"></et2-textbox>
</et2-vbox>
```

### Letting one child fill the rest

Because children do not grow, a vbox with a fixed height leaves a gap at the bottom. Put
`flex-grow: 1` on the child that should absorb it.

```html:preview
<et2-vbox style="height: 12em; border: 1px solid var(--sl-color-neutral-300)">
    <et2-description value="Notes"></et2-description>
    <et2-textarea style="flex-grow: 1" placeholder="grows to fill"></et2-textarea>
    <et2-button label="Save"></et2-button>
</et2-vbox>
```

### Centering

In a vbox `align="center"` centers the children horizontally - it is the cross axis here, not the
main one, so unlike in a row it also stops them from being stretched to full width.

```html:preview
<et2-vbox align="center" style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-description value="Nothing selected"></et2-description>
    <et2-button label="Add an entry"></et2-button>
</et2-vbox>
```

### Rows inside a column

Nest an [`et2-hbox`](/components/et2-hbox) to get a row inside the stack. This is the usual shape of
a small form: a column of rows.

```html:preview
<et2-vbox style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-textbox label="Subject"></et2-textbox>
    <et2-hbox>
        <et2-date label="Start"></et2-date>
        <et2-date label="End"></et2-date>
    </et2-hbox>
    <et2-hbox>
        <et2-button align="right" label="Save"></et2-button>
    </et2-hbox>
</et2-vbox>
```
