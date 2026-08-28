## How to use this mixin

`SearchMixin` gives a widget "ask the server for values that match a string the user types in,
then let them choose from the results" behaviour - it does not render anything on its own.

### 1. Extend

```ts
export class MySearchingWidget extends SearchMixin(Et2InputWidget(LitElement))
{
	// ...
}
```

### 2. Override `searchResultSelected()`

This is called when the user picks a result. Call `super.searchResultSelected()` first, then
update `this.value` from `this.selectedResults`:

```ts
protected searchResultSelected()
{
	super.searchResultSelected();
	this.value = this.selectedResults[0].value;
}
```

Other methods can be overridden if needed.

### 3. Call the two template methods from `render()`

```ts
render()
{
	return html`
		${this.searchInputTemplate()}
		${this.searchResultsTemplate()}
	`;
}
```

### Example

[`Et2TreeDropdown`](/components/et2-tree-dropdown/) is a real, working example - a tree picker
that searches the tree for matching nodes as you type. Simplified to the parts that matter for
`SearchMixin` specifically:

```ts
export class Et2TreeDropdown extends SearchMixin(Et2WidgetWithSelectMixin(LitElement))
{
	protected searchResultSelected()
	{
		super.searchResultSelected();

		// this.selectedResults holds whatever the server returned for the chosen result(s)
		if (this.multiple)
		{
			this.value = [...new Set([...this.value, ...this.selectedResults.map(el => el.value)])];
		}
		else
		{
			this.value = this.selectedResults[0].value;
		}
	}

	render()
	{
		return html`
			${this.searchInputTemplate()}
			${this.searchResultsTemplate()}
		`;
	}
}
```

Listen for the `et2-select` event on the widget if you need to react to selection changes from
the outside, rather than only inside `searchResultSelected()`.
