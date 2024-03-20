/**
 * EGroupware eTemplate2 - File selection WebComponent
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 */

import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {html, LitElement, nothing, PropertyValues, TemplateResult} from "lit";
import shoelace from "../Styles/shoelace";
import styles from "./Et2VfsSelect.styles";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {ifDefined} from "lit/directives/if-defined.js";
import {classMap} from "lit/directives/class-map.js";
import {repeat} from "lit/directives/repeat.js";
import {SelectOption} from "../Et2Select/FindSelectOptions";
import {DialogButton, Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {HasSlotController} from "../Et2Widget/slot";
import {egw, IegwAppLocal} from "../../jsapi/egw_global";
import {Et2Select} from "../Et2Select/Et2Select";
import {Et2VfsSelectRow} from "./Et2VfsSelectRow";
import {Et2VfsPath} from "./Et2VfsPath";
import {SearchMixin, SearchResult, SearchResultElement, SearchResultsInterface} from "../Et2Widget/SearchMixin";

/**
 * @summary Select files (including directories) from the VFS.
 *
 * The dialog does not do anything with the files, just handles the UI to select them.
 *
 * @dependency et2-box
 * @dependency et2-button
 * @dependency et2-dialog
 * @dependency et2-image
 * @dependency et2-searchbox
 * @dependency et2-select
 * @dependency et2-vfs-select-row
 *
 * @slot title - Optional additions to title.
 * @slot toolbar - Toolbar containing controls for search & navigation
 * @slot prefix - Before the toolbar
 * @slot suffix - Like prefix, but after
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the `help-text` attribute.
 * @slot footer - Customise the dialog footer
 *
 * @event change - Emitted when the control's value changes.
 *
 * @csspart toolbar - controls at the top
 * @csspart path - Et2VfsPath control
 * @csspart listbox - The list of files
 * @csspart mimefilter - Mime filter select
 * @csspart form-control-help-text - The help text's wrapper.
 *
 */

type Constructor<T = {}> = new (...args : any[]) => T;

export class Et2VfsSelectDialog
	extends SearchMixin<Constructor<any> & typeof LitElement, FileInfo, FileResultsInterface>(Et2InputWidget(LitElement))
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
	@property({type: String}) mode : "open" | "open-multiple" | "saveas" | "select-dir";

	/** Button label */
	@property({type: String}) buttonLabel : string = "Select";

	/** Provide a suggested filename for saving */
	@property() filename : string = "";

	/** Allow selecting multiple files */
	@property({type: Boolean}) multiple = false;

	/** Start path in VFS.  Leave unset to use the last used path. */
	@property() path : string = "";

	/** Limit display to the given mime-type */
	@property() mime : string | string[] | RegExp = "";

	/** List of mimetypes to allow user to filter.  */
	@property({type: Array}) mimeList : SelectOption[] = [
		{
			value: "/(application\\/vnd.oasis.opendocument.text|application\\/vnd.openxmlformats-officedocument.wordprocessingml.document)/i",
			label: "Documents"
		},
		{
			value: "/(application\\/vnd.oasis.opendocument.spreadsheet|application\\/vnd.openxmlformats-officedocument.spreadsheetml.sheet)/i",
			label: "Spreadsheets"
		},
		{value: "image/", label: "Images"},
		{value: "video/", label: "Videos"}
	];

	/** The select's help text. If you need to display HTML, use the `help-text` slot instead. */
	@property({attribute: 'help-text'}) helpText = '';

	@state() open : boolean = false;
	@state() currentResult : Et2VfsSelectRow;
	@state() selectedFiles : Et2VfsSelectRow[] = [];
	@state() _pathWritable : boolean = false;


	// SearchMixinInterface //
	@property() searchUrl : string = "EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelectFiles";

	// End SearchMixinInterface //

	// Still need some server-side info
	protected _serverContent : Promise<any> = Promise.resolve({});
	private static SERVER_URL = "EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelect_content";

	protected readonly hasSlotController = new HasSlotController(<LitElement><unknown>this, 'help-text', 'toolbar', 'footer');

	// @ts-ignore different types
	protected _appList : SelectOption[] = this.egw().link_app_list("query") ?? [];

	// Internal accessors
	get _dialog() : Et2Dialog { return this.shadowRoot.querySelector("et2-dialog");}

	get _filenameNode() : HTMLInputElement { return this.shadowRoot.querySelector("#filename");}

	get _fileNodes() : Et2VfsSelectRow[] { return Array.from(this.shadowRoot.querySelectorAll("et2-vfs-select-row"));}

	get _resultNodes() : (HTMLElement & SearchResultElement)[] { return <(HTMLElement & SearchResultElement)[]><unknown>this._fileNodes;}

	get _searchNode() : HTMLInputElement { return this.shadowRoot.querySelector("#search");}

	get _pathNode() : Et2VfsPath { return this.shadowRoot.querySelector("#path");}

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

		this.handleClose = this.handleClose.bind(this);
		this.handleCreateDirectory = this.handleCreateDirectory.bind(this);
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
		return this.egw().request(this.egw().link(this.egw().ajaxUrl(this.egw().decodePath(Et2VfsSelectDialog.SERVER_URL))),
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
			this.path = <string>this.egw()?.preference("startfolder", "filemanager") || "~";
		}
	}

	async getUpdateComplete()
	{
		const result = await super.getUpdateComplete();

		// Need to wait for server content
		await this._serverContent;

		return result;
	}

	firstUpdated()
	{
		this._dialog.updateComplete.then(() =>
		{
			this._dialog.panel.style.width = "60em";
			this._dialog.panel.style.height = "40em";
		});
		// Get file list
		this.startSearch();
	}

	protected willUpdate(changedProperties : PropertyValues)
	{
		super.willUpdate(changedProperties);

		if(changedProperties.has("mode"))
		{
			this.multiple = this.mode == "open-multiple";
		}

		if(changedProperties.has("path"))
		{
			this.startSearch();
		}
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
		this.currentResult = null;

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
	 * Get file information of currently displayed paths
	 *
	 * Returns null if the path is not currently displayed
	 * @param _path
	 */
	public fileInfo(_path)
	{
		return this._searchResults.find(f => f.path == _path);
	}

	/**
	 * Shows the dialog.
	 */
	public show()
	{
		this.open = true;
		if(this.path && this._searchResults.length == 0)
		{
			this.startSearch();
		}
		return Promise.all([
			this.updateComplete,
			this._searchPromise
		]).then(() =>
		{
			return this._dialog.show();
		}).then(() =>
		{
			// Set current file to first value
			if(this.value && this.value[0])
			{
				this.setCurrentResult(this._fileNodes.find(node => node.value.path == this.value[0]));
			}
		});
	}

	/**
	 * Hides the dialog.
	 */
	public hide()
	{
		this.open = false;
		return this._dialog.hide();
	}

	async getComplete() : Promise<[number, Object]>
	{
		await this.updateComplete;
		const value = await this._dialog.getComplete();
		await this.handleClose();
		value[1] = this.value;
		return value;
	}

	protected localSearch<FileInfo>(search : string, searchOptions : object, localOptions : FileInfo[] = []) : Promise<FileInfo[]>
	{
		return super.localSearch(search, {...searchOptions, mime: this.mime}, localOptions);
	}

	public searchMatch<FileInfo>(search : string, searchOptions : Object, option : FileInfo) : boolean
	{
		let result = super.searchMatch(search, searchOptions, option);

		// Add in local mime check
		if(result && searchOptions.mime)
		{
			result = result && option.mime.match(searchOptions.mime);
		}
		return result;
	}

	remoteSearch<FileInfo>(search : string, options : object) : Promise<FileInfo[]>
	{
		// Include a limit, even if options don't, to avoid massive lists breaking the UI
		let sendOptions = {
			path: this.path,
			mime: this.mime,
			num_rows: 100,
			...options
		}
		return super.remoteSearch(search, sendOptions);
	}

	processRemoteResults<FileInfo>(results) : FileInfo[]
	{
		const result = super.processRemoteResults(results);
		if(typeof results.path === "string")
		{
			// Something like a redirect or link followed - server is sending us a "corrected" path
			this.path = results.path;
		}
		if(typeof results.writable !== "undefined")
		{
			this._pathWritable = results.writable;
			this.requestUpdate("_pathWritable");
		}

		this.helpText = results?.message ?? "";

		return result;
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

	private async handleClose()
	{
		// Should already be complete, we want the button
		let dialogValue = await this._dialog.getComplete();
		switch(this.mode)
		{
			case "select-dir":
				// If they didn't pick a specific directory and didn't cancel, use the current directory
				this.value = this.value.length ? this.value : [this.path];
				break;
			case "saveas":
				// Saveas wants a full path, including filename
				this.value = [this.path + "/" + this._filenameNode.value ?? this.filename];

				// Check for existing file, ask what to do
				if(this.fileInfo(this.value[0]))
				{
					let result = await this.overwritePrompt(this._filenameNode ?? this.filename);
					if(result == null)
					{
						return;
					}
					this.value = [this.path + "/" + result];
				}
				break;
		}
		this.dispatchEvent(new Event("change", {bubbles: true}));
	}

	/**
	 * User tried to saveas when we can see that file already exists.  Prompt to overwrite or rename.
	 *
	 * We offer a suggested new name by appending "(#)", and give back either the original filename, their
	 * modified filename, or null if they cancel.
	 *
	 * @param filename
	 * @returns {Promise<[number|string, Object]|null>} [Button,filename] or null if they cancel
	 * @private
	 */
	private overwritePrompt(filename) : Promise<[number | string, object] | null>
	{
		// Make a filename suggestion
		const parts = filename.split(".");
		const extension = parts.pop();
		const newName = parts.join(".");
		let counter = 0;
		let suggestion;
		do
		{
			counter++;
			suggestion = `${newName} (${counter}).${extension}`;
		}
		while(this.fileInfo(suggestion))

		// Ask about it
		const saveModeDialogButtons = [
			{
				label: self.egw().lang("Yes"),
				id: "overwrite",
				class: "ui-priority-primary",
				"default": true,
				image: 'check'
			},
			{label: self.egw().lang("Rename"), id: "rename", image: 'edit'},
			{label: self.egw().lang("Cancel"), id: "cancel"}
		];
		return Et2Dialog.show_prompt(null,
			self.egw().lang('Do you want to overwrite existing file %1 in directory %2?', filename, this.path),
			self.egw().lang('File %1 already exists', filename),
			suggestion, saveModeDialogButtons, null).getComplete().then(([button, value]) =>
		{
			if(button == "cancel")
			{
				return null;
			}
			return button == "rename" ? value.value : filename;
		});
	}

	/**
	 * Sets the selected files
	 * @param {Et2VfsSelectRow | Et2VfsSelectRow[]} file
	 * @private
	 */
	private setSelectedFiles(file : Et2VfsSelectRow | Et2VfsSelectRow[])
	{
		const newSelectedOptions = Array.isArray(file) ? file : [file];

		// Clear existing selection
		this._fileNodes.forEach(el =>
		{
			el.selected = false;
			el.requestUpdate("selected");
		});

		// Set the new selection
		if(newSelectedOptions.length)
		{
			newSelectedOptions.forEach(el =>
			{
				el.selected = true;
				el.requestUpdate("selected");
			});
		}

		// Update selection, value, and display label
		this.searchResultSelected();
	}

	/**
	 * Toggles a search result's selected state
	 */
	protected toggleResultSelection(result : HTMLElement & SearchResultElement, force? : boolean)
	{
		if(!this.multiple)
		{
			this._resultNodes.forEach(n =>
			{
				n.selected = false;
				n.requestUpdate("selected");
			});
		}
		super.toggleResultSelection(result, force);
	}

	/**
	 * This method must be called whenever the selection changes. It will update the selected file cache, the current
	 * value, and the display value
	 */
	protected searchResultSelected()
	{
		super.searchResultSelected();

		// Update the value
		if(this.multiple)
		{
			this.value = this.selectedResults.map(el => el.value.path);
		}
		else
		{
			this.value = (this.selectedResults?.length ? [this.selectedResults[0].value.path] : []) ?? [];
		}
	}

	/**
	 * Create a new directory in the current one
	 * @param {MouseEvent | KeyboardEvent} event
	 * @returns {Promise<void>}
	 * @protected
	 */
	protected async handleCreateDirectory(event : MouseEvent | KeyboardEvent)
	{
		// Get new directory name
		let [button, value] = await Et2Dialog.show_prompt(
			null, this.egw().lang('New directory'), this.egw().lang('Create directory')
		).getComplete();
		let dir = value.value;

		if(button && dir)
		{
			this.egw().request('EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_create_dir', [dir, this.path])
				.then((msg) =>
				{
					this.egw().message(msg);
					this.setPath(this.path + '/' + dir);
				});
		}
	}

	/**
	 * SearchMixin handles the actual selection, we just reject directories here.
	 *
	 * @param {MouseEvent} event
	 */
	handleFileClick(event : MouseEvent)
	{
		const target = event.target as HTMLElement;
		const file : Et2VfsSelectRow = target.closest('et2-vfs-select-row');
		const oldValue = this.value;

		if(file && !file.disabled)
		{
			// Can't select a directory normally, can't select anything in "saveas"
			if(file.value.isDir && this.mode != "select-dir" || this.mode == "saveas")
			{
				event.preventDefault();
				event.stopPropagation();
				return;
			}
			// Set focus after updating so the value is announced by screen readers
			//this.updateComplete.then(() => this.displayInput.focus({ preventScroll: true }));
		}
	}

	handleFileDoubleClick(event : MouseEvent)
	{
		const target = event.target as HTMLElement;
		const file : Et2VfsSelectRow = target.closest('et2-vfs-select-row');

		if(file.value.isDir)
		{
			this.toggleResultSelection(file, false);
			const oldPath = this.path;
			this.setPath(file.value.path);
		}
		else
		{
			// Not a dir, just select it
			this.handleFileClick(event);

			// If we only want one, we've got it.  Close by clicking the primary button
			if(!this.multiple)
			{
				this.shadowRoot.querySelector('et2-button[variant="primary"]')?.click();
			}
		}
	}

	handleKeyDown(event)
	{
		// Ignore selects
		if(event.target.tagName.startsWith('ET2-SELECT'))
		{
			return;
		}

		// Grab any keypresses, avoid EgwAction reacting on them too
		event.stopPropagation()

		// Navigate options
		if(["ArrowUp", "ArrowDown", "Home", "End"].includes(event.key))
		{
			const files = this._fileNodes;
			const currentIndex = files.indexOf(this.currentResult);
			let newIndex = Math.max(0, currentIndex);

			// Prevent scrolling
			event.preventDefault();

			if(event.key === "ArrowDown")
			{
				newIndex = currentIndex + 1;
				if(newIndex > files.length - 1)
				{
					return this._mimeNode.focus();
				}
			}
			else if(event.key === "ArrowUp")
			{
				newIndex = currentIndex - 1;
				if(newIndex < 0)
				{
					return this._pathNode.focus();
				}
			}
			else if(event.key === "Home")
			{
				newIndex = 0;
			}
			else if(event.key === "End")
			{
				newIndex = files.length - 1;
			}

			this.setCurrentResult(files[newIndex]);
		}
		else if([" "].includes(event.key) && this.currentResult)
		{
			// Prevent scrolling
			event.preventDefault();

			return this.handleFileClick(event);
		}
		else if(["Enter"].includes(event.key) && this.currentResult && !this.currentResult.disabled)
		{
			return this.handleFileDoubleClick(event);
		}
		else if(["Escape"].includes(event.key))
		{
			this.open = false;
		}
	}

	handleSearchKeyDown(event)
	{
		clearTimeout(this._searchTimeout);

		// Up / Down navigates options
		if(['ArrowDown', 'ArrowUp'].includes(event.key) && this._searchResults.length)
		{
			return super.handleSearchKeyDown(event);
		}
		// Start search immediately
		else if(event.key == "Enter")
		{
			return super.handleSearchKeyDown(event);
		}
		else if(event.key == "Escape")
		{
			super.handleSearchKeyDown(event);

			event.preventDefault();
			this.value = [];
			this.hide();
			return;
		}

		// Start the search automatically if they have something typed
		if(this._searchNode.value.length > 0)
		{
			this._searchTimeout = window.setTimeout(() => {this.startSearch()}, Et2VfsSelectDialog.SEARCH_TIMEOUT);
		}
	}


	protected toolbarTemplate() : TemplateResult
	{
		return html`
            <div class="et2_toolbar">
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
                <et2-select id="app" emptyLabel="Applications" noLang="1"
                            .select_options=${this._appList}
                            @change=${(e) => this.setPath("/apps/" + e.target.value)}
                >
                </et2-select>
                <et2-button statustext="Create directory" id="createdir" class="createDir"
                            arial-label=${this.egw().lang("Create directory")}
                            ?disabled=${!this._pathWritable}
                            noSubmit="true"
                            image="filemanager/button_createdir"
                            roImage="filemanager/createdir_disabled"
                            @click=${this.handleCreateDirectory}
                ></et2-button>
                <file id="upload_file" statustext="upload file" progress_dropdownlist="true" multiple="true"
                      ?disabled=${!this._pathWritable}
                      onFinish="app.vfsSelectUI.storeFile"
                ></file>
                <et2-searchbox id="search"
                               @keydown=${this.handleSearchKeyDown}
                               @sl-clear=${this.startSearch}
                ></et2-searchbox>
            </div>
		`;
	}

	protected resultTemplate(file : FileInfo, index)
	{
		const classes = file.class ? Object.fromEntries((file.class).split(" ").map(k => [k, true])) : {};

		return html`
            <et2-vfs-select-row
                    class=${classMap({
                        ...classes
                    })}
                    ?disabled=${file.disabled || this.mode == "select-dir" && !file.isDir}
                    .selected=${this.value.includes(file.path)}
                    .value=${file}
                    @mouseup=${this.handleFileClick}
                    @dblclick=${this.handleFileDoubleClick}
            ></et2-vfs-select-row>`;
	}

	protected noResultsTemplate() : TemplateResult
	{
		return html`
            <div class="search__empty vfs_select__empty">
                <et2-image src="filemanager"></et2-image>
                ${this.egw().lang("no files in this directory.")}
            </div>`;
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
			{id: "ok", label: this.buttonLabel, image: image, button_id: Et2Dialog.OK_BUTTON},
			{id: "cancel", label: "cancel", image: "cancel", button_id: Et2Dialog.CANCEL_BUTTON}
		];

		return html`
            <slot name="footer" slot="footer"></slot>
            ${repeat(buttons, (button : DialogButton) => button.id, (button, index) =>
		{
            // style=order is to allow slotted buttons an opportunity to choose where they go.  
            // Default is they'll go before our primary button
			return html`
                    <et2-button id=${button.id}
                                button_id=${button.button_id}
                                class="et2_button et2_vfs__button"
                                style="order: ${(index + 1) * 2}"
                                label=${button.label}
                                variant=${index == 0 ? "primary" : "default"}
                                slot="footer"
                                .image=${ifDefined(button.image)}
                                .noSubmit=${true}
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
		const mime = typeof this.mime == "string" ? this.mime : (this.mimeList.length == 1 ? this.mimeList[0].value : "");

		return html`
            <et2-dialog
                    .isModal=${true}
                    .destroyOnClose=${false}
                    .title=${this.title}
                    .open=${this.open}
                    @keydown=${this.handleKeyDown}
                    @close=${this.handleClose}
            >
                ${hasFilename ? html`
                    <et2-textbox id="filename"
                                 .value=${this.filename}
                                 @change=${(e) => {this.filename = e.target.value;}}
                    >
                    </et2-textbox>` : nothing}
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
                    <et2-vfs-path id="path"
                                  .value=${this.path}
                                  @change=${() => {this.setPath(this._pathNode.value)}}
                    ></et2-vfs-path>
                </div>
                ${this.searchResultsTemplate()}
                <sl-visually-hidden>
                    <et2-label for="mimeFilter">${this.egw().lang("mime filter")}</et2-label>
                </sl-visually-hidden>
                <et2-select
                        id="mimeFilter"
                        part="mimefilter"
                        class="vfs_select__mimefilter"
                        ?readonly=${this.mimeList.length == 1}
                        .emptyLabel=${this.egw().lang("All files")}
                        .select_options=${this.mimeList}
                        .value=${mime}
                        @change=${(e) =>
                        {
                            this.mime = e.target.value;
                            this.startSearch();
                        }}
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
                ${this.footerTemplate()}
            </et2-dialog>
		`;
	}
}

customElements.define("et2-vfs-select-dialog", Et2VfsSelectDialog);

export type FileInfo = SearchResult &
{
	mime : string,
	isDir : boolean,
	// Full VFS path
	path? : string,
	// Direct download link
	downloadUrl? : string
}

/**
 * We expect the server to respond with file data in this format
 */
interface FileResultsInterface extends SearchResultsInterface<FileInfo>
{
	// Something like a redirect or link followed - server is sending us a "corrected" path
	path? : string,
	// The current directory is not writable
	writable? : boolean
}
