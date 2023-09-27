## Creating a component

ETemplate components are [LitElements](https://lit.dev/docs/) that are wrapped with
our [Et2Widget](https://github.com/EGroupware/egroupware/blob/master/api/js/etemplate/Et2Widget/Et2Widget.ts) mixin,
which adds properties and methods to support loading from our template files and returning values to the server. They
should (relatively) stand-alone.

Common components are in `api/js/etemplate/`. You can add application specific components in `<appname>/js/`.

### Create the files

```
myapp/
    js/
        MyWidget/
            test/
            MyWidget.ts
            
```

You should have [automatic tests](/tutorials/automatic-testing) to verify your component and avoid regressions
in `test/`.

### Get it loaded

To have EGroupware load your component, it must be included somewhere.
Add your component to the `include` block at the top of `/api/js/etemplate/etemplate2.js`. If you have an application
specific component, include at the top of your `app.js`.

```typescript
...
import './MyWidget/MyWidget.ts';

...
```

### Load and return

### AJAX data
