## When to use it

`et2-merge-dialog` is the "merge into document" action: the user picks a template document, and the
server fills its placeholders from the selected entries and returns the result.

Put it where an app offers document merge - typically in a list's actions or an entry's toolbar. It
is the whole interaction, not a piece of one: the widget opens the document picker, runs the merge
and handles the download or the resulting email.

:::warning
The examples here are not live. Every step needs a server - listing the merge templates from the
VFS, running the merge itself, and sending the result - and this documentation site has none.
:::

## Usage

`application` says which app's entries are being merged, and `path` is the VFS directory holding
that app's merge templates:

```xml
<et2-merge-dialog application="addressbook" path="/templates/addressbook"></et2-merge-dialog>
```

The entries to merge are the ones currently selected in the list the action was invoked from - they
are not passed to the widget.

## Merge and send

The dialog can also send the merged document straight out as email, rather than downloading it. That
option is only offered once an email template is chosen; with a plain document template there is
nothing to send, so the button stays unavailable.

## Related

The templates themselves are ordinary documents with placeholders, stored in the VFS - see
[Filemanager](/reference/filemanager) for how that directory is reached, and
[`et2-vfs-select-dialog`](/components/et2-vfs-select-dialog) for the picker this opens.
