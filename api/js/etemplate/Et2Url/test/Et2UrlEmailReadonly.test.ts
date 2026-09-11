/**
 * EGroupware eTemplate2 - Readonly email URL widget tests
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {assert, fixture, html} from "@open-wc/testing";
import {Et2UrlEmailReadonly} from "../Et2UrlEmailReadonly";

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
});
