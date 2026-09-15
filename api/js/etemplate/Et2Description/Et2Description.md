## When to use it

`<et2-description>` is the plain read-only text widget. Reach for it whenever a template needs to
show a string the user cannot edit - a heading, a hint, a value pulled out of the content array, a
paragraph of explanation. It is the widget a `<description>` in a legacy template becomes.

Its twin [`<et2-label>`](../et2-label) is the same class under a different tag name. Use `et2-label`
when the text names another widget and `et2-description` for everything else - see
[Et2Label](../et2-label) for the difference.

## Examples

### Text

The text comes from the `value` attribute, or from whatever you put between the tags.

```html:preview
<et2-description value="Set with the value attribute"></et2-description>
<et2-description>Set as the tag's own content</et2-description>
```

`value` is run through `egw.lang()`, so a phrase that has a translation is shown translated. Set
`noLang` on values that are data rather than a translatable phrase - a name, a number, a file path -
so they are never accidentally replaced by a translation.

```html:preview
<et2-description noLang value="Not a phrase, do not translate"></et2-description>
```

### Label

`label` is rendered before the value. If the label contains a `%s`, the value is placed at that spot
and the rest of the label follows it.

```html:preview
<et2-description label="Due:" value="Tomorrow"></et2-description>
<et2-description label="Remind me %s before the event" value="15 minutes"></et2-description>
```

### Links inside the text

`activateLinks` scans the value for URLs and mail addresses and wraps each one in a clickable link.
The text itself stays plain text - nothing else in it is interpreted.

```html:preview
<et2-description activateLinks
                 value="Read more at https://www.egroupware.org or write to info@example.org"></et2-description>
```

### The whole text as a link

`href` turns the entire value into one link. A bare `app.class.method` style href is expanded into
an EGroupware menuaction, an href starting with `/` is resolved relative to the EGroupware
installation, and anything else is used as given.

```html:preview
<et2-description href="https://www.egroupware.org" value="The EGroupware website"></et2-description>
```

`extraLinkTarget` sets the target (default `_browser`), and `extraLinkPopup` - a `widthxheight`
string such as `640x480` - opens the link in a popup window instead of a tab. Neither is shown live
here, because both would spawn a window while you are reading.

### Markdown

`markdown` parses the value as Markdown instead of showing it verbatim. This is opt-in per widget;
without it the value is always plain text, so nothing a user typed can turn into markup by accident.

```html:preview
<et2-description markdown
                 value="Some **bold** text, a bit of `code` and a [link](https://www.egroupware.org)."></et2-description>
```

Translation happens before parsing, so if you enable `markdown` on a translated phrase the Markdown
syntax has to be part of the translated string.
