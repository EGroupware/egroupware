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
