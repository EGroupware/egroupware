## Overview

`et2-nextmatch-header-account` filters a [Nextmatch](/components/et2-nextmatch) column by account -
"show me only this user's entries". It is the header to use for an owner, creator, assignee or any
other column holding an account id.

It is [`et2-select-account`](/components/et2-select-account) with `FilterMixin` wrapped around it, so
its own attributes are that widget's, and the mixin supplies the list behaviour. See
[the header family](/components/et2-nextmatch-header#how-a-filter-header-works).

Like the other filter headers it defaults to hoisted (so the dropdown escapes the header) and
clearable (so the user can return to no filter).

:::warning
These examples are not live. Account names come from the server, and the filter reloads a Nextmatch
that needs a server to fetch rows from - neither exists on this documentation site.
:::

## Usage

```xml
<et2-nextmatch-header-account id="owner" emptyLabel="All"></et2-nextmatch-header-account>
```

`accountType` controls who can be chosen, exactly as on
[`et2-select-account`](/components/et2-select-account) - accounts, groups, or both:

```xml
<et2-nextmatch-header-account id="owner" accountType="both" emptyLabel="Anyone"></et2-nextmatch-header-account>
```
