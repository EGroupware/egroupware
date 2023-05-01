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

describe('Et2EmailTag', () =>
{
	let component : Et2EmailTag;

	beforeEach(async() =>
	{
		component = await fixture<Et2EmailTag>(html`
            <et2-email-tag value="test@example.com"></et2-email-tag>`);
		await component.updateComplete;
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

	it('should have an onlyEmail property', () =>
	{
		assert.isFalse(component.onlyEmail);
	});

	it('should have a fullEmail property', () =>
	{
		assert.isFalse(component.fullEmail);
	});

	it('should open addressbook with email preset on (+) click', () =>
	{
		component.egw = () => ({
			open: (url, app, mode, extra) =>
			{
				assert.equal(url, '');
				assert.equal(app, 'addressbook');
				assert.equal(mode, 'add');
				assert.equal(extra['presets[email]'], 'test@example.com');
			}
		});
		component.handleClick(new MouseEvent('click'));
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
		component.egw = () => ({
			open: (id, app, mode, extra) =>
			{
				assert.equal(id, contact.id);
				assert.equal(app, 'addressbook');
				assert.equal(mode, 'view');
				assert.deepEqual(extra, {title: contact.n_fn, icon: contact.photo});
			}
		});
		await component.handleContactClick(new MouseEvent('click'));
	});
});
