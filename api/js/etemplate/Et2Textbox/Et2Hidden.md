## When to use it

A hidden input carries a value the user must not edit but the server needs back: a record id, a
token, a flag some javascript flips. It renders nothing at all - no label, no help text, no box -
and its value is still submitted with the rest of the template.

That is the difference from the two states it is often confused with:

|                 | Visible | Editable | Submits a value |
|-----------------|---------|----------|-----------------|
| `et2-hidden`    | no      | only from javascript | yes |
| `disabled`      | yes, greyed out | no | no |
| `readonly`      | yes | no | no |

So use `et2-hidden` when the value must come back, `readonly` when the user should see the value but
not change it, and `disabled` when the field may become editable again later. See
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden).

There is also a `hidden` attribute on every widget, which hides a widget that would otherwise be
visible and can be un-hidden from javascript. `et2-hidden` is not that: it has no visible form to
go back to.

## Examples

### Invisible, but not absent

The widget below sits between the two paragraphs. It occupies no space, has no shadow parts to
style, and still hands over its value - including a value set from javascript after the page was
built.

```html:preview
<p>Above the hidden widget.</p>
<et2-hidden id="hidden-example" value="42"></et2-hidden>
<p>Below it. Value: <strong id="hidden-output">?</strong> &nbsp;
    <et2-button id="hidden-button" label="Change it" noSubmit="true"></et2-button>
</p>
<script>
    const hidden = document.getElementById("hidden-example");
    const hiddenOutput = document.getElementById("hidden-output");
    const show = () => {hiddenOutput.textContent = JSON.stringify(hidden.value);};

    document.getElementById("hidden-button").addEventListener("click", () =>
    {
        hidden.value = String(Math.floor(Math.random() * 100));
        show();
    });
    customElements.whenDefined("et2-hidden").then(() => hidden.updateComplete).then(show);
</script>
```

### Not the same as `<et2-textbox type="hidden">`

`et2-textbox type="hidden"` builds the whole Shoelace input - label slot, help text, validation
chrome - and then hides it with CSS. It works, but you pay for a form control nobody can see.
`et2-hidden` renders a single `<input type="hidden">` and nothing else, so prefer it whenever the
field is never meant to be shown.
