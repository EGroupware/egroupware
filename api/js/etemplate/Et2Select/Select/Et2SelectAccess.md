## Examples

The three access levels an entry can have: private, visible to a group, or visible to everyone. The
list is built in the browser, so it needs nothing from the server and nothing in `sel_options`.

Everything on [et2-select](/components/et2-select) - `multiple`, `emptyLabel`, `readonly` - works
here too.

### Basic

The submitted value is `private`, `group` or `public`.

```html:preview
<et2-select-access id="access-basic" label="Access" value="private"></et2-select-access>
<p>Value: <span id="access-basic-output">?</span></p>
<script>
    const access = document.getElementById("access-basic");
    const accessOutput = document.getElementById("access-basic-output");
    const showAccess = () => {accessOutput.textContent = JSON.stringify(access.value);};

    access.addEventListener("change", showAccess);
    customElements.whenDefined("et2-select-access").then(() => access.updateComplete).then(showAccess);
</script>
```
