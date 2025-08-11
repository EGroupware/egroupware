```html:preview
<et2-filterbox clearable></et2-filterbox>
<script>
const filter = document.querySelector('et2-filterbox');
debugger;
filter.filters = [
{label: "Type", type: "et2-date-range"},
{label: "Search", type: "et2-searchbox"}
];
</script>
```

Filterbox shows a list of filters. Normally it pulls them from a nextmatch header & column filters but it's possible to
provide a list or a custom template.

## KDots Framework

Inside an `egw-app`, use the `filter` slot to override the automatic filters instead of using a `et2-filterbox`
directly. The framework will create a filterbox with our desired attributes.

```xml

<overlay>
    <template id="myapp.index.filter">
        <et2-vbox>
            My custom filters
            <et2-select-priority id="priority" label="Custom priority filter" class="et2-fixed-label"></et2-select-priority>
            <et2-select-dow id="day" label="Day of week" class="et2-fixed-label"></et2-select-dow>
        </et2-vbox>
    </template>
    <template id="myapp.index">
        <et2-template template="myapp.index.filters" slot="filter"></et2-template>
        <!-- Rest of template goes as normal -->
        <nextmatch/>
    </et2-template>
</overlay>
```

## Examples

### Filter groups

Set `data` with `groupName` on the nextmatch header to override the automatic filter grouping and create a custom group

```xml

<template id="myapp.index.rows">
    <grid width="100%">
        <columns><!-- ... --></columns>
        <rows>
            <row class="th">
                <!--- ... -->
                <et2-nextmatch-header-account id="owner" emptyLabel="Owner" accountType="both" data="groupName:People"/>
                <et2-nextmatch-header-account id="responsible" emptyLabel="Responsible" accountType="both" data="groupName:People"/>
            </row>
        </rows>
    </grid>
</template>
```

### Custom filters

You can put in custom content instead of providing a list or reading a nextmatch.

```html:preview
<et2-filterbox>
<et2-vbox>
    My custom filters
    <et2-select-priority id="priority" label="Custom priority filter" class="et2-fixed-label"></et2-select-priority>
    <et2-select-dow id="day" label="Day of week" class="et2-fixed-label"></et2-select-dow>
</et2-vbox>
</et2-filterbox>
```

### Autoapply

Use `autoapply` when you want each filter change to be handled separately instead of waiting for the 'Apply' button.

```html:preview
<et2-filterbox autoapply>
<et2-vbox>
    Autoapply
    <et2-select-priority id="priority" label="Custom priority filter" class="et2-fixed-label"></et2-select-priority>
    <et2-select-dow id="day" label="Day of week" class="et2-fixed-label"></et2-select-dow>
</et2-vbox>
</et2-filterbox>
```

### Clearable

Add the `clearable` attribute to add a clear button when at least one filter has content.

```html:preview
<et2-filterbox clearable>
<et2-vbox>
    Clearable
    <et2-select-priority id="priority" label="Custom priority filter" class="et2-fixed-label"></et2-select-priority>
    <et2-select-dow id="day" label="Day of week" class="et2-fixed-label"></et2-select-dow>
</et2-vbox>
</et2-filterbox>
```