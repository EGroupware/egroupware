## Overview

`et2-customfields-filters` renders an application's custom fields as filter controls, for the filter
area of a list. It is the third context of the family described on
[`et2-customfields`](/components/et2-customfields), alongside the editable widget and the read-only
[list](/components/et2-customfields-list).

:::warning
The examples here are not live. The widget renders from customfield definitions the server supplies
for a particular application, and this documentation site has no server, so a preview would show an
empty widget whatever it was given.
:::

## Not every field can be filtered

Unlike the other two widgets, this one does not render every visible field. A filter has to offer a
fixed set of choices to be useful, so the widget keeps only fields it can turn into a selection:

- select-style fields are rendered as filter selectboxes
- a field whose type is an installed application is also kept, and filters by entry from that app
- everything else - free text, dates, and so on - is skipped
- filemanager fields are excluded

So a field appearing in the edit dialog but not in the filters is expected, not a fault. If a field
should be filterable, its customfield type has to change.

Beyond that it shares the same visibility contract as the rest of the family, documented on
[`et2-customfields`](/components/et2-customfields#choosing-which-fields-appear): `fields`,
`exclude`, `typeFilter` and `tab` all apply here too.

## Usage

```xml
<et2-customfields-filters id="customfields"></et2-customfields-filters>
```

Inside a `kdots` application, filters usually reach the framework through the filter slot rather
than being placed directly - see [Et2Filterbox](/components/et2-filterbox), which describes how a
list's filters are assembled.
