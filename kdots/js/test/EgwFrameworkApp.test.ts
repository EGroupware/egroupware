import {assert, fixture, html} from '@open-wc/testing';
import * as sinon from "sinon";

import '../EgwFrameworkApp';
import {EgwFrameworkApp} from '../EgwFrameworkApp';

describe('EgwFrameworkApp', () =>
{
	let element : EgwFrameworkApp;
	let sandbox : sinon.SinonSandbox;

	beforeEach(async() =>
	{
		sandbox = sinon.createSandbox();

		element = await fixture(html`
            <egw-app
                    name="test-app"
                    url="https://test.app"
                    title="Test App"
            ></egw-app>
		`);
	});

	afterEach(() =>
	{
		sandbox.restore();
	});

	it('renders with default properties', () =>
	{
		assert.equal(element.name, 'test-app');
		assert.equal(element.url, 'https://test.app');
		assert.equal(element.title, 'Test App');
		assert.deepEqual(element.features, {});
	});

	it('loads content when url changes', async() =>
	{
		const newUrl = 'https://test.app/new';
		element.url = newUrl;

		await element.updateComplete;

		// You'll need to implement appropriate assertions based on
		// how content loading is handled
	});

	it('handles active state changes', async() =>
	{
		element.setAttribute('active', '');
		await element.updateComplete;

		assert.isTrue(element.hasAttribute('active'));
		// Add assertions for active state visual changes
	});
});