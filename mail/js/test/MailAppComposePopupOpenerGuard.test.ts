import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Ticket #124351 ("clicking reply/forward in a message popup does nothing", live-reproduced by
 * ralf 2026-09-28 with the browser console open): MailApp.openComposePopupUrl()'s narrow-viewport
 * check (`window.matchMedia('(max-width: 800px)').matches`) used to route straight into
 * openComposeDialog() - which builds its <et2-dialog> via loadWebComponent() DIRECTLY IN THE
 * CURRENT WINDOW's own already-open document - whenever that window happened to be narrower than
 * 800px, with no regard for whether that window was itself already a popup. A message-view popup
 * is very often narrower than 800px on its own; forwarding from inside one hit exactly this
 * branch and threw "NotAllowedError: Sharing constructed stylesheets in multiple documents is not
 * allowed" building every nested Lit component (a browser's constructed CSSStyleSheet is bound to
 * whichever document originally built it) - the compose dialog never actually rendered, so
 * nothing visible happened at all when clicking Forward.
 *
 * Fixed by never taking the inline-dialog branch when `window.opener` is set (i.e. the current
 * window is itself already a popup) - such a window can always safely open a further REAL popup
 * instead (a fresh page navigation, its own fresh document/JS realm, immune to this).
 */

function createMailApp()
{
	const app = Object.create(MailApp.prototype) as MailApp;
	const dialogCalls : any[] = [];

	Object.assign(app, {
		egw: {
			lang: (label : string) => label,
			link: (path : string, params : any) => path + '?' + new URLSearchParams(params).toString(),
		},
		openComposeDialog: async(settings : any, accId : string) => void dialogCalls.push({settings, accId}),
	});

	return {app, dialogCalls};
}

function invoke(app : MailApp, settings : any, accId : string) : any
{
	return (app as any).openComposePopupUrl(settings, accId);
}

describe("MailApp.openComposePopupUrl() - inline-dialog vs. real-popup branch", () =>
{
	let originalMatchMedia;
	let originalOpener;
	let originalGetOpenWindows;
	let originalOpenPopup;
	let popupCalls : any[];

	beforeEach(() =>
	{
		originalMatchMedia = window.matchMedia;
		originalOpener = window.opener;
		popupCalls = [];
		originalGetOpenWindows = egw.getOpenWindows;
		originalOpenPopup = egw.openPopup;
		//@ts-ignore
		egw.getOpenWindows = () => [];
		//@ts-ignore
		egw.openPopup = (...args : any[]) => { popupCalls.push(args); return {} as Window; };
	});

	afterEach(() =>
	{
		window.matchMedia = originalMatchMedia;
		Object.defineProperty(window, 'opener', {value: originalOpener, configurable: true});
		egw.getOpenWindows = originalGetOpenWindows;
		egw.openPopup = originalOpenPopup;
	});

	it("uses the inline dialog on a narrow TOP-LEVEL window (window.opener unset)", () =>
	{
		//@ts-ignore
		window.matchMedia = () => ({matches: true});
		Object.defineProperty(window, 'opener', {value: null, configurable: true});
		const {app, dialogCalls} = createMailApp();

		invoke(app, {id: '1', from: 'forward'}, '1');

		assert.lengthOf(dialogCalls, 1);
		assert.isEmpty(popupCalls, 'must not ALSO open a real popup');
	});

	it("opens a real popup instead of the inline dialog on a narrow window that is ITSELF already a popup (ticket #124351)", () =>
	{
		//@ts-ignore
		window.matchMedia = () => ({matches: true});
		Object.defineProperty(window, 'opener', {value: {/* any truthy stand-in for a real opener */}, configurable: true});
		const {app, dialogCalls} = createMailApp();

		invoke(app, {id: '1', from: 'forward'}, '1');

		assert.isEmpty(dialogCalls, 'must never build an <et2-dialog> directly in an already-open popup\'s own document');
		assert.lengthOf(popupCalls, 1);
	});

	it("uses a real popup on a wide window regardless of window.opener", () =>
	{
		//@ts-ignore
		window.matchMedia = () => ({matches: false});
		Object.defineProperty(window, 'opener', {value: null, configurable: true});
		const {app, dialogCalls} = createMailApp();

		invoke(app, {id: '1', from: 'forward'}, '1');

		assert.isEmpty(dialogCalls);
		assert.lengthOf(popupCalls, 1);
	});
});
