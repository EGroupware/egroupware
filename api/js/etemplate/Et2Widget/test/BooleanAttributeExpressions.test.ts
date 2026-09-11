import {assert, elementUpdated, fixture, html, oneEvent} from "@open-wc/testing";
import * as sinon from "sinon";
import {et2_arrayMgr} from "../../et2_core_arrayMgr";
import {Et2Template} from "../../Et2Template/Et2Template";
import {Et2Description} from "../../Et2Description/Et2Description";
import "../../Layout/Et2Box/Et2Box";

/**
 * Contract: hidden= and disabled= accept the full expression syntax the server's
 * check_disabled() understands - a bare @ref, a leading "!" negation, "=value" and
 * "=/regex/" comparisons, and any combination - for widgets a template builds
 * client-side, where no PHP ever saw the expression.
 *
 * This works because transformAttributes() chooses its resolver by the property's
 * declared type, not by attribute name: a Boolean property goes through
 * parseBoolExpression(), everything else through expandName(), which only handles a
 * bare @ref.  hidden and disabled are both declared @property({type: Boolean}) on the
 * Et2Widget mixin, so both get the expression-aware resolver.  Declaring either as
 * anything but Boolean would silently drop every comparison (resolving to undefined,
 * so the widget never hides) and turn every negation into a literal truthy string (so
 * it always hides) - the kind of breakage that shows up as a layout bug in one app
 * rather than as a failing build.
 *
 * Setup: each case builds a detached widget, hands it a content arrayMgr directly and
 * calls transformAttributes() - the same entry point loadWebComponent() uses.  The
 * template cases go through a real Et2Template load instead, so the child widgets are
 * created the way a client-loaded .xet creates them.
 *
 * Pass criteria: every expression form resolves to the same boolean the server would
 * compute, and a false result leaves no attribute behind - a boolean attribute applies
 * by presence, so hidden="false" would still hide.
 */

// Stub global egw, as the widgets call it while rendering
// @ts-ignore
window.egw = {
	debug_level: () => 0,
	debug: () => {},
	image: () => "",
	lang: i => i + "",
	link: i => i,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	webserverUrl: ""
};

// Et2Description is what most cases carry the attribute on, keep the import
let keepImport : Et2Description = new Et2Description();

const CONTENT = {
	yes: "yes",
	no: "no",
	empty: "",
	is_true: true,
	is_false: false,
	video: {session: "simulated", published: 1}
};

/**
 * Resolve one attribute expression the way loadWebComponent() would, and report both
 * what the widget ended up with and what the arrayMgr thinks the answer is.
 */
function resolve(attribute : string, expression : string, tag = "et2-description")
{
	const widget : any = document.createElement(tag);
	widget.setArrayMgr("content", new et2_arrayMgr(CONTENT));
	widget.transformAttributes({[attribute]: expression});

	return {
		attribute: widget.getAttribute(attribute),
		property: widget[attribute]
	};
}

function assertResolves(attribute : string, expression : string, expected : boolean, tag? : string)
{
	const result = resolve(attribute, expression, tag);

	assert.equal(!!result.property, expected,
		`${attribute}="${expression}" should resolve to ${expected}`);
	// A boolean attribute applies by presence: "false" would still apply
	assert.equal(result.attribute !== null, expected,
		`${attribute}="${expression}" left the attribute as ${JSON.stringify(result.attribute)}`);
}

describe("Boolean attribute expressions", () =>
{
	// Every form check_disabled() supports, and what it means against CONTENT above
	const EXPRESSIONS : [string, boolean][] = [
		["@yes", true],
		["@empty", false],
		["@missing", false],
		["@is_true", true],
		["@is_false", false],
		["!@yes", false],
		["!@empty", true],
		["!@missing", true],
		["@no=no", true],
		["@no=yes", false],
		["!@no=no", false],
		["!@no=yes", true],
		["@video[session]=simulated", true],
		["@video[session]=running", false],
		["@video[published]=1", true],
		["@video[session]=/(hosting|running|simulated)/", true],
		["@video[session]=/(hosting|running)/", false],
		["!@video[session]=/(hosting|running)/", true],
		["!@video[session]=/(hosting|running|simulated)/", false]
	];

	// The two attributes share one code path, so neither may be allowed to drift alone
	["hidden", "disabled"].forEach(attribute =>
	{
		describe(attribute, () =>
		{
			EXPRESSIONS.forEach(([expression, expected]) =>
			{
				it(`resolves ${attribute}="${expression}" to ${expected}`, () =>
				{
					assertResolves(attribute, expression, expected);
				});
			});

			it("is declared Boolean, which is what selects the expression-aware resolver", () =>
			{
				const widgetClass : any = window.customElements.get("et2-description");
				const options : any = widgetClass.getPropertyOptions(attribute);

				assert.equal(typeof options === "object" ? options.type : options, Boolean,
					`${attribute} must stay type: Boolean, or transformAttributes() falls back to ` +
					`expandName() and silently drops every comparison and negation`);
			});

			it("resolves expressions on a layout widget too", () =>
			{
				assertResolves(attribute, "@video[session]=simulated", true, "et2-hbox");
				assertResolves(attribute, "!@video[session]=simulated", false, "et2-hbox");
			});
		});
	});

	it("removes an attribute already on the widget when the expression resolves false", async() =>
	{
		const widget : any = document.createElement("et2-description");
		document.body.append(widget);
		try
		{
			widget.setArrayMgr("content", new et2_arrayMgr(CONTENT));

			// Reflect it into the DOM first, the way an earlier pass over the same widget does -
			// transformAttributes() runs again for "modifications", and a template can reload
			widget.hidden = true;
			await widget.updateComplete;
			assert.isNotNull(widget.getAttribute("hidden"), "setup: hidden should have reflected");

			widget.transformAttributes({hidden: "@no=yes"});

			// setAttribute("hidden", false) would write the string "false", which still hides
			assert.isNull(widget.getAttribute("hidden"),
				"a false result must remove the attribute, not write it as a string");
			assert.isFalse(!!widget.hidden, "widget should no longer be hidden");
		}
		finally
		{
			widget.remove();
		}
	});

	it("leaves an already-boolean value alone", () =>
	{
		const widget : any = document.createElement("et2-description");
		widget.setArrayMgr("content", new et2_arrayMgr(CONTENT));
		widget.transformAttributes({hidden: true, disabled: false});

		assert.isTrue(widget.hidden, "hidden={true} should stay true");
		assert.isFalse(!!widget.disabled, "disabled={false} should stay false");
		assert.isNull(widget.getAttribute("disabled"), "disabled={false} should leave no attribute");
	});
});

describe("Boolean attribute expressions in a client-loaded template", () =>
{
	// The blocks are addressed by class, not id: an id on a box opens a namespace, and as
	// these names are not in the content, that would leave the children an empty arrayMgr
	// and every @ref inside resolving to nothing - a different problem than the one here.
	const TEMPLATE = `<overlay><template id="bool_expr">
		<et2-hbox class="staff_block" hidden="!@is_staff">
			<et2-description value="staff"></et2-description>
		</et2-hbox>
		<et2-hbox class="student_block" hidden="@is_staff">
			<et2-description class="not_started" value="not started"
			                 hidden="@video[session]=/(hosting|running|simulated)/"></et2-description>
			<et2-box class="recording" hidden="!@video[session]=/(hosting|running|simulated)/"></et2-box>
		</et2-hbox>
		<et2-description class="comparison" value="compared" disabled="@no=no"></et2-description>
	</template></overlay>`;

	let element : Et2Template;

	before(() =>
	{
		const node = new window.DOMParser().parseFromString(TEMPLATE, "text/xml").children[0];
		Et2Template.templateCache["bool_expr"] = <Element>node.childNodes.item(0);
	});

	beforeEach(async() =>
	{
		// @ts-ignore
		element = await fixture(html`
            <et2-template></et2-template>`);
		sinon.stub(element, "egw").returns(window.egw);
		await elementUpdated(element);
	});

	/**
	 * @param is_staff value the staff/student pair of blocks is switched on
	 */
	async function load(is_staff : any)
	{
		const loaded = oneEvent(element, "load");
		element.setArrayMgr("content", new et2_arrayMgr({...CONTENT, is_staff}));
		element.template = "bool_expr";

		await element.updateComplete;
		await loaded;

		return (block : string) => element.querySelector(`.${block}`);
	}

	it("switches a !@flag / @flag pair of blocks in opposite directions", async() =>
	{
		// A truthy non-boolean is what the server actually sends for a flag like this
		const find = await load("admin");

		assert.isNull(find("staff_block").getAttribute("hidden"),
			`hidden="!@is_staff" must not hide the block when is_staff is truthy`);
		assert.isNotNull(find("student_block").getAttribute("hidden"),
			`hidden="@is_staff" must hide the block when is_staff is truthy`);
	});

	it("switches that same pair back when the flag is falsy", async() =>
	{
		const find = await load(false);

		assert.isNotNull(find("staff_block").getAttribute("hidden"),
			`hidden="!@is_staff" must hide the block when is_staff is falsy`);
		assert.isNull(find("student_block").getAttribute("hidden"),
			`hidden="@is_staff" must not hide the block when is_staff is falsy`);
	});

	it("resolves regex comparisons on children of a hidden block", async() =>
	{
		const find = await load("admin");

		// video.session is "simulated", so it matches the regex
		assert.isNotNull(find("not_started").getAttribute("hidden"),
			"a matching =/regex/ should hide");
		assert.isNull(find("recording").getAttribute("hidden"),
			"the negation of that same matching =/regex/ should not hide");
	});

	it("resolves an =value comparison for disabled=", async() =>
	{
		const find = await load("admin");

		assert.isNotNull(find("comparison").getAttribute("disabled"),
			`disabled="@no=no" should disable`);
	});
});
