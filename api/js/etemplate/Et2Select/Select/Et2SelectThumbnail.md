## Examples

A list of images, shown as thumbnails instead of as text. Each value is an image URL, and that URL is
both the option's value and the picture the user sees - there are no labels. In an application the
URLs are usually links into the VFS or somewhere else the pictures already live.

It is an [et2-select](/components/et2-select) with `multiple` and `allowFreeEntries` fixed on and the
dropdown arrow hidden, because the point of it is collecting URLs rather than choosing from a list.
Options that are already selected are hidden from the dropdown, so the same image cannot be added
twice.

The examples below use inline `data:` images so they need nothing from a server.

### Basic

The value is an array of URLs. Paste a URL and press Enter to add one; click a thumbnail's X to
remove it.

```html:preview
<et2-select-thumbnail id="thumb-basic" label="Images"></et2-select-thumbnail>
<p>Value: <span id="thumb-basic-output">?</span></p>
<script>
    // No literal spaces - see below
    const blueCircle = "data:image/svg+xml,<svg%20xmlns='http://www.w3.org/2000/svg'%20width='32'%20height='32'>" +
        "<circle%20cx='16'%20cy='16'%20r='15'%20fill='%232563eb'/></svg>";
    const orangeSquare = "data:image/svg+xml,<svg%20xmlns='http://www.w3.org/2000/svg'%20width='32'%20height='32'>" +
        "<rect%20width='32'%20height='32'%20rx='6'%20fill='%23ea580c'/></svg>";

    const thumb = document.getElementById("thumb-basic");
    const thumbOutput = document.getElementById("thumb-basic-output");
    thumb.value = [blueCircle, orangeSquare];
    const showThumb = () => {thumbOutput.textContent = thumb.value.length + " image(s)";};

    thumb.addEventListener("change", showThumb);
    customElements.whenDefined("et2-select-thumbnail").then(() => thumb.updateComplete).then(showThumb);
</script>
```

### Offering images to choose from

Options are not required, but they turn the widget into a picker rather than a paste box. Give each
one the image URL as its `value` and its `icon`, and leave the `label` empty - it is never shown.

```html:preview
<et2-select-thumbnail id="thumb-options" label="Images"></et2-select-thumbnail>
<script>
    const green = "data:image/svg+xml,<svg%20xmlns='http://www.w3.org/2000/svg'%20width='32'%20height='32'>" +
        "<circle%20cx='16'%20cy='16'%20r='15'%20fill='%2316a34a'/></svg>";
    const purple = "data:image/svg+xml,<svg%20xmlns='http://www.w3.org/2000/svg'%20width='32'%20height='32'>" +
        "<rect%20width='32'%20height='32'%20rx='6'%20fill='%237c3aed'/></svg>";

    const picker = document.getElementById("thumb-options");
    picker.select_options = [
        {value: green, label: "", icon: green},
        {value: purple, label: "", icon: purple}
    ];
    picker.value = [green];
</script>
```

### No spaces in the URL

A space in a value is not survivable: option values go through the dropdown, which cannot hold one,
and what comes back out has the spaces replaced. A URL with a space in it therefore arrives at the
`<img>` broken. Percent-encode it as `%20` before handing it over - which is why the `data:` images
above are written without a single literal space.
