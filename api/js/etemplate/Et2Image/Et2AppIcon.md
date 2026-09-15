## When to use it

`<et2-appicon>` shows the navbar icon of an EGroupware application. Use it wherever a row, a link or
a header has to say *which app* something belongs to - a link list mixing addressbook contacts and
infolog entries, a search result, a tab header.

It is [`<et2-image>`](../et2-image) with the source worked out for you: give it an application name
and it looks up that application's registered icon, so you never have to hard-code an image path
that changes when an app ships a new icon or a template set overrides it.

:::warning
The examples on this page are deliberately not live. Resolving an app icon needs the application
registry and the image map of a logged-in EGroupware session, and this documentation site has
neither - a preview here would render an empty box no matter what you passed it.
:::

## Examples

### An application's icon

`src` is the application name.

```xml
<et2-appicon src="addressbook"/>
<et2-appicon src="calendar"/>
<et2-appicon src="infolog"/>
```

With no `src` at all it falls back to the application the template belongs to, which is what you
want in an app's own header:

```xml
<et2-appicon/>
```

If the application name does not resolve to an icon, the generic `nonav` icon is shown rather than
nothing - so an app that was removed leaves a placeholder instead of a hole in the layout.

### From content

The usual case is a row whose application is data, not something known when the template is written.

```xml
<et2-appicon src="@app"/>
```

### Size

Size comes from [`<et2-image>`](../et2-image): `width` and `height` accept a number of pixels, a
value with a CSS unit, or a CSS expression.

```xml
<et2-appicon src="calendar" width="32"/>
<et2-appicon src="calendar" height="2rem"/>
```

### In the kdots template set

`kdots` switches to the kdots-specific navbar icon and inlines it as SVG, so the icon picks up that
application's colour from the `--<app>-color` CSS custom property instead of being a fixed-colour
bitmap.

```xml
<et2-appicon src="mail" kdots="true"/>
```
