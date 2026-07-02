
## Overview

### Named Template Mode

Use the `template` attribute when you already have a classic eTemplate rows template:

```xml
<et2-nextmatch id="nm" template="addressbook.index.rows"></et2-nextmatch>
```

The row/header structure is read from that template and converted for `et2-datagrid`.

### Slotted Template Mode

(WIP) When `template` is not set, `et2-nextmatch` reads slotted child markup from its light DOM.

```xml
<et2-nextmatch id="nm">
    <et2-box slot="header">Custom toolbar / filters / actions above grid</et2-box>

    <tr class="th" slot="columns">
        <et2-nextmatch-sortheader id="n_family" label="Name"></et2-nextmatch-sortheader>
        <et2-nextmatch-header id="note" label="Note"></et2-nextmatch-header>
    </tr>

    <tr class="$class $cat_id" slot="row">
        <et2-description id="${n_family}" noLang="1"></et2-description>
        <et2-textarea id="${note}" readonly="true" noLang="1"></et2-textarea>
    </tr>

    <tr slot="loader">
        <td colspan="2">
            <sl-skeleton effect="sheen" style="width:100%"></sl-skeleton>
        </td>
    </tr>
</et2-nextmatch>
```

### Shared Template Details

Full details for columns, rows, expression syntax, wrapper behaviour, and loader slots are documented in:

- [Et2Datagrid](/components/et2-datagrid)

## Notes

- If both a `template` attribute and slotted templates are provided, `template` wins.
- `setRows()` can preload initial rows; otherwise rows are fetched through the bound Nextmatch data provider.
