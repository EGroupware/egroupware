## Converting to webComponents

WebComponents have a different [lifecycle](https://lit.dev/docs/components/lifecycle/) and timing than our previous
widgets. This can cause issues when they are used together.

### Equivalent methods

| Old                                                            | WebComponent                                                                                                 | 
|----------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------|
| constructor(_parent, _attrs? : WidgetConfig, _child? : object) | constructor()                                                                                                | 
| destroy()                                                      | N/A                                                                                                          |
| attachToDOM()                                                  | connectedCallback()                                                                                          |
| detachFromDOM()                                                | disconnectedCallback()                                                                                       |
| transformAttributes(_attrs)                                    | transformAttributes(_attrs)                                                                                  |
| parseXMLAttrs(_attrsObj, _target, _proto)                      | N/A                                                                                                          |
| doLoadingFinished()                                            | N/A                                                                                                          |
| set_\[property](...)                                           | widget.\[property] = ...                                                                                     |
| get_\[property]()                                              | widget.\[property]                                                                                           |
| set_value(...)                                                 | set_value(...) remains, and is used when loading content from a template file.                               |
| loadingFinished(waiting : Promise[])                           | loadingFinished(waiting:Promise[]) remains for legacy compatability, but updateComplete() is the replacement |

### Accessing widget internals

Don't.

Widgets should be considered closed boxes, and will regenerate their contents as needed. They will also
malfunction if you try to alter their internal structure directly. Use their properties, methods, slots and parts.

### Is it ready

A lot of strange problems occur when trying to interact with a widget before it is "complete". The webComponent widgets
can update and will redraw their contents as needed. You can wait until they are ready using `updateComplete`:

```
    // FAILS, but may sometimes succeed just to be difficult
    let templateWidget = ...
    let label = templateWidget.getWidgetById("label");
    label.value = "Details":
    
    // Let it load
    let templateWidget = ...
    templateWidget.updateComplete.then(() => {
        let label = template.getWidgetById("label");
        label.value = "Details";
    });
```