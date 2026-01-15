import {html, LitElement, TemplateResult} from 'lit';
import {state} from "lit/decorators/state.js";
import {customElement, property} from 'lit/decorators.js';
import {AiAssistantController} from "./AiAssistantController";
import styles from "./Et2Ai.styles";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {unsafeHTML} from "lit/directives/unsafe-html.js";
import {Et2SelectLang} from "../Et2Select/Select/Et2SelectLang";

// etemplate2 helper (globally available)
declare const etemplate2 : {
	getValues : (node : HTMLElement) => Record<string, any>;
};

export interface AiPrompt
{
	id : string;
	label : string;
	action? : Function | {
		/* Et2 ID, CSS selector or keyword
		*
		* self:     the slotted widget (default)
		* subject:  sibling summary/subject field
		* label:    label/name field
		*/
		target? : string;
		/* How to deal with existing content in the target.  Default replace*/
		mode? : "replace" | "append" | "prepend";
	};
}

export const simplePrompts : AiPrompt[] = [
	{id: 'aiassist.summarize', label: 'Summarize text'},
	{id: "aiassist.generate_subject", label: "Generate a subject", action: {target: "subject"}},
	{id: 'aiassist.formal', label: 'Make more formal'},
	{id: 'aiassist.grammar', label: 'Fix grammar & spelling'},
	{id: 'aiassist.concise', label: 'Make concise'},
	{id: 'aiassist.translate', label: "Translate"}
];

/**
 * @summary AI Assistant widget to process content of slotted elements
 * @since 26.1
 *
 * @dependency sl-card
 * @dependency sl-dropdown
 * @dependency sl-menu
 * @dependency sl-spinner
 * @dependency sl-alert
 * @dependency et2-button-icon
 *
 * @slot - The default slot where the target widget (e.g. et2-textarea, et2-vbox, iframe) is placed.
 * @slot trigger - Custom trigger element for the AI menu. Defaults to an AI assistant icon button.
 *
 * @event et2-ai-start - Emitted when an AI process begins.
 * @event et2-ai-success - Emitted when the AI process completes successfully.
 * @event et2-ai-error - Emitted when the AI process fails.
 * @event et2-ai-apply - Emitted when the user clicks 'Apply' to insert the result into the target.
 *
 * @csspart base - The component's internal wrapper.
 * @csspart result - The sl-card containing the AI result or loader.
 * @csspart loader - Specific part for the result card when in loading state.
 * @csspart spinner - The loading spinner.
 * @csspart result-content - The container for the returned AI text/HTML.
 * @csspart apply-button - The button used to apply the result.
 * @csspart dropdown - The Shoelace dropdown containing prompts.
 * @csspart menu - The menu inside the dropdown.
 * @csspart menu-item - Individual prompt items in the menu.
 * @csspart error - The sl-alert shown on failure.
 *
 * @cssproperty --max-result-height - Automatically calculated based on the slotted element's height to ensure the result card fits.
 */
@customElement('et2-ai')
export class Et2Ai extends Et2Widget(LitElement)
{
	static get styles()
	{
		return [
			super.styles,
			styles
		];
	}

	@property({attribute: false})
	prompts : AiPrompt[] = simplePrompts;

	/* Specify a custom server endpoint for AI queries */
	@property({attribute: false, type: String})
	set endpoint(_val)
	{
		if (_val && this.ai)
		{
			this.ai.endpoint = _val;
		}
		this.noAiAssistant = !_val;
	}
	get endpoint() : string
	{
		return this.ai?.endpoint;
	}

	/* Use different context instead of child value */
	@property({type: Function})
	getContent = () => this._getOriginalValue();

	/* Use a custom target for applying the response */
	@property({type: Function})
	resolveTarget = (prompt? : AiPrompt) => this._findApplyTarget(prompt);

	/* Current selected prompt */
	@state() activePrompt : AiPrompt;
	/* Max height for showing the result */
	@state() maxResultHeight = 0;

	/* Flag for if the user has no access */
	private noAiAssistant : boolean = true;
	private ai;
	private targetResizeObserver : ResizeObserver;

	constructor()
	{
		super();
		this.clearResult = this.clearResult.bind(this);
		this._promptTemplate = this._promptTemplate.bind(this);
		this.ai = new AiAssistantController(this, this.endpoint);
	}

	transformAttributes(attrs)
	{
		// Check for global settings
		const global_data = this.getArrayMgr("modifications").getRoot().getEntry('~ai~', true);
		if(global_data)
		{
			// Specific attributes override global
			Object.assign(attrs, global_data, attrs);
		}
		super.transformAttributes(attrs);
	}

	disconnectedCallback()
	{
		this.targetResizeObserver?.disconnect();
		this.targetResizeObserver = undefined;
		super.disconnectedCallback();
	}

	protected async firstUpdated()
	{
		this.noAiAssistant = !this.endpoint;
		const slot = this.shadowRoot?.querySelector('slot');
		slot?.addEventListener('slotchange', () => this.handleSlotChange());

		this.handleSlotChange();

		// Deal with dropdown positioning trouble due to how we're using it
		const dropdown = this.shadowRoot?.querySelector('sl-dropdown') as any;
		const trigger = this.querySelector("[slot='trigger']") ?? this.shadowRoot?.querySelector("slot[name='trigger']");
		dropdown?.addEventListener('sl-after-show', () =>
		{
			requestAnimationFrame(() =>
			{
				dropdown.reposition?.()
			});
		});
	}


	/**
	 * User chose a prompt from the list
	 *
	 * @param event
	 */
	protected handlePromptSelect(event : CustomEvent)
	{
		const id = event.detail.item.value as string;
		this.activePrompt = this.prompts.find(p => p.id === id);
		if(!this.activePrompt)
		{
			return;
		}

		const originalValue = this.getContent();

		this.dispatchEvent(new CustomEvent('et2-ai-start', {
			detail: {
				prompt: this.activePrompt,
				originalValue,
				target: this.resolveTarget(this.activePrompt)
			},
			bubbles: true,
			composed: true
		}));

		this.ai.run(this.activePrompt.id, originalValue, this.endpoint)
			.then(() =>
			{
				if(this.ai.status === 'success')
				{
					this.dispatchEvent(new CustomEvent('et2-ai-success', {
						detail: {
							prompt: this.activePrompt,
							result: this.ai.result,
							target: this.resolveTarget(this.activePrompt)
						},
						bubbles: true,
						composed: true
					}));
				}
			})
			.catch(() =>
			{
				this.dispatchEvent(new CustomEvent('et2-ai-error', {
					detail: {
						prompt: this.activePrompt,
						error: this.ai.error,
						target: this.resolveTarget(this.activePrompt)
					},
					bubbles: true,
					composed: true
				}));
			})
			.finally(() =>
			{
				this.dispatchEvent(new CustomEvent('et2-ai-stop', {
					detail: {
						prompt: this.activePrompt,
						error: this.ai.error,
						target: this.resolveTarget(this.activePrompt)
					},
					bubbles: true,
					composed: true
				}));
			});
	}

	protected handleLangSelect(event : CustomEvent)
	{
		const select : Et2SelectLang = event.target as unknown as Et2SelectLang;
		const lang = select.value;
		const id = select.dom_id;
		// @ts-ignore
		this.shadowRoot.querySelector("sl-dropdown").open = false;
		select.value = "";

		this.addEventListener("et2-ai-start", (e : CustomEvent) => {e.detail.prompt.id += "-" + lang}, {once: true});
		this.addEventListener("et2-ai-stop", (e : CustomEvent) => {e.detail.prompt.id = id}, {once: true});
		this.handlePromptSelect(new CustomEvent('select', {
			detail: {
				item: {value: id}
			}
		}));
	}

	protected handleSlotChange()
	{
		this.targetResizeObserver?.disconnect();

		const target = this._findSlottedTarget();
		if(!target)
		{
			return;
		}

		this.targetResizeObserver = new ResizeObserver(entries =>
		{
			for(const entry of entries)
			{
				this.maxResultHeight = Math.max(
					entry.contentRect.height,
					80 // minimum usable height
				);
			}
		});

		this.targetResizeObserver.observe(target);
	}

	protected _applyResult()
	{
		let value = this.ai.result;
		const target = this.resolveTarget(this.activePrompt) as any;
		const prompt = this.activePrompt;

		const event = new CustomEvent('et2-ai-apply', {
			detail: {
				prompt: this.activePrompt,
				result: value,
				target
			},
			bubbles: true,
			composed: true
		});
		this.dispatchEvent(event);
		this.ai.status = "idle";
		this.activePrompt = null;

		if(event.defaultPrevented)
		{
			return;
		}

		// Prompt has an actual function to deal with it
		if(typeof prompt.action == "function")
		{
			return prompt.action.call(this, value);
		}

		// Handle internally by setting value
		// Prepend/append to existing value for string responses
		if(typeof value == "string")
		{
			value = value.trim();
			switch(prompt.action?.mode ?? "replace")
			{
				case "prepend":
					value = value + (target.value ?? "");
					break;
				case "append":
					value = (target.value ?? "") + value;
					break;
			}
		}
		if(target)
		{
			if(typeof target.setValue === 'function')
			{
				target.setValue(value);
			}
			else if('value' in target)
			{
				target.value = value;
			}
		}
	}

	public clearResult()
	{
		this.activePrompt = null;
		this.ai.status = "idle";
	}

	/**
	 * Figure out the target
	 * @return {HTMLElement | null}
	 * @protected
	 */
	protected _findApplyTarget(prompt? : AiPrompt) : HTMLElement | null
	{
		// Target from prompt
		const targetSpec = prompt?.action?.target;
		if(targetSpec)
		{
			if(targetSpec === "self")
			{
				return this._findSlottedTarget();
			}

			if(["subject", "label"].includes(targetSpec))
			{
				return this._findSemanticTarget(targetSpec);
			}

			// explicit et2 ID or selector
			return this.getWidgetById(targetSpec) ??
				this.getInstanceManager()?.getWidgetById(targetSpec) ??
				(this.getRootNode() as unknown as ParentNode).querySelector(targetSpec);
		}

		// Default
		return this._findSlottedTarget();
	}

	/**
	 * Find the target if it's the slotted child
	 *
	 * @return {HTMLElement | null}
	 * @protected
	 */
	protected _findSlottedTarget() : HTMLElement | null
	{
		const slot = this.shadowRoot?.querySelector('slot');
		const nodes = slot?.assignedElements({flatten: true}) ?? [];
		return (nodes[0] as HTMLElement) ?? null;
	}

	/**
	 * Try to figure out what the prompt target keywords are referring to
	 *
	 * @param {string} type
	 * @return {HTMLElement | null}
	 * @protected
	 */
	protected _findSemanticTarget(type : string) : HTMLElement | null
	{
		const candidates = {
			subject: ['subject', 'summary', 'title'],
			label: ['label']
		}[type] ?? [];

		for(const name of candidates)
		{
			const el = ((this.getInstanceManager()?.DOMContainer ?? this.getRootNode() as unknown) as ParentNode)
				.querySelector(`[id*="${name}"]`);
			if(el)
			{
				return el as HTMLElement;
			}
		}

		return null;
	}

	/**
	 * Figure out if the response is plain text or has more
	 */
	protected _isHtmlContent(value : string) : boolean
	{
		if(!value || typeof value !== "string")
		{
			return false;
		}
		// Must start with a tag
		if(!value.startsWith("<"))
		{
			return false;
		}

		// Must end with a tag
		if(!value.endsWith(">"))
		{
			return false;
		}

		const tpl = document.createElement("template");
		tpl.innerHTML = value.trim();

		// Look for actual element nodes, not just text
		const hasElements = Array.from(tpl.content.childNodes).some(
			node => node.nodeType === Node.ELEMENT_NODE
		);

		return hasElements;
	}

	/**
	 * Figure out the value to give for the purpose of prompting
	 *
	 * @return {string}
	 * @protected
	 */
	protected _getOriginalValue() : string
	{
		const el = this._findSlottedTarget() as any;
		if(!el)
		{
			return '';
		}

		if(typeof el.getValue === 'function')
		{
			return el.getValue();
		}

		if('value' in el)
		{
			return String(el.value ?? '');
		}

		try
		{
			if(typeof etemplate2?.getValues === 'function')
			{
				const values = etemplate2.getValues(el);
				if(values && Object.keys(values).length)
				{
					return JSON.stringify(values, null, 2);
				}
			}
		}
		catch
		{
			// ignore
		}

		// Target is iframe
		if(el instanceof HTMLIFrameElement)
		{
			try
			{
				const doc = el.contentDocument || el.contentWindow?.document;
				if(!doc)
				{
					return '';
				}

				// Visible text content
				return doc.body?.textContent?.trim() ?? '';
			}
			catch
			{
				// Cross-origin iframe means no access
				return '';
			}
		}

		return el.textContent?.trim() ?? '';
	}


	/**
	 * Render the different helpers based on status
	 *
	 * @return {TemplateResult | null}
	 * @protected
	 */
	protected _renderStatus() : TemplateResult | null
	{
		switch(this.ai.status)
		{
			case 'loading':
				return this._loadingTemplate();

			case 'success':
				return this._resultTemplate();

			case 'error':
				return this._errorTemplate();

			default:
				return null;
		}
	}

	/**
	 * Template for the loading state
	 *
	 * @protected
	 */
	protected _loadingTemplate() : TemplateResult
	{
		return html`
            <sl-card part="result loader" class="et2-ai-loader">
                <span slot="header">${this.activePrompt?.label}</span>
                <et2-button-icon slot="header" name="close" noSubmit @click=${this.clearResult}></et2-button-icon>
                <sl-spinner part="spinner" class="et2-ai-loading"></sl-spinner>
            </sl-card>`;
	}

	/**
	 * Template for the success state
	 *
	 * @protected
	 */
	protected _resultTemplate() : TemplateResult
	{
		const result = this.ai.result ?? '';
		const isHtml = typeof result === 'string' && this._isHtmlContent(result);

		return html`
            <sl-card part="result" class="et2-ai-result">
                <span slot="header">${this.activePrompt?.label}</span>
                <et2-button-icon
                        slot="header"
                        name="close"
                        noSubmit
                        @click=${this.clearResult}>
                </et2-button-icon>

                <div
                        part="result-content"
                        class="et2-ai-result-content ${isHtml ? 'html' : 'text'}"
                >${isHtml
                      ? unsafeHTML(<string>result)
                      : result.trim()
                    }
                </div>

                <sl-button
                        slot="footer"
                        @click=${this._applyResult}
                        part="apply-button">
                    ${this.egw().lang("Apply")}
                </sl-button>
            </sl-card>
		`;
	}


	/**
	 * Template for the error state
	 *
	 * @protected
	 */
	protected _errorTemplate() : TemplateResult
	{
		return html`
            <sl-alert variant="danger" part="result error" class="et2-ai-error"
                      open
                      closable
                      @sl-after-hide=${this.clearResult}
            >
                ${this.ai.error}
            </sl-alert>
		`;
	}

	protected _promptTemplate(prompt : AiPrompt) : TemplateResult
	{
		if(prompt.id == "aiassist.translate")
		{
			return html`
                <et2-select-lang id=${prompt.id}
                                 emptyLabel=${this.egw().lang(prompt.label)}
                                 @change=${this.handleLangSelect}
                ></et2-select-lang>
			`;
		}
		return html`
            <sl-menu-item value=${prompt.id} part="menu-item">${this.egw().lang(prompt.label)}</sl-menu-item>
		`;
	}

	protected render() : TemplateResult
	{
		// No AI for some reason, show just content
		if(this.noAiAssistant)
		{
			return html`
                <slot></slot>`;
		}

		return html`
            <div class="et2-ai form-control" part="base" style="--max-result-height: ${this.maxResultHeight}px;">
                ${this._renderStatus()}
                <slot></slot>
                <sl-dropdown class="et2-ai-dropdown" part="dropdown" placement="bottom-end" hoist no-flip
                             ?disabled=${this.disabled}
                >
                    <slot name="trigger" slot="trigger">
                        <et2-button-icon slot="trigger" name="aitools/navbar" noSubmit></et2-button-icon>
                    </slot>
                    <sl-menu @sl-select=${this.handlePromptSelect} part="menu">
                        ${this.prompts.map(this._promptTemplate)}
                    </sl-menu>
                </sl-dropdown>
            </div>
		`;
	}
}