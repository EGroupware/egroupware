## When to use it

`et2-category-box` shows the categories an entry belongs to, as a row of read-only chips. It is a
display widget: it renders whatever `value` it is given and offers no way to change the selection.

To let the user *pick* categories, use [`et2-select-cat`](/components/et2-select-cat).

```html:preview
<et2-category-box id="catbox-basic"></et2-category-box>
<script>
	customElements.whenDefined("et2-category-box").then(() =>
	{
		document.getElementById("catbox-basic").value = [
			{value: "1", label: "Urgent"},
			{value: "2", label: "Follow up"}
		];
	});
</script>
```

## Value

The value is an array of `{value, label}` objects - the same shape a category select produces - and
each entry becomes one [`et2-category-tag`](/components/et2-category-tag).

```html:preview
<et2-category-box id="catbox-value"></et2-category-box>
<script>
	customElements.whenDefined("et2-category-box").then(() =>
	{
		document.getElementById("catbox-value").value = [
			{value: "3", label: "Customer"},
			{value: "4", label: "Support"},
			{value: "5", label: "Billing"}
		];
	});
</script>
```

## An empty value takes up no room

An empty or missing value renders nothing at all, rather than an empty box, so a category column
stays blank instead of leaving a gap in the row.

The box below sits between the two labels and has an empty value, so the labels close up against
each other as if it were not there:

```html:preview
<div class="catbox-gap-demo">
	<span>Categories:</span>
	<et2-category-box id="catbox-empty"></et2-category-box>
	<span>(nothing between these)</span>
</div>
<style>
	.catbox-gap-demo {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		border: 1px solid var(--sl-color-neutral-300);
		padding: 0.5rem;
	}
</style>
<script>
	customElements.whenDefined("et2-category-box").then(() =>
	{
		document.getElementById("catbox-empty").value = [];
	});
</script>
```

## In a nextmatch row

The usual use is a category column bound to the row's category list:

```xml
<et2-category-box id="cat_id"></et2-category-box>
```

Category colours come from the individual tags, so they follow the category definitions without
anything extra here.
