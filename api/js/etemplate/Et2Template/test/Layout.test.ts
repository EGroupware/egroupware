import {assert, elementUpdated, fixture, html, nextFrame} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Template} from "../Et2Template";

/**
 * Layout is opt-in
 *
 * The layout property reflects, and the layout CSS (kdots/css/src/layouts) keys off the
 * attribute, so a default here would hand that layout to every template in every app.  These
 * tests pin that a template only gets a layout when it actually asks for one.
 */
// Stub global egw
// @ts-ignore
window.egw = {
	debug: () => {},
	debug_level: () => 0,
	lang: i => i + "*",
	link: i => i,
	tooltipUnbind: () => {},
	webserverUrl: ""
};

// A runtime reference, so the import is not erased as type-only - without it <et2-template>
// never registers and the fixture stays an unupgraded HTMLElement.
const keepImport = Et2Template;

async function make(markup)
{
	// @ts-ignore
	const element : Et2Template = await fixture(markup);
	sinon.stub(element, "egw").returns(window.egw);
	await elementUpdated(element);
	return element;
}

const base = (element : Et2Template) => element.shadowRoot.querySelector('[part="base"]');

describe("Et2Template layout is opt-in", () =>
{
	it("has no layout, attribute or layout class when none was asked for", async() =>
	{
		const element = await make(html`<et2-template></et2-template>`);

		assert.notOk(element.layout, "layout defaults to something instead of staying unset");
		assert.isFalse(element.hasAttribute("layout"),
			"a layout attribute is reflected onto a template that never asked for one");
		assert.notMatch(base(element).className, /layout-/,
			"a layout- class is set on a template that never asked for one");
	});

	it("keeps a layout the template does ask for, and reflects it for the CSS to match", async() =>
	{
		const element = await make(html`<et2-template layout="2-column"></et2-template>`);

		assert.equal(element.layout, "2-column");
		assert.equal(element.getAttribute("layout"), "2-column",
			"layout must stay on the attribute - the layout CSS matches [layout=...], not a class");
		assert.match(base(element).className, /\blayout-2-column\b/);
	});

	it("reflects a layout set after construction, and drops the attribute again when cleared",
		async() =>
	{
		const element = await make(html`<et2-template></et2-template>`);

		element.layout = "edit";
		await elementUpdated(element);
		assert.equal(element.getAttribute("layout"), "edit");
		assert.match(base(element).className, /\blayout-edit\b/);

		element.layout = undefined;
		await elementUpdated(element);
		assert.isFalse(element.hasAttribute("layout"));
		assert.notMatch(base(element).className, /layout-/);
	});
});

/**
 * span="end" / span="*" stretch a widget from the column it was placed in to the end of its line.
 *
 * The layout CSS lives in kdots.css, a document stylesheet this test page does not load, so the
 * grid the widgets are placed in has to be declared here.  What is under test is not that CSS but
 * Et2LayoutController's placement: `grid-column-end: -1` alone would drop the widget into the last
 * column and leave a hole beside it, so the controller measures which column auto-placement chose
 * and writes the real `grid-column` back.
 */
describe("Layout span to end of line", () =>
{
	const COLUMNS = 2;
	const COLUMN = 195;
	const GAP = 10;
	const WIDTH = COLUMNS * COLUMN + GAP;

	let styles : HTMLStyleElement;

	function grid(columns : number)
	{
		styles?.remove();
		styles = document.createElement("style");
		styles.textContent = `
			et2-template[layout="2-column"] { display: block; width: ${WIDTH}px; }
			et2-template[layout="2-column"]::part(base) {
				display: grid;
				grid-template-columns: repeat(${columns}, 1fr);
				column-gap: ${GAP}px;
			}
			et2-template[layout="2-column"] [span="all"] { grid-column: 1 / -1; }
			et2-template[layout="2-column"] > div { height: 10px; }
		`;
		document.head.append(styles);
	}

	afterEach(() => styles?.remove());

	// Where a child sits inside the grid, in whole columns from its left edge
	function placement(element : Et2Template, id : string)
	{
		const base = element.shadowRoot.querySelector('[part="base"]').getBoundingClientRect();
		const child = element.querySelector("#" + id).getBoundingClientRect();
		return {
			column: Math.round((child.left - base.left) / (COLUMN + GAP)) + 1,
			columns: Math.round((child.width + GAP) / (COLUMN + GAP)),
			width: Math.round(child.width),
			top: Math.round(child.top - base.top)
		};
	}

	async function laidOut(markup)
	{
		const element = await make(markup);
		// The controller measures and places in a requestAnimationFrame, and a child it widens
		// can push a later one onto another line, so let the whole pass settle
		await nextFrame();
		await nextFrame();
		return element;
	}

	it("stretches from the second column to the end of the line", async() =>
	{
		grid(COLUMNS);
		const element = await laidOut(html`
            <et2-template layout="2-column">
                <div id="a"></div>
                <div id="b" span="end"></div>
            </et2-template>`);

		assert.include(placement(element, "a"), {column: 1, columns: 1, top: 0});
		assert.include(placement(element, "b"), {column: 2, columns: 1, top: 0},
			"a span=end widget already in the last column must stay one column wide");
	});

	it("stretches from the first column to the end of the line", async() =>
	{
		grid(COLUMNS);
		const element = await laidOut(html`
            <et2-template layout="2-column">
                <div id="a" span="all"></div>
                <div id="b" span="end"></div>
                <div id="c"></div>
            </et2-template>`);

		const b = placement(element, "b");
		assert.equal(b.column, 1);
		assert.equal(b.columns, COLUMNS,
			"a span=end widget that starts a line must fill it, not jump to the last column");
		assert.isAbove(placement(element, "c").top, b.top,
			"the widget after it must be pushed onto the next line, not left beside it");
	});

	it("treats span=* the same as span=end", async() =>
	{
		grid(COLUMNS);
		const element = await laidOut(html`
            <et2-template layout="2-column">
                <div id="a" span="*"></div>
            </et2-template>`);

		assert.include(placement(element, "a"), {column: 1, columns: COLUMNS, top: 0});
	});

	it("fills the line when the grid has collapsed to one column", async() =>
	{
		grid(1);
		const element = await laidOut(html`
            <et2-template layout="2-column">
                <div id="a"></div>
                <div id="b" span="end"></div>
            </et2-template>`);

		assert.equal(placement(element, "b").width, WIDTH,
			"the only column is the whole line");
		assert.isAbove(placement(element, "b").top, 0,
			"and it goes on its own line, below the widget before it");
	});

	it("gives the placement back when the layout no longer wants it", async() =>
	{
		grid(COLUMNS);
		const element = await laidOut(html`
            <et2-template layout="2-column">
                <div id="a" span="all"></div>
                <div id="b" span="end"></div>
            </et2-template>`);
		const child = <HTMLElement>element.querySelector("#b");
		assert.equal(child.style.gridColumn, "1 / -1");

		element.layout = "stack";
		await elementUpdated(element);
		await nextFrame();

		assert.equal(child.style.gridColumn, "",
			"the inline grid-column has to go with the grid, or it survives into the next layout");
	});
});

/**
 * grow="2" takes twice the share of grow="1" in a stack.
 *
 * As above, the layout CSS is in kdots.css and not on this page, so stack's flex column is
 * declared here - plus the height its container would give it in a real dialog.  What is under
 * test is Et2LayoutController turning the attribute into a flex-grow factor: the stylesheet can
 * only say `flex: 1 1 auto`, which hands every growing child an equal share whatever it asked for.
 */
describe("Layout grow factors in a stack", () =>
{
	const HEIGHT = 300;
	const FIXED = 20;

	let styles : HTMLStyleElement;

	beforeEach(() =>
	{
		styles = document.createElement("style");
		styles.textContent = `
			et2-template[layout="stack"] { display: block; height: ${HEIGHT}px; }
			et2-template[layout="stack"]::part(base) {
				display: flex;
				flex-direction: column;
				min-height: 0;
				height: 100%;
			}
			et2-template[layout="stack"] > * { flex: 0 0 auto; min-height: 0; }
			et2-template[layout="stack"] [grow],
			et2-template[layout="stack"] et2-tabbox,
			et2-template[layout="stack"] et2-nextmatch { flex: 1 1 auto; min-height: 0; }
			/* A natural height on the growing children, which is the whole point: with
			 * flex-basis: auto that height becomes their basis, they fill the container on their
			 * own and are shrunk rather than grown - and shrinking ignores the grow factor.
			 * Without this the children are 0 high and every factor "works" by accident. */
			et2-template[layout="stack"] > * { height: 40px; }
			et2-template[layout="stack"] #fixed { height: ${FIXED}px; }
		`;
		document.head.append(styles);
	});
	afterEach(() => styles?.remove());

	const height = (element : Et2Template, id : string) =>
		Math.round(element.querySelector("#" + id).getBoundingClientRect().height);

	async function laidOut(markup)
	{
		const element = await make(markup);
		await nextFrame();
		await nextFrame();
		return element;
	}

	it("splits the leftover height by the factor, leaving the other widgets alone", async() =>
	{
		const element = await laidOut(html`
            <et2-template layout="stack">
                <div id="fixed"></div>
                <div id="one" grow="1"></div>
                <div id="two" grow="2"></div>
            </et2-template>`);

		assert.equal(height(element, "fixed"), FIXED, "a widget that did not ask to grow must keep its own height");
		assert.equal(height(element, "one") + height(element, "two"), HEIGHT - FIXED,
			"the growing widgets share everything that is left");
		// 1px of slack: the leftover does not always divide into whole pixels
		assert.closeTo(height(element, "two"), 2 * height(element, "one"), 1,
			"grow=2 has to get twice what grow=1 gets - the stylesheet alone gives them an equal share");
	});

	it("treats a bare grow as a factor of 1", async() =>
	{
		const element = await laidOut(html`
            <et2-template layout="stack">
                <div id="fixed"></div>
                <div id="one" grow></div>
                <div id="two" grow="1"></div>
            </et2-template>`);

		assert.equal(height(element, "one"), height(element, "two"));
	});

	it("grows an et2-tabbox without being asked, and by the factor when it is", async() =>
	{
		const element = await laidOut(html`
            <et2-template layout="stack">
                <div id="fixed"></div>
                <et2-tabbox id="tabs"></et2-tabbox>
                <div id="two" grow="3"></div>
            </et2-template>`);

		assert.closeTo(height(element, "two"), 3 * height(element, "tabs"), 1,
			"a tabbox grows with a factor of 1 unless it says otherwise");
	});

	it("grows an et2-nextmatch without being asked, the same way a tabbox does", async() =>
	{
		// A list is never what a stack should leave space around - whatever sits with it is a
		// header or a footer - so the tag is enough on its own, no grow= needed
		const element = await laidOut(html`
            <et2-template layout="stack">
                <div id="fixed"></div>
                <et2-nextmatch id="nm"></et2-nextmatch>
                <div id="two" grow="3"></div>
            </et2-template>`);

		assert.closeTo(height(element, "two"), 3 * height(element, "nm"), 1,
			"a nextmatch grows with a factor of 1 unless it says otherwise");
	});

	it("still honours a grow factor the nextmatch does name", async() =>
	{
		const element = await laidOut(html`
            <et2-template layout="stack">
                <div id="fixed"></div>
                <et2-nextmatch id="nm" grow="2"></et2-nextmatch>
                <div id="two" grow="1"></div>
            </et2-template>`);

		assert.closeTo(height(element, "nm"), 2 * height(element, "two"), 1,
			"the automatic factor must not override one the template asked for");
	});

	it("gives the factor back when the layout no longer wants it", async() =>
	{
		const element = await laidOut(html`
            <et2-template layout="stack">
                <div id="two" grow="2"></div>
            </et2-template>`);
		const child = <HTMLElement>element.querySelector("#two");
		assert.equal(child.style.flexGrow, "2");
		assert.equal(child.style.flexBasis, "0px",
			"the factor is only meaningful with the basis taken out of the way");

		element.layout = "2-column";
		await elementUpdated(element);
		await nextFrame();

		assert.equal(child.style.flexGrow, "",
			"an inline flex-grow left behind would follow the widget into a grid layout");
		assert.equal(child.style.flexBasis, "", "and so would the basis");
	});
});

/**
 * Fixed-width labels are what a layout gives you unless the container says otherwise.
 *
 * A layout means a form, and a form wants its labels in a column, so every layout puts
 * `et2-label-fixed` on its children rather than making each field ask.  The container's `class` is
 * the whole statement about labels: nothing said means the default, any class at all takes it
 * back, and naming `et2-label-fixed` among your own classes keeps it.
 */
describe("Layout gives its children fixed-width labels", () =>
{
	const fixed = (element : Et2Template, id = "one") =>
		element.querySelector("#" + id).classList.contains("et2-label-fixed");

	async function laidOut(markup)
	{
		const element = await make(markup);
		await nextFrame();
		return element;
	}

	["stack", "2-column", "edit"].forEach(layout =>
	{
		it(`does it by default for ${layout}`, async() =>
		{
			const element = await laidOut(html`
                <et2-template layout=${layout}>
                    <div id="one"></div>
                    <div id="two"></div>
                </et2-template>`);

			["one", "two"].forEach(id => assert.isTrue(fixed(element, id),
				`#${id} did not get fixed labels from the ${layout} layout`));
		});

		it(`hands them back for ${layout} as soon as the container names a class`, async() =>
		{
			const element = await laidOut(html`
                <et2-template layout=${layout} class="myapp-config">
                    <div id="one"></div>
                </et2-template>`);

			assert.isFalse(fixed(element),
				"a class on the container is how a form with long labels keeps its own label widths");
			assert.isTrue(element.classList.contains("myapp-config"),
				"and the class still does whatever it was there to do");
		});
	});

	it("counts an empty class as having said something", async() =>
	{
		const element = await laidOut(html`
            <et2-template layout="2-column" class="">
                <div id="one"></div>
            </et2-template>`);

		assert.isFalse(fixed(element));
	});

	it("keeps the default when et2-label-fixed is named alongside other classes", async() =>
	{
		const element = await laidOut(html`
            <et2-template layout="2-column" class="myapp-config et2-label-fixed">
                <div id="one"></div>
            </et2-template>`);

		assert.isTrue(fixed(element));
		assert.isTrue(element.classList.contains("myapp-config"));
	});

	it("reaches widgets appended after the layout was applied", async() =>
	{
		// How eTemplate actually builds a dialog: the template element connects first and the
		// widgets are created and appended to it afterwards, so the children the strategy saw when
		// it first ran were none of the real ones.
		const element = await laidOut(html`
            <et2-template layout="2-column"></et2-template>`);

		const late = document.createElement("div");
		late.id = "late";
		element.append(late);
		await nextFrame();
		await nextFrame();

		assert.isTrue(late.classList.contains("et2-label-fixed"),
			"a widget that arrived after the layout ran still has to get its label width");
	});

	it("still honours the class said on its own, so existing templates keep working", async() =>
	{
		const element = await laidOut(html`
            <et2-template layout="2-column" class="et2-label-fixed">
                <div id="one"></div>
            </et2-template>`);

		assert.isTrue(fixed(element));
	});
});

/**
 * The collapse, against the stylesheet the app actually ships.
 *
 * Every other test here declares its own grid, which pins the controller's behaviour but says
 * nothing about whether `kdots.css` still does what the controller is written against.  So take
 * the real rules: everything the layouts contribute is scoped to a `[layout=...]` attribute, which
 * is what makes it safe to lift them out of a whole theme.
 */
describe("Layout against the stylesheet the app ships", () =>
{
	let styles : HTMLStyleElement;

	before(async() =>
	{
		const css = await (await fetch("/kdots/css/kdots.css")).text();
		const kept = [];
		let depth = 0, start = 0;
		for(let i = 0; i < css.length; i++)
		{
			if(css[i] === "{" && depth++ === 0) continue;
			if(css[i] !== "}" || --depth > 0) continue;
			const block = css.slice(start, i + 1);
			start = i + 1;
			if(block.includes("[layout=")) kept.push(block);
		}
		assert.isAbove(kept.length, 0, "no [layout=] rules in kdots.css - has it been rebuilt from the less?");
		styles = document.createElement("style");
		styles.textContent = kept.join("\n");
		document.head.append(styles);
	});
	after(() => styles?.remove());

	async function at(width)
	{
		const element = await make(html`
            <et2-template layout="2-column">
                <div id="one"></div>
                <div id="two"></div>
            </et2-template>`);
		// the layout makes the host a container, so its own width is what everything keys off
		element.style.width = width + "px";
		await nextFrame();
		await nextFrame();
		return element;
	}

	const columns = (element : Et2Template) =>
		getComputedStyle(element.shadowRoot.querySelector('[part="base"]')).gridTemplateColumns
			.split(" ").filter(track => track.length).length;

	// --column-min-width defaults to 26rem, so a second column needs about 850px
	it("gives two columns with room for two", async() =>
	{
		assert.equal(columns(await at(900)), 2);
	});

	it("drops to one column when a second would go under --column-min-width", async() =>
	{
		assert.equal(columns(await at(700)), 1);
	});

	it("is down to one column well before the 600px fallback query", async() =>
	{
		// The fallback is a backstop, not the thing that normally decides: auto-fit has already
		// given up 250px earlier.  Worth pinning, because the two are easy to confuse when reading
		// grid-base.less.
		assert.equal(columns(await at(500)), 1);
	});
});
