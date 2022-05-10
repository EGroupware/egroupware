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


import {css, html, repeat, TemplateResult} from "@lion/core";
import {Et2Link, LinkInfo} from "./Et2Link";
import {egw} from "../../jsapi/egw_global";
import {Et2LinkString} from "./Et2LinkString";
import {egwMenu} from "../../egw_action/egw_menu";
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import {et2_vfsSelect} from "../et2_widget_vfs";
import {et2_createWidget} from "../et2_core_widget";

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
				display:flex;
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
			
			/* CSS for child elements */
            ::slotted(*):after {
            	/* Reset from Et2LinkString */
            	content: initial;
            }
            ::slotted(et2-vfs-mime), ::slotted(et2-image-expose) {
            	width: 16px;
            }
            ::slotted(et2-link) {
            	flex: 1 1 auto;
            }
            ::slotted(.delete_button) {
            	display: none;
            	width: 16px;
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

	constructor()
	{
		super();
		this.readonly = false;

		this._handleRowHover = this._handleRowHover.bind(this);
		this._handleRowContext = this._handleRowContext.bind(this);
		this.addEventListener("mouseover", this._handleRowHover);
		this.addEventListener("mouseout", this._handleRowHover);
	}

	connectedCallback()
	{
		super.connectedCallback();
		this._createContextMenu();
	}

	protected _listTemplate()
	{
		return html`
            ${repeat(this._link_list,
                    (link) => link.app + ":" + link.id,
                    (link) => this._rowTemplate(link))
            }`;
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
		super._addLinks(links);
		this.requestUpdate();

	}

	/**
	 * Render one link
	 *
	 * @param link
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _linkTemplate(link) : TemplateResult
	{
		return html`
            ${this._thumbnailTemplate(link)}
            <et2-link slot="${this._get_row_id(link)}" app="${link.app}" entry_id="${link.id}"
                      @contextmenu=${this._handleRowContext}
                      .value=${link}></et2-link>
            ${this._deleteButtonTemplate(link)}
		`;
	}

	/**
	 * Render one link
	 *
	 * @param link
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _rowTemplate(link) : TemplateResult
	{
		let hover = () =>
		{
			console.log(link);
			debugger;
		}
		return html`
            <div id="${this._get_row_id(link)}">
                <slot name="${this._get_row_id(link)}"></slot>
            </div>`;
	}

	/**
	 * Handle show/hide delete button
	 * @param _ev
	 * @protected
	 */
	protected _handleRowHover(_ev)
	{
		// Ignore delete button
		if(_ev.relatedTarget.classList.contains("delete_button") || _ev.relatedTarget.parentElement.classList.contains("delete_button"))
		{
			return;
		}
		if(_ev.type == "mouseout")
		{
			this.querySelectorAll(".delete_button").forEach(b => b.style.display = "");
		}

		if(_ev.type == "mouseover" && _ev.target.parentNode == this)
		{
			_ev.target.parentNode.querySelector(".delete_button[slot='" + _ev.target.slot + "']").style.display = "initial";
		}
	}

	/**
	 * Build a thumbnail for the link
	 * @param link
	 * @returns {TemplateResult}
	 * @protected
	 */
	protected _thumbnailTemplate(link) : TemplateResult
	{
		// If we have a mimetype, use a Et2VfsMime
		// Files have path set in 'icon' property, and mime in 'type'
		if(link.type && link.icon)
		{
			return html`
                <et2-vfs-mime slot="${this._get_row_id(link)}" ._parent=${this} .value=${Object.assign({
                    name: link.title,
                    mime: link.type,
                    path: link.icon
                }, link)}></et2-vfs-mime>`;
		}
		return html`
            <et2-image-expose
                    slot="${this._get_row_id(link)}" ._parent=${this}
                    href="${link.href}"
                    src=${this.egw().image(link.icon)}></et2-image-expose>`;
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
            <et2-image class="delete_button" slot="${this._get_row_id(link)}" src="delete" ._parent=${this}
                       .onclick=${() =>
                       {
                           this._delete_link(link);
                       }}
                       aria-label="${this.egw().lang("Delete")}"
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
		return "link_" + (link.dom_id ? link.dom_id : (typeof link.link_id == "string" ? link.link_id.replace(/[:\.]/g, '_') : link.link_id || link.id));
	}


	/**
	 * Delete a link
	 * @protected
	 */
	protected _delete_link(link : LinkInfo)
	{
		let link_element = <HTMLElement>this.querySelector("et2-link[slot='" + this._get_row_id(link) + "']");
		link_element.classList.add("loading");

		this.dispatchEvent(new CustomEvent("before_delete", {detail: link}));
		egw.json("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_delete", [link.link_id]).sendRequest()
			.then((data) =>
			{
				if(data)
				{
					this.querySelectorAll("[slot='" + this._get_row_id(link) + "']").forEach(e => e.remove());
				}
			});

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
					if(link.app)
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
						(<LinkInfo[]>this._link_list).push(<LinkInfo>link);
					}
				}
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
			let link_id = typeof this.context.data.link_id == 'number' ? this.context.data.link_id : this.context.data.link_id.replace(/[:\.]/g, '_');

			Et2Dialog.show_prompt(
				function(button, comment)
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
		this.context.addItem("file_info", this.egw().lang("File information"), this.egw().image("edit"), (menu_item) =>
		{
			var link_data = this.context.data;
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
		this.context.addItem("save", this.egw().lang("Save as"), this.egw().image('save'), (menu_item) =>
		{
			var link_data = this.context.data;
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
				a = jQuery(a)
					.prop('href', url)
					.prop('download', link_data.title || "")
					.appendTo(this.getInstanceManager().DOMContainer);

				var evt = document.createEvent('MouseEvent');
				evt.initMouseEvent('click', true, true, window, 1, 0, 0, 0, 0, false, false, false, false, 0, null);
				a[0].dispatchEvent(evt);
				a.remove();
				return false;
			}

			this.egw().open(link_data, "", "view", 'download', link_data.target ? link_data.target : link_data.app, link_data.app);
		});
		this.context.addItem("zip", this.egw().lang("Save as Zip"), this.egw().image('save_zip'), (menu_item) =>
		{
			// Highlight files for nice UI indicating what will be in the zip.
			// Files have negative IDs.
			jQuery('[id^="link_-"]', this.list).effect('highlight', {}, 2000);

			// Download ZIP
			window.location = this.egw().link('/index.php', {
				menuaction: 'api.EGroupware\\Api\\Etemplate\\Widget\\Link.download_zip',
				app: this.to_app,
				id: this.to_id
			});
		});

		// Only allow this option if the entry has been saved, and has a real ID
		if(this.to_id && typeof this.to_id != 'object')
		{
			this.context.addItem("copy_to", this.egw().lang("Copy to"), this.egw().image('copy'), (menu_item) =>
			{
				// Highlight files for nice UI indicating what will be copied
				jQuery('[id="link_' + this.context.data.link_id + ']', this.list).effect('highlight', {}, 2000);

				// Get target
				var select_attrs : any = {
					mode: "select-dir",
					button_caption: '',
					button_icon: 'copy',
					button_label: egw.lang("copy"),
					//extra_buttons: [{text: egw.lang("link"),	id:"link", image: "link"}],
					dialog_title: egw.lang('Copy to'),
					method: "EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_copy_to",
					method_id: this.context.data
				};
				let vfs_select = <et2_vfsSelect>et2_createWidget("vfs-select", select_attrs, self);

				// No button, just open it
				vfs_select.button.hide();
				vfs_select.click(null);
			});
		}
		this.context.addItem("-", "-");
		this.context.addItem("delete", this.egw().lang("Delete link"), this.egw().image("delete"), (menu_item) =>
		{
			var link_id = isNaN(this.context.data.link_id) ? this.context.data : this.context.data.link_id;
			var row = jQuery('#link_' + (this.context.data.dom_id ? this.context.data.dom_id : this.context.data.link_id), this);
			Et2Dialog.show_dialog(
				function(button)
				{
					if(button == Et2Dialog.YES_BUTTON)
					{
						this._delete_link(link_id, row);
					}
				},
				egw.lang('Delete link?')
			);
		});

	}

	protected _handleRowContext(_ev)
	{
		let _link_data = Object.assign({app: _ev.target.app, id: _ev.target.id}, _ev.target.dataset);
		// Comment only available if link_id is there and not readonly
		this.context.getItem("comment").set_enabled(typeof _link_data.link_id != 'undefined' && !this.readonly);
		// File info only available for existing files
		this.context.getItem("file_info").set_enabled(typeof _link_data.id != 'object' && _link_data.app == 'file');
		this.context.getItem("save").set_enabled(typeof _link_data.id != 'object' && _link_data.app == 'file');
		// Zip download only offered if there are at least 2 files
		this.context.getItem("zip").set_enabled(this._link_list.length >= 2);
		// Show delete item only if the widget is not readonly
		this.context.getItem("delete").set_enabled(!this.readonly);

		this.context.data = _link_data;
		this.context.showAt(_ev.pageX, _ev.pageY, true);
		_ev.preventDefault();

	}

	protected _set_comment(link, comment)
	{
		let remark = this.querySelector(".comment[slot='" + this._get_row_id(link) + "']");
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
		remark.addClass("loading");
		egw.json("EGroupware\\Api\\Etemplate\\Widget\\Link::ajax_link_comment",
			[link.link_id, comment]).sendRequest()
			.then(() =>
			{
				if(remark)
				{
					// Append "" to make sure it's a string, not undefined
					remark.removeClass("loading").text(comment + "");
					// Update internal data
					remark.textContent = comment + "";
				}
			});
	}
}

// @ts-ignore TypeScript says there's something wrong with types
customElements.define("et2-link-list", Et2LinkList);