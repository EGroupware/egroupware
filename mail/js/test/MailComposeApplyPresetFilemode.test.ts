import {assert} from "@open-wc/testing";
import * as sinon from "sinon";
import "./MailAppImportStub";
// breaks the et2_core_widget <-> Et2Widget import cycle (ClassWithAttributes TDZ) the same
// way the other mail tests do, before compose.ts pulls it in transitively
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailCompose} from "../compose";

/**
 * MailCompose.applyPresetFilemode(): a "send files as" mode chosen in ANOTHER app (filemanager's
 * Download link / Readonly share / Writable share actions, preset.filemode for a fresh popup or
 * MailApp.setCompose()'s confirmed yes/no question for an open one) has to count as the user's
 * explicit choice, or currentEmailFields() keeps sending the files as plain attachments: it only
 * ever converts to share links when `explicitShareModeChosen` is set, and a programmatic
 * set_value() on the widget never sets it (found live 2026-09-10 - widget said "link", the flag
 * stayed false).
 *
 * Setup: a bare MailCompose over a fake app and a hand-built et2 with the four widgets
 * checkSharingFilemode() touches. egw.app('stylite') is switchable: the expiration/password
 * fields only ever unlock with EPL, and without it share_rw is downgraded to share_ro, exactly
 * as checkSharingFilemode() does for a genuine pick.
 */
describe('MailCompose.applyPresetFilemode()', () =>
{
	let compose : MailCompose;
	let filemode : any;
	let expiration : any;
	let password : any;
	let messages : string[];
	let hasStylite : boolean;

	beforeEach(() =>
	{
		messages = [];
		hasStylite = false;
		let value = 'attach';
		filemode = {
			get_value: () => value,
			set_value: sinon.spy((v : string) => { value = v; }),
			select_options: [{value: 'attach', label: 'Attachment'}, {value: 'link', label: 'Download link'}],
		};
		expiration = {set_readonly: sinon.spy()};
		password = {set_readonly: sinon.spy(), set_suggest: sinon.spy()};
		const widgets : any = {filemode, expiration, password};
		const et2 = {
			getWidgetById: (id : string) => widgets[id],
			getArrayMgr: () => ({getEntry: () => undefined}),
		};
		const egw = {
			app: (name : string) => name === 'stylite' && hasStylite,
			lang: (s : string) => s,
			message: (m : string) => { messages.push(m); },
		};
		compose = new MailCompose({egw} as any);
		(compose as any).et2 = et2;
	});

	it('sets the widget, unlocks expiration/password (EPL) and marks the share mode as explicitly chosen', () =>
	{
		hasStylite = true;
		compose.applyPresetFilemode('link');

		assert.isTrue(filemode.set_value.calledOnceWith('link'));
		assert.equal(filemode.get_value(), 'link');
		assert.isTrue(expiration.set_readonly.calledWith(false));
		assert.isTrue(password.set_readonly.calledWith(false));
		assert.isTrue((compose as any).explicitShareModeChosen, 'what the send path gates on');
	});

	it('a plain "attach" keeps the fields locked and the flag off', () =>
	{
		hasStylite = true;
		compose.applyPresetFilemode('attach');

		assert.isFalse(filemode.set_value.called, 'already attach, nothing to set');
		assert.isTrue(expiration.set_readonly.calledWith(true));
		assert.isFalse((compose as any).explicitShareModeChosen);
	});

	it('going back to "attach" after a share mode clears the flag again', () =>
	{
		compose.applyPresetFilemode('link');
		compose.applyPresetFilemode('attach');

		assert.equal(filemode.get_value(), 'attach');
		assert.isFalse((compose as any).explicitShareModeChosen);
	});

	it('without EPL the fields stay locked even for a share mode', () =>
	{
		compose.applyPresetFilemode('link');

		assert.equal(filemode.get_value(), 'link');
		assert.isTrue(expiration.set_readonly.calledWith(true));
		assert.isTrue((compose as any).explicitShareModeChosen);
	});

	it('share_rw without EPL is downgraded to share_ro, and still counts as chosen', () =>
	{
		compose.applyPresetFilemode('share_rw');

		assert.equal(filemode.get_value(), 'share_ro');
		assert.isTrue((compose as any).explicitShareModeChosen);
		assert.lengthOf(messages, 1, 'the "requires EPL" notice');
	});

	it('ignores an empty mode and a missing widget', () =>
	{
		compose.applyPresetFilemode('');
		assert.isFalse(filemode.set_value.called);

		(compose as any).et2 = {getWidgetById: () => undefined, getArrayMgr: () => ({getEntry: () => undefined})};
		compose.applyPresetFilemode('link');
		assert.isFalse((compose as any).explicitShareModeChosen);
	});

	describe('applyPresetFiles() path normalisation', () =>
	{
		it('strips the vfs://default url prefix the classic preset params carry, keeps a bare path', () =>
		{
			const merged : any[] = [];
			(compose as any).mergeAttachmentEntries = (entries : any[]) => { merged.push(...entries); };

			compose.applyPresetFiles([
				{path: 'vfs://default/apps/acemailstor/494433/original_data/raw/raw_mail.eml', name: 'raw_mail.eml', type: 'message/rfc822'},
				{path: '/home/asig/a.pdf', name: 'a.pdf', type: 'application/pdf'},
			]);

			assert.deepEqual(merged.map((e) => e.jmapVfsPath),
				['/apps/acemailstor/494433/original_data/raw/raw_mail.eml', '/home/asig/a.pdf']);
			assert.deepEqual(merged.map((e) => e.name), ['raw_mail.eml', 'a.pdf']);
		});

		it('vfsPathFromPreset() only strips the exact prefix in front of a slash', () =>
		{
			assert.equal(MailCompose.vfsPathFromPreset('vfs://default/x/y'), '/x/y');
			assert.equal(MailCompose.vfsPathFromPreset('/x/y'), '/x/y');
			assert.equal(MailCompose.vfsPathFromPreset('vfs://defaultx/y'), 'vfs://defaultx/y');
			assert.equal(MailCompose.vfsPathFromPreset(''), '');
		});
	});
});
