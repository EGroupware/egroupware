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

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	vfsSelectUI;
	et2_core_inputWidget;
	et2_core_valueWidget;
	et2_widget_description;
	et2_widget_file;
*/

import {et2_valueWidget} from "./et2_core_valueWidget";
import {et2_createWidget, et2_register_widget, WidgetConfig} from "./et2_core_widget";
import {ClassWithAttributes} from "./et2_core_inheritance";
import {et2_description} from "./et2_widget_description";
import {et2_file} from "./et2_widget_file";
import {et2_IDetachedDOM} from "./et2_core_interfaces";
import {egw} from "../jsapi/egw_global";
import {egw_getAppObjectManager, egwActionObject} from "../egw_action/egw_action";
import {egw_keyHandler} from '../egw_action/egw_keymanager';
import {EGW_KEY_ENTER} from '../egw_action/egw_action_constants';
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";
import type {Et2VfsMime} from "./Vfs/Et2VfsMime";
import type {Et2VfsGid, Et2VfsUid} from "./Et2Vfs/Et2VfsUid";
import {Et2VfsName, Et2VfsNameReadonly} from "./Et2Vfs/Et2VfsName";

/**
 * Class which implements the "vfs" XET-Tag
 *
 * @augments et2_valueWidget
 */
export class et2_vfs extends et2_valueWidget implements et2_IDetachedDOM
{
	static readonly _attributes : any = {
		"value": {
			"type": "any", // Object
			"description": "Array of (stat) information about the file"
		}
	};

	/**
	 * Mime type of directories
	 */
	static readonly DIR_MIME_TYPE : string = 'httpd/unix-directory';

	value : any;
	span : JQuery = null;

	/**
	 * Constructor
	 *
	 * @memberOf et2_vfs
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_vfs._attributes, _child || {}));

		this.value = "";
		this.span = jQuery(document.createElement("ul"))
			.addClass('et2_vfs');

		this.setDOMNode(this.span[0]);
	}

	getValue()
	{
		return this.value;
	}

	set_value(_value)
	{
		if (typeof _value !== 'object')
		{
			// Only warn if it's an actual value, just blank for falsy values
			if(_value)
			{
				this.egw().debug("warn", "%s only has path, needs full array", this.id, _value);
			}
			this.span.empty().text(_value);
			return;
		}
		this.span.empty();
		this.value = _value;
		let path = _value.path ? _value.path : '/';
		// calculate path as parent of name, which can contain slashes
		// eg. _value.path=/home/ralf/sub/file, _value.name=sub/file --> path=/home/ralf
		// --> generate clickable fields for sub/ + file
		let sub_path = path.substring(0, _value.path.length-_value.name.length-1);
		let path_offset, path_parts;
		if(_value.path.indexOf(_value.name) >= 0 && sub_path[sub_path.length-1] === '/')
		{
			path = sub_path;
			path_offset = path.split('/').length;
			path_parts = _value.path.split('/');
		}
		else
		{
			if(_value.path.indexOf(_value.name) >= 0)
			{
				// Remove name from end, so we can add it again later
				path = sub_path;
			}
			path_offset = 0;
			path_parts = _value.name.split('/');
		}

		let text;
		for(let i = path_offset; i < path_parts.length; i++)
		{
			path += (path=='/'?'':'/')+path_parts[i];
			text = egw.decodePath(path_parts[i]);

			// Nice human-readable stuff for apps
			if(path_parts[1] == 'apps')
			{
				switch(path_parts.length)
				{
					case 2:
						if(i == 1)
						{
							text = this.egw().lang('applications');
						}
						break;
					case 3:
						if( i == 2)
						{
							text = this.egw().lang(path_parts[2]);
						}
						break;
					case 4:
						if(!isNaN(text))
						{
							let link_title = this.egw().link_title(path_parts[2],path_parts[3],
								function(title) {
									if(!title || this.value.name == title) return;
									jQuery('li',this.span).last().text(title);
								}, this
							);
							if(link_title && typeof link_title !== 'undefined') text = link_title;
						}
						break;
				}
			}
			let self = this;
			var data = {path: path, type: i < path_parts.length-1 ? et2_vfs.DIR_MIME_TYPE : _value.mime };
			var node = jQuery(document.createElement("li"))
				.addClass("vfsFilename")
				.text(text + (i < path_parts.length-1 ? '/' : ''))
				//.attr('title', egw.decodePath(path))
				.addClass("et2_clickable et2_link")
				.click({data:data, egw: this.egw()}, function(e) {
					if(!self.onclick) {
						e.data.egw.open(e.data.data, "file");
					}
					else if (self.click(e))
					{
						e.data.egw.open(e.data.data, "file");
					}
				})
				.appendTo(this.span);
		}
		// Last part of path do default action
		this._bind_default_action(node, data);
	}

	private _bind_default_action(node, data)
	{
		let links = [];
		let widget : any = this;
		let defaultAction = null;
		let object = null;
		let app = this.getInstanceManager().app;
		while(links.length === 0 && widget.getParent())
		{
			object = (<egwActionObject><unknown>egw_getAppObjectManager(app)).getObjectById(widget.id);
			if(object && object.manager && object.manager.children)
			{
				links = object.manager.children;
			}
			widget = widget.getParent();
		}
		for (let k in links)
		{
			if (links[k].default && links[k].enabled.exec(links[k]))
			{
				defaultAction = links[k];
				break;
			}
		}
		if(defaultAction && !this.onclick)
		{
			node.off('click').click({data:data, egw: this.egw()}, function(e) {
				// Wait until object selection happens
				window.setTimeout(function() {
					// execute default action
					egw_keyHandler(EGW_KEY_ENTER, false, false, false);
				});
				// Select row
				return true;
			}.bind({data: data, object: object}));
		}
	}

	/**
	 * Code for implementing et2_IDetachedDOM (data grid)
	 *
	 * @param {array} _attrs array of attribute-names to push further names onto
	 */
	getDetachedAttributes(_attrs)
	{
		_attrs.push("value");
	}

	getDetachedNodes()
	{
		return [this.span[0]];
	}

	setDetachedAttributes(_nodes, _values)
	{
		this.span = jQuery(_nodes[0]);
		if(typeof _values["value"] != 'undefined')
		{
			this.set_value(_values["value"]);
		}
	}


}
et2_register_widget(et2_vfs, ["vfs"]);

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
* vfs-size
* Human readable file sizes
*
* @augments et2_description
*/
export class et2_vfsSize extends et2_description
{
	static readonly _attributes : any = {
		"value": {
			"type": "any"	// not using "integer", as we use parseInt on everything not a number, but want to show empty of "" or undefined, not 0B
		}
	};
	/**
	 * Constructor
	 *
	 * @memberOf et2_vfsSize
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_vfsSize._attributes, _child || {}));
		this.span.addClass("et2_vfs");
	}

	human_size(size)
	{
		if(typeof size !== "number")
		{
			size = parseInt(size);
		}
		if(Number.isNaN(size))
		{
			return '';
		}
		let sign = '';
		if (size < 0)
		{
			sign = '-';
			size = -size;
		}
		const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		let i = 0;
		while(size >= 1024)
		{
			size /= 1024;
			++i;
		}
		return sign+size.toFixed(i == 0 ? 0 : 1) + ' ' + units[i];
	}

	set_value(_value)
	{
		if(_value.size)
		{
			_value = _value.size;
		}
		jQuery(this.node).text(this.human_size(_value));
	}

	setDetachedAttributes(_nodes, _values)
	{
		if(typeof _values["value"] !== "undefined") {
			this.node = _nodes[0];
			this.set_value(_values["value"]);
			delete _values["value"];
		}
		super.setDetachedAttributes(_nodes, _values);
	}
}
et2_register_widget(et2_vfsSize, ["vfs-size"]);


/**
* vfs-mode: textual representation of permissions + extra bits
*
* @augments et2_description
*/
export class et2_vfsMode extends et2_description
{
	// Masks for file types
	static readonly types : {l : number, s : number, p : number, c : number, d : number, b: number, '-' : number} = {
		'l': 0xA000, // link
		's': 0xC000, // Socket
		'p': 0x1000, // FIFO pipe
		'c': 0x2000, // Character special
		'd': 0x4000, // Directory
		'b': 0x6000, // Block special
		'-': 0x8000 // Regular
	};

	// Sticky / UID / GID
	static readonly sticky : {mask : number, char : string, position : number}[] = [
		{ mask: 0x200, "char": "T", position: 9 }, // Sticky
		{ mask: 0x400, "char": "S", position: 6 }, // sGID
		{ mask: 0x800, "char": "S", position: 3 } // SUID
	];

	static readonly perms : {x : number, w : number, r : number} = {
		'x': 0x1, // Execute
		'w': 0x2, // Write
		'r': 0x4  // Read
	};

	/**
	 * Constructor
	 *
	 * @memberOf et2_vfsMode
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_vfsMode._attributes, _child || {}));
		this.span.addClass("et2_vfs");
	}

	/**
	 * Get text for file stuff
	 * Result will be like -rwxr--r--.  First char is type, then read, write, execute (or other bits) for
	 * user, group, world
	 *
	 * @param {number} _value vfs mode
	 */
	text_mode(_value)
	{
		let text = [];
		if(typeof _value != "number")
		{
			_value = parseInt(_value);
		}
		if(!_value) return "----------";

		// Figure out type
		let type = 'u'; // unknown
		for(let flag in et2_vfsMode.types)
		{
			if((_value & et2_vfsMode.types[flag]) == et2_vfsMode.types[flag])
			{
				type = flag;
				break;
			}
		}

		// World, group, user - build string backwards
		for(let i = 0; i < 3; i++)
		{
			for(let perm in et2_vfsMode.perms)
			{
				if(_value & et2_vfsMode.perms[perm])
				{
					text.unshift(perm);
				}
				else
				{
					text.unshift("-");
				}
			}
			_value = _value >> 3;
		}
		// Sticky / UID / GID
		for(let i = 0; i < et2_vfsMode.sticky.length; i++)
		{
			if(et2_vfsMode.sticky[i].mask & _value)
			{
				let current = text[et2_vfsMode.sticky[i].position];
				text[et2_vfsMode.sticky[i].position] = et2_vfsMode.sticky[i]["char"];
				if(current == 'x') text[et2_vfsMode.sticky[i].position].toLowerCase();
			}
		}
		return type + text.join('');
	}

	set_value(_value)
	{
		if(_value.size)
		{
			_value = _value.size;
		}
		let text = this.text_mode(_value);
		jQuery(this.node).text(text);
	}

	setDetachedAttributes(_nodes, _values)
	{
		if(typeof _values["value"] !== "undefined") {
			this.node = _nodes[0];
			this.set_value(_values["value"]);
			delete _values["value"];
		}
		super.setDetachedAttributes(_nodes, _values);
	}
}
et2_register_widget(et2_vfsMode, ["vfs-mode"]);


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

/* vfs-upload aka VFS file:       displays either download and delete (x) links or a file upload
*   + ID is either a vfs path or colon separated $app:$id:$relative_path, eg: infolog:123:special/offer
*   + if empty($id) / new entry, file is created in a hidden temporary directory in users home directory
*     and calling app is responsible to move content of that dir to entry directory, after entry is saved
*   + option: required mimetype or regular expression for mimetype to match, eg. '/^text\//i' for all text files
*   + if path ends in a slash, multiple files can be uploaded, their original filename is kept then
*
* @augments et2_file
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
			let value = sender.getValue && sender.getValue() || sender.options?.value || false;
			let row;
			if(value)
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
				if(child.options.value.path === file_data.path)
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
					jQuery('img', this.parentNode.parentNode).trigger("click");
					return false;
				};
			}
		}
		let vfs = <et2_vfs> et2_createWidget('vfs', vfs_attrs, this);

		// If already attached, need to do this explicitly
		if(this.isAttached())
		{
			mime.set_value(file_data);
			vfs.set_value(file_data);
			vfs.doLoadingFinished();
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