## Overview

`Et2WidgetWithSelectMixin` is the common ground for every widget that offers the user a list to
choose from. It owns the options themselves, so a select, a listbox, a tree and a dropdown button all
take their options the same way.

It is deliberately below [`SelectSearchMixin`](/mixins/selectsearchmixin) in the chain - this mixin
is about *having* options, that one is about searching them.

## Options

`select_options` is the list. Each entry is an object with at least `value` and `label`; other keys
(an icon, a class, nested `children`) are used by whichever widget is rendering them.

Options reach a widget from three places, and they are merged rather than replacing one another:

| Source | Typical use |
|---|---|
| `sel_options` sent by the server | the normal case for a real template |
| `<option>` children in the `.xet` | a short fixed list written into the template |
| assigning `select_options` from javascript | building a list at runtime |

:::warning
`<option>` children only work in a `.xet` template. They are read when the template is parsed, not
from live DOM, so writing them in plain HTML - as in a documentation example - produces a widget with
no options at all. Assign `select_options` instead.
:::

## The empty option

`emptyLabel` adds an option meaning "nothing chosen" and gives it a label. It is worth setting on
almost any optional select: without it the empty choice is either absent or blank, and a user cannot
tell whether blank means "none" or "not loaded".

```xml
<et2-select id="status" emptyLabel="All"></et2-select>
```

The empty option's value is `""`, and a widget with an `emptyLabel` treats `""` as a legitimate value
rather than as "no value".

## Finding an option

`optionSearch()` looks a value up in the list, descending into `children` so it works on nested
options as well as flat ones. Use it rather than scanning `select_options` by hand - a tree's options
are not a flat array.

`set_select_options()` is the legacy setter kept for older code; new code assigns `select_options`
directly.
