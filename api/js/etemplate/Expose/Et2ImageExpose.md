## When to use it

`<et2-image-expose>` is an [`<et2-image>`](../et2-image) that opens full size when you click it. Use
it wherever a template shows a small version of something the user will want a proper look at - an
avatar, a photo in a row, a scanned document attached to an entry.

Two attributes do the work: `src` is the small image that sits in the layout, and `href` is the full
version the gallery loads. If you only ever need the thumbnail, use plain
[`<et2-image>`](../et2-image); if the image is a file in the VFS, use
[`<et2-vfs-mime>`](../et2-vfs-mime), which knows how to find its thumbnail and its download URL.

:::warning
The widgets in the examples below are live, but *opening* one is not. The gallery an
`<et2-image-expose>` opens is styled by the gallery stylesheet a real EGroupware page loads, and
this documentation site does not load it - so clicking one here appends an unstyled block to the
bottom of the page instead of taking over the window. In EGroupware you get a full-screen lightbox
that <kbd>Esc</kbd> or the close button dismisses.
:::

## Examples

### Click to enlarge

`src` is what you see, `href` is what opens.

```html:preview
<et2-image-expose src="image" href="/assets/images/logo.svg" label="The EGroupware logo"
                  style="font-size: 2rem;"></et2-image-expose>
```

### Thumbnail and full version

The two do not have to be the same picture, and usually should not be: point `src` at something
small enough for a list and `href` at the original. Both take a URL as well as an icon name.

```html:preview
<et2-image-expose id="expose-example" src="/assets/images/logo.svg"
                  href="/assets/images/logo.svg" label="Logo"></et2-image-expose>
```

### A caption

`label` is the title shown across the top of the gallery, and the image's `title` in the layout, so
it is worth setting even for a single image.

```html:preview
<et2-image-expose src="card-image" href="/assets/images/logo.svg"
                  label="Shown as the gallery's caption" style="font-size: 2rem;"></et2-image-expose>
```

### In a nextmatch

The real reason for this widget is rows. When it is used in a nextmatch, the gallery is not limited
to the image you clicked - it pages through every other exposable image in the list, loading more
rows as you reach the end, so the user can flick through a folder of photos without going back to
the list between each one.

```xml
<et2-image-expose id="${row}[thumbnail]" src="${row}[thumbnail]" href="${row}[url]" label="$row[name]"/>
```
