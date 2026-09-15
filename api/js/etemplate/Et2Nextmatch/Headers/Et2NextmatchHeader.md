## Overview

`et2-nextmatch-header` is the plain column header for a
[Nextmatch](/components/et2-nextmatch) list: a label that sits above a column and does nothing else.
It is also the base the rest of the header family builds on.

You rarely write one directly. Headers come from the columns of the rows template a Nextmatch is
given, and the interesting ones are the variants that let the user *do* something to the column.

:::warning
These examples are not live. A header only has meaning inside a Nextmatch - it reports itself to the
list, and a filter header calls back into it to reload - and a Nextmatch needs a server to fetch
rows from, which this documentation site does not have.
:::

## The family

| Widget | What the user gets |
|---|---|
| [`et2-nextmatch-header`](/components/et2-nextmatch-header) | a label, nothing interactive |
| [`et2-nextmatch-sortheader`](/components/et2-nextmatch-sortheader) | click the label to sort by that column |
| [`et2-nextmatch-header-filter`](/components/et2-nextmatch-header-filter) | a selectbox that filters the list |
| [`et2-nextmatch-header-account`](/components/et2-nextmatch-header-account) | the same, choosing an account |
| [`et2-nextmatch-header-entry`](/components/et2-nextmatch-header-entry) | the same, choosing a linked entry |
| [`et2-nextmatch-header-custom`](/components/et2-nextmatch-header-custom) | the same, wrapping a widget you name |
| [`et2-nextmatch-header-customfields`](/components/et2-nextmatch-header-customfields) | the application's custom fields as sortable headers |

## How a filter header works

The four filter headers are all the same idea applied to different input widgets. `FilterMixin`
takes an ordinary input and adds the two things a Nextmatch column filter needs: it finds the
Nextmatch it lives in, and on change it calls that list's `applyFilters()` so the rows reload.

That is the whole abstraction, which is why the variants are so short - each is its own input widget
with the mixin wrapped round it:

```
et2-nextmatch-header-filter    = FilterMixin(Et2Select)
et2-nextmatch-header-account   = FilterMixin(Et2SelectAccount)
et2-nextmatch-header-entry     = FilterMixin(Et2LinkEntry)
et2-nextmatch-header-custom    = FilterMixin(<the widget you name>)
```

So a filter header's own attributes are the attributes of the widget it is built from. For
`et2-nextmatch-header-filter`, that means everything on [`et2-select`](/components/et2-select).

## Usage

Headers go in the rows template's column definitions, not in the Nextmatch element itself:

```xml
<template id="myapp.index.rows">
    <grid>
        <columns>
            <column/>
            <column/>
        </columns>
        <rows>
            <row class="th">
                <et2-nextmatch-sortheader id="name" label="Name"></et2-nextmatch-sortheader>
                <et2-nextmatch-header-filter id="status" emptyLabel="All"></et2-nextmatch-header-filter>
            </row>
            <row>
                <et2-description id="${row}[name]"></et2-description>
                <et2-description id="${row}[status]"></et2-description>
            </row>
        </rows>
    </grid>
</template>
```

See [Nextmatch](/reference/nextmatch) for how a rows template is put together, and
[Et2Filterbox](/components/et2-filterbox) for filters that live outside the column headers.
