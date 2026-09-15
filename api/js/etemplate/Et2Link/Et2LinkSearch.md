## What it is for

The search box out of [`<et2-link-entry>`](/components/et2-link-entry/): an
[`<et2-select>`](/components/et2-select/) whose options are fetched by searching one application
through the linking system. Like [`<et2-link-apps>`](/components/et2-link-apps/) it is mostly an
internal part - use `<et2-link-entry>` unless you specifically want a search with no application
selector beside it.

See [Choosing a link widget](/components/et2-link/#choosing-a-link-widget) for the rest of the
family.

:::warning
Not live. Every keystroke sends `Link::ajax_link_search` to the server, and displaying an
already-set value asks the server for its title, so on this documentation site the search would
return nothing and a preset value would stay at `??`. Everything below describes the behaviour
against a running EGroupware.
:::

## Examples

### Searching one application

`app` says which application to search. It is a select with `search` on, so the options are the
search results, not a fixed list, and `clearable` is on so the user can empty it again.

```html
<et2-link-search id="contact" app="addressbook"></et2-link-search>
```

### Following an application selector

Put an `<et2-link-apps>` next to it, as a sibling in the same parent, and the search follows it: the
search asks its sibling which application to search rather than using its own `app`. That is exactly
the arrangement inside `<et2-link-entry>`, and the only reason to build it by hand is to put
something else between the two.

### Setting a value

The value is the entry's ID. It is normally *not* one of the current search results - the field was
filled in from stored content, not by searching - so the widget does not discard it the way a select
usually discards a value with no matching option. Instead it adds an option for it, shows `??` while
it asks the server for the title, and fills the title in when the answer arrives.

For the same reason it does not validate: there is no option list to check the value against.

### Search parameters

`searchOptions` is passed to the server with the search, and the `query` callback gets the request
before it is sent and can return false to cancel it. Both are usually set through the surrounding
`<et2-link-entry>` rather than here.
