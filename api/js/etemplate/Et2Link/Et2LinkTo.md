## What it is for

The widget that *creates* links. It is a row of four ways to attach something to the current entry:
upload a local file, pick a file out of the VFS, paste files from the EGroupware file clipboard, or
search for an existing entry and link that. The search half is an
[`<et2-link-entry>`](/components/et2-link-entry/); the "Link" button beside it appears once
something is selected.

See [Choosing a link widget](/components/et2-link/#choosing-a-link-widget) for how it relates to the
rest of the family.

It is also the upload widget that does not have to be told where files go. Whatever is uploaded
here becomes an attachment of the current entry (`/apps/<app>/<id>/`), and if that entry has no ID
yet the uploads are kept in the widget's value until it gets one. Prefer it over
[`<et2-vfs-upload>`](/components/et2-vfs-upload/), which needs a destination path and silently
drops files into a temporary directory without one, whenever you cannot name a VFS path or the
entry is not saved yet. Use [`<et2-file>`](/components/et2-file/) only when the files are not
going into the VFS at all.

## The Links tab

`<et2-link-to>` only creates links, it never shows them. The standard pairing is an
`<et2-link-list>` with the **same `id`** directly below it, which shows what is there and lets the
user remove it again:

```html
<et2-link-to id="link_to" span="all"></et2-link-to>
<et2-link-list id="link_to" span="all"></et2-link-list>
```

:::warning
Not live. Every path through this widget ends in a server request - `Link::ajax_link` to create the
link, `Link::ajax_link_search` to find an entry, a directory listing to pick a VFS file - and this
documentation site has no server to answer them. Everything below describes the behaviour against a
running EGroupware.
:::

## Value

The value names the entry that links are attached *to*, not the links themselves:

```js
{to_app: "infolog", to_id: "7"}
```

`to_id` being a plain ID means the entry exists, and links are created on the server the moment the
user clicks "Link" - an uploaded file is attached to the entry right then. If the entry has not
been saved yet there is nothing to link to, so `to_id` is instead an object collecting the links
(uploaded files included, as temporary files). They are submitted with the form, and the
application turns them into real links and attachments once it has saved the entry and knows its
ID:

```php
Api\Link::link('myapp', $new_id, $content['link_to']['to_id']);
```

The widget switches between the two on its own - a template does not have to care, as long as the
server put the entry's app and ID in the content.

## Limiting what can be linked

`onlyApp` restricts the search to one application and hides the application selector.
`applicationList` allows a comma separated set of them instead.

```html
<et2-link-to id="link_to" onlyApp="addressbook"></et2-link-to>
<et2-link-to id="link_to" applicationList="addressbook,infolog"></et2-link-to>
```

## Knowing when a link was made

After a successful link the widget clears itself and fires `et2-change`, whose `detail` is the array
of links that were just created. That is how `<et2-link-list>` updates itself without asking the
server again:

```js
this.et2.getWidgetById("link_to").addEventListener("et2-change", (e) =>
{
    console.log("linked", e.detail);
});
```

It also fires the older `link.et2_link_to` event with the raw server result, which existing
application code still listens for.

In the other direction it listens for `et2-delete` from any `<et2-link-list>` in the same template,
so that removing a link from a not-yet-saved entry also removes it from what will be submitted.
