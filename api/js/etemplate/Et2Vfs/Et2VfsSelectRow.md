## When to use it

You do not normally use `et2-vfs-select-row` directly. It renders one file in the listing inside
[`et2-vfs-select-dialog`](/components/et2-vfs-select-dialog), and that dialog creates the rows
itself.

It is documented here because it is the widget to change if you want the file dialog's rows to look
or behave differently.

```html:preview
<et2-vfs-select-row id="vfsrow-basic"></et2-vfs-select-row>
<script>
	customElements.whenDefined("et2-vfs-select-row").then(() =>
	{
		document.getElementById("vfsrow-basic").value = {
			name: "Quarterly report.pdf",
			path: "/home/demo/Quarterly report.pdf",
			mime: "application/pdf",
			size: 249856,
			is_dir: false
		};
	});
</script>
```

## Value

One file, as the dialog's listing returns it: `name`, `path` and `mime`, plus `size` and `is_dir`.
`is_dir` is what makes a row a folder the user can descend into rather than a file to select.

## State

Three properties drive how a row is drawn, all set by the dialog rather than by a template:

- `selected` - the row is part of the current selection.
- `current` - the row is the one keyboard navigation is on.
- `disabled` - the row cannot be chosen, because it does not match the dialog's `mime` filter.
