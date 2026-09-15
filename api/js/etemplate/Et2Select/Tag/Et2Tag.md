## Examples

A tag is one chosen value. When a [select](/components/et2-select) has `multiple`, each selected
option is rendered as one of these inside the select's input, which is where you will meet them
almost always - you do not normally write an `et2-tag` yourself. Understanding them matters
because a select customises its display by swapping in a tag subclass:
[et2-category-tag](/components/et2-category-tag),
[et2-thumbnail-tag](/components/et2-thumbnail-tag) and
[et2-email-tag](/components/et2-email-tag) are all just this widget with something extra, chosen
by the select's `tagTag` getter.

### Where tags come from

A multi-value select makes one tag per chosen option. Nothing here sets a tag up by hand.

```html:preview
<et2-select id="tag-source-example" multiple label="Colours"></et2-select>
<script>
    const tagSource = document.getElementById("tag-source-example");
    tagSource.select_options = [
        {value: "red", label: "Red"},
        {value: "green", label: "Green"},
        {value: "blue", label: "Blue"}
    ];
    tagSource.value = ["red", "blue"];
</script>
```

### On its own

There is no rule against using one outside a select. The content is the label, `value` is what it
stands for.

```html:preview
<et2-tag value="red">Red</et2-tag>
<et2-tag value="green">Green</et2-tag>
```

### Removable

`removable` adds the x. Clicking it does not remove the tag - it fires `sl-remove` and leaves the
decision to whoever put the tag there, which is how a select can refuse to drop a value.

```html:preview
<et2-tag id="tag-remove-example" value="draft" removable>Draft</et2-tag>
<script>
    const tag = document.getElementById("tag-remove-example");
    tag.addEventListener("sl-remove", () => {tag.remove();});
</script>
```

### Variants and size

`variant` colours the tag and `size` matches it to the input it sits in. A select passes its own
`size` down, so tags in a small select are small without anyone asking.

```html:preview
<et2-tag variant="primary">Primary</et2-tag>
<et2-tag variant="success">Success</et2-tag>
<et2-tag variant="neutral">Neutral</et2-tag>
<et2-tag variant="warning">Warning</et2-tag>
<et2-tag variant="danger">Danger</et2-tag>
<et2-tag variant="text">Text</et2-tag>
```

```html:preview
<et2-tag size="small">Small</et2-tag>
<et2-tag size="medium">Medium</et2-tag>
<et2-tag size="large">Large</et2-tag>
```

### Pill

```html:preview
<et2-tag pill>Rounded</et2-tag>
```

### Icon

The `prefix` slot goes before the label. This is the hook the subclasses use - a category's colour
bar, a thumbnail, an email address's avatar all arrive through the prefix.

```html:preview
<et2-tag value="attachment">
    <et2-image slot="prefix" src="paperclip"></et2-image>
    Attachment
</et2-tag>
```

### Editable

`editable` puts a pencil on the tag; clicking it swaps the label for a textbox, and Enter or
leaving the field commits the new value and fires `change`. Escape puts the old value back. A
select turns this on for its tags when its own `editModeEnabled` is set - free-entry values that
were typed once and typed wrong are worth being able to correct in place instead of removing and
re-adding.

```html:preview
<et2-tag id="tag-edit-example" value="typo@example.org" editable>typo@example.org</et2-tag>
<p>Value: <span id="tag-edit-output">typo@example.org</span></p>
<script>
    const editableTag = document.getElementById("tag-edit-example");
    const editOutput = document.getElementById("tag-edit-output");
    editableTag.addEventListener("change", () => {editOutput.textContent = editableTag.value;});
</script>
```

### Disabled

:::warning
A tag is not an input widget, so `disabled` does not grey it out - it takes it off the page, the
usual `Et2Widget` behaviour described in
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden). Two
tags are below; only one of them is visible.
:::

```html:preview
<et2-tag>Enabled</et2-tag>
<et2-tag disabled>Disabled</et2-tag>
```

A tag has no `readonly` rendering of its own either. A readonly select does not hand `removable`
or `editable` to its tags in the first place, which is what makes them look uneditable - so to get
the same result outside a select, leave those two attributes off.

```html:preview
<et2-tag removable editable>Editable</et2-tag>
<et2-tag>Not editable</et2-tag>
```

