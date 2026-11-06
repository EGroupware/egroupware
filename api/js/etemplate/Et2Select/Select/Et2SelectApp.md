## Examples

The applications installed on this EGroupware. The list comes from the server and is cached per
`apps` setting, so several app selects asking for the same set cost one request between them.

The value is the application name as EGroupware uses it internally - `addressbook`, `infolog` - not
its translated title.

### Basic

Normally the widget is written with no options and the server fills the list in. The options below
are written out by hand because this page has no server to ask.

```html:preview
<et2-select-app id="app-basic" label="Application" value="infolog"></et2-select-app>
<p>Value: <span id="app-basic-output">?</span></p>
<script>
    const app = document.getElementById("app-basic");
    const appOutput = document.getElementById("app-basic-output");
    app.select_options = [
        {value: "addressbook", label: "Addressbook", icon: "addressbook/navbar"},
        {value: "calendar", label: "Calendar", icon: "calendar/navbar"},
        {value: "infolog", label: "InfoLog", icon: "infolog/navbar"},
        {value: "filemanager", label: "Filemanager", icon: "filemanager/navbar"}
    ];
    const showApp = () => {appOutput.textContent = JSON.stringify(app.value);};

    app.addEventListener("change", showApp);
    customElements.whenDefined("et2-select-app").then(() => app.updateComplete).then(showApp);
</script>
```

In an eTemplate that is one line, and the list arrives with the template:

```xml
<et2-select-app id="app" label="Application"/>
```

### Which applications

`apps` decides what the server sends back:

| `apps` | |
|---|---|
| `installed` | everything installed - the default |
| `enabled` | installed and enabled |
| `user` | only what the current user may open |
| `all` | also applications that are not installed |
| `all+setup` | `all`, plus setup itself |

`user` is the right choice whenever the value will be used to send the user somewhere, since it is
the only one that cannot offer them an application they may not open.

```xml
<et2-select-app id="start_app" label="Start with" apps="user"/>
```
