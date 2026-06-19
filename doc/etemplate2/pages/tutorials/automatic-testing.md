## Automatic component testing

Automatic tests go in the `test/` subfolder of your component's directory. They will be found and run by
“web-test-runner”.
Tests are written using

* Mocha (https://mochajs.org/) & Chai Assertion Library (https://www.chaijs.com/api/assert/)
* Playwright (https://playwright.dev/docs/intro) runs the tests in actual browsers.

Here is a basic test for a generic input widget:

```ts
/**
 * Tests for MyWidget.
 */
import {assert, elementUpdated, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {MyWidget} from "../MyWidget";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";

let element : MyWidget;

async function createElement()
{
	element = await fixture<MyWidget>(html`
		<et2-my-widget></et2-my-widget>
	`);

	// Replace EGroupware services used by the widget with the smallest useful stub.
	sinon.stub(element, "egw").returns({
		lang: value => value,
		tooltipUnbind: () => {}
	});
	await elementUpdated(element);

	return element;
}

describe("MyWidget", () =>
{
	beforeEach(createElement);

	it('is defined', () =>
	{
		assert.instanceOf(element, MyWidget);
	});

	it("updates its value", async() =>
	{
		element.value = "Updated";
		await element.updateComplete;

		assert.equal(element.value, "Updated");
	});
});

// Reuse the common input contract tests when MyWidget extends Et2InputWidget.
inputBasicTests(createElement, "Test value", "input");
```

This verifies that the component can be created and reacts to a property update. Stub `element.egw()` with only the
services the component uses. This keeps the test independent of a running EGroupware installation. Use
`element.updateComplete` or `elementUpdated(element)` before asserting changes that require a render.

`inputBasicTests()` checks common input behaviour, including readonly handling and values in and out. It is only
appropriate for components based on `Et2InputWidget`.

Run one test file from the repository root:

```sh
npm run jstest -- api/js/etemplate/MyWidget/test/MyWidget.test.ts
```

Run the complete frontend test suite with:

```sh
npm run jstest
```

### What to test

#### Can the component be loaded and created?

Quite often components get accidental dependencies that complicate things, but sometimes they just break.

#### Value in = value out

Many of our components do correction and coercion on bad data or invalid values, but you should test that values out
match
the values going in. How to do this, and what to do with bad values, depends on the component.

### Test tips

* Component code should use `this.egw()`. Tests can stub it on the component; the global `egw` cannot be isolated as
  easily.
* Keep the stub small. Add only the EGroupware methods needed by the behaviour under test.
* Await `updateComplete` before checking rendered output after changing a reactive property.
