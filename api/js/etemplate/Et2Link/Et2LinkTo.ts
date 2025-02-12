/**
 * EGroupware eTemplate2 - JS Link list object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright 2022 Nathan Gray
 */


import {Et2InputWidget} from "../Et2InputWidget/Et2InputWidget";
import {css, html, LitElement, nothing} from "lit";
import {Et2Button} from "../Et2Button/Et2Button";
import {Et2LinkEntry} from "./Et2LinkEntry";
import {egw} from "../../jsapi/egw_global";
import {LinkInfo} from "./Et2Link";
import {ManualMessage} from "../Validators/ManualMessage";
import {Et2VfsSelectButton} from "../Et2Vfs/Et2VfsSelectButton";
import {Et2LinkPasteDialog, getClipboardFiles} from "./Et2LinkPasteDialog";
import {waitForEvent} from "../Et2Widget/event";
import {classMap} from "lit/directives/class-map.js";
import {Et2VfsSelectDialog} from "../Et2Vfs/Et2VfsSelectDialog";
import {Et2File} from "../Et2File/Et2File";
import type {Et2Tabs} from "../Layout/Et2Tabs/Et2Tabs";

/**
 * Choose an existing entry, VFS file or local file, and link it to the current entry.
 *
 * If there is no "current entry", link information will be stored for submission instead
 * of being directly linked.
 */
export class Et2LinkTo extends Et2InputWidget(LitElement)
{
	static get properties()
	{
		return {
			...super.properties,
			/**
			 * Hide buttons to attach files
			 */
			noFiles: {type: Boolean},
			/**
			 * Limit to just this application - hides app selection
			 */
			onlyApp: {type: String},
			/**
			 * Limit to the listed applications (comma seperated)
			 */
			applicationList: {type: String},

			value: {type: Object}
		}
	}

	static get styles()
	{
		return [
			...super.styles,
			css`
			:host(.can_link) #link_button {
				display: initial;
			}
			#link_button {
				display: none;
			}
			et2-link-entry {
				flex: 1 1 auto;
			}
			.input-group__container {
				flex: 1 1 auto;
			}

				.form-control-input {
				display: flex;
				width: 100%;
				gap: 0.5rem;
			}
			::slotted(.et2_file) {
				width: 30px;
			}
			`
		];
	}

	// Still not sure what this does, but it's important.
	// Seems to be related to rendering and what's available "inside"
	static get scopedElements()
	{
		return {
			// @ts-ignore
			...super.scopedElements,
			'et2-button': Et2Button,
			'et2-link-entry': Et2LinkEntry,
			'et2-vfs-select': Et2VfsSelectButton,
			'et2-link-paste-dialog': Et2LinkPasteDialog
		};
	}

	private get fileUpload() : Et2File { return this.shadowRoot?.querySelector("et2-file");}
	private get pasteButton() : Et2VfsSelectButton { return this.shadowRoot?.querySelector("#paste"); }

	private get pasteDialog() : Et2LinkPasteDialog { return <Et2LinkPasteDialog><unknown>this.pasteButton?.querySelector("et2-link-paste-dialog"); }

	private get vfsDialog() : Et2VfsSelectDialog { return <Et2VfsSelectDialog><unknown>this.shadowRoot.querySelector("#link")?.shadowRoot.querySelector("et2-vfs-select-dialog")}

	constructor()
	{
		super();
		this.noFiles = false;

		this.handleFilesUploaded = this.handleFilesUploaded.bind(this);
		this.handleEntrySelected = this.handleEntrySelected.bind(this);
		this.handleEntryCleared = this.handleEntryCleared.bind(this);
		this.handleLinkButtonClick = this.handleLinkButtonClick.bind(this);
		this.handleVfsSelected = this.handleVfsSelected.bind(this);

		this.handleLinkDeleted = this.handleLinkDeleted.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();
		this.getInstanceManager().DOMContainer.addEventListener("et2-delete", this.handleLinkDeleted);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		this.getInstanceManager().DOMContainer.removeEventListener("et2-delete", this.handleLinkDeleted);
	}

	_inputGroupBeforeTemplate()
	{
		// only set server-side callback, if we have a real application-id (not null or array)
		// otherwise it only gives an error on server-side
		let method = null;
		let method_id = null;
		let pasteEnabled = false;
		let pasteTooltip = ""
		if(this.value && this.value.to_id && typeof this.value.to_id != 'object')
		{
			method = 'EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_existing';
			method_id = this.value.to_app + ':' + this.value.to_id;

			getClipboardFiles().then((files) =>
			{
				if(files.length > 0 && !this.disabled && !this.readonly)
				{
					this.pasteButton.removeAttribute("disabled");
				}
			});
		}

		return html`
            <slot name="before"></slot>
            <et2-file multiple id=${this.id}
                      ?disabled=${this.disabled}
                      ?readonly=${this.readonly}
                      title=${this.egw().lang("File upload")}
                      dropTarget="popupMainDiv"
                      @et2-add=${(e) => {(<Et2Tabs>this.closest("et2-tabbox")).activateTab(this);}}
                      @change=${(e) => {this.handleFilesUploaded(e)}}
            ></et2-file>
            <et2-vfs-select
                    part="vfs button"
                    exportparts="base:button_base"
                    id="link"
                    ?disabled=${this.disabled}
                    ?readonly=${this.readonly}
                    method=${method || nothing}
                    method-id=${method_id || nothing}
                    multiple
                    title=${this.egw().lang("select file(s) from vfs")}
                    .buttonLabel=${this.egw().lang('Link')}
                    @change=${async() =>
                    {
                        this.handleVfsSelected(await this.vfsDialog.getComplete());
                    }}
            >
                <et2-button slot="footer" image="copy" id="copy" style="order:3" noSubmit="true"
                            label=${this.egw().lang("copy")}></et2-button>
                <et2-button slot="footer" image="move" id="move" style="order:3" noSubmit="true" ?disabled=${!method_id}
                            label=${this.egw().lang("move")}></et2-button>
            </et2-vfs-select>
            <et2-vfs-select
                    part="vfs button clipboard"
                    exportparts="base:button_base"
                    id="paste"
                    image="clipboard-data" aria-label=${this.egw().lang("clipboard contents")} noSubmit="true"
                    title=${this.egw().lang("Clipboard contents")}
                    ?readonly=${this.readonly}
                    disabled
                    multiple
                    @click=${async(e) =>
                            {
                                // Pre-select all files
                                let files = [];
                                let cbFiles = await getClipboardFiles();
                                cbFiles.forEach(f => files.push(f.path));
                                e.target.firstElementChild.value = files;
                                e.target.firstElementChild.requestUpdate();

                                waitForEvent(e.target._dialog, "sl-after-show").then(async() =>
                                {
                                    this.handleFilePaste(await this.pasteDialog.getComplete());
                                });
                            }}
            >
                <et2-link-paste-dialog open
                                       title=${this.egw().lang("Clipboard contents")}
                                       .buttonLabel=${this.egw().lang("link")}
                >
                    <et2-button slot="footer" image="copy" id="copy" style="order:3" noSubmit="true"
                                ?disabled=${!this.value?.to_id}
                                label=${this.egw().lang("copy")}
                                title=${this.egw().lang("Copy selected files")}
                    ></et2-button>
                    <et2-button slot="footer" image="move" id="move" style="order:3" noSubmit="true"
                                ?disabled=${!this.value?.to_id}
                                label=${this.egw().lang("move")}
                                title=${this.egw().lang("Move selected files")}
                    ></et2-button>
                </et2-link-paste-dialog>
            </et2-vfs-select>
		`;
	}

	/**
	 * @return {TemplateResult}
	 * @protected
	 */
	_inputGroupInputTemplate()
	{
		return html`
            <et2-link-entry .onlyApp="${this.onlyApp}"
                            .applicationList="${this.applicationList}"
                            .readonly=${this.readonly}
                            ?disabled=${this.disabled}
                            @sl-change=${this.handleEntrySelected}
                            @sl-clear="${this.handleEntryCleared}">
            </et2-link-entry>
            <et2-button id="link_button" label="Link" class="link" .noSubmit=${true}
                        @click=${this.handleLinkButtonClick}>
            </et2-button>
		`;
	}

	/**
	 * Create links
	 *
	 * Using current value for one end of the link, create links to the provided files or entries
	 *
	 * @param _links
	 */
	createLink(_links : LinkInfo[])
	{
		let links : LinkInfo[];
		if(typeof _links == 'undefined')
		{
			links = [];
		}
		else
		{
			links = _links;
		}

		// If no link array was passed in, don't make the ajax call
		if(links.length > 0)
		{
			egw.request("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link",
				[this.value.to_app, this.value.to_id, links]).then((result) => this._link_result(result))

		}
	}

	/**
	 * Sent some links, server has a result
	 *
	 * @param {Object} success
	 */
	_link_result(success)
	{
		if(success)
		{
			// Show some kind of success...

			// Reset
			this.resetAfterLink();

			// Server says it's OK, but didn't store - we'll send this again on submit
			// This happens if you link to something before it's saved to the DB
			if(typeof success == "object")
			{
				// Save as appropriate in value
				if(typeof this.value != "object")
				{
					this.value = {};
				}
				this.value.to_id = success;

				for(let link in success)
				{
					// Thumbnail might already be there
					if(typeof success[link]['id']?.src == "string")
					{
						success[link]['src'] = success[link]['id']?.src;
					}

					// Icon should be in registry
					if(typeof success[link].icon == 'undefined')
					{
						success[link].icon = egw.link_get_registry(success[link].app, 'icon');
						// No icon, try by mime type - different place for un-saved entries
						if(success[link].icon == false && success[link].id.type)
						{
							// Triggers icon by mime type, not thumbnail or app
							success[link].type = success[link].id.type;
							success[link].icon = true;
						}
					}
					// Special handling for file - if not existing, we can't ask for title
					if(success[link].app == 'file' && typeof success[link].title == 'undefined')
					{
						success[link].title = success[link].id.name || '';
					}
				}
			}

			// Send an event so listeners can update
			this.dispatchEvent(new CustomEvent("et2-change", {
				bubbles: true,
				detail: typeof success == "object" ? Object.values(success) : []
			}));
		}
		else
		{
			this.validators.push(new ManualMessage(this.egw().lang("failed")));
		}
		this.dispatchEvent(new CustomEvent('link.et2_link_to', {bubbles: true, detail: success}));
	}

	/**
	 * A link was attempted.  Reset internal values to get ready for the next one.
	 */
	resetAfterLink()
	{
		// Hide link button again
		this.classList.remove("can_link");
		this.link_button.image = "";

		// Clear internal
		delete this.value.app;
		delete this.value.id;

		// Clear file upload
		this.fileUpload.value = {};
		this.fileUpload.requestUpdate("value");

		// Clear link entry
		this.select.value = {app: this.select.app, id: ""};
		this.select._searchNode.clearSearch();
		this.select._searchNode.select_options = [];
	}

	/**
	 * Files have been uploaded (successfully), ready to link
	 *
	 * @param event
	 * @protected
	 */
	handleFilesUploaded(event)
	{
		this.classList.add("can_link");

		let links = [];

		// Get files from file upload widget
		let files = this.fileUpload.value;
		for(let file in files)
		{
			links.push({
				app: 'file',
				id: file,
				name: files[file].name,
				type: files[file].type,
				src: files[file].src
			});
		}
		if(links.length)
		{
			this.createLink(links);
		}
	}

	/**
	 * An entry has been selected, ready to link
	 *
	 */
	handleEntrySelected(event)
	{
		// Could be the app, could be they selected an entry
		if(event.target == this.select && (
			typeof this.select.value == "string" && this.select.value ||
			typeof this.select.value == "object" && this.select.value.id
		))
		{
			this.classList.add("can_link");
			this.link_button.focus();
		}
	}

	/**
	 * An entry was selected, but instead of clicking "Link", the user cleared the selection
	 */
	handleEntryCleared(event)
	{
		this.classList.remove("can_link");
	}

	handleLinkButtonClick(event : MouseEvent)
	{
		this.link_button.image = "loading";
		let link_info : LinkInfo[] = [];
		if(this.select.value)
		{
			let selected = this.select.value;
			// Extra complicated because LinkEntry doesn't always return a LinkInfo
			if(this.onlyApp)
			{
				selected = <LinkInfo>{app: this.onlyApp, id: selected};
			}
			link_info.push(<LinkInfo>selected);
		}
		this.createLink(link_info)
	}

	/**
	 * Handle a link being removed
	 *
	 * Event is thrown every time a link is removed (from a LinkList) but we only care if the
	 * entry hasn't been saved yet and has no ID.  In this case we've been keeping the list
	 * to submit and link server-side so we have to remove the deleted link from our list.
	 *
	 * @param {CustomEvent} e
	 */
	handleLinkDeleted(e : CustomEvent)
	{
		if(e && e.detail && this.value && typeof this.value.to_id == "object")
		{
			delete this.value.to_id[e.detail.link_id || ""]
		}
	}

	handleFilePaste([button, selected])
	{
		let fileInfo = []
		selected.forEach(file =>
		{
			const info = this.pasteDialog.fileInfo(file);
			fileInfo.push(info);
		})
		this.handleVfsFile(button, fileInfo);
		this.pasteButton.value = [];
	}

	handleVfsSelected([button, selected])
	{
		let fileInfo = []
		selected.forEach(file =>
		{
			let info = {
				...this.vfsDialog.fileInfo(file)
			}

			if(!this.value.to_id || typeof this.value.to_id == 'object')
			{
				info['app'] = button == "copy" ? "file" : "link";
				info['path'] = button == "copy" ? "vfs://default" + info.path : info.path;
			}
			fileInfo.push(info);
		})
		this.handleVfsFile(button, fileInfo);
	}

	protected handleVfsFile(button, selectedFileInfo)
	{
		if(!button)
		{
			return;
		}
		let values = true;
		// If entry not yet saved, store for linking on server
		if(!this.value.to_id || typeof this.value.to_id == 'object')
		{
			values = this.value.to_id || {};
			selectedFileInfo.forEach(info =>
			{
				debugger;
				values['link:' + info.path] = {
					app: info?.app,
					id: info.path ?? info.id,
					type: 'unknown',
					icon: 'link',
					remark: '',
					title: info.path
				};
			});
		}
		else
		{
			// Send to server to link
			const files = [];
			const links = [];
			selectedFileInfo.forEach(info =>
			{
				switch(info?.app)
				{
					case "filemanager":
						files.push(info.path);
						break;
					default:
						links.push({app: info.app, id: info.id});
				}
			});
			if(files.length > 0)
			{
				const file_method = 'EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_existing';
				const methodId = this.value.to_app + ':' + this.value.to_id;
				this.egw().request(
					file_method,
					[methodId, files, button]
				);
			}
			if(links.length > 0)
			{
				this.createLink(links);
			}
		}
		this._link_result(values);
	}

	get link_button() : Et2Button
	{
		return this.shadowRoot.querySelector("#link_button");
	}

	get select() : Et2LinkEntry
	{
		return this.shadowRoot.querySelector("et2-link-entry");
	}

	/**
	 * Types of validation supported by this FormControl (for instance 'error'|'warning'|'info')
	 *
	 * @type {ValidationType[]}
	 */
	static get validationTypes() : ValidationType[]
	{
		return ['error', 'success'];
	}


	render()
	{
		const labelTemplate = this._labelTemplate();
		const helpTemplate = this._helpTextTemplate();

		return html`
            <div
                    part="form-control"
                    class=${classMap({
                        'form-control': true,
                        'form-control--medium': true,
                        'form-control--has-label': labelTemplate !== nothing,
                        'form-control--has-help-text': helpTemplate !== nothing
                    })}
            >
                ${labelTemplate}
                <div part="form-control-input" class="form-control-input" @sl-change=${() =>
                {
                    this.dispatchEvent(new Event("change", {bubbles: true}));
                }}>
                    ${this._inputGroupBeforeTemplate()}
                    ${this._inputGroupInputTemplate()}
                </div>
                ${helpTemplate}
            </div>
		`;
	}
}

// @ts-ignore TypeScript is not recognizing that this widget is a LitElement
customElements.define("et2-link-to", Et2LinkTo);