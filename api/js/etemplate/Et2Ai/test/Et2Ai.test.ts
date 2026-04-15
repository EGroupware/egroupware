import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Ai} from "../Et2Ai";

window.egw = {
	ajaxUrl: () => "",
	user: () => ({apps: {aiassistant: true}}),
	lang: (label : string) => label,
	preference: () => "en",
	request: async() => ({success: true, result: ""})
} as any;

describe("Et2AI widget basics", () =>
{
	afterEach(() =>
	{
		sinon.restore();
	});

	async function createEl()
	{
		return fixture<Et2Ai>(html`
            <et2-ai endpoint="test-endpoint">
                <textarea>Original text</textarea>
            </et2-ai>
		`);
	}

	it("loads Et2Ai class", () =>
	{
		assert.isOk(Et2Ai);
	});

	it("renders only slot content when AI is unavailable", async() =>
	{
		const el = await createEl();
		el.endpoint = "";
		await el.updateComplete;

		const slot = el.shadowRoot!.querySelector("slot:not([name])");
		assert.exists(slot);
		assert.notExists(el.shadowRoot!.querySelector("sl-dropdown"));
		assert.notExists(el.shadowRoot!.querySelector("sl-card"));
		assert.notExists(el.shadowRoot!.querySelector("sl-alert"));
	});

	it("selecting a prompt triggers AI run", async() =>
	{
		const el = await createEl();
		await el.updateComplete;

		const run = sinon.stub((el as any).ai, "run").resolves();
		el.handlePromptSelect({
			detail: {item: {value: el.prompts[0].id}}
		} as any);
		await el.updateComplete;

		assert.isTrue(run.calledOnce);
		assert.equal(el.activePrompt?.id, el.prompts[0].id);
	});

	it("clearResult resets active prompt and AI status", async() =>
	{
		const el = await createEl();
		(el as any).ai.status = "success";
		el.activePrompt = {id: "x", label: "Test"};

		el.clearResult();

		assert.isNull(el.activePrompt);
		assert.equal((el as any).ai.status, "idle");
	});
});

describe("Et2AI applying results", () =>
{
	afterEach(() =>
	{
		sinon.restore();
	});

	async function createApplyEl()
	{
		const el = await fixture<Et2Ai>(html`
            <et2-ai endpoint="test-endpoint">
                <input value="Existing"/>
            </et2-ai>
		`);
		await el.updateComplete;

		(el as any).ai.status = "success";
		(el as any).ai.result = "AI result";
		return el;
	}

	it("dispatches et2-ai-apply and replaces value by default", async() =>
	{
		const el = await createApplyEl();
		const onApply = sinon.spy();
		el.addEventListener("et2-ai-apply", onApply as EventListener);
		el.activePrompt = {
			id: "x",
			label: "Test",
			actions: [{target: "self"}]
		};
		await el.updateComplete;

		const applyButton = el.shadowRoot!.querySelector('et2-button[part="apply-button"]') as HTMLElement;
		applyButton.click();
		await el.updateComplete;

		const target = el.querySelector("input") as HTMLInputElement;
		assert.isTrue(onApply.calledOnce);
		assert.equal(target.value, "AI result");
	});

	it("respects preventDefault on apply event", async() =>
	{
		const el = await createApplyEl();
		el.addEventListener("et2-ai-apply", (e : Event) => e.preventDefault());
		el.activePrompt = {
			id: "x",
			label: "Test",
			actions: [{target: "self"}]
		};
		await el.updateComplete;

		const applyButton = el.shadowRoot!.querySelector('et2-button[part="apply-button"]') as HTMLElement;
		applyButton.click();
		await el.updateComplete;

		const target = el.querySelector("input") as HTMLInputElement;
		assert.equal(target.value, "Existing");
	});
});
