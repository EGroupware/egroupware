## Examples

### Label and placeholder

`label` names the field, `placeholder` hints at what belongs in it.

```html:preview
<et2-textarea label="Notes" placeholder="Anything worth remembering"></et2-textarea>
```

### Rows

`rows` sets how many lines of text are visible at once. Without it the textarea stretches to fill
the height it is given, which is usually what you want inside a template.

```html:preview
<et2-textarea label="Two rows" rows="2"></et2-textarea>
<et2-textarea label="Six rows" rows="6"></et2-textarea>
```

### Resize

`resize` controls the grab handle in the corner: `vertical` (the default) lets the user drag it
taller, `auto` grows the field as they type, and `none` pins it to its `rows`.

```html:preview
<et2-textarea label="Grows as you type" rows="2" resize="auto"></et2-textarea>
<et2-textarea label="Fixed size" rows="2" resize="none"></et2-textarea>
```

### Width and height

`width` and `height` are applied as inline styles on the widget itself, so they take any CSS length.
They pre-date the web component; a `class` and a stylesheet do the same job and are preferred for
new templates.

```html:preview
<et2-textarea label="Narrow" width="20em" height="5em"></et2-textarea>
```

### Markdown

`markdown` turns the plain textarea into a markdown editor. The value stays markdown source - only
the display changes. `markdown-mode` picks which pane is shown: `view` shows the formatted text and
puts the caret where you click, `edit` shows the source, `split` shows both. Leave `markdown-mode`
off and the user's last choice is used.

```html:preview
<et2-textarea markdown markdown-mode="split" height="12em" value="## Shopping list

Some **bold** text, some *italic* text and a [link](https://www.egroupware.org).

- Milk
- Bread"></et2-textarea>
```

### Readonly

`readonly` keeps the textarea on screen but uneditable, and it submits nothing. In a template the
server usually swaps the widget for `et2-textarea_ro` instead, which renders the text without any
input chrome at all. See
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden).

```html:preview
<et2-textarea label="Readonly" rows="2" readonly value="You can read this, but not change it."></et2-textarea>
<et2-textarea_ro value="The readonly widget the server would use instead."></et2-textarea_ro>
```
