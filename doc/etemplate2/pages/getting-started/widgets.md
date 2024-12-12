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

Disabled widgets are fully shown, but in a way that indicates the value cannot be changed.

Readonly widgets are shown in a special way that displays a value, but is not interactive. Often we switch to a
different component for faster performance, such as a simple
label. It is impossible to change the value of a readonly widget.

Hidden widgets are not visible.

```html:preview
<et2-textbox label="Normal" value="Normal textbox" class="et2-label-fixed"></et2-textbox>
<et2-textbox label="Disabled" disabled value="Disabled textbox" class="et2-label-fixed"></et2-textbox>
<et2-textbox_ro label="Readonly" value="Readonly textbox" class="et2-label-fixed"></et2-textbox_ro>
<et2-textbox label="Hidden" hidden>Hidden textbox</et2-textbox>
```