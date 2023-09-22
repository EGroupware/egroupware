## Widgets

Widgets are the building blocks of our UI.
While there is some legacy mess, we are currently making all our
widgets [WebComponents](https://developer.mozilla.org/en-US/docs/Web/API/Web_components)
based on [Lit](https://lit.dev/docs/).

Automated widget testing is done using "web-test-runner" to run the tests, which are written using

* Mocha (https://mochajs.org/) & Chai Assertion Library (https://www.chaijs.com/api/assert/)
* Playwright (https://playwright.dev/docs/intro) runs the tests in actual browsers.

If you just want to use existing widgets, you can just put them in your .xet template file:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE overlay PUBLIC "-//EGroupware GmbH//eTemplate 2.0//EN" "https://www.egroupware.org/etemplate2.0.dtd">
<overlay>
    <template id="myapp.mytemplate">
        <et2-vbox>
            <et2-textbox id="name" label="Your name" class="et2-label-fixed"></et2-textbox>
            <et2-select-number id="quantity" label="Quantity" class="et2-label-fixed"></et2-select-number>
        </et2-vbox>
    </template>
</overlay>
```

<img src="/assets/images/widgets_rendered_example.png" alt="Rendered example template">