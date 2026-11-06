## Overview

`SelectSearchMixin` adds searching to a select: a field inside the dropdown that filters the options,
and optionally fetches more of them from the server as the user types.

[`Et2Select`](/components/et2-select) extends it, so **every select widget has these properties** -
all 20-odd variants, from `et2-select-bool` to `et2-select-account`.

```ts
export class Et2Select extends SelectSearchMixin(FreeEntryMixin(Et2WidgetWithSelect)) { … }
```

## Turning it on

`search` adds the search field. On its own it filters the options the widget already has, entirely
in the browser:

```xml
<et2-select id="country" search></et2-select>
```

`searchUrl` makes it ask the server instead, which is what you want when the full list is too large
to send - an account list, or an entry search. It takes a menuaction, or a function receiving the
search text and returning a promise of options:

```xml
<et2-select id="contact" search searchUrl="addressbook.addressbook_ui.ajax_search"></et2-select>
```

`searchOptions` is an object passed along with each query, for whatever extra parameters the
server-side search needs.

## Free entries

`allowFreeEntries` lets the user keep text that matches no option, turning what they typed into a
value of its own. Without it, typing only ever selects an existing option and the text is discarded
once a choice is made.

This is the one case where a select genuinely blurs into a
[searchbox](/components/et2-searchbox) - see that page for which to reach for.

## Local vs remote

`localSearch()`, `remoteSearch()` and `searchMatch()` are the pieces an extending widget overrides to
change *how* matching works - for example to match on a field other than the label, or to
post-process what the server returned. `startSearch()` is the entry point that decides between them
based on whether `searchUrl` is set.
