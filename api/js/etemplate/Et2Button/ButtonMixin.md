## Overview

`ButtonMixin` is what makes a button an eTemplate button rather than a plain one: it knows how to
submit the surrounding template, and it gives a button sensible defaults based on what the button is
called.

```ts
export class Et2Button extends Et2InputWidget(ButtonMixin(SlButton)) { … }
```

## Submitting

Clicking a button submits the template it lives in. Two properties change that:

| Property | Effect |
|---|---|
| `noSubmit` | click does not submit at all - the button is only there for its click handler |
| `noValidation` | submits without running validation first |

`noValidation` is for buttons where the current values matter more than their correctness - a
"Cancel", or a step that saves a draft. A returning `false` from the click handler also cancels the
submit, which is the usual way to make submitting conditional.

## Defaults chosen from the id

A button with no `image` gets one picked from its id, and may also get a colour class. This is why a
button called `save` looks like a save button without anyone saying so.

The id is matched against a table of patterns (`default_background_images` in the source). Among
them: `save`, `apply`, `cancel`, `delete`, `discard`, `edit`, `next`/`continue`, `finish`,
`back`/`previous`, `copy`, `more`, `yes`/`check`, `no`, `ok`, `close`, `link`, `add`/`create`.

Three ids also pick up a colour (`default_classes`): `cancel` and `yes`/`no` become yellow, `delete`
becomes red.

The patterns are anchored so they match the end of an id or a `[…]` segment, which is what lets
`button[cancel]` and `cancel` both match while `cancellation` does not. Setting `image` explicitly
always wins.

Some ids additionally register a keyboard shortcut - `save` binds Ctrl+S, for instance.

## hideOnReadonly

A readonly button is shown greyed out by default. `hideOnReadonly` is meant to restore the older
behaviour of hiding it entirely.

:::warning
The two spellings do not agree, so use `hideOnReadonly="true"` in templates.

The property is declared with `attribute: "hide"`, but the stylesheet matches
`:host([hideonreadonly][disabled])`. So writing `hideOnReadonly="true"` lands an attribute the CSS
matches - the button does hide - while leaving the *property* `false`; and writing `hide` sets the
property but does not match the CSS, so nothing happens visually. The source docblock notes the same
confusion. Reading `widget.hideOnReadonly` is therefore not a reliable answer to "is this hidden?".
:::

## Related

`noSubmit` and `noValidation` are the button half of the submit story; what each widget contributes
to a submit is on [`Et2InputWidget`](/mixins/et2inputwidget).
