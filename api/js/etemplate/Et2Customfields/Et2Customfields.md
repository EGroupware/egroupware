# Et2Customfields Widgets

## Purpose

These webcomponents provide the customfields used by
Nextmatch customfield headers and render customfield widgets for editable, list,
filter, and Datagrid row contexts.

## Components

- `et2-customfields` (`Et2Customfields`)
- `et2-customfields-list` (`Et2CustomfieldsList`)
- `et2-customfields-list-row` (`Et2CustomfieldsListRow`)
- `et2-customfields-filters` (`Et2CustomfieldsFilters`)

## Shared behaviour

All components delegate field visibility decisions to `Et2CustomfieldsController`:

- explicit `fields` selection (object or CSV)
- `exclude` filtering
- `typeFilter` including legacy `"previous"` behavior
- `tab` filtering

Customfields default to visible unless a constraint such as `fields`, `exclude`,
`typeFilter`, or `tab` limits them.

The same controller contract is used by Nextmatch customfield header
(`et2-nextmatch-header-customfields`) to keep chooser/header visibility behaviour aligned.

## List rendering

`et2-customfields` renders editable customfields as generated Et2 widgets.

`et2-customfields-list` renders selected customfields as read-only Et2 widgets for
normal customfield list contexts.

`et2-customfields-filters` renders filter-eligible customfields as
selectbox-style filter controls. It skips non-select fields unless the field type
is an installed app type, and keeps filemanager excluded.

`et2-customfields` and `et2-customfields-list` intentionally render child widgets
in light DOM so eTemplate widget lookup, validation, and event paths can
discover generated child widgets. Their component styles are therefore also
rendered into light DOM.

`et2-customfields-list-row` is a datagrid row-only renderer. `Et2RowProvider`
rewrites row-template `et2-customfields-list` elements to this tag so rows render
plain text values without creating nested Et2 widgets. `Et2Datagrid` assigns:

- `customfields`: shared customfield metadata keyed by unprefixed field name
- `fields`: selected visibility keyed by unprefixed field name
- `value`: row values keyed by prefixed `#field_name`

## Preference contract

Datagrid column preferences persist customfield visibility as:

- `customFields: string[]`

Only visible customfield names are stored.

## Field type mapping

Field type-to-widget mapping lives in `Et2CustomfieldWidgetMapper.ts`. The mapper
normalizes customfield type settings into generated Et2 widget tag names
and attributes while keeping `Et2CustomfieldsListRow` as plain text rendering when possible for
Datagrid performance.
