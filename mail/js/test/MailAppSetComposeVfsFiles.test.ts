import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail app.ts tests do, before app.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import type {MailApp} from "../app";
import {Et2Dialog} from "../../../api/js/etemplate/Et2Dialog/Et2Dialog";

// see ComposeMessageAccId.test.ts for why app.ts is loaded through its explicit source path
const APP_SOURCE = '/mail/js/app.ts';

/**
 * Regression coverage for a real report (2026-09-10): filemanager "share -> mail -> attach" into
 * an ALREADY-OPEN compose popup (egw.openWithinWindow()'s "Select an opened dialog" chooser
 * calling MailApp.setCompose()) hung that popup on its "please wait" prompt for ever.
 *
 * setCompose() only handled the forward-as-attachment content shape ({data:{emails:{ids}}})
 * client-side; the VFS-files shape ({data:{files:{file:[...]}}} - filemanager, addressbook
 * vCard-attach, a mail attachment re-attached via ajax_vfsOpen) fell through to the classic
 * appendix_data + submit() postback, which the client-side compose (mail/compose.php) has nothing
 * to answer. In JMAP mode the files must go through MailCompose.applyPresetFiles(), the same
 * jmapVfsPath-marker route composeWithPreset({files}) takes for a fresh popup.
 *
 * Setup: setCompose() only touches `this.compose` and the compose window's etemplate2, so the
 * app object is a bare Object.create(MailApp.prototype), the window a hand-built stub.
 */
describe('MailApp.setCompose() with VFS files', () =>
{
	let MailAppClass : typeof MailApp;
	let app : MailApp;
	let compose : any;
	let submit : sinon.SinonSpy;
	let appendix : {set_value : sinon.SinonSpy};
	let filemode : any;
	let composeWindow : any;

	before(async function()
	{
		this.timeout(15000);
		MailAppClass = (await import(APP_SOURCE)).MailApp;
	});

	beforeEach(() =>
	{
		(<any>window).egw = {preference: () => '', lang: (s : string) => s, debug: () => {}};
		submit = sinon.spy();
		appendix = {set_value: sinon.spy()};
		filemode = {get_value: () => 'attach', set_value: sinon.spy(), select_options: [
			{value: 'attach', label: 'Attachment'}, {value: 'link', label: 'Download link'},
		]};
		const widgets : any = {appendix_data: appendix, filemode};
		composeWindow = {
			closed: false,
			etemplate2: {getByApplication: () => [{
				widgetContainer: {
					getWidgetById: (id : string) => widgets[id],
					getInstanceManager: () => ({submit}),
				},
				submit,
			}]},
		};
		compose = {isJmapModeActive: true, applyPresetFiles: sinon.spy(), applyPresetFilemode: sinon.spy(), mergeForwardAttachments: sinon.spy()};

		app = Object.create(MailAppClass.prototype);
		Object.assign(app, {appname: 'mail', egw: (<any>window).egw});
		// MailApp.compose is a prototype getter over the loaded etemplate - shadow it
		Object.defineProperty(app, 'compose', {value: compose, configurable: true});
	});

	it('merges the files client-side in JMAP mode, never through the classic postback', () =>
	{
		const result = app.setCompose(composeWindow, {data: {files: {
			file: ['vfs://default/home/asig/certs/pw_atza_cert.odt'],
			name: ['pw_atza_cert.odt'],
			type: ['application/vnd.oasis.opendocument.text'],
			filemode: 'attach',
		}}});

		assert.isTrue(result);
		assert.isTrue(compose.applyPresetFiles.calledOnce);
		assert.deepEqual(compose.applyPresetFiles.firstCall.args[0], [{
			path: 'vfs://default/home/asig/certs/pw_atza_cert.odt',
			name: 'pw_atza_cert.odt',
			type: 'application/vnd.oasis.opendocument.text',
		}]);
		// the hang: submit() had nothing to talk to
		assert.isFalse(submit.called, 'no classic postback');
		assert.isFalse(appendix.set_value.called, 'appendix_data untouched');
	});

	it('asks before switching the filemode, then applies the mode as an explicit choice plus the files', () =>
	{
		const showDialog = sinon.stub(Et2Dialog, 'show_dialog');
		try
		{
			const result = app.setCompose(composeWindow, {data: {files: {file: ['vfs://default/home/asig/a.pdf'], filemode: 'link'}}});

			assert.isTrue(result);
			assert.isTrue(showDialog.calledOnce, 'the yes/no question');
			assert.isFalse(compose.applyPresetFiles.called, 'nothing merged before the answer');
			assert.isFalse(submit.called);

			showDialog.firstCall.args[0](Et2Dialog.NO_BUTTON);
			assert.isFalse(compose.applyPresetFiles.called, 'No: nothing happens');
			assert.isFalse(compose.applyPresetFilemode.called);

			showDialog.firstCall.args[0](Et2Dialog.YES_BUTTON);
			assert.isTrue(compose.applyPresetFilemode.calledOnceWith('link'), 'the mode counts as chosen by the user');
			assert.isTrue(compose.applyPresetFiles.calledOnce);
			assert.isTrue(compose.applyPresetFilemode.calledBefore(compose.applyPresetFiles));
		}
		finally
		{
			showDialog.restore();
		}
	});

	it('does not ask when the requested filemode is the current one', () =>
	{
		const showDialog = sinon.stub(Et2Dialog, 'show_dialog');
		try
		{
			app.setCompose(composeWindow, {data: {files: {file: ['vfs://default/home/asig/a.pdf'], filemode: 'attach'}}});

			assert.isFalse(showDialog.called);
			assert.isFalse(compose.applyPresetFilemode.called);
			assert.isTrue(compose.applyPresetFiles.calledOnce);
		}
		finally
		{
			showDialog.restore();
		}
	});

	it('falls back to the basename and a generic mime when a caller sends only paths', () =>
	{
		app.setCompose(composeWindow, {data: {files: {file: ['vfs://default/apps/addressbook/12/.entry', 'vfs://default/home/asig/a.pdf']}}});

		assert.deepEqual(compose.applyPresetFiles.firstCall.args[0], [
			{path: 'vfs://default/apps/addressbook/12/.entry', name: '.entry', type: 'application/octet-stream'},
			{path: 'vfs://default/home/asig/a.pdf', name: 'a.pdf', type: 'application/octet-stream'},
		]);
	});

	it('keeps the classic appendix_data + submit() postback for a non-JMAP popup', () =>
	{
		compose.isJmapModeActive = false;

		app.setCompose(composeWindow, {data: {files: {file: ['vfs://default/home/asig/a.pdf'], filemode: 'attach'}}});

		assert.isFalse(compose.applyPresetFiles.called);
		assert.isTrue(appendix.set_value.calledOnce);
		assert.isTrue(submit.calledOnce);
	});

	it('still handles forward-as-attachment ids first', () =>
	{
		app.setCompose(composeWindow, {data: {emails: {ids: 'mail::1::2::3::4,mail::1::2::3::5'}}});

		assert.isTrue(compose.mergeForwardAttachments.calledOnceWith(['mail::1::2::3::4', 'mail::1::2::3::5']));
		assert.isFalse(compose.applyPresetFiles.called);
		assert.isFalse(submit.called);
	});

	it('vfsFilesFromComposeContent() tolerates a missing or malformed files entry', () =>
	{
		assert.deepEqual(app.vfsFilesFromComposeContent(undefined), []);
		assert.deepEqual(app.vfsFilesFromComposeContent({}), []);
		assert.deepEqual(app.vfsFilesFromComposeContent({file: 'not-an-array'}), []);
		assert.deepEqual(app.vfsFilesFromComposeContent({file: ['', 'vfs://default/x/y.txt']}),
			[{path: 'vfs://default/x/y.txt', name: 'y.txt', type: 'application/octet-stream'}]);
	});
});
