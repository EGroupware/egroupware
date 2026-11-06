## Overview

`et2-nextmatch-header-custom` is the escape hatch of the
[header family](/components/et2-nextmatch-header): a column filter built from a widget you name,
for when none of the ready-made filter headers fits.

The other filter headers each wrap one fixed input - a select, an account select, a link entry. This
one wraps whatever you put in `widgetType`, so a column can be filtered by a date, a checkbox, a
textbox, or any other input widget.

:::warning
These examples are not live. A filter header reports itself to a Nextmatch and reloads it on change,
and a Nextmatch needs a server to fetch rows from, which this documentation site does not have.
:::

## Usage

`widgetType` is the tag name of the widget to use as the filter:

```xml
<et2-nextmatch-header-custom id="created" widgetType="et2-date"></et2-nextmatch-header-custom>
```

Attributes for that inner widget go in `widgetOptions` rather than on the header itself, since the
header's own attributes belong to the header:

```xml
<et2-nextmatch-header-custom id="created"
                             widgetType="et2-date"
                             widgetOptions='{"min": "2020-01-01"}'></et2-nextmatch-header-custom>
```

If `widgetType` is not set the header falls back to `et2-description`, which displays text and
filters nothing - so a custom header that appears inert is usually a missing or misspelled
`widgetType`.

Server-side modifications for the header's id can supply `widgetType` too, which is how an
application can decide the filter widget at runtime rather than fixing it in the template.
