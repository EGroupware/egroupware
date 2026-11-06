`et2-tabbox` shows one of several panels at a time, with a row of tabs to switch between them. It is
what every "Edit" dialog in EGroupware uses to keep General / Links / History apart.

A tabbox is made of three widgets: the tabbox itself, one [`et2-tab`](/components/et2-tab) per tab in
its `nav` slot, and one [`et2-tab-panel`](/components/et2-tab-panel) per panel. A tab is tied to its
panel by `panel="…"` matching the panel's `name="…"`.

In a template you do not write those two yourself. You list the tabs and the panel contents in
`<tabs>` and `<tabpanels>` child tags and the tabbox creates the widgets, pairing them up by
position:

```xml
<et2-tabbox id="tabs">
    <tabs>
        <tab id="general" label="General"/>
        <tab id="links" label="Links" statustext="Attachments and related entries"/>
        <tab id="history" label="History"/>
    </tabs>
    <tabpanels>
        <et2-template id="myapp.edit.general"/>
        <et2-template id="myapp.edit.links"/>
        <et2-template id="myapp.edit.history"/>
    </tabpanels>
</et2-tabbox>
```

The `id` on each `<tab>` is the panel name, the tabbox's own value, and the key the server uses to
hide a tab through `readonlys`. Give every tab one.

## Examples

### Tabs in plain HTML

Outside a template - in a test, or a hand-written page like this one - write the tabs and panels
directly. `slot="nav"` is what makes an `et2-tab` part of the tab row.

```html:preview
<et2-tabbox>
    <et2-tab slot="nav" panel="general" active>General</et2-tab>
    <et2-tab slot="nav" panel="links">Links</et2-tab>
    <et2-tab-panel name="general" active>
        <et2-textbox label="Subject"></et2-textbox>
    </et2-tab-panel>
    <et2-tab-panel name="links">
        <et2-description value="Nothing linked yet."></et2-description>
    </et2-tab-panel>
</et2-tabbox>
```

### The value is the open tab

A tabbox is an input widget whose value is the name of the panel currently showing. Setting `value`
switches tabs, so a template can open on a particular tab just by putting its id in the content
array.

```html:preview
<et2-tabbox id="tabs-value-example">
    <et2-tab slot="nav" panel="one" active>One</et2-tab>
    <et2-tab slot="nav" panel="two">Two</et2-tab>
    <et2-tab slot="nav" panel="three">Three</et2-tab>
    <et2-tab-panel name="one" active><et2-description value="First panel"></et2-description></et2-tab-panel>
    <et2-tab-panel name="two"><et2-description value="Second panel"></et2-description></et2-tab-panel>
    <et2-tab-panel name="three"><et2-description value="Third panel"></et2-description></et2-tab-panel>
</et2-tabbox>
<p>value: <span id="tabs-value-output"></span></p>
<et2-button id="tabs-value-button" label="Show the third tab"></et2-button>
<script>
    const tabs = document.getElementById("tabs-value-example");
    const out = document.getElementById("tabs-value-output");
    const show = () => {out.textContent = tabs.value;};

    tabs.addEventListener("sl-tab-show", show);
    document.getElementById("tabs-value-button").addEventListener("click", () => {tabs.value = "three";});
    customElements.whenDefined("et2-tabbox").then(() => tabs.updateComplete).then(show);
</script>
```

### Where the tabs sit

`placement` moves the tab row. `top` is the default; `start` and `end` put it down the side, which is
what the mobile framework switches to by default.

```html:preview
<et2-tabbox placement="start">
    <et2-tab slot="nav" panel="a" active>First</et2-tab>
    <et2-tab slot="nav" panel="b">Second</et2-tab>
    <et2-tab-panel name="a" active><et2-description value="Tabs on the left"></et2-description></et2-tab-panel>
    <et2-tab-panel name="b"><et2-description value="Second panel"></et2-description></et2-tab-panel>
</et2-tabbox>
```

The older `alignTabs` attribute did the same thing and is deprecated - use `placement`.

### Panel height

Without `tabHeight` the tabbox measures its first panel once and pins every panel to that height, so
the dialog does not jump around when you switch tabs. Give `tabHeight` a value to set it yourself, or
`auto` to let the panels flex to whatever their parent gives them.

```html:preview
<et2-tabbox tabHeight="8em">
    <et2-tab slot="nav" panel="short" active>Short</et2-tab>
    <et2-tab slot="nav" panel="long">Long</et2-tab>
    <et2-tab-panel name="short" active><et2-description value="One line."></et2-description></et2-tab-panel>
    <et2-tab-panel name="long">
        <et2-vbox>
            <et2-description value="Rather"></et2-description>
            <et2-description value="a lot"></et2-description>
            <et2-description value="more"></et2-description>
            <et2-description value="content"></et2-description>
        </et2-vbox>
    </et2-tab-panel>
</et2-tabbox>
```

### Reacting to a tab change

`sl-tab-show` and `sl-tab-hide` carry the panel name in `event.detail.name`. This is the hook for
loading a tab's data the first time it is opened.

```html:preview
<et2-tabbox id="tabs-event-example">
    <et2-tab slot="nav" panel="first" active>First</et2-tab>
    <et2-tab slot="nav" panel="second">Second</et2-tab>
    <et2-tab-panel name="first" active><et2-description value="Switch tabs"></et2-description></et2-tab-panel>
    <et2-tab-panel name="second"><et2-description value="and watch the log"></et2-description></et2-tab-panel>
</et2-tabbox>
<p>last: <span id="tabs-event-output">none</span></p>
<script>
    const out = document.getElementById("tabs-event-output");
    document.getElementById("tabs-event-example").addEventListener("sl-tab-show", (e) =>
    {
        out.textContent = "sl-tab-show " + e.detail.name;
    });
</script>
```

### Finding and opening the tab a widget is on

Validation has to be able to show the field it is complaining about even when the field is on a
closed tab. `activateTab(widget)` opens the tab containing a widget, and the static
`Et2Tabs.getTabPanel(widget)` tells you which panel a widget is on without opening anything - pass
`true` as the second argument for the tab's label instead of its id.

```js
// somewhere with a reference to the tabbox and the offending widget
tabbox.activateTab(widget);
```

### Adding tabs from the server

`extraTabs` takes an array of `{label, template}` objects and appends them to the tabs from the
template - or replaces them, if `addTabs` is not set. Each entry may also carry `id`, `hidden`,
`prepend` (`true` for the front, or the id of the tab to insert before) and `statustext`. This is how
custom fields and app plugins get their own tab.

```php
$tpl->setElementAttribute('tabs', 'addTabs', true);
$tpl->setElementAttribute('tabs', 'extraTabs', [
    ['label' => 'Notes', 'template' => 'myapp.edit.notes', 'id' => 'notes'],
]);
```
