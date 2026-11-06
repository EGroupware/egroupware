## Examples

A [tag](/components/et2-tag) that is a picture rather than a word. It is what
[et2-select-thumbnail](/components/et2-select-thumbnail) renders each selected value as - and
there the value *is* the image URL, so the tag shows the thing itself instead of a filename
nobody can recognise.

### The image

The image goes in the `prefix` slot as a plain `<img>`, which is what the select does. The tag
sizes it for you: full width of the tag, fixed height, and the tag itself is capped so one wide
picture cannot stretch the whole row.

```html:preview
<et2-thumbnail-tag removable>
    <img slot="prefix" src="/assets/images/logo.svg"/>
</et2-thumbnail-tag>
```

### Several

Side by side is the normal case - a select with `multiple` produces one per chosen image.

```html:preview
<et2-thumbnail-tag removable>
    <img slot="prefix" src="/assets/images/logo.svg"/>
</et2-thumbnail-tag>
<et2-thumbnail-tag removable>
    <img slot="prefix" src="/assets/images/logo.svg"/>
</et2-thumbnail-tag>
```

### With a caption

Content after the image is still the label, so you can name the picture as well as show it.
Whether that helps depends on the list - for a set of photos it is noise, for a set of templates
or signatures it is not.

```html:preview
<et2-thumbnail-tag removable>
    <img slot="prefix" src="/assets/images/logo.svg"/>
    Logo
</et2-thumbnail-tag>
```

### In a select

What you would actually write. Values are image URLs, and the user adds one by dropping or pasting
it in:

```html
<et2-select-thumbnail id="images" multiple></et2-select-thumbnail>
```

Removing, sizes and pill all behave as on [et2-tag](/components/et2-tag); this subclass only
changes how the image inside is sized.

