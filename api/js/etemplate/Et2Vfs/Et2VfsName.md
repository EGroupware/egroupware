## When to use it

`et2-vfs-name` is the editable file-name field in a filemanager row. It shows the *basename* of a
VFS file while keeping the full path internally, so renaming a file does not require the template to
carry the directory around separately.

For the read-only column, use [`et2-vfs-name_ro`](/components/et2-vfs-name_ro). To show or edit a
whole path rather than one file's name, use [`et2-vfs-path`](/components/et2-vfs-path).

```html:preview
<et2-vfs-name id="vfsname-basic"></et2-vfs-name>
<script>
	customElements.whenDefined("et2-vfs-name").then(() =>
	{
		document.getElementById("vfsname-basic").value = {
			path: "/home/demo/Quarterly report.pdf",
			name: "Quarterly report.pdf",
			mime: "application/pdf"
		};
	});
</script>
```

## Value

The widget is bound to a whole VFS row, not to a string:

```xml
<et2-vfs-name id="$row"></et2-vfs-name>
```

A row object supplies `path` (the full path) and `name` (the basename). The widget displays `name`
and remembers the rest, because opening the file needs the path and the mime type. Given a plain
string instead, it uses that string as the name and has no path to open.

## Path encoding

VFS paths are encoded on the wire and decoded for display: the client side always holds a decoded
value, the server always sends and receives an encoded one. The widget decodes the value once when
it first receives it and encodes it again on submit, so a file called `Report #3 (final).pdf`
survives a round trip without the template doing anything.

That conversion is the reason to use this widget rather than a plain
[`et2-textbox`](/components/et2-textbox) for a file name.

## Opening the file

Clicking a name opens the file with the standard handler for its mime type. If the server could not
resolve the file at all - a symlink whose target was deleted, for instance - the widget says so
rather than opening a link that would 404 into a blank tab.
