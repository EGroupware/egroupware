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