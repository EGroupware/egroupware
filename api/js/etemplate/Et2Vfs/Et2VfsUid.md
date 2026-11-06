## When to use it

`et2-vfs-uid` shows the owner of a file or directory - the numeric uid from a VFS stat, rendered as
the account's name. [`et2-vfs-gid`](/components/et2-vfs-gid) is the same widget for the group.

It is read-only, and is what you want in a file listing's owner column. To let someone *choose* an
account, use [`et2-select-account`](/components/et2-select-account), which this extends.

:::warning
The examples here are not live. Turning an account id into a name is a server lookup, and this
documentation site has no server - every id would render as itself.
:::

## Usage

```xml
<et2-vfs-uid id="uid"></et2-vfs-uid>
<et2-vfs-gid id="gid"></et2-vfs-gid>
```

In a row template the value comes from the stat, so nothing else is needed:

```xml
<et2-vfs-uid id="${row}[uid]"></et2-vfs-uid>
```

## uid 0 is root

A uid of `0` - or no value at all - renders as **root** rather than as an empty cell or a bare zero.
That is deliberate: `0` is a real, meaningful owner on a filesystem, and the account system has no
account with that id to look up.

This is worth knowing when reading a listing: "root" means owned by the system, not "owner unknown".
