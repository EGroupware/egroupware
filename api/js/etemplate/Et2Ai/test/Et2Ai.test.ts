import {assert, fixture, html} from "@open-wc/testing";
import * as sinon from "sinon";
import {Et2Ai} from "../Et2Ai";

window.egw = {
	ajaxUrl: () => "",
	user: () => ({apps: {aiassistant: true}}),
	lang: (label : string) => label,
	preference: () => "en",
	request: async() => ({success: true, result: ""}),
	prompts: () => []
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
		el.prompts = [{id: "prompt-1", label: "Prompt 1"}];
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

	/**
	 * No prompts at all (AI unconfigured/disabled - the server never sends any, see api/user.php)
	 * must hide the whole UI, not show a trigger button with an empty/broken menu behind it - found
	 * live 2026-09-17 (ralf, boulder.egroupware.org, ticket #124681 follow-up): a per-instance
	 * server-side "disable" modification never reached a widget mounted from a referenced
	 * sub-template (eg. mail's preview pane), so the icon stayed visible there regardless of
	 * whether AI was actually configured. Replaced with deriving uiDisabled purely from whether
	 * there's anything to show - a state that can't exist wrong regardless of template nesting,
	 * since prompts are delivered globally (egw.set_prompts(), same channel as egw.set_user()).
	 */
	it("hides the UI when there are no prompts to show", async() =>
	{
		const el = await createEl();
		el.prompts = [];
		sinon.stub(el, "getInstanceManager").returns({app: "test"} as any);

		const attrs : any = {};
		el.transformAttributes(attrs);

		assert.isTrue(attrs.uiDisabled);
	});

	it("does not disable the UI when prompts are available", async() =>
	{
		const el = await createEl();
		el.prompts = [{id: "prompt-1", label: "Prompt 1"}];

		const attrs : any = {};
		el.transformAttributes(attrs);

		assert.notOk(attrs.uiDisabled);
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

	it("applies changes made to the result by an et2-ai-apply listener", async() =>
	{
		const el = await createApplyEl();
		// The listener transforms the event payload; the transformed value must reach the target.
		el.addEventListener("et2-ai-apply", (event : Event) =>
		{
			(event as CustomEvent).detail.result += " with signature";
		});
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
		assert.equal(target.value, "AI result with signature");
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

	// Minimal stand-in for Et2Iframe.ts: a custom element that keeps a real <iframe> in its own
	// shadow root and exposes it via an `.iframe` getter, WITHOUT itself being an
	// HTMLIFrameElement - exactly the shape that broke _getOriginalValue()/_getSelectedText()/
	// _canApplyResult() below, which all used to check `el instanceof HTMLIFrameElement` directly.
	class FakeIframeWrapper extends HTMLElement
	{
		connectedCallback()
		{
			this.attachShadow({mode: "open"});
			this.shadowRoot!.appendChild(document.createElement("iframe"));
		}

		get iframe() : HTMLIFrameElement
		{
			return this.shadowRoot!.querySelector("iframe")!;
		}
	}
	if(!customElements.get("fake-iframe-wrapper"))
	{
		customElements.define("fake-iframe-wrapper", FakeIframeWrapper);
	}

	// Regression tests: reported live as AiTools translating (and, separately, "get selected
	// text") an <et2-ai> wrapping an <et2-iframe> (mail's preview pane body) always returning an
	// empty result. `el instanceof HTMLIFrameElement` is false for `<et2-iframe>` - the slotted
	// element is the widget HOST, not the real iframe inside its shadow root - so the iframe
	// branch was silently skipped, falling through to a generic `el.textContent` (empty for a
	// webcomponent whose content lives in its shadow root, not as light-DOM text).
	describe("iframe-wrapping widgets (eg. Et2Iframe)", () =>
	{
		async function createIframeWrapperEl()
		{
			return fixture<Et2Ai>(html`
                <et2-ai endpoint="test-endpoint">
                    <fake-iframe-wrapper></fake-iframe-wrapper>
                </et2-ai>
			`);
		}

		it("_getOriginalValue() reads the real iframe's content, not an empty string", async() =>
		{
			const el = await createIframeWrapperEl();
			const wrapper = el.querySelector("fake-iframe-wrapper") as any;
			const doc = wrapper.iframe.contentDocument;
			doc.open();
			doc.write("<p>Mail body content</p>");
			doc.close();

			assert.equal((el as any)._getOriginalValue(), "<p>Mail body content</p>");
		});

		it("_getSelectedText() reads a selection made inside the real iframe", async() =>
		{
			const el = await createIframeWrapperEl();
			const wrapper = el.querySelector("fake-iframe-wrapper") as any;
			const doc = wrapper.iframe.contentDocument;
			doc.open();
			doc.write("<p id='target'>Select this text</p>");
			doc.close();

			const range = doc.createRange();
			range.selectNodeContents(doc.getElementById("target"));
			doc.getSelection()!.removeAllRanges();
			doc.getSelection()!.addRange(range);

			assert.equal((el as any)._getSelectedText(), "Select this text");
		});

		it("_canApplyResult() treats it as read-only, even though it defines set_value() (like Et2Iframe does)", async() =>
		{
			const el = await createIframeWrapperEl();
			const wrapper = el.querySelector("fake-iframe-wrapper") as any;
			wrapper.set_value = () => {};

			assert.isFalse((el as any)._canApplyResult(wrapper));
		});
	});
});
