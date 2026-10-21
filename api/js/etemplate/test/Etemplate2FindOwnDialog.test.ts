import {assert} from "@open-wc/testing";
import {etemplate2} from "../etemplate2";

/**
 * Ticket #126711 (2026-10-20, a real customer, via Birgit) - see MailComposeCloseAfterSend.test.ts
 * (mail/js/test/) for the full root-cause writeup, which independently hit the identical mistake.
 * findOwnDialog() backs handle_load()'s window-close/refresh-opener handling: a dialog needs
 * closing via ITS OWN dialog.close(), never window.close()/egw.close() (both no-ops for an inline
 * <et2-dialog> rendered in the SAME window a narrow-viewport/"open_popups_in: same_window" popup
 * uses instead of a real separate window).
 *
 * Extracted purely for unit testability - handle_load() itself handles too many unrelated response
 * shapes to drive directly in a focused test.
 */
describe("etemplate2.findOwnDialog()", () =>
{
	const findOwnDialog = (etemplate2 as any).findOwnDialog.bind(etemplate2);

	it("returns undefined when there's no <et2-dialog> ancestor (a real popup window)", () =>
	{
		const container = document.createElement('div');
		document.body.append(container);
		try
		{
			assert.isUndefined(findOwnDialog({DOMContainer : container}));
		}
		finally
		{
			container.remove();
		}
	});

	it("returns undefined for a nullish/incomplete instance, without throwing", () =>
	{
		assert.isUndefined(findOwnDialog(undefined));
		assert.isUndefined(findOwnDialog({}));
	});

	it("finds the <et2-dialog> ancestor through multiple levels of wrapper elements - the exact DOM shape live-verified for #126711 (<et2-dialog><div class=\"dialog_content\"><et2-template>...), no <form> anywhere", () =>
	{
		const dialog = document.createElement('et2-dialog');
		const content = document.createElement('div');
		content.className = 'dialog_content dialog--has_template';
		const template = document.createElement('et2-template');
		const container = document.createElement('div');
		dialog.append(content);
		content.append(template);
		template.append(container);
		document.body.append(dialog);
		try
		{
			assert.strictEqual(findOwnDialog({DOMContainer : container}), dialog);
		}
		finally
		{
			dialog.remove();
		}
	});
});
