The user can choose which inputs are shown. Inputs that do
not fit are hidden.

```html:preview
<et2-toolbar>
    <et2-button-icon image="balloon" label="Balloon"></et2-button-icon>
    <et2-button-icon image="text-left">Left</et2-button-icon>
    <et2-button-icon image="text-center">Center</et2-button-icon>
    <et2-button-icon image="text-right">Right</et2-button-icon>
    
    <sl-button-group label="History">
        <sl-button id="undo"><sl-icon name="arrow-counterclockwise" label="Undo"></sl-icon></sl-button>
        <sl-button id="redo"><sl-icon name="arrow-clockwise" label="Redo"></sl-icon></sl-button>
    </sl-button-group>

    <sl-button-group label="Formatting">
        <sl-button id="bold"><sl-icon name="type-bold" label="Bold"></sl-icon></sl-button>
        <sl-button id="italic"><sl-icon name="type-italic" label="Italic"></sl-icon></sl-button>
        <sl-button id="underline"><sl-icon name="type-underline" label="Underline"></sl-icon></sl-button>
    </sl-button-group>
</et2-toolbar>
```

## Children

Toolbar is a container like Box, so it can have any child. Some children do not make sense, but most inputs will work.

```html:preview

<et2-toolbar id="child_example" preferenceApp="docs">
    <et2-button-icon id="add" image="plus" label="Add"></et2-button-icon>
    <!--
    <et2-searchbox id="search"></et2-searchbox>
    <et2-button-toggle id="notification" icon="notification" label="Notification></et2-button-toggle>
    <et2-switch id="database" label="Delete database"></et2-switch>
    <et2-switch-icon id="holidays" label="Have holidays" onIcon="check" offIcon="x"></et2-switch-icon>
    <et2-dropdown>
        <et2-checkbox id="check_a" label="Checkbox"></et2-checkbox>
        <et2-checkbox id="check_b" label="Checkbox"></et2-checkbox>
        <et2-checkbox id="check_c" label="Checkbox"></et2-checkbox>
    </et2-dropdown>
    -->
</et2-toolbar>

```

## Actions

Any actions set for a toolbar will be turned into inputs. Toolbar will not have a context menu.
