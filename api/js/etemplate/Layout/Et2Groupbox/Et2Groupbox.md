`et2-groupbox` draws a titled frame around a group of widgets - the classic HTML `<fieldset>` and
`<legend>` look. Use it to show that a handful of fields belong together.

It extends [`et2-details`](/components/et2-details) and forces itself open in the constructor, so it
is a details that never collapses. It inherits every Et2Details attribute, but the collapse-related
ones (`toggleOnHover`, `accordionGroup`, `hoist`, `overlaySummaryOnOpen`) have nothing to act on and
setting `open="false"` does not close it. The only attribute that is really its own is
`summaryInside`.

## Examples

### A titled group

`summary` is the title drawn into the top border.

```html:preview
<et2-groupbox summary="Delivery address">
    <et2-vbox>
        <et2-textbox label="Street"></et2-textbox>
        <et2-textbox label="City"></et2-textbox>
    </et2-vbox>
</et2-groupbox>
```

### Summary inside the box

`summaryInside` puts the title on its own line inside the frame instead of breaking the top border.
Use it when the title is long enough that it would swallow most of the border.

```html:preview
<et2-groupbox summary="Notification settings for this account" summaryInside>
    <et2-vbox>
        <et2-checkbox>Email</et2-checkbox>
        <et2-checkbox>Popup</et2-checkbox>
    </et2-vbox>
</et2-groupbox>
```

### Grouping several boxes

Groupboxes stack like any other block widget, so a form of related sections is just several of them.

```html:preview
<et2-groupbox summary="Who">
    <et2-textbox label="Name"></et2-textbox>
</et2-groupbox>
<et2-groupbox summary="When">
    <et2-date label="Date"></et2-date>
</et2-groupbox>
```

### Use et2-details if it should collapse

A groupbox is deliberately always open. If the point is to hide the group until it is wanted, that is
[`et2-details`](/components/et2-details) - same widget family, same `summary`, but it opens and
closes.

```html:preview
<et2-groupbox summary="Groupbox - always open">
    <et2-description value="No toggle."></et2-description>
</et2-groupbox>
<et2-details summary="Details - collapses">
    <et2-description value="Has a toggle."></et2-description>
</et2-details>
```
