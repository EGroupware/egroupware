import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {setupEgwFrameworkTests} from "./EgwFrameworkTestSetup"
import '../EgwFramework';
import {EgwFramework} from '../EgwFramework';
import {EgwFrameworkApp} from '../EgwFrameworkApp';

// Create common stubs that will be used across tests
const egwStub = {
	window: {
		opener: null,
		egw_ready: Promise.resolve(),
		framework: null
	},
	lang: sinon.stub().callsFake(t => t),
	preference: sinon.stub().resolves(""),
	set_preference: sinon.stub(),
	add_timer: sinon.stub(),
	link_quick_add: sinon.stub(),
	onLogout_timer: sinon.stub().resolves(),
	open_link: sinon.stub(),
	registerJSONPlugin: sinon.stub()
};

describe('EgwFramework', () =>
{
	setupEgwFrameworkTests();
	let element : EgwFramework;
	let sandbox : sinon.SinonSandbox;

	beforeEach(async() =>
	{
		sandbox = sinon.createSandbox();
		// Replace global egw with our stub
		(window as any).egw = egwStub;

		element = await fixture(html`
            <egw-framework>
                <div slot="header">Header content</div>
                <div slot="status">Status content</div>
            </egw-framework>
		`);
	});

	afterEach(() =>
	{
		sandbox.restore();
	});

	// Make sure it works
	it("renders", async() =>
	{
		assert.ok(element);
		assert.instanceOf(element, EgwFramework);
	});

	it('has correct default properties', () =>
	{
		assert.equal(element.layout, 'default');
		assert.isArray(element.applicationList);
		assert.isEmpty(element.applicationList);
	});

	it('loads an app correctly', async() =>
	{
		// Setup test data
		const testApp = {
			name: 'test-app',
			internalName: 'test',
			url: 'https://test.app',
			title: 'Test App',
			icon: 'https://test.app/icon.png',
			status: '1',
			features: {}
		};
		element.applicationList = [testApp];

		// Test loading the app
		const app = element.loadApp('test-app', true);

		assert.instanceOf(app, EgwFrameworkApp);
		assert.equal(app.getAttribute('name'), 'test');
		assert.equal(app.getAttribute('id'), 'test-app');
		assert.equal(app.url, 'https://test.app');
		assert.equal(app.title, 'Test App');
		assert.isTrue(app.hasAttribute('active'));
	});

	it('handles message plugin registration', async() =>
	{
		await element.getEgwComplete();

		assert.isTrue(egwStub.registerJSONPlugin.calledOnce);

		// Get the handler function that was registered
		const handler = egwStub.registerJSONPlugin.firstCall.args[0];

		// Test successful message handling
		assert.isTrue(handler('message', {
			data: {
				message: 'test message',
				type: 'info'
			}
		}));

		// Test error handling
		assert.throws(() =>
		{
			handler('message', {data: {}});
		}, 'Invalid parameters');
	});

	it('loads hidden apps on first update', async() =>
	{
		const hiddenApp = {
			name: 'status',
			status: '5',
			url: 'https://test.app/status'
		};
		element.applicationList = [hiddenApp];

		await element.updateComplete;

		const app = element.querySelector('egw-app[name="status"]');
		assert.exists(app);
		assert.equal(app.getAttribute('id'), 'status');
	});

	it('gets application by name', () =>
	{
		const app = document.createElement('egw-app');
		app.setAttribute('name', 'test-app');
		element.appendChild(app);

		const found = element.getApplicationByName('test-app');
		assert.equal(found, app);
	});

	it('properly handles menuaction generation', () =>
	{
		const result = element.getMenuaction(
			'test',
			'menuaction=app.handler.method',
			'home'
		);

		assert.equal(
			result,
			'home.kdots_framework.test.template.app.handler.method'
		);
	});
});