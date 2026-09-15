## When to use it

`<et2-html>` takes a string of HTML and puts it on the page as HTML. Use it when the markup is
produced somewhere else - a rich-text field, a preview rendered on the server, a fragment assembled
by an application - and there is no widget that already knows how to display it.

If your value is plain text, use [`<et2-description>`](../et2-description) instead: it escapes the
value, so nothing a user typed can turn into markup. `<et2-html>` deliberately does not escape
anything, so only give it HTML you trust.

Unlike most widgets, this one renders into the light DOM rather than a shadow root. That is the
point: the markup you hand it has to inherit the page's normal styling, and any CSS classes in it
have to keep matching the application's stylesheet.

## Examples

### Showing HTML

The value is inserted as markup, not as text.

```html:preview
<et2-html value="<p>A paragraph with <strong>bold</strong> and <em>italic</em> text.</p>"></et2-html>
```

### A label in front

`label` is rendered as a plain span before the value.

```html:preview
<et2-html label="Description" value="<ul><li>First item</li><li>Second item</li></ul>"></et2-html>
```

### Setting the value from javascript

The value is a normal reactive property, so assigning to it replaces what is shown.

```html:preview
<et2-html id="html-example" value="<p>Nothing loaded yet.</p>"></et2-html>
<button id="html-example-button" type="button">Load</button>
<script>
    const widget = document.getElementById("html-example");
    document.getElementById("html-example-button").addEventListener("click", () =>
    {
        widget.value = "<p style='color: var(--sl-color-success-600)'>Replaced at " +
            new Date().toLocaleTimeString() + "</p>";
    });
</script>
```

### Scripts in the value

A `<script>` inserted through `innerHTML` never runs. Legacy `et2_html` did run them, because it
appended through jQuery, and applications relied on that - so this widget re-creates every script
element after rendering to keep them executing.

That is worth knowing before you feed it a value from anywhere you do not control: script tags in
the value are not inert here.

```html
<et2-html id="report" value="<div id='out'></div><script>document.getElementById('out').textContent = 'ran';</script>"></et2-html>
```
