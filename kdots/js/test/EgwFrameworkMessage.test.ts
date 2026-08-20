import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import '../EgwFrameworkMessage';
import {EgwFrameworkMessage} from '../EgwFrameworkMessage';

/**
 * These cover the type / auto-close-duration contract of <egw-message>.
 *
 * The element has to resolve an empty type itself: egw.message()'s _type defaults to "",
 * and its no-framework fallback path passes that straight through.  An empty type is not
 * one of the declared values, so render() would skip the 5s success default and emit an
 * sl-alert with no duration at all - a toast that never auto-closes.
 */
describe('EgwFrameworkMessage type & duration', () =>
{
	const alertOf = (el : EgwFrameworkMessage) => el.shadowRoot.querySelector('sl-alert');

	it('resolves an empty type to success, and auto-closes after 5s', async() =>
	{
		const el : EgwFrameworkMessage = await fixture(html`
            <egw-message .message=${"InfoLog entry saved"} .type=${""}></egw-message>`);

		assert.equal(el.type, 'success', 'empty type resolved to success');
		assert.equal(alertOf(el).getAttribute('duration'), '5000', 'gets the success auto-close');
	});

	it('resolves an empty type on a failure message to error, which does NOT auto-close', async() =>
	{
		const el : EgwFrameworkMessage = await fixture(html`
            <egw-message .message=${"Error: could not save"} .type=${""}></egw-message>`);

		assert.equal(el.type, 'error');
		assert.isFalse(alertOf(el).hasAttribute('duration'), 'errors wait for the user');
	});

	it('leaves an explicitly given type alone', async() =>
	{
		const el : EgwFrameworkMessage = await fixture(html`
            <egw-message .message=${"Something to look at"} .type=${"warning"}></egw-message>`);

		assert.equal(el.type, 'warning', 'not re-sniffed as success');
		assert.isFalse(alertOf(el).hasAttribute('duration'), 'warnings wait for the user');
	});

	it('keeps an explicit duration on a success message', async() =>
	{
		const el : EgwFrameworkMessage = await fixture(html`
            <egw-message .message=${"Saved"} .type=${"success"} .duration=${30}></egw-message>`);

		assert.equal(alertOf(el).getAttribute('duration'), '30');
	});

	it('detectType() only treats error-ish text as an error', () =>
	{
		assert.equal(EgwFrameworkMessage.detectType('InfoLog entry saved'), 'success');
		assert.equal(EgwFrameworkMessage.detectType('Permission denied!'), 'success');
		assert.equal(EgwFrameworkMessage.detectType('An error occurred'), 'error');
		assert.equal(EgwFrameworkMessage.detectType(''), 'success');
		assert.equal(EgwFrameworkMessage.detectType(undefined), 'success');
	});

	describe('restartAutoHide()', () =>
	{
		// sl-alert is not upgraded in this environment, so drive the delegation through a
		// stand-in for the inner alert rather than Shoelace's own (private) method
		const withFakeAlert = (el : EgwFrameworkMessage, fake : any) =>
			Object.defineProperty(el, 'alert', {get: () => fake, configurable: true});

		it('re-arms the underlying sl-alert', async() =>
		{
			const el : EgwFrameworkMessage = await fixture(html`
                <egw-message .message=${"Saved"} .type=${"success"}></egw-message>`);
			const restartAutoHide = sinon.stub();
			withFakeAlert(el, {restartAutoHide});

			el.restartAutoHide();

			assert.isTrue(restartAutoHide.calledOnce);
		});

		it('does nothing rather than throwing when the alert cannot be re-armed', async() =>
		{
			const el : EgwFrameworkMessage = await fixture(html`
                <egw-message .message=${"Saved"} .type=${"success"}></egw-message>`);

			withFakeAlert(el, undefined);
			assert.doesNotThrow(() => el.restartAutoHide(), 'no alert at all');

			// eg. a Shoelace release that renames it
			withFakeAlert(el, {});
			assert.doesNotThrow(() => el.restartAutoHide(), 'alert without the method');
		});
	});
});
