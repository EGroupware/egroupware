---
meta:
  title: 'Usage'
  description: How to use an eTemplate widget - its properties, slots, events, methods, and the CSS hooks it exposes.
---

# Usage

Every widget documented in the [component reference](/components/sandbox) is a
[custom element](https://developer.mozilla.org/en-US/docs/Web/API/Web_components/Using_custom_elements). Each one's
page opens with the widget's tag and class name, followed by whatever hand-written material exists for it - an
overview of what the widget is for, and worked examples that render live in the page with their source beside them.
Not every widget has either, but where they exist they will tell you more about that particular widget than any of
the tables can, so start there.

The rest of the page is generated from the source, and is the same set of sections every time:

| Section                                   | What it is                                                                |
|-------------------------------------------|---------------------------------------------------------------------------|
| [Properties](#properties)                 | The widget's public state - what you set to configure it                  |
| [Slots](#slots)                           | Places inside the widget where you can put your own content               |
| [Events](#events)                         | What the widget tells you about                                           |
| [Methods](#methods)                       | What you can ask the widget to do                                         |
| [Custom properties](#custom-properties)   | CSS variables the widget reads, for adjusting its appearance              |
| [Parts](#component-parts)                 | Elements inside the widget's shadow DOM that you are allowed to style     |
| [Animations](#animations)                 | Animations the widget plays, and how to replace them                      |

This page explains how to use each of those. For the template syntax itself see [Widgets](/getting-started/widgets),
and for the shared CSS variables and utility classes see [Styling](/getting-started/styling).

## Where widgets are used

Almost always in an application's `.xet` template file, which [Widgets](/getting-started/widgets) covers. eTemplate
parses the file, creates the widgets, connects them to the content your PHP code supplied, and inserts them into the
page.

Because they are custom elements, the same widgets also work in plain HTML or when created from JavaScript with
`document.createElement()`. That is how the live examples on these documentation pages work. A widget created that way
has no template around it, so it has no content array, no id namespace and no `etemplate2` instance to submit to -
anything in this page that depends on content (`@`/`$` expansion, translation of `label`, `readonly` from the
server-side `readonlys` array) only happens for widgets built from a template.

## Properties

Properties are the widget's public state. The Properties table on each component page lists the property name, its
type, and its default. Everything a widget lets you configure is a property.

In a template, set a property by using its name as an attribute:

```xml
<et2-textbox id="name" label="Name" maxlength="64" required="true"></et2-textbox>
```

```html:preview
<et2-textbox label="Name" maxlength="64" class="et2-label-fixed"></et2-textbox>
```

A few things about this that are specific to eTemplate:

**Case is preserved.** `.xet` files are parsed as XML, not HTML, so attribute names keep their case. Write the property
exactly as the table shows it - `noLang`, `selectOptions`, `helpText` - not `nolang`. (In plain HTML, such as the
previews on this site, the browser lowercases attribute names, so a camelCase property has to be set from JavaScript
instead.) For compatibility with older templates, an underscore attribute name is converted to camelCase, so
`no_lang` also sets `noLang`.

**Values are coerced to the property's type.** The widget declares the type, and eTemplate converts the attribute
string before setting it:

- `Boolean` properties are parsed as boolean expressions, so `readonly="true"`, `disabled="!@allow_edit"` and
  `hidden="@type=note"` all work. Those three are the standard eTemplate state properties, and they do not mean the
  same thing - see [Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden) for the
  difference, and for which of them still submit a value.
- `Object` and `Array` properties are parsed as JSON, so `<et2-select selectOptions='{"1":"One","2":"Two"}'>`.
- `Function` properties - the `on*` handlers - are compiled, see [Events](#events).

**Content expansion happens first.** An attribute value containing `@` or `$` is looked up in the content array before
it is set, so `label="@name_label"` takes its text from the content your application sent, and `$row`/`$row_cont`
resolve per row inside a repeating grid or nextmatch.

**Some properties are translated.** `label`, `statustext`, `helpText` and `placeholder` are run through
`egw.lang()` automatically, so write them in English and add the phrase to your app's `lang/egw_en.lang` -
do not call `egw.lang()` yourself in the template. Text in braces is translated piecewise, so
`label="{Firstname} {Lastname}"` translates each word separately.

### Attributes and properties

An attribute is the string you write in the markup; a property is the value the widget actually holds. They are not
always the same thing, and only some properties are reflected back out as attributes.

From JavaScript, always set the property - never `setAttribute()`. Only the property accepts a non-string value, and
only the property reliably triggers the widget to re-render:

```js
const name = this.et2.getWidgetById('name');
name.label = 'Full name';   // good
name.value = 'Fred';        // good
name.setAttribute('value', 'Fred');   // do not do this
```

Values you can only set from JavaScript, never as an attribute, are the ones that are not strings to begin with: a
callback function, or an object holding live references.

## Slots

A slot is a labelled hole inside the widget where you can put your own content. The widget decides where slotted
content is rendered; you decide what goes in it. Use the `slot` attribute on the child:

```xml
<et2-details>
    <et2-description slot="summary" value="Advanced options"></et2-description>
    <et2-textbox id="tricky" label="Tricky option"></et2-textbox>
</et2-details>
```

The Slots table lists each named slot and what it is for. A row named `(default)` is the unnamed slot - any child
without a `slot` attribute ends up there, which is why the textbox above lands in the details' body.

```html:preview
<et2-details>
  <span slot="summary">Advanced options</span>
  <et2-textbox label="Tricky option" class="et2-label-fixed"></et2-textbox>
</et2-details>
```

Slotted content stays in the light DOM, so it is styled by your application's stylesheet in the normal way - no
`::part()` needed. It is also not permanent: a widget can be re-rendered, and content can be added to or removed from
a slot at any time.

Some widgets, such as `<et2-select>`, use their default slot for their own child widgets (the options), so check the
component page before slotting something into it.

## Events

Widgets report what happened by dispatching DOM events. The Events table on each component page lists the event name
and, where the event carries data, the type of its `detail`.

There are three kinds of event name you will see:

- `change`, `input`, `focus`, `blur` - the standard names, used by the input widgets. `change` is emitted only as the
  result of user input; setting `widget.value` from your own code deliberately does *not* emit it, the same as a
  native form control.
- `et2-*` - events specific to eTemplate, for example `et2-load`, `et2-selection-changed`, `et2-file-complete`.
- `sl-*` - events that come from the underlying [Shoelace](https://shoelace.style) component the widget is built on,
  for example `sl-show` and `sl-after-hide`.

In a template, handle an event with the matching `on*` attribute. The value is a function name, which is called with
the event and the widget:

```xml
<et2-select id="status" onchange="app.myapp.statusChanged"></et2-select>
<et2-button id="check" label="Check" onclick="app.myapp.check"></et2-button>
```

```js
// myapp/js/app.ts
statusChanged(ev, widget)
{
    this.et2.getWidgetById('reason').disabled = widget.value !== 'rejected';
}
```

`app.<appname>.<method>` is resolved against your application's JavaScript object, and is the form to use. An
`on*` attribute can also hold a short expression, which is compiled into a function with `ev` and `widget` in scope -
useful for one-liners such as `onchange="egw.set_preference('myapp', widget.id, widget.value)"`, but anything longer
belongs in your app object. Returning `false` from an `onclick` handler stops the default action, which for a submit
button means the form is not submitted.

Only `on*` attributes that the widget declares as a `Function` property work this way - `onchange` on the input
widgets, `onclick` on all of them. For anything else in the Events table, add a listener:

```js
this.et2.getWidgetById('nm').addEventListener('et2-selection-changed', (ev) => {
    console.log(ev.detail);
});
```

Nearly all `et2-*` events are dispatched with `bubbles` and `composed` set, so a listener on a containing element
sees them too. The `sl-*` events behave the same way. That makes it possible to listen once on a container rather
than on each widget - but check the component page before relying on it, a few events are deliberately dispatched
without bubbling.

## Methods

Methods are what you can ask a widget to do. The Methods table lists the method name, what it does, and its arguments.
Methods are only callable from JavaScript - there is no way to call one from a template.

Get the widget by the `id` you gave it in the template, then call the method:

```js
// myapp/js/app.ts
et2_ready(et2, name)
{
    super.et2_ready(et2, name);

    if(name === 'myapp.edit')
    {
        this.et2.getWidgetById('name').focus();
    }
}
```

`this.et2` is the widget tree of the template currently loaded for your application, and `getWidgetById()` searches
it by widget id. Do this from `et2_ready()` or later - before it runs, the widgets do not exist yet. If you need a
template belonging to a different application, or have several loaded at once, use
`etemplate2.getById('<dom-id>')` or `etemplate2.getByApplication('<appname>')` and take `.widgetContainer` from
the result.

Inherited methods are listed in their own collapsed section on each component page, because every widget inherits a
substantial number of them from `Et2Widget` and its mixins. The ones you are most likely to want:

- `getWidgetById(id)` / `getParent()` / `getChildren()` to move around the widget tree
- `getInstanceManager()` for the `etemplate2` instance the widget belongs to, whose `submit()` submits the template
- `getArrayMgr('content')` for the content the widget was built from
- `getValue()` on input widgets, which is what submit uses - it returns `null` for a widget that will not submit,
  whereas the `value` property just tells you the current value

## Custom properties

Custom properties are CSS variables the widget reads for its own appearance. They are part of the widget's public
API - unlike its internal variables, they are documented and will not be renamed without notice. Set one anywhere
the widget inherits it from, which is usually on the widget itself or on an ancestor:

```css
/* myapp/templates/default/app.css */
#myapp-index_nm {
    --row-height: 3em;
}
```

Because a CSS variable is inherited, setting it on an ancestor applies it to every widget inside that reads it:

```css
.compact-view {
    --row-height: 1.5em;
}
```

That inheritance is also why they are the right tool for anything that has to respond to context - a custom property
can be changed by a media query, by a `:hover` rule, or by a class your application toggles, none of which a regular
property can do. Set them from your application's stylesheet rather than from JavaScript.

Global variables that are not tied to a single widget - `--primary-background-color`, `--label-width`,
`--category-color` and the Shoelace design tokens - are documented under [Styling](/getting-started/styling).
A custom property whose name starts with `--et2-` or `--sl-` is one of those global tokens, not a widget's own.

## Component parts

A widget's internals live in shadow DOM, which your stylesheet cannot reach. A part is an element the widget has
deliberately exposed so you *can* style it. Target it with `::part()`:

```html:preview
<style>
  .tomato-button::part(base) {
    background: var(--sl-color-neutral-0);
    border: solid 1px tomato;
  }

  .tomato-button::part(label) {
    color: tomato;
  }
</style>
<et2-button class="tomato-button" label="Custom button"></et2-button>
```

Part names follow a BEM-inspired convention. `base` is the widget's outermost internal wrapper, and most widgets
expose it. Input widgets additionally expose `form-control`, `form-control-label`, `form-control-input` and
`form-control-help-text` for the standard label / control / help-text regions. A name containing `__`, such as
`tag__prefix`, is a part forwarded from a component nested inside the widget.

Two limits are worth knowing before you reach for a part:

- You can only style the part itself. `::part()` cannot match a part's children or siblings, so
  `::part(base) > span` does not work. If you need to style something inside, it has to be its own part.
- Only documented parts exist. An internal class name is not a part, and styling one by any other means will break
  when the widget is next changed.

Prefer a part when you need to restyle one specific element; prefer a [custom property](#custom-properties) when the
value is reused throughout the widget, or when it needs to change with screen size.

## Animations

Widgets animate through two mechanisms.

Most animate in their own stylesheet with CSS, wrapped in a `prefers-reduced-motion` query so the animation is
skipped for users who have asked for that. If the animated element is exposed as a part, you can override or disable
the animation from your stylesheet in the normal way:

```css
et2-details::part(summary-icon) {
    transition: none;
}
```

Widgets built on a Shoelace component - anything with `sl-show` / `sl-hide` in its Events table - use Shoelace's
animation registry for showing and hiding instead. Those are the animations listed in a component's Animations table.
They are not CSS, so they are replaced through the registry rather than from a stylesheet. The names come from the
underlying Shoelace component, so `<et2-details>` uses `details.show` and `details.hide`:

```js
import {setAnimation, setDefaultAnimation} from '@shoelace-style/shoelace/dist/utilities/animation-registry.js';

// For one widget
setAnimation(this.et2.getWidgetById('advanced'), 'details.show', {
    keyframes: [{opacity: 0}, {opacity: 1}],
    options: {duration: 150, easing: 'ease'}
});

// For every widget of that type
setDefaultAnimation('details.show', {
    keyframes: [{opacity: 0}, {opacity: 1}],
    options: {duration: 150, easing: 'ease'}
});
```

Pass `null` as the animation to remove it entirely. Whichever mechanism you use, keep honouring
`prefers-reduced-motion` - an animation a user has opted out of should not come back because it was overridden.
