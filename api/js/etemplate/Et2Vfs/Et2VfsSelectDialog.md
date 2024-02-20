You can get the files by:

### Widget value

The dialog will return values on submit back to the server

### Change event

When the selected file(s) change, the change event is fired

```js
const dialog = this.et2.getWidgetById("files");
dialog.addEventListener("change", this.handleFilesSelected);
```

### getComplete() Promise

When the user closes the dialog, getComplete() will return the selected files

```js
const dialog = this.et2.getWidgetById("files");
let files = await dialog.getComplete();
```
