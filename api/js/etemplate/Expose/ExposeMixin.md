## Overview

`ExposeMixin` adds "click it to see it bigger": a widget showing an image, a file or a linked entry
gains a full-screen gallery on click, with the other items from the same list to page through.

```ts
export class Et2ImageExpose extends ExposeMixin(Et2Image) { … }
```

Used by [`et2-image-expose`](/components/et2-image-expose),
[`et2-description-expose`](/components/et2-description-expose) and
[`et2-link`](/components/et2-link).

## What gets shown

`getMedia()` turns the widget's value into the gallery's list of items. The default handles the
shapes eTemplate already uses - a VFS stat, a link, an image URL - so most widgets need nothing.

`mediaContentFunction` is the hook for a widget whose value is none of those: give it a function and
it is asked for the media list instead.

`exposeValue` is the value the gallery is built from, which is not always the widget's own `value` -
in a list, it is the row's.

## In a list

The interesting case is a widget inside a [Nextmatch](/components/et2-nextmatch) row: the gallery is
populated from the *whole list*, not just the clicked row, so the user can page through every image
in the result set.

That has a consequence worth knowing when working on this code: a row widget has no widget-tree
parent, the clicked widget can be detached mid-gallery as rows are recycled, and the list's total
count reads zero while a reload is in flight. The mixin handles those; code added around it has to
expect them too.

## The gallery itself

The gallery is [blueimp-gallery](https://github.com/blueimp/Gallery), created once and reused - the
mixin appends a single `#blueimp-gallery` element to the document body the first time it is needed,
rather than one per widget.

:::warning
On this documentation site the gallery opens unstyled - blueimp's own stylesheet is not loaded, only
EGroupware's overrides - so it appears at the bottom of the page instead of as a lightbox. That is a
gap in the docs site, not in the widget.
:::
