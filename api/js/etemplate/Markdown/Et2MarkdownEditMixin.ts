/**
 * EGroupware eTemplate2 - markdown editing mixin
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */

import {html, LitElement, nothing, type CSSResultGroup} from "lit";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js";
import {dedupeMixin} from "@open-wc/dedupe-mixin";
import {Et2MarkdownMixin} from "./Et2MarkdownMixin";
import editStyles from "./Et2MarkdownEditMixin.styles";
import {
	applyCommand,
	minimalEdit,
	offsetOfLine,
	sourceOffsetForRendered,
	type MarkdownCommand
} from "./MarkdownCommands";
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
export type MarkdownMode = "edit" | "split" | "view";

/**
 * Remembers the last view the user chose, across fields and sessions.
 */
const VIEW_PREFERENCE = "markdown_view";

const VIEW_MODES : { mode : MarkdownMode, icon : string, label : string }[] = [
	{mode: "edit", icon: "pencil", label: "Edit"},
	{mode: "split", icon: "layout-split", label: "Split view"},
	{mode: "view", icon: "eye", label: "Preview"}
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
				editStyles
			];
		}

		/**
		 * Which pane(s) to show.  No effect unless `markdown` is enabled.
		 *
		 * Seeded from the user's last choice, unless the template says otherwise.  Defaults to
		 * "view": a field opens showing the formatted text, and clicking it puts the caret where
		 * you clicked, so reading costs nothing and editing costs one click.
		 */
		@property({type: String, reflect: true, attribute: "markdown-mode"})
		markdownMode : MarkdownMode = "view";

		/** is the on-selection format popup showing? */
		@state() protected _markdownPopupOpen = false;

		/** did the template pin the mode, or may the preference decide? */
		private _markdownModeFromTemplate = false;

		/**
		 * The popup's anchor, kept stable per textarea.
		 *
		 * sl-popup re-runs its positioning whenever `anchor` changes identity.  Building a fresh
		 * virtual element inside render() therefore hands it a "new" anchor on every update and
		 * it never settles, so the object is made once per source node and reused.  The rect
		 * itself is still read live, so it stays accurate while typing and scrolling.
		 */
		private _markdownAnchorFor : HTMLTextAreaElement = null;
		private _markdownAnchor : { getBoundingClientRect : () => DOMRect } = null;

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
		 * A stable virtual element over the current selection, for sl-popup's `anchor`.
		 */
		protected get _markdownSelectionAnchor()
		{
			const node = this._markdownSourceNode;
			if(!node)
			{
				return null;
			}
			if(node !== this._markdownAnchorFor)
			{
				this._markdownAnchorFor = node;
				this._markdownAnchor = selectionVirtualElement(node);
			}
			return this._markdownAnchor;
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
			this._markdownPopupOpen = this.markdownMode !== "view"
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
		 * Clicking the preview puts the caret back into the source where it was clicked.
		 *
		 * In preview it switches to edit first; in split the editor is already visible, so it only
		 * moves the caret.  That switch deliberately does NOT write the view preference - the user
		 * asked to edit this one field, not to change what every field opens as.
		 */
		protected _handleMarkdownPreviewClick = (event : MouseEvent) =>
		{
			const target = <HTMLElement>event.composedPath()[0];
			// a link in the preview is still a link
			if(!target?.closest || target.closest("a"))
			{
				return;
			}

			const block = target.closest("[data-source-line]");
			const line = parseInt(block?.getAttribute("data-source-line") ?? "", 10);
			if(isNaN(line))
			{
				return;
			}

			const value = this._host.value ?? "";
			const offset = this._markdownCaretOffset(event, <HTMLElement>block, line, value);

			if(this.markdownMode === "view")
			{
				this.markdownMode = "edit";
			}

			// the source is only focusable once the mode change has rendered
			this.updateComplete.then(() =>
			{
				const node = this._markdownSourceNode;
				if(node)
				{
					node.focus();
					node.setSelectionRange(offset, offset);
				}
			});
		};

		/**
		 * Where in the source the click landed.
		 * The exact character comes from caretPositionFromPoint
		 * Browsers too old for the shadowRoots argument simply get the start of the block.
		 */
		protected _markdownCaretOffset(event : MouseEvent, block : HTMLElement, line : number,
									   value : string) : number
		{
			const blockStart = offsetOfLine(value, line);

			const caret = (<any>document).caretPositionFromPoint?.(event.clientX, event.clientY,
				{shadowRoots: [this.shadowRoot]});
			const node = caret?.offsetNode;
			if(!node || !block.contains(node))
			{
				return blockStart;
			}

			// offset of the clicked text node within the block's own rendered text
			const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
			let rendered = 0;
			let text : Node;
			while((text = walker.nextNode()))
			{
				if(text === node)
				{
					return sourceOffsetForRendered(value, blockStart, block.textContent ?? "",
						rendered + caret.offset);
				}
				rendered += text.textContent.length;
			}
			return blockStart;
		}

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
                        .anchor=${this._markdownSelectionAnchor}
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
                <div
                        class="markdown-shell__preview" part="markdown-preview"
                        @click=${this._handleMarkdownPreviewClick}
                >
					${this._markdownController.render(this._host.value, true)}
                </div>`;

			// The source pane stays in the DOM in every view, hidden rather than dropped.
			// Shoelace's textarea reaches for this.input in updated() and in validation, so a
			// preview that removed it would throw on the next update.
			const hideSource = this.markdownMode === "view";

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
