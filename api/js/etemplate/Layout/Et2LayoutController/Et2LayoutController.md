## Layout
Any widget that implements `Et2LayoutHost` ( {{ classesImplementing("Et2LayoutHost") | safe }} ) can use the `layout` attribute (`stack`, `2-column`, `edit`, `absolute`) to control layout behaviour.  `edit` is the same as `2-column` with a few tweaks for edit dialogs, notably the header & footer stay full-width.  `stack` is a simple stack (1-column) of widgets.

### `2-column` (wide container)

![two-column layout](/assets/images/layout-2-column.png)

### Collapsed to 1 column

![two-column collapsed layout](/assets/images/layout-1-column.png)

### Label wrapping

![Wrapped lable layout](/assets/images/layout-1-column-wrapped.png)

## CSS Variables

### `--column-min-width`

- Default: `26rem`
- Used by `2-column` and `edit` to determine minimum column width before collapsing to 1 column.
- `--column-min-width` and `--label-width` together determine
  when the label / widget break point is reached.

Example:

```css
et2-template[layout="edit"] {
  --column-min-width: 24rem;
}
```

### `--collapse-width`

- Default: `600px`
- Fallback container-query collapse point for narrower or legacy behavior.

Example:

```css
et2-template[layout="2-column"] {
  --collapse-width: 680px;
}
```

## Grow rows

For `stack`, `2-column`, and `edit`, row heights are managed by the layout to distribute extra vertical space.

- `et2-tabbox` grows automatically
- `[grow]` supports optional numeric factors (`grow="2"` etc.) to distribute extra space proportionally.


## Widget Implementation


`Et2LayoutController` applies layout strategies (`stack`, `2-column`, `edit`, `absolute`) to layout hosts and keeps
grow rows sized correctly as content resizes.

Most column behavior is implemented in LESS (`kdots/css/src/layouts/*.less`), while this controller and
`Et2LayoutStrategies.ts` handle runtime behavior like grow-row sizing and strategy lifecycle.

Row sizing is recalculated via `ResizeObserver` + `requestAnimationFrame`


## Usage

Any widget that wants layout behavior should implement `Et2LayoutHost` and instantiate the controller.

```ts
import {Et2LayoutController, Et2LayoutHost} from "./Et2LayoutController";
import type {Et2LayoutName} from "./Et2LayoutStrategies";

export class MyLayoutHost extends HTMLElement implements Et2LayoutHost
{
	layout : Et2LayoutName = "2-column";
	private _layoutController = new Et2LayoutController(this);
}
```

At lifecycle updates, the controller:

1. Looks up the strategy from `layout`
2. Cleans up the previous strategy (if changed)
3. Applies the active strategy to current children

## `2-column` / `edit` responsive behavior

`2-column` and `edit` share the same grid base mixin in:

- `kdots/css/src/layouts/grid-base.less`

The base grid uses:

```css
grid-template-columns: repeat(auto-fit, minmax(var(--column-min-width, 26rem), 1fr));
```

This keeps two columns while there is enough room, then collapses to one column before columns become too narrow.

In one-column mode, label/input parts are normalized together so widgets do not wrap inconsistently per-field:

- `::part(form-control-label)` -> `width: 100%`, `flex-basis: 100%`, `margin-right: 0`
- `::part(form-control-input)` -> `flex-basis: 100%`
- `::part(form-control-help-text)` -> `left: 0`

## Widgets implementing `Et2LayoutHost`

{{ classesImplementing("Et2LayoutHost") | safe }}
