## Examples

A [tag](/components/et2-tag) with the category's colour down its left edge. It is what
[et2-select-cat](/components/et2-select-cat) renders each selected category as; that is the only
place it is used, and picking it is the select's job, not something you set.

The colour is not an attribute. Every category's colour is published by the server as a CSS
variable named after the category id - `--cat-42-color` - and the tag turns its own `value` into a
reference to the matching one. So a tag with `value="42"` picks up category 42's colour with
nothing else set, anywhere on the page, and a category whose colour changes changes everywhere at
once.

### The colour comes from the value

:::warning
This documentation site has no server, so none of those variables exist here. The preview below
declares two of them by hand to show what the widget does with them. In an application you never
write this - `api/categories.php` emits one for every category the user can see.
:::

```html:preview
<style>
    :root {
        --cat-101-color: #4a90d9;
        --cat-102-color: #cc3333;
    }
</style>
<et2-category-tag value="101">Customers</et2-category-tag>
<et2-category-tag value="102">Urgent</et2-category-tag>
<et2-category-tag value="103">No colour set</et2-category-tag>
```

The third one is a category with no colour: the border falls back to transparent rather than to
some default, so an uncoloured category looks like a plain tag instead of a wrongly-coloured one.

### In a select

What you would actually write. The tags, their colours and their values all come from the
categories the server sends:

```html
<et2-select-cat id="cat_id" multiple></et2-select-cat>
```

### Everything else

Removing, sizes, variants and the `prefix` slot all behave exactly as on
[et2-tag](/components/et2-tag) - this subclass adds the colour bar and nothing else.

```html:preview
<style>
    :root {
        --cat-201-color: #7a9e3f;
    }
</style>
<et2-category-tag value="201" removable size="small">Small</et2-category-tag>
<et2-category-tag value="201" removable size="large" pill>Large pill</et2-category-tag>
```
