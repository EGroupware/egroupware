import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./CalendarAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {CalendarApp} from "../app";

/** See ComposeMeetingMail.test.ts for why app.ts is imported through its explicit source path. */
const APP_SOURCE = '/calendar/js/app.ts';

/**
 * Contract: CalendarApp._fetch_group_members() adds the group participants it fetches to the sidebox
 * owner widget's options, and adds nothing else.
 *
 * Why it matters: the owner widget's select_options getter returns its own options plus the search
 * results currently shown, which come back grouped by app (option groups with an array value).  When
 * those groups were written back as options, every later search of the widget failed matching against
 * them, before it sent its request - the search did nothing until the page was reloaded.
 *
 * Setup: a bare Object.create(CalendarApp.prototype) with a stubbed egw.request(), and a stand-in owner
 * widget whose select_options getter / setter behave like the real one: the getter adds the current
 * search results, the setter replaces only the widget's own options.
 *
 * Pass criteria: after fetching a group, the widget's own options are the ones it had plus the fetched
 * group with its members, and no option group; a group that already has its members is not fetched.
 *
 * Environment: no network.
 */
describe('CalendarApp._fetch_group_members()', () =>
{
	let app : any;
	let request : sinon.SinonStub;
	let owner : any;
	let CalendarAppClass : typeof CalendarApp;

	before(async function()
	{
		this.timeout(10000);
		CalendarAppClass = (await import(APP_SOURCE)).CalendarApp;
	});

	beforeEach(() =>
	{
		owner = {
			own: [
				{value: "9", label: "Me"},
				{value: "-1", label: "Default", resources: ["9"]}
			],
			searchResults: [
				{label: "api-accounts", value: [{value: "6", label: "Demo, Account"}]},
				{label: "addressbook", value: [{value: "c12", label: "Somebody"}]}
			],
			get select_options() { return [...this.own, ...this.searchResults]; },
			set select_options(options) { this.own = options; }
		};
		request = sinon.stub();
		app = Object.create(CalendarAppClass.prototype);
		Object.assign(app, {
			appname: 'calendar',
			egw: {request},
			_group_query_cache: {},
			sidebox_et2: {getWidgetById: (id) => id === 'owner' ? owner : null}
		});
	});

	it('adds the fetched group, but not the search results shown at the time', async() =>
	{
		request.resolves({"-1171": {value: "-1171", label: "Group AG", app: "api-accounts", resources: ["9", "6"]}});

		await app._fetch_group_members({participants: {"-1171": "A", "9": "A"}});

		assert.isTrue(request.calledOnceWithExactly("calendar.calendar_owner_etemplate_widget.ajax_owner", [[-1171]]));
		assert.deepEqual(owner.own.map(o => o.value), ["9", "-1", "-1171"]);
		assert.isFalse(owner.own.some(o => Array.isArray(o.value)), "search result groups became options");
		assert.deepEqual(owner.own.find(o => o.value === "-1171").resources, ["9", "6"]);
	});

	it('does not fetch a group whose members it already has', async() =>
	{
		assert.isNull(await app._fetch_group_members({participants: {"-1": "A", "9": "A"}}));
		assert.isFalse(request.called);
	});
});
