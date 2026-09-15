## Examples

`et2-url` is a [textbox](/components/et2-textbox) for a web address, with a button on the end that
opens whatever is in the field in a new window. It is the general member of the url family - see
[et2-url-email](/components/et2-url-email), [et2-url-phone](/components/et2-url-phone) and
[et2-url-fax](/components/et2-url-fax) for the ones that dial, compose or fax instead of opening.

### Basic

The ⎆ button stays disabled while the field is empty and becomes active as soon as there is
something to open.

```html:preview
<et2-url label="Website" value="https://www.egroupware.org"></et2-url>
```

### A missing protocol is filled in

Users type `www.example.com`, not `https://www.example.com`. Nothing rewrites what they typed - the
stored value stays exactly as entered - but a value with no `://` in it is opened as `http://…`.

```html:preview
<et2-url label="Website" value="www.egroupware.org"></et2-url>
```

### Paths instead of URLs

`allowPath` accepts a filesystem or VFS path - a value starting with `/` - in place of a URL, and
rejects actual URLs. There is no client-side check yet, so a wrong value is only caught when the
template is submitted; give the field a `placeholder` so the user learns what is wanted before
that.

```html:preview
<et2-url label="Document root" allowPath placeholder="/path/to/somewhere" value="/var/www/egroupware"></et2-url>
```

### Trailing slash

`trailingSlash` says whether the value has to end in a `/`. Unlike the rules above this one is
enforced in the browser: leaving the field adds or removes the slash to match. Type an address into
each of these and tab away.

In a template you write `trailingSlash="true"` or `trailingSlash="false"` and the template reader
turns the text into a boolean. Plain HTML has no such reader - `trailingSlash="false"` is an
attribute that is *present*, which for a boolean attribute means true - so the second example below
sets the property from script instead.

```html:preview
<et2-url label="Must end with /" trailingSlash value="https://www.egroupware.org/"></et2-url>
<et2-url id="url-no-slash" label="Must not end with /" value="https://www.egroupware.org"></et2-url>
<script>
    document.getElementById("url-no-slash").trailingSlash = false;
</script>
```

### Readonly

Readonly urls are a different widget, `et2-url_ro`: a link rather than an input. With a `label` as
well as a `value`, the label is the link text and the value is where it goes.

```html:preview
<et2-url_ro value="https://www.egroupware.org" label="EGroupware"></et2-url_ro>
```
