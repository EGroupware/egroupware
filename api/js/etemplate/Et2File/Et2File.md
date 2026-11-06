```html:preview
<et2-file label="Attachment"></et2-file>
```

File allows the user to upload files to EGroupware. The uploaded files are processed on the server by the application
after the form is
submitted. As files are selected, they will be shown in a list with [FileItem](../et2-file-item)

:::tip
Three widgets can upload a file: [File](../et2-file), [VfsUpload](../et2-vfs-upload)
and [LinkTo](../et2-link-to).

- [File](../et2-file) if the files are not going into the VFS at all, or you don't know yet where they go. They arrive
  as temporary files in the submitted content (`name`, `type`, `tmp_name`, `size`, like `$_FILES`) and your application
  decides what to do with them.
- [LinkTo](../et2-link-to) if the files belong to the current entry. It puts them into the entry's own attachment
  directory without being told a path, and holds uploads for a not yet saved entry until it has an ID.
- [VfsUpload](../et2-vfs-upload) if you can name the destination. It needs a target path, or the already saved entry
  the files belong to - see [Where the files go](../et2-vfs-upload/#where-the-files-go) for what happens when it
  can't resolve one.
:::

## Examples

### Icon

Use `image` to specify the icon

```html:preview
<et2-file image="cloud-upload" ></et2-file>
```

### Limit files allowed

Use the `multiple`, `accept`, `maxFiles` and `maxFileSize` attributes to place restrictions on the files to be uploaded.

```html:preview
<et2-file image="image" accept="image/*" label="Choose an image"></et2-file>
<et2-file image="images" accept="image/*" multiple label="Choose images"></et2-file>
<et2-file maxFiles="3" label="Max. 3 files"></et2-file>
<et2-file maxFileSize="10000" label="Small files only"></et2-file>
```

`accept` can take mimetypes (`"image/*"`), subtypes (`"image/jpeg"`), extensions (`".svg"`) or combinations (
`"image/*,application/pdf"`)

### Inline

Normally the selected files are listed in a dropdown to avoid changing the flow of the rest of the page. Set `inline` to
not do that

```html:preview
<et2-file label="Choose an image" inline></et2-file>
```

### Display

Use the `display` attribute for different ways of showing results
```html:preview
<et2-file display="large" label="Large">
<et2-file-item slot="list" image="https://images.unsplash.com/photo-1529778873920-4da4926a72c2?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&q=80" size="654321000" display="large" closable>kitten.jpg</et2-file-item>
</et2-file>
<et2-file display="small" label="Small">
<et2-file-item slot="list" image="https://images.unsplash.com/photo-1591871937573-74dbba515c4c?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&q=80" size="654321000" display="small" closable>kitten2.jpg</et2-file-item>
</et2-file>
<et2-file display="list" label="List">
<et2-file-item slot="list" size="1234567" display="list" closable>File(s) shown as list</et2-file-item>
<et2-file-item slot="list" image="file-earmark-pdf" size="654321000" display="list" closable>annual-report.pdf</et2-file-item>
<et2-file-item slot="list" image="file-earmark-image" size="87654" display="list" closable>vacation-photo.jpg</et2-file-item>
<et2-file-item slot="list" image="file-earmark-spreadsheet" size="45210" display="list" closable>budget.xlsx</et2-file-item>
</et2-file>
```
