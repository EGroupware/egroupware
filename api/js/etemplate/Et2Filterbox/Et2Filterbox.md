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
directly.

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