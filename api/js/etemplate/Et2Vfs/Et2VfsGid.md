## When to use it

`et2-vfs-gid` shows the group of a file or directory - the numeric gid from a VFS stat, rendered as
the group's name.

It is the same widget as [`et2-vfs-uid`](/components/et2-vfs-uid), which documents the family: both
are read-only, both render `0` as **root**, and both come from
[`et2-select-account`](/components/et2-select-account). Use that one if you need the user to pick a
value rather than read one.

:::warning
The examples here are not live. Turning a group id into a name is a server lookup, and this
documentation site has no server.
:::

## Usage

```xml
<et2-vfs-gid id="${row}[gid]"></et2-vfs-gid>
```
