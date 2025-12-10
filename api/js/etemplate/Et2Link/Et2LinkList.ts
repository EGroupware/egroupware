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


import {css, html, nothing, TemplateResult} from "lit";
import {repeat} from "lit/directives/repeat.js";
import {LinkInfo} from "./Et2Link";
import {egw} from "../../jsapi/egw_global";
import {Et2LinkString} from "./Et2LinkString";
import {egwMenu} from "../../egw_action/egw_menu";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {loadWebComponent} from "../Et2Widget/Et2Widget";
import {Et2VfsSelectButton} from "../Et2Vfs/Et2VfsSelectButton";

/**
 * Display a list of entries in a comma separated list
 *
 * Given an application & entry ID, will query the list of links and display
 *
 * @see Et2Link
 *
 * To make things easy and consistent for ExposeMixin, we don't want children in the shadow dom, so they are slotted
 * in.  When rendering we generate a slot for each link, then let browser slot them in using the slot name.
 *
 * Templates:
 * _listTemplate - generates the list
 * 	_rowTemplate - creates the slots
 * 	_linkTemplate - generates the content _inside_ each slot
 * 		_thumbnailTemplate - generates the thumbnail image
 */

// @ts-ignore TypeScript says there's something wrong with types
export class Et2LinkList extends Et2LinkString
{

	static get styles()
	{
		return [
			...super.styles,
			css`
				:host {
					display: flex;
					flex-direction: column;
					column-gap: 10px;
					overflow: hidden;
				}

				div {
					display: flex;
					gap: 10px;
				}

				div:hover {
					background-color: var(--highlight-background-color);
				}

				div.zip_highlight {
					animation-name: new_entry_pulse, new_entry_clear;
					animation-duration: 5s;
					animation-delay: 0s, 30s;
					animation-fill-mode: forwards;
				}

				/* CSS for child elements */

				et2-link::part(title):after {
					/* Reset from Et2LinkString */
					content: initial;
				}

				et2-link::part(icon) {
					width: 1rem;
					display: inline-block;
				}

				et2-link::part(remark) {
					/* Reset from Et2LinkString */
					display: initial;
					/*edit windows link tabs highlight comments*/
					margin-left:auto;
					font-weight: bold;
					font-style: italic;
					color: var(--sl-color-gray-700)
				}

				et2-link {
					display: block;
					flex: 1 1 auto;
				}

				et2-link:hover {
					text-decoration: none;
				}

				et2-link::part(base) {
					display: flex;
				}

				.remark {
					flex: 1 1 auto;
					width: 20%;
				}

				div et2-image[part=delete-button] {
					visibility: hidden;
					width: 16px;
					order: 5;
					cursor: pointer;
				}

				div:hover et2-image[part=delete-button] {
					visibility: initial;
				}
			`
		];
	}


	static get properties()
	{
		return {
			...super.properties,

			// JS code which is executed when the links change
			onchange: {type: Function},
			// Does NOT allow user to enter data, just displays existing data
			// Disables delete, etc.
			readonly: {type: Boolean}
		}
	}

	private context : egwMenu;

	constructor()
	{
		super();
		this.readonly = false;

		this._handleRowContext = this._handleRowContext.bind(this);

		this._handleChange = this._handleChange.bind(this);
		this._handleLinkToChange = this._handleLinkToChange.bind(this);
	}

	connectedCallback()
	{
		super.connectedCallback();

		// Look for LinkTo and listen for change so we can update
		if(this.getInstanceManager())
		{
			this.getInstanceManager().DOMContainer.querySelectorAll("et2-link-to").forEach(link =>
			{
				link.addEventListener("et2-change", this._handleLinkToChange);
			})
		}

		this.addEventListener("change", this._handleChange);
	}

	disconnectedCallback()
	{
		super.disconnectedCallback();
		if(this.getInstanceManager())
		{
			this.getInstanceManager().DOMContainer.querySelectorAll("et2-link-to").forEach(link =>
			{
				link.removeEventListener("et2-change", this._handleLinkToChange);
			})
		}
		this.removeEventListener("change", this._handleChange);
	}

	protected _listTemplate()
	{
		return html`
            ${repeat(this._link_list,
                    (link) => link.app + ":" + link.id,
                    (link) => this._rowTemplate(link))
            }
		`;
	}

	protected async moreResultsTemplate()
	{
		if(this._totalResults <= 0 || !this._loadingPromise)
		{
			return nothing;
		}
		return this._loadingPromise.then(() =>
		{
			const moreCount = this._totalResults - this._link_list.length;
			const more = this.egw().lang("%1 more...", moreCount);
			return html`${moreCount > 0 ? html`
                <et2-button image="box-arrow-down" label="${more}" noSubmit="true"
                            ._parent=${this}
                            @click="${(e) =>
                            {
                                // Change icon for some feedback
                                e.target.querySelectorAll("[slot=prefix]").forEach(n => n.remove());
                                e.target.append(Object.assign(document.createElement("sl-spinner"), {slot: "prefix"}));

                                // Get the next batch
                                const start = this._link_list.filter(l => l.app !== "file").length;
                                this.get_links([], start);
                            }}"
                ></et2-button>` : nothing}`;
		});
	}

	/**
	 * Render a list of links inside the list
	 * These get put inside the shadow dom rather than slotted
	 *
	 * @param links
	 * @protected
	 */
	protected _addLinks(links : LinkInfo[])
	{
		this._link_list = links;
		this.requestUpdate();
		this.updateComplete.then(() => super._addLinks(links));
	}

	/**
	 * Render one link
	 * These elements are slotted and are found in the light DOM (use this.querySelector(...) to find them)
	 *
	 * @param link
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _linkTemplate(link) : TemplateResult
	{
		const id = typeof link.id === "string" ? link.id : link.link_id;
		return html`
            <et2-link app="${link.app}" entryId="${id}" statustext="${link.title}"
                      ._parent=${this}
                      .value=${link}></et2-link>
            ${this._deleteButtonTemplate(link)}
		`;
	}

	/**
	 * Render the row for one link.
	 * This is just the structure and slot, actual row contents are done in _linkTemplate.
	 * These rows are found in the shadowRoot.  Use this.shadowRoot.querySelector(...) to find them.
	 *
	 * @param link
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _rowTemplate(link) : TemplateResult
	{
		return html`
            <div id="${this._get_row_id(link)}"
                 @contextmenu=${this._handleRowContext}>
                ${this._linkTemplate(link)}
            </div>`;
	}

	/**
	 * Handle & pass on an internal change
	 * @param {ChangeEvent} _event
	 * @protected
	 */
	protected _handleChange(_event : Event)
	{
		if(!this.onchange)
		{
			return;
		}
		this.onchange(this, _event.data, _event)
	}

	/**
	 * We listen to LinkTo widgets so we can update
	 *
	 * @param _ev
	 * @protected
	 */
	protected _handleLinkToChange(_ev)
	{
		if(_ev && typeof _ev.currentTarget)
		{
			// Add in new links from LinkTo
			for(let link of <LinkInfo[]>Object.values(_ev.detail || []))
			{
				if(!this._link_list.some(l => l.app == link.app && (l.id == link.id ||
					// Unsaved
					typeof link.id == "object" && typeof l.id == "object" && link.id.id == l.id.id
				)))
				{
					this._link_list.unshift(link);
				}
			}
			// No need to ask server if we got it in the event
			if(_ev.detail.length)
			{
				this.requestUpdate();
			}
			else
			{
				// Event didn't have it, need to ask
				this.get_links();
			}
		}
	}


	/**
	 * Build the delete button
	 *
	 * @param {LinkInfo} link
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _deleteButtonTemplate(link : LinkInfo) : TemplateResult
	{
		if(this.readonly)
		{
			return html``;
		}
		return html`
            <et2-image class="delete_button" slot="${this._get_row_id(link)}" src="delete"
                       part="delete-button"
                       ._parent=${this}
                       .onclick=${() =>
                       {
                           this._delete_link(link);
                       }}
                       aria-label="${this.egw().lang(link.app === "file" ? "Delete" : "Unlink")}"
            >
            </et2-image>`;
	}

	/**
	 * Get an ID for a link
	 * @param {LinkInfo} link
	 * @protected
	 */
	protected _get_row_id(link : any) : string
	{
		return "link_" + (link.dom_id ? link.dom_id : (typeof link.link_id == "string" ? link.link_id.replace(/[:.]/g, '_') : link.link_id || link.id));
	}


	/**
	 * Delete a link
	 * @protected
	 */
	protected _delete_link(link : LinkInfo)
	{
		let link_element = <HTMLElement>this.shadowRoot.querySelector("[id='" + this._get_row_id(link) + "']");
		link_element.classList.add("loading");

		this.dispatchEvent(new CustomEvent("et2-before-delete", {detail: link}));

		let removeLink = () =>
		{
			this.shadowRoot.querySelectorAll("[id='" + this._get_row_id(link) + "']").forEach(e => e.remove());
			if(this._link_list.indexOf(link) != -1)
			{
				this._link_list.splice(this._link_list.indexOf(link), 1);
				this._totalResults--;
			}
			this.requestUpdate();
			this.updateComplete.then(() =>
			{
				this.dispatchEvent(new CustomEvent("et2-delete", {bubbles: true, detail: link}));
				let change = new Event("change", {bubbles: true});
				change['data'] = link;
				this.dispatchEvent(change);
			})
		};

		// Unsaved entry, had no ID yet
		if(!this.entryId || typeof this.entryId !== "string" && this.entryId[link.link_id])
		{
			if(this.entryId)
			{
				delete this.entryId[link.link_id];
			}
			removeLink();
		}
		else if(typeof this.entryId == "string" && link.link_id)
		{
			egw.json("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_delete", [link.link_id]).sendRequest()
				.then((data) =>
				{
					if(data)
					{
						removeLink();
					}
				});
		}
	}

	/**
	 * Handle values passed as an array
	 *
	 * @param _value
	 */
	set value(_value : { to_app : string, to_id : string } | LinkInfo[])
	{
		this._link_list = [];

		// Handle case where server passed a list of links that aren't ready yet
		if(_value && typeof _value == "object")
		{
			let list = [];
			if(!Array.isArray(_value) && _value.to_id && typeof _value.to_id == "object")
			{
				list = _value.to_id;
			}
			else if(Array.isArray(_value) && _value.length)
			{
				list = _value;
			}
			if(list.length > 0)
			{
				for(let id in list)
				{
					let link = list[id];
					if(!link.app && typeof list.splice !== "undefined")
					{
						list.splice(parseInt(id), 1);
						continue;
					}
					else if(link.app)
					{
						// Temp IDs can cause problems since the ID includes the file name or :
						if(link.link_id && typeof link.link_id != 'number')
						{
							link.dom_id = 'temp_' + egw.uid();
						}
						// Icon should be in registry
						if(!link.icon)
						{
							link.icon = egw.link_get_registry(link.app, 'icon');
							// No icon, try by mime type - different place for un-saved entries
							if(link.icon == false && link.id.type)
							{
								// Triggers icon by mime type, not thumbnail or app
								link.type = link.id.type;
								link.icon = true;
							}
						}
						// Special handling for file - if not existing, we can't ask for title
						if(typeof link.id == 'object' && !link.title)
						{
							link.title = link.id.name || '';
						}
					}
				}
				this._addLinks(list);
			}
			else
			{
				super.set_value(_value);
			}
		}
	}

	protected _createContextMenu()
	{
		// Set up context menu
		this.context = new egwMenu();
		this.context.addItem("comment", this.egw().lang("Comment"), "", () =>
		{
			Et2Dialog.show_prompt(
				(button, comment) =>
				{
					if(button != Et2Dialog.OK_BUTTON)
					{
						return;
					}
					this._set_comment(this.context.data, comment);
				},
				'', this.egw().lang("Comment"), this.context.data.remark || ''
			);

		});
		this.context.addItem("file_info", this.egw().lang("File information"), this.egw().image("edit"), () =>
		{
			let link_data = this.context.data;
			if(link_data.app == 'file')
			{
				// File info is always the same
				let url = '/apps/' + link_data.app2 + '/' + link_data.id2 + '/' + decodeURIComponent(link_data.id);
				if(typeof url == 'string' && url.indexOf('webdav.php'))
				{
					// URL is url to file in webdav, so get rid of that part
					url = url.replace('/webdav.php', '');
				}
				this.egw().open(url, "filemanager", "edit");
			}
		});
		this.context.addItem("-", "-");
		this.context.addItem("save", this.egw().lang("Save as"), this.egw().image('save'), () =>
		{
			let link_data = this.context.data;
			// Download file
			if(link_data.download_url)
			{
				let url = link_data.download_url;
				if(url[0] == '/')
				{
					url = egw.link(url);
				}

				let a = document.createElement('a');
				if(typeof a.download == "undefined")
				{
					window.location.href = url + "?download";
					return false;
				}

				// Multiple file download for those that support it
				a.setAttribute("href",url);
				a.setAttribute("download",link_data.title || "");
				this.getInstanceManager().DOMContainer.appendChild(a);

				let evt = document.createEvent('MouseEvent');
				evt.initMouseEvent('click', true, true, window, 1, 0, 0, 0, 0, false, false, false, false, 0, null);
				a.dispatchEvent(evt);
				a.remove();
				return false;
			}

			this.egw().open(link_data, "", "view", 'download', link_data.target ? link_data.target : link_data.app, link_data.app);
		});
		this.context.addItem("zip", this.egw().lang("Save as Zip"), this.egw().image('save_zip'), () =>
		{
			// Highlight files for nice UI indicating what will be in the zip.
			// Files have negative IDs.
			this.shadowRoot.querySelectorAll('div[id^="link_-"]').forEach((row) => row.classList.add("zip_highlight"));

			// Download ZIP
			window.location.href = this.egw().link('/index.php', {
				menuaction: 'api.EGroupware\\Api\\Etemplate\\Widget\\Link.download_zip',
				app: this.application,
				id: this.entryId
			});
		});

		// Only allow this option if the entry has been saved, and has a real ID
		if(this.to_id && typeof this.to_id != 'object' || this.entryId && this.application)
		{
			this.context.addItem("copy_to", this.egw().lang("Copy to"), this.egw().image('copy'), () =>
			{
				// Highlight files for nice UI indicating what will be copied
				this.shadowRoot.querySelectorAll('div[id^="link_-"]').forEach((row) => row.classList.add("zip_highlight"));

				// Get target
				let select_attrs : any = {
					mode: "select-dir",
					image: 'copy',
					buttonLabel: egw.lang("copy"),
					//extra_buttons: [{text: egw.lang("link"),	id:"link", image: "link"}],
					dialog_title: egw.lang('Copy to'),
					method: "EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_copy_to",
				};
				let vfs_select = <Et2VfsSelectButton>loadWebComponent("et2-vfs-select", select_attrs, this);
				vfs_select.methodId = this.context.data;
				document.body.append(vfs_select);

				// No button, just open it
				vfs_select.click();
				vfs_select.addEventListener("change", (e) => {vfs_select.remove()});
			});
		}
		this.context.addItem("-", "-");
		this.context.addItem("delete", this.egw().lang("Delete link"), this.egw().image("delete"), () =>
		{
			Et2Dialog.show_dialog(
				(button) =>
				{
					if(button == Et2Dialog.YES_BUTTON)
					{
						this._delete_link(this.context.data);
					}
				},
				egw.lang('Delete link?')
			);
		});

	}

	protected _handleRowContext(_ev)
	{
		// Do not trigger expose view if one of the operator keys are held
		if(this.readonly || _ev.altKey || _ev.ctrlKey || _ev.shiftKey || _ev.metaKey)
		{
			return;
		}

		if(!this.context)
		{
			this._createContextMenu();
		}
		// Find the link
		let link = _ev.currentTarget.querySelector("et2-link");

		let _link_data = Object.assign({app: link.app, id: link.entryId}, link.dataset);
		// Comment only available if link_id is there and not readonly
		this.context.getItem("comment").set_enabled(typeof _link_data.link_id != 'undefined' && !this.readonly);
		// File info only available for existing files
		this.context.getItem("file_info").set_enabled(typeof _link_data.id != 'object' && _link_data.app == 'file');
		this.context.getItem("save").set_enabled(typeof _link_data.id != 'object' && _link_data.app == 'file');
		// Zip download only offered if there are at least 2 files
		this.context.getItem("zip").set_enabled(this._link_list.length >= 2);
		// Show delete item only if the widget is not readonly
		this.context.getItem("delete").set_enabled(!this.readonly);
		this.context.getItem("delete").caption = _link_data.app === "file" ? this.egw().lang("Delete file") : this.egw().lang("Delete link");
		this.context.data = _link_data;
		this.context.instance?.requestUpdate();
		this.context.showAt(_ev.pageX, _ev.pageY, true);
		_ev.preventDefault();

	}

	protected _set_comment(link, comment)
	{
		let remark = this.shadowRoot.querySelector("#" + this._get_row_id(link) + " et2-link");
		if(!remark)
		{
			console.warn("Could not find link to comment on", link);
			return;
		}
		/* // TODO
		if(isNaN(link.link_id))	// new entry, not yet stored
		{
			remark.text(comment);
			// Look for a link-to with the same ID, refresh it

			if(link.link_id)
			{
				var _widget = link_id.widget || null;
				self.getRoot().iterateOver(
					function(widget)
					{
						if(widget.id == self.id)
						{
							_widget = widget;
						}
					},
					self, et2_link_to
				);
				var value = _widget != null ? _widget.getValue() : false;
				if(_widget && value && value.to_id)
				{
					value.to_id[self.context.data.link_id].remark = comment;
				}
			}
			return;
		}

		 */
		remark.classList.add("loading");
		egw.json("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_comment",
			[link.link_id, comment]).sendRequest()
			.then(() =>
			{
				if(remark)
				{
					// Append "" to make sure it's a string, not undefined
					remark.classList.remove("loading");
					// Update internal data
					link.remark = comment + "";
					// Update link widget
					remark.value = link;
				}
			});
	}
}

// @ts-ignore TypeScript says there's something wrong with types
customElements.define("et2-link-list", Et2LinkList);