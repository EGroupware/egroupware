## Examples

`et2-url-email` holds one email address. It is the only member of the url family that checks what
was typed while the user is still in the field, and its invoker button composes a message rather
than opening a page.

For a field that takes *several* addresses, with contact search and drag & drop, use
[et2-email](/components/et2-email) instead.

### Basic

The @ button composes a mail to the address - in EGroupware's mail app if the user has it, through
the browser's `mailto:` handler otherwise.

```html:preview
<et2-url-email label="Email" value="info@egroupware.org"></et2-url-email>
```

### What counts as valid

A bare address is valid, and so is a name in front of an address in angle brackets. A name
containing a comma has to be quoted, because an unquoted comma is how address lists are separated.
Type over these values and tab away to see the validation message.

```html:preview
<et2-url-email label="Plain address" value="info@egroupware.org"></et2-url-email>
<et2-url-email label="With a name" value="EGroupware GmbH <info@egroupware.org>"></et2-url-email>
<et2-url-email label="Quoted name" value="&quot;Becker, Ralf&quot; <rb@egroupware.org>"></et2-url-email>
<et2-url-email label="Not an address" value="info@egroupware"></et2-url-email>
```

The @ button is blocked while the address is invalid - it does nothing until the value is fixed.

### Readonly

Readonly addresses are `et2-url-email_ro`, which renders the address as clickable text that opens
the compose window.

```html:preview
<et2-url-email_ro value="info@egroupware.org"></et2-url-email_ro>
```

`emailDisplay` decides what that text says: `email` (the default) shows the address itself, `name`
shows the contact's name, `full` shows `Name <address>`, `domain` shows `Name (domain)` and
`preference` follows the user's own *emailTag* preference. Everything but `email` needs the contact
behind the address, which the widget fetches from the server, so those variants cannot be shown
here:

```html
<et2-url-email_ro value="info@egroupware.org" emailDisplay="domain"></et2-url-email_ro>
```
