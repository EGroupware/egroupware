/**
 * Test file for Etemplate webComponent Et2Password
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Password} from "../Et2Password";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";

// Stub global egw for cssImage to find
// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {}
};
// Reference to component under test
let element : Et2Password;

async function before()
{
	// Create an element to test with, and wait until it's ready
	element = await fixture<Et2Password>(html`
        <et2-password></et2-password>
	`);

	// Stub egw()
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Password widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2Password);
	});

	it('no toggle button by default', () =>
	{
		assert.isNull(element.shadowRoot.querySelector(".input__password-toggle"), "Rendered a toggle button we did not ask for");
	});

	it('shows the toggle button for the inherited password-toggle attribute', async() =>
	{
		const toggle = await fixture<Et2Password>(html`
            <et2-password password-toggle></et2-password>
		`);

		assert.isNotNull(toggle.shadowRoot.querySelector(".input__password-toggle"), "password-toggle did not add a toggle button");
	});

	it('shows the toggle button for togglePassword', async() =>
	{
		element.togglePassword = true;
		await elementUpdated(element);

		assert.isNotNull(element.shadowRoot.querySelector(".input__password-toggle"), "togglePassword did not add a toggle button");
		assert.isTrue(element.passwordToggle, "togglePassword did not reach passwordToggle");
	});

	it('maps the viewable & togglePassword template attributes onto passwordToggle', async() =>
	{
		for(const [attribute, expected] of [["viewable", true], ["togglePassword", true], ["viewable", false]])
		{
			const password = await fixture<Et2Password>(html`
                <et2-password></et2-password>
			`);
			password.transformAttributes({[<string>attribute]: expected});
			await elementUpdated(password);

			assert.equal(password.passwordToggle, expected, attribute + "=" + expected + " did not reach passwordToggle");
			assert.equal(password.togglePassword, expected, attribute + "=" + expected + " did not reach togglePassword");
			assert.equal(
				password.shadowRoot.querySelector(".input__password-toggle") !== null, expected,
				attribute + "=" + expected + " rendered the wrong thing"
			);
		}
	});
});

inputBasicTests(before, "a good password", "input");
