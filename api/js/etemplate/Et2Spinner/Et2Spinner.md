## When to use it

`<et2-spinner>` is the "working on it" indicator - a ring that turns while something is loading.
Show one while you wait for a request, hide it when the answer arrives.

It has no value, no label and nothing to click. If the wait has a known length or a known number of
steps, a progress bar tells the user more; use a spinner when you genuinely do not know how long it
will take.

## Examples

### A spinner

There is nothing to configure to get the default.

```html:preview
<et2-spinner></et2-spinner>
```

### Size

The ring is sized from the font size, so it scales with whatever text it sits in. Set `font-size` on
the widget to make it bigger or smaller.

```html:preview
<et2-spinner style="font-size: 1rem;"></et2-spinner>
<et2-spinner style="font-size: 2rem;"></et2-spinner>
<et2-spinner style="font-size: 4rem;"></et2-spinner>
```

### Colour, thickness and speed

Four CSS custom properties control the rest: `--track-width`, `--track-color`, `--indicator-color`
and `--speed`, the time for one full turn.

```html:preview
<et2-spinner style="font-size: 3rem; --track-width: 6px;"></et2-spinner>
<et2-spinner style="font-size: 3rem; --indicator-color: var(--sl-color-danger-600); --track-color: var(--sl-color-danger-100);"></et2-spinner>
<et2-spinner style="font-size: 3rem; --speed: 3s;"></et2-spinner>
```

### Showing it while you wait

The usual pattern is to keep the spinner in the template and toggle it around the request.

```html:preview
<et2-spinner id="spinner-example" style="font-size: 2rem;"></et2-spinner>
<span id="spinner-example-status">Loading…</span>
<button id="spinner-example-button" type="button">Start again</button>
<script>
    const spinner = document.getElementById("spinner-example");
    const status = document.getElementById("spinner-example-status");

    const run = () =>
    {
        spinner.hidden = false;
        status.textContent = "Loading…";
        window.setTimeout(() =>
        {
            spinner.hidden = true;
            status.textContent = "Done";
        }, 2000);
    };

    document.getElementById("spinner-example-button").addEventListener("click", run);
    run();
</script>
```
