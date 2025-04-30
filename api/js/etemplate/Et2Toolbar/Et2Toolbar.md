The user can choose which inputs are shown. Inputs that do
not fit are hidden. If the toolbar has an ID, the user's choice is remembered as a preference.

```html:preview
<et2-toolbar>
    <et2-button-icon image="balloon" label="Balloon"></et2-button-icon>
    <et2-button-icon image="text-left">Left</et2-button-icon>
    <et2-button-icon image="text-center">Center</et2-button-icon>
    <et2-button-icon image="text-right">Right</et2-button-icon>
    
    <sl-button-group label="History">
        <et2-button-icon id="undo" image="arrow-counterclockwise" label="Undo"></et2-button-icon>
        <et2-button-icon id="redo" image="arrow-clockwise" label="Redo"></et2-button-icon>
    </sl-button-group>

    <sl-button-group label="Formatting">
        <et2-button-icon id="bold" image="type-bold" label="Bold"></et2-button-icon>
        <et2-button-icon id="italic" image="type-italic" label="Italic"></et2-button-icon>
        <et2-button-icon id="underline" image="type-underline" label="Underline"></et2-button-icon>
    </sl-button-group>
</et2-toolbar>
```

## Children

Toolbar is a container like Box, so it can have any child. Some children do not make sense, but most inputs will work.

The value of any child widgets will be returned if the form is submitted.

```html:preview

<et2-toolbar id="child_example" preferenceApp="docs">
    <et2-button-icon id="add" image="plus" label="Add"></et2-button-icon>
    <et2-searchbox id="search"></et2-searchbox> 
    <!--
    <et2-button-toggle id="notification" icon="notification" label="Notification></et2-button-toggle>
    -->
    <et2-button-toggle id="notification" label="Notification"></et2-button-toggle>
    <et2-switch id="database" label="Delete database"></et2-switch>
    <et2-switch-icon id="holidays" label="Have holidays" onIcon="check" offIcon="x"></et2-switch-icon>
    <et2-dropdown-button label="Some checkboxes">
        <et2-checkbox id="check_a" label="Checkbox A"></et2-checkbox>
        <et2-checkbox id="check_b" label="Checkbox B"></et2-checkbox>
        <et2-checkbox id="check_c" label="Checkbox C"></et2-checkbox>
    </et2-dropdown>
</et2-toolbar>

```

## Actions

Any actions set for a toolbar will be turned into inputs. Toolbar will not have a context menu.
If the form is submitted, the ID of the last action will be returned under `<toolbar#id>["action"]`