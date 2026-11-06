import {assert} from "@open-wc/testing";
import {emulateMedia} from "@web/test-runner-commands";
import frameworkStyles from "../EgwFramework.styles";
import appStyles from "../EgwFrameworkApp.styles";

/**
 * Contract under test: a long list printed from inside the framework is not cut short.
 *
 * Containers around the content that scroll (overflow: auto or hidden) make the browser lose the
 * last rows of what is printed, so while printing none of them may be one.  On screen they
 * keep scrolling.
 *
 * Setup strategy: the framework's and the app's real stylesheets on a minimal shadow root with
 * the container in question, under print media emulation.
 */
describe("Framework containers while printing", () =>
{
	function container(styles : {styleSheet? : CSSStyleSheet}, markup : string) : HTMLElement
	{
		const host = document.createElement("div");
		const root = host.attachShadow({mode: "open"});
		root.adoptedStyleSheets = [styles.styleSheet!];
		root.innerHTML = markup;
		document.body.append(host);
		return root.firstElementChild as HTMLElement;
	}

	const overflow = (element : HTMLElement) =>
	{
		const style = getComputedStyle(element);
		return [style.overflowX, style.overflowY];
	};

	afterEach(async() =>
	{
		await emulateMedia({media: "screen"});
		document.querySelectorAll("body > div").forEach(host => host.shadowRoot && host.remove());
	});

	[
		{name: "the framework's main area", styles: frameworkStyles, markup: `<main class="egw_fw__main"></main>`},
		{name: "the app's main area", styles: appStyles, markup: `<div class="egw_fw_app__main"></div>`},
		{name: "the app's content area", styles: appStyles, markup: `<div class="egw_fw_app__main_content content"></div>`}
	].forEach(({name, styles, markup}) =>
	{
		it(`does not scroll ${name} when printing`, async() =>
		{
			const element = container(<any>styles, markup);
			await emulateMedia({media: "print"});

			assert.deepEqual(overflow(element), ["visible", "visible"]);
		});

		it(`still scrolls ${name} on screen`, async() =>
		{
			const element = container(<any>styles, markup);
			await emulateMedia({media: "screen"});

			assert.notDeepEqual(overflow(element), ["visible", "visible"]);
		});
	});
});
