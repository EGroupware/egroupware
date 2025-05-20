```html:preview
<et2-favorites>
</et2-favorites>
```

## Examples

### Filtering favourites

Favourites are handled at the application level, but sometimes you want to limit which favourites are shown for
complicated applications.
You can filter the favourites by listening for the `et2-load` event and modifying the list. Make sure the listener is
bound before the widget is loaded so you catch the event.

```ts
document.body.querySelector("form#projectmanager-list").addEventListener("et2-load", (e) =>
{
	// Make sure the target is a favourite, other things fire et2-load
	if(e.target instanceof Et2Favorites)
	{
		this.filterFavourites(e.detail);
	}
});

function filterFavourites(favouriteList)
{
	Object.keys(favouriteList).forEach(favouriteName =>
	{
		// Remove any favourites that don't have a "u" in the name
		if(!favouriteName.includes("u"))
		{
			delete favouriteList[favouriteName];
		}
	})
}
```

A more complicated example using the favourites setting in nextmatch:

```php
// PHP:

$content['nm'] => [
    ...
    // Set favourites filter.  N.B. that this will be included in the favourite
    'favorites' => ['sub_filter' => 'sub_a']
]
```

```ts
// app.ts:
constructor()
{

	// ...

	document.body.querySelector("form#projectmanager-list").addEventListener("et2-load", (e) =>
	{
		// Make sure the target is a favourite, other things fire et2-load
		if(e.target instanceof Et2Favorites)
		{
			this.filterFavourites(e.target.filters?.sub_filter ?? "", e.detail);
		}
	});
}

function filterFavourites(sub_filter, favouriteList)
{
	Object.keys(favouriteList).forEach(favouriteName =>
	{
		// Filter to match parent nextmatch settings, only showing matches
		if(favouriteList[favouriteName].state?.favourites?.sub_filter !== sub_filter)
		{
			delete favouriteList[favouriteName];
		}
	});
}
```
