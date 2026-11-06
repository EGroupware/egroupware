## When to use it

`et2-tree` shows hierarchical data the user can expand, collapse and select from - a folder tree, a
category hierarchy, a set of nested groups.

If you want a tree the user picks *from* in a compact control rather than a panel, use
[`et2-tree-dropdown`](/components/et2-tree-dropdown), which puts this inside a dropdown.

## Options

Like a select, a tree takes its nodes from `select_options`. Each node is an object with at least an
id and a label, and children nest inside it:

```html:preview
<et2-tree id="tree-basic" label="Folders"></et2-tree>
<script>
    const tree = document.getElementById("tree-basic");
    tree.select_options = [
        {
            value: "inbox", label: "Inbox", open: true,
            children: [
                {value: "inbox/work", label: "Work"},
                {value: "inbox/personal", label: "Personal"}
            ]
        },
        {value: "sent", label: "Sent"},
        {value: "trash", label: "Trash"}
    ];
</script>
```

`open: true` on a node starts it expanded.

## Selection

The value is the id of the selected node. `multiple` lets the user select several, and the value
becomes an array.

```html:preview
<et2-tree id="tree-select" multiple></et2-tree>
<p>Selected: <span id="tree-select-output">nothing</span></p>
<script>
    const picker = document.getElementById("tree-select");
    const out = document.getElementById("tree-select-output");
    picker.select_options = [
        {value: "a", label: "Apples"},
        {value: "b", label: "Bananas"},
        {value: "c", label: "Cherries"}
    ];
    picker.addEventListener("change", () =>
    {
        out.textContent = JSON.stringify(picker.value) || "nothing";
    });
</script>
```

`leafOnly` restricts selection to nodes with no children, for when only a leaf is a meaningful
answer and a branch is just a grouping.

## Icons

`leafIcon`, `openIcon` and `collapsedIcon` set the default icons for nodes without their own. A node
can override them individually.

```html:preview
<et2-tree id="tree-icons"></et2-tree>
<script>
    const icons = document.getElementById("tree-icons");
    icons.leafIcon = "file-earmark";
    icons.openIcon = "folder2-open";
    icons.collapsedIcon = "folder";
    icons.select_options = [
        {value: "docs", label: "Documents", children: [{value: "docs/notes", label: "notes.txt"}]}
    ];
</script>
```

## Loading children on demand

A large tree does not have to be sent all at once. Mark a node with `child: 1` but give it no
children, and `autoloading` is asked for them the first time the user expands it.

`autoloading` takes either a menuaction to call, or a javascript function receiving the node and
returning a promise of `{children: [...]}`. The function form is the one to use when the caller
already has the data and does not want a round-trip:

```js
tree.autoloading = (item) => Promise.resolve({
    children: [{value: item.value + "/child", label: "Loaded on demand"}]
});
```

## Remembering what was expanded

`openStatePreference` takes an `app.prefName` string. When set, the tree restores which nodes were
expanded from that preference on load, and saves the current state back (debounced) whenever the
user opens or closes a node.

```xml
<et2-tree id="folders" openStatePreference="mail.folderTree"></et2-tree>
```

This needs a server to store the preference, so it does nothing on this documentation site.

## Events

`et2-click` fires when a node is clicked. Clicks on the expand/collapse arrow and on slotted content
are deliberately excluded, so you get the node the user meant rather than the twisty they aimed at.

`sl-expand` fires when a node expands, with the node's id and item in its detail.
