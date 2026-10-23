/**
 * Tests for Et2IconOnlyButtonTooltips
 *
 * The layout CSS clips the label of a button with an icon at narrow widths.  The controller has to
 * notice that and give the label as tooltip to buttons that have no tooltip of their own.  Here a
 * host carries the controller and the clipping is a class on the host, which is what the container
 * query does in the real CSS.
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {LitElement} from "lit";
import * as sinon from "sinon";
import {Et2Button} from "../../../Et2Button/Et2Button";
import {Et2IconOnlyButtonTooltips} from "../Et2IconOnlyButtonTooltips";

class IconOnlyTestHost extends LitElement
{
	public controller = new Et2IconOnlyButtonTooltips(this);

	createRenderRoot() { return this; }
}

if(!customElements.get("et2-icon-only-test-host"))
{
	customElements.define("et2-icon-only-test-host", IconOnlyTestHost);
}
// A runtime reference, so the import is not erased as type-only
const keepImport = Et2Button;

describe("Icon-only button tooltips", () =>
{
	let host : IconOnlyTestHost;
	let egw;

	async function make(buttons)
	{
		host = await fixture(html`
            <et2-icon-only-test-host>
                <style>
                    et2-icon-only-test-host.narrow et2-button::part(label) {
                        display: block;
                        width: 1px;
                        overflow: hidden;
                    }
                </style>
                ${buttons}
            </et2-icon-only-test-host>`);
		egw = {tooltipBind: sinon.spy(), tooltipUnbind: sinon.spy(), lang: i => i, debug: () => {}};
		host.querySelectorAll("et2-button").forEach(b => sinon.stub(<any>b, "egw").returns(egw));
		await Promise.all(Array.from(host.querySelectorAll("et2-button")).map((b : any) => b.updateComplete));
		return host;
	}

	const narrow = async(on : boolean) =>
	{
		host.classList.toggle("narrow", on);
		host.controller.update();
		await elementUpdated(host);
	};

	it("gives the label as tooltip to an icon-only button without tooltip, and takes it back", async() =>
	{
		await make(html`<et2-button label="Save"></et2-button>`);
		const button = host.querySelector("et2-button");

		await narrow(true);
		assert.isTrue(egw.tooltipBind.calledOnceWith(button, "Save"), "label was not bound as tooltip");

		await narrow(false);
		assert.isTrue(egw.tooltipUnbind.calledWith(button), "label tooltip stayed after the label was shown");
	});

	it("does not bind twice while it stays narrow", async() =>
	{
		await make(html`<et2-button label="Save"></et2-button>`);
		await narrow(true);
		host.controller.update();
		assert.equal(egw.tooltipBind.callCount, 1);
	});

	it("leaves a button with a statustext alone", async() =>
	{
		await make(html`<et2-button label="Save" statustext="Save and close"></et2-button>`);
		await narrow(true);
		await narrow(false);
		assert.isFalse(egw.tooltipBind.called, "a button with a statustext got the label as tooltip");
		assert.isFalse(egw.tooltipUnbind.called, "the statustext tooltip was unbound");
	});

	it("does nothing while the labels are visible", async() =>
	{
		await make(html`<et2-button label="Save"></et2-button>`);
		await narrow(false);
		assert.isFalse(egw.tooltipBind.called);
	});
});
