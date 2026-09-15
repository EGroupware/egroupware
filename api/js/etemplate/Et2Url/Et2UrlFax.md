## Examples

`et2-url-fax` is [et2-url-phone](/components/et2-url-phone) with a different destination: the same
free-form number field, but the 📠 button sends a fax instead of placing a call.

### Basic

The value is a phone number written however the user likes; nothing is reformatted or validated.

```html:preview
<et2-url-fax label="Fax" value="+49 (0) 631 31657-26"></et2-url-fax>
```

### Where the fax goes

Fax is sent by email in most installations. If the *fax_email* configuration is set, the number is
stripped down to digits and a `+`, rewritten into an address by *fax_email_regexp* (for instance
`+4963131657.26@fax.example.com`) and handed to the mail compose window, exactly as
[et2-url-email](/components/et2-url-email) would.

With no fax-to-email configuration the widget falls back to dialling the number like
[et2-url-phone](/components/et2-url-phone) does, which is right for a fax modem on the desk and
useless otherwise. Neither is configured on this documentation site, so the button here does
nothing.

### Readonly

Readonly fax numbers are `et2-url-fax_ro`, which renders the number as clickable text that sends on
click.

```html:preview
<et2-url-fax_ro value="+49 (0) 631 31657-26"></et2-url-fax_ro>
```
