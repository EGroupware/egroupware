`et2-details` shows a summary line and expands to reveal the rest. Use it for the parts of a form
that most users do not need - advanced options, rarely used address fields, a long description.

It is a thin wrapper around Shoelace's `<sl-details>`, so `summary`, `open`, `disabled`, the
`show()` / `hide()` methods and the `sl-show` / `sl-hide` events all behave as documented there. The
attributes below are the eTemplate additions.

Content inside a closed details is still in the DOM, still submitted, and still found by
`getWidgetById()`. If you need it gone rather than collapsed, use `disabled` on the widgets
themselves.

## Examples

### Summary and content

`summary` is the always-visible line. Anything else you put inside is the content that appears when
it is opened.

```html:preview
<et2-details summary="Advanced options">
    <et2-vbox>
        <et2-checkbox>Keep a copy in Sent</et2-checkbox>
        <et2-checkbox>Request a read receipt</et2-checkbox>
    </et2-vbox>
</et2-details>
```

### Open by default

```html:preview
<et2-details summary="Already open" open>
    <et2-description value="Visible without a click."></et2-description>
</et2-details>
```

### Toggle on the left

The expand arrow sits at the right end of the summary. `toggleAlign="left"` puts it in front of the
summary text instead, which reads better for a short label in a narrow column.

```html:preview
<et2-details summary="Arrow on the right">
    <et2-description value="Default"></et2-description>
</et2-details>
<et2-details summary="Arrow on the left" toggleAlign="left">
    <et2-description value="toggleAlign=left"></et2-description>
</et2-details>
```

### Opening on hover

`toggleOnHover` opens the details when the pointer enters it and closes it again when the pointer
leaves. Handy for a preview or a toolbar overflow; avoid it for anything the user has to interact
with, since moving the mouse to a field inside can close it on the way.

```html:preview
<et2-details summary="Hover over me" toggleOnHover>
    <et2-description value="Opened without a click."></et2-description>
</et2-details>
```

### Accordion

Give several details the same `accordionGroup` and opening one closes the others. The group is
matched by name across the whole document, so pick something specific to the template.

```html:preview
<et2-details summary="Contact" accordionGroup="details-accordion-example">
    <et2-description value="One at a time."></et2-description>
</et2-details>
<et2-details summary="Delivery" accordionGroup="details-accordion-example">
    <et2-description value="Opening this closes the others."></et2-description>
</et2-details>
<et2-details summary="Payment" accordionGroup="details-accordion-example">
    <et2-description value="And this one closes that."></et2-description>
</et2-details>
```

### Breaking out of the container

By default the content pushes everything below it down. `hoist` positions the content fixed instead,
so it floats over the following content and the surrounding layout does not move. Use it when the
details lives in a toolbar or a table cell that must not change height.

```html:preview
<div style="border: 1px solid var(--sl-color-neutral-300); padding: var(--sl-spacing-small)">
    <et2-details summary="Hoisted" hoist>
        <et2-description value="Floats over the text below."></et2-description>
    </et2-details>
    <et2-description value="This line does not move when the details opens."></et2-description>
</div>
```

### Overlaying the summary

`overlaySummaryOnOpen` lets the open content cover the summary line, so the widget takes no more room
open than closed apart from the content itself. Combine it with `toggleAlign` to choose which side
the toggle stays on.

```html:preview
<et2-details summary="Search" overlaySummaryOnOpen>
    <et2-textbox placeholder="Covers the summary"></et2-textbox>
</et2-details>
```

### Reacting to open and close

The Shoelace `sl-show` and `sl-hide` events fire on open and close. `sl-show` is also what
`accordionGroup` listens to.

```html:preview
<et2-details id="details-event-example" summary="Watch the log">
    <et2-description value="Open and close me."></et2-description>
</et2-details>
<p>Last event: <span id="details-event-output">none</span></p>
<script>
    const details = document.getElementById("details-event-example");
    const out = document.getElementById("details-event-output");
    details.addEventListener("sl-show", () => {out.textContent = "sl-show";});
    details.addEventListener("sl-hide", () => {out.textContent = "sl-hide";});
</script>
```

### Disabled hides it

Shoelace's `<sl-details>` greys itself out when disabled, but the eTemplate base widget applies
`display: none` to any disabled widget that is not an input, and a details is not an input. So
`disabled` here removes the whole thing - summary and all - exactly like `hidden`.

The two details below are in the preview; only the first one is on the page.

```html:preview
<et2-details summary="Enabled">
    <et2-description value="Visible"></et2-description>
</et2-details>
<et2-details summary="Disabled - you cannot see this" disabled>
    <et2-description value="Unreachable"></et2-description>
</et2-details>
```

If you want the summary to stay on screen but not open, `disabled` is not it - leave the details
enabled and disable the widgets inside.
