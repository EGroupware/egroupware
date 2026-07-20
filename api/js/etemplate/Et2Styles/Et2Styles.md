`Et2Styles` injects CSS into the document `<head>` so the rules can affect the whole
page, not just one widget. It is the modern replacement for the legacy `et2_styles`
widget. Inline CSS can be passed as text content or the `value` property, and an
external stylesheet can be loaded with `src`. When the component is removed, the
injected `<style>` and `<link>` nodes are cleaned up automatically.

## Examples

### Inline CSS

The simplest way is to put the CSS rules inside the tag. They are injected into
the head as a `<style>` element.

```html:preview
<et2-styles>
    .styles-demo {
        color: rebeccapurple;
        font-weight: bold;
    }
</et2-styles>
<div class="styles-demo">This text is styled by the et2-styles above.</div>
```

You can also set the rules from the server via the `value` property:

```html:preview
<et2-styles value=".styles-demo-2 { color: teal; }"></et2-styles>
<div class="styles-demo-2">Styled through the value property.</div>
```

### External stylesheet

Set `src` to load an external `.css` file. Absolute paths and full URLs are used
as-is; relative paths are resolved against the EGroupware webserver root.

```html:preview
<et2-styles src="/egroupware/api/etemplate/extra.css"></et2-styles>
```

A `data:` URL is also passed through unchanged, which is handy for self-contained
examples:

```html:preview
<et2-styles src="data:text/css,.styles-demo-3%20%7B%20color%3A%20orange%3B%20%7D"></et2-styles>
<div class="styles-demo-3">Styled via a data: URL.</div>
```

### Stylesheet beside the template

If you give `src` a bare filename like `row.css`, it is resolved **relative to the
template that contains it**, so you can keep a stylesheet next to the `.xet` that
uses it:

```xml
<!-- infolog/templates/default/index.xet -->
<et2-styles src="row.css"></et2-styles>
```

With the above, `row.css` is loaded from
`/egroupware/infolog/templates/default/row.css` — no app prefix or absolute path
required.

### Datagrid row template styles

When an `et2-nextmatch` row template contains an `et2-styles` element, those
styles are treated as row-template-local styles. They are adopted into the
`et2-datagrid` row shadow root instead of being injected into the document head.

```xml
<template id="infolog.index.rows">
    <grid>
        <columns>
            <column id="info_subject"></column>
        </columns>
        <row class="$class">
            <et2-description id="info_subject"></et2-description>
        </row>
    </grid>

    <et2-styles src="row.css"></et2-styles>
</template>
```

The `et2-styles` element can be anywhere inside the row template definition; it
does not need to be inside the `<row>` element. Both inline CSS and `src` are
supported. A bare filename such as `row.css` resolves beside the `.xet` file that
contains the row template.

If row-template-local styles are present, `et2-nextmatch` adopts only the
framework row stylesheet and the extracted row-template styles into the datagrid
row shadow root. The application's `templates/default/app.css` fallback is not
added to that shadow root. If no row-template-local `et2-styles` is present,
`app.css` is still used as the compatibility fallback for row styling.

:::tip

The injected CSS is document-wide, not scoped to the widget. Use specific
selectors (or a unique `id` on the `et2-styles` element) to avoid affecting
unrelated parts of the page.

:::
