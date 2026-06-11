/**
 * EGroupware eTemplate2 - HTML area widget
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {css, html, LitElement, nothing, PropertyValues} from "lit";
import {ifDefined} from "lit/directives/if-defined.js";
import {classMap} from "lit/directives/class-map.js";
import {unsafeHTML} from "lit/directives/unsafe-html.js";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {query} from "lit/decorators/query.js";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {Et2VfsSelectDialog, type FileInfo} from "../Et2Vfs/Et2VfsSelectDialog";
import {loadWebComponent} from "../Et2Widget/Et2Widget";
import "tinymce";
import type {Editor as TinyMceEditor, RawEditorOptions, TinyMCE} from "tinymce";
import {
	BLOCK_FORMATS,
	editorContentStyle,
	htmlAreaFormats,
	type HtmlAreaMode,
	LANGUAGE_CODE as HTMLAREA_LANGUAGE_CODE,
	menubarFromPreference,
	NPM_PLUGIN_SET as HTMLAREA_NPM_PLUGIN_SET,
	normalizeFormatBlock,
	paragraphStyles,
	requestedToolbarSetting,
	toolbarForMode
} from "./Et2HtmlAreaConfig";
import "tinymce/icons/default";
import "tinymce/themes/silver";
import "tinymce/models/dom";
import "tinymce/skins/ui/oxide/skin.ts";
import "tinymce/skins/ui/oxide/content.ts";
import "tinymce/skins/content/default/content.ts";
import "tinymce/plugins/advlist";
import "tinymce/plugins/anchor";
import "tinymce/plugins/autolink";
import "tinymce/plugins/charmap";
import "tinymce/plugins/code";
import "tinymce/plugins/codesample";
import "tinymce/plugins/directionality";
import "tinymce/plugins/fullscreen";
import "tinymce/plugins/help";
import "tinymce/plugins/image";
import "tinymce/plugins/insertdatetime";
import "tinymce/plugins/link";
import "tinymce/plugins/lists";
import "tinymce/plugins/media";
import "tinymce/plugins/nonbreaking";
import "tinymce/plugins/pagebreak";
import "tinymce/plugins/searchreplace";
import "tinymce/plugins/table";
import "tinymce/plugins/visualblocks";
import "tinymce/plugins/visualchars";
import "tinymce/plugins/wordcount";
import "@tinymce/tinymce-webcomponent";
import "./Et2HtmlAreaReadonly";

type TinyMceConfig = RawEditorOptions;
type TinyMceUploadHandler = NonNullable<TinyMceConfig["images_upload_handler"]>;
type TinyMceFilePickerCallback = NonNullable<TinyMceConfig["file_picker_callback"]>;
type TinyMceConfigBridge = Record<string, TinyMceConfig>;
type TinyMceCallbackBridge = Record<string, {
	setup : NonNullable<TinyMceConfig["setup"]>;
	imagesUploadHandler? : TinyMceUploadHandler;
}>;
const TINYMCE_POPUP_SINK_STYLE_ID = "egw-et2-htmlarea-popup-sink-style";

window.tinymce.overrideDefaults({
	license_key: "gpl"
});

declare global
{
	interface Window
	{
		tinymce : TinyMCE;
		/**
		 * TinyMCE web-component integration bridge.
		 *
		 * The installed `@tinymce/tinymce-webcomponent` does not accept a config
		 * object property. Its `config` API is an HTML attribute containing a
		 * global object path string, which the component resolves at runtime.
		 *
		 * Each Et2HtmlArea instance publishes its current TinyMCE config here
		 * under a stable per-instance key and passes that key path to the
		 * `<tinymce-editor config="...">` attribute.
		 */
		egwEt2HtmlAreaConfigBridge? : TinyMceConfigBridge;
		egwEt2HtmlAreaCallbackBridge? : TinyMceCallbackBridge;
	}
}

type TinyMceEditorElement = HTMLElement & {
	value : string | null;
	readonly : boolean;
	disabled : boolean;
};

type TinyMceSetupHook = (editor : TinyMceEditor) => void;

/**
 * @summary Rich text editor widget built on TinyMCE's web component.
 *
 * `Et2HtmlArea` provides both rich text and plain text editing modes,
 * integrates with EGroupware upload and language preferences, and exposes a
 * small extension surface for editor-specific integrations such as custom
 * toolbar buttons.
 */
@customElement("et2-htmlarea")
export class Et2HtmlArea extends Et2InputWidget(LitElement)
{
	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					display: flex;
					flex-direction: column;
					width: 100%;
					min-height: 100px;
					min-width: 0;
				}

				.form-control {
					display: flex;
					align-items: stretch;
					flex-direction: column;
					flex-wrap: nowrap;
					flex: 1 1 auto;
					min-height: 0;
				}

				.form-control-input {
					flex: 1 1 auto;
					min-height: 0;
					min-width: 0;
				}

				.form-control__help-text {
					display: none;
					flex-basis: 2em;
				}

				.form-control--has-help-text .form-control__help-text {
					display: block;
				}

				tinymce-editor,
				textarea {
					display: block;
					flex: 1 1 auto;
					height: 100%;
					min-height: 0;
					width: 100%;
					box-sizing: border-box;
				}

				textarea {
					resize: vertical;
					font: inherit;
				}

				.htmlarea__readonly {
					flex: 1 1 auto;
					min-height: 0;
					min-width: 0;
					overflow-wrap: anywhere;
					white-space: pre-wrap;
				}
			`
		];
	}

	/**
	 * The value of the widget.
	 */
	@property({type: String})
	value = "";

	/**
	 * One of {ascii|simple|extended|advanced}.
	 *
	 * `simple`, `extended`, and `advanced` force built-in toolbar presets and do
	 * not use the `rte_toolbar` preference. Leave mode empty to use the user
	 * preference-driven toolbar.
	 */
	@property({type: String})
	mode : HtmlAreaMode | string = "";

	/**
	 * Height.
	 *
	 * Applied to the widget host by `Et2Widget`; the editor fills the host and
	 * does not mirror this into TinyMCE's own sizing config.
	 */
	@property({type: String})
	height = "";

	/**
	 * Width.
	 *
	 * Applied to the widget host by `Et2Widget`; the editor fills the host and
	 * does not mirror this into TinyMCE's own sizing config.
	 */
	@property({type: String})
	width = "";

	/**
	 * URL to upload dragged or pasted images, or the id of a link_to-style
	 * widget whose VFS path should receive the upload.
	 */
	@property({type: String, attribute: "image-upload"})
	imageUpload = "";

	/**
	 * Callback function to get called when file picker is clicked.
	 */
	@property({type: Function, attribute: false})
	filePickerCallback : TinyMceFilePickerCallback | null = null;

	/**
	 * Callback function for handling image upload.
	 */
	@property({type: Function, attribute: false})
	imagesUploadHandler : TinyMceUploadHandler | null = null;

	/**
	 * Disables the toolbar at the top of the editor.
	 */
	@property({type: Boolean, attribute: "no-toolbar"})
	noToolbar = false;

	/**
	 * Disables the menubar at the top of the editor.
	 */
	@property({type: Boolean, attribute: "no-menubar"})
	noMenubar = false;

	/**
	 * Disables the status bar on the bottom of the editor.
	 *
	 * The status bar is off by default. Set `no-statusbar="false"` in modern
	 * usage, or `statusbar="true"` in legacy templates, to enable it.
	 */
	@property({type: Boolean, attribute: "no-statusbar"})
	noStatusbar = true;

	/**
	 * Enables to control what child tag is allowed or not allowed of the present tag. For instance: +body[style], makes style tag allowed inside body.
	 */
	@property({type: String, attribute: "valid-children"})
	validChildren = "+body[style]";

	/**
	 * Controls how toolbar items are shown when space is limited.
	 */
	@property({type: String, attribute: "toolbar-mode"})
	toolbarMode : "floating" | "sliding" | "scrolling" | "wrap" = "sliding";

	/**
	 * Force default font and size as style attribute into the markup. Also ensures all non-block elements are wrapped in p.
	 * Use this when users really want the font & size (email).
	 */
	@property({type: Boolean, attribute: "apply-default-font"})
	applyDefaultFont = false;

	/**
	 * Placeholder text shown when the editor is empty.
	 */
	@property({type: String})
	placeholder = "";

	@query("tinymce-editor")
	private _editorElement : TinyMceEditorElement;

	@query("textarea")
	private _textareaElement : HTMLTextAreaElement;

	/**
	 * Compatibility bridge: legacy integrations still expect a `tinymce` promise
	 * resolving like `tinymce.init()`.
	 *
	 * @deprecated New integrations should use the component instance directly.
	 */
	tinymce : Promise<TinyMceEditor[]>;

	/**
	 * Compatibility bridge: legacy callers expect direct access to the editor instance.
	 *
	 * @deprecated New integrations should add explicit component APIs instead.
	 */
	get editor() : TinyMceEditor | null
	{
		return this._tinyMceEditor;
	}

	set editor(value : TinyMceEditor | null)
	{
		this._tinyMceEditor = value;
	}

	/**
	 * Compatibility bridge: TinyMCE callback surface retained for migration.
	 *
	 * @deprecated Replace with explicit component APIs where possible.
	 */
	get file_picker_callback() : TinyMceFilePickerCallback | null
	{
		return this.filePickerCallback;
	}

	set file_picker_callback(value : TinyMceFilePickerCallback | null)
	{
		this.filePickerCallback = value;
	}

	/**
	 * Compatibility bridge: legacy TinyMCE callback surface retained for migration.
	 *
	 * @deprecated Replace with explicit component APIs where possible.
	 */
	get images_upload_handler() : TinyMceUploadHandler | null
	{
		return this.imagesUploadHandler;
	}

	set images_upload_handler(value : TinyMceUploadHandler | null)
	{
		this.imagesUploadHandler = value;
	}

	/**
	 * Compatibility bridge for legacy snake_case property naming.
	 *
	 * @deprecated Use validChildren.
	 */
	get valid_children() : string
	{
		return this.validChildren;
	}

	set valid_children(value : string)
	{
		this.validChildren = value;
	}

	/**
	 * Compatibility bridge for legacy snake_case property naming.
	 *
	 * @deprecated Use toolbarMode.
	 */
	get toolbar_mode() : string
	{
		return this.toolbarMode;
	}

	set toolbar_mode(value : string)
	{
		if(["floating", "sliding", "scrolling", "wrap"].includes(value))
		{
			this.toolbarMode = value as typeof this.toolbarMode;
		}
	}

	private _tinyMceEditor : TinyMceEditor | null = null;
	private _resolveTinymce : ((editor : TinyMceEditor[]) => void) | null = null;
	private _tinymceResolved = false;
	private _syncingFromEditor = false;
	private _pendingBlurTimeout : number | null = null;
	private _editorSetupHooks = new Map<string, TinyMceSetupHook>();
	private _toolbarItems = new Set<string>();

	constructor()
	{
		super();
		this.tinymce = new Promise(resolve =>
		{
			this._resolveTinymce = resolve;
		});
		this._handleAsciiInput = this._handleAsciiInput.bind(this);
		this._handleAsciiChange = this._handleAsciiChange.bind(this);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		if(this._pendingBlurTimeout !== null)
		{
			window.clearTimeout(this._pendingBlurTimeout);
			this._pendingBlurTimeout = null;
		}
		this._removePublishedConfig();
		this._removePublishedCallbacks();
		this._tinyMceEditor = null;
	}

	updated(changedProperties : PropertyValues)
	{
		super.updated(changedProperties);

		if(changedProperties.has("value") && !this._syncingFromEditor)
		{
			this._syncValueToEditor();
		}
		if(changedProperties.has("readonly") && this.readonly)
		{
			this._removePublishedConfig();
			this._removePublishedCallbacks();
			this._tinyMceEditor = null;
		}
		else if(changedProperties.has("readonly") && this._tinyMceEditor?.mode)
		{
			this._tinyMceEditor.mode.set(this.readonly ? "readonly" : "design");
		}
		if(changedProperties.has("disabled") && this._tinyMceEditor?.options?.isRegistered?.("disabled"))
		{
			this._tinyMceEditor.options.set("disabled", this.disabled);
		}
	}

	et2HandleBlur(event : FocusEvent)
	{
		// TinyMCE can move focus between iframe, menus, and editor chrome while
		// focus still logically belongs to the widget. Ignore those transient blur
		// events and only validate on a real leave.
		if(this._hasInternalFocus())
		{
			return;
		}

		super.et2HandleBlur(event);
	}

	set_value(value)
	{
		super.set_value(value ?? "");
		this._syncValueToEditor();
	}

	/**
	 * Serialize the widget value, optionally normalizing rich-text content for submit.
	 *
	 * Mail clients often strip editor-level CSS, so users expect their preferred
	 * font and size to survive in the markup itself. This is not ideal, but it is
	 * easier to do here before submit than to do it server-side.
	 */
	getValue(submit_value? : boolean)
	{
		if(this.readonly || this.disabled)
		{
			return super.getValue(submit_value);
		}
		if(!this._isAsciiMode && submit_value && this.applyDefaultFont)
		{
			this._applyDefaultFontToContent();
			const nextValue = this._tinyMceEditor?.getContent?.() ?? this._editorElement?.value ?? this.value ?? "";
			this.value = nextValue;
			return nextValue;
		}

		return super.getValue(submit_value);
	}

	getInputNode() : HTMLInputElement
	{
		return (this._isAsciiMode ? this._textareaElement : this._editorElement) as unknown as HTMLInputElement;
	}

	async focus()
	{
		await super.focus();

		if(this._isAsciiMode)
		{
			this._textareaElement?.focus();
			return;
		}
		if(this._tinyMceEditor?.focus)
		{
			this._tinyMceEditor.focus();
			return;
		}
		(this.getInputNode() as HTMLElement)?.focus?.();
	}

	/**
	 * Append a toolbar item to the computed toolbar for integrations that extend
	 * the editor UI before TinyMCE initializes.
	 */
	addToolbarItem(item : string)
	{
		if(!item || this._toolbarItems.has(item))
		{
			return;
		}
		this._toolbarItems.add(item);
		this.requestUpdate();
	}

	/**
	 * Register a TinyMCE setup hook for integrations that need to add custom UI
	 * such as toolbar buttons before the editor finishes initializing.
	 */
	registerEditorSetupHook(key : string, hook : TinyMceSetupHook)
	{
		this._editorSetupHooks.set(key, hook);
	}

	/**
	 * Remove a previously registered TinyMCE setup hook.
	 */
	unregisterEditorSetupHook(key : string)
	{
		this._editorSetupHooks.delete(key);
	}

	protected get _editorId() : string
	{
		return `${this.dom_id || this.id || "et2-htmlarea"}_editor`;
	}

	protected get _isAsciiMode() : boolean
	{
		return this._normalizedMode === "ascii";
	}

	protected get _normalizedMode() : HtmlAreaMode
	{
		switch(this.mode)
		{
			case "":
			case "ascii":
			case "extended":
			case "advanced":
			case "simple":
				return this.mode;
			default:
				return "";
		}
	}

	protected get _contentCss() : string
	{
		const api = this.egw();
		const darkmode = document.documentElement?.dataset?.darkmode ?? "0";
		const prefs = btoa([
			api.preference("rte_font", "common"),
			api.preference("rte_font_size", "common"),
			api.preference("rte_font_unit", "common")
		].join("::"));

		return `${api.webserverUrl}/api/tinymce.php?darkmode=${darkmode}&${prefs}`;
	}

	protected get _languageCode() : string
	{
		const language = String(this.egw().preference("lang", "common") || "en").toLowerCase();
		return HTMLAREA_LANGUAGE_CODE[language] || "en";
	}

	protected get _defaultFormatBlock() : string
	{
		return normalizeFormatBlock(this.egw().preference("rte_formatblock", "common"));
	}

	protected get _toolbar() : string | false
	{
		return toolbarForMode(this._normalizedMode, this._requestedToolbarSetting, this.noToolbar, [...this._toolbarItems]);
	}

	/**
	 * Toolbar selection comes from the user preference when mode is not fixed.
	 * The modern component API only supports explicit disable via `noToolbar`.
	 */
	protected get _requestedToolbarSetting() : string
	{
		return requestedToolbarSetting(this.egw().preference("rte_toolbar", "common"), this.noToolbar);
	}

	/**
	 * Menubar visibility is still subject to the legacy user preference.
	 *
	 * The TinyMCE web component parses `menubar` as `false | string`, not a
	 * boolean. When enabled, provide the default menu list explicitly instead of
	 * the string `"true"`, which TinyMCE interprets as a menu named `true`.
	 */
	protected get _menubar() : string | false
	{
		return menubarFromPreference(this.egw().preference("rte_menubar", "common"), this.noMenubar);
	}

	/**
	 * TinyMCE's web component renders the popup sink inside its own shadow root.
	 * In split mode that sink still participates in tab-panel scrolling unless
	 * we force it out of layout flow.
	 */
	protected _ensurePopupSinkStyles()
	{
		const root = this._editorElement?.shadowRoot;
		if(!root || root.getElementById(TINYMCE_POPUP_SINK_STYLE_ID))
		{
			return;
		}

		const style = document.createElement("style");
		style.id = TINYMCE_POPUP_SINK_STYLE_ID;
		style.textContent = `
			.tox.tox-silver-popup-sink.tox-tinymce-aux {
				position: fixed !important;
				inset: 0 !important;
				width: 0 !important;
				height: 0 !important;
				overflow: visible !important;
			}

			/* Prevent too-small menus */
			.tox.tox-silver-popup-sink.tox-tinymce-aux .tox-menu {
				min-width: min(16rem, calc(100vw - var(--sl-spacing-large))) !important;
			}

			.tox.tox-silver-popup-sink.tox-tinymce-aux .tox-listboxfield {
				min-width: min(10rem, calc(100vw - var(--sl-spacing-large))) !important;
			}

			.tox.tox-silver-popup-sink.tox-tinymce-aux .tox-collection__item-label,
			.tox.tox-silver-popup-sink.tox-tinymce-aux .tox-menu__label > * {
				word-break: normal !important;
				overflow-wrap: anywhere;
			}
		`;
		root.append(style);
	}

	/**
	 * Publish the current editor config through the global bridge required by
	 * TinyMCE's web component.
	 *
	 * Why this exists:
	 * The installed `@tinymce/tinymce-webcomponent` reads `config` from an HTML
	 * attribute and interprets its value as a global object path. It does not
	 * expose a property-based config API we can bind directly from Lit.
	 *
	 * Lifecycle:
	 * - on every render, we overwrite this instance's entry with the latest
	 *   computed config
	 * - the returned string is passed to `<tinymce-editor config="...">`
	 * - on disconnect, `_removePublishedConfig()` removes the stale entry
	 *
	 * Scope:
	 * The bridge is intentionally isolated to this one helper so the rest of the
	 * component can continue to work with normal local config objects.
	 */
	protected _publishConfig() : string
	{
		window.egwEt2HtmlAreaConfigBridge ??= {};
		window.egwEt2HtmlAreaConfigBridge[this._editorId] = this._getTinyMceConfig();
		return `egwEt2HtmlAreaConfigBridge.${this._editorId}`;
	}

	/**
	 * Remove this instance's published config when the component disconnects.
	 *
	 * TinyMCE resolves the config lazily from the global path string, so stale
	 * entries would otherwise accumulate across component lifecycles.
	 */
	protected _removePublishedConfig()
	{
		if(window.egwEt2HtmlAreaConfigBridge)
		{
			delete window.egwEt2HtmlAreaConfigBridge[this._editorId];

			if(Object.keys(window.egwEt2HtmlAreaConfigBridge).length === 0)
			{
				delete window.egwEt2HtmlAreaConfigBridge;
			}
		}
	}

	/**
	 * Publish callback paths for TinyMCE web-component options that are
	 * supported as global-path attributes, such as `setup` and
	 * `images_upload_handler`.
	 */
	protected _publishCallbacks() : string
	{
		window.egwEt2HtmlAreaCallbackBridge ??= {};
		window.egwEt2HtmlAreaCallbackBridge[this._editorId] = {
			setup: (editor) => this._handleTinyMceSetup(editor),
			...(this.imagesUploadHandler ? {imagesUploadHandler: this.imagesUploadHandler} : {})
		};
		return `egwEt2HtmlAreaCallbackBridge.${this._editorId}`;
	}

	/**
	 * Remove this instance's published callback bridge on disconnect.
	 */
	protected _removePublishedCallbacks()
	{
		if(window.egwEt2HtmlAreaCallbackBridge)
		{
			delete window.egwEt2HtmlAreaCallbackBridge[this._editorId];

			if(Object.keys(window.egwEt2HtmlAreaCallbackBridge).length === 0)
			{
				delete window.egwEt2HtmlAreaCallbackBridge;
			}
		}
	}

	/**
	 * Wrap loose text and inline nodes in paragraphs so default block styling can
	 * be written into the saved markup.
	 */
	protected _wrapTextNodes() : boolean
	{
		const body = this._tinyMceEditor?.getBody?.() ?? null;
		if(!body)
		{
			return false;
		}

		let toWrap : ChildNode[] = [];
		body.childNodes.forEach(node =>
		{
			const computedDisplay = node.nodeType === Node.ELEMENT_NODE ?
			                        window.getComputedStyle(node as Element).display :
			                        "";
			const textOrNonBlockNode = node.nodeType === Node.TEXT_NODE ||
				node.nodeType === Node.ELEMENT_NODE && computedDisplay !== "block";

			if(textOrNonBlockNode)
			{
				toWrap.push(node);
			}
			if((!textOrNonBlockNode || node === body.lastChild) && toWrap.length)
			{
				const wrap = body.ownerDocument.createElement("p");
				toWrap.forEach(currentNode =>
				{
					wrap.appendChild(currentNode === toWrap[0] ?
					                 body.replaceChild(wrap, currentNode) :
					                 body.removeChild(currentNode));
				});
				toWrap = [];
			}
		});

		const firstChild = body.firstChild;
		if(firstChild instanceof HTMLParagraphElement &&
			firstChild.firstChild !== firstChild.lastChild &&
			firstChild.firstChild?.nodeName === "BR")
		{
			firstChild.removeChild(firstChild.firstChild);
		}

		return true;
	}

	/**
	 * Inline the preferred font and size on saved content for mail workflows.
	 */
	protected _applyDefaultFontToContent() : boolean
	{
		const editArea = this._tinyMceEditor?.getDoc?.() ?? null;
		if(!editArea)
		{
			return false;
		}

		this._wrapTextNodes();

		const api = this.egw();
		const styles = paragraphStyles(api.preference.bind(api));
		editArea.querySelectorAll(
			'h1:not([style*="font-family"]),h2:not([style*="font-family"]),h3:not([style*="font-family"]),' +
			'h4:not([style*="font-family"]),h5:not([style*="font-family"]),h6:not([style*="font-family"]),' +
			'div:not([style*="font-family"]),li:not([style*="font-family"]),p:not([style*="font-family"]),' +
			'blockquote:not([style*="font-family"]),td:not([style*="font-family"]),th:not([style*="font-family"])'
		).forEach(elem =>
		{
			(elem as HTMLElement).style.fontFamily = styles["font-family"];
		});

		editArea.querySelectorAll(
			'div:not([style*="font-size"]),li:not([style*="font-size"]),p:not([style*="font-size"]),' +
			'blockquote:not([style*="font-size"]),td:not([style*="font-size"]),th:not([style*="font-size"])'
		).forEach(elem =>
		{
			(elem as HTMLElement).style.fontSize = styles["font-size"];
		});

		return true;
	}

	/**
	 * Build the TinyMCE config from the component's current state.
	 *
	 * Keep this bridge limited to TinyMCE options that the web component cannot
	 * reliably express as simple HTML attributes. Attribute-supported options are
	 * rendered directly in `_renderEditor()` so there is a single source of truth
	 * for the visible editor chrome.
	 */
	protected _getTinyMceConfig() : TinyMceConfig
	{
		const api = this.egw();
		const config : TinyMceConfig = {
			base_url: `${api.webserverUrl}/node_modules/tinymce`,
			body_id: `${this.dom_id}_htmlarea`,
			browser_spellcheck: true,
			block_formats: BLOCK_FORMATS,
			content_style: editorContentStyle(api.preference.bind(api)),
			convert_urls: false,
			// setting p (and below also the preferred formatblock) to the user's font and -size preference
			formats: htmlAreaFormats(api.preference.bind(api)),
			license_key: "gpl",
			language: this._languageCode,
			language_url: this._languageCode === "en" ? undefined :
			              `${api.webserverUrl}/api/js/tinymce/langs/${this._languageCode}.js`,
			noneditable_class: "mceNonEditable",
			paste_data_images: true,
			contextmenu: false,
			image_advtab: true,
			// TinyMCE's split UI mode hoists menus/popups out of scroll-clipped
			// containers such as tab panels, preventing the editor chrome from
			// forcing parent containers to scroll while menus are open.
			ui_mode: "split",
			toolbar_mode: this.toolbarMode,
			file_picker_callback: (callback, value, meta) => this._handleFilePickerCallback(callback, value, meta),
			file_picker_types: "image media file",
			valid_children: this.validChildren
		};

		return config;
	}

	/**
	 * Resolve the TinyMCE image upload URL.
	 *
	 * When `imageUpload` points at a `link_to`-style widget or content-path
	 * value, the upload endpoint needs the current request id and widget id so
	 * the server can store the file in the right VFS location.
	 *
	 * Without `imageUpload`, use the plain endpoint so TinyMCE can still upload dropped or pasted images.
	 */
	protected _getImageUploadUrl() : string
	{
		const api = this.egw();
		const base = api.ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_htmlarea_upload");
		const requestId = this.getInstanceManager()?.etemplate_exec_id;

		if(this.imageUpload && this.imageUpload[0] !== "/" && !this.imageUpload.startsWith("http"))
		{
			return `${base}&request_id=${requestId}&widget_id=${this.imageUpload}&type=htmlarea`;
		}
		if(this.imageUpload)
		{
			return this.imageUpload;
		}
		return `${base}&type=htmlarea`;
	}

	/**
	 * Route TinyMCE's file picker through a custom override when provided, or
	 * through the default EGroupware VFS picker otherwise.
	 */
	protected _handleFilePickerCallback(
		callback : Parameters<TinyMceFilePickerCallback>[0],
		value : Parameters<TinyMceFilePickerCallback>[1],
		meta : Parameters<TinyMceFilePickerCallback>[2]
	)
	{
		if(this.filePickerCallback)
		{
			this.filePickerCallback.call(this, callback, value, meta);
			return;
		}

		void this._openDefaultFilePicker(callback, meta);
	}

	/**
	 * Open EGroupware's VFS picker for TinyMCE's image, media, and generic file
	 * dialogs. This restores the legacy browse workflow while keeping the
	 * callback override available as an explicit escape hatch.
	 */
	protected async _openDefaultFilePicker(
		callback : Parameters<TinyMceFilePickerCallback>[0],
		meta : Parameters<TinyMceFilePickerCallback>[2]
	)
	{
		const isImage = meta.filetype === "image";
		const isMedia = meta.filetype === "media";
		const dialog = loadWebComponent("et2-vfs-select-dialog", {
			id: `${this._editorId}_picker`,
			mode: "open",
			multiple: false,
			buttonLabel: this.egw().lang("Select"),
			title: isImage ? this.egw().lang("Select image") :
			       isMedia ? this.egw().lang("Select media") :
			       this.egw().lang("Select file"),
			mime: isImage ? "image/" : isMedia ? /^(audio|video)\//i : "",
			open: true
		}, this) as unknown as Et2VfsSelectDialog;

		this._raiseDialogAboveTinyMce(dialog);
		document.body.append(dialog as unknown as Node);
		await dialog.show();

		try
		{
			const [button, selection] = await dialog.getComplete();
			const path = Array.isArray(selection) ? selection[0] : selection;
			if(!button || !path)
			{
				return;
			}

			const file = dialog.fileInfo(path) as FileInfo | undefined;
			const url = file?.downloadUrl ? `${this.egw().webserverUrl}${file.downloadUrl}` : "";
			if(!url)
			{
				return;
			}

			callback(url, this._filePickerMeta(file, meta));
		}
		finally
		{
			if(dialog.isConnected)
			{
				dialog.remove();
			}
		}
	}

	/**
	 * TinyMCE dialogs stack above Shoelace's default dialog z-index. Raise only
	 * this transient VFS picker instance so it can sit above the active
	 * Insert/edit image/media/file dialog without changing global dialog layering.
	 */
	protected _raiseDialogAboveTinyMce(dialog : Et2VfsSelectDialog)
	{
		dialog.style.setProperty("--sl-z-index-dialog", "1300");
	}

	/**
	 * Provide TinyMCE with the metadata it expects for the selected VFS file.
	 */
	protected _filePickerMeta(file : FileInfo | undefined, meta : Parameters<TinyMceFilePickerCallback>[2]) : Record<string, string>
	{
		if(!file)
		{
			return {};
		}

		const label = file.label;

		if(meta.filetype === "image")
		{
			return {
				alt: label,
				title: label
			};
		}
		if(meta.filetype === "file")
		{
			return {
				text: label,
				title: label
			};
		}
		return {
			title: label
		};
	}

	/**
	 * Attach TinyMCE event handlers and bridge them onto the host widget API.
	 */
	protected _handleTinyMceSetup(editor : TinyMceEditor)
	{
		this._tinyMceEditor = editor;
		for(const hook of this._editorSetupHooks.values())
		{
			hook(editor);
		}
		editor.on("init", () =>
		{
			this._ensurePopupSinkStyles();
			this._syncValueToEditor();
			this._applyDefaultFormatBlock(editor);
			if(!this._tinymceResolved)
			{
				this._resolveTinymce?.([editor]);
				this._tinymceResolved = true;
			}
		});
		editor.on("input", () => this._syncValueFromEditor(false));
		editor.on("change undo redo", () => this._syncValueFromEditor(true));
		editor.on("focus", () =>
		{
			if(this._pendingBlurTimeout !== null)
			{
				window.clearTimeout(this._pendingBlurTimeout);
				this._pendingBlurTimeout = null;
			}
			this.dispatchEvent(new FocusEvent("focus", {bubbles: true, composed: true}));
		});
		editor.on("blur", () => this._queueHostBlur());
	}

	protected _applyDefaultFormatBlock(editor : TinyMceEditor)
	{
		editor.formatter.apply(this._defaultFormatBlock);
		editor.nodeChanged();
	}

	/**
	 * Delay host blur long enough to ignore TinyMCE's internal focus transitions.
	 */
	protected _queueHostBlur()
	{
		if(this._pendingBlurTimeout !== null)
		{
			window.clearTimeout(this._pendingBlurTimeout);
		}
		this._pendingBlurTimeout = window.setTimeout(() =>
		{
			this._pendingBlurTimeout = null;

			// TinyMCE focus can move through iframe/chrome/menu internals. Only
			// propagate blur once focus actually leaves the widget.
			if(this._hasInternalFocus())
			{
				return;
			}

			this.dispatchEvent(new FocusEvent("blur", {bubbles: true, composed: true}));
		}, 0);
	}

	/**
	 * Check whether focus is still logically inside the widget, including
	 * TinyMCE's iframe, menus, and shadow-host transitions.
	 */
	protected _hasInternalFocus() : boolean
	{
		const activeElement = document.activeElement as HTMLElement | null;

		if(this._isAsciiMode)
		{
			return activeElement === this._textareaElement || activeElement === this || this.contains(activeElement);
		}

		if(this._tinyMceEditor?.hasFocus?.())
		{
			return true;
		}
		if(activeElement === this || activeElement === this._editorElement)
		{
			return true;
		}
		if(this._tinyMceEditor?.iframeElement === activeElement)
		{
			return true;
		}
		if(activeElement && this._tinyMceEditor?.editorContainer?.contains(activeElement))
		{
			return true;
		}

		let current : Node | null = activeElement;
		while(current)
		{
			if(current === this || current === this._editorElement)
			{
				return true;
			}

			const root = (current as HTMLElement).getRootNode?.();
			if(root instanceof ShadowRoot)
			{
				current = root.host;
				continue;
			}
			break;
		}

		return false;
	}

	/**
	 * Mirror editor content into the widget value and emit host input/change events.
	 */
	protected _syncValueFromEditor(dispatchChange : boolean)
	{
		if(!this._tinyMceEditor?.getContent)
		{
			return;
		}
		const nextValue = this._tinyMceEditor.getContent() ?? "";
		if(nextValue === this.value)
		{
			return;
		}

		this._syncingFromEditor = true;
		const oldValue = this.value;
		this.value = nextValue;
		this.requestUpdate("value", oldValue);
		this._syncingFromEditor = false;

		this.dispatchEvent(new Event("input", {bubbles: true, composed: true}));
		if(dispatchChange)
		{
			this.dispatchEvent(new Event("change", {bubbles: true, composed: true}));
		}
	}

	/**
	 * Push the current widget value into the active editor implementation.
	 */
	protected _syncValueToEditor()
	{
		if(this._isAsciiMode)
		{
			if(this._textareaElement && this._textareaElement.value !== (this.value ?? ""))
			{
				this._textareaElement.value = this.value ?? "";
			}
			return;
		}
		if(this._tinyMceEditor?.setContent)
		{
			const nextValue = this.value ?? "";
			if(this._tinyMceEditor.getContent() !== nextValue)
			{
				this._tinyMceEditor.setContent(nextValue);
			}
		}
		else if(this._editorElement)
		{
			this._editorElement.value = this.value ?? "";
		}
	}

	/**
	 * Keep textarea mode aligned with the widget value contract.
	 */
	protected _handleAsciiInput(event : Event)
	{
		const target = event.target as HTMLTextAreaElement;
		const oldValue = this.value;
		this.value = target.value;
		this.requestUpdate("value", oldValue);
		this.dispatchEvent(new Event("input", {bubbles: true, composed: true}));
	}

	/**
	 * Re-emit textarea change events through the widget host.
	 */
	protected _handleAsciiChange()
	{
		this.dispatchEvent(new Event("change", {bubbles: true, composed: true}));
	}

	/**
	 * Render the active editor implementation for the current mode.
	 */
	protected _renderEditor()
	{
		if(this.readonly)
		{
			const value = this.value ?? "";

			return html`
                <div part="readonly-content" class="htmlarea__readonly">
                    ${this._isAsciiMode ? html`${value}` : unsafeHTML(value)}
                </div>
			`;
		}

		if(this._isAsciiMode)
		{
			return html`
                <textarea
                        id=${this._editorId}
                        .value=${this.value ?? ""}
                        placeholder=${ifDefined(this.placeholder || undefined)}
                        ?disabled=${this.disabled}
                        ?readonly=${this.readonly}
                        @input=${this._handleAsciiInput}
                        @change=${this._handleAsciiChange}
                ></textarea>
			`;
		}

		const configPath = this._publishConfig();
		const callbackPath = this._publishCallbacks();
		return html`
            <tinymce-editor
                    width="100%" height="100%"
                    id=${this._editorId}
                    config="${configPath}"
                    setup="${callbackPath}.setup"
                    toolbar="${this._toolbar === false ? "false" : this._toolbar}"
                    menubar="${this._menubar === false ? "false" : this._menubar}"
                    statusbar="${String(!this.noStatusbar)}"
                    plugins="${HTMLAREA_NPM_PLUGIN_SET}"
                    content_css="${this._contentCss}"
                    promotion="false"
                    resize="false"
                    images_upload_handler=${ifDefined(this.imagesUploadHandler ? `${callbackPath}.imagesUploadHandler` : undefined)}
                    images_upload_url=${ifDefined(!this.imagesUploadHandler ? this._getImageUploadUrl() : undefined)}
                    placeholder=${ifDefined(this.placeholder || undefined)}
                    ?disabled=${this.disabled}
                    ?readonly=${this.readonly}
            >${this.value ?? ""}
            </tinymce-editor>
		`;
	}

	render()
	{
		const labelTemplate = this._labelTemplate();
		const helpTextTemplate = this._helpTextTemplate();

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        "form-control": true,
                        "form-control--medium": true,
                        "form-control--has-label": labelTemplate !== nothing,
                        "form-control--has-help-text": helpTextTemplate !== nothing
                    })}
            >
                ${labelTemplate}
                <div part="form-control-input" class="form-control-input">
                    ${this._renderEditor()}
                </div>
                ${helpTextTemplate}
            </div>
		`;
	}
}
