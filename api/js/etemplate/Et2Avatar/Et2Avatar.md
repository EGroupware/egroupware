By default a generic icon will be shown. You can customise the icon, as well as set initials and image. You should
always provide a `label` for assistive devices.

```html:preview
<et2-avatar></et2-avatar>
```

Our Avatar widget extends Shoelace's [Avatar](https://shoelace.style/components/avatar) widget

:::tip

There are multiple components for showing an image for an account / contact / email

* [Avatar](../et2-avatar): (This one) Shows an image to go with a profile.
* [LAvatar](../et2-lavatar): Does everything `Avatar` does, and also shows a letter avatar if there's no image

You probably want to use [LAvatar](../et2-lavatar) in your template as it gives more options

:::

## Examples

### Image

To use an image for the avatar, set the `image` attribute. Avatar images can be lazily loaded by setting the `loading`
attribute to `lazy`.

```html:preview
<et2-avatar
  image="https://images.unsplash.com/photo-1529778873920-4da4926a72c2?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&q=80"
  label="Avatar of a gray tabby kitten looking down"
></et2-avatar>
<et2-avatar
  image="https://images.unsplash.com/photo-1591871937573-74dbba515c4c?ixlib=rb-1.2.1&auto=format&fit=crop&w=300&q=80"
  label="Avatar of a white and grey kitten on grey textile"
  loading="lazy"
></et2-avatar>
```

### Initials

If you don't have an image to use, you can set `initials` to show initials instead of the icon.

```html:preview
<et2-avatar initials="EG"></et2-avatar>
```

### Custom Icons

When no image or initials are set, an icon will be shown. The default avatar shows a generic “user” icon, but you can
customize this with the icon slot.

```html:preview
<et2-avatar label="Avatar with an image icon">
  <et2-image slot="icon" name="image"></et2-image>
</et2-avatar>

<et2-avatar label="Avatar with an archive icon">
  <et2-image slot="icon" name="archive"></et2-image>
</et2-avatar>

<et2-avatar label="Avatar with a briefcase icon">
  <et2-image slot="icon" name="briefcase"></et2-image>
</et2-avatar>
```

### Contact ID

If you have only the ID for the profile, set `contactId` and Avatar will ask the server for everything. When possible,
avoid this as it requires an extra request to the server. Setting `initials` will show the initials until the image is
found.

`contactId` could be in one of these formats:

* `#`  will be considered as contact ID
* `contact:#` similar to above
* `account:#` will be considered as account ID
* `email:<email>` will be considered as email address

```html:preview
<et2-avatar contactid="contact:6"></et2-avatar>
<et2-avatar initials="TU" contactid="contact:6" loading="lazy"></et2-avatar>
```

:::warning

The image fetched is cached by the browser, which is often fine.   
If it can be changed ([editable](#editable)) set `image` to something with a cachebuster.

:::
### Editable

Make avatar widget editable to be able to crop profile picture or upload a new photo

```html:preview
<et2-avatar editable></et2-avatar>
```

### Avatar Groups

Use [Avatar Group](../et2-avatar-group) to show a group of avatars _when you have the needed information in an array_.
Otherwise, just use multiple `Avatars` and some CSS.

```html:preview
<div class="avatar-group">
    <et2-avatar shape="circle" image="https://images.unsplash.com/photo-1525351484163-7529414344d8?fm=jpg&q=60&w=3000&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D"></et2-avatar>
    <et2-avatar shape="circle" image="https://images.unsplash.com/photo-1530610476181-d83430b64dcd?q=80&w=1935&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D"></et2-avatar>
    <et2-avatar shape="circle" image="https://images.unsplash.com/photo-1542276867-c7f5032e1835?q=80&w=1984&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D"></et2-avatar>
</div>

<style>
  .avatar-group et2-avatar:not(:first-of-type) {
    margin-left: -1rem;
  }

  .avatar-group et2-avatar::part(base) {
    border: solid 2px var(--sl-color-neutral-0);
  }
</style>
```