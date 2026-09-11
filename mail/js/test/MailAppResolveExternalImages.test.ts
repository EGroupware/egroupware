import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import {MailApp} from "../app";

/**
 * Coverage for MailApp.resolveExternalImages() - doc/ai/projects/mail-test-coverage.md's
 * priority-4 entry. Genuinely untested until now, with real privacy/security risk: this is the
 * client-side half of the "blocked external image" feature (the server leaves an
 * `alt="[blocked external image:<url>]"` marker instead of a real src for html mail with
 * external images) - a broken guard could bypass an admin-forced "Never allow" preference, or
 * silently show a security-relevant HTTP-vs-HTTPS warning incorrectly.
 */

function createMailApp(imageProxy = 'https://proxy.example.org/')
{
	const app = Object.create(MailApp.prototype) as MailApp;
	Object.assign(app, {
		egw: {lang: (label : string, ...args : string[]) => args.length ? `${label} ${args.join(' ')}` : label},
		image_proxy: imageProxy,
	});
	return app;
}

/** A detached container with one or more blocked-image markers, matching the server's own alt-text convention. */
function containerWithBlockedImages(...urls : string[]) : HTMLElement
{
	const div = document.createElement('div');
	for (const url of urls)
	{
		const img = document.createElement('img');
		img.alt = `[blocked external image:${url}]`;
		div.appendChild(img);
	}
	return div;
}

function banner(node : HTMLElement) : HTMLElement | null
{
	return node.querySelector('.mail_externalImagesMsg');
}

describe("MailApp.resolveExternalImages()", () =>
{
	let originalPreference;
	let originalSetPreference;
	let prefs : Record<string, any>;

	beforeEach(() =>
	{
		prefs = {allowExternalIMGs: 2, allowExternalDomains: {}};
		originalPreference = egw.preference;
		originalSetPreference = egw.set_preference;
		//@ts-ignore
		egw.preference = (name : string) => prefs[name];
		//@ts-ignore
		egw.set_preference = (_app : string, name : string, value : any) => void (prefs[name] = value);
	});

	afterEach(() =>
	{
		egw.preference = originalPreference;
		egw.set_preference = originalSetPreference;
	});

	it("does nothing when there are no blocked-image markers at all", () =>
	{
		const app = createMailApp();
		const node = document.createElement('div');
		node.innerHTML = '<p>no images here</p>';

		app.resolveExternalImages(node);

		assert.isNull(banner(node));
	});

	it("shows the unblock banner for a blocked image under the default 'ask for permission' preference", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node);

		assert.isNotNull(banner(node));
	});

	it("does not create a second banner when one is already showing", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node);
		app.resolveExternalImages(node);

		assert.equal(node.querySelectorAll('.mail_externalImagesMsg').length, 1);
	});

	it("never shows the banner when the preference is forced to 'Never' and show isn't explicitly requested", () =>
	{
		prefs.allowExternalIMGs = 0;
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node);

		assert.isNull(banner(node), "a 'Never' preference must not offer any way to unblock");
	});

	it("shows images directly (no banner at all) when show=true is passed, even under a 'Never' preference", () =>
	{
		prefs.allowExternalIMGs = 0;
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node, true);

		assert.isNull(banner(node), "show=true resolves images directly, without ever creating a banner");
		assert.equal(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png');
	});

	it("resolves images immediately, without a banner, when the domain is already on the allow-list", () =>
	{
		prefs.allowExternalDomains = {a: 'example.org'};
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node);

		assert.isNull(banner(node));
		assert.equal(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png');
	});

	it("routes an http:// image through the configured image proxy, leaving https:// untouched", () =>
	{
		const app = createMailApp('https://proxy.example.org/');
		const node = containerWithBlockedImages('http://insecure.example.org/photo.png');

		app.resolveExternalImages(node, true);

		assert.equal(node.querySelector('img')!.getAttribute('src'), 'https://proxy.example.org/insecure.example.org/photo.png');
	});

	it("shows an extra security warning (red banner) when any blocked image is served over plain HTTP", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('http://insecure.example.org/photo.png');

		app.resolveExternalImages(node);

		assert.isTrue(banner(node)!.classList.contains('red'));
	});

	it("does not add the HTTP security warning class when every blocked image is HTTPS", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node);

		assert.isFalse(banner(node)!.classList.contains('red'));
	});

	it("'Show' resolves the images this time only, without persisting the domain to the allow-list", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');
		app.resolveExternalImages(node);

		const showButton = Array.from(node.querySelectorAll('button'))
			.find((btn) => btn.textContent === app.egw.lang('Show'));
		showButton!.click();

		assert.equal(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png');
		assert.isNull(banner(node), "the banner must be removed after clicking Show");
		assert.deepEqual(prefs.allowExternalDomains, {}, "a one-time Show must not persist the domain");
	});

	it("'Allow' resolves the images AND persists the domain to the allow-list", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');
		app.resolveExternalImages(node);

		const allowButton = Array.from(node.querySelectorAll('button'))
			.find((btn) => btn.textContent === app.egw.lang('Allow'));
		allowButton!.click();

		assert.equal(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png');
		assert.isNull(banner(node));
		assert.deepEqual(Object.values(prefs.allowExternalDomains), ['example.org']);
	});

	it("'Close' dismisses the banner without resolving any image", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');
		app.resolveExternalImages(node);

		(node.querySelector('.closeBtn') as HTMLElement).click();

		assert.isNull(banner(node));
		assert.notEqual(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png',
			"closing the banner must leave the image blocked");
	});
});
