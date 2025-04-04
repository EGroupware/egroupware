```html:preview

<et2-vfs-upload
    multiple
    image="cloud-upload" 
    label="Select files to upload" 
    helpText="Please check your files are complete before uploading"
></et2-vfs-upload>
```

VFS Upload allows the user to upload files to a specified location in the VFS. It works much the same
as [File](../et2-file), but there are differences:

1. Files go directly into the VFS without the application needing to handle them. With the File widget the file is
   stored temporarily and the application must move it.
2. Any operations (save, delete, replace existing file) are handled directly. With the File widget, the application must
   handle this.

Any option for File will also work for VfsUpload.

`VfsUpload` does not return file information to the application since all file actions are done immediately via
AJAX.

:::tip
There are two widgets for uploading files, [File](../et2-file) and [VfsUpload](../et2-vfs-upload).

Use `File` when you don't know where in the VFS the file will be stored or don't intend to store it.

Use `VfsUpload` otherwise.
:::

## Examples

### Path

Use `path` to specify the where in the VFS the files will be stored. Specifying a specific file name will allow
uploading a single file, which will be renamed accordingly. Using a directory will allow uploading multiple files into
the directory.

Setting path will adjust `multiple` to match.

```html:preview
<et2-vfs-upload path="~/uploads/" label="Directory"></et2-vfs-upload>
<et2-vfs-upload path="~/contract.pdf" label="Upload contract.pdf" accept="application/pdf"></et2-vfs-upload>
```