import {assert, fixture, html, oneEvent} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Ai} from "../Et2Ai";

window.egw = {
	ajaxUrl: () => "",
	user: () =>
	{
		apps: {
			"aiassistant"
		}
	}
}
describe("Et2AI widget basics", () =>
{
	let testAPIStub : sinon.SinonStub;

	beforeEach(() =>
	{

	});

	afterEach(() =>
	{
		sinon.restore();
	});

	async function createEl()
	{
		const el : Et2Ai = await fixture<Et2Ai>(html`
            <et2-ai>
                <textarea>Original text</textarea>
            </et2-ai>
		`);
		await el.updateComplete;
		return el;
	}

	// Make sure it works
	it('is defined', async() =>
	{
		const element = await createEl()
		assert.instanceOf(element, Et2Ai);
	});


	describe("initial rendering", () =>
	{
		it("renders only slot content when AI is unavailable", async() =>
		{
			const el = await createEl();
			const slot = el.shadowRoot!.querySelector("slot");

			assert.exists(slot);
			assert.notExists(el.shadowRoot!.querySelector("sl-dropdown"));
		});
	});

	describe("prompt selection", () =>
	{
		it("sets activePrompt and dispatches et2-ai-start", async() =>
		{
			const el = await createEl();

			const menu = el.shadowRoot!.querySelector("sl-menu")!;
			const handler = sinon.spy();
			el.addEventListener("et2-ai-start", handler);

			el.handlePromptSelect({
				detail: {item: {value: el.prompts[0].id}}
			} as any);

			assert.isTrue(handler.calledOnce);
			assert.equal(el.activePrompt!.id, el.prompts[0].id);
		});
	});
});

describe("Et2AI applying results", () =>
{
	let el : Et2Ai;

	beforeEach(async() =>
	{
		el = await fixture<Et2Ai>(html`
            <et2-ai>
                <input value="Existing"/>
            </et2-ai>
		`);
		await el.updateComplete;

		// Fake a successful AI response
		(el as any).ai.endpoint = "test";
		(el as any).ai.result = "AI result";
		(el as any).ai.status = "success";
	});

	afterEach(() =>
	{
		sinon.restore();
	});

	it("dispatches et2-ai-apply event", async() =>
	{
		el.activePrompt = {id: "x", label: "Test"};

		const ev = oneEvent(el, "et2-ai-apply");
		el["_applyResult"]();

		const e = await ev;
		assert.equal(e.detail.result, "AI result");
	});

	it("resets activePrompt and AI status", async() =>
	{
		el.activePrompt = {id: "x", label: "Test"};

		el["_applyResult"]();

		await el.updateComplete;

		assert.isNull(el.activePrompt);
		assert.equal((el as any).ai.status, "idle");
	});

	it("respects preventDefault on apply event", () =>
	{
		el.activePrompt = {id: "x", label: "Test"};

		el.addEventListener("et2-ai-apply", e => e.preventDefault());

		const target = el.querySelector("input")!;
		el["_applyResult"]();

		assert.equal(target.value, "Existing");
	});

	it("applies result via function action", () =>
	{
		const target = el.querySelector("input")!;
		const fn = sinon.spy();

		el.activePrompt = {
			id: "x",
			label: "Test",
			actions: [{handler: fn}]
		};

		el["_applyResult"]();

		assert.isTrue(fn.calledOnce);
		assert.equal(fn.firstCall.args[0], "AI result");
	});

	it("replaces target value by default", () =>
	{
		const target = el.querySelector("input")!;

		el.activePrompt = {
			id: "x",
			label: "Test",
			actions: [{target: "self"}]
		};

		el["_applyResult"]();

		assert.equal(target.value, "AI result");
	});

	it("prepends value", () =>
	{
		const target = el.querySelector("input")!;

		el.activePrompt = {
			id: "x",
			label: "Test",
			actions: [{target: "self", mode: "prepend"}]
		};

		el["_applyResult"]();

		assert.equal(target.value, "AI resultExisting");
	});

	it("appends value", () =>
	{
		const target = el.querySelector("input")!;

		el.activePrompt = {
			id: "x",
			label: "Test",
			actions: [{target: "self", mode: "append"}]
		};

		el["_applyResult"]();

		assert.equal(target.value, "ExistingAI result");
	});
});
