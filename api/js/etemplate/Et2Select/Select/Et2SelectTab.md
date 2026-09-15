## Examples

An application, or one particular tab inside it. It is an
[et2-select-app](/components/et2-select-app) - same list, same `apps` attribute - with free entries
turned on, because the tabs of an application are not a list the server can offer in advance.

A tab is written as the application name, a hyphen, and the tab's own name: `addressbook-edit`. A
value with no hyphen is just the application.

### Basic

Free entries are on, so a tab that is not in the list can be typed in. The options below are written
out by hand because this page has no server to ask for the application list.

```html:preview
<et2-select-tab id="tab-basic" label="Open at" value="addressbook-edit"></et2-select-tab>
<p>Value: <span id="tab-basic-output">?</span></p>
<script>
    const tab = document.getElementById("tab-basic");
    const tabOutput = document.getElementById("tab-basic-output");
    tab.select_options = [
        {value: "addressbook", label: "Addressbook"},
        {value: "calendar", label: "Calendar"},
        {value: "infolog", label: "InfoLog"}
    ];
    const showTab = () => {tabOutput.textContent = JSON.stringify(tab.value);};

    tab.addEventListener("change", showTab);
    customElements.whenDefined("et2-select-tab").then(() => tab.updateComplete).then(showTab);
</script>
```

A value naming a tab is shown as it is written - there is no list of tab names to look a nicer label
up in, so `addressbook-edit` reads as `addressbook-edit`.

In an eTemplate that is one line, and the list arrives with the template:

```xml
<et2-select-tab id="default_tab" label="Open at"/>
```
