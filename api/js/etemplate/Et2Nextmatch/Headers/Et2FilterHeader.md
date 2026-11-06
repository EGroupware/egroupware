## Overview

`et2-nextmatch-header-filter` is a selectbox in a column header: choosing an option filters the
[Nextmatch](/components/et2-nextmatch) to matching rows.

It is [`et2-select`](/components/et2-select) with `FilterMixin` wrapped around it, so **every
`et2-select` attribute applies here** - `select_options`, `multiple`, `search`, `emptyLabel` and the
rest. The mixin adds only the list behaviour: find the surrounding Nextmatch, and on change call its
`applyFilters()` so the rows reload. See
[the header family](/components/et2-nextmatch-header#how-a-filter-header-works) for how the variants
relate.

Two defaults differ from a plain select, both chosen for sitting in a cramped header: the dropdown is
hoisted so it escapes the header's overflow, and it is clearable so the user can get back to "no
filter".

:::warning
These examples are not live. A filter header reports itself to a Nextmatch and reloads it on change,
and a Nextmatch needs a server to fetch rows from, which this documentation site does not have.
:::

## Usage

The id is the filter's name, which is what arrives server-side in the column filters.

```xml
<et2-nextmatch-header-filter id="status" emptyLabel="All"></et2-nextmatch-header-filter>
```

`emptyLabel` is worth setting on nearly every filter header - it is the "no filter applied" choice,
and without it the empty option has no label to explain itself.

## Options

Options come from the same places as any select: from the server in `sel_options`, or declared in
the template.

```xml
<et2-nextmatch-header-filter id="status" emptyLabel="All">
    <option value="open">Open</option>
    <option value="done">Done</option>
</et2-nextmatch-header-filter>
```

To let the user pick several values at once, `multiple` works here as it does on
[`et2-select`](/components/et2-select) - the filter then matches any of the chosen values.
