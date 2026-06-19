## Creating a widget

ETemplate components are [LitElements](https://lit.dev/docs/) that are wrapped with
our [Et2Widget](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Widget/Et2Widget.ts) mixin,
which adds properties and methods to support loading from our template files and returning values to the server. They
should be (relatively) stand-alone.

Before implementing a component, review the [Web Component Authoring standards](/tutorials/web-component-authoring).

Common components are in `api/js/etemplate/`. You can add application specific components in `<appname>/js/`.

### Choose the widget type

Before creating the component, decide its role:

- Extend `Et2Widget` for display and container widgets.
- Extend `Et2InputWidget` when the widget has a value that must be loaded from and returned to an eTemplate.

Define the component's public contract at the same time: its properties, slots, events, and value behavior. The
[Web Component Authoring standards](/tutorials/web-component-authoring) cover the implementation details.

### Create the files

```
myapp/
    js/
        MyWidget/
            test/
            MyWidget.ts
            
```

You should have [automatic tests](/tutorials/automatic-testing) to verify your component and avoid regressions
in `test/`.

Create and register a custom element tag. Use `et2-*` for shared widgets, or an application prefix for
application-specific widgets:

```typescript
import {html, LitElement} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {Et2Widget} from "../../../api/js/etemplate/Et2Widget/Et2Widget";

/**
 * @summary Briefly describe the widget.
 */
@customElement("et2-my-widget") // or "myapp-my-widget"
export class MyWidget extends Et2Widget(LitElement)
{
	render()
	{
		return html`...`;
	}
}
```

### Get it loaded

To have EGroupware load your component, it must be included somewhere.
Add a shared component to the import block in `/api/js/etemplate/etemplate2.ts`. If the component is specific to one
application, import it from that application's `app.ts`.

```typescript
...
import './MyWidget/MyWidget.ts';
...
```

Once the widget is loaded, it can be used in an `.xet` template:

```xml

<et2-my-widget id="example"></et2-my-widget>
```

### Add it to the DTD

The `.xet` files use `doc/etemplate2/etemplate2.0.dtd` for validation. A new tag must be in the DTD before IDEs and
XML validators will accept it in templates.

For most widgets, add the class documentation and `@customElement()` registration first, then regenerate the component
metadata and Relax NG schema:

```sh
npm run docs
php doc/etemplate2-rng.php > doc/etemplate2/etemplate2.0.rng
```

Then convert `doc/etemplate2/etemplate2.0.rng` to `doc/etemplate2/etemplate2.0.dtd` with PhpStorm's
Tools > XML Actions > Convert Schema action. Check that the new tag is included in the `%Widgets;` entity and has an
`<!ELEMENT ...>` / `<!ATTLIST ...>` declaration.

If the generated schema needs help with allowed children, attribute types, or compatibility names, add an override in
`doc/etemplate2-rng.php` and regenerate instead of hand-editing only the final DTD.

### Value behaviour

Components do not need to have a value. For components based on `Et2InputWidget`, `widget.value` is the normal way to
access the value programmatically. The eTemplate framework loads the initial value and uses `widget.getValue()` when
submitting the template. Readonly or disabled widgets do not return a value.

Add focused tests for the component's public behaviour. Input widgets should reuse `inputBasicTests()` where practical
to verify standard value and readonly behaviour.

### Namespaces

A namespace scopes a widget's children to a nested section of the managed arrays (`content`, `select_options`,
`readonlys`, and `modifications`). The namespaced widget's `id` is used as the key. For example:

```json
{
  "address": {
	"street": "123 Example Street",
	"city": "Testville"
  }
}
```

```xml

<et2-my-address id="address">
    <et2-textbox id="street"></et2-textbox>
    <et2-textbox id="city"></et2-textbox>
</et2-my-address>
```

If `et2-my-address` creates a namespace, its child widgets read from `address[street]` and `address[city]` instead of
the top-level `street` and `city` entries. Their values are returned to the server with the same nested structure.
Namespaces are useful for container widgets, especially when the same group of child IDs can appear more than once in
a template.

To give a widget its own namespace, override `_createNamespace()`:

```typescript
export class MyAddress extends Et2Widget(LitElement)
{
	_createNamespace() : boolean
	{
		return true;
	}
}
```

The widget must have an `id`, because that ID identifies the namespace. `Et2Widget` creates the scoped array-manager
perspectives automatically when the ID is set, so the widget should not call `checkCreateNamespace()` itself.

Most input widgets should not create a namespace: their ID normally identifies their own value. Namespace support is
primarily intended for widgets that contain other widgets. If namespace creation is conditional, follow the
`Et2Template` pattern and make `_createNamespace()` return the condition; re-run `checkCreateNamespace()` when that
condition changes.
