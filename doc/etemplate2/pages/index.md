---
meta:
  title: 'eTemplate documentation'
  description: Reference and tutorials for building EGroupware interfaces with eTemplate
toc: false
---

# eTemplate documentation

eTemplate builds EGroupware application interfaces from XML template (`.xet`) files. Templates arrange reusable widgets
and connect them to the data supplied by application code.

## Editing a template

If you are editing or customising a `.xet` file, start with:

- [Widgets](/getting-started/widgets) for template syntax and common widget behaviour.
- [Styling](/getting-started/styling) for shared CSS variables, utility classes, parts, and custom properties.
- [Component reference](/components/sandbox) to find the attributes, properties, events, methods, slots, and styling
  hooks
  available for each widget.

Templates are normally stored in `<app>/templates/default/*.xet`. A minimal template looks like this:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE overlay PUBLIC "-//EGroupware GmbH//eTemplate 2.0//EN" "https://www.egroupware.org/etemplate2.0.dtd">
<overlay>
    <template id="myapp.edit">
        <et2-vbox>
            <et2-textbox id="name" label="Name" class="et2-label-fixed"></et2-textbox>
            <et2-button id="save" label="Save"></et2-button>
        </et2-vbox>
    </template>
</overlay>
```

## Developing widgets

If an existing widget does not provide the behaviour you need, use the tutorials to:

- [Create a widget](/tutorials/creating-a-widget).
- Follow the [Web Component Authoring standards](/tutorials/web-component-authoring).
- [Convert a legacy widget to a Web Component](/tutorials/converting-to-webcomponents).
- Add [automatic tests](/tutorials/automatic-testing).

## Live examples

Examples marked as previews are rendered directly in the documentation:

```html:preview
<et2-box>Box content</et2-box>
```

Unfortunately, not all examples work properly in the documentation. Some need additional setup or information from the
server.