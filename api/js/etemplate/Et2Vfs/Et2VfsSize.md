## When to use it

`<et2-vfs-size>` turns a byte count into something a person can read - `1.23 MB` instead of
`1234567`. Use it anywhere a file size is shown: the filemanager's size column, an attachment list,
a quota display.

It is a pure formatter with no input of its own. Give it the number and it does the rest, in the
user's own locale - so the same widget prints `1.23 MB` for one user and `1,23 MB` for another.

## Examples

### What it does

```html:preview
<table>
    <thead>
        <tr><th align="left">bytes</th><th align="left">shown as</th></tr>
    </thead>
    <tbody>
        <tr><td>0</td><td><et2-vfs-size value="0"></et2-vfs-size></td></tr>
        <tr><td>512</td><td><et2-vfs-size value="512"></et2-vfs-size></td></tr>
        <tr><td>1536</td><td><et2-vfs-size value="1536"></et2-vfs-size></td></tr>
        <tr><td>1234567</td><td><et2-vfs-size value="1234567"></et2-vfs-size></td></tr>
        <tr><td>5368709120</td><td><et2-vfs-size value="5368709120"></et2-vfs-size></td></tr>
    </tbody>
</table>
```

### How long the unit is spelled out

`display` chooses between `short` (the default, `kB`), `long` (`kilobytes`) and `narrow` (`kB` with
no space). Long is worth it in a sentence; short is what you want in a table column.

```html:preview
<et2-vfs-size value="1234567" display="short"></et2-vfs-size> ·
<et2-vfs-size value="1234567" display="long"></et2-vfs-size> ·
<et2-vfs-size value="1234567" display="narrow"></et2-vfs-size>
```

### Bytes or bits

`unit` switches between `byte` (the default) and `bit`. Bits are for transfer rates, not file sizes.

```html:preview
<et2-vfs-size value="1536" unit="byte"></et2-vfs-size> ·
<et2-vfs-size value="1536" unit="bit"></et2-vfs-size>
```

### In a row

The widget accepts the whole VFS row object as well as a bare number and picks `size` out of it, so
binding it straight to the row works.

```xml
<et2-vfs-size id="${row}[size]"/>
```

### Nothing to show

A missing size renders nothing at all rather than `0 B`, so a row with no size stays blank instead
of claiming the file is empty. A real `0` still prints - an empty file is a fact worth showing.

```html:preview
zero: <et2-vfs-size value="0"></et2-vfs-size> ·
no value: <et2-vfs-size></et2-vfs-size> ·
(end of the line)
```

The same is true for `null`, `undefined` and non-numeric values assigned from javascript. Watch out
for an empty-string *attribute* though: `value=""` in markup is converted to a number before the
widget ever sees it, and so prints `0 byte` rather than nothing.

### From javascript

`humanFileSize()` is exported alongside the widget for the places that need a plain string rather
than a rendered element - a "file too large, maximum %1" message, for instance. It uses binary
prefixes (1 KB = 1024 B), which is what the server-side size limits are expressed in.

```js
import {humanFileSize} from "../../api/js/etemplate/Et2Vfs/Et2VfsSize";

humanFileSize(1536);    // "1.5 KB"
humanFileSize(1234567); // "1.2 MB"
```
