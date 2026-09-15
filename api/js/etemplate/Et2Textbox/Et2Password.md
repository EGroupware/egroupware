## Examples

A password is a [textbox](/components/et2-textbox) whose characters are never shown while typing.
Everything Et2Textbox can do - `label`, `helpText`, `required`, `minlength`, `placeholder` - works
here too.

The examples on this page are plain HTML rather than a template, so they set `type="password"`
themselves. Read from a template, the widget sets it for you and you never write it.

### Basic

```html:preview
<et2-password type="password" label="Password" value="correct horse battery staple"></et2-password>
```

### Letting the user look

`viewable` adds an eye button that switches the field to plain text, for a password the user is
allowed to read back - a stored account password, not their own login.

```html:preview
<et2-password type="password" label="Stored password" value="correct horse battery staple" viewable></et2-password>
```

Use that name and no other. The server decides from the template's own attributes whether to send
the password to the client at all, and `viewable` is what it looks for - a field that shows an eye
button the server masked the value for reveals nothing but asterisks. `togglePassword` is an older
spelling of the same attribute, still accepted but deprecated, and Shoelace's inherited
`password-toggle` is ignored in templates for exactly that reason.

### Two fields, one password

Nothing links two password fields together on its own; templates ask for the password twice and
compare the two values server side. The second field is conventionally the first field's id with
`_2` appended, which is also the id "suggest password" looks for when it fills both at once.

```html:preview
<et2-password id="password-pair" type="password" label="Password"></et2-password>
<et2-password id="password-pair_2" type="password" label="Repeat password"></et2-password>
```

### Suggesting a password

`suggest` is the length of password to generate, and adds a button to the field that asks the
**server** for one. This documentation site has no backend, so a preview of it could only fail -
here is the markup:

```html
<et2-password label="Password" suggest="16"></et2-password>
```

When the suggestion arrives the field switches to plain text so the user can read what they have
been given, and a second field named `<id>_2` is filled with the same value.

### Encrypted passwords

`plaintext` (the default) means the value travels and is stored as typed. With `plaintext="false"`
the value is stored encrypted and reaches the client encrypted, so revealing it is not a matter of
changing the input type: the widget prompts for the user's own password and has the server decrypt
the stored value first.

### Browser autofill

Password managers fill anything that looks like a login form, which is wrong for a field that edits
somebody else's stored password. `autocomplete="new-password"` marks the field as not-a-login, and
the widget additionally keeps the input readonly until it is focused, so autofill has nothing to
write into.

```html:preview
<et2-password type="password" label="Password" autocomplete="new-password"></et2-password>
```

### Disabled and readonly

`disabled` greys the field out but keeps it in the DOM, `readonly` shows it without allowing any
change and submits nothing. Neither reveals the password.

```html:preview
<et2-password type="password" label="Disabled" value="secret" disabled></et2-password>
<et2-password type="password" label="Readonly" value="secret" readonly></et2-password>
```
