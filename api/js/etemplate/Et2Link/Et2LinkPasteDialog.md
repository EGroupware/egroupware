## What it is for

The dialog behind the clipboard button in [`<et2-link-to>`](/components/et2-link-to/). It is an
[`<et2-vfs-select-dialog>`](/components/et2-vfs-select-dialog/) whose contents are not a directory
but the EGroupware *file clipboard* - what Filemanager's "Copy" and "Cut" put there - so the user
can pick some of those files and link them to the current entry.

Because the list is the clipboard, the dialog has no path bar and no toolbar: there is nowhere to
navigate to. Directories in the clipboard are treated as files, so they are selectable like
everything else instead of being opened by a double click.

You do not normally put this tag in a template. `<et2-link-to>` creates one for you, and disables
its clipboard button until it has confirmed the clipboard is not empty. See
[Choosing a link widget](/components/et2-link/#choosing-a-link-widget) for the rest of the family.

:::warning
Not live. The dialog's contents come from the session (`egw.getSessionItem('phpgwapi',
'egw_clipboard')`), and acting on the selection needs a server to link or copy the files. This
documentation site has neither a session nor a server, so the dialog would open empty and its
buttons would do nothing. Everything below describes the behaviour against a running EGroupware.
:::

## Examples

### Opening it yourself

It inherits `<et2-vfs-select-dialog>`'s API, so it opens with `show()` and hands back the button
that was pressed together with the selection:

```html
<et2-link-paste-dialog id="paste" buttonLabel="link"></et2-link-paste-dialog>
```

```js
const dialog = this.et2.getWidgetById("paste");
let [button, files] = await dialog.getComplete();
```

`button` matters as much as `files`: the dialog's footer offers "link", "copy" and "move", which is
the difference between referencing the file where it is and putting a copy of it on the entry.
`fileInfo(path)` turns one of the returned paths into the `{app, id, path, ...}` record the linking
system wants.

### An empty clipboard

If nothing has been copied, the dialog says so - a Filemanager icon and "clipboard is empty!" -
rather than showing an empty list. `<et2-link-to>` avoids getting that far by leaving its clipboard
button disabled until it knows there is something to paste.
