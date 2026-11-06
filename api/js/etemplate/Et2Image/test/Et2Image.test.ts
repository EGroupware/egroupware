/**
 * Tests for Et2Image (`et2-image`).
 *
 * At ~584 uses across shipped templates this is the most-used widget in the product that had no
 * tests.  Almost all of its behaviour is in two places: `parse_href()`, which decides what a `src`
 * string actually means (a path, a URL, a data/blob URI, an egw image name, or a Bootstrap icon
 * name), and `render()`, which then picks between a CSS icon class, an inlined SVG and a plain
 * `<img>`.  Every wrong answer there is silent - the widget renders nothing rather than erroring,
 * so a broken icon looks like a missing one.
 *
 * Behaviour under test:
 * - `parse_href()` for each accepted src shape, including the Bootstrap-name fallback used when
 *   `egw().image()` has no mapping (ie. outside a real logged-in session)
 * - render picks the CSS icon class for a Bootstrap path and emits no `<img>`, and emits a proper
 *   `<img>` otherwise; nothing at all for an unresolvable src
 * - the widget renders into the LIGHT DOM (`createRenderRoot()` returns `this`), which is what
 *   lets etemplate2.css style the `<img>` and what nextmatch relies on
 * - `alt`/`title`: label is used for both, but `statustext` deliberately suppresses `title` so the
 *   framework tooltip is not doubled by a plain OS one
 * - swapping `src` between two Bootstrap icons drops the previous `bi-*` class
 * - `width`/`height` accept a bare number (px), a CSS length, or a CSS function
 * - `href` makes the image clickable and routes through `egw().open_link()`
 * - `transformAttributes()` resolves `src` out of the content array manager, including the
 *   object form that becomes a link
 * - `transformSvg()` normalises an inlined SVG's size and sanitises it
 * - the `et2_IDetachedDOM` contract nextmatch uses to reuse one widget per row
 *
 * Setup strategy:
 * `egw()` is stubbed per element with a small `image()` map so name resolution is deterministic
 * and offline; the stub is also installed globally because `parse_href()` is reached from the
 * `src` setter during upgrade, before a per-instance stub could be attached.  Assertions read the
 * light DOM (`element.querySelector`), not a shadow root.
 *
 * Pass criteria:
 * Explicit assertions on `parse_href()`'s return value, on the rendered `<img>`/class, and on the
 * recorded `open_link()` call.
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {assert, fixture} from "@open-wc/testing";

// Real files under the repo root, so the test server actually serves them and the run stays
// free of 404 noise.  Deliberately NOT a bi-*.svg: render() inlines those instead of emitting
// an <img>, which is its own case below.
const IMAGES = {
	"navbar": "/api/templates/default/images/bgtopmenu2.png",
	"logo": "/api/templates/default/images/ajax-loader.gif"
};

let opened : any[] = [];

const stub = {
	lang: (label : string) => label,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	image: (name : string) => IMAGES[name] || "",
	link: (url : string, params : any) => url + "?" + new URLSearchParams(params).toString(),
	open_link: (...args : any[]) => opened.push(args),
	webserverUrl: "",
	preference: () => null,
	debug: () => {}
};
const callableEgw = function() { return stub; };
Object.assign(callableEgw, stub);
// @ts-ignore
window.egw = callableEgw;

import "../Et2Image";
import {Et2Image} from "../Et2Image";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import {assertNoElement} from "../../test/assertDom";

async function image(attributes = "")
{
	const element = <Et2Image>await fixture(`<et2-image ${attributes}></et2-image>`);
	await element.updateComplete;
	return element;
}

const img = (element : Et2Image) => element.querySelector("img");
const biClasses = (element : Et2Image) => Array.from(element.classList).filter(c => c.startsWith("bi-"));

describe("Et2Image", () =>
{
	beforeEach(() => {opened = [];});

	it("upgrades", async() =>
	{
		const element = await image('src="navbar"');
		assert.instanceOf(element, Et2Image, "et2-image did not upgrade");
	});

	it("renders into the light DOM, so etemplate2.css can reach the img", async() =>
	{
		const element = await image('src="navbar"');

		assertNoElement(element.shadowRoot, "Et2Image must not have a shadow root");
		assert.exists(img(element), "the img should be a normal child element");
	});

	describe("parse_href", () =>
	{
		const cases : [string, string, string][] = [
			["an absolute path", "/some/image.png", "/some/image.png"],
			["an http URL", "http://example.invalid/i.png", "http://example.invalid/i.png"],
			["an https URL", "https://example.invalid/i.png", "https://example.invalid/i.png"],
			["a data URI", "data:image/png;base64,AAAA", "data:image/png;base64,AAAA"],
			["a blob URI", "blob:http://x/y", "blob:http://x/y"],
			["a known egw image name", "navbar", IMAGES.navbar]
		];

		cases.forEach(([what, input, expected]) =>
		{
			it(`passes through ${what}`, async() =>
			{
				const element = await image();
				assert.equal((<any>element).parse_href(input), expected, `${what} resolved wrong`);
			});
		});

		it("falls back to a Bootstrap icon name when egw has no mapping", async() =>
		{
			// Outside a real session nothing calls egw.set_images(), so egw().image() returns
			// nothing.  Many of our icon names are Bootstrap names, so a bare name still resolves.
			const element = await image();
			assert.equal(
				(<any>element).parse_href("printer"),
				"/node_modules/bootstrap-icons/icons/printer.svg",
				"a bare unknown name should be treated as a Bootstrap icon"
			);
		});

		it("gives up on an unknown name that looks like a path", async() =>
		{
			const element = await image();
			assert.equal(
				(<any>element).parse_href("some/unknown/name"),
				"",
				"a name with a slash is not a Bootstrap icon name"
			);
		});

		it("gives up on an empty src", async() =>
		{
			const element = await image();
			assert.equal((<any>element).parse_href(""), "", "no src is not an image");
			assert.equal((<any>element).parse_href(undefined), "", "undefined src must not throw");
		});
	});

	describe("rendering", () =>
	{
		it("renders an img for a normal image", async() =>
		{
			const element = await image('src="navbar"');

			assert.equal(img(element)?.getAttribute("src"), IMAGES.navbar, "img should point at the resolved url");
			assert.equal(img(element)?.getAttribute("part"), "image", "img should expose the image part");
			assert.equal(img(element)?.getAttribute("loading"), "lazy", "images should stay lazy");
		});

		it("comes back after being hidden and given the same src again", async() =>
		{
			// A widget that blanks its image while it is busy - et2-vfs-select does this for the
			// length of its request - and then restores it.  The src it restores is the one it
			// already had, so anything that hid the img by writing to it directly rather than by
			// re-rendering leaves lit with that url still committed: the restore looks like a
			// no-op, the write is skipped, and the image never returns.
			const element = await image('src="navbar"');
			assert.equal(img(element)?.getAttribute("src"), IMAGES.navbar, "starts out showing the image");

			element.src = "";
			await element.updateComplete;
			assertNoElement(img(element), "no valid image means no img at all, not an empty one");

			element.src = "navbar";
			await element.updateComplete;
			assert.equal(
				img(element)?.getAttribute("src"), IMAGES.navbar,
				"the same src as before still has to render, or the image is lost for good"
			);
		});

		it("renders a Bootstrap icon as a CSS class, not an img", async() =>
		{
			const element = await image('src="printer"');

			assert.deepEqual(biClasses(element), ["bi-printer"], "a Bootstrap icon becomes a bi-* class");
			assertNoElement(img(element), "a CSS icon must not also emit an img");
		});

		it("renders nothing when the src cannot be resolved", async() =>
		{
			const element = await image('src="some/unknown/name"');

			assertNoElement(img(element), "an unresolvable src should render no img");
			assert.isEmpty(biClasses(element), "and no icon class either");
		});

		it("falls back to defaultSrc when src does not resolve", async() =>
		{
			const element = await image('src="some/unknown/name" defaultSrc="navbar"');

			assert.equal(img(element)?.getAttribute("src"), IMAGES.navbar, "defaultSrc should be used as a fallback");
		});

		it("drops the previous icon class when the src changes", async() =>
		{
			const element = await image('src="printer"');
			assert.deepEqual(biClasses(element), ["bi-printer"], "starts as the printer icon");

			element.src = "trash";
			await element.updateComplete;

			assert.deepEqual(
				biClasses(element),
				["bi-trash"],
				"the old bi-* class must be removed, or both icons fight over the same element"
			);
		});

		it("sizes an img by height when a height is set, by width otherwise", async() =>
		{
			const wide = await image('src="navbar"');
			assert.include(wide.querySelector("img")?.getAttribute("style"), "max-width: 100%", "default is width-constrained");

			const tall = await image('src="navbar" height="32"');
			assert.include(tall.querySelector("img")?.getAttribute("style"), "max-height: 100%", "a set height constrains height instead");
		});
	});

	describe("label, alt and title", () =>
	{
		it("uses the label as both alt text and title", async() =>
		{
			const element = await image('src="navbar" label="Home"');

			assert.equal(img(element)?.getAttribute("alt"), "Home", "label should be the alt text");
			assert.equal(element.title, "Home", "label should be the title, it has no other tooltip");
		});

		it("does not set title when statustext is used", async() =>
		{
			// Et2Widget already binds statustext to the framework's own tooltip; also writing it
			// into title stacks a second, plain OS tooltip on top of it.
			const element = await image('src="navbar" statustext="Go home"');

			assert.equal(element.title, "", "statustext must not also become a native title");
			assert.equal(img(element)?.getAttribute("alt"), "Go home", "but it is still the alt text");
		});
	});

	describe("size properties", () =>
	{
		const cases : [string, string, string][] = [
			["a bare number as px", "32", "32px"],
			["a CSS length as-is", "2rem", "2rem"]
		];

		cases.forEach(([what, input, expected]) =>
		{
			it(`treats width ${what}`, async() =>
			{
				const element = await image('src="navbar"');
				element.width = input;
				await element.updateComplete;

				assert.equal(element.style.width, expected, `width "${input}" applied wrong`);
			});

			it(`treats height ${what}`, async() =>
			{
				const element = await image('src="navbar"');
				element.height = input;
				await element.updateComplete;

				assert.equal(element.style.height, expected, `height "${input}" applied wrong`);
			});
		});

		/**
		 * A CSS function must not get "px" appended.  The exact string is not asserted: the CSSOM
		 * re-serialises calc() when reading it back (operand order can change), so pinning the
		 * literal would be testing the browser, not the widget.
		 */
		it("passes a CSS function through without appending px", async() =>
		{
			const element = await image('src="navbar"');
			element.width = "calc(1rem + 2px)";
			element.height = "calc(1rem + 2px)";
			await element.updateComplete;

			[element.style.width, element.style.height].forEach(value =>
			{
				assert.match(value, /^calc\(/, "a CSS function must survive as a calc()");
				assert.notMatch(value, /\)px$/, "and must not have px appended to it");
			});
		});
	});

	describe("href", () =>
	{
		it("opens the link on click, with the configured target and popup size", async() =>
		{
			const element = await image('src="navbar" href="/index.php" extraLinkTarget="_blank" extraLinkPopup="640x480"');
			element.click();

			assert.deepEqual(
				opened,
				[["/index.php", "_blank", "640x480"]],
				"clicking an image with an href should open it"
			);
		});

		it("marks itself clickable when it has an href", async() =>
		{
			const element = await image('src="navbar" href="/index.php"');

			assert.isTrue(element.classList.contains("et2_clickable"), "an image with a link should look clickable");
		});

		it("does not open anything without an href", async() =>
		{
			const element = await image('src="navbar"');
			element.click();

			assert.isEmpty(opened, "a plain image is not a link");
		});
	});

	describe("transformAttributes", () =>
	{
		it("resolves src out of the content array manager", async() =>
		{
			const element = await image();
			element.setArrayMgr("content", new et2_arrayMgr({photo: "navbar"}));
			element.transformAttributes({src: "photo"});
			await element.updateComplete;

			assert.equal(element.src, "navbar", "src should come from content, not be used literally");
			assert.equal(img(element)?.getAttribute("src"), IMAGES.navbar, "and then resolve normally");
		});

		it("turns an object src into a link", async() =>
		{
			// Some apps put {menuaction: ...} in content instead of a path
			const element = await image();
			element.setArrayMgr("content", new et2_arrayMgr({photo: {menuaction: "addressbook.photo"}}));
			element.transformAttributes({src: "photo"});
			await element.updateComplete;

			assert.include(element.src, "menuaction=addressbook.photo", "an object src should be built into a link");
		});

		it("leaves src alone when content has no entry for it", async() =>
		{
			const element = await image();
			element.setArrayMgr("content", new et2_arrayMgr({}));
			element.transformAttributes({src: "navbar"});
			await element.updateComplete;

			assert.equal(element.src, "navbar", "a src with no content entry is used as-is");
		});
	});

	describe("transformSvg", () =>
	{
		it("forces the svg to fill the widget", async() =>
		{
			const element = await image();
			const result = (<any>element).transformSvg('<svg width="16" height="16" viewBox="0 0 16 16"><path d="M0 0"/></svg>');

			assert.include(result, 'width="100%"', "a fixed width must be normalised away");
			assert.include(result, 'height="100%"', "a fixed height must be normalised away");
			assert.notInclude(result, 'width="16"', "the original size must not survive");
		});

		it("adds a size to an svg that has none", async() =>
		{
			const element = await image();
			const result = (<any>element).transformSvg('<svg viewBox="0 0 16 16"><path d="M0 0"/></svg>');

			assert.include(result, 'width="100%"', "a missing width should be added");
			assert.include(result, 'height="100%"', "a missing height should be added");
		});

		it("adds the image part for consistent styling", async() =>
		{
			const element = await image();
			const result = (<any>element).transformSvg('<svg viewBox="0 0 16 16"></svg>');

			assert.include(result, 'part="image"', "an inlined svg should be stylable like an img");
		});

		it("sanitises the svg", async() =>
		{
			// Inlined SVG is unsafeSVG()'d straight into the page, so this is the only thing
			// between a hostile icon file and script execution.
			const element = await image();
			const result = (<any>element).transformSvg('<svg viewBox="0 0 16 16"><script>alert(1)</script></svg>');

			assert.notInclude(result, "alert(1)", "script content must be stripped");
		});

		it("leaves something that is not an svg alone", async() =>
		{
			const element = await image();
			assert.equal((<any>element).transformSvg("not an svg"), "not an svg", "non-svg input should pass through");
		});
	});

	describe("et2_IDetachedDOM contract", () =>
	{
		/**
		 * Nextmatch reuses one widget instance for every row of an image column, pushing each
		 * row's data through setDetachedAttributes().  If the attribute list or the setter drifts,
		 * rows render the previous row's icon instead of failing.
		 */
		it("declares the attributes nextmatch pushes per row", async() =>
		{
			const element = await image('src="navbar"');
			const attrs : string[] = [];
			element.getDetachedAttributes(attrs);

			assert.includeMembers(attrs, ["src", "label", "href", "statustext"], "per-row attributes changed");
		});

		it("returns itself as the detached node", async() =>
		{
			const element = await image('src="navbar"');
			assert.deepEqual(element.getDetachedNodes(), [<any>element], "the widget is its own detached node");
		});

		it("applies per-row values and re-renders", async() =>
		{
			const element = await image('src="navbar"');
			element.setDetachedAttributes([<any>element], {src: "logo", label: "Logo"});
			await element.updateComplete;

			assert.equal(img(element)?.getAttribute("src"), IMAGES.logo, "the row image should be applied");
			assert.equal(img(element)?.getAttribute("alt"), "Logo", "and its label with it");
		});
	});
});
