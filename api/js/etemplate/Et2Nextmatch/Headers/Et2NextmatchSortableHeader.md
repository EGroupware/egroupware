## Overview

`et2-nextmatch-sortheader` is a [column header](/components/et2-nextmatch-header) the user can click
to sort the list by that column. It is the header you want on most columns that hold a value worth
ordering by.

:::warning
These examples are not live. Sorting is a server operation - the header asks its
[Nextmatch](/components/et2-nextmatch) to re-fetch rows in the new order - and this documentation
site has no server to fetch from.
:::

## Usage

Give it the column's label; the id is the field the list sorts on.

```xml
<et2-nextmatch-sortheader id="name" label="Name"></et2-nextmatch-sortheader>
```

## Sort direction

`sortmode` carries the current direction, and is one of:

| Value | Meaning |
|---|---|
| `""` | not sorted by this column |
| `asc` | ascending |
| `desc` | descending |

The Nextmatch sets it as the user clicks, so a column shows an arrow when it is the one being sorted
by. Setting it in the template is how you express a default sort order:

```xml
<et2-nextmatch-sortheader id="modified" label="Last changed" sortmode="desc"></et2-nextmatch-sortheader>
```

Only one column is sorted at a time - sorting by a second column clears the first.
