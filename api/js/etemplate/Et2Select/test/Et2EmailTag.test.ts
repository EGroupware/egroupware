/**
 * EGroupware eTemplate2 - Email Tag WebComponent tests
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {assert, fixture, html} from '@open-wc/testing';
import {Et2EmailTag} from "../Tag/Et2EmailTag";
import * as sinon from 'sinon';

// Stub global egw
// @ts-ignore
window.egw = {
	tooltipUnbind: () => {},
	lang: i => i + "*",
	image: () => "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNS4wLjAsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkViZW5lXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMzJweCIgaGVpZ2h0PSIzMnB4IiB2aWV3Qm94PSIwIDAgMzIgMzIiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDMyIDMyIiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBmaWxsPSIjNjk2OTY5IiBkPSJNNi45NDMsMjguNDUzDQoJYzAuOTA2LDAuNzY1LDIuMDk3LDEuMTI3LDMuMjg2LDEuMTA5YzAuNDMsMC4wMTQsMC44NTItMC4wNjgsMS4yNjUtMC4yMDdjMC42NzktMC4xOCwxLjMyOC0wLjQ1LDEuODY2LTAuOTAyTDI5LjQwMywxNC45DQoJYzEuNzcyLTEuNDk4LDEuNzcyLTMuOTI1LDAtNS40MjJjLTEuNzcyLTEuNDk3LTQuNjQ2LTEuNDk3LTYuNDE4LDBMMTAuMTE5LDIwLjM0OWwtMi4zODktMi40MjRjLTEuNDQtMS40NTctMy43NzItMS40NTctNS4yMTIsMA0KCWMtMS40MzgsMS40Ni0xLjQzOCwzLjgyNSwwLDUuMjgxQzIuNTE4LDIzLjIwNiw1LjQ3NCwyNi45NDcsNi45NDMsMjguNDUzeiIvPg0KPC9zdmc+DQo=",
	webserverUrl: "",
	app: (_app) => _app,
	jsonq: () => Promise.resolve({})
};

describe('Et2EmailTag', () =>
{
	let component : Et2EmailTag;

	beforeEach(async() =>
	{
		component = await fixture<Et2EmailTag>(html`
            <et2-email-tag value="test@example.com"></et2-email-tag>`);
		// Stub egw()
		// @ts-ignore
		sinon.stub(component, "egw").returns(window.egw);
		await component.updateComplete;

		// Asserting this instanceOf forces class loading
		assert.instanceOf(component, Et2EmailTag);
	});

	it('should be defined', () =>
	{
		assert.isDefined(component);
	});

	it('should have a value property', () =>
	{
		assert.equal(component.value, 'test@example.com');
	});

	it('should have a contactPlus property', () =>
	{
		assert.isTrue(component.contactPlus);
	});

	it('should have an emailDisplay property', () =>
	{
		assert.exists(component.emailDisplay);
	});

	it('should open addressbook with email preset on (+) click', () =>
	{
		window.egw.open = () =>
		{
			open: (url, app, mode, extra) =>
			{
				assert.equal(url, '');
				assert.equal(app, 'addressbook');
				assert.equal(mode, 'add');
				assert.equal(extra['presets[email]'], 'test@example.com');
			}
		};
		component.shadowRoot.querySelector("et2-button-icon").dispatchEvent(new MouseEvent('click'));
	});

	it('should open addressbook CRM on avatar click', async() =>
	{
		// Fake data to test against
		const contact = {
			id: '123',
			n_fn: 'Test User',
			photo: 'test.jpg'
		};
		component.value = 'test@example.com';
		component.checkContact = async(email) => contact;
		component.egw.open = () =>
		{
			open: (id, app, mode, extra) =>
			{
				assert.equal(id, contact.id);
				assert.equal(app, 'addressbook');
				assert.equal(mode, 'view');
				assert.deepEqual(extra, {title: contact.n_fn, icon: contact.photo});
			}
		};
		await component.handleContactMouseDown(new MouseEvent('click'));
	});
});

/**
 * Check a bunch of email addresses for correct formatting according to emailDisplay
 */
describe("Email formatting", function()
{
	const runs = [
		// @formatter:off

		// FULL
		{emailDisplay: "full", email: "test@example.com", expected: "test@example.com"},
		{emailDisplay: "full", email: "\"Mr. Test Guy\" <test@example.com>", expected: "Mr. Test Guy <test@example.com>"},

		// No <angle brackets> around email
		{emailDisplay: "full", email: "\"Mr Test Guy\" test@example.com", expected: "\"Mr Test Guy\" test@example.com"},

		// Multiple quotes
		{emailDisplay: "full", email: "\"'EGroupware GmbH | Birgit Becker'\" <bb@egroupware.org>", expected: "EGroupware GmbH | Birgit Becker <bb@egroupware.org>"},

		// EMAIL
		{emailDisplay: "email", email: "test@example.com", expected: "test@example.com"},
		{emailDisplay: "email", email: "\"Mr. Test Guy\" <test@example.com>", expected: "test@example.com"},

		// No <angle brackets> around email
		{emailDisplay: "email", email: "\"Mr Test Guy\" test@example.com", expected: "\"Mr Test Guy\" test@example.com"},

		// Multiple quotes
		{emailDisplay: "email", email: "\"'EGroupware GmbH | Birgit Becker'\" <bb@egroupware.org>", expected: "bb@egroupware.org"},

		// NAME
		{emailDisplay: "name", email: "test@example.com", expected: "test@example.com"},
		{emailDisplay: "name", email: "\"Mr. Test Guy\" <test@example.com>", expected: "Mr. Test Guy"},

		// No <angle brackets> around email
		{emailDisplay: "name", email: "\"Mr Test Guy\" test@example.com", expected: "\"Mr Test Guy\" test@example.com"},

		// Multiple quotes
		{emailDisplay: "name", email: "\"'EGroupware GmbH | Birgit Becker'\" <bb@egroupware.org>", expected: "EGroupware GmbH | Birgit Becker"},


		// DOMAIN
		{emailDisplay: "domain", email: "test@example.com", expected: "test@example.com"},
		{emailDisplay: "domain", email: "\"Mr. Test Guy\" <test@example.com>", expected: "Mr. Test Guy (example.com)"},

		// No <angle brackets> around email
		{emailDisplay: "domain", email: "\"Mr Test Guy\" test@example.com", expected: "\"Mr Test Guy\" test@example.com"},

		// Multiple quotes
		{emailDisplay: "domain", email: "\"'EGroupware GmbH | Birgit Becker'\" <bb@egroupware.org>", expected: "EGroupware GmbH | Birgit Becker (egroupware.org)"},

		// @formatter:on
	];

	// Create component for testing
	let getComponent = async(display, value) =>
	{
		let component = await fixture<Et2EmailTag>(html`
            <et2-email-tag value="${value}" .emailDisplay="${display}"></et2-email-tag>`);
		// Stub egw()
		// @ts-ignore
		sinon.stub(component, "egw").returns(window.egw);
		await component.updateComplete;

		// Asserting this instanceOf forces class loading
		assert.instanceOf(component, Et2EmailTag);
		return component;
	}

	runs.forEach(function(run)
	{
		it(run.emailDisplay + ": " + run.email, async() =>
		{
			const component = await getComponent(run.emailDisplay, run.email);
			const actual = component.shadowRoot.querySelector("[part='content']")?.innerText ?? "*missing*";
			assert.equal(actual, run.expected);
		});
	});
});
