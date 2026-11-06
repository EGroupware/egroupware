## Examples

Users and groups. The list is not sent with the template - it comes from the browser's account
cache, capped at 100 entries, and typing searches the server for the rest. On an installation with
more accounts than that, the dropdown is a starting point rather than the whole list, so never
design a template around the whole list being present.

Each option shows the account's avatar, and the value is the numeric account id. Groups are
negative. Nothing has to be written in the template but the widget itself.

:::warning
The examples on this page are not live. Account names and the account list both come from the
server, and this documentation site has no server, so the widget would render an empty dropdown
here. Everything below describes the real behaviour against a running EGroupware.
:::

```xml
<et2-select-account id="owner" label="Owner"/>
```

### Users, groups, or both

`accountType` decides what the list holds. Changing it re-fetches, so it can be switched at runtime.

| `accountType` | |
|---|---|
| `accounts` | users only - the default |
| `groups` | groups only |
| `both` | users and groups |
| `owngroups` | only the groups the current user is in |

```xml
<et2-select-account id="owner" label="Owner" accountType="both"/>
```

### The account selection preference

How much of the account list a user may see is a preference, not a template decision, and this widget
obeys it. Under `primary_group` an `accountType` asking for groups is narrowed to the user's own
groups. Under `none` the local list is empty for everyone but an administrator, and the user has to
search for the account they want - which is why the widget always points `searchUrl` at the server
unless that preference is `none`.

The examples here are template markup rather than live previews: the account list comes from the
server, and this documentation site has no backend to answer with.
