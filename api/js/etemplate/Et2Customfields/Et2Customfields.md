## Overview

`et2-customfields` renders an application's custom fields as editable widgets. You do not declare
the individual fields - the widget is given the application's customfield definitions and generates
a widget per field, choosing the tag and attributes from each field's configured type.

There are three widgets in this family, one per context:

| Widget | Renders |
|---|---|
| [`et2-customfields`](/components/et2-customfields) | editable fields, for an edit dialog |
| [`et2-customfields-list`](/components/et2-customfields-list) | the same fields read-only, for a Datagrid row |
| [`et2-customfields-filters`](/components/et2-customfields-filters) | the filterable ones as filter controls |

They share one visibility contract, described below, which the Nextmatch customfield header
([`et2-nextmatch-header-customfields`](/components/et2-nextmatch-header-customfields)) also uses so
that the column chooser and the rendered fields never disagree.

:::warning
The examples here are not live. Every one of these widgets renders from customfield definitions the
server supplies for a specific application, and this documentation site has no server - there are no
definitions to render, so a preview would show an empty widget whatever it was given.
:::

## Choosing which fields appear

Custom fields are visible by default. Each of the attributes below narrows that, and they combine:

| Attribute | Effect |
|---|---|
| `fields` | an explicit selection, as an object or a comma-separated list |
| `exclude` | removes named fields from whatever is otherwise visible |
| `typeFilter` | keeps only fields belonging to a given entry type |
| `tab` | keeps only fields assigned to a named tab |

```xml
<!-- every custom field, editable -->
<et2-customfields id="customfields"></et2-customfields>

<!-- just two of them -->
<et2-customfields id="customfields" fields="contract,region"></et2-customfields>

<!-- everything except one -->
<et2-customfields id="customfields" exclude="internal_note"></et2-customfields>

<!-- only the fields belonging to one tab of the edit dialog -->
<et2-customfields id="customfields" tab="details"></et2-customfields>
```

The decisions themselves live in `Et2CustomfieldsController`, so all four widgets answer "is this
field visible?" the same way. `typeFilter` also accepts the legacy `"previous"` value for
compatibility with older templates.

## Field type to widget

Which widget a field becomes is decided by `Et2CustomfieldWidgetMapper.ts`, which turns a field's
configured type into a tag name plus attributes. A date field becomes an `et2-date`, a selection
becomes an `et2-select`, and so on, in each of the four rendering contexts.

## Light DOM

`et2-customfields` and `et2-customfields-list` deliberately render their generated children into the
light DOM rather than a shadow root, because eTemplate's widget lookup, validation and event
handling all need to find those generated widgets in the normal tree. Their styles are rendered into
the light DOM for the same reason.

This matters if you are styling them: the usual shadow-DOM boundary is not there, so ordinary
selectors from your application's CSS do reach the generated fields.
