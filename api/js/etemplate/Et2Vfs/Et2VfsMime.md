## When to use it

`<et2-vfs-mime>` shows the icon for a file in EGroupware's virtual filesystem - the little page-,
folder- or filetype-symbol at the start of a row in the filemanager, in a link list, or beside an
attachment.

It is more than a picture. The icon is chosen from the file's mime type, image and PDF files get a
real thumbnail of their content rather than a generic symbol, a symlink gets a small link overlay,
and clicking it opens the file: in the [expose gallery](../et2-image-expose) if it is something that
can be shown there, in the configured editor if it is an editable document.

Use [`<et2-image>`](../et2-image) if all you want is a fixed icon, and
[`<et2-vfs-name>`](../et2-vfs-name) if you want the file's name as well.

:::warning
The examples on this page are deliberately not live. The icons and thumbnails come from
`api/thumbnail.php`, which reads the file out of the VFS - this documentation site has no server to
ask, so a preview here would render an empty box. Everything below describes the behaviour against a
running EGroupware.
:::

## Examples

### In a filemanager-style row

The value is the row itself. Nothing has to be spelled out: the widget picks `mime`, `path`, `mode`
and `download_url` out of it.

```xml
<et2-vfs-mime id="${row}[mime]"/>
```

### The value

Set as a whole object, the keys the widget reads are:

| key | what it does |
|---|---|
| `mime` | chooses the icon, and decides whether the file can be exposed or edited |
| `path` | the VFS path - used for the thumbnail, and shown as `PDF File` style label |
| `mode` | the unix mode; the symlink bit adds the link overlay |
| `download_url` | where the full file is fetched from when it is opened |
| `mtime` | passed to the thumbnail so a changed file is not served from cache |
| `src` | an explicit icon, overriding the mime lookup |

```js
this.et2.getWidgetById("icon").value = {
    mime: "application/pdf",
    path: "/home/demo/report.pdf",
    download_url: "/webdav.php/home/demo/report.pdf",
    mode: 0o100644
};
```

### Just a mime type

A bare string containing a `/` is treated as a mime type. You get the right icon, but no thumbnail
and nothing to open, because there is no file behind it.

```js
this.et2.getWidgetById("icon").value = "application/pdf";
```

### Directories

A directory - mime type `httpd/unix-directory` - gets the folder icon and is deliberately *not*
exposable. Opening a folder is a navigation, which the action system handles, not something the
gallery should try to display.

### ODF thumbnails

For OpenDocument text, presentation, spreadsheet and chart files the widget binds a tooltip showing
a large version of the thumbnail, so hovering a row previews the document without opening it.
