## What it is for

The application selector out of [`<et2-link-entry>`](/components/et2-link-entry/): a select whose
options are the applications that can be linked to. It is rarely used on its own - it exists so
`<et2-link-entry>`, `<et2-link-to>` and `<et2-link-add>` can all share one selector - but it is a
normal select, so you can put it in a template when you need "pick an application" on its own.

Its options are not configured anywhere. They come from the link registry: every application that
registered a `query` handler with the linking system shows up, which is exactly the set of
applications you can search for an entry in. See
[Choosing a link widget](/components/et2-link/#choosing-a-link-widget) for the rest of the family.

:::warning
The examples below are not live. Before it can render, the selector reads the user's `link_app`
preference to pick a default, and looks up the current application (`egw.app_name()`) to fall back
on - this documentation site has no current application, so the widget never gets as far as
rendering. Everything below describes the behaviour against a running EGroupware.
:::

## Examples

### All linkable applications

The plain widget lists everything registered with the linking system, plus one extra option:
`url`, a pseudo-application for linking an arbitrary external address. No real application is
registered for it, so it is added here by hand and carries its own `http` icon.

```html
<et2-link-apps id="app"></et2-link-apps>
```

The selected application is remembered: choosing one writes the user's `link_app` preference, and
that is what a fresh selector starts on next time.

### Icons or names

`appIcons` defaults to true, which is why the selector in a link entry is a narrow strip of
application icons rather than a list of names. Turn it off to get the names.

```html
<et2-link-apps id="app" appIcons="false"></et2-link-apps>
```

### A fixed set of applications

`applicationList` takes a comma separated list and offers only those.

```html
<et2-link-apps id="app" applicationList="addressbook,infolog"></et2-link-apps>
```

### Just one application

`onlyApp` is not the same thing as a one-element `applicationList`: there is nothing left to choose,
so the widget hides itself entirely and simply reports that application as its value. That is how
`onlyApp` on an `<et2-link-entry>` makes the selector disappear and leaves only the search box.

```html
<et2-link-apps id="app" onlyApp="infolog"></et2-link-apps>
```
