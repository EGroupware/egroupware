## Opening the dialog

Put the dialog in your template and call `show()` on it:

```html
<et2-vfs-select-dialog id="files"></et2-vfs-select-dialog>
<et2-button id="pick" noSubmit label="Open dialog"></et2-button>
```

```js
const dialog = this.et2.getWidgetById("files");
this.et2.getWidgetById("pick").onclick = () => dialog.show();
```

:::warning
This one example is deliberately not live. `show()` waits on the directory listing before it
opens the dialog, and this documentation site has no server to list a directory from - so the
dialog would never appear no matter what you clicked. Everything below describes the real
behaviour against a running EGroupware.
:::

## Selected files

You can get the selected files by:

### Widget value

If the dialog is in the template, it will return values on submit back to the server.

### Change event

When the selected file(s) change, the change event is fired

```js
const dialog = this.et2.getWidgetById("files");
dialog.addEventListener("change", this.handleFilesSelected);
```

### getComplete() Promise

When the user closes the dialog, getComplete() will return the selected files.

```js
const dialog = this.et2.getWidgetById("files");
let [button, files] = await dialog.getComplete();
```

This is probably the best way to get files (or directories) that you then want to do something with on the client. See
also [Et2VfsSelectButton](../et2-vfs-select) which can pass the files to take action on the server. 