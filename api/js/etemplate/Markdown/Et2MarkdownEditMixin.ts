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
	insertLink,
	minimalEdit,
	offsetOfLine,
	sourceOffsetForRendered,
	type CommandResult,
	type LinkTarget,
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
 * Which view a markdown field opens in.  Read only - set in the preferences app
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
 * The content key an attachment's target entry is read from, when the host names no other.
 *
 * "link_to" is what et2-link-to binds to and what every app with a Links tab already fills with
 * {to_app, to_id} - so a markdown field attaches to the same entry the Links tab links to,
 * without the template having to say so.
 */
const MARKDOWN_UPLOAD_WIDGET = "link_to";

/**
 * Should this file render inline, rather than as a link to download?
 *
 * Only raster images - an SVG is a document that happens to draw, and the sanitizer strips it
 * out of the preview anyway, so offering it as `![...]` would just produce a broken image.
 */
function isInlineImage(mime: string): boolean
{
	return /^image\/(png|jpeg|gif|webp|bmp|avif)$/.test(mime ?? "");
}

/**
 * Every browser hands a pasted screenshot over as "image.png", every single time.
 *
 * The store overwrites a file of the same name, so pasting a second screenshot into the same entry
 * would replace the first one on disk and leave both links pointing at the survivor.  Only a name
 * the browser clearly invented is replaced - a file copied out of a file manager keeps its own.
 */
function namePastedImage(file : File) : File
{
	if(file.name && !/^image\.[a-z0-9]+$/i.test(file.name))
	{
		return file;
	}
	// from the name if it had one, else from the mime type - "image/svg+xml" would otherwise
	// become a file called ".svg+xml"
	const extension = (file.name?.split(".").pop() || file.type.split("/").pop() || "png")
		.toLowerCase().replace(/[^a-z0-9]/g, "") || "png";
	const stamp = new Date().toISOString().replace(/[-:T]/g, "").slice(0, 14);
	return new File([file], `image-${stamp}-${Math.random().toString(36).slice(2, 6)}.${extension}`,
		{type: file.type});
}

/**
 * The files carried by a paste or a drop.
 *
 * @param data clipboardData or dataTransfer
 * @param imagesOnly paste is restricted to images - see _handleMarkdownPaste()
 */
function transferredFiles(data : DataTransfer, imagesOnly : boolean) : File[]
{
	const files = Array.from(data?.files ?? []);
	return imagesOnly ? files.filter(file => /^image\//.test(file.type)).map(namePastedImage) : files;
}

/**
 * Adds a markdown *editing* surface to a widget that edits its value in a plain textarea.
 *
 * Composes Et2MarkdownMixin rather than extending it: that mixin is display-only
 *
 * The host keeps rendering its own editor; this mixin wraps it.
 * When `markdown` is false the shell is left out - the host is expected to return its untouched template -

 * The field controls are the exception: the host renders them whether markdown is on or not, so
 * whatever is slotted into them (eg. et2-ai's button) has one place to go on every such field.
 *
 * @slot field-controls - Controls shown over the top-right corner of the field, next to the view
 *     switcher.  Et2Ai puts its button here.
 *
 * @csspart field-controls - The strip over the top-right corner holding the controls.
 *
 * @example
 * export class Et2Example extends Et2MarkdownEditMixin(Et2InputWidget(LitElement))
 * {
 *     render()
 *     {
 *         const source = html`<textarea .value=${this.value}></textarea>`;
 *         return html`${this.markdown ? this._markdownShellTemplate(source) : source}
 *             ${this._fieldControlsTemplate()}`;
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
		 * Seeded from the user's preference, unless the template says otherwise. Defaults to
		 * "view": a field opens showing the formatted text, and clicking it puts the caret where
		 * you clicked after changing to "edit"
		 */
		@property({type: String, reflect: true, attribute: "markdown-mode"})
		markdownMode : MarkdownMode = "view";

		/**
		 * Where an uploaded image goes: the id of a link_to-style widget whose entry should
		 * receive it, or a URL to post to.
		 *
		 * Lives here rather than on each host because both of them need exactly the same answer -
		 * et2-htmlarea for the images TinyMCE uploads on drag/paste, and any markdown field for
		 * the ones pasted, dropped or attached through the format popup.  Two declarations meant
		 * two places to keep in step, and a markdown textarea that had none at all until one was
		 * added for it.
		 *
		 * Empty by default, NOT "link_to": et2-htmlarea reads this in html mode too, where an
		 * empty value selects TinyMCE's plain upload endpoint.  firstUpdated() fills in the
		 * default, and only once markdown is actually on.
		 */
		@property({type: String, attribute: "image-upload"})
		imageUpload = "";

		/**
		 * May a file be attached from the format popup?
		 *
		 * Set by the server, which is the only side that can see whether the entry this field
		 * belongs to has been saved yet: an upload for an entry with no id parks in the user's
		 * temp directory until the save files it away, and a link written to it in the meantime
		 * would dangle.  Handling that case is a later step - for now the button is simply not
		 * offered.
		 */
		@property({type: Boolean, attribute: "can-attach-file"})
		canAttachFile = false;

		/** is the on-selection format popup showing? */
		@state() protected _markdownPopupOpen = false;

		/** is an upload running?  Only to show it, the button stays usable */
		@state() protected _markdownUploads = 0;

		/**
		 * Where the caret was when each pending upload started.
		 *
		 * An upload is asynchronous and the textarea's selection is long gone by the time it
		 * finishes.  The button also stays usable meanwhile: a second file can be picked while
		 * the first is still uploading, so one snapshot is not enough - the second click would
		 * otherwise send the first file's link to the wrong place.
		 *
		 * Each pending upload therefore keeps its own, under a token minted when it starts.
		 */
		private _markdownFileSelections = new Map<object, { start: number, end: number }>();

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

			if(!this.markdown)
			{
				return;
			}
			if(!this._markdownModeFromTemplate)
			{
				const preference = this._host.egw()?.preference(VIEW_PREFERENCE, "common");
				if(VIEW_MODES.some(view => view.mode === preference))
				{
					this.markdownMode = <MarkdownMode>preference;
				}
			}
			// Default it here rather than in the field initializer above: that initializer also
			// runs for an et2-htmlarea in html mode, where an empty imageUpload is meaningful
			// (it picks TinyMCE's plain upload endpoint) and "link_to" would change where every
			// dragged-in image goes.  Only a markdown field wants the default, and only if the
			// template did not already say.
			if(!this.imageUpload)
			{
				this.imageUpload = MARKDOWN_UPLOAD_WIDGET;
			}
		}

		/**
		 * Whose content the upload endpoint should read the target entry from.
		 *
		 * `imageUpload` - the same attribute TinyMCE uses for exactly this, so a field that accepts
		 * dragged-in images in html mode accepts pasted ones in markdown without being configured
		 * twice.  firstUpdated() defaults it to "link_to", the content key every app with a Links
		 * tab already fills with {to_app, to_id}.
		 */
		protected get _markdownUploadWidgetId() : string
		{
			return this.imageUpload || MARKDOWN_UPLOAD_WIDGET;
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
		 * Switch this field's view.
		 */
		protected _setMarkdownMode(mode : MarkdownMode)
		{
			this.markdownMode = mode;
			this._markdownPopupOpen = false;
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

			this._writeMarkdownSource(applyCommand(node.value, node.selectionStart, node.selectionEnd,
				command));
		}

		/**
		 * Write a command's result back into the source.
		 *
		 * Shared by the format commands and the file buttons - the undo-preserving dance below is
		 * subtle enough that a second copy of it would drift.
		 */
		protected _writeMarkdownSource(result: CommandResult)
		{
			const node = this._markdownSourceNode;
			if(!node)
			{
				return;
			}

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
		 * Remember where to insert, before a dialog or a file picker takes the focus away.
		 *
		 * The bar already preventDefault()s mousedown to keep the textarea's selection alive, so
		 * the range is still readable here - but it will not be by the time an upload comes back,
		 * which is why it is taken now rather than read live in the completion handler.
		 */
		protected _handleMarkdownFileStart = () : object =>
		{
			const node = this._markdownSourceNode;
			// a token rather than the button: the button is reusable immediately, so it cannot
			// identify one upload among several running at once
			const pending = {};
			if(node)
			{
				this._markdownFileSelections.set(pending,
					{start: node.selectionStart, end: node.selectionEnd});
			}
			return pending;
		};

		/**
		 * The selection a pending upload started from, taken out of the map as it is used
		 */
		private _takeMarkdownFileSelection(key) : { start: number, end: number }
		{
			const where = key ? this._markdownFileSelections.get(key) : null;
			if(key)
			{
				this._markdownFileSelections.delete(key);
			}
			return where ?? null;
		}

		/**
		 * Attach the chosen files to the entry, and link them in the source.
		 *
		 * The upload goes through Vfs::ajax_htmlarea_upload() - the same endpoint TinyMCE posts a
		 * dragged-in image to, and the reason `imageUpload` exists.  That endpoint resolves the
		 * target from the *server's* copy of the named widget's content ("link_to" being
		 * {to_app, to_id}), stores the file under the entry - /apps/$app/$id/, which is what makes
		 * it an attachment - and answers with the URL to reach it by.  So there is nothing to
		 * configure here beyond which content key to read, and no second call to link the file:
		 * writing it into the entry's own directory IS the link.
		 */
		protected async _handleMarkdownFilesChosen(files: FileList | File[], pending: object)
		{
			const chosen = Array.from(files ?? []);
			if(!chosen.length)
			{
				this._takeMarkdownFileSelection(pending);
				return;
			}
			this._markdownUploads++;
			try
			{
				// sequentially: each insert shifts the offsets the next one is measured against,
				// and the server answers one file per request anyway
				for(const file of chosen)
				{
					const uploaded = await this._uploadMarkdownFile(file);
					if(uploaded?.url)
					{
						this._insertMarkdownLink({
							name: uploaded.name ?? file.name,
							url: uploaded.url,
							image: isInlineImage(file.type)
						}, this._markdownFileSelections.get(pending));
					}
				}
			}
			finally
			{
				this._takeMarkdownFileSelection(pending);
				this._markdownUploads--;
			}
		}

		/**
		 * POST one file the way TinyMCE does, and read the URL back.
		 *
		 * Not egw().request(): this endpoint takes multipart form-data and switches EGroupware's
		 * own JSON response handling off (Json\Request::isJSONRequest(false)), answering with a
		 * bare {location} - TinyMCE's images_upload_handler contract.
		 */
		protected async _uploadMarkdownFile(file: File): Promise<{ url: string, name?: string }>
		{
			const egw = this._host.egw();
			const body = new FormData();
			body.append("file", file, file.name);

			const url = egw.ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_htmlarea_upload")
				+ "&type=htmlarea"
				+ "&request_id=" + encodeURIComponent((<any>this).getInstanceManager?.()?.etemplate_exec_id ?? "")
				+ "&widget_id=" + encodeURIComponent(this._markdownUploadWidgetId);

			let answer: any = null;
			try
			{
				answer = await (await fetch(url, {method: "POST", body, credentials: "same-origin"})).json();
			}
			catch(e)
			{
				egw.message(egw.lang("Error uploading file"), "error");
				return null;
			}
			// This endpoint answers TinyMCE, which only ever displays what it gets back, so it
			// reports failure by putting the message in the very field the URL would go in - and
			// falls back to a whole data: URL when it has nowhere to store the file.  Neither
			// belongs in an entry's text, and the only thing telling them apart is that a real
			// answer went through Framework::link() and so is a path or an absolute URL.
			const location = answer?.location;
			if(!location || !/^(\/|https?:)/.test(location))
			{
				egw.message(location || egw.lang("Error uploading file"), "error");
				return null;
			}
			return {url: location, name: file.name};
		}

		/**
		 * Write the markdown for an attached file at the caret its interaction started from.
		 *
		 * @param where the snapshot taken when the user clicked, or null if it was lost
		 */
		protected _insertMarkdownLink(link: LinkTarget, where: { start: number, end: number } = null)
		{
			const node = this._markdownSourceNode;
			if(!node)
			{
				return;
			}

			// losing the snapshot should not lose the file - append rather than overwrite
			where = where ?? {start: node.value.length, end: node.value.length};

			const result = insertLink(node.value, where.start, where.end, link);
			this._shiftMarkdownFileSelections(where, result.value.length - node.value.length, result.start);

			this._writeMarkdownSource(result);
			this._markdownLinksChanged();
		}

		/**
		 * Keep the still-pending attachments pointing where they were meant to.
		 *
		 * Two uploads from the same caret is the ordinary case - click, pick, click, pick - and
		 * whichever finishes first moves the text out from under the other one's snapshot.  So
		 * everything after this insert moves with it, and the second file lands after the first
		 * rather than in front of it.
		 *
		 * @param where the range this insert replaced
		 * @param delta how much longer the source got
		 * @param caret where the caret ended up, just after the insert
		 */
		private _shiftMarkdownFileSelections(where: { start: number, end: number }, delta: number,
											 caret: number)
		{
			for(const [key, pending] of this._markdownFileSelections)
			{
				if(pending.start >= where.end)
				{
					this._markdownFileSelections.set(key,
						{start: pending.start + delta, end: pending.end + delta});
				}
				else if(pending.end > where.start)
				{
					// it pointed into the text that was just replaced, so there is nothing left
					// to point at - put it after the insert rather than inside it
					this._markdownFileSelections.set(key, {start: caret, end: caret});
				}
			}
		}

		/**
		 * Tell the entry that its attachments changed.
		 *
		 * There is no generic "links changed" event in the API - tracker's comment_add_vfs()
		 * sweeps the link lists by hand instead - so this does both: refresh what is on screen
		 * now, and dispatch an event so an app can react without patching this mixin.
		 */
		protected _markdownLinksChanged()
		{
			this.dispatchEvent(new CustomEvent("et2-link-changed", {bubbles: true, composed: true}));

			const container = (<any>this).getInstanceManager?.()?.widgetContainer;
			container?.querySelectorAll?.("et2-link-list")
				.forEach((list: any) => list.get_links?.());
		}

		/**
		 * Paste an image straight into the text.
		 *
		 * This is the gesture that matters: screenshot, Ctrl+V, done - and the one the html editor
		 * has had all along (Et2HtmlArea gives TinyMCE paste_data_images and the same upload URL),
		 * so markdown mode was the odd one out.
		 *
		 * Images only.  A paste carries whatever is on the clipboard and most pastes are text; a
		 * copied PDF has to go through the attach button, where the user asked for it explicitly.
		 */
		protected _handleMarkdownPaste = (event : ClipboardEvent) =>
		{
			if(!this._canUploadMarkdownFiles())
			{
				return;
			}
			const files = transferredFiles(event.clipboardData, true);
			if(!files.length)
			{
				return;
			}
			// only now, or a plain text paste would lose its text
			event.preventDefault();
			this._handleMarkdownFilesChosen(files, this._handleMarkdownFileStart());
		};

		/**
		 * Let a file dropped on the source through, so the browser fires "drop" at all.
		 */
		protected _handleMarkdownDragOver = (event : DragEvent) =>
		{
			if(!this._canUploadMarkdownFiles() || !event.dataTransfer?.types?.includes("Files"))
			{
				return;
			}
			event.preventDefault();
			event.dataTransfer.dropEffect = "copy";
		};

		/**
		 * Drop a file into the text, at the point it was dropped on.
		 *
		 * Any file, not just images - a drop is as deliberate as picking one through the button,
		 * and a non-image just becomes a link instead of an embed.
		 */
		protected _handleMarkdownDrop = (event : DragEvent) =>
		{
			if(!this._canUploadMarkdownFiles())
			{
				return;
			}
			const files = transferredFiles(event.dataTransfer, false);
			if(!files.length)
			{
				return;
			}
			event.preventDefault();
			// et2-file listens for drops on a container around us (dropTarget), and would upload
			// the same file a second time as an attachment of its own
			event.stopPropagation();

			const node = this._markdownSourceNode;
			const offset = this._markdownDropOffset(event);
			if(node && offset !== null)
			{
				node.focus();
				node.setSelectionRange(offset, offset);
			}
			this._handleMarkdownFilesChosen(files, this._handleMarkdownFileStart());
		};

		/**
		 * Where in the source a drop landed, or null if the browser will not say.
		 */
		protected _markdownDropOffset(event : DragEvent) : number | null
		{
			const node = this._markdownSourceNode;
			const caret = (<any>document).caretPositionFromPoint?.(event.clientX, event.clientY,
				{shadowRoots: [this.shadowRoot]});
			// the textarea renders its text in an internal node, so the offset is already the one
			// we want - but only if the drop actually landed in our own source
			return node && caret?.offsetNode && node.contains(caret.offsetNode) ? caret.offset : null;
		}

		/**
		 * May a pasted or dropped file be uploaded right now?
		 *
		 * Same gate as the attach button: without a target the upload endpoint has nowhere to put
		 * the file and answers with a whole data: URL, which does not belong in an entry's text.
		 */
		protected _canUploadMarkdownFiles() : boolean
		{
			return this.markdown && this.canAttachFile && this.markdownMode !== "view"
				&& !(<any>this).readonly && !(<any>this).disabled;
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
		 * Clicking the preview puts the caret back into the source where it was clicked,
		 * or after the last word when the click landed on no text.
		 *
		 * In preview it switches to edit first; in split the editor is already visible, so it only
		 * moves the caret.  Either way the switch is local to this field, like every other view
		 * change - see _setMarkdownMode().
		 */
		protected _handleMarkdownPreviewClick = (event : MouseEvent) =>
		{
			const target = <HTMLElement>event.composedPath()[0];
			// a link in the preview is still a link
			if(!target?.closest || target.closest("a"))
			{
				return;
			}

			const value = this._host.value ?? "";
			const block = target.closest("[data-source-line]");
			const line = parseInt(block?.getAttribute("data-source-line") ?? "", 10);

			// A click that hit no block at all - the empty space under the text, or the whole
			// pane when there is no text yet - still means "let me edit this", so it lands after
			// the last word rather than doing nothing.  An empty field renders no blocks, so
			// without this there was no way into the editor by clicking at all.
			const offset = isNaN(line) ? value.length :
						   this._markdownCaretOffset(event, <HTMLElement>block, line, value);

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
		 * Tells Et2Ai (or anything else wanting a corner of the field) that there is a
		 * field-controls slot to put its control into, rather than overlaying its own.
		 */
		get hasFieldControls() : boolean
		{
			return true;
		}

		/**
		 * The strip over the top-right corner of the field: the view switcher, and the
		 * field-controls slot to its right.
		 *
		 * Hosts render this whether markdown is on or not - see the class docblock.
		 *
		 * @param markdownView show the view switcher, ie. the markdown shell is rendered too
		 */
		protected _fieldControlsTemplate(markdownView : boolean = this.markdown)
		{
			return html`
                <div class="field-controls" part="field-controls">
					${markdownView ? this._markdownToggleTemplate() : nothing}
                    <slot name="field-controls"></slot>
                </div>`;
		}

		/**
		 * The compact view switcher, one of the field controls.
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
		 * Attach a file to the entry, and link it here.
		 *
		 * A plain file input rather than et2-vfs-upload: the upload endpoint takes one multipart
		 * POST and answers with the URL, so there is nothing for a widget to manage - no queue,
		 * no file list, no per-file UI to keep out of the way inside a popup.
		 *
		 * mousedown does the snapshotting, before the file chooser takes the focus away, and the
		 * token it returns is what the completion handler finds its caret under.
		 */
		protected _markdownFileButtonsTemplate()
		{
			if(!this.canAttachFile)
			{
				return nothing;
			}

			let pending: object = null;

			return html`
                <div class="markdown-popup__separator"></div>
                <et2-button-icon
                        noSubmit
                        class="markdown-popup__attach"
                        image=${this._markdownUploads ? "loading" : "paperclip"}
                        statustext=${this._host.egw().lang("Attach a file")}
                        label=${this._host.egw().lang("Attach a file")}
                        @mousedown=${() => {pending = this._handleMarkdownFileStart();}}
                        @click=${(event: MouseEvent) =>
                        {
                            (<HTMLElement>event.currentTarget).parentElement
                                .querySelector<HTMLInputElement>(".markdown-popup__file").click();
                        }}
                ></et2-button-icon>
                <input
                        type="file" multiple hidden
                        class="markdown-popup__file"
                        @change=${(event: Event) =>
                        {
                            const input = <HTMLInputElement>event.target;
                            const chosen = input.files;
                            // let the same file be picked twice in a row
                            this._handleMarkdownFilesChosen(chosen, pending).then(() => input.value = "");
                            pending = null;
                        }}
                />`;
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
						${this._markdownFileButtonsTemplate()}
                    </div>
                </sl-popup>`;
		}

		/**
		 * Wrap the host's own editor in the preview pane and the popup.
		 *
		 * The view switcher is not part of it: it lives in the field controls, which the host
		 * renders beside this - see _fieldControlsTemplate().
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

			// paste/drop live on the source pane rather than the shell, so a drop on the preview
			// is not silently treated as a drop into the text behind it
			const panes = this.markdownMode === "split"
				? html`
                    <et2-split>
                        <div slot="start" class="markdown-shell__source"
                             @paste=${this._handleMarkdownPaste}
                             @dragover=${this._handleMarkdownDragOver}
                             @drop=${this._handleMarkdownDrop}
                        >${source}</div>
                        <div slot="end">${preview}</div>
                    </et2-split>`
				: html`
                    <div class="markdown-shell__source" ?hidden=${hideSource}
                         @paste=${this._handleMarkdownPaste}
                         @dragover=${this._handleMarkdownDragOver}
                         @drop=${this._handleMarkdownDrop}
                    >${source}</div>
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
                    <div class="markdown-shell__panes">${panes}</div>
					${this._markdownFormatPopupTemplate()}
                </div>`;
		}
	}

	return Et2MarkdownEdit;
});
