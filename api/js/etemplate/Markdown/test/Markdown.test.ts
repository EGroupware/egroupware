/**
 * Test file for the eTemplate markdown renderer
 *
 * Contract under test: markdownToHtml() renders the full markdown syntax and returns HTML that is
 * safe to hand to unsafeHTML().  Two independent layers have to hold - markdown-it with
 * html:false, so raw HTML in the source is escaped and never parsed, and the DOMPurify allow-list
 * as defence in depth over the URLs the parser now does emit.
 *
 * Assertions use booleans rather than the element itself: a failing assert.isNull(<Element>)
 * cannot be serialised back to the test runner and hangs it for two minutes instead of failing.
 *
 * Setup: the renderer is a pure function, so there is no fixture and no egw() stub.  Output is
 * re-parsed with DOMParser, which is inert - no script runs and no image loads - so a regression
 * shows up as a failed assertion rather than as a side effect.
 *
 * A failure in "hostile input" means a real XSS or tracking regression.  A failure in "markdown
 * syntax" means a parser option changed.
 */
import {assert, elementUpdated, fixture, html} from '@open-wc/testing';
import {markdown, markdownToHtml, SUPPORTED_TAGS} from "../MarkdownDirective";

/**
 * Re-parse rendered output into an inert document for structural assertions
 */
function parse(source : string) : Document
{
	return new DOMParser().parseFromString(markdownToHtml(source), "text/html");
}

describe("markdownToHtml() - hostile input", () =>
{
	it("escapes raw HTML instead of parsing it", () =>
	{
		assert.include(markdownToHtml("<script>alert(1)</script>"), "&lt;script&gt;",
			"Raw HTML was not escaped");
		assert.isFalse(!!parse("<script>alert(1)</script>").querySelector("script"),
			"A script node survived");
	});

	it("does not let raw HTML img/onerror through", () =>
	{
		const doc = parse('<img src=x onerror=alert(1)>');

		// The source is escaped, so "onerror=" survives as literal *text* - which is harmless.
		// What matters is that no element exists to carry it.
		assert.isFalse(!!doc.querySelector("img"), "An img node survived");
		assert.isFalse(!!doc.querySelector("[onerror]"), "An event handler attribute survived");
		assert.include(doc.body.textContent, "onerror=", "The escaped source should still be shown");
	});

	it("drops javascript: links", () =>
	{
		const doc = parse("[x](javascript:alert(1))");

		assert.isFalse(!!doc.querySelector('a[href^="javascript:"]'), "A javascript: href survived");
		assert.include(doc.body.textContent, "x", "The link text should still be shown");
	});

	it("drops data: links", () =>
	{
		assert.isNull(parse("[x](data:text/html;base64,PHNjcmlwdD48L3NjcmlwdD4=)")
			.querySelector('[href^="data:"]'), "A data: href survived");
	});

	it("drops data:image links, which markdown-it's own validator would allow", () =>
	{
		// markdown-it permits data:image/(gif|png|jpeg|webp) in validateLink, so the DOMPurify
		// allow-list is the layer that has to catch this - it proves layer 2 is active
		assert.isNull(parse("[x](data:image/png;base64,iVBORw0KGgo=)")
			.querySelector('[href^="data:"]'), "A data:image href survived");
	});

	it("allows a raster data: image but not an SVG one", () =>
	{
		// markdown-it's validateLink only passes data:image/(gif|png|jpeg|webp), so an inline
		// image cannot be SVG - which is the only data: image format that could carry script.
		assert.isTrue(!!parse("![i](data:image/png;base64,iVBORw0KGgo=)").querySelector("img"),
			"A raster data: image should render");
		assert.isFalse(!!parse("![i](data:image/svg+xml,<svg onload=alert(1)>)").querySelector("img"),
			"An SVG data: image survived");
		assert.isFalse(!!parse("[x](data:image/png;base64,iVBORw0KGgo=)").querySelector("a[href]"),
			"A data: href survived on a link");
	});

	it("emits table alignment as a fixed style, never user text", () =>
	{
		// style survives now that no sanitizer strips it, which is what restores column
		// alignment.  The value is one markdown-it picks, so it cannot carry user content.
		const rendered = markdownToHtml("| a | b |\n|:--|--:|\n| 1 | 2 |");

		assert.include(rendered, 'style="text-align:left"');
		assert.include(rendered, 'style="text-align:right"');
		assert.notMatch(rendered, /style="(?!text-align:(?:left|right|center)")/,
			"A style attribute carried something other than text-align");
	});
});

describe("markdownToHtml() - links and images", () =>
{
	it("renders and hardens an external link", () =>
	{
		const a = parse("[x](https://e.org)").querySelector("a");

		assert.isNotNull(a, "No link was rendered");
		assert.equal(a.getAttribute("target"), "_blank");
		assert.equal(a.getAttribute("rel"), "noopener noreferrer");
	});

	it("keeps a relative href", () =>
	{
		// A scheme-anchored ALLOWED_URI_REGEXP would silently strip this, leaving a dead anchor.
		// Internal EGroupware links are relative everywhere, so this is the regression to catch.
		assert.equal(parse("[x](/index.php?menuaction=infolog.infolog_ui.index)")
			.querySelector("a")?.getAttribute("href"), "/index.php?menuaction=infolog.infolog_ui.index");
	});

	it("keeps a fragment href", () =>
	{
		assert.equal(parse("[x](#top)").querySelector("a")?.getAttribute("href"), "#top");
	});

	it("keeps mailto: links without forcing a new window", () =>
	{
		const a = parse("[mail](mailto:info@example.org)").querySelector("a");

		assert.isNotNull(a, "No mailto link was rendered");
		assert.equal(a.getAttribute("href"), "mailto:info@example.org");
		assert.isNull(a.getAttribute("target"), "mailto should not open a new window");
	});

	it("linkifies a bare URL and renders an autolink", () =>
	{
		assert.isNotNull(parse("see https://www.egroupware.org for more").querySelector("a"),
			"linkify did not produce a link");
		assert.isNotNull(parse("<https://auto.link>").querySelector("a"),
			"autolink was not rendered");
	});

	it("renders a reference link", () =>
	{
		assert.isNotNull(parse("[ref][1]\n\n[1]: https://e.org").querySelector("a"),
			"reference link was not rendered");
	});

	it("renders a remote image with its alt and title", () =>
	{
		const img = parse('![alt text](https://e.org/a.png "the title")').querySelector("img");

		assert.isNotNull(img, "No image was rendered");
		assert.equal(img.getAttribute("src"), "https://e.org/a.png");
		assert.equal(img.getAttribute("alt"), "alt text");
		assert.equal(img.getAttribute("title"), "the title");
	});
});

describe("markdownToHtml() - output surface", () =>
{
	// Without a sanitizer, the parser config IS the security boundary.  These two tests are what
	// stops a re-enabled rule or html:true from quietly widening it.

	const KITCHEN_SINK = [
		"# h1", "## h2", "para **b** *i* ~~s~~ `c`", "- a\n- b", "3. three",
		"> quote", "---", "| a | b |\n|:--|--:|\n| 1 | 2 |", "```js\nlet a = 1;\n```",
		"[abs](https://e.org)", "[rel](/index.php?a=b)", "![i](https://e.org/a.png)"
	].join("\n\n");

	it("emits only the supported tags", () =>
	{
		const doc = parse(KITCHEN_SINK);
		const seen = [...new Set(Array.from(doc.body.querySelectorAll("*")).map(e => e.tagName.toLowerCase()))];
		const unexpected = seen.filter(t => !SUPPORTED_TAGS.includes(t));

		assert.deepEqual(unexpected, [], "Unexpected tag(s) in the output: " + unexpected.join(", "));
	});

	it("emits only the expected attributes, and never an event handler", () =>
	{
		const doc = parse(KITCHEN_SINK);
		const attrs = new Set();
		for(const el of Array.from(doc.body.querySelectorAll("*")))
		{
			for(const a of Array.from(el.attributes))
			{
				attrs.add(a.name);
			}
		}

		assert.deepEqual([...attrs].sort(),
			["alt", "class", "href", "rel", "src", "start", "style", "target"],
			"Unexpected attribute in the output");
		assert.lengthOf(doc.querySelectorAll("[onerror], [onload], [onclick]"), 0, "An event handler survived");
	});

	it("only ever puts text-align in a style attribute", () =>
	{
		const rendered = markdownToHtml("| a | b |\n|:--|--:|\n| 1 | 2 |");

		assert.include(rendered, 'style="text-align:left"');
		assert.include(rendered, 'style="text-align:right"');
		assert.notMatch(rendered, /style="(?!text-align:(?:left|right|center)")/,
			"A style attribute carried something other than text-align");
	});

	it("escapes a hostile fenced-code info string into the class", () =>
	{
		// the info string is the only user text that reaches an attribute value
		const code = parse('```js" onload="alert(1)\ncode\n```').querySelector("pre > code");

		assert.isNotNull(code, "No code block was rendered");
		assert.isFalse(!!code.getAttribute("onload"), "The info string broke out of the attribute");
		assert.match(code.getAttribute("class"), /^language-/, "class is not the language- prefix");
	});
});

describe("markdownToHtml() - markdown syntax", () =>
{
	it("renders headings", () =>
	{
		assert.equal(parse("# H1").querySelector("h1")?.textContent, "H1");
		assert.equal(parse("## H2").querySelector("h2")?.textContent, "H2");
	});

	it("renders emphasis", () =>
	{
		assert.equal(parse("**b**").querySelector("strong")?.textContent, "b");
		assert.equal(parse("*i*").querySelector("em")?.textContent, "i");
	});

	it("renders strikethrough", () =>
	{
		// markdown-it emits <s>, not <del> - it has to be in ALLOWED_TAGS or the markup is dropped
		assert.equal(parse("~~gone~~").querySelector("s")?.textContent, "gone");
	});

	it("renders an unordered list", () =>
	{
		const items = parse("- a\n- b").querySelectorAll("ul > li");

		assert.lengthOf(items, 2, "Wrong number of list items");
		assert.equal(items[0].textContent.trim(), "a");
	});

	it("renders a nested list", () =>
	{
		assert.isNotNull(parse("- a\n    - a1\n- b").querySelector("ul > li > ul > li"),
			"Nested list was not rendered");
	});

	it("renders an ordered list", () =>
	{
		assert.lengthOf(parse("1. a\n2. b").querySelectorAll("ol > li"), 2);
	});

	it("renders inline code and a fenced code block", () =>
	{
		assert.equal(parse("`c`").querySelector("code")?.textContent, "c");

		const block = parse("```js\nlet a = 1;\n```").querySelector("pre > code");
		assert.isNotNull(block, "No code block was rendered");
		assert.include(block.textContent, "let a = 1;");
	});

	it("renders a blockquote", () =>
	{
		assert.include(parse("> quoted").querySelector("blockquote")?.textContent ?? "", "quoted");
	});

	it("renders a horizontal rule", () =>
	{
		assert.isNotNull(parse("a\n\n---\n\nb").querySelector("hr"), "No hr was rendered");
	});

	it("renders a table", () =>
	{
		const doc = parse("| a | b |\n|---|---|\n| 1 | 2 |");

		assert.isNotNull(doc.querySelector("table"), "No table was rendered");
		assert.lengthOf(doc.querySelectorAll("thead th"), 2, "Wrong number of header cells");
		assert.lengthOf(doc.querySelectorAll("tbody td"), 2, "Wrong number of body cells");
	});

	it("turns a single newline into a line break", () =>
	{
		assert.isNotNull(parse("a\nb").querySelector("br"), "breaks:true is not active");
	});

	it("returns an empty string for an empty value", () =>
	{
		assert.equal(markdownToHtml(""), "");
		assert.equal(markdownToHtml(null), "");
		assert.equal(markdownToHtml(undefined), "");
	});
});

describe("markdown directive", () =>
{
	it("renders the parsed markdown into the DOM", async() =>
	{
		const element = await fixture<HTMLDivElement>(html`
            <div>${markdown("# Directive")}</div>
		`);
		await elementUpdated(element);

		assert.equal(element.querySelector("h1")?.textContent, "Directive");
	});

	it("renders nothing for an empty value", async() =>
	{
		const element = await fixture<HTMLDivElement>(html`
            <div>${markdown("")}</div>
		`);
		await elementUpdated(element);

		assert.equal(element.textContent.trim(), "");
	});
});
