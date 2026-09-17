import {assert, fixture, html} from "@open-wc/testing";
import "../Et2PasswordReadonly";

/**
 * Contract under test:
 * - A readonly password says whether a password is set, and nothing more.
 *
 * Setup strategy:
 * - Give the widget a stored value the way a nextmatch row does, and read back what it kept.
 *
 * Pass criteria:
 * - Something stored shows a mask, nothing stored shows nothing, and the value it was handed is
 *   not retrievable from the widget afterwards.
 */
describe("et2-password_ro", () =>
{
	const stored = "caps3UfNJHx6imfMRKXD2A==nXIvE/hPqD2hFdB5uRVGsg==";

	it("shows only that a password is set", async() =>
	{
		const element = await fixture<any>(html`
			<et2-password_ro></et2-password_ro>
		`);
		element.value = stored;
		await element.updateComplete;

		assert.equal(element.value, "***", "a stored password is shown as a mask");
		assert.notInclude(element.shadowRoot?.innerHTML ?? "", stored, "the password must not be rendered");
		assert.notInclude(element.innerHTML, stored);
	});

	it("does not keep the password it was given", async() =>
	{
		const element = await fixture<any>(html`
			<et2-password_ro></et2-password_ro>
		`);
		element.value = stored;
		await element.updateComplete;
		assert.notEqual(element.value, stored, "the widget must not hold the value it was handed");
	});

	it("shows nothing when there is no password", async() =>
	{
		const element = await fixture<any>(html`
			<et2-password_ro></et2-password_ro>
		`);
		element.value = "";
		await element.updateComplete;
		assert.equal(element.value, "", "an empty password should show nothing at all");
	});

	it("does not re-mask a mask", async() =>
	{
		const element = await fixture<any>(html`
			<et2-password_ro></et2-password_ro>
		`);
		element.value = "***";
		await element.updateComplete;
		assert.equal(element.value, "***");
	});
});
