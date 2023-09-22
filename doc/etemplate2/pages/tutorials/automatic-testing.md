## Automatic testing

Automatic tests go in the `test/` subfolder of your component's directory. They will be found and run by
“web-test-runner”.
Tests are written using

* Mocha (https://mochajs.org/) & Chai Assertion Library (https://www.chaijs.com/api/assert/)
* Playwright (https://playwright.dev/docs/intro) runs the tests in actual browsers.

Here's a simple example:

```ts
/**
 * Test file for Etemplate webComponent Textbox
 */
import {assert, fixture, html} from '@open-wc/testing';
import {Et2Textbox} from "../Et2Textbox";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";

// Reference to component under test
let element : Et2Textbox;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2Textbox>(html`
        <et2-textbox></et2-textbox>
	`);
	return element;
}

describe("Textbox widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Textbox);
	});

	it('has a label', () =>
	{
		element.set_label("Yay label");
		assert.isEmpty(element.shadowRoot.querySelectorAll('.et2_label'));
	})
});

// Run some common, basic tests for inputs (readonly, value, etc.)
inputBasicTests(before, "I'm a good test value", "input");
```

This verifies that the component can be loaded and created.  `inputBasicTests()` checks readonly and in/out values.

### What to test

#### Can the component be loaded and created?

Quite often components get accidental dependencies that complicate things, but sometimes they just break.

#### Value in = value out

Many of our components do correction and coercion on bad data or invalid values, but you should test that values out
match
the values going in. How to do this, and what to do with bad values, depends on the component.

### Test tips

* Always use `this.egw()`. It can be easily stubbed for your test. Global `egw` cannot.