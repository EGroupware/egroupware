import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";
import {Et2Dialog} from "../../../api/js/etemplate/Et2Dialog/Et2Dialog";

/**
 * Direct coverage for MailApp.markOpenedMessageRead() - doc/ai/projects/mail-test-coverage.md's
 * priority-4 entry. MailMobileViewFlag.test.ts already exercises this method's normal JMAP-flagging
 * path indirectly (through mobileView()/openMessage()), but not its own guard conditions or the
 * MDN (read-receipt) prompt branch - both genuinely untested until now, and both have real
 * "silently wrong" risk: a broken guard could re-flag an already-read message on every open, and a
 * broken MDN gate could either never prompt for a receipt request or re-prompt one already
 * answered.
 */

const ROW_ID = 'mail::1::2::SU5CT1gvdGVzdDM=::42';

function createMailApp()
{
	const app = Object.create(MailApp.prototype) as MailApp;
	const patched : string[] = [];
	const reduced : number[] = [];
	const seen : {references : any[], keyword : string, value : boolean}[] = [];
	const trySetMdn : {messages : any, sent : boolean}[] = [];

	Object.assign(app, {
		egw: {lang: (label : string) => label},
		patchRow: (rowId : string) => void patched.push(rowId),
		reduceCounterWithoutServerRoundtrip: () => void reduced.push(1),
		trySetMdnFlag: (messages : any, sent : boolean) => void trySetMdn.push({messages, sent}),
		// class field initializers (Map, etc.) never run via Object.create() - only a real
		// constructor call sets them up, which EgwApp's own constructor can't do here
		pendingReadMark: new Map(),
	});
	Object.defineProperty(app, 'jmap', {value: {
		messageReference: (id : string) => ({id}),
		setSystemFlag: (references : any[], keyword : string, value : boolean) =>
		{
			seen.push({references, keyword, value});
			return Promise.resolve();
		}
	}, configurable: true});

	return {app, patched, reduced, seen, trySetMdn};
}

/** A jmap whose messageReference() throws, matching a non-JMAP (classic-only) row id. */
function createNonJmapMailApp()
{
	const {app, patched, reduced} = createMailApp();
	Object.defineProperty(app, 'jmap', {value: {
		messageReference: () => { throw new Error('not a JMAP row'); },
		setSystemFlag: () => { throw new Error('must never be called for a non-JMAP row'); },
	}, configurable: true});
	return {app, patched, reduced};
}

function invoke(app : MailApp, rowId : string, data : any) : void
{
	(app as any).markOpenedMessageRead(rowId, data);
}

describe("MailApp.markOpenedMessageRead() - early-return guards", () =>
{
	let originalJsonq;
	let queued : {menuaction : string, parameters : any[]}[];

	beforeEach(() =>
	{
		queued = [];
		originalJsonq = egw.jsonq;
		//@ts-ignore
		egw.jsonq = (menuaction : string, parameters : any[]) => void queued.push({menuaction, parameters});
	});

	afterEach(() => { egw.jsonq = originalJsonq; });

	it("does nothing when data is undefined", () =>
	{
		const {app, patched, seen} = createMailApp();

		invoke(app, ROW_ID, undefined);

		assert.isEmpty(patched);
		assert.isEmpty(seen);
		assert.isEmpty(queued);
	});

	it("does nothing when data has no class property at all", () =>
	{
		const {app, patched, seen} = createMailApp();

		invoke(app, ROW_ID, {flags: {}});

		assert.isEmpty(patched);
		assert.isEmpty(seen);
		assert.isEmpty(queued);
	});

	it("does nothing when class contains neither 'unseen' nor 'recent'", () =>
	{
		const {app, patched, seen} = createMailApp();

		invoke(app, ROW_ID, {class: 'flagged important', flags: {}});

		assert.isEmpty(patched);
		assert.isEmpty(seen);
		assert.isEmpty(queued);
	});
});

describe("MailApp.markOpenedMessageRead() - marking as read", () =>
{
	let originalJsonq;
	let queued : {menuaction : string, parameters : any[]}[];

	beforeEach(() =>
	{
		queued = [];
		originalJsonq = egw.jsonq;
		//@ts-ignore
		egw.jsonq = (menuaction : string, parameters : any[]) => void queued.push({menuaction, parameters});
	});

	afterEach(() => { egw.jsonq = originalJsonq; });

	it("marks read, patches the row and persists $seen via JMAP when class contains 'unseen'", () =>
	{
		const {app, patched, reduced, seen} = createMailApp();
		const data : any = {class: 'unseen', flags: {}};

		invoke(app, ROW_ID, data);

		assert.equal(data.flags.read, 'read');
		assert.equal(data.class, '');
		assert.deepEqual(patched, [ROW_ID]);
		assert.equal(reduced.length, 1);
		assert.deepEqual(seen, [{references: [{id: ROW_ID}], keyword: '$seen', value: true}]);
		assert.deepInclude(queued, {
			menuaction: 'mail.EGroupware\\Mail\\Ui.ajax_flagMessages',
			parameters: ['read', {msg: [ROW_ID]}, false],
		});
	});

	it("marks read the same way when class contains 'recent' instead of 'unseen'", () =>
	{
		const {app, seen} = createMailApp();
		const data : any = {class: 'recent', flags: {}};

		invoke(app, ROW_ID, data);

		assert.equal(data.class, '');
		assert.equal(seen.length, 1);
	});

	it("strips only 'unseen'/'recent', keeping every other class name", () =>
	{
		const {app} = createMailApp();
		const data : any = {class: 'unseen flagged important', flags: {}};

		invoke(app, ROW_ID, data);

		assert.equal(data.class, 'flagged important');
	});

	it("does not throw when data.flags is entirely absent", () =>
	{
		const {app, patched} = createMailApp();

		assert.doesNotThrow(() => invoke(app, ROW_ID, {class: 'unseen'}));
		assert.deepEqual(patched, [ROW_ID]);
	});

	it("still applies the local class/patchRow side effects and the classic jsonq fallback for a non-JMAP row, but never calls setSystemFlag", () =>
	{
		const {app, patched} = createNonJmapMailApp();
		const data : any = {class: 'unseen', flags: {}};

		assert.doesNotThrow(() => invoke(app, ROW_ID, data));

		assert.equal(data.class, '');
		assert.deepEqual(patched, [ROW_ID]);
		assert.deepInclude(queued, {
			menuaction: 'mail.EGroupware\\Mail\\Ui.ajax_flagMessages',
			parameters: ['read', {msg: [ROW_ID]}, false],
		});
	});

	it("tracks the pending read-mark promise until it settles", async() =>
	{
		const {app} = createMailApp();
		const data : any = {class: 'unseen', flags: {}};

		invoke(app, ROW_ID, data);

		assert.isTrue((app as any).pendingReadMark.has(ROW_ID), "must be tracked synchronously, before the JMAP call has resolved");
		await new Promise((resolve) => setTimeout(resolve, 0));
		assert.isFalse((app as any).pendingReadMark.has(ROW_ID), "must be cleared again once the JMAP call has settled");
	});
});

describe("MailApp.markOpenedMessageRead() - MDN (read-receipt) prompt gating", () =>
{
	let originalJsonq;
	let originalShowDialog;
	let queued : {menuaction : string, parameters : any[]}[];
	let dialogCalls : any[];

	beforeEach(() =>
	{
		queued = [];
		dialogCalls = [];
		originalJsonq = egw.jsonq;
		//@ts-ignore
		egw.jsonq = (menuaction : string, parameters : any[]) => void queued.push({menuaction, parameters});
		originalShowDialog = Et2Dialog.show_dialog;
		//@ts-ignore
		Et2Dialog.show_dialog = (...args : any[]) => void dialogCalls.push(args);
	});

	afterEach(() =>
	{
		egw.jsonq = originalJsonq;
		Et2Dialog.show_dialog = originalShowDialog;
	});

	it("prompts for a read receipt when dispositionnotificationto is set and neither MDN flag is set yet", () =>
	{
		const {app} = createMailApp();

		invoke(app, ROW_ID, {class: 'unseen', flags: {}, dispositionnotificationto: 'sender@example.org'});

		assert.equal(dialogCalls.length, 1);
	});

	it("does not prompt again once mdnsent is already set", () =>
	{
		const {app} = createMailApp();

		invoke(app, ROW_ID, {class: 'unseen', flags: {mdnsent: true}, dispositionnotificationto: 'sender@example.org'});

		assert.isEmpty(dialogCalls);
	});

	it("does not prompt again once mdnnotsent is already set", () =>
	{
		const {app} = createMailApp();

		invoke(app, ROW_ID, {class: 'unseen', flags: {mdnnotsent: true}, dispositionnotificationto: 'sender@example.org'});

		assert.isEmpty(dialogCalls);
	});

	it("does not prompt when dispositionnotificationto is absent", () =>
	{
		const {app} = createMailApp();

		invoke(app, ROW_ID, {class: 'unseen', flags: {}});

		assert.isEmpty(dialogCalls);
	});
});
