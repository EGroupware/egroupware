import {html, LitElement, nothing, PropertyValueMap, render} from "lit";
import {customElement} from "lit/decorators/custom-element.js";
import {property} from "lit/decorators/property.js";
import {state} from "lit/decorators/state.js";
import {classMap} from "lit/directives/class-map.js";
import {ifDefined} from "lit/directives/if-defined.js";
import {repeat} from "lit/directives/repeat.js";
import shoelace from "../Styles/shoelace";
import styles from "./Et2File.styles";
import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {Et2FileItem} from "./Et2FileItem";
import Resumable from "../../Resumable/resumable";
import {HasSlotController} from "../Et2Widget/slot";

// ResumableFile not defined in a way we can use it
export interface FileInfo extends ResumableFile
{
	loading? : boolean;
	accepted? : boolean;
	warning? : string;

	// Existing values
	path? : string;

	// ResumableFile
	fileName : string;
	uniqueIdentifier : string;
	file : File;
	progress? : Function;
	abort? : Function
	cancel? : Function;
}

/**
 * @summary Displays a button to select files to upload
 *
 *
 * @dependency sl-format-bytes
 * @dependency sl-progress-bar
 * @dependency sl-icon
 *
 * @slot image - The component's image
 * @slot label - Button label
 * @slot prefix	- Used to prepend a presentational icon or similar element before the button.
 * @slot suffix - Used to append a presentational icon or similar element after the button.
 * @slot help-text - Text that describes how to use the input. Alternatively, you can use the help-text attribute.
 * @slot button - A button to use in lieu of the default button
 * @slot list - Selected files are listed here.  Place something in this slot to override the normal file list.
 *
 * @event et2-add - Emitted when a file is added
 * @event et2-load - Emitted when file is complete
 * @event change - Emitted when all selected files are complete
 *
 * @csspart base - Component internal wrapper
 * @csspart button - Button that opens browser's file selection dialog
 * @csspart list - List of files
 */
@customElement("et2-file")
export class Et2File extends Et2InputWidget(LitElement)
{

	static get styles()
	{
		return [
			shoelace,
			super.styles,
			styles
		];
	}

	/** A string that defines the file types the file should accept. Defaults to all. */
	@property({type: String, reflect: true}) accept = "";

	/** An optional maximum size of a file that will be considered valid. */
	@property({type: Number, attribute: "max-file-size"}) maxFileSize? : number;

	/** The maximum amount of files that are allowed. */
	@property({type: Number, attribute: "max-files"}) maxFiles : number;

	/** Indicates if multiple files can be uploaded */
	@property({type: Boolean, reflect: true}) multiple = false;

	/** Draws the item in a loading state. */
	@property({type: Boolean, reflect: true}) loading = false;

	/** If true, no file list will be shown */
	@property({type: Boolean, reflect: true, attribute: "no-file-list"}) noFileList = false;

	/** Target element to show file list in instead of the default dropdown.  Either a querySelector or widget ID.*/
	@property({type: String}) fileListTarget : string;

	/** Component to listen for file drops */
	@property({type: String}) dropTarget : string;

	@property({type: String}) display : "large" | "small" | "list" = "large";

	/** Show the files inline instead of floating over the rest of the page.  This can cause the page to reflow */
	@property({type: Boolean}) inline = false;

	/** The button's image */
	@property({type: String}) image : string = "paperclip";

	/** Server path to receive uploaded file */
	@property({type: String}) uploadTarget : string = "EGroupware\\Api\\Etemplate\\Widget\\File::ajax_upload";

	@property({type: Object}) uploadOptions : {};

	/** Chunk size can be altered by the server */
	@property({type: Number}) chunkSize = 1020 * 1024;

	@property({type: Function}) onStart : Function;
	@property({type: Function}) onFinishOne : Function;
	@property({type: Function}) onFinish : Function;

	@state() files : FileInfo[] = [];

	// Allows us to check to see if label or help-text is set.  Overridden to check additional slots.
	protected readonly hasSlotController = new HasSlotController(this, 'help-text', 'label', 'button', 'list');
	protected resumable : Resumable = null;
	private __value : { [tempName : string] : FileInfo } = {};

	// In case we need to do things between file added and start of upload, we wait
	protected _uploadPending : { [uniqueIdentifier : string] : Promise<void> } = {};
	private _uploadDelayTimeout : number;
	private _destroyDelayTimeout : number;

	/** Files already uploaded */
	@property({type: Object})
	set value(newValue : { [tempFileName : string] : FileInfo })
	{
		const oldValue = this.value;
		if(typeof newValue !== 'object' || !newValue)
		{
			newValue = {};
		}
		if(typeof newValue.length !== "undefined")
		{
			// We use an object, not an Array
			newValue = {...newValue};
		}
		Object.keys(newValue).forEach((key) =>
		{
			if(typeof newValue[key].uniqueIdentifier == "undefined")
			{
				newValue[key].uniqueIdentifier = (newValue[key]['ino'] ?? key) + newValue[key].path;
			}
		});
		this.__value = newValue;
		this.requestUpdate("value");
	}

	get value() : { [tempFileName : string] : FileInfo }
	{
		return this.__value;
	}

	get fileInput() : HTMLInputElement { return this.shadowRoot?.querySelector("#file-input");}

	get list() : HTMLElement
	{
		return this.fileListTarget ?
			   this.parentNode.querySelector(this.fileListTarget) ?? this.egw().window.document.querySelector(this.fileListTarget) ?? this.getRoot()?.getWidgetById(this.fileListTarget)?.getDOMNode() :
			   this.shadowRoot?.querySelector("slot[name='list']");
	}

	get fileItemList() : Et2FileItem[]
	{
		return Array.from(this.list?.querySelectorAll("et2-file-item")) ?? [];
	}

	constructor()
	{
		super();
		this.resumableQuery = this.resumableQuery.bind(this);
		this.handleFileClick = this.handleFileClick.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		// Resumable does not deal with destruction well.
		// If we were waiting to see if this reconnected, it has, so stop the destroy timeout
		if(this._destroyDelayTimeout)
		{
			window.clearTimeout(this._destroyDelayTimeout);
		}
		this.classList.add("et2-button-widget");
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		if(this._uploadDelayTimeout)
		{
			window.clearTimeout(this._uploadDelayTimeout);
		}

		// Resumable does not deal with destruction well.
		// If actually destroyed it will not work again, so we'll wait a bit on destruction to see if it reconnects
		// (widget was just moved in the DOM)
		if(this.resumable && !this._destroyDelayTimeout)
		{
			this._destroyDelayTimeout = window.setTimeout(() =>
			{
				this.resumable.cancel();
				this.resumable = null;
			}, 1000);
		}
	}


	firstUpdated(changedProperties : PropertyValueMap<any>)
	{
		super.firstUpdated(changedProperties)
		this.resumable = this.createResumable();
	}

	updated(changedProperties : PropertyValueMap<any>)
	{
		super.updated(changedProperties);
		if(this.fileListTarget && this.list)
		{
			render(this.fileListTemplate(), this.list);
		}
	}

	loadFromXML(node : Node)
	{
		super.loadFromXML(node);

		// If it's readonly, don't care if multiple wasn't set, show all files
		if(this.readonly && !node.hasAttribute("multiple") && this.value && Object.values(this.value).length > 1)
		{
			this.multiple = true;
			this.egw().debug("log", "Setting multiple=true for readonly file widget with more than one file");
		}

		// If it's readonly and inline was not set, show as inline not dropdown
		if(this.readonly && !node.hasAttribute("inline"))
		{
			this.inline = true;
			this.egw().debug("log", "Setting inline=true for readonly file widget (no popup)");
		}

		// Set display to "small" for multiple=false && nothing else set
		if(!node.hasAttribute("display") && !this.multiple)
		{
			this.display = "small";
		}
	}

	protected createResumable()
	{
		const resumable = new Resumable(this.resumableOptions);
		resumable.assignBrowse(this.fileInput);
		if(this.dropTarget)
		{
			const target = this.getRoot().getWidgetById(this.dropTarget) ?? this.egw().window.document.getElementById(this.dropTarget);
			if(target)
			{
				resumable.assignDrop([target])
			}
		}
		resumable.on('fileAdded', this.resumableFileAdded.bind(this));
		resumable.on('fileProgress', this.resumableFileProgress.bind(this));
		resumable.on('fileSuccess', this.resumableFileComplete.bind(this));
		resumable.on('fileError', this.resumableFileError.bind(this));
		resumable.on('complete', this.resumableUploadComplete.bind(this));

		return resumable;
	}

	protected get resumableOptions()
	{
		const options = {
			target: this.egw().ajaxUrl(this.uploadTarget),
			query: this.resumableQuery,
			chunkSize: this.chunkSize,

			// Checking for already uploaded chunks - resumable uploads
			testChunks: true,
			testTarget: this.egw().ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\File::ajax_test_chunk"),

			// Failure & retry
			//xhrTimeout: 10000
		};
		if(this.accept)
		{
			options["fileType"] = this.accept.split(",").map(f => f.trim())
		}
		if(this.maxFiles || !this.multiple)
		{
			options["maxFiles"] = this.maxFiles ?? 1;
		}
		if(this.maxFileSize)
		{
			options['maxFileSize'] = this.maxFileSize;
		}

		Object.assign(options, this.uploadOptions);

		return options;
	}

	protected resumableQuery(file /*: ResumableFile*/, chunk /*: ResumableChunk */)
	{
		return {
			request_id: this.getInstanceManager()?.etemplate_exec_id,
			widget_id: this.id,
		};
	}

	public findFileItem(file)
	{
		const searchIdentifier = file.uniqueIdentifier;
		let fileInfo = this.files.find((i) => i.file.uniqueIdentifier == searchIdentifier);
		if(!fileInfo)
		{
			const source = <FileInfo[]>Object.values(this.value);
			fileInfo = source.find(e => e.uniqueIdentifier == searchIdentifier);
		}

		file = fileInfo;
		const fileItem : Et2FileItem = this.fileItemList.find(i => i.dataset.fileId == searchIdentifier);
		return fileItem;
	}

	protected async resumableFileAdded(file : FileInfo, event)
	{
		this.set_validation_error(false);
		file = {
			accepted: true,
			loading: true,
			...file
		}
		if(!file.accepted)
		{
			return;
		}
		this.files = this.multiple ? [...this.files, file] : [file];
		if(!this.multiple)
		{
			// New selection overwrites existing
			this.__value = {};
		}
		this.requestUpdate();

		await this.updateComplete;

		const fileItem = this.findFileItem(file);
		if(!fileItem)
		{
			return;
		}
		fileItem.loading = true;
		fileItem.requestUpdate("loading");

		// Bind close => abort upload
		fileItem.addEventListener("sl-hide", () =>
		{
			file.abort();
			this.resumable.removeFile(file);
		}, {once: true});

		// Actually start uploading
		await fileItem.updateComplete;
		const ev = new CustomEvent("et2-add", {bubbles: true, detail: file, cancelable: true});
		this.dispatchEvent(ev);

		if(typeof this.onStart == "function")
		{
			this.onStart(ev);
		}
		if(ev.defaultPrevented)
		{
			// Event handling canceled the upload
			file.cancel();
		}
		// We can't pause individual files, just the upload as a whole, so wait together
		if(this._uploadDelayTimeout)
		{
			window.clearTimeout(this._uploadDelayTimeout);
		}
		this._uploadDelayTimeout = window.setTimeout(() =>
		{
			Promise.allSettled(Object.values(this._uploadPending)).then(() =>
			{
				this._uploadPending = {};
				this._uploadDelayTimeout = null;
				window.setTimeout(this.resumable.upload);
			});
		}, 100);
	}

	protected resumableFileProgress(file : FileInfo, event)
	{
		const fileItem = this.findFileItem(file);
		if(fileItem && file.progress())
		{
			fileItem.progress = file.progress() * 100;
			// Show indeterminate while processing
			if(fileItem.progress == 100)
			{
				delete fileItem.progress;
			}
			fileItem.requestUpdate("progress");
		}
	}

	protected resumableFileComplete(file : FileInfo, jsonResponse)
	{
		const response = ((JSON.parse(jsonResponse)['response'] ?? {}).find(i => i['type'] == "data") ?? {})['data'] ?? {};
		const fileItem = this.findFileItem(file);
		file.loading = false;
		file.progress = () => 100;
		if(fileItem)
		{
			fileItem.progress = 100;
			fileItem.loading = false;
		}

		if((!response || response.length || Object.entries(response).length == 0 || response[file.file?.name]) || (
			Object.values(response).length == 1 && typeof Object.values(response)[0] == "string"
		))
		{
			console.warn("Problem uploading", jsonResponse);
			file.warning = response[file?.file?.name] ?? Object.values(response).pop() ?? "No response";
			if(fileItem)
			{
				fileItem.variant = "warning";
				fileItem.innerHTML += "<br />" + file.warning;
			}
		}
		else
		{
			const ev = new CustomEvent("et2-load", {bubbles: true, detail: file});
			Object.keys(response).forEach((tempName) =>
			{
				if(fileItem)
				{
					fileItem.variant = "success";
				}
				file['tempName'] = tempName;
				this.dispatchEvent(ev);

				// Add file into value
				if (typeof this.value !== 'object' || !this.value)
				{
					this.value = {};
				}
				this.value[tempName] = {
					file: file.file,
					uniqueIdentifier: file.uniqueIdentifier,
					src: (<HTMLSlotElement>fileItem?.shadowRoot.querySelector("slot[name='image']"))?.assignedElements()[0]?.src ?? "",
					...response[tempName],
					accepted: true
				}
				// Remove file from file input & resumable
				this.resumable.removeFile(file);
				this.removeFile(file);
			});
			if(typeof this.onFinishOne == "function")
			{
				this.onFinishOne(ev);
			}
		}
		if(fileItem)
		{
			fileItem.requestUpdate("loading");
			fileItem.requestUpdate("progress");
			fileItem.requestUpdate("variant");
		}
	}

	protected resumableFileError(file, jsonResponse)
	{
		const fileItem = this.findFileItem(file);
		const response = ((JSON.parse(jsonResponse)['response'] ?? {}).find(i => i['type'] == "data") ?? {})['data'] ?? {};
		if((!response || response.length || Object.entries(response).length == 0 || response[file.file?.name]) || (
			Object.values(response).length == 1 && typeof Object.values(response)[0] == "string"
		))
		{
			console.warn("Problem uploading", jsonResponse);
			file.warning = response[file?.file?.name] ?? Object.values(response).pop() ?? "No response";
			if(fileItem)
			{
				fileItem.variant = "warning";
				fileItem.error(file.warning);
			}
		}
		fileItem.loading = false;

		fileItem.requestUpdate("variant");
		fileItem.requestUpdate("loading");
	}

	protected resumableUploadComplete()
	{
		this.requestUpdate();
		this.updateComplete.then(() =>
		{
			const ev = new CustomEvent("change", {detail: this.value, bubbles: true});
			this.dispatchEvent(ev);
			if(typeof this.onFinish == "function")
			{
				const fileWidget = this;
				Object.defineProperty(ev, 'data', {
					get: function()
					{
						console.warn("event.data is deprecated, use event.detail");
						return fileWidget;
					}
				});
				this.onFinish(ev, Object.values(this.value).length);
			}
		})
	}

	public show()
	{
		return this.handleBrowseFileClick();
	}

	addFile(file : File)
	{
		if(typeof file !== "object" || !file.name || !file.type || !file.size)
		{
			console.warn("Invalid file", file);
			return;
		}
		if(this.maxFiles && this.files.length >= this.maxFiles || !this.multiple && this.files.length > 0)
		{
			// TODO : Warn too many files
			return;
		}

		const fileInfo : FileInfo = {
			abort: () => false,
			cancel: () => false,
			uniqueIdentifier: file.name,
			file: file,
			progress: () => 0
		};
		if(!checkMime(file, this.accept))
		{
			fileInfo.accepted = false;
			fileInfo.warning = this.egw().lang("File is of wrong type (%1 != %2)!", file.type, this.accept)
		}
		else if(!hasValidFileSize(file, this.maxFileSize))
		{
			fileInfo.accepted = false;
			// TODO: Stop using et2_vfsSize
			//fileInfo.warning = this.egw().lang("File too large.  Maximum %1", et2_vfsSize.prototype.human_size(this.maxFileSize));
		}

		else
		{
			fileInfo.accepted = true;
		}

		// This can cause doubled FileInfo
		//this.files = this.multiple ? [...this.files, fileInfo] : [fileInfo];
		this.requestUpdate("files");
		if(fileInfo.accepted)
		{
			this.updateComplete.then(() =>
			{
				this.resumable.addFile(fileInfo.file)
			})
		}

	}

	removeFile(file : FileInfo)
	{
		const fileInfo = this.files.find((i) => i.uniqueIdentifier == file.uniqueIdentifier);
		const fileIndex = this.files.indexOf(fileInfo) ?? null;
		if(fileIndex != -1)
		{
			this.files.splice(fileIndex, 1);
		}
		this.requestUpdate("files");
	}

	handleFiles(fileList : FileList | null)
	{
		if(!fileList || fileList.length === 0)
		{
			return;
		}

		if(!this.multiple && fileList.length > 1)
		{
			// TODO : Warn too many files
			return;
		}

		Object.values(fileList).forEach(file =>
		{
			if(typeof file === "object" && file.name && file.type && file.size)
			{
				this.addFile(file)
			}
		});

		this.dispatchEvent(new CustomEvent("change", {bubbles: true, detail: this.files}));
	}

	handleBrowseFileClick()
	{
		if(this.disabled)
		{
			return;
		}
		this.fileInput.click();
	}

	handleFileInputChange()
	{
		this.loading = true;
		this.requestUpdate("loading");
		setTimeout(() =>
		{
			this.handleFiles(this.fileInput.files);

			this.loading = false;
			this.requestUpdate("loading");
		});
	}

	handleFileRemove(info : FileInfo)
	{
		let index = this.files.indexOf(info);
		if(index === -1)
		{
			const source = <FileInfo[]>Object.values(this.value);
			index = source.indexOf(info);
			delete this.value[Object.keys(this.value)[index]];
		}
		else
		{
			this.files.splice(index, 1);
		}

		if(index === -1)
		{
			return;
		}
		if(info && typeof info.progress == "function" && typeof info.abort == "function")
		{
			info.progress() < 1 ? info.abort() : this.resumable.removeFile(this.resumable.getFromUniqueIdentifier(info.uniqueIdentifier));
		}

		this.requestUpdate();

		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new CustomEvent("change", {detail: this.files}));
		})
	}

	handleFileListChange(list)
	{
		for(const mutation of list)
		{
			if(mutation.type === "childList")
			{

			}
		}
	}

	handleFileClick(e)
	{
		// If super didn't handle it (returns false), just use egw.open()
		if(super._handleClick(e) && e.target?.dataset?.path)
		{
			this.egw().open({
				path: e.target.dataset.path,
				type: e.target.dataset.type
			}, "file");

			e.stopImmediatePropagation();
			return;
		}
	}

	fileListTemplate()
	{
		return html`
            <div part="list" class="file__file-list" id="file-list"
                 @click=${this.handleFileClick}
            >
            ${repeat(this.files, (file) => file.uniqueIdentifier, (item, index) => this.fileItemTemplate(item, index))}
            ${repeat(Object.values(this.value), (file) => file.uniqueIdentifier, (item, index) => this.fileItemTemplate(item, index))}
            </div>
		`;
	}

	fileItemTemplate(fileInfo : FileInfo, index)
	{
		const label = (fileInfo.accepted ? (fileInfo['name'] ?? fileInfo.file.name) : fileInfo.warning) ?? fileInfo['name'];
		let icon = fileInfo.icon ?? (fileInfo.warning ? "exclamation-triangle" : undefined);

		// Pull thumbnail from file if we can
		const type = fileInfo.file?.type ?? fileInfo["type"];
		let thumbnail;
		//fileInfo.file can be string this leads to js error
		// so we check if it is actually a file
		if(!icon && fileInfo.file instanceof File && type?.startsWith("image/"))
		{
			try
			{
				thumbnail = URL.createObjectURL(fileInfo.file);
			}
			catch(e)
			{
				// Thumbnail creation failed, but we don't really care
			}
		}
		const variant = !fileInfo.warning ? "default" : "warning";
		const closable = !this.readonly && (fileInfo.accepted || Object.values(this.value).indexOf(fileInfo) !== -1)

		return html`
            <et2-file-item
                    display=${this.display}
                    size=${fileInfo.accepted ? (fileInfo.file.size) : fileInfo.size ?? nothing}
                    variant=${variant}
                    ?closable=${closable}
                    ?loading=${fileInfo.loading}
                    image=${ifDefined(icon)}
                    progress=${typeof fileInfo.progress == "function" ? fileInfo.progress() : (fileInfo.progress ?? nothing)}
                    data-file-index=${index}
                    data-file-id=${fileInfo.uniqueIdentifier}
                    data-path=${fileInfo.path ?? nothing}
                    data-type=${fileInfo.file?.type ?? fileInfo.type ?? nothing}
                    @sl-after-hide=${(event : CustomEvent) =>
                    {
                        event.stopPropagation();
                        event.preventDefault();
                        if(thumbnail)
                        {
                            // Unload thumnail, don't need it anymore
                            URL.revokeObjectURL(thumbnail);
                        }
                        this.handleFileRemove(fileInfo);
                    }}
            >
                ${!icon && thumbnail || !type ? html`
                    <et2-image slot="image"
                               src=${thumbnail ?? "upload"}
                    />
                ` : nothing}
                ${!icon && !thumbnail && type ? html`
                    <et2-vfs-mime
                            slot="image"
                            mime=${type}
                            .value=${{...fileInfo, mime: type}}
                    ></et2-vfs-mime>` : nothing
                }
                ${label}
            </et2-file-item>
		`;
	}


	render()
	{
		const filesList = this.fileListTemplate();
		const hasButtonSlot = this.hasSlotController?.test('button');
		const anchorTarget = hasButtonSlot ? this.shadowRoot?.querySelector("slot[name='button']")?.assignedNodes()[0] : null;

		return html`
            <div
                    part="base"
                    class=${classMap({
                        "file": true,
                        //@ts-ignore disabled comes from Et2Widget
                        "file--disabled": this.disabled,
                        "file--hidden": this.hidden,
                        "file--multiple": this.multiple,
                        "file--single": !this.multiple
                    })}
            >
                <div
                        @click="${(e : Event) =>
                        {
                            e?.preventDefault();
                            e?.stopPropagation();
                            this.handleBrowseFileClick();
                        }}"
                >
                    <slot name="prefix"></slot>
                    <slot name="button">
                        <et2-button part="button"
                                    exportparts="base:button__base"
                                    class="file__button"
                                    id="visible-button"
                                    ?disabled=${this.disabled}
                                    title=${this.helptext ?? this.egw().lang("fileupload")}
                                    noSubmit
                                    image=${!this.loading ? this.image : ""}
                        >
                            ${this.loading ? html`
                                <sl-spinner slot="prefix"></sl-spinner>` : nothing}
                            ${this._labelTemplate()}
                        </et2-button>
                    </slot>
                    ${this.multiple || this.noFileList || this.fileListTarget ? nothing : html`
                        <slot name="list">
                            ${filesList}
                        </slot>`
                    }
                    <slot name="suffix"></slot>
                </div>
                <input type="file"
                       id="file-input"
                       style="display: none;"
                       accept=${ifDefined(this.accept)}
                       ?multiple=${this.multiple || this.maxFiles > 1}
                       ?readonly=${this.readonly}
                       ?disabled=${this.disabled}
                       @change=${this.handleFileInputChange}
                       value=${Array.isArray(this.value)
                               ? this.value.map((f : File | string) => (f instanceof File ? f.name : f)).join(",")
                               : ""}
                />
                ${(this.noFileList || this.fileListTarget || !this.multiple) ? nothing : html`
                    <slot name="list">
                        ${this.inline ? html`
                            ${filesList}
                        ` : html`
                            <sl-popup
                                    part="list"
                                    class="file__file-list"
                                    id="file-list"
                                    .anchor=${anchorTarget ?? "visible-button"}
                                    ?active=${this.files.length > 0 || Object.values(this.value).length > 0}
                                    strategy="fixed"
                                    placement=${"bottom-start"}
                                    flip
                                    flip-fallback-placements="top-end"
                                    auto-size="vertical"
                                    @click=${this.handleFileClick}
                            >${filesList}
                            </sl-popup>`
                        }
                    </slot>`
                }
                ${this._helpTextTemplate()}
            </div>`;
	}
}

/**
 * Check to see if the provided file's mimetype matches
 *
 * @param f File object
 * @return boolean
 */
export function checkMime(f : File, accept = "")
{
	if(!accept || accept == "*")
	{
		return true;
	}

	let mime : string | RegExp = '';
	if(accept.indexOf("/") != 0)
	{
		// Lower case it now, if it's not a regex
		mime = accept.toLowerCase();
	}
	else
	{
		// Convert into a js regex
		var parts = accept.substr(1).match(/(.*)\/([igm]?)$/);
		mime = new RegExp(parts[1], parts.length > 2 ? parts[2] : "");
	}

	// If missing, let the server handle it
	if(!mime || !f.type)
	{
		return true;
	}

	var is_preg = (typeof mime == "object");
	if(!is_preg && f.type.toLowerCase() == mime || is_preg && mime.test(f.type))
	{
		return true;
	}

	// Not right mime
	return false;
}

export function hasValidFileSize(file, maxFileSize)
{
	return !maxFileSize || file.size <= maxFileSize;
}