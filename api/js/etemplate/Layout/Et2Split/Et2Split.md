`et2-split` puts two panels side by side with a draggable divider between them. It is what sits
between a list and a detail view, or between a tree and its contents.

It takes exactly two children, one in the `start` slot and one in the `end` slot. In a template you
can leave the slots off - the first two children are assigned to `start` and `end` automatically when
the template is parsed - but in hand-written HTML you have to name them yourself.

:::warning
A splitter is `height: 100%`, so it is as tall as whatever contains it. In a container with no
height it collapses to a few pixels and looks like nothing rendered. Give it, or its parent, a
height.
:::

## Examples

### Two panels

`position` is the divider's distance from the start edge, as a percentage.

```html:preview
<div style="height: 10em">
    <et2-split position="30">
        <et2-box slot="start">
            <et2-description value="Start panel"></et2-description>
        </et2-box>
        <et2-box slot="end">
            <et2-description value="End panel - drag the divider"></et2-description>
        </et2-box>
    </et2-split>
</div>
```

### Stacking instead of side by side

`vertical` stacks the panels, with a horizontal divider between them.

```html:preview
<div style="height: 12em">
    <et2-split vertical position="40">
        <et2-box slot="start">
            <et2-description value="Top"></et2-description>
        </et2-box>
        <et2-box slot="end">
            <et2-description value="Bottom"></et2-description>
        </et2-box>
    </et2-split>
</div>
```

The older `orientation` attribute does the same thing and is still accepted, but it names the
*divider* rather than the panels, so its values read backwards: `orientation="h"` means a horizontal
divider, which is `vertical` above. Prefer `vertical` in new templates.

### Docking

Set `primary` to say which panel keeps its size when the splitter itself is resized. It also enables
docking: double-click the divider to collapse the primary panel, double-click again to bring it back
to where it was. `dock()`, `undock()`, `toggleDock()` and `isDocked()` do the same from javascript.

```html:preview
<div style="height: 10em">
    <et2-split id="split-dock-example" primary="start" position="30">
        <et2-box slot="start">
            <et2-description value="Double-click the divider"></et2-description>
        </et2-box>
        <et2-box slot="end">
            <et2-description value="or use the button below"></et2-description>
        </et2-box>
    </et2-split>
</div>
<et2-button id="split-dock-button" label="Toggle dock"></et2-button>
<script>
    const split = document.getElementById("split-dock-example");
    document.getElementById("split-dock-button").addEventListener("click", () =>
    {
        customElements.whenDefined("et2-split")
            .then(() => split.updateComplete)
            .then(() => split.toggleDock());
    });
</script>
```

Without `primary` there is nothing to dock, so double-clicking the divider does nothing.

### Limits and snapping

`--min` and `--max` bound how far the divider can travel. `snap` lists positions it should snap to,
and `snapThreshold` how close it has to get first.

```html:preview
<div style="height: 10em">
    <et2-split style="--min: 20%; --max: 80%" snap="25% 50% 75%" snap-threshold="20">
        <et2-box slot="start">
            <et2-description value="Snaps at 25, 50 and 75%"></et2-description>
        </et2-box>
        <et2-box slot="end">
            <et2-description value="and stops at 20 / 80%"></et2-description>
        </et2-box>
    </et2-split>
</div>
```

### Reacting to a resize

`sl-reposition` fires as the divider moves. Read `position` for the new percentage.

```html:preview
<div style="height: 8em">
    <et2-split id="split-event-example">
        <et2-box slot="start"><et2-description value="Drag me"></et2-description></et2-box>
        <et2-box slot="end"><et2-description value=""></et2-description></et2-box>
    </et2-split>
</div>
<p>position: <span id="split-event-output">50</span></p>
<script>
    const split = document.getElementById("split-event-example");
    const out = document.getElementById("split-event-output");
    split.addEventListener("sl-reposition", () => {out.textContent = Math.round(split.position);});
</script>
```

Widgets that size themselves by hand - a nextmatch, anything implementing `et2_IResizeable` - are
told to resize automatically after the drag ends, so they do not need their own listener.

### Remembering the position

A splitter with an `id` saves where the user left the divider as a preference of the current
application, under `splitter-size-<id>`, and loads it again the next time the template runs. Drop the
`id` and the position resets to 50% on every load.

This needs a logged-in session, so there is no live example for it - it is nothing more than adding
the `id`:

```xml
<et2-split id="splitter" primary="start">
    <nextmatch id="nm" template="myapp.index.rows"/>
    <et2-template id="myapp.detail"/>
</et2-split>
```
