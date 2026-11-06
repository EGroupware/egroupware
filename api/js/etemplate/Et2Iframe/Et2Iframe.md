## When to use it

`<et2-iframe>` embeds a separate document inside a template - a PDF or image preview, a page served
by another part of EGroupware, a report that is easier to render as its own document than as
widgets.

It is a thin wrapper around a real `<iframe>`. The widget fills its container, so give it a size (or
put it in a box that has one) or you will get a zero-height frame.

## Examples

### A URL

`src` is the document to load. While it is loading a spinner is shown in its place.

```html:preview
<et2-iframe src="/assets/examples/include.html" style="width: 100%; height: 10rem;"></et2-iframe>
```

### Changing the document

`src` is reactive, so assigning a new one loads it. That matters for the common case of binding it
to content - `src="@pdf_file"` in a template, or a grid handing a row's value down to the widget -
where nothing ever calls a setter explicitly.

```html:preview
<et2-iframe id="iframe-src-example" src="/assets/examples/include.html"
            style="width: 100%; height: 10rem;"></et2-iframe>
<button id="iframe-src-button" type="button">Load the next document</button>
<script>
    const documents = [
        "/assets/examples/include.html",
        "/assets/images/widgets_rendered_example.png",
        "/assets/images/styling_et2-label-fixed_1.png"
    ];
    const frame = document.getElementById("iframe-src-example");
    let next = 1;
    document.getElementById("iframe-src-button").addEventListener("click", () =>
    {
        frame.src = documents[next++ % documents.length];
    });
</script>
```

### Content instead of a URL

`set_srcdoc()` puts a document you already have in hand into the frame, without a round trip to a
server. It reaches into the real `<iframe>`, so wait for the widget to be defined before calling it.

```html:preview
<et2-iframe id="iframe-doc-example" style="width: 100%; height: 8rem;"></et2-iframe>
<script>
    const frame = document.getElementById("iframe-doc-example");
    customElements.whenDefined("et2-iframe")
        .then(() => frame.updateComplete)
        .then(() =>
        {
            frame.set_srcdoc("<body style='font: 1rem sans-serif; padding: 1rem'>" +
                "<h1>A whole document</h1><p>Rendered inside the frame, styled by its own CSS, " +
                "with no server involved.</p></body>");
        });
</script>
```

### A target for links

`name` names the frame, so links and forms elsewhere on the page can target it. This is how the
"open it in the preview pane" patterns are built.

```html:preview
<p>
    <a href="/assets/images/widgets_rendered_example.png" target="iframe-target-example">A screenshot</a> |
    <a href="/assets/examples/include.html" target="iframe-target-example">A page</a>
</p>
<et2-iframe name="iframe-target-example" src="/assets/examples/include.html"
            style="width: 100%; height: 10rem;"></et2-iframe>
```

### Permissions

`allow` is passed straight to the `<iframe>` as its permissions policy, and `fullscreen` sets
`allowfullscreen`. Both only matter for documents that ask for those capabilities, so there is
nothing to see in a preview.

```xml
<et2-iframe id="conference" src="@meeting_url" allow="camera; microphone; display-capture" fullscreen="true"/>
```

### Getting at the real frame

`getDOMNode()` returns the `<et2-iframe>` host element, because the framework's placement and sizing
code needs that. When you need the `<iframe>` itself - its `contentDocument`, a `load` listener -
use the `iframe` getter.

```js
const frame = this.et2.getWidgetById("preview");
frame.iframe.addEventListener("load", () => console.log("document ready"));
```
