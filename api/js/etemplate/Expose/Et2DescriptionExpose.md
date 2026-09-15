## When to use it

`et2-description-expose` is an [`et2-description`](/components/et2-description) that opens its file
in the gallery overlay when clicked, instead of navigating to it.

Use it for a caption or file name that should preview in place. For an image that is itself the
thing to click, use [`et2-image-expose`](/components/et2-image-expose).

```html:preview
<et2-description-expose id="expose-basic" value="Quarterly report"></et2-description-expose>
```

## Pointing it at a file

`href` is the file to show and `mime` tells the widget what it is. The gallery needs both: without a
mime type it cannot tell whether it is able to display the file.

```xml
<et2-description-expose id="attachment" value="$name" href="$download_url" mime="$mime"></et2-description-expose>
```

## When the gallery cannot show it

The widget degrades instead of failing. If `mime` is a type the gallery cannot display - a
spreadsheet, an archive - the widget behaves like an ordinary linked description and the click
follows the link as a normal download.

That means it is safe to use for a mixed list of attachments: previewable files open in the
gallery, the rest download, and the template does not have to choose between two widgets per row.

## Gallery behaviour

The overlay itself comes from [ExposeMixin](/mixins/exposemixin), which is also what gives the
gallery its navigation between sibling files in the same list.
