import {assert} from "@open-wc/testing";
// both of these have to come before "../app" - see MailAppImportStub's own docblock
import "./MailAppImportStub";
import "../../../api/js/etemplate/Et2Widget/Et2Widget";
import "@shoelace-style/shoelace";
import "../../../api/js/etemplate/Et2Button/Et2Button";
import "../../../api/js/etemplate/Et2Description/Et2Description";
import {MailApp} from "../app";
import {assertNoElement} from "../../../api/js/etemplate/test/assertDom";

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

/** dispatch a click, as et2-button.click() needs its rendered inner button, eg. in a detached node */
function click(button : Element)
{
	button.dispatchEvent(new MouseEvent('click', {bubbles: true}));
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

		assertNoElement(banner(node));
	});

	it("shows the unblock banner for a blocked image under the default 'ask for permission' preference", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node);

		assert.isNotNull(banner(node));
	});

	it("shows the message in an et2-description", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node);

		const description = banner(node)!.querySelector('et2-description.mail_externalImagesMsgText') as any;
		assert.equal(description.value, 'In order to protect your privacy all external sources within this email are blocked.');
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

		assertNoElement(banner(node), "a 'Never' preference must not offer any way to unblock");
	});

	it("shows images directly (no banner at all) when show=true is passed, even under a 'Never' preference", () =>
	{
		prefs.allowExternalIMGs = 0;
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node, true);

		assertNoElement(banner(node), "show=true resolves images directly, without ever creating a banner");
		assert.equal(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png');
	});

	it("resolves images immediately, without a banner, when the domain is already on the allow-list", () =>
	{
		prefs.allowExternalDomains = {a: 'example.org'};
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node);

		assertNoElement(banner(node));
		assert.equal(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png');
	});

	it("routes an http:// image through the configured image proxy, leaving https:// untouched", () =>
	{
		const app = createMailApp('https://proxy.example.org/');
		const node = containerWithBlockedImages('http://insecure.example.org/photo.png');

		app.resolveExternalImages(node, true);

		assert.equal(node.querySelector('img')!.getAttribute('src'), 'https://proxy.example.org/insecure.example.org/photo.png');
	});

	it("shows an extra security warning (danger banner) when any blocked image is served over plain HTTP", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('http://insecure.example.org/photo.png');

		app.resolveExternalImages(node);

		assert.equal((banner(node) as any).variant, 'danger');
	});

	it("does not add the HTTP security warning class when every blocked image is HTTPS", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');

		app.resolveExternalImages(node);

		assert.equal((banner(node) as any).variant, 'warning');
	});

	it("'Show' resolves the images this time only, without persisting the domain to the allow-list", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');
		app.resolveExternalImages(node);

		const showButton = Array.from(node.querySelectorAll('et2-button'))
			.find((btn) => btn.textContent === app.egw.lang('Show'));
		click(showButton!);

		assert.equal(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png');
		assertNoElement(banner(node), "the banner must be removed after clicking Show");
		assert.deepEqual(prefs.allowExternalDomains, {}, "a one-time Show must not persist the domain");
	});

	it("'Allow' resolves the images AND persists the domain to the allow-list", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');
		app.resolveExternalImages(node);

		const allowButton = Array.from(node.querySelectorAll('et2-button'))
			.find((btn) => btn.textContent === app.egw.lang('Allow'));
		click(allowButton!);

		assert.equal(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png');
		assertNoElement(banner(node));
		assert.deepEqual(Object.values(prefs.allowExternalDomains), ['example.org']);
	});

	/**
	 * A JMAP-native body (MailJmap.wrapDocument()) blocks other external content by its CSP and
	 * marks the body with the first blocked url, eg. for a CSS background - which needs a banner
	 * just like an image, and "Show"/"Allow" have to render the body again to widen its CSP.
	 */
	function jmapBodyDocument(blockedOther : string | null, ...urls : string[]) : Document
	{
		// in an iframe of our document, like the preview pane: the banner is placed after the iframe
		const frameContainer = document.createElement('div');
		frameContainer.appendChild(document.createElement('iframe'));
		document.body.appendChild(frameContainer);
		frameContainers.push(frameContainer);
		const doc = (frameContainer.firstElementChild as HTMLIFrameElement).contentDocument!;
		if (blockedOther) doc.body.setAttribute('data-blocked-external', blockedOther);
		doc.body.appendChild(containerWithBlockedImages(...urls));
		return doc;
	}

	const frameContainers : HTMLElement[] = [];
	afterEach(() => frameContainers.splice(0).forEach((frameContainer) => frameContainer.remove()));

	/** element holding the iframe of a body document, and the banner placed after it */
	function frameContainer(doc : Document) : HTMLElement
	{
		return (doc.defaultView!.frameElement as HTMLElement).parentElement!;
	}

	/**
	 * "Show" and "Allow" load content from every blocked host, not just the one "Allow" saves,
	 * so both titles name them all: the blocked images' hosts and a JMAP-native body's
	 * data-blocked-hosts (CSS backgrounds etc.), each once.
	 */
	it("names every host Show and Allow load content from in their titles", () =>
	{
		const app = createMailApp();
		const doc = jmapBodyDocument('https://claude.ai/images/email/hand_wave.gif',
			'https://example.org/photo.png', 'https://tracker.example.com/pixel.gif', 'https://example.org/other.png');
		doc.body.setAttribute('data-blocked-hosts', 'claude.ai tracker.example.com');
		app.resolveExternalImages(doc);
		const title = (label : string) => (Array.from(frameContainer(doc).querySelectorAll('et2-button'))
			.find((btn) => btn.textContent === label) as any).statustext;

		assert.equal(title('Allow'), 'Always allow external sources from %1 and load content from %2 example.org example.org, tracker.example.com, claude.ai');
		assert.equal(title('Show'), 'Load content from %1 this time only example.org, tracker.example.com, claude.ai');
	});

	function clickButton(app : MailApp, doc : Document, label : string)
	{
		click(Array.from(frameContainer(doc).querySelectorAll('et2-button')).find((btn) => btn.textContent === app.egw.lang(label))!);
	}

	it("shows the banner for a body whose only blocked external content is no image (eg. a CSS background)", () =>
	{
		const app = createMailApp();
		const doc = jmapBodyDocument('https://claude.ai/images/email/hand_wave.gif');

		app.resolveExternalImages(doc);

		assert.isNotNull(banner(frameContainer(doc)));
		assert.include((Array.from(frameContainer(doc).querySelectorAll('et2-button')).find((btn) => btn.textContent === 'Allow') as any).statustext,
			'claude.ai');
	});

	it("'Show' renders a JMAP-native body again instead of setting the images' src", () =>
	{
		const app = createMailApp();
		const doc = jmapBodyDocument('https://claude.ai/images/email/hand_wave.gif', 'https://example.org/photo.png');
		let rendered = 0;
		(app as any).externalContentRenderers = new WeakMap([[doc, () => rendered++]]);
		app.resolveExternalImages(doc);

		clickButton(app, doc, 'Show');

		assert.equal(rendered, 1);
		assert.notEqual(doc.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png',
			"the rendered document shows the images, the old one must not request them");
		assert.deepEqual(prefs.allowExternalDomains, {}, "a one-time Show must not persist any domain");
	});

	it("'Allow' persists only the domain named in its title, not the domains of the other images", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png', 'https://tracker.example.com/pixel.gif');
		app.resolveExternalImages(node);
		const allowButton = Array.from(node.querySelectorAll('et2-button')).find((btn) => btn.textContent === 'Allow');
		assert.include((allowButton as any).statustext, 'example.org');

		click(allowButton!);

		assert.deepEqual(Object.values(prefs.allowExternalDomains), ['example.org']);
		assert.equal(node.querySelectorAll('img')[1].getAttribute('src'), 'https://tracker.example.com/pixel.gif',
			"all images are still shown this time");
	});

	it("an allowlisted first image does not unblock the images of other domains", () =>
	{
		prefs.allowExternalDomains = {a: 'example.org'};
		const app = createMailApp('https://proxy.example.org/');
		const node = containerWithBlockedImages('http://example.org/photo.png', 'https://tracker.example.com/pixel.gif');

		app.resolveExternalImages(node);

		const [allowed, blocked] = Array.from(node.querySelectorAll('img'));
		assert.equal(allowed.getAttribute('src'), 'https://proxy.example.org/example.org/photo.png', "the allowlisted image is shown");
		assert.isNull(blocked.getAttribute('src'), "the other domain's image stays blocked");
		assert.isNotNull(banner(node));
		assert.include((Array.from(node.querySelectorAll('et2-button')).find((btn) => btn.textContent === 'Allow') as any).statustext,
			'tracker.example.com', "the banner names the still blocked domain");
	});

	it("'Allow' persists the blocked domain and renders a JMAP-native body again", () =>
	{
		const app = createMailApp();
		const doc = jmapBodyDocument('https://claude.ai/images/email/hand_wave.gif', 'https://example.org/photo.png');
		let rendered = 0;
		(app as any).externalContentRenderers = new WeakMap([[doc, () => rendered++]]);
		app.resolveExternalImages(doc);

		clickButton(app, doc, 'Allow');

		assert.equal(rendered, 1);
		assert.includeMembers(Object.values(prefs.allowExternalDomains), ['example.org']);
		assertNoElement(banner(frameContainer(doc)));
	});

	/**
	 * A server-rendered body gets its CSP as header, so "Show"/"Allow" reload it with
	 * _showExternal=1 (MessageDisplayHandler::externalContentCsp() then allows https: content),
	 * and the reloaded body shows its images directly.
	 */
	function classicBodyDocument(search : string, ...urls : string[]) : {doc : Document, replaced : string[]}
	{
		const doc = jmapBodyDocument(null, ...urls);
		const replaced : string[] = [];
		Object.defineProperty(doc, 'defaultView', {value: {
			frameElement: doc.defaultView!.frameElement,
			location: {search, href: 'https://egw.example.org/egroupware/index.php'+search, replace: (url : string) => replaced.push(url)},
		}});
		return {doc, replaced};
	}

	it("'Show' reloads a server-rendered body with _showExternal=1", () =>
	{
		const app = createMailApp();
		const {doc, replaced} = classicBodyDocument('?menuaction=mail.EGroupware%5CMail%5CUi.loadEmailBody&_messageID=1',
			'https://example.org/photo.png');
		app.resolveExternalImages(doc);

		clickButton(app, doc, 'Show');

		assert.deepEqual(replaced, ['https://egw.example.org/egroupware/index.php?menuaction=mail.EGroupware%5CMail%5CUi.loadEmailBody&_messageID=1&_showExternal=1']);
		assert.notEqual(doc.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png');
	});

	it("shows the images of a server-rendered body reloaded with _showExternal=1 directly, without a banner", () =>
	{
		const app = createMailApp();
		const {doc, replaced} = classicBodyDocument('?menuaction=mail.EGroupware%5CMail%5CUi.loadEmailBody&_messageID=1&_showExternal=1',
			'https://example.org/photo.png');

		app.resolveExternalImages(doc);

		assertNoElement(banner(frameContainer(doc)));
		assert.equal(doc.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png');
		assert.deepEqual(replaced, [], "an already reloaded body must not be reloaded again");
	});

	/**
	 * The banner uses web components, which can not live in the body document (own custom element
	 * registry, no scripts) - it is overlaid at the bottom of the body's iframe in our own document,
	 * and replaced, not duplicated, when the next message is loaded into the same iframe.
	 */
	it("places the banner after the body's iframe in our own document, once per iframe", async() =>
	{
		const app = createMailApp();
		const container = document.createElement('div');
		const iframe = document.createElement('iframe');
		container.appendChild(iframe);
		document.body.appendChild(container);
		try
		{
			const load = (url : string) =>
			{
				const body = iframe.contentDocument!.body;
				body.innerHTML = '';
				body.appendChild(containerWithBlockedImages(url));
				app.resolveExternalImages(iframe.contentDocument);
			};
			load('https://example.org/photo.png');
			load('https://tracker.example.com/pixel.gif');

			const banners = container.querySelectorAll('.mail_externalImagesMsg');
			assert.equal(banners.length, 1, "the previous message's banner must be replaced");
			assert.equal(iframe.nextElementSibling, banners[0]);
			assert.isTrue(container.classList.contains('mail_externalImagesMsgHost'));
			assertNoElement(banner(iframe.contentDocument!.body), "nothing is added to the body document itself");
			assert.include((banners[0].querySelector('.mailExternalShow') as any).statustext, 'tracker.example.com');
		}
		finally
		{
			container.remove();
		}
	});

	it("a click anywhere on the banner hides it without resolving any image", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');
		app.resolveExternalImages(node);

		click(banner(node)!.querySelector('.mail_externalImagesMsgText')!);

		assertNoElement(banner(node));
		assert.notEqual(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png',
			"hiding the banner must leave the image blocked");
	});

	it("'Close' dismisses the banner without resolving any image", () =>
	{
		const app = createMailApp();
		const node = containerWithBlockedImages('https://example.org/photo.png');
		app.resolveExternalImages(node);

		// the alert's own close button hides it, which ends with this event
		banner(node)!.dispatchEvent(new Event('sl-after-hide'));

		assertNoElement(banner(node));
		assert.notEqual(node.querySelector('img')!.getAttribute('src'), 'https://example.org/photo.png',
			"closing the banner must leave the image blocked");
	});
});
