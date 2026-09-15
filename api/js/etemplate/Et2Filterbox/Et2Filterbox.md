```html:preview
<et2-filterbox id="filterbox-overview">
    <et2-vbox>
        <et2-date-range id="date" label="Date"></et2-date-range>
        <et2-searchbox id="search" label="Search"></et2-searchbox>
    </et2-vbox>
</et2-filterbox>
```

Filterbox shows a list of filters. Normally it pulls them from a nextmatch header & column filters, but you can put your
own filters inside it or point it at a custom template instead.

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

Add the `clearable` attribute to get a clear button whenever at least one filter has a value. The priority filter below
starts out set, so the button is there to begin with; clear both filters and it goes away again.

A filter can only be emptied if it has an empty value to go back to, so give a select an `emptyLabel` - without one it
falls back to its first option instead of clearing.

```html:preview
<et2-filterbox id="filterbox-clearable" clearable>
<et2-vbox>
    <et2-searchbox id="search" label="Search" class="et2-fixed-label"></et2-searchbox>
    <et2-select-priority id="priority" label="Priority" emptyLabel="Any" value="3"
                         class="et2-fixed-label"></et2-select-priority>
</et2-vbox>
</et2-filterbox>
```