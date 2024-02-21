```html:preview
<et2-vfs-select-dialog class="file-select"></et2-vfs-select-dialog>
<et2-button noSubmit>Open dialog</et2-button>
// TODO: This doesn't work because of Dialog / keymanager issues
<script>
  const dialog = document.querySelector('.file-select');
  const openButton = dialog.nextElementSibling;

  openButton.addEventListener('click', () => {dialog.show()});
</script>
```

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
let files = await dialog.getComplete();
```

This is probably the best way to get files (or directories) that you then want to do something with on the client. See
also [Et2VfsSelectButton](../et2-vfs-select) which can pass the files to take action on the server. 