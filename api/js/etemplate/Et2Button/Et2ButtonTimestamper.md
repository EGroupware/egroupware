## Usage

A button that writes "date time username: " into another field at the cursor, so a user adding to
a running log - an InfoLog description, a note on a ticket - does not have to type the date and
their own name every time. It extends [Button](/components/et2-button) and sets `noSubmit`
itself, so clicking it only edits the target field.

`target` is the `id` of the widget to write into. Everything else is optional.

```html
<et2-textarea id="description" rows="5"></et2-textarea>
<et2-button-timestamp target="description" label="Timestamp"></et2-button-timestamp>
```

:::warning
Deliberately not a live example. The button looks the target up through the eTemplate widget tree
(`getRoot().getWidgetById()`) and it asks the server for the current user's full name when the
name is not already cached. This documentation site has neither, so a button here would render and
then do nothing at all when clicked. Everything below describes the real behaviour inside a
running EGroupware.
:::

## Where the text goes

The text is inserted at the cursor, not appended - so a user can put a new entry at the top of a
field that already has older ones. If the target is on another tab, that tab is activated first so
the user can see what happened, and a TinyMCE-backed field gets the text through the editor rather
than through the underlying textarea.

## Format

`format` is a date format string, in the same PHP-style notation the rest of EGroupware uses
(`Y-m-d H:i`); the username always follows it. Left
unset, it follows the user's own date and time format preferences, which is usually what you want.
`timezone` likewise defaults to the user's timezone preference.

```html
<et2-button-timestamp target="description" format="Y-m-d H:i" timezone="UTC"></et2-button-timestamp>
```
