/**
 * EGroupware eTemplate2 - markdown editing mixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {css, html, LitElement, nothing, type CSSResultGroup} from "lit";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js";
import {dedupeMixin} from "@open-wc/dedupe-mixin";
import {Et2MarkdownMixin} from "./Et2MarkdownMixin";
import {applyCommand, minimalEdit, type MarkdownCommand} from "./MarkdownCommands";
import {selectionVirtualElement, textareaSelectionRect} from "./textareaSelectionRect";
// self-registering, so the popup works no matter which widget pulled the mixin in first
import "@shoelace-style/shoelace/dist/components/popup/popup.js";

type Constructor<T = {}> = new (...args : any[]) => T;

/**
 * The members Et2Widget / Et2InputWidget actually supply on every host this mixin is applied to,
 * invisible to TypeScript here since the superclass is only typed as Constructor<LitElement>.
 *
 * A `declare egw`/`declare value` field pair used to stand in for this - the idiomatic tsc way to
 * add ambient, no-runtime-emission members to a class - but this project's build does NOT run
 * tsc's own emit: its Babel-based decorator transform does not recognize `declare` and compiled
 * those into real `{kind: "field", value: void 0}` descriptors, each shadowing the real, inherited
 * member with an own `undefined` instance property on every instance - breaking `this.egw()`
 * app-wide for every Et2HtmlArea (found live 2026-09-04: a blank mail compose window hung forever,
 * "TypeError: this.egw is not a function" in Et2HtmlArea's own _menubar getter). Cast `this` to
 * this interface at each use site instead (`(this as unknown as HasEgwAndValue)`) - a cast has no
 * runtime representation at all, in any transform, so it cannot repeat that failure mode. Widening
 * the mixin's own generic constraint to `Constructor<LitElement & HasEgwAndValue>` would avoid the
 * casts, but confuses TypeScript's inference of the OTHER, unrelated members Et2InputWidget mixes
 * in below Et2HtmlArea's own call site - tried live, it turned narrow, correct member access here
 * into a cascade of unrelated "does not exist on type Et2HtmlArea" errors there instead.
 */
interface HasEgwAndValue
{
	egw() : any;
	value : string;
}

/**
 * Which pane(s) the markdown editor is showing.
 */
export type MarkdownMode = "edit" | "split" | "preview";

/**
 * Remembers the last view the user chose, across fields and sessions.
 */
const VIEW_PREFERENCE = "markdown_view";

const VIEW_MODES : { mode : MarkdownMode, icon : string, label : string }[] = [
	{mode: "edit", icon: "pencil", label: "Edit"},
	{mode: "split", icon: "layout-split", label: "Split view"},
	{mode: "preview", icon: "eye", label: "Preview"}
];

/**
 * Block styles offered by the popup's style dropdown.  "normal" strips whatever is there.
 */
const BLOCK_STYLES : { command : MarkdownCommand, label : string }[] = [
	{command: "normal", label: "Normal"},
	{command: "h1", label: "Heading 1"},
	{command: "h2", label: "Heading 2"},
	{command: "h3", label: "Heading 3"},
	{command: "h4", label: "Heading 4"},
	{command: "h5", label: "Heading 5"},
	{command: "h6", label: "Heading 6"},
	{command: "quote", label: "Quote"}
];

const INLINE_COMMANDS : { command : MarkdownCommand, icon : string, label : string }[] = [
	{command: "bold", icon: "type-bold", label: "Bold"},
	{command: "italic", icon: "type-italic", label: "Italic"},
	{command: "strikethrough", icon: "type-strikethrough", label: "Strikethrough"},
	{command: "code", icon: "code", label: "Code"},
	{command: "link", icon: "link-45deg", label: "Link"}
];

const LIST_COMMANDS : { command : MarkdownCommand, icon : string, label : string }[] = [
	{command: "ul", icon: "list-ul", label: "Bullet list"},
	{command: "ol", icon: "list-ol", label: "Numbered list"},
	{command: "checklist", icon: "check2-square", label: "Task list"}
];

/**
 * Ctrl/Cmd combinations the source textarea claims for itself.
 */
const SHORTCUTS : { [key : string] : MarkdownCommand } = {b: "bold", i: "italic", k: "link"};

/**
 * Adds a markdown *editing* surface to a widget that edits its value in a plain textarea.
 *
 * Composes Et2MarkdownMixin rather than extending it: that mixin is display-only and is already
 * on Et2Description and Et2Ai, where a view mode and a format popup would mean nothing.
 *
 * The host keeps rendering its own editor; this mixin wraps it.  When `markdown` is false the
 * mixin contributes nothing at all - the host is expected to return its untouched template - so
 * turning the feature off restores today's behaviour exactly.
 *
 * @example
 * export class Et2Example extends Et2MarkdownEditMixin(Et2InputWidget(LitElement))
 * {
 *     render()
 *     {
 *         const source = html`<textarea .value=${this.value}></textarea>`;
 *         return this.markdown ? this._markdownShellTemplate(source) : source;
 *     }
 * }
 */
export const Et2MarkdownEditMixin = dedupeMixin(<T extends Constructor<LitElement>>(superclass : T) =>
{
	class Et2MarkdownEdit extends Et2MarkdownMixin(superclass)
	{
		static get styles() : CSSResultGroup
		{
			return [
				// @ts-ignore superclass is only typed as Constructor<LitElement>, which has no styles
				...(super.styles ? (Symbol.iterator in Object(super.styles) ? super.styles : [super.styles]) : []),
				css`
					.markdown-shell {
						position: relative;
						display: flex;
						flex-direction: column;
						height: 100%;
						min-height: 0;
					}

					.markdown-shell__panes {
						flex: 1 1 auto;
						min-height: 0;
						display: flex;
					}

					.markdown-shell__panes > * {
						flex: 1 1 auto;
						min-width: 0;
					}

					.markdown-shell__source {
						display: flex;
						min-width: 0;
						min-height: 0;
					}

					.markdown-shell__source > * {
						flex: 1 1 auto;
						min-width: 0;
					}

					/* beats the flex display above, so ?hidden really hides */
					.markdown-shell [hidden] {
						display: none !important;
					}

					/* the preview scrolls on its own, so a long document cannot stretch the field */
					.markdown-shell__preview {
						overflow: auto;
						padding: var(--sl-spacing-x-small);
						background-color: var(--sl-color-neutral-0);
						border: solid var(--sl-input-border-width) var(--sl-input-border-color);
						border-radius: var(--sl-input-border-radius-medium);
					}

					/* the view switcher sits over the top-left corner of the editor, mirroring
					   the AI button in the top-right */
					.markdown-view {
						position: absolute;
						top: var(--sl-spacing-3x-small);
						left: var(--sl-spacing-3x-small);
						z-index: 1;
					}

					.markdown-view::part(panel) {
						padding: var(--sl-spacing-3x-small);
					}

					.markdown-view__panel {
						display: flex;
						gap: var(--sl-spacing-3x-small);
					}

					.markdown-view__option.active::part(base) {
						background-color: var(--sl-color-neutral-200);
						border-radius: var(--sl-border-radius-small);
					}

					.markdown-popup__bar {
						display: flex;
						align-items: center;
						gap: var(--sl-spacing-3x-small);
						padding: var(--sl-spacing-3x-small);
						background-color: var(--sl-panel-background-color);
						border: solid var(--sl-panel-border-width) var(--sl-panel-border-color);
						border-radius: var(--sl-border-radius-medium);
						box-shadow: var(--sl-shadow-large);
					}

					.markdown-popup__separator {
						width: var(--sl-panel-border-width);
						align-self: stretch;
						background-color: var(--sl-panel-border-color);
					}
				`
			];
		}

		/**
		 * Which pane(s) to show.  No effect unless `markdown` is enabled.
		 *
		 * Seeded from the user's last choice, unless the template says otherwise.
		 */
		@property({type: String, reflect: true, attribute: "markdown-mode"})
		markdownMode : MarkdownMode = "edit";

		/** is the on-selection format popup showing? */
		@state() protected _markdownPopupOpen = false;

		/** did the template pin the mode, or may the preference decide? */
		private _markdownModeFromTemplate = false;

		/** see HasEgwAndValue's own docblock for why this cast exists instead of a declared field */
		private get _host() : HasEgwAndValue
		{
			return this as unknown as HasEgwAndValue;
		}

		connectedCallback()
		{
			super.connectedCallback();
			// read before the first update: reflect:true would otherwise make this always true
			this._markdownModeFromTemplate = this.hasAttribute("markdown-mode");
		}

		firstUpdated(changedProperties)
		{
			// @ts-ignore not every superclass defines firstUpdated
			super.firstUpdated?.(changedProperties);

			if(this.markdown && !this._markdownModeFromTemplate)
			{
				const preference = this._host.egw()?.preference(VIEW_PREFERENCE, "common");
				if(VIEW_MODES.some(view => view.mode === preference))
				{
					this.markdownMode = <MarkdownMode>preference;
				}
			}
		}

		/**
		 * The textarea holding the markdown source.
		 *
		 * Both hosts render one into their shadow root - Shoelace's for et2-textarea, its own for
		 * et2-htmlarea in ascii mode.  Override if that ever stops being true.
		 */
		protected get _markdownSourceNode() : HTMLTextAreaElement
		{
			return this.shadowRoot?.querySelector("textarea");
		}

		/**
		 * Switch view and remember it.
		 */
		protected _setMarkdownMode(mode : MarkdownMode)
		{
			this.markdownMode = mode;
			this._markdownPopupOpen = false;
			this._host.egw()?.set_preference("common", VIEW_PREFERENCE, mode);
		}

		/**
		 * Run a command over the current selection and write the result back.
		 */
		protected _applyMarkdownCommand(command : MarkdownCommand)
		{
			const node = this._markdownSourceNode;
			if(!node)
			{
				return;
			}

			const result = applyCommand(node.value, node.selectionStart, node.selectionEnd, command);
			const edit = minimalEdit(node.value, result.value);

			node.focus();
			node.setSelectionRange(edit.from, edit.to);

			// execCommand is deprecated but is still the only way to edit a textarea while keeping
			// the browser's native undo stack - assigning to .value throws the history away.
			// Do NOT "modernise" this to setRangeText(), which has the same problem.
			let applied = false;
			try
			{
				applied = document.execCommand("insertText", false, edit.text);
			}
			catch(e)
			{
				applied = false;
			}
			if(!applied)
			{
				node.value = result.value;
			}

			node.setSelectionRange(result.start, result.end);

			// the web component's value has to follow the DOM node, or the edit is lost on submit
			this._host.value = node.value;
			this.dispatchEvent(new Event("input", {bubbles: true, composed: true}));
			this.dispatchEvent(new Event("change", {bubbles: true, composed: true}));

			this._markdownUpdatePopup();
		}

		/**
		 * Show the popup while there is a selection in the source, hide it otherwise.
		 */
		protected _markdownUpdatePopup()
		{
			const node = this._markdownSourceNode;
			this._markdownPopupOpen = this.markdownMode !== "preview"
				&& !!node && !!textareaSelectionRect(node);
		}

		protected _handleMarkdownSelect = () => this._markdownUpdatePopup();

		/**
		 * Claim Ctrl/Cmd+B, +I and +K.
		 *
		 * They have to be claimed explicitly: Et2Textarea deliberately lets modified keystrokes
		 * bubble out, so without this they reach the nextmatch and document handlers instead.
		 */
		protected _handleMarkdownKeyDown = (event : KeyboardEvent) =>
		{
			// keyup handles repositioning; on keydown the selection is still the pre-keystroke one
			if(!(event.ctrlKey || event.metaKey) || event.altKey)
			{
				return;
			}
			const command = SHORTCUTS[event.key?.toLowerCase()];
			if(!command || !this._markdownSourceNode)
			{
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			this._applyMarkdownCommand(command);
		};

		/**
		 * Hide the popup when focus leaves the widget entirely.
		 */
		protected _handleMarkdownFocusOut = (event : FocusEvent) =>
		{
			const going = <Node>event.relatedTarget;
			if(!going || !(this.contains(going) || this.shadowRoot?.contains(going)))
			{
				this._markdownPopupOpen = false;
			}
		};

		/**
		 * The compact view switcher, pinned over the top-left corner.
		 */
		protected _markdownToggleTemplate()
		{
			const current = VIEW_MODES.find(view => view.mode === this.markdownMode) ?? VIEW_MODES[0];

			return html`
                <et2-dropdown
                        class="markdown-view" part="markdown-view"
                        placement="bottom-start" hoist
                        @click=${(event : MouseEvent) => event.stopPropagation()}
                >
                    <et2-button-icon
                            slot="trigger" noSubmit
                            image=${current.icon}
                            statustext=${this._host.egw().lang("Markdown view: %1", this._host.egw().lang(current.label))}
                    ></et2-button-icon>
                    <div class="markdown-view__panel">
						${VIEW_MODES.map(view => html`
                            <et2-button-icon
                                    noSubmit
                                    class=${classMap({
                                        "markdown-view__option": true,
                                        "active": this.markdownMode === view.mode
                                    })}
                                    image=${view.icon}
                                    statustext=${this._host.egw().lang(view.label)}
                                    @click=${() => this._setMarkdownMode(view.mode)}
                            ></et2-button-icon>`)}
                    </div>
                </et2-dropdown>`;
		}

		/**
		 * The format popup, anchored over the selection.
		 */
		protected _markdownFormatPopupTemplate()
		{
			const node = this._markdownSourceNode;
			if(!node)
			{
				return nothing;
			}

			const button = (entry : { command : MarkdownCommand, icon : string, label : string }) => html`
                <et2-button-icon
                        noSubmit
                        image=${entry.icon}
                        statustext=${this._host.egw().lang(entry.label)}
                        @click=${() => this._applyMarkdownCommand(entry.command)}
                ></et2-button-icon>`;

			return html`
                <sl-popup
                        class="markdown-popup" part="markdown-popup"
                        placement="top" strategy="fixed" flip shift distance="6"
                        ?active=${this._markdownPopupOpen}
                        .anchor=${selectionVirtualElement(node)}
                >
                    <div
                            class="markdown-popup__bar"
                            @mousedown=${(event : MouseEvent) => event.preventDefault()}
                    >
                        <et2-dropdown placement="bottom-start" hoist>
                            <et2-button slot="trigger" noSubmit
                            >${this._host.egw().lang("Normal")}
                            </et2-button>
                            <sl-menu @sl-select=${(event : CustomEvent) =>
									this._applyMarkdownCommand(event.detail.item.value)}>
								${BLOCK_STYLES.map(style => html`
                                    <et2-menu-item value=${style.command}>
										${this._host.egw().lang(style.label)}
                                    </et2-menu-item>`)}
                            </sl-menu>
                        </et2-dropdown>
						${INLINE_COMMANDS.map(button)}
                        <div class="markdown-popup__separator"></div>
						${LIST_COMMANDS.map(button)}
                    </div>
                </sl-popup>`;
		}

		/**
		 * Wrap the host's own editor in the view switcher, the preview pane and the popup.
		 *
		 * Only mounts et2-split in "split" - a splitter that is not visible is not worth its
		 * resize listeners.  It deliberately gets no id, so it never writes a splitter-size
		 * preference of its own.
		 *
		 * @param source the host's untouched editor template
		 */
		protected _markdownShellTemplate(source)
		{
			const preview = html`
                <div class="markdown-shell__preview" part="markdown-preview">
					${this._markdownTemplate(this._host.value)}
                </div>`;

			// The source pane stays in the DOM in every view, hidden rather than dropped.
			// Shoelace's textarea reaches for this.input in updated() and in validation, so a
			// preview that removed it would throw on the next update.
			const hideSource = this.markdownMode === "preview";

			const panes = this.markdownMode === "split"
				? html`
                    <et2-split>
                        <div slot="start" class="markdown-shell__source">${source}</div>
                        <div slot="end">${preview}</div>
                    </et2-split>`
				: html`
                    <div class="markdown-shell__source" ?hidden=${hideSource}>${source}</div>
					${hideSource ? preview : nothing}`;

			return html`
                <div
                        class="markdown-shell" part="markdown-shell"
                        @select=${this._handleMarkdownSelect}
                        @mouseup=${this._handleMarkdownSelect}
                        @keyup=${this._handleMarkdownSelect}
                        @keydown=${this._handleMarkdownKeyDown}
                        @focusout=${this._handleMarkdownFocusOut}
                >
					${this._markdownToggleTemplate()}
                    <div class="markdown-shell__panes">${panes}</div>
					${this._markdownFormatPopupTemplate()}
                </div>`;
		}
	}

	return Et2MarkdownEdit;
});
