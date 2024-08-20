## Examples ##

### Spinners ###

To add up / down arrow buttons to change the value, set `step`.

```html:preview
<et2-number label="0.5 step" step="0.5"></et2-number>
```

### Precision ###

To enforce a certain number of decimal places, set `precision`.

```html:preview
<et2-number label="Two decimal places" precision="2" value="123.456"></et2-number>
<et2-number label="Integers only" precision="0" value="123.456"></et2-number>
```

### Number Format ###

Normally numbers use the user's number format for thousands and decimal separator from preferences, but it is possible
to specify for a particular number. The internal value is not affected.

```html:preview
<et2-number decimalSeparator="p" thousandsSeparator=" " value="1234.56"></et2-number>
```

### Minimum and Maximum ###

Limit the value with `min` and `max`

```html:preview
<et2-number min="0" label="Greater than 0"></et2-number>
<et2-number min="10" max="20" label="Between 10 and 20"></et2-number>
```

### Prefix & Suffix ###

Use `prefix` and `suffix` attributes to add text before or after the input field. To include HTML or other widgets, use
the `prefix` and `suffix` slots instead.

```html:preview
<et2-number prefix="$" value="15.46"></et2-number>
```

### Currency ###

Using `suffix`,`min` and `precision` together

```html:preview
<et2-number suffix="€" min="5.67" precision="2" label="Price"></et2-number>
```

