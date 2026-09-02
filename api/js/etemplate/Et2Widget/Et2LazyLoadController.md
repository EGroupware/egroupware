## What this is for

Some widgets kick off work - typically a server request - as soon as they're given
something to work with, whether or not anyone can currently see the result. Inside a
virtualized list that's a widget per row, so it's a request per row, all at once, most of
them for rows nobody has scrolled to yet. `Et2LazyLoadController` lets a widget hold that
work until it's actually worth doing: the host is connected and not hidden by CSS
anywhere up the tree, and, if you gave it one, some other condition of your choosing.

It doesn't touch rendering. It only decides *when* to call a function you give it.

## Basic use

```ts
export class MyWidget extends Et2Widget(LitElement)
{
	protected _lazyLoad = new Et2LazyLoadController(this, () => this._fetchData());

	set entryId(value)
	{
		this._entryId = value;
		if(!this._lazyLoad.ready)
		{
			// Nobody can see the answer yet - _fetchData() runs again once we're ready
			return;
		}
		this._fetchData();
	}

	protected _fetchData()
	{
		// ... send the request, update this.data, requestUpdate() ...
	}
}
```

`onReady` (the second constructor argument) can fire more than once - the host can be
hidden and shown again, or you can call `recheck()` / `force()` yourself - so write it to
be safe to call when there's nothing to do, the same way `_fetchData()` above would just
be a wasted call if `entryId` hadn't actually changed since the last successful fetch.
Et2LinkString's `get_links()` / `_loadDeferred()` (`api/js/etemplate/Et2Link/Et2LinkString.ts`)
is a real, working example of this pattern, including how to stay correct when the host
gets recycled for a different entry while a request is still in flight.

### Waiting for something else too

Pass a third argument to add a condition on top of visibility - e.g. "don't fetch until
the parent widget has finished loading":

```ts
protected _lazyLoad = new Et2LazyLoadController(
	this,
	() => this._fetchData(),
	() => this.parentWidget.loaded
);
```

There's no event for that condition - this controller has no way to know what it depends
on - so call `recheck()` from whatever code changes it:

```ts
parentWidgetFinishedLoading()
{
	this.loaded = true;
	this.childWidgets.forEach(child => child._lazyLoad.recheck());
}
```

### Forcing it

`force()` runs `onReady` right away, gates or no gates - for something like a print view
that needs every row's content whether or not it was ever scrolled into view. It's a
one-off bypass, not a standing override: `ready` still reports the real state afterward,
so if your `onReady` re-checks `ready` itself before doing anything (as `_loadDeferred()`
does), make sure that check accounts for being forced.

### Waiting on it instead of reacting to it

`whenReady` is the same event as `onReady`, as a `Promise` - useful for code that wants to
`await` the gates once instead of supplying a callback:

```ts
async printPreview()
{
	await this._lazyLoad.whenReady;
	// definitely ready now, whether it already was or we just waited for it
	return this._fetchData();
}
```

It resolves the moment `onReady` would be called, including via `recheck()` or `force()`.
Read it again for each new wait - it settles once, so it won't tell you about the *next*
time the host goes hidden and becomes ready again.

## `Et2LazyLoadController` vs. `until()`

Lit's [`until()`](https://lit.dev/docs/templates/directives/#until) directive and this
controller can look like they solve the same problem - both show a placeholder while
something loads - but they're deferring different things.

`until()` defers what gets **rendered**. The promise it's waiting on is already running by
the time `until()` sees it - `until()` just chooses what to put in the DOM until it
resolves:

```ts
render()
{
	// This request starts the moment render() runs - whether or not this widget, or the
	// row it's in, is even visible. until() only controls what's shown while it's pending.
	return html`${until(this._fetchData(), this._loadingTemplate())}`;
}
```

`Et2LazyLoadController` defers **starting the work at all**. Nothing is requested until
`ready` is true - a hidden row costs nothing, not even a request that gets thrown away:

```ts
render()
{
	// Nothing to await here - _fetchData() populates this.data and calls
	// requestUpdate() itself, once the controller decides it's worth running
	return html`${this.data ? this._dataTemplate() : this._loadingTemplate()}`;
}
```

They compose fine: use `Et2LazyLoadController` to decide *when* to kick off the request,
and `until()` (or a Lit `@state` + plain conditional, as above) to decide what to show
while that request - once it's actually running - is in flight. What you don't want is
`until()` on a promise that already started unconditionally; that's the exact cost this
controller exists to avoid.
