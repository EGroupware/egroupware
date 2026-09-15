## Examples

The languages EGroupware has translations installed for. The list comes from the server and is
cached, so it costs one request per page no matter how many language selects are on it.

The value is the language code the rest of EGroupware uses - `en`, `de`, `fr` - and the labels are
not translated: a language is named in its own language, so someone who cannot read the current one
can still find theirs.

### Basic

Normally the widget is written with no options and the server fills the list in. The options below
are written out by hand because this page has no server to ask.

```html:preview
<et2-select-lang id="lang-basic" label="Language" value="de"></et2-select-lang>
<p>Value: <span id="lang-basic-output">?</span></p>
<script>
    const lang = document.getElementById("lang-basic");
    const langOutput = document.getElementById("lang-basic-output");
    lang.select_options = [
        {value: "en", label: "English"},
        {value: "de", label: "Deutsch"},
        {value: "fr", label: "Français"},
        {value: "es", label: "Español"}
    ];
    const showLang = () => {langOutput.textContent = JSON.stringify(lang.value);};

    lang.addEventListener("change", showLang);
    customElements.whenDefined("et2-select-lang").then(() => lang.updateComplete).then(showLang);
</script>
```

In an eTemplate that is one line, and the list arrives with the template:

```xml
<et2-select-lang id="lang" label="Language"/>
```
