## Select or searchbox?

A select hands back the value of the option the user chose. If what you want is the text they typed,
you want [et2-searchbox](/components/et2-searchbox) instead - the two are compared in
[Searchbox or select?](/components/et2-searchbox).

## Where the options come from

A select is only as good as its list, and the list can arrive three different ways. They are merged,
not exclusive, so a widget with a built-in list (most of the `et2-select-*` variants below) still
accepts extra options from either of the other two.

In an eTemplate, the usual source is the `sel_options` array the server sends with the content. The
widget finds its own entry by `id`, so nothing has to be written in the template at all:

```php
$sel_options['priority'] = ['1' => 'low', '2' => 'normal', '3' => 'high'];
```

For a short fixed list that never changes, `<option>` children in the `.xet` file are less work than
a server array. The tag's text is the label, and it is translated:

```xml
<et2-select id="priority" label="Priority">
	<option value="1">low</option>
	<option value="2">normal</option>
	<option value="3">high</option>
</et2-select>
```

From javascript - and in every example on this page, since there is no server here - assign an array
of `{value, label}` objects to `select_options`.

## Examples

### Options

Each option needs at least a `value` and a `label`.

```html:preview
<et2-select id="select-basic" label="Priority"></et2-select>
<script>
    document.getElementById("select-basic").select_options = [
        {value: "1", label: "Low"},
        {value: "2", label: "Normal"},
        {value: "3", label: "High"}
    ];
</script>
```

### Value

`value` is the chosen option's `value`, and changing it fires `change`. A value that is not in the
list is not kept, since there would be no label to show for it: on the first render the widget falls
back to the empty option if there is one, and to the first option if there is not. That is why a
select with no `emptyLabel` never sits there showing nothing.

```html:preview
<et2-select id="select-value" value="2"></et2-select>
<p>Value: <span id="select-value-output">?</span></p>
<script>
    const select = document.getElementById("select-value");
    const selectOutput = document.getElementById("select-value-output");
    select.select_options = [
        {value: "1", label: "Low"},
        {value: "2", label: "Normal"},
        {value: "3", label: "High"}
    ];
    const showValue = () => {selectOutput.textContent = JSON.stringify(select.value);};

    select.addEventListener("change", showValue);
    customElements.whenDefined("et2-select").then(() => select.updateComplete).then(showValue);
</script>
```

### Empty label

`emptyLabel` adds a first option whose value is `""`, for "no choice made". Give it a word that says
what empty means here - "All" in a filter, "None" in a form - rather than leaving the reader to
guess.

```html:preview
<et2-select id="select-empty" emptyLabel="All"></et2-select>
<script>
    document.getElementById("select-empty").select_options = [
        {value: "1", label: "Low"},
        {value: "2", label: "Normal"},
        {value: "3", label: "High"}
    ];
</script>
```

### Multiple

`multiple` lets the user pick more than one. The value becomes an array, and each choice is shown as
a tag.

```html:preview
<et2-select id="select-multiple" multiple></et2-select>
<p>Value: <span id="select-multiple-output">?</span></p>
<script>
    const multi = document.getElementById("select-multiple");
    const multiOutput = document.getElementById("select-multiple-output");
    multi.select_options = [
        {value: "phone", label: "Phone"},
        {value: "email", label: "Email"},
        {value: "fax", label: "Fax"},
        {value: "post", label: "Post"}
    ];
    multi.value = ["email", "post"];
    const showMulti = () => {multiOutput.textContent = JSON.stringify(multi.value);};

    multi.addEventListener("change", showMulti);
    customElements.whenDefined("et2-select").then(() => multi.updateComplete).then(showMulti);
</script>
```

### Searching

`search` adds a field that filters the list as the user types. It is worth adding once the list is
longer than a screen, and pointless below about a dozen options.

```xml
<et2-select id="cat_id" label="Category" search="true"/>
```

`search`, `searchUrl`, `searchOptions` and `allowFreeEntries` all come from
[SelectSearchMixin](/mixins/selectsearchmixin/), which documents them in full - including how to
have the server supply more options as the user types, so the list never has to be sent in one
piece. Several widgets on this page turn `search` on for themselves because their lists are always
long: [et2-select-country](/components/et2-select-country) and
[et2-select-account](/components/et2-select-account) among them.

### Free entries

`allowFreeEntries` turns text that matches no option into an option of its own, so the value can be
something you never offered. Type a name and press Enter or comma.

```html:preview
<et2-select id="select-free" multiple allowFreeEntries></et2-select>
<p>Value: <span id="select-free-output">?</span></p>
<script>
    const free = document.getElementById("select-free");
    const freeOutput = document.getElementById("select-free-output");
    free.select_options = [
        {value: "red", label: "Red"},
        {value: "green", label: "Green"},
        {value: "blue", label: "Blue"}
    ];
    free.addEventListener("change", () => {freeOutput.textContent = JSON.stringify(free.value);});
</script>
```

### Editing a free entry

`editModeEnabled` lets the user correct a tag they typed by double-clicking it, instead of removing
it and typing the whole thing again. It only applies to free entries, so `allowFreeEntries` has to
be on too.

```html:preview
<et2-select id="select-edit" multiple allowFreeEntries editModeEnabled></et2-select>
<script>
    const edit = document.getElementById("select-edit");
    edit.select_options = [{value: "red", label: "Red"}, {value: "green", label: "Green"}];
    edit.value = ["mauve"];
</script>
```

### Placeholder, help text and clearing

`placeholder` is a hint shown while nothing is selected - unlike `emptyLabel` it is not an option, so
it cannot be chosen. `helpText` explains the field below it, and `clearable` adds an X to get back to
no selection.

The help text's attribute is `help-text`, not `helpText`. Both work from a `.xet` template, where
eTemplate maps the camelCase name for you; plain HTML like the preview below only has the real
attribute.

```html:preview
<et2-select id="select-extras" label="Priority" placeholder="Not set yet"
            help-text="How soon this needs doing" clearable></et2-select>
<script>
    document.getElementById("select-extras").select_options = [
        {value: "1", label: "Low"},
        {value: "2", label: "Normal"},
        {value: "3", label: "High"}
    ];
</script>
```

### Icons and titles

An option can carry an `icon`, using the same image names as
[et2-image](/components/et2-image), and a `title` that becomes its tooltip.

```html:preview
<et2-select id="select-icons" value="email"></et2-select>
<script>
    document.getElementById("select-icons").select_options = [
        {value: "phone", label: "Phone", icon: "telephone", title: "Call them"},
        {value: "email", label: "Email", icon: "envelope", title: "Write to them"},
        {value: "post", label: "Post", icon: "printer", title: "Print and mail it"}
    ];
</script>
```

### Groups

An option with a `children` array becomes a heading with its own options under it, rather than a
selectable option itself - so do not leave the value to default, or it lands on a heading that
cannot be displayed.

```html:preview
<et2-select id="select-groups" value="email"></et2-select>
<script>
    document.getElementById("select-groups").select_options = [
        {
            value: "electronic", label: "Electronic", children: [
                {value: "phone", label: "Phone"},
                {value: "email", label: "Email"}
            ]
        },
        {
            value: "paper", label: "On paper", children: [
                {value: "fax", label: "Fax"},
                {value: "post", label: "Post"}
            ]
        }
    ];
</script>
```

### Disabled and readonly

`disabled` means "not right now" and can be turned off again from javascript. `readonly` shows the
value without allowing any change and submits nothing. See
[Disabled vs Readonly vs Hidden](/getting-started/widgets#disabled-vs-readonly-vs-hidden).

```html:preview
<et2-select id="select-disabled" value="2" disabled></et2-select>
<et2-select id="select-readonly" value="2" readonly></et2-select>
<script>
    const options = [
        {value: "1", label: "Low"},
        {value: "2", label: "Normal"},
        {value: "3", label: "High"}
    ];
    document.getElementById("select-disabled").select_options = options.slice();
    document.getElementById("select-readonly").select_options = options.slice();
</script>
```

## Variants

Most of the time you do not want a plain `et2-select` at all, but one of the variants that brings its
own list. They all take everything on this page - only the list, and the one or two attributes that
shape it, are different.

| | |
|---|---|
| [et2-select-access](/components/et2-select-access) | private / group public / global public |
| [et2-select-account](/components/et2-select-account) | users and groups |
| [et2-select-app](/components/et2-select-app) | installed applications |
| [et2-select-bitwise](/components/et2-select-bitwise) | a set of flags stored as one integer |
| [et2-select-bool](/components/et2-select-bool) | yes / no |
| [et2-select-cat](/components/et2-select-cat) | categories, as a tree |
| [et2-select-country](/components/et2-select-country) | countries, with flags |
| [et2-select-day](/components/et2-select-day) | day of the month, 1 - 31 |
| [et2-select-dow](/components/et2-select-dow) | days of the week |
| [et2-select-hour](/components/et2-select-hour) | hour of the day |
| [et2-select-lang](/components/et2-select-lang) | installed languages |
| [et2-select-month](/components/et2-select-month) | January - December |
| [et2-select-number](/components/et2-select-number) | a range of numbers |
| [et2-select-percent](/components/et2-select-percent) | 0% - 100% |
| [et2-select-priority](/components/et2-select-priority) | low / normal / high |
| [et2-select-state](/components/et2-select-state) | states or provinces of one country |
| [et2-select-tab](/components/et2-select-tab) | an application, or one of its tabs |
| [et2-select-thumbnail](/components/et2-select-thumbnail) | images, shown as thumbnails |
| [et2-select-timezone](/components/et2-select-timezone) | timezones |
| [et2-select-year](/components/et2-select-year) | years around this one |
