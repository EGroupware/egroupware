## What it is for

The odd one out of the family: it does not show or create a link, it opens another application's
"add entry" dialog with the current entry already attached. Pick an application, press `+`, and you
get - for example - a new InfoLog whose Links tab already holds the entry you came from.

It is an [`<et2-link-apps>`](/components/et2-link-apps/) selector plus a button. Its value is the
entry to attach, in the same `{to_app, to_id}` shape [`<et2-link-to>`](/components/et2-link-to/)
uses, so giving it the same `id` as the `<et2-link-to>` on the same tab is enough to wire it up.

See [Choosing a link widget](/components/et2-link/#choosing-a-link-widget) for the rest of the
family.

:::warning
Not live. Pressing the button calls `egw.open()`, which needs a running EGroupware to open the
target application's add dialog in, and the application selector needs a current application to
default to. Neither exists on this documentation site. Everything below describes the behaviour
against a running EGroupware.
:::

## Examples

### On a Links tab

Give it the `id` the rest of the Links tab uses, and the server fills in which entry is being
edited.

```html
<et2-link-add id="link_to" span="all"></et2-link-add>
```

### Only one target application

`application` limits it to a single application - the selector then hides itself and only the button
is left, which is the right shape for a one-purpose "add a related X" button.

```html
<et2-link-add id="link_to" application="infolog"></et2-link-add>
```

`applicationList` offers a comma separated set of them instead.

### Reacting to the application change

The selector fires `change` when a different target application is picked, before anything is
opened, so a template can react to the choice:

```html
<et2-link-add id="link_add" onchange="app.projectmanager.element_add_app_change_handler"></et2-link-add>
```
