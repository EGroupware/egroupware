## Examples

A set of flags that is stored as one integer. Each option's value is a single bit, the user picks any
number of them, and the server adds the chosen ones up into the one number that goes into the
database - then splits it apart again on the way back.

The client side is an ordinary [et2-select](/components/et2-select): it never sees the integer, only
the individual bits, so `multiple` is what makes it useful.

Its usual job is ACL rights. With `appname` set, the options are that application's rights, as its
`acl_rights` hook declares them.

```xml
<et2-select-bitwise id="rights" label="Rights" appname="addressbook" multiple="true"/>
```

Anywhere else, the options are yours to supply, and their values have to be powers of two or the sum
cannot be taken apart again.

### Basic

The options below are written out by hand because this page has no server to ask for an
application's rights.

```html:preview
<et2-select-bitwise id="bitwise-basic" label="Rights" multiple></et2-select-bitwise>
<p>Selected bits: <span id="bitwise-basic-output">?</span></p>
<script>
    const bitwise = document.getElementById("bitwise-basic");
    const bitwiseOutput = document.getElementById("bitwise-basic-output");
    bitwise.select_options = [
        {value: "1", label: "Read"},
        {value: "2", label: "Add"},
        {value: "4", label: "Edit"},
        {value: "8", label: "Delete"},
        {value: "16", label: "Private"}
    ];
    bitwise.value = ["1", "4"];
    const showBitwise = () => {
        const bits = bitwise.value || [];
        bitwiseOutput.textContent = JSON.stringify(bits) + " - stored as " +
            bits.reduce((sum, bit) => sum + parseInt(bit), 0);
    };

    bitwise.addEventListener("change", showBitwise);
    customElements.whenDefined("et2-select-bitwise").then(() => bitwise.updateComplete).then(showBitwise);
</script>
```

The sum is worked out on the server, not here - the line above only shows what it will come to.
