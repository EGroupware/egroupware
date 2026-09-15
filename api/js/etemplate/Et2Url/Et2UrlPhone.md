## Examples

`et2-url-phone` holds a telephone number, and its ✆ button dials it. The field itself accepts
anything - a number is not checked here or on the server, because phone numbers are written in too
many ways to pin down - so what is interesting about this widget is what happens on the way to the
phone.

### Basic

Spaces, brackets, dashes and a leading `+` are all kept in the value exactly as typed.

```html:preview
<et2-url-phone label="Phone" value="+49 (0) 631 31657-0"></et2-url-phone>
```

### What gets dialled

The stored value is cleaned up before dialling, never on screen. A `(0)` national prefix is
dropped, letters are folded onto their phone keypad digits, and everything that is not a digit or a
`+` is removed - so `+49 631 EGROUPWARE` is dialled as `+496313476879273`.

```html:preview
<et2-url-phone label="Vanity number" value="+49 631 EGROUPWARE"></et2-url-phone>
```

### How it dials

That depends on the client and on the installation, and none of it is configured on this
documentation site:

- on a phone or tablet the number is opened as a `tel:` link, which hands it to the dialler
- otherwise the *call_link* configuration decides - a `tel:` URL, or a URL of a telephony system
  with `%1` for the number, `%u` for the current user and `%t` for their extension
- with neither, the button does nothing

### Readonly

Readonly numbers are `et2-url-phone_ro`, which renders the number as clickable text that dials on
click.

```html:preview
<et2-url-phone_ro value="+49 (0) 631 31657-0"></et2-url-phone_ro>
```
