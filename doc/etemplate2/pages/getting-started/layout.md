---
meta:
  title: 'Layout'
  description: The ways to arrange widgets in a template - the layout attribute, boxes and the other layout widgets, your own CSS, and grid - and how to pick one.
---

# Layout

A template is a list of widgets.  Layout is how you say where they go.

## With no layout at all

A template that says nothing puts its widgets in the page one after another and lets each one take
whatever space its own CSS asks for:

```html:preview
<et2-textbox label="Name"></et2-textbox>
<et2-date label="Preferred day"></et2-date>
<et2-button label="Save"></et2-button>
<et2-button label="Cancel"></et2-button>
```

Every eTemplate widget is a block, so they come out one per line, in source order: the two inputs
stretch across the whole width, the buttons take only what they need but still get a line each.
Nothing sits beside anything else, and nothing lines up - each input starts wherever its own label
happens to end.

They do reflow, but each one on its own.  A widget drops its label above its input once the two
stop fitting side by side, and where that happens depends on how long that widget's label is:
narrow the example above and "Preferred day" gives up well before "Name" does, so there is a whole
range of widths at which the form is half wrapped and half not.  Nothing reflows *together*, and no
widget can be told to take the space left at the bottom.

That is workable for two or three widgets and for nothing else.  Everything below is a way of
saying more than that, in the order you should reach for them.  They also combine: an `et2-hbox`
holding a field and its unit is one child of a `layout`, and a box you have styled yourself can sit
inside either.

| Approach                                                    | Reach for it when                                                                                                          |
|--------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| [The `layout` attribute](#the-layout-attribute)             | A form or a dialog - labelled fields that should reflow as the popup or panel changes size.  The default answer.            |
| [Boxes](#boxes-et2-hbox-and-et2-vbox)                       | A few widgets that belong together as one unit: a field and its unit, a row of buttons, a group you show and hide together. |
| [Other layout widgets](#other-layout-widgets)               | Tabs, a folding section, a titled group, a split pane - an arrangement a widget already provides.                           |
| [Your own CSS](#your-own-css)                               | The arrangement is specific to this one screen and none of the above says it.                                               |
| [`grid`](#grid-and-when-you-still-need-it)                  | Rows repeated from content.  Otherwise, a last resort.                                                                      |

## The `layout` attribute

Put `layout` on the container and its children arrange themselves - and re-arrange themselves when
the window, the popup or the panel they are in changes size.  No wrapper, no counting columns.

```html:preview
<et2-template id="myapp.edit" layout="2-column" style="max-width: 60em">
    <et2-textbox id="name" label="Name"></et2-textbox>
    <et2-select id="status" label="Status"></et2-select>
    <et2-date id="due" label="Due"></et2-date>
    <et2-number id="amount" label="Amount"></et2-number>
</et2-template>
```

Drag the handle on the right edge of the box, and it goes
through three states:

1. **Two columns**, while there is room for two.
2. **One column, labels still beside their inputs** - below about 850px, where a second column
   would go under `--column-min-width`.
3. **One column, every label above its input** - below 600px.  All four move at the same moment,
   not one field at a time as they did with no layout at all.
   That is the part a form cannot do for itself, and it comes with the layout: every field gets
   the same label width, until there is no room for a label beside an input at all.

The example is capped at `60em` so that it stops at two columns
instead of growing a third on a wide screen.   (see
[Layout Controller](/mixins/et2layoutcontroller#collapse-width))

The layouts are `stack` (one per line), `2-column`, and `edit` (`2-column` with the conventions an
edit dialog wants), and `<et2-template>` and `<et2-customfields>` are the widgets that accept them.

:::warning
The layout arranges the container's **direct children**.  Do not wrap them in a `<grid>` or one big
box - the layout would have exactly one child to arrange, and nothing would happen.
:::

See [Layout Controller](/mixins/et2layoutcontroller) for how the columns collapse, the `span` and `grow`
attributes, lining up labels, and the CSS variables that control when it all happens.

## Boxes: `et2-hbox` and `et2-vbox`

A box groups widgets so they can be arranged, shown, hidden or styled as one thing.
[`et2-hbox`](/components/et2-hbox) puts its children in a row,
[`et2-vbox`](/components/et2-vbox) in a column, and both leave a gap between them.
[`et2-box`](/components/et2-box) is the same widget with no direction fixed and no gap.

```html:preview
<et2-hbox>
    <et2-number id="quantity" label="Quantity"></et2-number>
    <et2-textbox id="unit" label="Unit"></et2-textbox>
</et2-hbox>
<et2-vbox>
    <et2-number id="quantity-v" label="Quantity"></et2-number>
    <et2-textbox id="unit-v" label="Unit"></et2-textbox>
</et2-vbox>
```

The same two widgets, in an `et2-hbox` and then an `et2-vbox`.  Either way they are one child of
whatever holds the box, so the widget above can arrange, hide or style the pair as a unit.

Use `align` on the box to move all of its children to one end, or on a single child to push just
that one over.  A footer with `Save` on the left and `Cancel` on the right is one `align="right"`:

```html:preview
<et2-hbox>
    <et2-button label="Save"></et2-button>
    <et2-button label="Apply"></et2-button>
    <et2-button align="right" label="Cancel"></et2-button>
</et2-hbox>
```

:::warning
An `id` on a box opens a content namespace.  `<et2-box id="details">` makes every widget inside it
read from `content["details"]`, so if there is no such entry the whole group silently gets nothing -
empty values, `@`-references resolving falsy, autorepeat stopping.  Only give a box an `id` when the
content really is nested under it.
:::

## Other layout widgets

Boxes are the plain containers; several widgets exist to arrange things in a particular way, and
each has its own page in the component reference under **Layout**:

| Widget                                              | What it does                                                            |
|-----------------------------------------------------|---------------------------------------------------------------------------|
| [`et2-tabbox`](/components/et2-tabbox)              | Tabs - one panel visible at a time.  Grows to fill a layout on its own.  |
| [`et2-details`](/components/et2-details)            | A section the user can fold away.                                        |
| [`et2-groupbox`](/components/et2-groupbox)          | A titled, framed group of widgets.                                       |
| [`et2-split`](/components/et2-split)                | Two panes with a divider the user can drag.                              |
| [`et2-app-box`](/components/et2-app-box)            | The application's own frame - header, content and footer.                |
| [`et2-visually-hidden`](/components/et2-visually-hidden) | Content for screen readers only, taking no space.                   |

They are containers like the boxes above, so the same rules apply: they hold their children, and
they are themselves one child of whatever holds them.

## Your own CSS

When the arrangement is specific to one screen, give the container a class and write the CSS
yourself.

The one thing to know: a box's flex row lives in its shadow DOM, so rules aimed at the tag do
nothing - `et2-box.address { display: grid }` sets a property on an element whose children are laid
out one level further in.  Target `::part(base)` instead:

```css
et2-box.address::part(base) {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: var(--sl-spacing-small);
}
```

That CSS belongs in the app's own stylesheet (`<app>/templates/default/app.css`).  For rules that
only make sense for one template, [`<et2-styles>`](/components/et2-styles) carries them in the
template file itself.  Either way, prefer `em` and the existing CSS variables over fixed pixels -
see [Styling](/getting-started/styling).

## `grid`, and when you still need it

`<grid>` is the original way to lay out a template: you declare the columns, you declare the rows,
and what you get is what everybody gets at every window size.

It is still the only construct that **repeats rows from content**.  A grid's last row is repeated
once per entry in the content array its widgets read from - `${row}` in a child's id is what says
"whichever row this turns out to be" - and that is how nextmatch rows and every content-driven list
are built:

```xml

<grid id="events">
	<columns>
		<column/>
		<column/>
	</columns>
	<rows>
		<row class="th">
			<et2-description value="Time"/>
			<et2-description value="Title"/>
		</row>
		<row>
			<et2-date-time id="${row}[time]" readonly="true"/>
			<et2-description id="${row}[title]"/>
		</row>
	</rows>
</grid>
```

(The header row is why a content row is row 2 - a grid's rows are numbered from 1, including the
ones that hold nothing but labels.)

For anything else - and especially for a form - it is the thing the `layout` attribute replaces.  A
dialog built as one `table.et2_grid` cannot reflow, cannot collapse to one column, and cannot hand
its leftover height to the widget that should have it.
