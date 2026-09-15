## Overview

`et2-nextmatch-columnselection` is the column chooser behind a list: the panel where a user picks
which columns to show, reorders them by dragging, and sets how often the list refreshes itself.

You do not place it in a template. [`et2-datagrid`](/components/et2-datagrid) creates it and puts it
in the list header - it is the control that appears at the right-hand end of the header row - and
the datagrid owns its state. It is documented here because its behaviour is what a reader sees, not
because it is something to instantiate.

:::warning
These examples are not live. The chooser is driven by a datagrid's column state and persists the
user's choice as a preference, both of which need a server; on this documentation site there is
neither a list to configure nor anywhere to save the result.
:::

## What it is given

| Property | Contents |
|---|---|
| `columns` | the available columns, with their labels and current visibility |
| `value` | the selected columns |
| `autoRefresh` | the refresh interval in seconds, `0` for off |

`Et2DatagridColumnState` is the shape the datagrid builds to hand over - see
`Et2Datagrid/Et2DatagridColumnState.ts`, which exists specifically to produce what this widget
consumes.

## Custom fields

An application's custom fields appear in the chooser alongside its ordinary columns, and are stored
separately in the column preferences as `customFields: string[]` holding only the visible names. The
visibility rules are shared with the
[customfields widgets](/components/et2-customfields#choosing-which-fields-appear), so the chooser and
the rendered fields cannot disagree about what is shown.

## Reordering

Columns are reordered by dragging within the chooser. The order is part of what is saved, so a
user's arrangement survives reloading the list.

## Auto-refresh

The same panel carries the list's auto-refresh interval, because it is the place a user goes to
configure how the list behaves rather than what it contains. Setting it to `0` turns refreshing off.
Each tick reloads the list rather than patching individual rows.
