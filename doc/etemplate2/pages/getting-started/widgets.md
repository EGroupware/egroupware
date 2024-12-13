## Widgets

Widgets are the building blocks of our UI.
We are currently making all our
widgets [WebComponents](https://developer.mozilla.org/en-US/docs/Web/API/Web_components)
based on [Lit](https://lit.dev/docs/). Many of our widgets use [Shoelace](https://shoelace.style) components as building
blocks.

If you just want to use existing widgets, you can put them in your .xet template file:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE overlay PUBLIC "-//EGroupware GmbH//eTemplate 2.0//EN" "https://www.egroupware.org/etemplate2.0.dtd">
<overlay>
    <template id="myapp.mytemplate">
        <et2-vbox>
            <et2-textbox id="name" label="Your name" class="et2-label-fixed"></et2-textbox>
            <et2-select-number id="quantity" label="Quantity" class="et2-label-fixed"></et2-select-number>
        </et2-vbox>
    </template>
</overlay>
```

<img src="/assets/images/widgets_rendered_example.png" alt="Rendered example template">

## Attributes

Widget behaviour is customised by setting attributes

### ID

// TODO, maybe some notes about content & namespaces.

### Disabled vs Readonly vs Hidden

Disabled widgets are fully shown, but in a way that indicates the value cannot be changed. Use disabled to indicate that
at some point a user may be able to change the value, but not right now. The widget may be enabled via javascript.

Readonly widgets are shown in a special way that displays a value, but is not interactive. Often we switch to a
different component for faster performance, such as a simple
label. It is impossible to change the value of a readonly widget, and it cannot be made editable via javascript - the
page must be reloaded.

Hidden widgets are not visible, but may be enabled via javascript.

```html:preview
<table>
<tr><td>Normal</td><td><et2-textbox value="Normal textbox" class="et2-label-fixed"></et2-textbox></td><td><et2-button label="Button"></et2-button></td></tr>
<tr><td>Disabled</td><td><et2-textbox disabled value="Disabled textbox" class="et2-label-fixed"></et2-textbox></td><td><et2-button label="Button" disabled></et2-button></td></tr>
<tr><td>Readonly</td><td><et2-textbox_ro value="Readonly textbox" class="et2-label-fixed"></et2-textbox_ro></td><td><et2-buttton disabled label="Button" readonly></et2-button></td></tr>
<tr><td>Hidden</td><td><et2-textbox hidden>Hidden textbox</et2-textbox></td><td><et2-button label="Hidden" hidden></et2-button></td></tr>
</table>
```

When the page is submitted normal and hidden widgets will have their values returned and validated. Widgets that are
disabled when the page is submitted will not return their value.
Readonly widgets do not return a value.