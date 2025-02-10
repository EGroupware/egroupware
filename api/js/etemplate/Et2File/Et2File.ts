import {html, LitElement, nothing} from "lit";
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

// ResumableFile not defined in a way we can use it
export interface FileInfo extends ResumableFile
{
	loading? : boolean;
	accepted? : boolean;
	warning? : string;
	// ResumableFile
	uniqueIdentifier : string;
	file : File;
	progress : Function;
	abort : Function
}

/**
 * @summary Displays a button to select files to upload
 *
 *
 * @dependency sl-format-bytes
 * @dependency sl-progress-bar
 * @dependency sl-icon
 *
 * @slot - File name
 * @slot image - The file's image (mimetype icon, status icon, etc)
 * @slot close-button - Close button
 * @event load - Emitted when file is loaded
 *
 * @csspart base - Component internal wrapper
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

	/** A string that defines the file types the file dropzone should accept. Defaults to all. */
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

	/** Target element to show file list in instead */
	@property({type: String}) fileListTarget : string;

	/** Component to listen for file drops */
	@property({type: String}) dropTarget : string;

	@property({type: String}) display : "large" | "small" | "list" = "large";

	/** Show the files inline instead of over the rest of the page */
	@property({type: Boolean}) inline = false;

	/** The button's image */
	@property({type: String}) image : string = "paperclip";

	/** Server path to receive uploaded file */
	@property({type: String}) uploadTarget : string = "EGroupware\\Api\\Etemplate\\Widget\\File::ajax_upload";

	@property({type: Object}) uploadOptions : {};

	/** Files already uploaded */
	@property({type: Array}) value : { [tempName : string] : FileInfo[] } = {};

	@state() files : FileInfo[] = [];

	protected resumable : Resumable = null;

	get fileInput() : HTMLInputElement { return this.shadowRoot?.querySelector("#file-input");}

	get list() : HTMLElement
	{
		return this.fileListTarget ?
			   this.egw().window.document.querySelector(this.fileListTarget) ?? this.getRoot()?.getWidgetById(this.fileListTarget) :
			   this.shadowRoot?.querySelector("slot[name='list']");
	}

	get fileItemList() : Et2FileItem[]
	{
		return Array.from(this.list?.querySelectorAll("et2-file-item")) ?? [];
	}

	disconnectedCallback()
	{
		if(this.resumable)
		{
			this.resumable.cancel();
		}
	}

	firstUpdated()
	{
		this.resumable = this.createResumable();
	}

	protected createResumable()
	{
		const resumable = new Resumable(this.resumableOptions);
		resumable.assignBrowse(this.fileInput);
		if(this.dropTarget)
		{
			resumable.assignDrop(this.getRoot().getWidgetById(this.dropTarget) || this.egw().window.document.getElementById(this.dropTarget))
		}
		resumable.on('fileAdded', this.resumableFileAdded.bind(this));
		resumable.on('fileProgress', this.resumableFileProgress.bind(this));
		resumable.on('fileSuccess', this.resumableFileSuccess.bind(this));
		resumable.on('fileError', this.resumableFileError.bind(this));
		resumable.on('complete', this.resumableUploadComplete.bind(this));

		return resumable;
	}

	protected get resumableOptions()
	{
		const options = {
			target: this.egw().ajaxUrl(this.uploadTarget),
			query: {
				request_id: this.getInstanceManager()?.etemplate_exec_id,
				widget_id: this.id,
			},
			chunkSize: 1024 * 1024,

			// Checking for already uploaded chunks - resumable uploads
			testChunks: true,
			testTarget: this.egw().ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\File::ajax_test_chunk")
		};
		if(this.accept)
		{
			options["fileType"] = this.accept.split(",").map(f => f.trim())
		}
		if(this.maxFiles)
		{
			options["maxFiles"] = this.maxFiles;
		}
		if(this.maxFileSize)
		{
			options['maxFileSize'] = this.maxFileSize;
		}

		Object.assign(options, this.uploadOptions);

		return options;
	}

	public findFileItem(file)
	{
		const fileInfo = this.files.find((i) => i.file.uniqueIdentifier == file.uniqueIdentifier);
		file = fileInfo;
		const fileIndex = this.files.indexOf(fileInfo) ?? null;
		const fileItem : Et2FileItem = fileIndex !== -1 ? this.fileItemList[fileIndex] : null;
		return fileItem;
	}

	private async resumableFileAdded(file : FileInfo, event)
	{
		file = {
			accepted: true,
			loading: true,
			...file
		}
		this.files = this.multiple ? [...this.files, file] : [file];
		this.requestUpdate();
		if(!file.accepted)
		{
			return;
		}

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
		this.dispatchEvent(new CustomEvent("et2-add", {bubbles: true, detail: file}));
		setTimeout(this.resumable.upload, 100);
	}

	private resumableFileProgress(file : FileInfo, event)
	{
		const fileItem = this.findFileItem(file);
		if(fileItem && file.progress())
		{
			fileItem.progress = file.progress() * 100;
			fileItem.requestUpdate("progress");
		}
	}

	private resumableFileSuccess(file : FileInfo, jsonResponse)
	{
		const response = (JSON.parse(jsonResponse)['response'] ?? {}).find(i => i.type == "data")['data'] ?? {};
		const fileItem = this.findFileItem(file);
		file.loading = false;
		fileItem.progress = 100;
		fileItem.loading = false;

		if(!response || response.length == 0)
		{
			file.warning = "No response";
			fileItem.variant = "warning";
			fileItem.innerHTML += "<br />" + file.warning;
		}
		else
		{
			this.dispatchEvent(new CustomEvent("et2-load", {bubbles: true, detail: file}));
			Object.keys(response).forEach((tempName) =>
			{
				fileItem.variant = "success";

				// Add file into value
				this.value[tempName] = {
					file: file.file,
					src: (<HTMLSlotElement>fileItem.shadowRoot.querySelector("slot[name='image']")).assignedElements()[0]?.src ?? "",
					...response[tempName]
				}
				// Remove file from file input & resumable
				this.resumable.removeFile(file);
				this.removeFile(file);
			})

		}

		fileItem.requestUpdate("loading");
		fileItem.requestUpdate("progress");
		fileItem.requestUpdate("variant");
	}

	private resumableFileError(file, message)
	{
		const fileItem = this.findFileItem(file);
		fileItem.loading = false;
		file.warning = message;

		fileItem.error(message);

		fileItem.requestUpdate("variant");
		fileItem.requestUpdate("loading");
	}

	private resumableUploadComplete()
	{
		this.requestUpdate();
		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new CustomEvent("change", {detail: this.value, bubbles: true}));
		})
	}

	addFile(file : File)
	{
		if(typeof file !== "object" || !file.name || !file.type || !file.size)
		{
			console.warn("Invalid file", file);
			return;
		}
		if(this.maxFiles && this.files.length >= this.maxFiles)
		{
			// TODO : Warn too many files
			return;
		}

		const fileInfo : FileInfo = {
			abort: () => false,
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

		this.files = this.multiple ? [...this.files, fileInfo] : [fileInfo];
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
		const index = this.files.indexOf(info);
		if(index === -1)
		{
			return;
		}
		const fileInfo = this.files[index];

		this.files.splice(index, 1);
		this.requestUpdate();

		this.updateComplete.then(() =>
		{
			this.dispatchEvent(new CustomEvent("change", {detail: this.files}));
		})
	}

	handleFileListChange(list)
	{
		debugger;
		for(const mutation of list)
		{
			if(mutation.type === "childList")
			{

			}
		}
	}

	fileItemTemplate(fileInfo : FileInfo, index)
	{
		let icon = fileInfo.icon ?? (fileInfo.warning ? "exclamation-triangle" : undefined);

		// Pull thumbnail from file if we can
		let thumbnail;
		if(!icon && fileInfo.file && fileInfo.file.type.startsWith("image/"))
		{
			thumbnail = URL.createObjectURL(fileInfo.file);
		}

		return html`
            <et2-file-item
                    display=${this.display}
                    size=${fileInfo.accepted ? fileInfo.file.size : nothing}
                    variant=${fileInfo.accepted && !fileInfo.warning ? "default" : "warning"}
                    ?closable=${fileInfo.accepted}
                    ?loading=${fileInfo.loading}
                    image=${ifDefined(icon)}
                    progress=${fileInfo.progress() || nothing}
                    data-file-index=${index}
                    data-file-id=${fileInfo.uniqueIdentifier}
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
                ${!icon && thumbnail || !fileInfo.file.type ? html`
                    <et2-image slot="image"
                               src=${thumbnail ?? "upload"}
                    />
                ` : nothing}
                ${!icon && !thumbnail && fileInfo.file.type ? html`
                    <et2-vfs-mime
                            slot="image"
                            mime=${fileInfo.file.type}
                            .value=${{mime: fileInfo.file.type}}
                    ></et2-vfs-mime>` : nothing
                }
                ${fileInfo.accepted ? fileInfo.file.name : fileInfo.warning}
            </et2-file-item>
		`;
	}


	render()
	{
		const filesList = html`
            ${repeat(this.files, (file) => file.uniqueIdentifier, (item, index) => this.fileItemTemplate(item, index))}`;

		return html`
            <div
                    part="base"
                    class=${classMap({
                        "file": true,
                        //@ts-ignore disabled comes from Et2Widget
                        "file--disabled": this.disabled,
                        "file--hidden": this.hidden,
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
                    <slot name="button">
                        <et2-button part="button"
                                    class="file__button"
                                    id="visible-button"
                                    ?disabled=${this.disabled}
                                    noSubmit
                                    image=${!this.loading ? this.image : ""}
                        >
                            ${this.loading ? html`
                                <sl-spinner slot="prefix"></sl-spinner>` : nothing}
                            ${this._labelTemplate()}
                        </et2-button>
                    </slot>
                </div>
                <input type="file"
                       id="file-input"
                       style="display: none;"
                       accept=${ifDefined(this.accept)}
                       ?multiple=${this.multiple}
                       @change=${this.handleFileInputChange}
                       value=${Array.isArray(this.value)
                               ? this.value.map((f : File | string) => (f instanceof File ? f.name : f)).join(",")
                               : ""}
                />
                ${(this.noFileList || this.fileListTarget) ? nothing : html`
                    <slot name="list">
                        ${this.inline ? html`
                            <div class="file__file-list" id="file-list">${filesList}</div>` : html`
                            <sl-popup
                                    class="file__file-list"
                                    id="file-list"
                                    anchor="visible-button"
                                    ?active=${this.files.length > 0 || Object.values(this.value).length > 0}
                                    strategy="fixed"
                                    placement="bottom-start"
                                    auto-size="vertical"
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