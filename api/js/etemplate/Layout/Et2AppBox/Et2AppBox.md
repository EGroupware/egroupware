# Et2AppBox

`et2-app-box` is a minimal application component for etemplate apps.

It is intentionally simpler than `egw-app` / `EgwFrameworkApp`, but keeps enough behaviour for legacy app loading and
refresh flows.

:::warning
prefer `egw-app` (`EgwFrameworkApp`) as part of `kdots` for full framework behaviour.
:::

## Behaviour

- Unconditional slot-based layout (all slots always present)
- Minimal header actions:
    - filter button
    - reload button
- Loading support:
    - `load(url)`
- Refresh support:
    - `refresh(_msg, _id, _type)`
- Event listeners:
    - `load`, `clear`, `et2-search-result`, `et2-show`

## Intentionally Not Included

- No iframe fallback loading, urls must end with "&ajax=true"
- Split panel / collapse behaviour
    - `showLeft()/hideLeft()/showRight()/hideRight()` are no-ops
- Tab hide/show splitter logic
- Sidebox/menu handling
    - `setSidebox()` is intentionally a no-op
- Application menu
    - No direct access to preferences, categories, etc.

## Examples

:::warning
If loading an existing application into an AppBox, the DOM target of the etemplate _must_ be changed to something unique
to avoid conflict with the normal application tab.

EGroupware applications are not expected to work perfectly inside an
Et2AppBox and may generate errors or unexpected behaviour.

```php
// Overriding the DOM ID for filemanager
class filemanager_appbox extends filemanager_ui
{
    public static function get_view()
    {
        return array(new filemanager_appbox(), 'listview');
    }
    function listview(array $content = null, $msg = null)
    {
        $this->etemplate = $this->etemplate ? 
            $this->etemplate : new \EGroupware\Api\Etemplate(static::LIST_TEMPLATE);
        // Override the target DOM ID from "filemanager.index", doesn't matter what it is
        $this->etemplate->set_dom_id('appboxtest');
        return parent::listview($content, $msg);
    }
}
```

```xml
<!-- template example using overridden DOM ID -->
<et2-app-box
        url="/egroupware/index.php?menuaction=filemanager.filemanager_appbox.index&amp;ajax=true"
        name="filemanager"
>
</et2-app-box>
```
:::

### Basic

Shows infolog app

```xml

<et2-app-box
        name="infolog"
        url="/egroupware/index.php?menuaction=infolog.infolog_custom.index&amp;ajax=true">
</et2-app-box>
```

### Add filemanager into right slot of infolog

```xml

<template id="infolog.index">
    <template template="infolog.index.header" slot="main-header"></template>
    <nextmatch id="nm" template="infolog.index.rows" span="all"/>
    <et2-app-box
            slot="right"
            url="/egroupware/index.php?menuaction=filemanager.filemanager_custom.index&amp;ajax=true"
            name="filemanager"
    >
    </et2-app-box>
</template>
```

### Same slots as egw-app

```xml

<et2-app-box
        name="calendar"
        url="/egroupware/index.php?menuaction=calendar.calendar_uicustom.index&amp;ajax=true">
    <et2-button-icon slot="header-actions" name="bank"></et2-button-icon>
</et2-app-box>
```

More about [`egw-app` slots](https://github.com/EGroupware/egroupware/wiki/Framework#slots)
