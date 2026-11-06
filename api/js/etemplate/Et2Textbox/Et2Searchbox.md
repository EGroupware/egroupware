## Searchbox or select?

The two look alike once a [select](/components/et2-select) has its own search field, but they answer
different questions. Ask what you want back from the user:

| | `et2-searchbox` | `et2-select` |
|---|---|---|
| Value | the text the user typed | the value of the option they chose |
| Allowed values | anything | the options you provided |
| What the typing is for | it *is* the answer | finding an option, then it is thrown away |

A searchbox is a [textbox](/components/et2-textbox): free text, handed to whatever does the actual
searching. A select has a list, and `search` turns on a field for filtering it - `searchUrl` even
fetches more options from the server as the user types - but that text only ever picks an option,
and the value that comes back is the option's.

The one case where the line really does blur is `allowFreeEntries`: a select with free entries turns
text matching no option into an option of its own, so typed text can become the value after all.

## Examples

A searchbox is a textbox preset for searching: `type="search"`, `clearable` and a translated
"Search" placeholder, so a bare tag is already a usable search field.

### Basic

The placeholder and the clear button come for free - no attributes needed.

```html:preview
<et2-searchbox></et2-searchbox>
```

### When the search happens

Leaving the field - clicking away, or tabbing out - fires one `change` event carrying what was
typed. Listen for that and run the search.

```html:preview
<et2-searchbox id="search-blur"></et2-searchbox>
<p>Searched for: <span id="search-blur-output">nothing yet</span></p>
<script>
    const search = document.getElementById("search-blur");
    const output = document.getElementById("search-blur-output");
    search.addEventListener("change", () => {output.textContent = search.value || "nothing yet";});
</script>
```

### Search as you type

`autochange` fires `change` half a second after the user stops typing, instead of waiting for them
to leave the field. Use it where searching is cheap; it means one search per pause in typing.

```html:preview
<et2-searchbox id="search-auto" autochange></et2-searchbox>
<p>Searched for: <span id="search-auto-output">nothing yet</span></p>
<script>
    const autoSearch = document.getElementById("search-auto");
    const autoOutput = document.getElementById("search-auto-output");
    autoSearch.addEventListener("change", () => {autoOutput.textContent = autoSearch.value || "nothing yet";});
</script>
```

### Label and placeholder

Both can be overridden; a label is worth adding when the box is not obviously a search field from
its surroundings.

```html:preview
<et2-searchbox label="Find" placeholder="Name or email"></et2-searchbox>
```
