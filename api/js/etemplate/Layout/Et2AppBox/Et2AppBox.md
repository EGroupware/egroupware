# Et2AppBox

`et2-app-box` is a minimal application component for etemplate apps.

It is intentionally simpler than `egw-app` / `EgwFrameworkApp`, but keeps enough behaviour for legacy app loading and
refresh flows.

:::warning
prefer `egw-app` (`EgwFrameworkApp`) as part of `kdots` for full framework behaviour.
:::

## Behavior

- Unconditional slot-based layout (all slots always present)
- Minimal header actions:
    - filter button
    - reload button
- Loading support:
    - `load(url)`
    - iframe fallback loading
- Refresh support:
    - `refresh(_msg, _id, _type)`
- Event listeners:
    - `load`, `clear`, `et2-search-result`, `et2-show`

## Intentionally Not Included

- Split panel / collapse behaviour
    - `showLeft()/hideLeft()/showRight()/hideRight()` are no-ops
- Tab hide/show splitter logic
- Sidebox/menu handling
    - `setSidebox()` is intentionally a no-op
- Application menu
    - No direct access to preferences, categories, etc.

## Examples

:::warning
The examples show the standard URLs for simplicity, but the application sidebox must be stopped and the DOM
target of the etemplate must be changed to avoid conflict with the normal application tab
:::

### Basic

Shows infolog app

```xml

<et2-app-box
        name="infolog"
        url="/egroupware/index.php?menuaction=infolog.infolog_ui.index&amp;ajax=true">
</et2-app-box>
```

### Add filemanager into right slot of infolog

```xml

<template id="infolog.index">
    <template template="infolog.index.header" slot="main-header"></template>
    <nextmatch id="nm" template="infolog.index.rows" span="all"/>
    <et2-app-box
            slot="right"
            url="/egroupware/index.php?menuaction=filemanager.filemanager_ui.index&amp;ajax=true"
            name="filemanager"
    >
    </et2-app-box>
</template>
```

### Same slots as egw-app

```xml

<et2-app-box
        name="calendar"
        url="/egroupware/index.php?menuaction=calendar.calendar_uiviews.index&amp;ajax=true">
    <et2-button-icon slot="header-actions" name="bank"></et2-button-icon>
</et2-app-box>
```

More about [`egw-app` slots](https://github.com/EGroupware/egroupware/wiki/Framework#slots)
