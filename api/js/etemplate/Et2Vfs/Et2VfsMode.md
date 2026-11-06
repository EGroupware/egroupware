## When to use it

`<et2-vfs-mode>` turns a numeric unix file mode into the `drwxr-xr-x` string `ls -l` would print. It
is a pure formatter - it takes the number the VFS stores and makes it readable. There is nothing to
edit and no way to change permissions through it.

## Examples

### What it does

The value is the raw mode; the widget shows the permission string. The same string is also the
element's tooltip, so a narrow column stays readable.

```html:preview
<table>
    <thead>
        <tr><th align="left">value</th><th align="left">octal</th><th align="left">shown as</th></tr>
    </thead>
    <tbody>
        <tr><td>33188</td><td>100644</td><td><et2-vfs-mode value="33188"></et2-vfs-mode></td></tr>
        <tr><td>33261</td><td>100755</td><td><et2-vfs-mode value="33261"></et2-vfs-mode></td></tr>
        <tr><td>16877</td><td>40755</td><td><et2-vfs-mode value="16877"></et2-vfs-mode></td></tr>
        <tr><td>33152</td><td>100600</td><td><et2-vfs-mode value="33152"></et2-vfs-mode></td></tr>
        <tr><td>41471</td><td>120777</td><td><et2-vfs-mode value="41471"></et2-vfs-mode></td></tr>
    </tbody>
</table>
```

The first character is the file type, taken from the same `S_IFMT` bits the C library uses: `-` for
a regular file, `d` for a directory, `l` for a symlink, and `s`, `p`, `c` or `b` for the rarer kinds.

```html:preview
<et2-vfs-mode value="24996"></et2-vfs-mode>
```

That is `0o60644`, a block device. The type is matched against the whole `S_IFMT` field rather than
by testing single bits, which matters here: a block device's bits contain a character device's, so a
per-bit test reports the wrong type for it.

### Special permission bits

set-UID, set-GID and the sticky bit each replace the execute character of their own triplet.
Following `ls`, the replacement is lowercase when that execute bit is set too and uppercase when it
is not - so the letter tells you the special bit is on, and its case tells you whether the file is
actually executable.

```html:preview
<table>
    <thead>
        <tr><th align="left">octal</th><th align="left">bit</th><th align="left">shown as</th></tr>
    </thead>
    <tbody>
        <tr><td>104755</td><td>set-UID, owner executable</td><td><et2-vfs-mode value="35309"></et2-vfs-mode></td></tr>
        <tr><td>104655</td><td>set-UID, not owner executable</td><td><et2-vfs-mode value="35245"></et2-vfs-mode></td></tr>
        <tr><td>102755</td><td>set-GID, group executable</td><td><et2-vfs-mode value="34285"></et2-vfs-mode></td></tr>
        <tr><td>41777</td><td>sticky, world executable</td><td><et2-vfs-mode value="17407"></et2-vfs-mode></td></tr>
        <tr><td>41776</td><td>sticky, not world executable</td><td><et2-vfs-mode value="17406"></et2-vfs-mode></td></tr>
        <tr><td>107777</td><td>all three at once</td><td><et2-vfs-mode value="36863"></et2-vfs-mode></td></tr>
    </tbody>
</table>
```

:::warning
The server-side `Api\Vfs::int2mode()` still reports a block device as `c`, so a permission string
rendered by PHP and one rendered by this widget disagree for that one file type. The special bits
themselves agree.
:::

### In a row

The usual use is a filemanager column. The widget accepts the whole row object as well as a bare
number, and picks `mode` out of it - so binding it to the row is enough.

```xml
<et2-vfs-mode id="${row}[mode]"/>
```

### Nothing to show

An empty, missing or non-numeric value renders nothing at all, rather than a row of dashes that
would look like a real set of permissions.

```html:preview
<table>
    <tbody>
        <tr><td>a real mode</td><td><et2-vfs-mode value="33188"></et2-vfs-mode></td><td>&larr; shows</td></tr>
        <tr><td>an empty value</td><td><et2-vfs-mode value=""></et2-vfs-mode></td><td>&larr; empty</td></tr>
        <tr><td>no value at all</td><td><et2-vfs-mode></et2-vfs-mode></td><td>&larr; empty</td></tr>
        <tr><td>not a number</td><td><et2-vfs-mode value="rw-r--r--"></et2-vfs-mode></td><td>&larr; empty</td></tr>
    </tbody>
</table>
```

### From javascript

`Et2VfsMode.formatMode()` is the same conversion as a static method, for when you need the string
outside a widget - in a tooltip, a confirmation message, a log line.

```js
import {Et2VfsMode} from "../../api/js/etemplate/Et2Vfs/Et2VfsMode";

Et2VfsMode.formatMode(33188);        // "-rw-r--r--"
Et2VfsMode.formatMode({mode: 16877}); // "drwxr-xr-x"
```
