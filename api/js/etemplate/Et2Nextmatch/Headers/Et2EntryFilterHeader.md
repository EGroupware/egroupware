## Overview

`et2-nextmatch-header-entry` filters a [Nextmatch](/components/et2-nextmatch) column by a linked
entry - "show me only the rows attached to this contact", for instance.

It is [`et2-link-entry`](/components/et2-link-entry) with `FilterMixin` wrapped around it: the user
searches for an entry the way they would when linking one, and the chosen entry becomes the filter.
See [the header family](/components/et2-nextmatch-header#how-a-filter-header-works).

:::warning
These examples are not live. Searching for an entry is a server operation, and the filter reloads a
Nextmatch that needs a server to fetch rows from - neither exists on this documentation site.
:::

## Usage

```xml
<et2-nextmatch-header-entry id="contact_id" onlyApp="addressbook"></et2-nextmatch-header-entry>
```

`onlyApp` restricts the search to one application, which is usually what you want in a column that
holds entries of a single kind. Without it the user picks the application as well as the entry.

## Value

An entry is two pieces of information - which application, and which entry within it - so the value
is an object rather than a plain id:

```js
{app: "addressbook", id: "123"}
```

The header only filters once **both** parts are present; a half-made selection, where the user has
chosen an application but not yet an entry, reports no value and leaves the list unfiltered. That is
why picking an application on its own does not appear to do anything.
