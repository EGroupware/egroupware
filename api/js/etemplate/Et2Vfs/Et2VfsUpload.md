```html:preview
<et2-vfs-upload label="Attachment"></et2-vfs-upload>
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
Three widgets can upload a file: [File](../et2-file), [VfsUpload](../et2-vfs-upload)
and [LinkTo](../et2-link-to).

- [VfsUpload](../et2-vfs-upload) needs a destination. It writes into the VFS itself, so it only works if you can name
  the target path, or the already saved entry the files belong to.
- [LinkTo](../et2-link-to) if you have no VFS path, or the entry is not saved yet. It puts files into the entry's own
  attachment directory for you, and holds uploads for a new entry until it has an ID.
- [File](../et2-file) if the files are not going into the VFS at all. The upload is handed to your application as a
  temporary file and what happens to it is up to you.
:::

## Where the files go

`VfsUpload` writes the file itself, so it has to know where. The destination comes from `path`, or - if `path` is not
set - from the widget's `id`. That fallback is why many existing templates are written as
`<et2-vfs-upload id="myapp:$cont[id]:"></et2-vfs-upload>` with no `path` attribute at all.

Either one can be:

- an absolute VFS path - `/home/demo/uploads/`, `/apps/myapp/42/contract.pdf`
- `<app>:<id>:<relative path>`, resolved on the server (`Api\Link::vfs_path()`) to that entry's own attachment
  directory, eg. `myapp:42:` becomes `/apps/myapp/42/`. `$cont[...]` is expanded, so `path="myapp:$cont[id]:"` follows
  whichever entry the template is showing.

A trailing `/` is a directory and allows multiple files. Anything else is a single file name, and the uploaded file is
renamed to match.

:::danger
A destination that cannot be resolved is not an error. A plain `id` with no `path`, or `<app>:<id>:` for an entry that
does not have an ID yet, both end up in a per-user temporary directory (`/home/<user>/.tmp/<app>_<hash>/`). The upload
succeeds, the user sees the file listed as though everything worked, and the application gets the temporary paths in
the widget's value when the form is submitted - but nothing ever moves those files to where they belong. Doing that,
and cleaning up what is left behind, is entirely up to the application.
:::

## Entries that are not saved yet

Because of that, `VfsUpload` only fits once the entry exists and its ID is in the content. An edit dialog that also
creates entries should use [LinkTo](../et2-link-to) instead: while `to_id` is still an array it collects the uploads
in its own value, and the application turns them into real attachments after saving with

```php
Api\Link::link('myapp', $new_id, $content['link_to']['to_id']);
```

## Examples

### Path

Use `path` to specify the where in the VFS the files will be stored. Specifying a specific file name will allow
uploading a single file, which will be renamed accordingly. Using a directory will allow uploading multiple files into
the directory.

Setting path will adjust `multiple` to match.

```html:preview
<et2-vfs-upload path="/home/demo/uploads/" label="Directory"></et2-vfs-upload>
<et2-vfs-upload path="/home/demo/contract.pdf" label="Upload contract.pdf" accept="application/pdf"></et2-vfs-upload>
```
