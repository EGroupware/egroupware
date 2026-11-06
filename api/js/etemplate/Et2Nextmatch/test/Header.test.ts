/**
 * Tests for Et2NextmatchHeader, the plain caption header (`et2-nextmatch-header`).
 *
 * Behaviour under test:
 * - renders its label into the shadow DOM, marked up the way the existing nextmatch CSS expects
 *   (`.label`, plus `.et2_label_empty` when there is no caption to show)
 * - the `set_label()` legacy wrapper still drives the reactive `label` property, including
 *   normalising null/undefined to "" so the empty-label class stays correct
 * - `setNextmatch()` accepts and stores the owning nextmatch (`et2_INextmatchHeader` contract)
 *
 * Setup strategy:
 * Each test creates the element through `document.createElement` and appends it to a throwaway
 * host, because a header only completes its first update once connected.
 *
 * Pass criteria:
 * Explicit assertions on rendered shadow DOM text/classes and on the public property values.
 * A failure here means the header's render contract changed, which would silently break the
 * ~668 `<et2-nextmatch-header>` uses in shipped templates.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert} from "@open-wc/testing";
import "../Headers/Header";
import {Et2NextmatchHeader} from "../Headers/Header";
import {fakeNextmatch, inHost, installEgwStub} from "./headerHelpers";

installEgwStub();

const header = () => <Et2NextmatchHeader>document.createElement("et2-nextmatch-header");

const labelNode = (element : Et2NextmatchHeader) => element.shadowRoot?.querySelector(".label");

describe("Et2NextmatchHeader", () =>
{
	it("upgrades to the component class", async() =>
	{
		const {host, element} = await inHost(header());
		try
		{
			assert.instanceOf(element, Et2NextmatchHeader, "et2-nextmatch-header did not upgrade");
		}
		finally
		{
			host.remove();
		}
	});

	it("renders its label", async() =>
	{
		const {host, element} = await inHost(header(), e => e.label = "Name");
		try
		{
			assert.equal(labelNode(element)?.textContent?.trim(), "Name", "label text should be rendered");
			assert.isFalse(
				labelNode(element)?.classList.contains("et2_label_empty"),
				"a header with a caption should not be marked empty"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("marks an absent label empty so the column keeps its minimum width", async() =>
	{
		const {host, element} = await inHost(header());
		try
		{
			assert.isTrue(
				labelNode(element)?.classList.contains("et2_label_empty"),
				"a header with no caption should get .et2_label_empty"
			);
			assert.equal(labelNode(element)?.textContent?.trim(), "", "no caption should render no text");
		}
		finally
		{
			host.remove();
		}
	});

	it("re-renders when set_label() is used instead of the property", async() =>
	{
		const {host, element} = await inHost(header());
		try
		{
			element.set_label("Modified");
			await element.updateComplete;

			assert.equal(element.label, "Modified", "set_label() should drive the reactive property");
			assert.equal(labelNode(element)?.textContent?.trim(), "Modified", "set_label() should re-render");
		}
		finally
		{
			host.remove();
		}
	});

	it("normalises a null label to empty rather than rendering 'null'", async() =>
	{
		const {host, element} = await inHost(header(), e => e.label = "Name");
		try
		{
			element.set_label(<any>null);
			await element.updateComplete;

			assert.equal(element.label, "", "set_label(null) should clear the label");
			assert.equal(labelNode(element)?.textContent?.trim(), "", "cleared label should render nothing");
			assert.isTrue(
				labelNode(element)?.classList.contains("et2_label_empty"),
				"a cleared label should get .et2_label_empty back"
			);
		}
		finally
		{
			host.remove();
		}
	});

	it("stores the owning nextmatch handed over by the header bar", async() =>
	{
		const {host, element} = await inHost(header());
		try
		{
			const nextmatch = fakeNextmatch();
			element.setNextmatch(nextmatch);

			assert.strictEqual(
				(<any>element).nextmatch,
				nextmatch,
				"setNextmatch() should keep the reference for later sort/filter interactions"
			);
		}
		finally
		{
			host.remove();
		}
	});
});
