# Et2 Customfields Header

`et2-nextmatch-header-customfields` shows custom fields as sortable headers in a Nextmatch list.

Use it when a Nextmatch should let users sort by visible custom fields and manage those fields through column selection.
The header uses the custom field labels configured for the application, so users see familiar names such as
`Project`, `Region` or `Contract type` instead of technical field ids.

## Examples

### Basic header

Add the custom fields header to the Nextmatch header row where custom field columns should appear.

```html
<et2-nextmatch-header-customfields label="Custom fields"></et2-nextmatch-header-customfields>
```

When visible custom fields are available, each one is shown as its own sort header. If no custom fields are visible,
the header shows the configured `label`.

### Keep the header compact

Use `max-visible-fields` to control how many custom field headers are shown directly. Extra fields are grouped under
the label and shown when the user hovers or focuses the header.

```html
<et2-nextmatch-header-customfields
	label="More fields"
	max-visible-fields="2"
></et2-nextmatch-header-customfields>
```

This is useful for applications with many optional custom fields, where showing every custom field in the header would
make the list hard to scan.

### Show selected custom fields

Custom field visibility is normally loaded from the Nextmatch column selection and saved preferences. You can also set
the initial selection in the template with `fields`.

```html
<et2-nextmatch-header-customfields
	label="Custom fields"
	fields="cf_project,cf_region"
></et2-nextmatch-header-customfields>
```

You can also pass a visibility map when creating or updating the header.

```js
header.setCustomfieldVisibility({
	cf_project: true,
	cf_region: true,
	cf_internal_note: false
});
```

Only fields set to `true` are displayed as sortable headers.

### Limit by custom field type

Use `type-filter` when the header should only offer certain kinds of custom fields.

```html
<et2-nextmatch-header-customfields
	label="Selectable fields"
	type-filter="select"
></et2-nextmatch-header-customfields>
```

## Usage Notes

- Custom fields use their configured labels in the header.
- Sorting works the same way as other Nextmatch sortable headers.
- Column selection controls which custom fields are visible.
- The label is used as a fallback and as the compact caption when there are many visible fields.
- Keep `max-visible-fields` low when the application has several optional custom fields.
