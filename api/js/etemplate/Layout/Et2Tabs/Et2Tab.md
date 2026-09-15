`et2-tab` is one clickable label in a [`et2-tabbox`](/components/et2-tabbox)'s tab row. It is not a
standalone widget: it only does anything inside a tabbox, in the `nav` slot, paired with an
[`et2-tab-panel`](/components/et2-tab-panel).

Two attributes do the work:

- `panel` names the [`et2-tab-panel`](/components/et2-tab-panel) this tab shows. It has to match that
  panel's `name` exactly, or clicking the tab shows nothing.
- `active` marks the tab that is open. The tabbox keeps it in sync from then on; you only set it to
  choose which tab starts open.

`hidden` removes the tab from the row - that is what the server's `readonlys` array does to a tab it
does not want shown. `disabled` does the same: Shoelace would grey the tab out, but the eTemplate
base widget applies `display: none` to any disabled non-input widget, and that wins. Either way the
tab is gone, not greyed.

In a template you never write this tag. The tabbox builds one `et2-tab` per `<tab>` entry, so
`<tab id="general" label="General"/>` becomes `<et2-tab panel="general">General</et2-tab>`. Write it
by hand only outside a template.

## Examples

### Tabs in their tabbox

The label is the tab's content, not an attribute - anything can go in there, including an icon.

```html:preview
<et2-tabbox>
    <et2-tab slot="nav" panel="general" active>General</et2-tab>
    <et2-tab slot="nav" panel="links">
        <et2-image src="image"></et2-image>
        Links
    </et2-tab>
    <et2-tab slot="nav" panel="history" disabled>History</et2-tab>
    <et2-tab-panel name="general" active>
        <et2-description value="There is a third, disabled tab - it is not in the row at all."></et2-description>
    </et2-tab-panel>
    <et2-tab-panel name="links">
        <et2-description value="Links panel"></et2-description>
    </et2-tab-panel>
    <et2-tab-panel name="history">
        <et2-description value="Never reached"></et2-description>
    </et2-tab-panel>
</et2-tabbox>
```

### Clicks and double clicks

A tab takes `onclick` like any other widget, and adds `ondblclick` - EGroupware uses the double click
to toggle a maximised tab. Both run in addition to switching to the panel, they do not replace it.

```html:preview
<et2-tabbox>
    <et2-tab id="tab-click-example" slot="nav" panel="one" active>Double-click me</et2-tab>
    <et2-tab slot="nav" panel="two">Other</et2-tab>
    <et2-tab-panel name="one" active><et2-description value="Panel one"></et2-description></et2-tab-panel>
    <et2-tab-panel name="two"><et2-description value="Panel two"></et2-description></et2-tab-panel>
</et2-tabbox>
<p>clicks: <span id="tab-click-output">none</span></p>
<script>
    const tab = document.getElementById("tab-click-example");
    const out = document.getElementById("tab-click-output");
    tab.addEventListener("click", () => {out.textContent = "click";});
    tab.addEventListener("dblclick", () => {out.textContent = "dblclick";});
</script>
```
