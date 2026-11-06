## When to use it

`et2-vfs-name_ro` is the read-only file-name column in a filemanager listing: it shows the basename
of a VFS file, and clicking it opens the file.

It is [`et2-vfs-name`](/components/et2-vfs-name) with an
[`et2-description`](/components/et2-description) base instead of a textbox. Everything else - the
row binding, the path decoding, the click-to-open behaviour - is shared between the two, so use
this one wherever the name is not meant to be edited.

```html:preview
<et2-vfs-name_ro id="vfsnamero-basic"></et2-vfs-name_ro>
<script>
	customElements.whenDefined("et2-vfs-name_ro").then(() =>
	{
		document.getElementById("vfsnamero-basic").value = {
			path: "/home/demo/Quarterly report.pdf",
			name: "Quarterly report.pdf",
			mime: "application/pdf"
		};
	});
</script>
```

## Value

Like the editable widget, it binds to the whole row rather than to a string:

```xml
<et2-vfs-name_ro id="$row"></et2-vfs-name_ro>
```

See [Et2VfsName: Value](/components/et2-vfs-name) for the row shape and the path encoding rules,
which are identical here.
