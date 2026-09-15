## Examples

The states, provinces or regions of one country. Which country that is comes from `countryCode`, and
the list is fetched from the server for that code - so the widget is only useful next to a
[et2-select-country](/components/et2-select-country), and defaults to `DE` until you say otherwise.

### Basic

Changing `countryCode` re-fetches the list, so it is a normal thing to do from an `onchange` on the
country field beside it. The options below are written out by hand because this page has no server
to ask.

```html:preview
<et2-select-state id="state-basic" label="Province" countryCode="CA" value="ON"></et2-select-state>
<p>Value: <span id="state-basic-output">?</span></p>
<script>
    const state = document.getElementById("state-basic");
    const stateOutput = document.getElementById("state-basic-output");
    state.select_options = [
        {value: "AB", label: "Alberta"},
        {value: "BC", label: "British Columbia"},
        {value: "ON", label: "Ontario"},
        {value: "QC", label: "Quebec"}
    ];
    const showState = () => {stateOutput.textContent = JSON.stringify(state.value);};

    state.addEventListener("change", showState);
    customElements.whenDefined("et2-select-state").then(() => state.updateComplete).then(showState);
</script>
```

In an eTemplate the starting country comes from the content, as it does in the addressbook:

```xml
<et2-select-state id="adr_one_region" statustext="State" emptyLabel="Select one"
                  countryCode="$cont[adr_one_countrycode]" allowFreeEntries="true"/>
```

That only covers the first render. Keeping the two in step afterwards is the app's job - the
addressbook listens for a change on the country field and calls `set_country_code()` on the matching
state field:

```ts
(<Et2SelectState>this.et2.getWidgetById(country.id.replace('countrycode', 'region')))?.set_country_code(country.getValue());
```

Not every country has a list. `allowFreeEntries` is worth setting for that reason alone, so the user
can still type a region the server does not know.
