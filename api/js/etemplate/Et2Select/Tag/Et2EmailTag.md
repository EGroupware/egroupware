## Examples

A [tag](/components/et2-tag) for one email address. It is what [et2-email](/components/et2-email)
renders each address as - the recipients of a mail, the participants of an event - and what it
adds over a plain tag is that it knows the difference between an address and a person.

Two things follow from that. It can show the address in whatever form is most readable, because
`"Ralf Becker" <rb@example.org>` carries a name that does not need to be shown alongside the
address. And on hover it asks the server whether that address belongs to a contact: if it does,
the tag shows the contact's avatar and clicking it opens the contact; if it does not, it shows an
add icon that opens a new contact with the address already filled in.

:::warning
The contact lookup is the half that needs a server, and this documentation site has none. The
previews below render and format correctly, but the avatar / add-contact icon at the front of each
tag stays a spinner permanently: the lookup is a single batched request, and when it fails nothing
resolves the tag's pending prefix, so the spinner has no timeout and no error state to fall back
to. In an application the request answers and you get an avatar or an add icon instead.
:::

### Display format

`emailDisplay` decides what is shown. The address itself is unchanged - it is only the label that
gets shorter.

- `full` - `Ralf Becker <rb@example.org>`
- `name` - `Ralf Becker`
- `domain` - `Ralf Becker (example.org)`, the default
- `email` - `rb@example.org`

`domain` is the default because it is the compromise that matters in mail: you see who it is, and
you can still tell `rb@example.org` from `rb@example.com` at a glance.

```html:preview
<et2-email-tag value="Ralf Becker <rb@example.org>" emailDisplay="full"></et2-email-tag>
<et2-email-tag value="Ralf Becker <rb@example.org>" emailDisplay="name"></et2-email-tag>
<et2-email-tag value="Ralf Becker <rb@example.org>" emailDisplay="domain"></et2-email-tag>
<et2-email-tag value="Ralf Becker <rb@example.org>" emailDisplay="email"></et2-email-tag>
```

When the address carries no name there is nothing to shorten to, so every format but `full` falls
back to the address.

```html:preview
<et2-email-tag value="rb@example.org" emailDisplay="name"></et2-email-tag>
```

The tag's own default is `domain`, but a tag inside an [et2-email](/components/et2-email) is
handed the field's format instead, and that one starts from the user's *Email display* preference
- so a user who wants to see full addresses sees them everywhere without any template saying so.

### Hovering over a known contact

Nothing to set - it is what the widget does. The full address is always the tag's `title`, so the
part that was shortened away is still one hover from being readable.

```html
<et2-email-tag value="Ralf Becker <rb@example.org>"></et2-email-tag>
```

### Without the contact integration

`contactPlus` is on by default and turns off the hover behaviour. It has to be set as a property,
not an attribute: it is a boolean that defaults to true, and a boolean attribute counts as set
whatever its value, so `contactPlus="false"` in a template switches it *on*.

```js
this.et2.getWidgetById("tag").contactPlus = false;
```

### Removable

As on any tag, `removable` adds the x and fires `sl-remove` rather than removing anything itself.
[et2-email](/components/et2-email) is what normally passes it in.

```html:preview
<et2-email-tag id="email-tag-remove" value="Ralf Becker <rb@example.org>" removable></et2-email-tag>
<script>
    const emailTag = document.getElementById("email-tag-remove");
    emailTag.addEventListener("sl-remove", () => {emailTag.remove();});
</script>
```

### In an email field

What you would actually write:

```html
<et2-email id="to" multiple label="To"></et2-email>
```
