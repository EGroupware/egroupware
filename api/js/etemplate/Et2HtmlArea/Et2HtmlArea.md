## Examples

### Toolbar Mode vs Preference

The `mode` property controls whether the editor uses one of the built-in
toolbar presets or the user's `rte_toolbar` preference.

- `mode="simple"` uses the simple preset
- `mode="extended"` uses the extended preset
- `mode="advanced"` uses the advanced preset
- `mode="ascii"` renders a plain textarea
- empty `mode` uses the `rte_toolbar` preference

If you want the editor to follow the user's selected toolbar features, leave
`mode` empty.

```html:preview
<et2-htmlarea label="Preference-driven toolbar"></et2-htmlarea>
```

If you need a fixed toolbar regardless of the user preference, set `mode`
explicitly.

```html:preview
<et2-htmlarea label="Simple preset" mode="simple"></et2-htmlarea>
```

To hide the toolbar entirely, use `noToolbar`.

```html:preview
<et2-htmlarea label="No toolbar" noToolbar></et2-htmlarea>
```

### Plain Text Mode

`mode="ascii"` renders a textarea instead of TinyMCE when editable, while
preserving the same value, disabled, placeholder, focus, input, and change
contracts as rich text mode.

```html:preview
<et2-htmlarea label="Plain text" mode="ascii" placeholder="Enter plain text"></et2-htmlarea>
```

### Readonly Rendering

Readonly htmlareas render their value directly instead of initializing TinyMCE
or a textarea. Rich text values are rendered as HTML; `mode="ascii"` values are
rendered as literal text.

The lightweight `et2-htmlarea_ro` element is registered for readonly widget
substitution, including nextmatch detached row updates.

### File Selection And Uploads

Dropped and pasted images upload through EGroupware's HTMLArea upload endpoint
by default. Set `image-upload` to a VFS target widget id, content path, absolute
path, or external URL when the upload destination needs to be explicit.

TinyMCE's image, media, and file dialogs use the EGroupware VFS picker unless a
custom `filePickerCallback` property is assigned.

# Legacy Compatibility

The component keeps a small compatibility bridge while older integrations move
off direct TinyMCE access:

- `tinymce` resolves with the TinyMCE editor array
- `editor` exposes the active TinyMCE editor
- `file_picker_callback`, `images_upload_handler`, `valid_children`, and
  `toolbar_mode` map to their modern camelCase properties

New integrations should prefer the component properties plus
`addToolbarItem()` and `registerEditorSetupHook()` for editor UI extensions.
