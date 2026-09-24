import {assert} from "@open-wc/testing";
import {Et2LAvatar} from "../Et2LAvatar";

/**
 * Manual cross-browser benchmark: what does repeated row-rebuild churn actually COST,
 * per browser?
 *
 * Behaviour measured: the profile from the affected instance shows Et2Datagrid rows being
 * torn down and rebuilt continuously, with the time going into web-component constructors
 * (the legacy decorator path) and, downstream, Firefox's cycle collector reclaiming the
 * resulting object graph.  The defects found so far reproduce identically in Firefox and
 * Chromium, so they cannot by themselves explain a Firefox-only report.  This measures
 * whether the same work is simply far more expensive in one engine.
 *
 * Setup: two arms, same shape of DOM churn, repeated for several rounds.
 *   - "web components" builds/attaches/discards N <et2-lavatar> elements
 *   - "plain DOM" builds/attaches/discards N equivalent plain <span><img></span> pairs
 * The plain-DOM arm is the control: it isolates "this engine is slower at DOM work in
 * general" from "this engine is disproportionately slow at custom-element construction and
 * the garbage it produces".  The ratio between the arms is the number that matters, since
 * it cancels out per-machine and per-engine baseline speed.
 *
 * Pass criteria: none - measurement only, like the sibling rowRerender benchmark.  Compare
 * the printed ratio between the browsers.
 *
 * Environment: wall-clock timing, so absolute numbers are machine-dependent and the two
 * browsers are not directly comparable in ms; only each browser's own ratio is.  Garbage
 * collection is not forced, deliberately - unreclaimed churn is part of the cost under study.
 *
 * Run with:
 * npx web-test-runner --config web-test-runner.config.mjs \
 *   api/js/etemplate/Et2Avatar/test/Et2Avatar.churnCost.benchmark.ts
 */

const egwStub = {
	lang: i => i === null || typeof i === "undefined" ? "" : String(i),
	preference: () => null,
	accountData: () => Promise.resolve({}),
	webserverUrl: "",
	debug: () => {},
	decodePath: url => url,
	image: () => "",
	link: url => url,
	tooltipBind: () => {},
	tooltipUnbind: () => {},
	getSessionItem: () => null,
	setSessionItem: () => {},
	removeSessionItem: () => {},
	request: () => Promise.resolve({}),
	window: window
};
// @ts-ignore
window.egw = egwStub;

class ChurnAvatar extends Et2LAvatar
{
	egw() : any
	{
		return window.egw;
	}
}

customElements.define("test-churn-avatar", <CustomElementConstructor><unknown>ChurnAvatar);

const WIDGETS_PER_ROUND = 100;
// The plain-DOM control needs far more elements per round than the web-component arm:
// Firefox quantizes performance.now() to 1ms by default, so a control that takes well under
// a millisecond reads as a flat "1.00ms" and makes any ratio against it meaningless.  Both
// arms are normalized to cost-per-element below, so the differing counts cancel out.
const PLAIN_PER_ROUND = 4000;
const ROUNDS = 12;

function median(values : number[]) : number
{
	const sorted = [...values].sort((a, b) => a - b);
	return sorted[Math.floor(sorted.length / 2)];
}

/** One round: build N elements, attach them, let them render, then discard the lot. */
async function churn(host : HTMLElement, build : () => HTMLElement, count : number) : Promise<number>
{
	const start = performance.now();
	const made : HTMLElement[] = [];
	for(let i = 0; i < count; i++)
	{
		const el = build();
		host.appendChild(el);
		made.push(el);
	}
	// Let custom elements upgrade and render before tearing down, so construction cost is
	// actually incurred rather than skipped by never rendering.
	await Promise.all(made.map(el => (<any>el).updateComplete ?? Promise.resolve()));
	host.replaceChildren();
	return (performance.now() - start) / count;      // cost per element
}

describe("Et2Avatar row-rebuild churn cost", () =>
{
	it("measures web-component churn against a plain-DOM control", async() =>
	{
		const host = document.createElement("div");
		document.body.appendChild(host);

		const widgetTimes : number[] = [];
		const plainTimes : number[] = [];

		for(let round = 0; round < ROUNDS; round++)
		{
			widgetTimes.push(await churn(host, () =>
			{
				const el = document.createElement("test-churn-avatar");
				el.setAttribute("fname", "Ada");
				el.setAttribute("lname", "Lovelace");
				el.setAttribute("contactId", `account:${round}`);
				return el;
			}, WIDGETS_PER_ROUND));

			plainTimes.push(await churn(host, () =>
			{
				const span = document.createElement("span");
				const img = document.createElement("img");
				img.setAttribute("alt", "AL");
				span.appendChild(img);
				span.textContent = "AL";
				return span;
			}, PLAIN_PER_ROUND));
		}

		host.remove();

		const widget = median(widgetTimes);
		const plain = median(plainTimes);
		console.log(`web components: ${(widget * 1000).toFixed(1)}us per element (${WIDGETS_PER_ROUND}/round)`);
		console.log(`plain DOM     : ${(plain * 1000).toFixed(1)}us per element (${PLAIN_PER_ROUND}/round)`);
		console.log(`RATIO (web components / plain DOM): ${(widget / plain).toFixed(1)}x`);

		assert.ok(true, "measurement only");
	});
});
