/**
 * Test file for Etemplate webComponent Et2Password
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2Password} from "../Et2Password";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import {Et2Dialog} from "../../Et2Dialog/Et2Dialog";
import {assertNoElement} from "../../test/assertDom";

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
		assertNoElement(element.shadowRoot.querySelector(".input__password-toggle"), "Rendered a toggle button we did not ask for");
	});

	it('shows the toggle button for the inherited password-toggle attribute', async() =>
	{
		const toggle = await fixture<Et2Password>(html`
            <et2-password password-toggle></et2-password>
		`);

		assert.isNotNull(toggle.shadowRoot.querySelector(".input__password-toggle"), "password-toggle did not add a toggle button");
	});

	it('shows the toggle button for the viewable attribute', async() =>
	{
		const viewable = await fixture<Et2Password>(html`
            <et2-password viewable></et2-password>
		`);

		assert.isNotNull(viewable.shadowRoot.querySelector(".input__password-toggle"), "viewable did not add a toggle button");
	});

	it('viewable is a declared boolean property, so it reaches the schema & gets parsed', () =>
	{
		// @ts-ignore static Lit API
		const options = Et2Password.getPropertyOptions("viewable");

		assert.equal(options?.type, Boolean, "viewable is not declared as a boolean property");
		assert.equal(options?.attribute, "viewable", "viewable is not bound to its own attribute");
	});

	it('maps the viewable & deprecated togglePassword template attributes', async() =>
	{
		for(const [attribute, expected] of [["viewable", true], ["togglePassword", true], ["viewable", false]])
		{
			const password = await fixture<Et2Password>(html`
                <et2-password></et2-password>
			`);
			password.transformAttributes({[<string>attribute]: expected});
			await elementUpdated(password);

			assert.equal(password.viewable, expected, attribute + "=" + expected + " did not reach viewable");
			assert.equal(password.passwordToggle, expected, attribute + "=" + expected + " did not reach passwordToggle");
			assert.equal(
				password.shadowRoot.querySelector(".input__password-toggle") !== null, expected,
				attribute + "=" + expected + " rendered the wrong thing"
			);
		}
	});

	it('resolves the string a template actually passes', async() =>
	{
		// a .xet gives transformAttributes() strings, not booleans, and they are only turned into
		// booleans by the content arrayMgr a real template has
		for(const [attribute, value, expected] of <[string, string, boolean][]>[
			["viewable", "true", true],
			["viewable", "false", false],
			["togglePassword", "true", true],
			["viewable", "@can_see", true]
		])
		{
			const password = await fixture<Et2Password>(html`
                <et2-password></et2-password>
			`);
			password.setArrayMgr("content", new et2_arrayMgr({can_see: true}));
			password.transformAttributes({[attribute]: value});
			await elementUpdated(password);

			assert.equal(password.viewable, expected, attribute + '="' + value + '" did not resolve to ' + expected);
			assert.equal(
				password.shadowRoot.querySelector(".input__password-toggle") !== null, expected,
				attribute + '="' + value + '" rendered the wrong thing'
			);
		}
	});

	describe("revealing a password the server sent encrypted", () =>
	{
		let prompt : sinon.SinonStub;
		let promptInput : { type : string };

		beforeEach(async() =>
		{
			// enough of the dialog for the widget to reach the prompt's input widget
			promptInput = {type: "text"};
			prompt = sinon.stub(Et2Dialog, "show_prompt").returns(<any>{
				getUpdateComplete: () => Promise.resolve(),
				eTemplate: {widgetContainer: {getWidgetById: () => promptInput}}
			});

			// what transformAttributes() gives every password built from a template
			element.type = "password";
			element.viewable = true;
			element.plaintext = false;
			element.value = "ciphertext==";
			await elementUpdated(element);
		});

		afterEach(() => prompt.restore());

		it('stays masked while the user has not authenticated yet', async() =>
		{
			element.shadowRoot.querySelector<HTMLButtonElement>(".input__password-toggle").click();
			await elementUpdated(element);

			assert.isTrue(prompt.called, "Did not ask the user to authenticate");
			assert.equal(
				element.shadowRoot.querySelector("input").type, "password",
				"Unmasked the stored value without waiting for the user to authenticate"
			);
		});

		it('stays masked when the user cancels', async() =>
		{
			element.shadowRoot.querySelector<HTMLButtonElement>(".input__password-toggle").click();
			await elementUpdated(element);

			// what Et2Dialog would hand the callback for the Cancel button
			prompt.firstCall.args[0](Et2Dialog.CANCEL_BUTTON, "");
			await elementUpdated(element);

			assert.equal(element.shadowRoot.querySelector("input").type, "password", "Unmasked a cancelled reveal");
		});

		it('asks for the login password in a password field', async() =>
		{
			element.shadowRoot.querySelector<HTMLButtonElement>(".input__password-toggle").click();
			await elementUpdated(element);
			await Promise.resolve();

			assert.equal(promptInput.type, "password", "Login password would have been typed in clear");
		});

		/**
		 * The server answers a wrong login password with an empty value, while a request that
		 * failed has no answer at all.  Both tests answer the authentication prompt and stub
		 * egw.request() with one of the two; they pass when the validation message shown names the
		 * right problem and the value stays masked.
		 */
		const authenticate = async(answer : any) =>
		{
			(<any>window.egw).request = sinon.fake.returns(Promise.resolve(answer));
			sinon.stub(element, "getInstanceManager").returns(<any>{etemplate_exec_id: "exec"});
			const message = sinon.stub(element, "set_validation_error");

			element.shadowRoot.querySelector<HTMLButtonElement>(".input__password-toggle").click();
			await elementUpdated(element);
			prompt.firstCall.args[0](Et2Dialog.OK_BUTTON, "login password");
			await new Promise(resolve => setTimeout(resolve, 0));

			delete (<any>window.egw).request;
			return message;
		};

		it('says the password is invalid when the server refuses it', async() =>
		{
			const message = await authenticate("");

			assert.equal(message.firstCall.args[0], "invalid password*");
			assert.equal(element.shadowRoot.querySelector("input").type, "password");
		});

		it('says the request failed, not that the password is invalid, when there is no answer', async() =>
		{
			const message = await authenticate(undefined);

			assert.isTrue(message.calledOnce, "User was not told");
			assert.notInclude(message.firstCall.args[0], "invalid password");
			assert.include(message.firstCall.args[0], "returned with an error");
			assert.equal(element.shadowRoot.querySelector("input").type, "password");
		});

		it('asks for nothing to show what the user typed', async() =>
		{
			const input = element.shadowRoot.querySelector("input");
			input.value = "a good password";
			input.dispatchEvent(new Event("input"));
			await elementUpdated(element);

			element.shadowRoot.querySelector<HTMLButtonElement>(".input__password-toggle").click();
			await elementUpdated(element);

			assert.isFalse(prompt.called, "Asked the user to authenticate to see their own typing");
			assert.equal(
				element.shadowRoot.querySelector("input").type, "text",
				"Did not show the password the user just typed"
			);
		});
	});

	it('ignores passwordToggle from a template, which the server would not honour', async() =>
	{
		element.transformAttributes({passwordToggle: true});
		await elementUpdated(element);

		assert.isFalse(element.viewable, "A template's passwordToggle was honoured");
		assertNoElement(
			element.shadowRoot.querySelector(".input__password-toggle"),
			"Rendered a reveal button the server would have masked the password for"
		);
	});
});

inputBasicTests(before, "a good password", "input");
