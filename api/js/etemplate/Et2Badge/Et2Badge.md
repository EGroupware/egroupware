## When to use it

`<et2-badge>` is a small coloured pill for a count or a short status word - unread messages, open
tickets, "draft", "overdue". It is for a label attached to something else on the screen, not for a
sentence; anything longer than a word or two belongs in a
[description](../et2-description).

## Examples

### Content

The badge shows whatever you put between the tags.

```html:preview
<et2-badge>12</et2-badge>
<et2-badge>New</et2-badge>
```

### Variant

`variant` picks the colour, and with it the meaning: `primary` (the default), `success`, `neutral`,
`warning` and `danger`.

```html:preview
<et2-badge variant="primary">Primary</et2-badge>
<et2-badge variant="success">Success</et2-badge>
<et2-badge variant="neutral">Neutral</et2-badge>
<et2-badge variant="warning">Warning</et2-badge>
<et2-badge variant="danger">Danger</et2-badge>
```

### Pill and pulse

`pill` rounds the ends, which reads better for a bare number. `pulse` animates the badge to draw the
eye to something that just changed - use it sparingly, an always-pulsing badge stops meaning
anything.

```html:preview
<et2-badge variant="danger" pill>3</et2-badge>
<et2-badge variant="danger" pill pulse>3</et2-badge>
```

### Setting the count from javascript

`value` is the badge's content seen as a widget value - reading it gives you the text, assigning to
it replaces the text. That is what lets a badge be bound to content in a template, or updated from
application code.

```html:preview
<et2-badge id="badge-example" variant="primary" pill>0</et2-badge>
<button id="badge-example-button" type="button">One more</button>
<script>
    const badge = document.getElementById("badge-example");
    document.getElementById("badge-example-button").addEventListener("click", () =>
    {
        badge.value = String(parseInt(badge.value || "0", 10) + 1);
    });
</script>
```

### On another widget

A badge is usually positioned over the thing it counts. Put both in a container and let the badge
overlap.

```html:preview
<span style="position: relative; display: inline-block;">
    <et2-button noSubmit label="Inbox"></et2-button>
    <et2-badge variant="danger" pill
               style="position: absolute; top: -0.5rem; right: -0.5rem;">7</et2-badge>
</span>
```
