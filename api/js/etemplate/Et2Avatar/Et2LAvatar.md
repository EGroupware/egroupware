LAvatar extends [Avatar](../et2-avatar) by adding `fname` and `lname`, and a coloured background. The background color
is based on the `fname` and `lname`, not random.

```html:preview
<et2-lavatar></et2-lavatar>
```

:::tip

There are multiple components for showing an image for an account / contact / email

* [Avatar](../et2-avatar): Shows an image to go with a profile.
* LAvatar: (This one) Does everything `Avatar` does, and also shows a letter avatar if there's no image

:::

## Examples

### Name

If you don't have an image to use, you can `fname` and `lname` to show initials. Setting `fname` or
`lname` will also set the tooltip.  `LAvatar` interferes with the `initials` attribute.

```html:preview
<et2-lavatar initials="EG"></et2-lavatar>
<et2-lavatar fname="Test" lname="User"></et2-lavatar>
```

### Contact ID

If you have only the ID for the profile, set `contactId` and LAvatar will ask the server for everything. If you set
`lname` and `fname` the letter avatar will be shown until the server responds.

```html:preview
<et2-lavatar contactid="contact:6"></et2-lavatar>
<et2-lavatar fname="Test" lname="User" contactid="contact:6" loading="lazy"></et2-lavatar>
```

See [Avatar](../et2-avatar) for more examples which will also work for `LAvatar`