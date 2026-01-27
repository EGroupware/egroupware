import {html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import {state} from "lit/decorators/state.js";
import {customElement, property} from "lit/decorators.js";
import {AiAssistantController, AiStatus} from "./AiAssistantController";
import styles from "./Et2Ai.styles";
import {Et2Widget} from "../Et2Widget/Et2Widget";
import {unsafeHTML} from "lit/directives/unsafe-html.js";
import {map} from "lit/directives/map.js";
import type {et2_htmlarea} from "../et2_widget_htmlarea";
import {classMap} from "lit/directives/class-map.js";
import {Et2SelectWidgets, StaticOptions} from "../Et2Select/StaticOptions";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import {until} from "lit/directives/until.js";

// etemplate2 helper (globally available)
declare const etemplate2 : {
	getValues : (node : HTMLElement) => Record<string, any>;
};

export interface AiPrompt
{
	id : string;
	label : string;
	actions? : AiAction[];
	/* Nested prompts will be shown as sub-arrays */
	children? : AiPrompt[];
}

export type AiAction = { label? : string, handler : Function, target? : never, mode? : never } | {
	label? : string;
	/* Et2 ID, CSS selector or keyword
	*
	* self:     the slotted widget (default)
	* subject:  sibling summary/subject field
	* label:    label/name field
	*/
	target? : string;
	/* How to deal with existing content in the target.  Default replace*/
	mode? : "replace" | "append" | "prepend";

	handler? : never;
};

/* Actions for all prompt responses, unless otherwise set */
const defaultActions : AiAction[] = [
	{label: "apply", target: "self", mode: "replace"},
	{label: "Insert before", target: "self", mode: "prepend"},
	{label: "Insert after", target: "self", mode: "append"}
];

export const simplePrompts : AiPrompt[] = [
	{id: "aiassist.summarize", label: "Summarize text"},
	{id: "aiassist.generate_subject", label: "Generate a subject", actions: [{target: "subject"}]},
	{id: "aiassist.formal", label: "Make more formal"},
	{id: "aiassist.grammar", label: "Fix grammar & spelling"},
	{id: "aiassist.concise", label: "Make concise"},
	{id: "aiassist.translate", label: "Translate", children: []}
];

export const generatePrompts : AiPrompt[] = [
	{id: "aiassist.generate_reply", label: "Professional reply"},
	{id: "aiassist.meeting_followup", label: "Meeting follow-up"},
	{id: "aiassist.thank_you", label: "Thank you note"}
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
 * @slot actions - Additional actions to be shown in the response header
 *
 * @event et2-ai-start - Emitted when an AI process begins.
 * @event et2-ai-success - Emitted when the AI process completes successfully.
 * @event et2-ai-error - Emitted when the AI process fails.
 * @event et2-ai-apply - Emitted when the user clicks "Apply" to insert the result into the target.
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
@customElement("et2-ai")
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
	prompts : AiPrompt[] = Object.assign([], simplePrompts);

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

	/* Use different content instead of child value */
	@property({type: Function})
	getContent = () => this._getSelectedText() || this._getOriginalValue();

	/* Use a custom target for applying the response */
	@property({type: Function})
	resolveTarget = (action? : AiAction, prompt? : AiPrompt) => this._findApplyTarget(action);

	/* Current selected prompt */
	@state() activePrompt : AiPrompt;
	/* Max height for showing the result */
	@state() maxResultHeight = 0;

	/* Flag for if the user has no access */
	private noAiAssistant : boolean = true;
	/* AiAssistantController instance to manage the actual communication */
	private readonly ai : AiAssistantController;
	/* Watch children to keep our size up to date */
	private targetResizeObserver : ResizeObserver;
	/* HTMLArea needs special handling */
	private _htmlAreaTarget : et2_htmlarea;

	/* For faking progress bar */
	private _progressTimer : number | null = null;
	private _progressValue = 0;
	private _lastAiStatus : AiStatus | null = null;

	constructor()
	{
		super();
		this.clearResult = this.clearResult.bind(this);
		this._promptTemplate = this._promptTemplate.bind(this);
		this._actionButtonTemplate = this._actionButtonTemplate.bind(this);

		this.ai = new AiAssistantController(this, this.endpoint);
	}

	transformAttributes(attrs)
	{
		// Check for global settings
		const global_data = this.getArrayMgr("modifications").getRoot().getEntry("~ai~", true);
		if(global_data)
		{
			// Specific attributes override global
			Object.assign(attrs, global_data, attrs);
		}
		// Add translation languages
		this._addTranslations();
		super.transformAttributes(attrs);
	}

	disconnectedCallback()
	{
		this.targetResizeObserver?.disconnect();
		this.targetResizeObserver = undefined;
		super.disconnectedCallback();
		this._stopProgress();
	}

	protected async firstUpdated()
	{
		this.noAiAssistant = !this.endpoint;
		const slot = this.shadowRoot?.querySelector("slot");
		slot?.addEventListener("slotchange", () => this.handleSlotChange());

		this.handleSlotChange();

		// Deal with dropdown positioning trouble due to how we're using it
		const dropdown = this.shadowRoot?.querySelector("sl-dropdown") as any;
		dropdown?.addEventListener("sl-after-show", () =>
		{
			requestAnimationFrame(() =>
			{
				dropdown.reposition?.()
			});
		});
	}

	protected updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);
		const status = this.ai?.status;
		if(status && status !== this._lastAiStatus)
		{
			this._lastAiStatus = status;
			if(status === "loading")
			{
				this._startProgress();
			}
			else
			{
				this._stopProgress();
			}
		}
	}

	/**
	 * Overridden to deal with grabbing legacy HTML Editor before it initializes
	 * @param {Promise<any>[]} promises
	 * @protected
	 */
	protected loadingFinished(promises : Promise<any>[])
	{
		// Use constructor to check for legacy et2_htmlarea without importing it
		if(this.getChildren()[0]?.constructor?.name === "et2_htmlarea")
		{
			this._adoptHTMLAreaTarget(<et2_htmlarea>this.getChildren()[0]);
		}
		super.loadingFinished(promises);
	}

	/**
	 * Add translation options from preferences
	 *
	 * @return {Promise<void>}
	 * @private
	 */
	private async _addTranslations()
	{
		const translation = this._findPrompt("aiassist.translate", this.prompts);
		if(!translation || translation.children.length)
		{
			return;
		}

		const labels = await this._getLanguageLabels();

		if(translation.children.length == 0)
		{
			labels.forEach(lang =>
			{
				translation.children.push({
					id: `${translation.id}-${lang.value}`,
					label: lang.label
				});
			});
		}
	}

	/**
	 * Get language labels for AI translation
	 *
	 * @protected
	 */
	protected async _getLanguageLabels() : Promise<SelectOption[]>
	{
		// Get languages without duplicates
		const languages : string[] = [...new Set([
			<string>this.egw().preference("lang", "common") ?? "en",
			...((<string>this.egw().preference("languages", "aitools") ?? "").split(",") || ["en", "de", "fr", "es-es", "it"])
		])];
		// Need the language names for labels, but that might take a bit
		const labels = await StaticOptions.lang((this as unknown as Et2SelectWidgets), []);

		return languages.map(lang => ({
			value: lang,
			label: (labels.find(o => o.value == lang)?.label ?? lang)
		}));
	}

	/**
	 * User chose a prompt from the list
	 *
	 * @param event
	 */
	protected handlePromptSelect(event : CustomEvent)
	{
		const id = event.detail.item.value as string;
		this.activePrompt = this._findPrompt(id, this.prompts);
		// No action on parents, just leaves
		if(!this.activePrompt || this.activePrompt.children?.length)
		{
			return;
		}
		return this.askAi();
	}

	/**
	 * Run the currently selected prompt and apply the result
	 *
	 * @return {Promise<void>}
	 * @protected
	 */
	protected async askAi()
	{

		const originalValue = this.getContent();
		this.ai.isHTML = this._isHtmlContent(originalValue);

		this.dispatchEvent(new CustomEvent("et2-ai-start", {
			detail: {
				prompt: this.activePrompt,
				originalValue
			},
			bubbles: true,
			composed: true
		}));

		return this.ai.run(this.activePrompt.id, originalValue, this.endpoint)
			.then(() =>
			{
				if(this.ai.status === "success")
				{
					this.dispatchEvent(new CustomEvent("et2-ai-success", {
						detail: {
							prompt: this.activePrompt,
							result: this.ai.result
						},
						bubbles: true,
						composed: true
					}));
				}
			})
			.catch(() =>
			{
				this.dispatchEvent(new CustomEvent("et2-ai-error", {
					detail: {
						prompt: this.activePrompt,
						error: this.ai.error
					},
					bubbles: true,
					composed: true
				}));
			})
			.finally(() =>
			{
				this.dispatchEvent(new CustomEvent("et2-ai-stop", {
					detail: {
						prompt: this.activePrompt,
						error: this.ai.error
					},
					bubbles: true,
					composed: true
				}));
			});
	}

	protected async handleSlotChange()
	{
		this.targetResizeObserver?.disconnect();

		let target = this._findSlottedTarget();
		if(!target)
		{
			return;
		}
		if(this._htmlAreaTarget)
		{
			target = await this._htmlAreaTarget.tinymce.then((e) =>
			{
				return e[0]?.editorContainer ?? this._htmlAreaTarget.editor?.editorContainer;
			});
			if(!target)
			{
				// The editor is not ready, but the Promise is done.  Poll I guess.
				return this.updateComplete.then(() => window.setTimeout(() => this.handleSlotChange(), 1000));
			}
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

	protected _applyResult(action = this.activePrompt?.actions?.[0])
	{
		let value = this.ai.result;
		const target = this.resolveTarget(action, this.activePrompt) as any;

		const event = new CustomEvent("et2-ai-apply", {
			detail: {
				prompt: this.activePrompt,
				result: value,
				target
			},
			bubbles: true,
			composed: true,
			cancelable: true
		});
		this.dispatchEvent(event);

		if(event.defaultPrevented)
		{
			return;
		}
		queueMicrotask(() => this.clearResult());

		// Prompt has an actual function to deal with it
		if(typeof action?.handler === "function")
		{
			return action.handler.call(this, value);
		}

		// Handle internally by setting value
		// Prepend/append to existing value for string responses
		let actionValue = value;
		if(typeof actionValue == "string")
		{
			actionValue = actionValue.trim();
			const currentValue = (typeof target.getValue == "function" ? target.getValue() : target.value) ?? ""
			switch(action?.mode ?? "replace")
			{
				case "prepend":
					actionValue = actionValue + "\n\n" + (currentValue ?? "");
					break;
				case "append":
					actionValue = (currentValue ?? "") + "\n\n" + actionValue;
					break;
			}
		}
		if(target)
		{
			if(typeof target.set_value === "function")
			{
				target.set_value(actionValue);
			}
			else if("value" in target)
			{
				target.value = actionValue;
			}
		}
	}

	public clearResult()
	{
		this.activePrompt = null;
		this.ai.status = "idle";
	}

	/**
	 * Recursively find a prompt by ID
	 *
	 * @param {string} id
	 * @param {AiPrompt[]} prompts
	 * @protected
	 */
	protected _findPrompt(id : string, prompts : AiPrompt[]) : AiPrompt | null
	{
		for(const prompt of prompts)
		{
			if(prompt.id === id)
			{
				return prompt;
			}
			if(prompt.children?.length)
			{
				const found = this._findPrompt(id, prompt.children);
				if(found)
				{
					return found;
				}
			}
		}
		return null;
	}

	/**
	 * Figure out the target
	 * @return {HTMLElement | null}
	 * @protected
	 */
	protected _findApplyTarget(action? : AiAction) : HTMLElement | null
	{
		const targetSpec = typeof action === "object" ? action?.target : undefined;

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
		if(this._htmlAreaTarget)
		{
			// @ts-ignore
			return this._htmlAreaTarget;
		}
		const slot : HTMLSlotElement = this.shadowRoot?.querySelector("slot:not([name])");
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
			subject: ["subject", "summary", "title"],
			label: ["label"]
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
	 * Adopt an HTMLArea element and add Ai bits to it
	 */
	protected _adoptHTMLAreaTarget(target : et2_htmlarea)
	{
		// Turn off autosizing
		target.options.resize_ratio = "0";
		target.options.height = "";

		// If et2_htmlarea is in ascii mode, don't do anything else
		if((target?.mode ?? target.options.mode) == "ascii")
		{
			return;
		}

		this.ai.isHTML = true;
		this._htmlAreaTarget = target;
		this.classList.add("et2-ai--has-html-target");

		// Add generation prompts
		if(!this._findPrompt("generate", this.prompts))
		{
			this.prompts.push({id: "generate", label: this.egw().lang("Generate"), children: generatePrompts});
		}
		// @ts-ignore
		const originalExtended = target._extendedSettings.bind(target);
		const setup = (editor) =>
		{
			// Run original _extendedSettings()
			// @ts-ignore
			const original = originalExtended();
			typeof original["setup"] == "function" && original["setup"](editor);

			// See https://www.tiny.cloud/docs/tinymce/latest/custom-toolbarbuttons/
			editor.ui.registry.addIcon("aitools", "<et2-image src='aitools/navbar'></et2-image>");
			// Add prompts as menu actions
			editor.ui.registry.addMenuButton("aitoolsPrompts", {
				tooltip: this.egw().lang("AI Tools"),
				icon: "aitools",
				fetch: (callback) =>
				{
					// Make sure we have translations in
					this._addTranslations().then(() =>
					{
						callback(this.prompts.map(p => this._promptToTinyMenu(p)));
					})
				}
			});
		};
		// @ts-ignore monkey patching with no restore
		target._extendedSettings = () =>
		{
			const original = originalExtended();
			return Object.assign(original, {
				height: "100%",
				toolbar: (original.toolbar || "") + " | aitoolsPrompts",
				setup: setup
			});
		}
	}

	protected _promptToTinyMenu(prompt : AiPrompt)
	{
		const menuItem = {
			type: (typeof prompt.children == "undefined" ? "menuitem" : "nestedmenuitem"),
			text: this.egw().lang(prompt.label),
			onAction: () => this.handlePromptSelect(new CustomEvent("select", {detail: {item: {value: prompt.id}}}))
		};
		if(typeof prompt.children != "undefined")
		{
			menuItem["getSubmenuItems"] = () => prompt.children.map(p => this._promptToTinyMenu(p));
		}
		return menuItem;
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
		const trimmed = value.trim();
		// Must start with a tag
		if(!trimmed.startsWith("<"))
		{
			return false;
		}

		// Must end with a tag
		if(!trimmed.endsWith(">"))
		{
			return false;
		}

		const tpl = document.createElement("template");
		tpl.innerHTML = trimmed;

		// Look for actual element nodes, not just text
		return Array.from(tpl.content.childNodes).some(
			node => node.nodeType === Node.ELEMENT_NODE
		);
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

		// Iframe
		if(el instanceof HTMLIFrameElement)
		{
			try
			{
				const doc = el.contentDocument;
				if(doc)
				{
					return doc.body.innerHTML;
				}
			}
			catch(e)
			{
				// Cross-origin iframe – fall through to fallback
			}
		}

		// Widgets with a value
		if(typeof el.getValue === "function")
		{
			return el.getValue();
		}

		if("value" in el)
		{
			return String(el.value ?? '');
		}

		// See if we can get values from Etemplate
		try
		{
			if(typeof etemplate2?.getValues === "function")
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



		return el.textContent?.trim() ?? '';
	}


	/**
	 * Get selected text inside the resolved / slotted target only
	 *
	 * @return {string}
	 * @protected
	 */
	protected _getSelectedText() : string
	{
		const el = this._findSlottedTarget() as any;
		if(!el)
		{
			return '';
		}
		if(el && el.editor && typeof el.editor?.selection?.getContent == "function")
		{
			return el.editor.selection.getContent({format: "html"});
		}

		// Iframe (htmlarea, rich text, etc)
		if(el instanceof HTMLIFrameElement)
		{
			try
			{
				const doc = el.contentDocument;
				const sel = doc?.getSelection();
				if(sel && sel.rangeCount > 0)
				{
					return sel.toString().trim();
				}
			}
			catch(e)
			{
				// Cross-origin iframe or inaccessible
				return '';
			}
			return '';
		}

		// Inputs / textareas
		if(typeof el.selectionStart === "number" && typeof el.selectionEnd === "number")
		{
			const start = el.selectionStart;
			const end = el.selectionEnd;
			if(start !== end)
			{
				return String(el.value ?? '').substring(start, end).trim();
			}
			return '';
		}

		// Generic DOM selection (scoped to target element)
		const sel = window.getSelection();
		if(!sel || sel.rangeCount === 0)
		{
			return '';
		}

		const range = sel.getRangeAt(0);
		const container = range.commonAncestorContainer;

		// Only accept selection if it lives inside our target
		if(el.contains(container.nodeType === Node.ELEMENT_NODE
					   ? container
					   : container.parentElement))
		{
			return sel.toString().trim();
		}

		return '';
	}

	/**
	 * Figure out if the element can take a response
	 *
	 * @param {HTMLElement} target
	 * @return {boolean}
	 * @protected
	 */
	protected _canApplyResult(target : HTMLElement = this.resolveTarget(this.activePrompt.actions[0])) : boolean
	{
		if(!target)
		{
			return false;
		}

		// Iframes are always read-only for us
		if(target instanceof HTMLIFrameElement)
		{
			return false;
		}

		// Contenteditable elements
		if((target as HTMLElement).isContentEditable)
		{
			return true;
		}

		// et2 widgets with value API
		const et2 = target as any;
		if(typeof et2.set_value === "function")
		{
			return !target.readonly && !target.disabled;
		}
		if("value" in et2)
		{
			return !target.readonly && !target.disabled;
		}

		return false;
	}

	/**
	 * Start the timer that updates progress bar
	 * @private
	 */
	private _startProgress()
	{
		this._stopProgress();

		this._progressValue = 0;
		const start = performance.now();
		const duration = 60_000; // 60s, it will timeout after that

		this._progressTimer = window.setInterval(() =>
		{
			const elapsed = performance.now() - start;
			const progress = Math.min(100, (elapsed / duration) * 100);

			// Set internal variable for consistency if we re-render
			this._progressValue = progress;

			// Directly update for speed
			const progressbar = this.shadowRoot.querySelector("sl-progress-bar")
			progressbar?.setAttribute("value", progress.toString());
			progressbar.indeterminate = false;

			if(progress >= 100)
			{
				this._stopProgress();
			}
		}, 250); // smooth but cheap
	}

	/**
	 * Stop the timer that updates the progress bar
	 * @private
	 */
	private _stopProgress()
	{
		if(this._progressTimer !== null)
		{
			clearInterval(this._progressTimer);
			this._progressTimer = null;
		}

		this._progressValue = 0;
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
			case "loading":
				return this._loadingTemplate();

			case "success":
				return this._resultTemplate();

			case "error":
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
                <sl-progress-bar part="spinner" class="et2-ai-loading"
                                 label=${this.egw().lang("AI Progress")}
                                 ?value=${this._progressValue}
                ></sl-progress-bar>
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
		const isHtml = typeof result === "string" && this.ai.isHTML;
		const actions = this.activePrompt.actions || defaultActions;

		return html`
            <sl-card part="result" class="et2-ai-result">
                <span slot="header">
					${this.activePrompt.id.includes("translate") ?
                      until(this._translationTemplate(), this.activePrompt?.label) :
                      this.activePrompt?.label
                    }
				</span>
                <et2-hbox slot="header">
                    <slot name="actions"></slot>
                    <sl-copy-button label=${this.egw().lang("Copy to clipboard")} value=${result}></sl-copy-button>
                    <et2-button-icon
                            name="close"
                            noSubmit
                            @click=${this.clearResult}>
                    </et2-button-icon>
                </et2-hbox>

                <div
                        part="result-content"
                        class="et2-ai-result-content ${isHtml ? "html" : "text"}"
                >${isHtml
                      ? unsafeHTML(<string>result)
                      : result.toString().trim()
                    }
                </div>
                <et2-hbox slot="footer">
                    ${map(actions, this._actionButtonTemplate)}
                    <et2-button @click=${this.clearResult}>${this.egw().lang("cancel")}</et2-button>
                </et2-hbox>
            </sl-card>
		`;
	}

	protected _actionButtonTemplate(action : AiAction) : TemplateResult | symbol
	{
		if(!this._canApplyResult(this.resolveTarget(action)))
		{
			return nothing;
		}

		let label = this.egw().lang(action.label ?? "Apply");

		return html`
            <et2-button part="apply-button"
                        @click=${() => this._applyResult(action)}
            >
                ${label}
            </et2-button>`
	}

	protected async _translationTemplate() : Promise<TemplateResult | typeof nothing>
	{
		const targetLang = this.activePrompt.label;
		const sourceLang = this.ai.data?.source_lang;
		let template : symbol | TemplateResult = nothing;
		if(sourceLang)
		{
			const labels = await this._getLanguageLabels();
			template = html`
                <div class="et2-ai-translation">
                    <et2-select
                            aria-label=${this.egw().lang("source language")}
                            .select_options=${labels}
                            value=${sourceLang}
                            @change=${async(e) =>
                            {
                                // Set the source language explicitly
                                this.ai.options.source_lang = e.target.value;
                                await this.askAi();
                                delete this.ai.data?.source_lang;
                            }}
                    ></et2-select>
                    <et2-image src="arrow-right" aria-hidden></et2-image>
                    ${targetLang}
                </div>`;
		}
		else
		{
			template = html`${targetLang}`;
		}
		return template;
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
		let label : string | TemplateResult = this.egw().lang(prompt.label);
		if(prompt.children)
		{
			label = html`${label}
            <sl-menu slot="submenu">
                ${map(prompt.children, this._promptTemplate)}
            </sl-menu>`;
		}
		return html`
            <sl-menu-item
                    value=${prompt.id}
                    part="menu-item"
            >${label}
            </sl-menu-item>
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
            <div class=${classMap({
                "et2-ai": true,
                "et2-ai--has-html-target": this._htmlAreaTarget !== undefined,
                "form-control": true
            })}
                 part="base" style="--max-result-height: ${this.maxResultHeight}px;"
            >
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