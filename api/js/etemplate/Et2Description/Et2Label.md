## et2-label or et2-description?

`<et2-label>` and [`<et2-description>`](../et2-description) are the same class - `Et2Label` is an
empty subclass of `Et2Description` registered under a second tag name. Every attribute documented
below behaves identically in both.

They exist separately so a template says which of the two jobs the text is doing:

- **`<et2-label>`** names something else on the screen. It is the caption in front of an input, the
  title over a box, the word that tells the user what the widget beside it is for.
- **`<et2-description>`** *is* the content. It shows a value, a hint or a paragraph of explanation
  that stands on its own.

If the text would be meaningless with the widget next to it removed, it is a label. If it would
still say something useful, it is a description.

Most widgets have their own `label` attribute, and that is always the better choice - it is
associated with the input for screen readers and it lays out correctly in a form. Use a separate
`<et2-label>` for the cases a `label` attribute cannot cover: a caption over a group of widgets, or
a label that has to sit somewhere the widget's own label cannot go.

## Examples

### A caption

The text comes from `value`, or from the tag's own content.

```html:preview
<et2-label value="Participants"></et2-label>
<et2-label>Attachments</et2-label>
```

### Naming another widget

`for` takes the `id` of another widget in the same template. The label's text is then published as
that widget's accessible name, and clicking the label focuses it.

This one is deliberately not live: `for` is resolved against the surrounding eTemplate widget tree,
and this documentation page has no eTemplate around its examples, so the two widgets below would
never find each other here.

```xml
<et2-label for="name" value="Your name"></et2-label>
<et2-textbox id="name"></et2-textbox>
```

### Everything else

Labels support the same text handling as descriptions - `activateLinks`, `href`, `markdown`,
`noLang` and a `label` of their own. See [Et2Description](../et2-description) for worked examples of
each.

```html:preview
<et2-label markdown value="Fields marked **bold** are required"></et2-label>
```
