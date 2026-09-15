## Overview

`et2-customfields-list` is the read-only half of
[`et2-customfields`](/components/et2-customfields): the same custom fields, rendered as display
widgets rather than inputs. Its normal home is a Datagrid row template, where it shows an entry's
custom field values alongside the ordinary columns.

It shares the whole field-visibility contract - `fields`, `exclude`, `typeFilter`, `tab` - with the
editable widget, so a field that is hidden in the edit dialog is hidden in the list for the same
reason. That contract is documented once, on
[`et2-customfields`](/components/et2-customfields#choosing-which-fields-appear).

:::warning
The examples here are not live. The widget renders from customfield definitions the server supplies
for a particular application, and this documentation site has no server, so a preview would show an
empty widget whatever it was given.
:::

## In a row template

Declare it in the rows template like any other column widget:

```xml
<et2-customfields-list id="customfields"></et2-customfields-list>
```

`Et2Datagrid` recognises the element through `Et2RowProvider` and supplies it with three things per
row:

| Assigned | Contents |
|---|---|
| `customfields` | the shared field metadata, keyed by field name |
| `fields` | which fields are currently visible, keyed by field name |
| `value` | that row's values, keyed by the prefixed `#field_name` |

You do not set any of those yourself - the datagrid owns them, which is what keeps every row
consistent with the column chooser.

## Column preferences

Custom field visibility is stored in the datagrid's column preferences as `customFields: string[]`,
holding only the names of the visible fields. That is why toggling a custom field in the column
chooser persists the same way an ordinary column does.

## Light DOM

Like `et2-customfields`, this widget renders its generated children into the light DOM rather than a
shadow root, so eTemplate's widget lookup and event handling can reach them. Its styles go to the
light DOM for the same reason, which means your application's ordinary CSS selectors do reach the
generated fields.
