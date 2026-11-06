## Examples

Categories, as a tree. Unlike the rest of the select family this is not an
[et2-select](/components/et2-select) at all but a
[tree dropdown](/components/et2-tree-dropdown), because categories nest and a flat list cannot show
that. Most of the attributes on this page come from there rather than from `et2-select`.

Categories are per application and are never translated, so a category called "Support" stays
"Support" in every language.

### Basic

The list comes from the server for one application. The options below are written out by hand
because this page has no categories to read - note that a sub-category is a `children` array on its
parent, not a flat entry with a parent id.

```html:preview
<et2-select-cat id="cat-basic" label="Category" value="2"></et2-select-cat>
<p>Value: <span id="cat-basic-output">?</span></p>
<script>
    const cat = document.getElementById("cat-basic");
    const catOutput = document.getElementById("cat-basic-output");
    cat.select_options = [
        {
            value: "1", label: "Support", children: [
                {value: "2", label: "Hardware"},
                {value: "3", label: "Software"}
            ]
        },
        {value: "4", label: "Sales"},
        {value: "5", label: "Internal"}
    ];
    const showCat = () => {catOutput.textContent = JSON.stringify(cat.value);};

    cat.addEventListener("change", showCat);
    customElements.whenDefined("et2-select-cat").then(() => cat.updateComplete).then(showCat);
</script>
```

In an eTemplate that is one line, and the categories arrive with the template:

```xml
<et2-select-cat id="cat_id" label="Category"/>
```

### Which categories

`application` picks whose categories to show. Left unset it is the application the template belongs
to, which is what you want almost always - set it only to reach into another application's
categories.

`globalCategories` (on by default) includes the categories that belong to no application, and
`parentCat` narrows the list to the sub-tree below one category.

```xml
<et2-select-cat id="cat_id" label="Category" application="infolog" globalCategories="false"/>
```

### Colours

A category can have a colour, and the widget shows it: as a bar down the side of the closed control
for a single value, and on each tag when `multiple` is set. Nothing has to be passed for that - the
colour comes with the category from the server.
