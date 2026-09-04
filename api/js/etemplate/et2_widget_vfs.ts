/**
 * EGroupware eTemplate2 - JS VFS widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link https://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 */

import {et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_file} from "./et2_widget_file";
import {egw} from "../jsapi/egw_global";
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";
import {Et2VfsPath} from "./Et2Vfs/Et2VfsPath";
import type {Et2VfsMime} from "./Vfs/Et2VfsMime";
import type {Et2VfsGid, Et2VfsUid} from "./Et2Vfs/Et2VfsUid";
import {Et2VfsName, Et2VfsNameReadonly} from "./Et2Vfs/Et2VfsName";

/**
* @deprecated use Et2VfsName
*/
export type et2_vfsName = Et2VfsName;

/**
 * @deprecated use Et2VfsName_ro
 */
export type et2_vfsName_ro = Et2VfsNameReadonly;

/**
 * vfs-mime: icon for mimetype of file, or thumbnail
 * incl. optional link overlay icon, if file is a symlink
 *
 * Creates following structure
 * <span class="iconOverlayContainer">
 *   <img class="et2_vfs vfsMimeIcon" src="..."/>
 *   <span class="overlayContainer">
 *      <img class="overlay" src="etemplate/templates/default/images/link.png"/>
 *   </span>
 * </span>
 *
 * span.overlayContainer is optional and only generated for symlinks
 * @augments et2_valueWidget
 * @deprecated use Et2VfsMime
*/
export type et2_vfsMime = Et2VfsMime;

/**
* vfs-uid / vfs-gid: Displays the name for an ID.
* Same as read-only selectAccount, except if there's no user it shows "root"
*
* @deprecated use Et2VfsUid
*/
export type et2_vfsUid = Et2VfsUid;
/**
 * @deprecated use Et2VfsGid
 */
export type et2_vfsGid = Et2VfsGid;

/**
 * @deprecated use Et2VfsUpload;
 */
export class et2_vfsUpload extends et2_file
{
	static readonly _attributes : any = {
		"value": {
			"type": "any"	// Either nothing, or an object with file info
		},
		"path": {
			"name": "Path",
			"description": "Upload files to the specified VFS path",
			"type": "string",
			"default": ''
		},
		"listonly": {
			"name": "List Only",
			"description": "Display given file objects only as list (removes span,input and progress from the dom)",
			"type": "boolean",
			"default": false
		}
	};

	public static readonly legacyOptions : string[] = ["mime"];

	list : JQuery = null;

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param attrs
	 * @memberof et2_vfsUpload
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_vfsUpload._attributes, _child || {}));

		jQuery(this.node).addClass("et2_vfs");

		if(!this.options.path)
		{
			this.options.path = this.options.id;
		}
		// If the path is a directory, allow multiple uploads
		if(this.options.path.substr(-1) == '/')
		{
			this.set_multiple(true);
		}
		this.list = jQuery(document.createElement('table')).appendTo(this.node);
		if (this.options.listonly)
		{
			this.input.remove();
			this.span.remove();
			this.progress.remove();
		}
	}

	/**
	 * Get any specific async upload options
	 */
	getAsyncOptions(self)
	{
		return jQuery.extend({},super.getAsyncOptions(self),{
			target: egw.ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_upload")
		});
	}

	/**
	 * If there is a file / files in the specified location, display them
	 * Value is the information for the file[s] in the specified location.
	 *
	 * @param {Object{}} _value
	 */
	set_value(_value) {
		// Remove previous
		while(this._children.length > 0)
		{
			var node = this._children[this._children.length - 1];
			this.removeChild(node);
			node.destroy();
		}
		this.progress.empty();
		this.list.empty();

		// Set new
		if(typeof _value == 'object' && _value && Object.keys(_value).length)
		{
			for(let i in _value)
			{
				this._addFile(_value[i]);
			}
		}
		return true;
	}

	getDOMNode(sender) {
		if(sender && sender !== this && (sender.tagName && sender.tagName.indexOf("VFS") >= 0 || sender._type && sender._type.indexOf('vfs') >= 0))
		{
			// sender.fileInfo (Et2VfsPath's full stat-array, set via set_value()) takes priority - a
			// readonly Et2VfsPath's own value/getValue() is just the plain path string / null
			let value = sender.fileInfo || sender.getValue && sender.getValue() || sender.value || false;
			let row;
			if(value && value.path)
			{
				// Have a value, we can find the right place
				row = jQuery("[data-path='" + (value.path.replace(/'/g, '&quot')) + "']", this.list);
			}
			else
			{
				// No value, just use the last one
				row = jQuery("[data-path]", this.list).last();
			}
			if(sender.tagName === "ET2-VFS-MIME" || sender._type === 'vfs-mime')
			{
				return jQuery('.icon', row).get(0) || null;
			}
			else
			{
				return jQuery('.title', row).get(0) || null;
			}
		}
		else
		{
			return super.getDOMNode(sender);
		}
	}

	/**
	 * Add in the request id
	 *
	 * @param {type} form
	 */
	beforeSend(form)
	{
		let extra = super.beforeSend(form);
		extra["path"] = this.options.path;
		return extra;
	}

	/**
	 * A file upload is finished, update the UI
	 *
	 * @param {object} file
	 * @param {string|object} response
	 */
	finishUpload(file, response) {
		let result = super.finishUpload(file, response);

		if(typeof response == 'string') response = jQuery.parseJSON(response);
		if(response.response[0] && typeof response.response[0].data.length == 'undefined') {
			for(let key in response.response[0].data) {
				let value = response.response[0].data[key];
				if(value && value.path)
				{
					this._addFile(value);
					jQuery("[data-file='"+file.fileName.replace(/'/g, '&quot')+"']",this.progress).hide();
				}
			}
		}
		return result;
	}

	private _addFile(file_data) {
		if(jQuery("[data-path='"+file_data.path.replace(/'/g, '&quot')+"']").remove().length)
		{
			for(var child_index = this._children.length-1; child_index >= 0; child_index--)
			{
				var child = this._children[child_index];
				if(!child.options.value || child.options.value.path === file_data.path)
				{
					this.removeChild(child);
					child.destroy();
				}
			}
		}
		// Set up for expose
		if(file_data && typeof file_data.download_url === "undefined")
		{
			file_data.download_url = "/webdav.php" + file_data.path;
		}
		let row = jQuery(document.createElement("tr"))
			.attr("data-path", file_data.path.replace(/'/g, '&quot'))
			.attr("draggable", "true")
			.appendTo(this.list);
		jQuery(document.createElement("td"))
			.addClass('icon')
			.appendTo(row);

		jQuery(document.createElement("td"))
			.addClass('title')
			.appendTo(row);
		let mime = <Et2VfsMime>et2_createWidget('vfs-mime', {value: file_data}, this);

		// Trigger expose on click, if supported
		let vfs_attrs = {value: file_data, onclick: undefined};
		if (file_data && (typeof file_data.download_url != 'undefined'))
		{
			var fe_mime = egw.file_editor_prefered_mimes(file_data.mime);
			// Pass off opening responsibility to the Et2VfsMime widget
			if(typeof file_data.mime === 'string' && mime.isExposable())
			{
				vfs_attrs.onclick = function(ev)
				{
					ev.stopPropagation();
					// Pass it off to the associated vfsMime widget
					this.parentNode.parentNode.querySelector("et2-vfs-mime")?.dispatchEvent(new Event("click"));
					return false;
				};
			}
		}
		let vfs = <Et2VfsPath> et2_createWidget('vfs-path', {...vfs_attrs, readonly: true}, this);

		// If already attached, need to do this explicitly
		if(this.isAttached())
		{
			mime.set_value(file_data);
			vfs.set_value(file_data);
		}

		// Add in delete button
		if (!this.options.readonly)
		{
			let self = this;
			let delete_button = jQuery(document.createElement("td"))
				.appendTo(row);
			jQuery("<div />")
				.appendTo(delete_button)
				// We don't use ui-icon because it assigns a bg image
				.addClass("delete icon")
				.bind( 'click', function() {
					let d = new Et2Dialog('api');
					d.transformAttributes({
						callback: function(button) {
							if(button == Et2Dialog.YES_BUTTON)
							{
								egw.json("filemanager_ui::ajax_action", [
										'delete',
										[row.attr('data-path').replace(/&quot/g, "'")],
										''
									],
									function(data) {
										if(data && data.errs == 0) {row.slideUp(null, row.remove);}
										if(data && data.msg) {
											self.egw().message(data.msg, data.errs == 0 ? 'success' : 'error');
										}
									}
								).sendRequest();
							}
						},
						message: self.egw().lang('Delete file')+'?',
						title: self.egw().lang('Confirmation required'),
						buttons: Et2Dialog.BUTTONS_YES_NO,
						dialog_type: Et2Dialog.QUESTION_MESSAGE,
						width: 250
					});
					document.body.appendChild(<HTMLElement><unknown>d);
				});
		}
	}
}
et2_register_widget(et2_vfsUpload, ["vfs-upload"]);