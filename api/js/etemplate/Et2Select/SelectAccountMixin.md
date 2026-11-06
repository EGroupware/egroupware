## Overview

`SelectAccountMixin` lets a select hold account ids without being given the accounts up front.

An account list can be large, and a widget usually only needs the handful of accounts its value
actually refers to. So instead of shipping every account as an option, this mixin watches the value
and fills in whatever it does not recognise.

[`et2-select-account`](/components/et2-select-account) is what this produces, and
[`et2-vfs-uid`](/components/et2-vfs-uid) and [`et2-vfs-gid`](/components/et2-vfs-gid) build on that.

## Resolving names

When the value contains an id with no matching option, the mixin adds one immediately with a
placeholder label - the id followed by `...` - and asks the server for the real name through
`egw.link_title()`. When the answer arrives it replaces the placeholder in place.

Two consequences worth knowing:

- A widget briefly displays `123 ...` before settling on the name. That is the lookup in flight, not
  a broken value.
- The replacement is done by editing the existing option rather than rebuilding the list, to avoid
  disturbing Lit's markers in the rendered output.

Lookups are batched, so a value with several ids costs one request rather than one per id.

## Which accounts can be chosen

Resolving names is separate from *offering* accounts to pick from. That list comes from the widget's
own `accountType` (accounts, groups, or both) and the `account_selection` preference, which can
restrict a user to the accounts they share a group with, or to none at all.

So a widget can display an account it would not have let you choose - which is correct: an existing
value must still be readable.
