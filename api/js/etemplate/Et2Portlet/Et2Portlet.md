## When to use it

`<et2-portlet>` is one card on the Home page. It is the base class every Home widget extends rather
than something you drop into an ordinary template: it gives you the card frame, the title bar, the
context menu with *Configure* and *Remove*, drag-to-move and drag-to-resize on the Home grid, and
the plumbing that saves the resulting size and position back to the user's preferences.

You use it by subclassing it. A portlet of your own supplies its content and, if it has anything to
configure, the list of settings the *Configure* dialog should offer.

:::warning
The examples on this page are deliberately not live. `<et2-portlet>` is registered by the Home
application's own bundle, not by the eTemplate one this documentation site loads, and a portlet
positions itself against the Home grid it expects as its parent - so a preview here would render
nothing at all.
:::

## Examples

### A portlet of your own

Override `bodyTemplate()` to say what the card contains. `headerTemplate()`, `footerTemplate()` and
`imageTemplate()` are there too if the default title bar is not what you want.

```js
import {Et2Portlet} from "../../api/js/etemplate/Et2Portlet/Et2Portlet";
import {html} from "lit";

export class Et2PortletExample extends Et2Portlet
{
    bodyTemplate()
    {
        return html`<p>${this.settings?.message ?? "Nothing configured yet"}</p>`;
    }
}

customElements.define("et2-portlet-example", Et2PortletExample);
```

### Letting the user configure it

`portletProperties` is the list of settings the *Configure* dialog shows - the same shape as a
preference definition, one entry per thing the user can change. The base class already contributes
`color`; add yours to what `super` returns rather than replacing it.

```js
get portletProperties()
{
    return [
        ...super.portletProperties,
        {name: "message", label: "Message", type: "et2-textbox"},
        {
            name: "size", label: "Size", type: "et2-select",
            select_options: [{value: "s", label: "Small"}, {value: "l", label: "Large"}]
        }
    ];
}
```

What the user picks lands in `settings`, which is persisted for that user and handed back to the
portlet the next time Home is opened. `editTemplate` points at the eTemplate used to render that
dialog, and defaults to Home's generic one - set it only if your portlet needs a hand-built
configuration form.

### Actions

Every portlet gets *Configure* and *Remove* for free. Anything you set on `actions` is merged on top
of those, so you can add your own entries and override the defaults, but you do not have to
re-declare them.

```js
this.set_actions({
    refresh: {caption: "Refresh", icon: "reload", group: "portlet", onExecute: () => this.load()}
});
```

### Size and position

`width` and `height` are in grid cells, not pixels, and `row` and `col` place the card on the Home
grid. They are normally not written by hand - the user drags the card, and the new values are saved
through the same `settings` mechanism as everything else.

```xml
<et2-portlet-example id="example" title="Example" row="1" col="1" width="2" height="2"/>
```

### An eTemplate inside a portlet

If a portlet's body is a whole eTemplate rather than a bit of markup, it is loaded with the
portlet's own `id`. The base class looks it up by that id when the card is resized and tells it to
lay itself out again, so anything inside follows the new size.
