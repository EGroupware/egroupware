`et2-hbox` is [`et2-box`](/components/et2-box) with the direction pinned to a row and a gap between
the children. Everything on the Et2Box page - `align` on the box, `align` on a child, `id` opening a
namespace - applies here unchanged.

Use it wherever a row is what you mean. A plain `et2-box` happens to lay out in a row too, but only
because that is the CSS default; `et2-hbox` says so, survives a future change to the shared box
styling, and spaces its children.

## Examples

### The difference from et2-box

The only visible difference is the gap.

```html:preview
<et2-description value="et2-box"></et2-description>
<et2-box style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-button label="One"></et2-button>
    <et2-button label="Two"></et2-button>
    <et2-button label="Three"></et2-button>
</et2-box>

<et2-description value="et2-hbox"></et2-description>
<et2-hbox style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-button label="One"></et2-button>
    <et2-button label="Two"></et2-button>
    <et2-button label="Three"></et2-button>
</et2-hbox>
```

### Changing the gap

The gap is `var(--gap, var(--sl-spacing-2x-small))`, so setting the `--gap` custom property on the
hbox changes it.

```html:preview
<et2-hbox style="--gap: var(--sl-spacing-2x-large); border: 1px solid var(--sl-color-neutral-300)">
    <et2-button label="One"></et2-button>
    <et2-button label="Two"></et2-button>
</et2-hbox>
```

### Setting gap or justify-content directly does nothing

The flex row is a `<div>` inside the hbox's shadow DOM. `display: flex` never applies to the host
element, so `gap`, `justify-content` and `align-items` written on the hbox from a template or an
app stylesheet are silently ignored - no error, no effect. The box below has both set and looks
exactly like a default one.

```html:preview
<et2-hbox style="justify-content: space-between; gap: 5em; border: 1px solid var(--sl-color-neutral-300)">
    <et2-button label="One"></et2-button>
    <et2-button label="Two"></et2-button>
</et2-hbox>
```

Use `--gap` for the spacing, `align` on the box or on a child for the placement, and a margin on the
child for anything else.

```html:preview
<et2-hbox style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-button label="One"></et2-button>
    <et2-button align="right" label="Two"></et2-button>
</et2-hbox>
```
