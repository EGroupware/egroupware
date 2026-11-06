## When to use it

`et2-vfs-select` is a button that opens the VFS file-selection dialog. Use it when the user needs to
pick a file or a directory from the filemanager - attaching an existing file, choosing an export
destination, setting a directory in configuration.

To upload a new file instead of picking an existing one, use
[`et2-vfs-upload`](/components/et2-vfs-upload).

```html:preview
<et2-vfs-select id="vfsselect-basic"></et2-vfs-select>
```

Opening the dialog needs a server to list the VFS, so the button above shows the widget but cannot
open anything on this documentation site.

## Two ways to use the result

The widget can either hand you the selection as its value, or act on it immediately.

As a value, it behaves like any other input - the selected path or paths become the widget's value
and are submitted with the form:

```xml
<et2-vfs-select id="target_dir" mode="select-dir" buttonLabel="Choose folder"></et2-vfs-select>
```

With `method`, the dialog instead calls that server method with the selection as soon as the user
confirms, and the widget carries no value of its own. `methodId` is passed along so the method knows
which entry it is working on:

```xml
<et2-vfs-select method="app.ui.ajax_import" methodId="$id" mode="open-multiple"></et2-vfs-select>
```

## Choosing what can be picked

- `mode` - what the dialog is for: opening a file, opening several, saving to a name, or choosing a
  directory.
- `multiple` - allow more than one selection.
- `mime` - restrict the listing to matching file types.
- `path` - the directory the dialog starts in.
- `filename` - the name pre-filled in a save dialog.

## Appearance

`buttonLabel` sets the text and `image` the icon; with neither, the button shows the filemanager
icon alone. `title` sets the tooltip.

The dialog itself is documented under
[`et2-vfs-select-dialog`](/components/et2-vfs-select-dialog).
