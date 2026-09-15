## Examples

A pair of stacked up / down buttons. It has no value and no idea what it is next to - all it does
is emit `et2-scroll` with `detail` `1` for up and `-1` for down, and leave the arithmetic to you.
That is the point: the same widget steps a number, a date, a page number or a position in a list,
depending on what the thing it is slotted into does with the event.

### Stepping a value

Put it in an input's `suffix` slot and handle `et2-scroll`.

```html:preview
<et2-textbox id="scroll-example" value="10">
    <et2-button-scroll slot="suffix"></et2-button-scroll>
</et2-textbox>
<script>
    const field = document.getElementById("scroll-example");
    field.addEventListener("et2-scroll", (event) =>
    {
        field.value = String((parseInt(field.value) || 0) + event.detail);
    });
</script>
```

### Stepping by something other than one

`detail` is a direction, not an amount, so a bigger step is a multiplication in your handler.

```html:preview
<et2-textbox id="scroll-step-example" value="100">
    <et2-button-scroll slot="suffix"></et2-button-scroll>
</et2-textbox>
<script>
    const stepField = document.getElementById("scroll-step-example");
    stepField.addEventListener("et2-scroll", (event) =>
    {
        stepField.value = String((parseInt(stepField.value) || 0) + 25 * event.detail);
    });
</script>
```

### On mobile

The widget renders nothing at all on a mobile device. Two half-height targets stacked on top of
each other are not something a thumb can hit, and a numeric keyboard is faster anyway - so
whatever you slot it into has to remain usable without it.
