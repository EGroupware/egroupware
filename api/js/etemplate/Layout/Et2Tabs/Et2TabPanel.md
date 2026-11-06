`et2-tab-panel` holds the content of one tab in a [`et2-tabbox`](/components/et2-tabbox). Like
[`et2-tab`](/components/et2-tab) it is not a standalone widget - on its own it is an ordinary,
permanently visible box.

- `name` is what an [`et2-tab`](/components/et2-tab)'s `panel` attribute points at. The pairing is by
  this string, not by document order, so a typo shows an empty tab.
- `active` marks the panel that is showing. The tabbox maintains it; set it only to pick the panel
  that starts open, and set it on the matching tab as well.
- `hidden` takes the panel out along with its tab.

The panel is a normal widget container, so it makes no namespace of its own and the widgets inside it
read from the same content as the rest of the template. In a template the tabbox creates one panel
per `<tabpanels>` child and puts that child inside it, which is why panel contents are usually a
single [`et2-template`](/components/et2-template).

An inactive panel is hidden, not removed: its widgets are in the DOM, are found by
`getWidgetById()`, and are submitted with the form. Only [`et2-template`](/components/et2-template)
content that has not been fetched yet is genuinely absent.

## Examples

### Panels in their tabbox

```html:preview
<et2-tabbox>
    <et2-tab slot="nav" panel="address" active>Address</et2-tab>
    <et2-tab slot="nav" panel="contact">Contact</et2-tab>
    <et2-tab-panel name="address" active>
        <et2-vbox>
            <et2-textbox label="Street"></et2-textbox>
            <et2-textbox label="City"></et2-textbox>
        </et2-vbox>
    </et2-tab-panel>
    <et2-tab-panel name="contact">
        <et2-vbox>
            <et2-textbox label="Phone"></et2-textbox>
            <et2-textbox label="Email"></et2-textbox>
        </et2-vbox>
    </et2-tab-panel>
</et2-tabbox>
```

### A hidden panel is still there

The second panel below starts closed, but its textbox already exists and can be read and written
before anyone opens the tab.

```html:preview
<et2-tabbox>
    <et2-tab slot="nav" panel="visible" active>Visible</et2-tab>
    <et2-tab slot="nav" panel="closed">Closed</et2-tab>
    <et2-tab-panel name="visible" active>
        <et2-button id="panel-peek-button" label="Read the other panel"></et2-button>
        <et2-description id="panel-peek-output"></et2-description>
    </et2-tab-panel>
    <et2-tab-panel name="closed">
        <et2-textbox id="panel-peek-input" value="written before you looked"></et2-textbox>
    </et2-tab-panel>
</et2-tabbox>
<script>
    const input = document.getElementById("panel-peek-input");
    const out = document.getElementById("panel-peek-output");
    document.getElementById("panel-peek-button").addEventListener("click", () =>
    {
        out.value = input.value;
    });
</script>
```
