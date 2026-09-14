import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";

/**
 * MailApp.safeOpener() (private static) - and the `jmap` getter that crashed live because of its
 * absence. Found live 2026-09-14 (ralf, relaying a real user's report): logging into the mail app
 * threw "Failed to read a named property 'app' from 'Window': Blocked a frame with origin ...
 * from accessing a cross-origin frame" right inside the MailApp constructor, and persisted across
 * logout/login - window.opener is a property of the browser TAB itself (set when the tab was
 * originally opened via a link/window.open() from an unrelated, different-origin site without
 * rel="noopener"), unrelated to the egroupware session. Reading any custom property off such a
 * cross-origin Window (even via optional chaining) throws a SecurityError rather than returning
 * undefined - only a small spec-allowlisted set of properties (closed, location, ...) are exempt.
 */
const APP_SOURCE = '/mail/js/app.ts';

describe('MailApp.safeOpener()', () =>
{
	let MailAppClass : typeof MailApp;
	let originalOpener : any;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		originalOpener = (window as any).opener;
	});

	afterEach(() =>
	{
		Object.defineProperty(window, 'opener', {value : originalOpener, configurable : true, writable : true});
	});

	function safeOpener(read : (opener : Window) => any) : any
	{
		return (MailAppClass as any).safeOpener(read);
	}

	it('returns undefined when there is no opener', () =>
	{
		Object.defineProperty(window, 'opener', {value : null, configurable : true});

		assert.isUndefined(safeOpener((opener) => (opener as any).app));
	});

	it('returns undefined when the opener is closed', () =>
	{
		Object.defineProperty(window, 'opener', {value : {closed : true}, configurable : true});

		assert.isUndefined(safeOpener((opener) => (opener as any).app));
	});

	it('returns undefined (not throwing) when reading a cross-origin opener throws a SecurityError', () =>
	{
		const crossOriginOpener = {
			closed : false,
			get app() : any { throw new DOMException("Blocked a frame with origin ... from accessing a cross-origin frame.", "SecurityError"); },
		};
		Object.defineProperty(window, 'opener', {value : crossOriginOpener, configurable : true});

		assert.isUndefined(safeOpener((opener) => (opener as any).app?.mail?.jmap));
	});

	it('returns read()\'s result for a normal, same-origin opener', () =>
	{
		const jmap = {fetchRows : sinon.spy()};
		Object.defineProperty(window, 'opener', {value : {closed : false, app : {mail : {jmap}}}, configurable : true});

		assert.strictEqual(safeOpener((opener) => (opener as any).app?.mail?.jmap), jmap);
	});

	describe('jmap getter', () =>
	{
		it('falls back to building its own MailJmap instead of throwing, for a cross-origin opener', () =>
		{
			const crossOriginOpener = {
				closed : false,
				get app() : any { throw new DOMException("cross-origin", "SecurityError"); },
			};
			Object.defineProperty(window, 'opener', {value : crossOriginOpener, configurable : true});
			(window as any).app = {};

			const egw = {user : (_key : string) => 1, preference : () => null, request : async() => ({})};
			const app = Object.create(MailAppClass.prototype);
			Object.assign(app, {appname : 'mail', egw});

			assert.doesNotThrow(() => app.jmap);
			assert.isDefined(app.jmap);
		});
	});
});
