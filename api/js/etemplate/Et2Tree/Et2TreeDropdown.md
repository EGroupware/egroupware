## When to use it

`et2-tree-dropdown` is [`et2-tree`](/components/et2-tree) in a dropdown. Reach for it when the
choices are hierarchical but the tree itself would take up too much room to leave on screen - a
category picker in a toolbar, a folder picker in an edit dialog.

If there is room for the tree to stay open, use `et2-tree`; if the choices are flat, use
[`et2-select`](/components/et2-select).

```html:preview
<et2-tree-dropdown id="treedrop-basic" label="Folder"></et2-tree-dropdown>
<script>
	customElements.whenDefined("et2-tree-dropdown").then(() =>
	{
		document.getElementById("treedrop-basic").select_options = [
			{
				value: "inbox", label: "Inbox",
				children: [
					{value: "inbox/work", label: "Work"},
					{value: "inbox/personal", label: "Personal"}
				]
			},
			{value: "sent", label: "Sent"},
			{value: "trash", label: "Trash"}
		];
	});
</script>
```

## Options

Nodes come from `select_options`, in exactly the shape `et2-tree` uses: an object per node with a
`value` and a `label`, and `children` nesting below it. See
[Et2Tree: Options](/components/et2-tree) for the full node format.

## Selecting several

`multiple` lets the user pick more than one node. Each selection becomes a tag in the control, and
the value becomes an array of node ids.

```html:preview
<et2-tree-dropdown id="treedrop-multi" label="Folders" multiple></et2-tree-dropdown>
<script>
	customElements.whenDefined("et2-tree-dropdown").then(() =>
	{
		document.getElementById("treedrop-multi").select_options = [
			{
				value: "projects", label: "Projects",
				children: [
					{value: "projects/website", label: "Website"},
					{value: "projects/migration", label: "Migration"}
				]
			},
			{value: "archive", label: "Archive"}
		];
	});
</script>
```

## Restricting what can be chosen

`leafOnly` stops the user selecting a node that has children, for the common case where only the
bottom of the hierarchy is a real choice and the levels above it are just grouping.

```html:preview
<et2-tree-dropdown id="treedrop-leaf" label="Sub-category" leafOnly></et2-tree-dropdown>
<script>
	customElements.whenDefined("et2-tree-dropdown").then(() =>
	{
		document.getElementById("treedrop-leaf").select_options = [
			{
				value: "support", label: "Support",
				children: [
					{value: "support/bug", label: "Bug"},
					{value: "support/question", label: "Question"}
				]
			},
			{
				value: "sales", label: "Sales",
				children: [
					{value: "sales/quote", label: "Quote"}
				]
			}
		];
	});
</script>
```

`clearable` adds a clear button once something is selected, and `placeholder` sets the hint shown
while the control is empty.
