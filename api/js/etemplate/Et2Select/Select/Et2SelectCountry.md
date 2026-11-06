## Examples

Every country, with its flag. The list comes from the server and is cached, so several country
selects on one page cost one request between them - and there is nothing to write in the template
but the widget itself.

Searching is on by default. The list is around 250 options long and nobody scrolls that.

:::warning
The examples on this page are not live. The country list is fetched from the server and this
documentation site has no server, so the widget would render an empty dropdown here whatever it was
given. Everything below describes the real behaviour against a running EGroupware.
:::

```xml
<et2-select-country id="adr_one_countrycode" label="Country"/>
```

The value is the two-letter ISO country code - `DE`, `FR`, `CA` - not the country's name.

### Flags

The flags are not images on the options. They are drawn by `api/templates/default/css/flags.css`,
which the widget loads itself, onto a `country_XX_flag` CSS part built from the option's value. So a
flag needs nothing in the option data, and an option whose value is not an ISO code simply gets no
flag rather than a broken image.

That also means the flags can be restyled - or dropped - from your own CSS:

```css
et2-select-country.no-flags::part(flag) {
	display: none;
}
```

### Several countries

`multiple` works as it does on [et2-select](/components/et2-select), and the value becomes an array
of codes.

```xml
<et2-select-country id="ships_to" label="Ships to" multiple="true"/>
```

The examples here are template markup rather than live previews: the country list is fetched from
the server, and this documentation site has no backend to answer with.
