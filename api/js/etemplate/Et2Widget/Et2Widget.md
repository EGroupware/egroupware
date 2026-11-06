## Overview

`Et2Widget` is the mixin every eTemplate widget is built from. It is what turns a plain
[Lit](https://lit.dev) element into something an eTemplate can create, address, fill with content
and destroy - so almost everything in the Properties table below appears on every widget in this
reference, and is documented here rather than repeated on all of them.

```ts
export class Et2Example extends Et2Widget(LitElement) { … }
```

An input additionally uses [`Et2InputWidget`](/mixins/et2inputwidget), which layers value handling
and validation on top of this.

## What it provides

**Identity.** `id` is the widget's name inside its template, and is what the server sends values back
under. `dom_id` is the resulting DOM id, which is namespaced by the template so two templates on one
page cannot collide.

**Content binding.** A widget does not usually carry its own value in the template - it names one,
and the surrounding content supplies it. `getArrayMgr()` and the array managers reachable from it are
how a widget resolves `@`-references and `${row}` expressions against the content, the select
options, the modifications and the validation errors the server sent.

Beware the namespace rule: putting an `id` on a container opens a *namespace* for everything inside
it, so children resolve their references relative to that id. Giving a layout box an id it does not
need silently starves its whole subtree - references go falsy and auto-repeating grids stop
repeating, with nothing logged.

**The widget tree.** `getChildren()`, `getRoot()`, `getPath()` and `getInstanceManager()` navigate the
eTemplate structure, which is not the same as the DOM tree - a row widget in a datagrid, for
instance, has no widget-tree parent at all.

**Construction from XML.** `loadFromXML()`, `createElementFromNode()` and `parseXMLAttrs()` are how a
`.xet` template becomes live widgets. You call these only when building something that hosts its own
template; ordinary widgets get them for free.

**Presentation.** `label`, `class`, `statustext` (the tooltip), `align`, `accesskey` and `hidden`.

**Actions.** `actions` attaches the egw action system - context menus, drag and drop, keyboard
shortcuts.

## disabled is not what you expect

`Et2Widget`'s own style is:

```css
:host([disabled]) { display: none; }
```

Only [`Et2InputWidget`](/mixins/et2inputwidget) puts that back to `display: initial`. So on an input,
`disabled` greys the widget out as you would expect - but on **anything else**, a box, a groupbox,
`et2-details`, a tab, `disabled` removes the widget from the page entirely and is indistinguishable
from `hidden`.

Use `hidden` when you mean "not visible", so the template says what it means. See
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden).

## Lifecycle

`loadingFinished()` runs once the widget and its children are constructed and bound to content - it
is the point at which the widget tree is complete, and the right place for work that needs to see
siblings. `destroy()` tears the widget down and detaches it from its parent.

For the standard Lit lifecycle underneath this - `connectedCallback`, `willUpdate`, `firstUpdated`,
`updated` - see [Lit's own documentation](https://lit.dev/docs/components/lifecycle/). A practical
consequence worth knowing: a widget is *constructed* as soon as it is inserted into the document,
but its render root does not exist until the first update, so anything reaching into a widget from
outside has to wait for `updateComplete`.

## Writing one

See [Creating a widget](/tutorials/creating-a-widget) for a worked example, and
[Web Component Authoring](/tutorials/web-component-authoring) for the conventions this codebase
expects - including how to document what you write.
