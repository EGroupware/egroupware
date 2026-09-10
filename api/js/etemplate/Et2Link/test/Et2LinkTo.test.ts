/**
 * Test file for Etemplate webComponent Et2LinkTo
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {Et2LinkTo} from "../Et2LinkTo";
import {inputBasicTests} from "../../Et2InputWidget/test/InputBasicTests";
import * as sinon from "sinon";

// @ts-ignore
window.egw = {
	lang: i => i + "*",
	tooltipUnbind: () => {},
	image: () => "",
	link_app_list: () => ({}),
	langRequireApp: () => Promise.resolve(),
	getLocalStorageItem: () => null,
	tooltipBind: () => {}
};
// Reference to component under test
let element : Et2LinkTo;
let instanceManagerStub;

async function before()
{
	if(instanceManagerStub)
	{
		instanceManagerStub.restore();
	}
	// connectedCallback() unconditionally uses getInstanceManager().DOMContainer, so this has to
	// be stubbed on the prototype before the fixture connects, not on the instance after
	instanceManagerStub = sinon.stub(Et2LinkTo.prototype, "getInstanceManager").returns({
		DOMContainer: document.createElement("div"),
		etemplate_exec_id: "mocked-id"
	});

	element = await fixture<Et2LinkTo>(html`
        <et2-link-to></et2-link-to>
	`);

	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);

	return element;
}

describe("Link to widget", () =>
{
	// Setup run before each test
	beforeEach(before);

	it('is defined', () =>
	{
		assert.instanceOf(element, Et2LinkTo);
	});
});

// value is a plain string | LinkInfo, unmodified by any getter/setter override - a plain string
// round-trips exactly, unlike the LinkInfo-object shape its sibling widgets normalize to.
inputBasicTests(before, "infolog:123", "input", {
	checkEmptyDisplay: (element : Et2LinkTo) =>
		assert.notOk(element.shadowRoot.querySelector("et2-link-entry")?.["value"]?.id, "Displaying something when there is no value")
});
