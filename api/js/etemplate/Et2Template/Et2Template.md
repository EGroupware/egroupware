The template displays a loader while it is loading the file, and is replaced with the actual content once all widgets
are ready.

```html:preview
<style>
    et2-template {
        min-height: 5em;
    }
</style>
<et2-template template="template"></et2-template>
```

## Loading

:::tip
Since template files are auto-fetched from the server, actual examples here would not work.
:::

### Template

Use `template` attribute with `<app>.<template>` format to specify which template to load.

This will fetch
`/<app>/templates/<interface>/<template>.xet`, where `<interface>` is the user's current interface, or default.

```xml

<et2-template template="infolog.edit"></et2-template>
```

### Sub-templates

If the template file contains more than one template definition, you can load any of the other templates defined after
the file has been loaded using either their full ID or a shortened form. This is useful for breaking a template into
smaller parts.

multiple.xml:

```xml

<overlay>
    <template id="multiple.one" class="one">...</template>
    <template id="multiple.two" class="two">...</template>
    <template id="multiple" class="multiple">
        ...
        <et2-template template="multiple.one"></et2-template>
        ...
        <et2-template template="two"></et2-template>
    </template>
</overlay>
```

### URL

If you need to bypass the autoloading based on template ID, you can specify the full URL to the template file. If there
are multiple templates defined in the file and you did not specify `template`, the last template in the file will be
loaded.

## Content

When loading `Et2Template` will use its array managers (content, select_options, readonlys & modification) to set the
child widget attributes as it loads them.

Use `content` to create a namespace, loading the template using only a sub-section of the content arrays.

Content data:

```json
{
  "address_one": {
    "street": "123 example street",
    "city": "Testville"
  },
  "address_two": {
    "street": "321 Industrial Ave",
    "city": "Testville"
  }
}
```

client/default/view.xet:

```xml

<overlay>
    <template id="address">
        <et2-textbox id="street" label="Street"></et2-textbox>
        <et2-textbox id="city" label="City"></et2-textbox>
        ...
    </template>
    <template id="client.view">
        ...
        <et2-template template="address" content="address_one"></et2-template>
        <et2-template template="address" content="address_two"></et2-template>
        ...
    </template>
</overlay>
```

Result:

![Content example](/assets/components/template_example_content.png)