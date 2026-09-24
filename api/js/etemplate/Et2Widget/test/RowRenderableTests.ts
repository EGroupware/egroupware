import {assert} from "@open-wc/testing";

/**
 * Shared contract test for any widget that can appear in an Et2Datagrid/Et2Nextmatch row
 * template.
 *
 * Et2Datagrid renders a row by handing `rowElement.outerHTML` to lit's `unsafeHTML()`, which
 * replaces the whole row node whenever that string differs from last time - re-running every
 * widget constructor in the row.  An unchanged string keeps the same physical nodes (see
 * Et2Datagrid.rowRerender.benchmark.ts).  So a widget in a row has to reach its final attribute
 * set during its first render: anything it writes to its own attributes afterwards rebuilds the
 * row it sits in.
 *
 * This is invisible in ordinary widget tests, because the widget renders correctly either way -
 * just far more often than it should.  Et2Avatar.image was reflected while being resolved over
 * the network, which rebuilt one row per avatar in every mail list.
 *
 * Call this from your widget's own test file, the same way input widgets call
 * inputBasicTests().  It does not replace widget-specific tests; it is the baseline that keeps
 * a row-renderable widget cheap to render.
 *
 * @example
 * async function before()
 * {
 *     return <Et2Avatar><unknown>await fixture(`<et2-avatar contactid="account:1"></et2-avatar>`);
 * }
 * rowRenderableTests(before, {settleMs: 300, requireResolved: el => !!el.image});
 *
 * @param before Function creating the widget, run before each test, returning the widget.
 * @param options See {@link RowRenderableOptions}.
 */
export interface RowRenderableOptions
{
	/**
	 * How long to leave the widget alone before re-reading its HTML.  Must comfortably outlast
	 * whatever the widget resolves asynchronously (a fetch, a queued request, a debounce),
	 * otherwise the test passes because nothing had happened yet rather than because nothing
	 * changes.  Default 300ms.
	 */
	settleMs? : number;
	/**
	 * Optional non-vacuity check, STRONGLY recommended for any widget that resolves something
	 * asynchronously.  Without it, "the HTML did not change" is also satisfied by a widget that
	 * silently did nothing at all - which is exactly what a wrong fix looks like.  Return true
	 * once the widget has genuinely produced its late value (eg. `el => !!el.image`).
	 */
	requireResolved? : (element : any) => boolean;
	/** Names of checks to skip; each needs a one-line comment at the call site saying why. */
	skip? : string[];
}

export function rowRenderableTests(before : Function, options : RowRenderableOptions = {})
{
	const settleMs = options.settleMs ?? 300;
	const skip = options.skip || [];

	describe("Row-renderable contract", () =>
	{
		if(!skip.includes("stable-html"))
		{
			it("does not change its own attributes after first render", async function()
			{
				this.timeout(Math.max(10000, settleMs * 8));
				const element = await before();
				await(element.updateComplete ?? Promise.resolve());
				const atFirstRender = element.outerHTML;

				await new Promise(resolve => setTimeout(resolve, settleMs));
				const afterSettle = element.outerHTML;

				if(options.requireResolved)
				{
					assert.isTrue(options.requireResolved(element),
						"the widget never produced its asynchronous value, so HTML stability here " +
						"proves nothing - a widget that does nothing is trivially stable");
				}
				assert.equal(atFirstRender, afterSettle,
					"this widget rewrote its own attributes after rendering.  In a datagrid row " +
					"that changes rowElement.outerHTML, and unsafeHTML() then replaces the whole " +
					"row - rebuilding every widget in it.  Keep late-resolved values off the " +
					"host's attributes (do not reflect them); the property can still change.");
			});
		}

		if(!skip.includes("stable-rehydration"))
		{
			it("serializes the same after being rebuilt from its own HTML", async function()
			{
				this.timeout(Math.max(10000, settleMs * 8));
				const element = await before();
				await(element.updateComplete ?? Promise.resolve());
				await new Promise(resolve => setTimeout(resolve, settleMs));
				const settled = element.outerHTML;

				// Rebuild it the way unsafeHTML() would, from the serialized string.
				const host = document.createElement("div");
				document.body.appendChild(host);
				host.innerHTML = settled;
				const rebuilt : any = host.firstElementChild;
				await(rebuilt?.updateComplete ?? Promise.resolve());
				await new Promise(resolve => setTimeout(resolve, settleMs));
				const rebuiltHtml = rebuilt?.outerHTML;
				host.remove();

				assert.equal(rebuiltHtml, settled,
					"a widget rebuilt from its own serialized HTML serializes differently, so each " +
					"row rebuild would feed another one instead of converging");
			});
		}
	});
}
