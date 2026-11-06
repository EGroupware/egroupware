`et2-visually-hidden` keeps its content in the accessibility tree and out of the picture. A screen
reader reads it, `Ctrl+F` finds it, but it takes up no space and nothing is drawn.

Use it where the meaning of something is obvious to a sighted user and invisible to everyone else: an
icon-only button, a link that says "more", a heading that only exists to structure the page. It is
not a way to hide content - `hidden` or `disabled` do that, and they hide it from assistive
technology too, which is usually the point.

Content inside it becomes visible again while it has focus, so a skip link or a keyboard shortcut
hint parked in one appears when the user tabs to it.

## Examples

### Labelling an icon

The button shows only an icon; the hidden text is what a screen reader announces.

```html:preview
<et2-button>
    <et2-image src="image"></et2-image>
    <et2-visually-hidden>Add an InfoLog entry</et2-visually-hidden>
</et2-button>
```

### It really does take no space

Both boxes below contain the same two lines of text. The second one has the middle line wrapped, and
is exactly one line tall.

```html:preview
<et2-vbox style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-description value="Total"></et2-description>
    <et2-description value="(including tax)"></et2-description>
</et2-vbox>
<et2-vbox style="border: 1px solid var(--sl-color-neutral-300)">
    <et2-description value="Total"></et2-description>
    <et2-visually-hidden>
        <et2-description value="(including tax)"></et2-description>
    </et2-visually-hidden>
</et2-vbox>
```

### It comes back on focus

Tab into the example below - the hint appears while the link has focus and disappears again when it
loses it. That is what makes "skip to main content" links work.

```html:preview
<et2-visually-hidden>
    <a href="#top">Skip to the top of the page</a>
</et2-visually-hidden>
<et2-description value="Press Tab with the cursor in this example."></et2-description>
```
