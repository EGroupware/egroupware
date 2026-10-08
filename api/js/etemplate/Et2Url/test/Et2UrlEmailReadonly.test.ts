/**
 * EGroupware eTemplate2 - Readonly email URL widget tests
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2UrlEmailReadonly} from "../Et2UrlEmailReadonly";
import {Et2UrlEmail} from "../Et2UrlEmail";

const egw = {
	debug: () => {},
	lang: value => value,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	preference: () => "onlyname",
	jsonq: () =>
	{
		throw new Error("email display should not request contact data");
	}
};

window.egw = function() { return egw; } as any;
Object.assign(window.egw, egw);
window.egwIsMobile = () => false;

describe("Et2UrlEmailReadonly", () =>
{
	/**
	 * Contract: explicit email display uses the address directly.
	 * Setup: contact lookup would throw if called, simulating an unavailable
	 * async formatter dependency.
	 * Pass: the widget exposes the email immediately without waiting for, or
	 * requesting, contact data.
	 */
	it("displays emailDisplay=email synchronously without contact lookup", async() =>
	{
		const element = await fixture<Et2UrlEmailReadonly>(html`
            <et2-url-email_ro emailDisplay="email"></et2-url-email_ro>
		`);
		assert.instanceOf(element, Et2UrlEmailReadonly);

		element.value = "\"Test User\" <test@example.com>";

		assert.equal(element.value, "test@example.com");
	});

	/**
	 * Ticket #126151: a mail-list row's own sender/address chip must never intercept a plain
	 * click/tap to mailto:-open a compose - only the row itself (select/open for reading) should
	 * react to that. disableClickAction is checked INSIDE the default onclick handler, at actual
	 * click time - NOT used in transformAttributes() to decide whether to install that handler at
	 * all. Reason (found live): for a widget living inside a nextmatch row template,
	 * transformAttributes() can run before this property's own XET-attribute value has been
	 * applied yet, so a row's widget could already read disableClickAction=true while still
	 * carrying the default onclick, installed moments earlier while the property still read its
	 * `false` default. A real click can only ever happen once the widget's attribute/property
	 * application has long since finished, so checking there is reliable regardless of that
	 * construction-time race.
	 */
	describe("disableClickAction", () =>
	{
		afterEach(() => sinon.restore());

		it("transformAttributes() installs the default onclick regardless of disableClickAction (checked later, at click time)", () =>
		{
			const element = new Et2UrlEmailReadonly();
			element.disableClickAction = true;

			const attrs : any = {};
			element.transformAttributes(attrs);

			assert.equal(typeof attrs.onclick, "function");
		});

		it("the installed default onclick is a no-op once disableClickAction is true, however late it was set", () =>
		{
			const actionStub = sinon.stub(Et2UrlEmail, "action");
			const element = new Et2UrlEmailReadonly();
			const attrs : any = {};
			element.transformAttributes(attrs);
			// Simulates the exact race found live: disableClickAction only becomes true AFTER
			// transformAttributes() already ran (and so already installed the handler above) -
			// a real click can only ever happen once that's long since settled.
			element.disableClickAction = true;
			(element as any)._value = "test@example.com";

			attrs.onclick.call(element, {currentTarget: element});

			assert.isFalse(actionStub.called);
		});

		it("the installed default onclick still fires normally when disableClickAction is never set", () =>
		{
			const actionStub = sinon.stub(Et2UrlEmail, "action");
			const element = new Et2UrlEmailReadonly();
			const attrs : any = {};
			element.transformAttributes(attrs);
			(element as any)._value = "test@example.com";

			attrs.onclick.call(element, {currentTarget: element});

			assert.isTrue(actionStub.calledOnceWith("test@example.com"));
		});

		/**
		 * Ticket #126151, part 2: a real customer/user report after the first fix landed - the
		 * compose popup was gone, but so was ordinary row selection/preview and the double-click
		 * message-view popup. Root cause: Et2Widget's own willUpdate() adds the "et2_clickable"
		 * CSS class whenever `onclick` is a function - true here even though the function itself
		 * now no-ops - and Et2Datagrid._isInteractiveRowEventTarget() treats any click landing on
		 * a ".et2_clickable" element as "belongs to the widget, not the row", skipping the row's
		 * own click handling entirely. Fixed by clearing `onclick` itself (not just no-opping its
		 * body) once disableClickAction is known true, via updated() - runs after a real custom
		 * element's full attribute/property lifecycle, so it reaches the correct state reliably,
		 * same reasoning as checking disableClickAction inside the handler body above.
		 */
		it("clears onclick (and so the et2_clickable class) once disableClickAction is true", async() =>
		{
			const element = await fixture<Et2UrlEmailReadonly>(html`
				<et2-url-email_ro emailDisplay="email"></et2-url-email_ro>
			`);
			// Simulates transformAttributes() having installed the default handler - fixture()
			// does not go through that XET-content-array pipeline itself.
			element.onclick = () => {};
			await element.updateComplete;
			assert.isTrue(element.classList.contains("et2_clickable"),
				"sanity check: a plain onclick does add the class, same as a real row widget");

			element.disableClickAction = true;
			await element.updateComplete;

			assert.isNull(element.onclick);
			assert.isFalse(element.classList.contains("et2_clickable"));
		});
	});
});
