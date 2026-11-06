## Overview

`Et2InputWidget` is the mixin that makes a widget an *input*: something with a value the user can
change, that validates, and that gets submitted back to the server. Every field in this reference -
textbox, select, date, checkbox, file - is built from it, on top of
[`Et2Widget`](/mixins/et2widget).

```ts
export class Et2Example extends Et2InputWidget(LitElement) { … }
export class Et2Example extends Et2InputWidget(SlInput) { … }   // wrapping a Shoelace input
```

## Value

The value is not declared here as a property, because each widget stores it in whatever shape suits
it - a string, a number, an array for a multi-select, an object for a link. What the mixin defines is
the *contract* around it: `getInputNode()` returns the element actually holding the value (often
inside a Shoelace component's shadow root), and `handleSlChange()` bridges a wrapped Shoelace
component's own change event to eTemplate's.

Two things that catch people out:

- A widget's submitted value is not always what it displays. Check the individual widget's page -
  a date widget displays in the user's format but submits a fixed one, and a checkbox submits
  `selectedValue`/`unselectedValue` rather than `true`/`false`.
- Setting a property before the element has upgraded is fine - Lit replays it - but calling a method
  or reading `updateComplete` is not. Wait for `customElements.whenDefined()` first.

## Validation

`validate()` runs the widget's validators and `isValid()` reports the result. `needed` marks a field
as required. Validation errors sent by the server arrive through the array managers
([`Et2Widget`](/mixins/et2widget)) and are shown against the right field automatically.

`hasFeedbackFor` carries which kinds of feedback are currently displayed, which is how a field shows
an error state without the template having to manage it.

Validation happens on submit, and a failing field blocks it. A button with `noValidation` submits
anyway - useful for "cancel" or for a button that only needs the current values, not correct ones.

## Submitting

`submit()` sends the surrounding eTemplate to the server. Which widgets contribute a value is worth
being precise about, because it is a common source of "my value did not arrive":

| State | Submits a value? |
|---|---|
| normal | yes |
| `hidden` | yes |
| `readonly` | no |
| `disabled` when the page was generated or submitted | no |

See [Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden).

## Focus and blur

`et2HandleFocus()` and `et2HandleBlur()` normalise focus handling across plain elements and wrapped
Shoelace components, which report focus from inside their shadow root. `autofocus` puts the cursor in
the field when the template loads.

## disabled behaves normally here

[`Et2Widget`](/mixins/et2widget)'s base style hides a disabled widget outright
(`:host([disabled]) {display: none}`). This mixin overrides that back to `display: initial`, so on an
input `disabled` does what it should: the field stays visible and shows that it cannot be edited.
That override is the reason inputs and everything else disagree about what `disabled` means.
