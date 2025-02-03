```html:preview
<et2-file-item image="file-arrow-up">uploaded_file.ext</et2-file-item>
```

File items show files that have been or are being uploaded with a FileUpload

## Examples

### Closable

Add the `closable` attribute to show a close button that will hide the file.

```html:preview
<et2-file-item image="file-arrow-up" closable>uploaded_file.ext</et2-file-item>
```

### File size

Set the `size` attribute in bytes to display the file's size.

```html:preview
<et2-file-item image="file-arrow-up" size="123455678" >uploaded_file.ext</et2-file-item>
```

### Loading & Progress

Set the `loading` attribute to indicate action on the file. Set `progress` to show progress.

```html:preview
<et2-file-item image="file-arrow-up" loading>uploaded_file.ext</et2-file-item>
<et2-file-item image="file-arrow-up" loading progress="35">uploaded_file.ext</et2-file-item>
```

### Variants

Use the `variant` attribute to set the file item's variant.

```html:preview
<et2-file-item image="file-arrow-up" variant="default">Default</et2-file-item>
<et2-file-item image="file-arrow-up" variant="primary">Primary</et2-file-item>
<et2-file-item image="file-arrow-up" variant="success">Success</et2-file-item>
<et2-file-item image="file-arrow-up" variant="neutral">Neutral</et2-file-item>
<et2-file-item image="file-arrow-up" variant="warning">Warning</et2-file-item>
<et2-file-item image="file-arrow-up" variant="danger">Danger</et2-file-item>
```

### Warnings and errors

`variant` combined with an appropriate image can be used to show a status message.

```html:preview
<et2-file-item image="file-earmark-check" variant="success">File uploaded successfully</et2-file-item>
<et2-file-item image="exclamation-triangle" variant="warning">Upload interrupted.  Please try again later.</et2-file-item>
<et2-file-item image="exclamation-octagon" variant="danger">Wrong filetype</et2-file-item>