`SearchMixin` gives a widget "ask for values matching what the user typed, then let them choose from
the results" behaviour. It handles the debounce, running local and remote searches together,
de-duplicating, counting, and keyboard navigation. It renders a search input and a result list for
you, but a host that already has its own can suppress either.

## Getting started

### 1. Extend

```ts
export class MySearchingWidget extends SearchMixin(Et2InputWidget(LitElement))
{
	// ...
}
```

### 2. Say what selecting a result means

The mixin knows a result was picked; only you know what that does to your value. Call `super` first,
then read `this.selectedResults`:

```ts
protected searchResultSelected()
{
	super.searchResultSelected();
	this.value = this.selectedResults[0]?.value ?? "";
}
```

### 3. Put the two templates in your `render()`

```ts
render()
{
	return html`
		${this.searchInputTemplate()}
		${this.searchResultsTemplate()}
	`;
}
```

That is a working searching widget. Everything below is for when the defaults are not what you want.

## Getting results from the server

Point `searchUrl` at a menuaction. The mixin sends the search string and its options, and expects
`{results: [...], total: n}` back:

```ts
@property() searchUrl : string = "EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelectFiles";
```

The server side wants to answer with that shape. `total` is how many exist altogether, not how many
you are returning - the mixin uses the difference to say "42 more...":

```php
public static function ajax_mySearch($search, $options)
{
	$rows = my_search_function($search, $options['num_rows'] ?? 100);

	Json\Response::get()->data([
		'results' => array_map(static function($row) {
			return ['value' => $row['id'], 'label' => $row['name']];
		}, $rows),
		'total'   => my_count_function($search),
		// optional - shown to the user, eg. "Access denied"
		'message' => null,
	]);
}
```

`Api\Etemplate\Widget\Vfs::ajax_vfsSelectFiles()` is a real one to copy from.

A result needs `value` and `label`. It may also carry `title` (hover text), `icon`, `color`,
`class`, `disabled`, and `children` for a group - see the `SearchResult` type.

### Sending extra parameters

Whatever is in `searchOptions` goes along with the request, and can be set from a template:

```html
<et2-select id="folder" searchUrl="app.mail.searchFolder"
            searchOptions="{&quot;noPrefixId&quot;: &quot;true&quot;}"></et2-select>
```

If your widget always needs a parameter, put it in `_classSearchOptions` instead - that way
`searchOptions` can still override it:

```ts
// a suggestion list is not a result list, so ask for fewer than the default 100
protected _classSearchOptions = {num_rows: 10};
```

## Getting results from a JS method instead

`searchUrl` does not have to be a server endpoint.

**An app method, from a template.** Give it `"app.<appname>.<method>"` and it is resolved through
`egw().applyFunc()`, which loads that app's JS object if it is not loaded yet - so this needs no
extra code in the app's `et2_ready()`:

```html
<et2-select id="folder" searchUrl="app.mail.searchFolder"></et2-select>
```

```ts
// myapp/js/app.ts
async mySearch(search : string, options : any) : Promise<SearchResultsInterface<SearchResult>>
{
	const matches = (await this.getThings())
		.filter(t => t.name.toLowerCase().includes(search.toLowerCase()));

	return {results: matches.map(t => ({value: t.id, label: t.name})), total: matches.length};
}
```

Mail's real `searchFolder()` returns a bare `{value, label}[]` instead, and works because it is
used on an `et2-select`, which normalises legacy shapes (see below).

A bare array is tolerated on the plain mixin too - it warns once naming the source, then uses the
array as the results. **Do not rely on that.** There is no total in a bare array, so "n more..."
cannot be shown, and the tolerance is marked `@deprecated` to be removed once nothing warns. Return
`{results, total}`.

**A function, for one widget instance.** Useful when the results come from something you already
have in hand:

```ts
const widget = this.et2.getWidgetById("picker");
widget.searchUrl = (search, options) =>
{
	const matches = this.alreadyLoadedRows.filter(r => r.name.includes(search));
	return Promise.resolve({
		results: matches.map(r => ({value: r.id, label: r.name})),
		total: matches.length
	});
};
```

Prefer this over overriding `remoteSearch()` for a single instance - overriding needs a subclass
with its own registered tag.

`searchUrl` is `{attribute: false}`, because a function cannot come from an HTML attribute. Setting
it from a template still works: etemplate assigns attributes as properties.

## Searching things you already have on the client

Local and remote searches run together, and the results are merged. The mixin cannot guess where
your options live, so hand them over:

```ts
protected localSearch<DataType extends SearchResult>(search : string, searchOptions : object, localOptions : DataType[] = []) : Promise<DataType[]>
{
	return super.localSearch(search, searchOptions, this.select_options);
}
```

It recurses into `children`, so option groups work without extra effort.

## Changing what counts as a match

`searchMatch()` decides whether one local option matches. By default it is case-insensitively
contained in `label`, `value` or `title`, in the original or the translation.

To add a condition, call `super` and narrow the result:

```ts
public searchMatch<FileInfo>(search : string, searchOptions : Object, option : FileInfo) : boolean
{
	let result = super.searchMatch(search, searchOptions, option);

	// also has to be the right mime type
	if(result && searchOptions.mime)
	{
		result = result && option.mime.match(searchOptions.mime);
	}
	return result;
}
```

To replace the rule entirely, do not call `super`:

```ts
// only match from the start, not anywhere in the label
public searchMatch(search : string, searchOptions : Object, option) : boolean
{
	return option.label?.toLowerCase().startsWith(search.toLowerCase()) ?? false;
}
```

Note this only affects **local** options. The server decides what matches remotely - if you need
different remote matching, that belongs in the endpoint.

## Changing how a result looks

Override `resultTemplate()` for the whole row, or `iconTemplate()` for just the icon:

```ts
protected resultTemplate(result : FileInfo, index : number) : TemplateResult
{
	return html`
        <et2-vfs-select-row .value=${result} ?disabled=${result.disabled}></et2-vfs-select-row>`;
}
```

Whatever you render should behave like a `SearchResultElement` - a `value`, and `selected` /
`current` / `disabled` the mixin can set - so its keyboard navigation and selection still work.

## When the host renders the results itself

If your widget already has somewhere to show results, suppress the mixin's list and use your own.
`Et2Select` does this because `sl-select` owns an option list already, so a second one would be a
duplicate:

```ts
protected searchResultsTemplate()
{
	return nothing;
}
```

`Et2Email` goes further and suppresses both templates, because its input is part of its combobox.
Its results are in `this._searchResults` for its own render to use.

## Reference

### Overrides

| Override | When |
|---|---|
| `searchResultSelected()` | always - only you know what a selection does to your value |
| `localSearch(search, options, localOptions)` | you have options on the client to search |
| `searchMatch(search, options, option)` | a local option matches on different terms |
| `remoteSearch(search, options)` | results come from somewhere none of the `searchUrl` forms covers |
| `processRemoteResults(results)` | the response needs converting first |
| `resultTemplate(result, index)` / `iconTemplate(option)` | a result should look different |
| `searchInputTemplate()` / `searchResultsTemplate()` | the host renders these itself |
| `_classSearchOptions` | request defaults `searchOptions` should still override |
| `static SEARCH_TIMEOUT` | a different debounce (default 500ms) |

### State you can read

| | |
|---|---|
| `searching` | a search is in flight |
| `resultsOpen` | the result list is showing |
| `hasFocus` | the widget has focus |
| `_searchResults` | the results, after merging and de-duplication |
| `_totalResults` | how many the server says exist |
| `selectedResults` | the result elements the user has chosen |
| `currentResult` | the one the keyboard is on |

Listen for the `et2-select` event on the widget to react to selection from outside, rather than only
inside `searchResultSelected()`.

### Two things that are easy to get wrong

- **`getValueAsArray()` defers to the host.** If the superclass has its own, that one wins - the
  mixin only supplies a fallback. `Et2WidgetWithSelectMixin`'s version keeps `""` when there is an
  `emptyLabel`, and shadowing it silently drops the empty option.
- **`_totalResults` is the server's count, not yours.** "n more" subtracts `_searchResults.length`,
  not however many rows are on screen - counting rendered rows includes your own local matches,
  which were never part of the server's total, and under-reports.

## Legacy responses

The mixin expects `{results: [...], total: n}`. Several older EGroupware endpoints send a bare array,
or an object with `total` mixed in among the results. `Et2Select/legacySearchResults.ts` converts
those and warns once per `searchUrl` when it has to, so the console shows which endpoints still need
updating. Reuse it if you point a widget at an older endpoint; a new one should just send the right
shape.

## Who uses it

- [`Et2TreeDropdown`](/components/et2-tree-dropdown/) - the straightforward case, renders both
  templates as they come.
- `Et2VfsSelectDialog` - its own result rows via `resultTemplate()`, and extra mime matching in
  `searchMatch()`.
- `Et2Select`, through `SelectSearchMixin` - suppresses `searchResultsTemplate()` and feeds
  `select_options` instead, and adds a `.json` `searchUrl` form for static option files.
- `Et2Email` - no local options at all, suppresses both templates, and opens its own `sl-popup`
  once the search lands.
