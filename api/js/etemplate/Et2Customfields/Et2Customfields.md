# Et2Customfields Widgets

## Purpose

These webcomponents provide the customfield visibility/filter state model used by
nextmatch customfield header and upcoming customfield rendering widgets.

## Components

- `et2-customfields` (`Et2Customfields`)
- `et2-customfields-list` (`Et2CustomfieldsList`)
- `et2-customfields-list-row` (`Et2CustomfieldsListRow`)
- `et2-customfields-filters` (`Et2CustomfieldsFilters`)

## Shared behavior

All components delegate field visibility decisions to `Et2CustomfieldsController`:

- explicit `fields` selection (object or CSV)
- `exclude` filtering
- `typeFilter` including legacy `"previous"` behavior
- `tab` filtering
- mode-specific defaults (`customfields-filters` starts visible for all fields)

The same controller contract is used by Nextmatch customfield header
(`et2-nextmatch-header-customfields`) to keep chooser/header visibility behavior aligned.

## List rendering

`et2-customfields-list` renders selected customfields as read-only Et2 widgets for
normal customfield list contexts.

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

## Current scope

This migration stage focuses on visibility/filter contracts and nextmatch
integration. Full customfield input widget generation is intentionally out of scope
for this stage.
