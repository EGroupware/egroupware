/**
 * EGroupware eTemplate2 - File selection WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {html, LitElement, nothing, TemplateResult} from "lit";
import shoelace from "../Styles/shoelace";
import styles from "./Et2VfsSelect.styles";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {ifDefined} from "lit/directives/if-defined.js";
import {repeat} from "lit/directives/repeat.js";
import {until} from "lit/directives/until.js";
import {SearchMixinInterface} from "../Et2Select/SearchMixin";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import {DialogButton, Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {HasSlotController} from "../Et2Widget/slot";
import {IegwAppLocal} from "../../jsapi/egw_global";
import {Et2Select} from "../Et2Select/Et2Select";

/**
 * @summary Select files (including directories) from the VFS
 * @since 23.1
 *
 * @dependency et2-dialog
 * @dependency et2-select
 *
 * @slot title - Optional additions to title.  Works best with `et2-button-icon`.
 * @slot toolbar - Toolbar containing controls for search & navigation
 * @slot prefix - Before the toolbar
 * @slot suffix - Like prefix, but after
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the `help-text` attribute.
 * @slot footer - Customise the dialog footer
 *
 * @event change - Emitted when the control's value changes.
 *
 * @csspart form-control-input - The textbox's wrapper.
 * @csspart form-control-help-text - The help text's wrapper.
 * @csspart prefix - The container that wraps the prefix slot.
 * @csspart suffix - The container that wraps the suffix slot.
 *
 */

export class Et2VfsSelect extends Et2InputWidget(LitElement) implements SearchMixinInterface
{
	static get styles()
	{
		return [
			shoelace,
			...super.styles,
			styles
		];
	}

	/** Currently selected files */
	@property() value : string[] = [];

	/**
	 * The dialog’s label as displayed in the header.
	 * You should always include a relevant label, as it is required for proper accessibility.
	 */
	@property() title : string = "Select";

	/**
	 * Dialog mode
	 * Quickly sets button label, multiple, selection and for "select-dir", mime-type
	 **/
	@property() mode : "open" | "open-multiple" | "saveas" | "select-dir" = "open";

	/** Button label */
	@property() buttonLabel : string = "Select";

	/** Provide a suggested filename for saving */
	@property() filename : string = "";

	/** Allow selecting multiple files */
	@property({type: Boolean}) multiple = false;

	/** Start path in VFS.  Leave unset to use the last used path. */
	@property() path : string = "";

	/** Limit display to the given mime-type */
	@property() mime : string | string[] | RegExp = "";

	/** List of mimetypes to allow user to filter.  */
	@property() mimeList : SelectOption[] = [];

	/** The select's help text. If you need to display HTML, use the `help-text` slot instead. */
	@property({attribute: 'help-text'}) helpText = '';

	@state() searching = false;
	@state() open : boolean = false;
	@state() currentFile;

	// SearchMixinInterface //
	@property() searchUrl : string = "EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelectFiles";

	/** Additional options passed to server search */
	@property({type: Object}) searchOptions : object = {};

	search : boolean = true;
	allowFreeEntries : boolean = false;

	// End SearchMixinInterface //
	protected _searchTimeout : number;
	protected _searchPromise : Promise<FileInfo[]> = Promise.resolve([]);
	private static SEARCH_TIMEOUT : number = 500;

	// Still need some server-side info
	protected _serverContent : Promise<any> = Promise.resolve({});
	private static SERVER_URL = "EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelectContent";

	protected readonly hasSlotController = new HasSlotController(this, 'help-text', 'toolbar', 'footer');

	protected _fileList = [];

	// Internal accessors
	get _dialog() : Et2Dialog { return this.shadowRoot.querySelector("et2-dialog");}

	get _filenameNode() : HTMLInputElement { return this.shadowRoot.querySelector("#filename");}

	get _fileNodes() : HTMLElement[] { return Array.from(this.shadowRoot.querySelectorAll(".vfs_select__file"));}

	get _searchNode() : HTMLInputElement { return this.shadowRoot.querySelector("#search");}

	get _pathNode() : HTMLElement { return this.shadowRoot.querySelector("#path");}

	get _listNode() : HTMLElement { return this.shadowRoot.querySelector("#listbox");}

	get _mimeNode() : Et2Select { return this.shadowRoot.querySelector("#mimeFilter");}

	/*
	* List of properties that get translated
	* Done separately to not interfere with properties - if we re-define label property,
	* labels go missing.
	*/
	static get translate()
	{
		return {
			...super.translate,
			title: true,
			buttonLabel: true
		}
	}

	constructor(parent_egw? : string | IegwAppLocal)
	{
		super();

		if(parent_egw)
		{
			this._setApiInstance(parent_egw);
		}
		// Use filemanager translations
		this.egw().langRequireApp(this.egw().window, "filemanager", () => {this.requestUpdate()});

		this.handleButtonClick = this.handleButtonClick.bind(this);
		this.handleCreateDirectory = this.handleCreateDirectory.bind(this);
		this.handleSearchKeyDown = this.handleSearchKeyDown.bind(this);
	}

	transformAttributes(attr)
	{
		super.transformAttributes(attr);

		// Start request to get server-side info
		let content = {};
		let attrs = {
			mode: this.mode,
			label: this.buttonLabel,
			path: this.path || null,
			mime: this.mime || null,
			name: this.title
		};
		return this.egw().request(this.egw().link(this.egw().ajaxUrl(this.egw().decodePath(Et2VfsSelect.SERVER_URL))),
			[content, attrs]).then((results) =>
		{
			debugger;

		});
	}

	connectedCallback()
	{
		super.connectedCallback();

		if(this.path == "")
		{
			this.path = "~";
		}
		// Get file list
		this.startSearch();
	}

	async getUpdateComplete()
	{
		const result = await super.getUpdateComplete();

		// Need to wait for server content
		await this._serverContent;

		return result;
	}

	public setPath(path)
	{
		if(path == '..')
		{
			path = this.dirname(this.path);
		}
		const oldValue = this.path;
		this._pathNode.value = this.path = path;
		this.requestUpdate("path", oldValue);

		return this._searchPromise;
	}

	/**
	 * Get directory of a path
	 *
	 * @param {string} _path
	 * @returns string
	 */
	public dirname(_path)
	{
		let parts = _path.split('/');
		parts.pop();
		return parts.join('/') || '/';
	}

	/**
	 * Shows the dialog.
	 */
	public show()
	{
		this.open = true;
		if(this.path && this._fileList.length == 0)
		{
			this.startSearch();
		}
		return Promise.all([
			this._searchPromise,
			this._dialog.show()
		]);
	}

	/**
	 * Hides the dialog.
	 */
	public hide()
	{
		this.open = false;
		return this._dialog.hide();
	}

	startSearch() : Promise<void>
	{
		// Stop timeout timer
		clearTimeout(this._searchTimeout);

		this.searching = true;
		this.requestUpdate("searching");

		// Start the searches
		this._searchPromise = this.remoteSearch(this._searchNode?.value ?? "", this.searchOptions);
		return this._searchPromise.then(async() =>
		{
			this.searching = false;
			this.requestUpdate("searching", true);
		});
	}

	localSearch(search : string, options : object) : Promise<FileInfo[]>
	{
		// No local search
		return Promise.resolve([]);
	}

	remoteSearch(search : string, options : object) : Promise<FileInfo[]>
	{
		// Include a limit, even if options don't, to avoid massive lists breaking the UI
		let sendOptions = {
			path: this._pathNode?.value ?? this.path,
			mime: this._mimeNode?.value ?? this.mime,
			num_rows: 100,
			...options
		}
		return this.egw().request(this.egw().link(this.egw().ajaxUrl(this.egw().decodePath(this.searchUrl))), [search, sendOptions]).then((results) =>
		{
			return this.processRemoteResults(results);
		});
	}

	processRemoteResults(results) : FileInfo[]
	{
		if(results.message)
		{
			this.helpText = results.message;
		}
		this._fileList = results.files ?? [];

		return this._fileList;
	}

	searchMatch(search : string, options : object, item : LitElement) : boolean
	{
		// No local matching
		return false;
	}

	/**
	 * Inject application specific egw object with loaded translations into the dialog
	 *
	 * @param {string|egw} _egw_or_appname egw object with already loaded translations or application name to load translations for
	 */
	_setApiInstance(_egw_or_appname ? : string | IegwAppLocal)
	{
		if(typeof _egw_or_appname == 'undefined')
		{
			// @ts-ignore
			_egw_or_appname = egw_appName;
		}
		// if egw object is passed in because called from et2, just use it
		if(typeof _egw_or_appname != 'string')
		{
			this.__egw = _egw_or_appname;
		}
		// otherwise use given appname to create app-specific egw instance and load default translations
		else
		{
			this.__egw = egw(_egw_or_appname);
			this.egw().langRequireApp(this.egw().window, _egw_or_appname);
		}
	}

	protected handleButtonClick(event : MouseEvent)
	{
		if(event.target.id !== "cancel")
		{

			throw new Error("Method not implemented.");
		}
		this.open = false;
		this.requestUpdate("open", true);
	}

	protected handleCreateDirectory(event : MouseEvent | KeyboardEvent)
	{
		throw new Error("Method not implemented.");
	}

	handleSearchKeyDown(event)
	{
		clearTimeout(this._searchTimeout);

		// Up / Down navigates options
		if(['ArrowDown', 'ArrowUp'].includes(event.key) && this._fileList.length)
		{
			event.stopPropagation();
			this.setCurrentOption(this._fileNodes[0]);
			return;
		}
		// Start search immediately
		else if(event.key == "Enter")
		{
			event.preventDefault();
			this.startSearch();
			return;
		}
		else if(event.key == "Escape")
		{
			this.value = [];
			this.close();
			return;
		}

		// Start the search automatically if they have enough letters
		// -1 because we're in keyDown handler, and value is from _before_ this key was pressed
		if(this._searchNode.value.length - 1 > 0)
		{
			this._searchTimeout = window.setTimeout(() => {this.startSearch()}, Et2VfsSelect.SEARCH_TIMEOUT);
		}
	}


	protected toolbarTemplate() : TemplateResult
	{
		return html`
            <et2-box class="et2_toolbar">
                <et2-button statustext="Go to your home directory" id="home"
                            image="filemanager/gohome"
                            aria-label=${this.egw().lang("Go to your home folder")}
                            noSubmit="true"
                            @click=${() => this.setPath("~")}
                ></et2-button>
                <et2-button statustext="Up" id="up"
                            image="filemanager/goup" noSubmit="true" aria-label=${this.egw().lang("Up")}
                            @click=${() => this.setPath("..")}
                >
                </et2-button>
                <et2-button statustext="Favorites" id="favorites"
                            aria-label=${this.egw().lang("Favorites")}
                            image="filemanager/fav_filter" noSubmit="true"
                            @click=${() => this.setPath("/apps/favorites")}
                ></et2-button>
                <et2-select-app id="app" emptyLabel="Applications" noLang="1"></et2-select-app>
                <et2-button statustext="Create directory" id="createdir" class="createDir"
                            arial-label=${this.egw().lang("Create directory")}
                            noSubmit="true"
                            image="filemanager/button_createdir"
                            roImage="filemanager/createdir_disabled"
                            @click=${this.handleCreateDirectory}
                ></et2-button>
                <file id="upload_file" statustext="upload file" progress_dropdownlist="true" multiple="true"
                      onFinish="app.vfsSelectUI.storeFile"/>
                <et2-searchbox id="search"
                               @keydown=${this.handleSearchKeyDown}
                ></et2-searchbox>
            </et2-box>
		`;
	}

	protected filesTemplate()
	{
		const empty = this._fileList.length == 0;
		const noFilesTemplate = html`
                                     <div class="vfs_select__empty">
                                         <et2-image src="filemanager"></et2-image>
                                         ${this.egw().lang("no files in this directory.")}
                                     </div>`;
		const promise = this._searchPromise.then(() =>
		{
			return html`
				${empty ? noFilesTemplate : html`
				${repeat(this._fileList, (file) => file.path, (file, index) =>
				{
					return html`
						   <et2-vfs-mime
								   .value=${file}
						   ></et2-vfs-mime>
						   ${file.name}`;
				}
			)}`
			}`;
		});
		return html`
			${until(promise, html`<sl-spinner></sl-spinner>`)}`;
	}

	protected mimeOptionsTemplate()
	{
		return html``;
	}

	protected footerTemplate()
	{
		let image = "check";
		switch(this.mode)
		{
			case "saveas":
				image = "save_new";
				break;
		}

		const buttons = [
			{id: "ok", label: this.buttonLabel, image: image},
			{id: "cancel", label: "cancel", image: "cancel"}
		];

		return html`
            ${repeat(buttons, (button : DialogButton) => button.id, (button, index) =>
		{
			return html`
                    <et2-button id=${button.id}
                                class="et2_button et2_vfs__button"
                                label=${button.label}
                                variant=${index == 0 ? "primary" : "default"}
                                slot="footer"
                                .image=${ifDefined(button.image)}
                                .noSubmit=${true}
                                @click=${this.handleButtonClick}
                    >${button.label}
                    </et2-button>
                `
		})}`;
	}

	render()
	{
		const hasHelpTextSlot = this.hasSlotController.test('help-text');
		const hasFooterSlot = this.hasSlotController.test('footer');
		const hasToolbarSlot = this.hasSlotController.test('toolbar');
		const hasHelpText = this.helpText ? true : !!hasHelpTextSlot;
		const hasToolbar = !!hasToolbarSlot;

		const hasFilename = this.mode == "saveas";

		return html`
            <et2-dialog
                    .isModal="true"
                    .destroyOnClose="false"
                    .title=${this.title}
                    .open=${this.open}
            >
                ${hasFilename ? html`<input id="filename"/>` : nothing}
                <div
                        part="toolbar"
                        id="toolbar"
                        class="vfs_select__toolbar"
                >
                    <slot name="prefix"></slot>
                    <slot name="toolbar">${hasToolbar ? nothing : this.toolbarTemplate()}</slot>
                    <slot name="suffix"></slot>
                </div>
                <div
                        part="path"
                >
                    <input id="path"
                           value=${this.path}
                           @onchange=${this.startSearch}
                    />
                </div>
                <div
                        id="listbox"
                        role="listbox"
                        aria-expanded=${this.open ? 'true' : 'false'}
                        aria-multiselectable=${this.multiple ? 'true' : "false"}
                        aria-labelledby="title"
                        part="listbox"
                        class="vfs_select__listbox"
                        tabindex="-1"
                >
                    ${this.filesTemplate()}
                </div>
                <sl-visually-hidden>
                    <et2-label for="mimeFilter">${this.egw().lang("mime filter")}</et2-label>
                </sl-visually-hidden>
                <et2-select
                        id="mimeFilter"
                        part="mimefilter"
                        class="vfs_select__mimefilter"
                        emptyLabel=${this.egw().lang("All files")}
                        ?readonly=${this.mimeList.length == 1}
                >
                    ${this.mimeOptionsTemplate()}
                </et2-select>
                <slot></slot>
                <div
                        part="form-control-help-text"
                        id="help-text"
                        class="form-control__help-text"
                        aria-hidden=${hasHelpText ? 'false' : 'true'}
                >
                    <slot name="help-text">${this.helpText}</slot>
                </div>
                ${hasFooterSlot ? nothing : this.footerTemplate()}
            </et2-dialog>
		`;
	}

}

customElements.define("et2-vfs-select", Et2VfsSelect);

export interface FileInfo
{
	name : string,
	mime : string,
	isDir : boolean,
	path? : string,
}