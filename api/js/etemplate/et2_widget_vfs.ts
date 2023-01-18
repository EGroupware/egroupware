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
import {et2_textbox, et2_textbox_ro} from "./et2_widget_textbox";
import {et2_description} from "./et2_widget_description";
import {et2_file} from "./et2_widget_file";
import {et2_inputWidget} from "./et2_core_inputWidget";
import {et2_IDetachedDOM} from "./et2_core_interfaces";
import {et2_no_init} from "./et2_core_common";
import {egw} from "../jsapi/egw_global";
import {egw_getAppObjectManager, egwActionObject} from "../egw_action/egw_action.js";
import {egw_keyHandler} from '../egw_action/egw_keymanager.js';
import {EGW_KEY_ENTER} from '../egw_action/egw_action_constants.js';
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";
import type {Et2VfsMime} from "./Vfs/Et2VfsMime";
import type {Et2VfsGid, Et2VfsUid} from "./Et2Vfs/Et2VfsUid";

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
* vfs-name
* filename automatically urlencoded on return (urldecoded on display to user)
*
* @augments et2_textbox
*/
export class et2_vfsName extends et2_textbox
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_vfsName
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_vfsName._attributes, _child || {}));
		this.input.addClass("et2_vfs");
	}
	set_value(_value)
	{
		if(_value.path)
		{
			_value = _value.path;
		}
		try
		{
			_value = egw.decodePath(_value);
		} catch (e)
		{
			_value = 'Error! ' + _value;
		}
		super.set_value(_value);
	}

	getValue()
	{
		return egw.encodePath(super.getValue() || '');
	}
}
et2_register_widget(et2_vfsName, ["vfs-name"]);

/**
* vfs-name
* filename automatically urlencoded on return (urldecoded on display to user)
*
* @augments et2_textbox
*/
export class et2_vfsPath extends et2_vfsName
{
	static readonly _attributes : any = {
		noicon: {
			type: "boolean",
			description: "suppress folder icons",
			default: true
		}
	};

	private div : JQuery ;
	private span : JQuery;

	/**
	 * Constructor
	 *
	 * @memberOf et2_vfsName
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_vfsPath._attributes, _child || {}));
	}

	createInputWidget()
	{
		super.createInputWidget();
		this.div = jQuery(document.createElement("div"))
			.addClass('et2_vfsPath');
		this.span = jQuery(document.createElement("ul"))
			.appendTo(this.div);
		this.div.prepend(this.input);
		this.setDOMNode(this.div[0]);
		this.span.on('wheel', function(e){
			var delta = e.originalEvent["deltaY"] > 0 ? 30 : -30;
			this.scrollLeft = this.scrollLeft - delta;
		});
		this.span.on('mouseover', function (e){
			if (this.scrollWidth > this.clientWidth)
			{
				jQuery(this).addClass('scrollable');
			}
			else
			{
				jQuery(this).removeClass('scrollable');
			}
		});
		this.input.on('focus', function() {
			this.input.val(this.options.value);
			this.span.hide();
		}.bind(this))
			.on('focusout', function() {
				// Can't use show() because it uses the wrong display
				this.span.css('display', 'flex');
				this.input.val('');
			}.bind(this));
	}

	change(_node?)
	{
		if(this.input.val())
		{
			this.set_value(this.input.val());
		}
		return super.change(_node);
	}

	set_value(_value)
	{
		if(_value.path)
		{
			_value = _value.path;
		}
		if(_value === this.options.value && this._oldValue !== et2_no_init) return;

		let path_parts = _value.split('/');
		if(_value === '/') path_parts = [''];
		let path = "/";
		let text = '';
		if (this.span) this.span.empty().css('display', 'flex');
		this.input.val('');
		for(let i = 0; i < path_parts.length; i++)
		{
			path += (path=='/'?'':'/')+path_parts[i];
			text = egw.decodePath(path_parts[i]);

			let image = path=='/' ? this.egw().image('navbar','api') : this.egw().image(text);

			// Nice human-readable stuff for apps
			if(path_parts[1] == 'apps')
			{
				if(i === 1)
				{
					text = this.egw().lang('applications');
				}
				else if( i === 2)
				{
					text = this.egw().lang(path_parts[2]);
					image = this.egw().image('navbar',path_parts[2].toLowerCase());
				}
				else if(i === 3 && !isNaN(<number><unknown>text))
				{
					// we first need to call link_title without callback, as callback would be called for cache-hits too!
					let link_title = this.egw().link_title(path_parts[2], path_parts[3], false);
					if(link_title && typeof link_title !== 'undefined')
					{
						text = link_title;
					}
					else
					{
						this.egw().link_title(path_parts[2], path_parts[3], true).then(title =>
						{
							if(!title) return;
							jQuery('li',this.span).first().text(title);
						});
					}
				}
			}
			let self = this;
			let node = jQuery(document.createElement("li"))
				.addClass("vfsPath et2_clickable")
				.text(text)
				//.attr('title', egw.decodePath(path))
				.click({data:path, egw: this.egw()}, function(e) {
					return self.set_value(e.data.data);
				})
				.prependTo(this.span);
			if(image && !this.options.noicon)
			{
				node.prepend(this.egw().image_element(image));
			}
			jQuery(this.getDOMNode()).append(this.span);
		}

		if(this.isAttached() && this.options.value !== _value)
		{
			this._oldValue = this.options.value;
			this.options.value = _value;
			this.change();
		}
	}
	getValue() {
		return this.options ? this.options.value : null;
	}
}
et2_register_widget(et2_vfsPath, ["vfs-path"]);

/**
* vfs-name
* filename automatically urlencoded on return (urldecoded on display to user)
*
* @augments et2_textbox_ro
*/
export class et2_vfsName_ro extends et2_textbox_ro
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_vfsName_ro
	 */
	/**
	 * Constructor
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_vfsName_ro._attributes, _child || {}));
	}

	set_value(_value)
	{
		if(_value.path)
		{
			_value = _value.path;
		}
		try
		{
			_value = egw.decodePath(_value);
		} catch (e)
		{
			_value = 'Error! ' + _value;
		}
		super.set_value(_value);
	}

	getValue()
	{
		return egw.encodePath(super.getValue() || '');
	}
}
et2_register_widget(et2_vfsName_ro, ["vfs-name_ro"]);

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
			"type": "integer"
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
		if(!size)
		{
			size = 0;
		}
		const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		let i = 0;
		while(size >= 1024)
		{
			size /= 1024;
			++i;
		}
		return size.toFixed(i == 0 ? 0 : 1) + ' ' + units[i];
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


export class et2_vfsSelect extends et2_inputWidget
{
	// Allowed mode options
	modes : string[] = ['open','open-multiple','saveas','select-dir'];

	static readonly _attributes : any = {
		"mode": {
			name: "Dialog mode",
			type: "string",
			description: "One of {open|open-multiple|saveas|select-dir}",
			default: "open-multiple"
		},
		"method": {
			name: "Server side callback",
			type: "string",
			description: "Server side callback to process selected value(s) in \n\
		app.class.method or class::method format.  The first parameter will \n\
		be Method ID, the second the file list. 'download' is reserved and it \n\
		means it should use download_baseUrl instead of path in value (no method\n\
		 will be actually executed)."
		},
		"method_id": {
			name: "Method ID",
			type: "any",
			description: "optional parameter passed to server side callback.\n\
		Can be a string or a function.",
			default: ""
		},
		"path": {
			name: "Path",
			type: "string",
			description:"Start path in VFS.  Leave unset to use the last used path."
		},
		"mime": {
			name: "Mime type",
			type: "any",
			description: "Limit display to the given mime-type"
		},
		"button_label": {
			name: "Button label",
			description: "Set the label on the dialog's OK button.",
			default: "open"
		},
		"value": {
			type: "any", // Object
			description: "Array of paths (strings)"
		},
		"button_caption":{
			name: "button caption",
			type: "string",
			default: "Select files from Filemanager ...",
			description: "Caption for vfs-select button.",
			translate:true
		},
		"button_icon":{
			name: "button icon",
			type: "string",
			default: "check",
			description: "Custom icon to show on submit button."
		},
		"name": {
			name:"File name",
			type: "any", // Object
			description: "file name",
			default: ""
		},
		"dialog_title":{
			name: "dialog title",
			type: "string",
			default: "Save as",
			description: "Title of dialog",
			translate:true
		},
		"extra_buttons": {
			name: "extra action buttons",
			type: "any",
			description: "Extra buttons passed to dialog. It's co-related to method."
		}
	};

	button : JQuery;
	submit_callback : any;
	dialog : Et2Dialog;
	private value : any;
	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _attrs
	 * @memberOf et2_vfsSelect
	 */
	constructor(_parent, _attrs? : WidgetConfig, _child? : object)
	{
		// Call the inherited constructor
		super(_parent, _attrs, ClassWithAttributes.extendAttributes(et2_vfsSelect._attributes, _child || {}));

		// Allow no child widgets
		this.supportedWidgetClasses = [];

		this.button = jQuery(document.createElement("et2-button"))
			.attr("title", this.egw().lang("Select file(s) from VFS"))
			.attr("noSubmit", true)
			.addClass("et2_vfs_btn")
			.attr("image", "filemanager/navbar");

		if(this.options.readonly)
		{
			this.button.hide();
		}

		if (this.options.button_caption != "")
		{
			this.button.text(this.options.button_caption);
		}
		this.setDOMNode(this.button[0]);
	}

	_content(_content, _callback)
	{
		egw(window).loading_prompt('vfs-select', true, '', 'body');
		let self = this;
		if (typeof app.vfsSelectUI !="undefined")
		{
			if(this.dialog)
			{
				this.dialog.close();
			}
			delete app.vfsSelectUI;
		}
		let attrs = {
			mode: this.options.mode,
			label: this.options.button_label,
			path: this.options.path || null,
			mime: this.options.mime || null,
			name: this.options.name,
			method: this.options.method,
			recentPaths: et2_vfsSelect._getRecentPaths()
		};
		var callback = _callback || this._buildDialog;
		egw(window).json(
			'EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelect_content',
			[_content, attrs],
			function(_content){
				egw(window).loading_prompt('vfs-select', false);
				callback.apply(self, arguments);
			}
		).sendRequest(true);
	}

	/**
	 * Builds file navigator dialog
	 *
	 * @param {object} _data content
	 */
	private _buildDialog(_data)
	{
		if (!_data.content.mode.match(/open|open-multiple|saveas|select-dir/)) {
			egw.debug('warn', 'Mode is not matched!');
			return;
		}
		let self = this;
		let buttons = [
			{
				label: egw.lang(_data.content.label),
				id: "submit",
				image: _data.content.mode.match(/saveas|select-dir/) ? "save" : this.options.button_icon
			}
		];
		let extra_buttons_action = {};

		if (this.options.extra_buttons && this.options.method)
		{
			for (let i=0; i < this.options.extra_buttons.length; i++)
			{
				delete(this.options.extra_buttons[i]['click']);
				buttons.push(this.options.extra_buttons[i]);
				extra_buttons_action[this.options.extra_buttons[i]['id']] = this.options.extra_buttons[i]['id'];
			}

		}
		buttons.push({label: egw.lang("Close"), id: "close", image: "cancel"});


		// Don't rely only on app_name to fetch et2 object as app_name may not
		// always represent current app of the window, e.g.: mail admin account.
		// Try to fetch et2 from its template name.
		let etemplate = jQuery('form').data('etemplate');
		let et2;
		let currentApp = egw(window).app_name();
		if (etemplate && etemplate.name && !app[currentApp])
		{
			et2 = etemplate2.getByTemplate(etemplate.name)[0];
			currentApp = et2.name.split('.')[0];
		}
		else
		{
			et2 = etemplate2.getByApplication(currentApp)[0];
		}

		let data = jQuery.extend(_data, {'currentapp': currentApp, etemplate_exec_id: et2.etemplate_exec_id});

		// define a mini app object for vfs select UI
		app.vfsSelectUI = new app.classes.vfsSelectUI;

		// callback for dialog
		this.submit_callback = function(submit_button_id, submit_value, savemode)
		{
			if ((submit_button_id == 'submit' || (extra_buttons_action && extra_buttons_action[submit_button_id])) && submit_value)
			{
				let files : any = [];
				switch(_data.content.mode)
				{
					case 'open-multiple':
						if (submit_value.dir && submit_value.dir.selected)
						{
							for(var key in Object.keys(submit_value.dir.selected))
							{
								if (submit_value.dir.selected[key] != "")
								{
									files.push(submit_value.path+'/'+submit_value.dir.selected[key]);
								}
							}
						}
						break;
					case 'select-dir':
						files = submit_value.path;
						break;
					default:
						if (self.options.method === 'download') submit_value.path = _data.content.download_baseUrl;
						files = submit_value.path+'/'+submit_value.name;
						if (self.options.mode === 'saveas'  && !savemode)
						{
							for(var p in _data.content.dir)
							{
								if (_data.content.dir[p]['name'] == submit_value.name)
								{
									var saveModeDialogButtons = [
										{text: self.egw().lang("Yes"), id: "overwrite", class: "ui-priority-primary", "default": true, image: 'check'},
										{text: self.egw().lang("Rename"), id:"rename", image: 'edit'},
										{text: self.egw().lang("Cancel"), id:"cancel"}
									];
									return Et2Dialog.show_prompt(function(_button_id, _value) {
											switch (_button_id)
											{
												case "overwrite":
													return self.submit_callback(submit_button_id, submit_value, 'overwrite');
												case "rename":
													submit_value.name = _value;
													return self.submit_callback(submit_button_id, submit_value, 'rename');
											}
										},
										self.egw().lang('Do you want to overwrite existing file %1 in directory %2?', submit_value.name, submit_value.path),
										self.egw().lang('File %1 already exists', submit_value.name),
										submit_value.name, saveModeDialogButtons, null);
								}
							}
						}
						break;
				}
				et2_vfsSelect._setRecentPaths(submit_value.path);
				self.value = files;
				if (self.options.method && self.options.method !== 'download')
				{
					egw(window).request(
						self.options.method,
						[self.options.method_id, files, submit_button_id, savemode]
					).then(function(data){
							jQuery(self.node).change();
						}
					);
				}
				else
				{
					jQuery(self.node).change();
				}
				delete app.vfsSelectUI;
				self.dialog.close();
				return true;
			}
		};
		this.dialog = new Et2Dialog('api');

		this.dialog.transformAttributes(
			{
				callback: this.submit_callback,
				title: this.options.dialog_title,
				buttons: buttons,
				minWidth: 500,
				minHeight: 400,
				value: data,
				template: egw.webserverUrl + '/api/templates/default/vfsSelectUI.xet',
				resizable: false,
				// Don't destroy on close, it would take out the opening etemplate
				destroy_on_close: false
			});
		this.dialog.addEventListener("before-load", (ev) =>
		{
			this.dialog.template.uniqueId = 'api.vfsSelectUI';
		});
		document.body.appendChild(<HTMLElement><unknown>this.dialog);

		this.dialog.addEventListener('open', function(e)
		{
			app.vfsSelectUI.et2 = self.dialog.template.widgetContainer;
			app.vfsSelectUI.vfsSelectWidget = self;
			app.vfsSelectUI.et2_ready(app.vfsSelectUI.et2, 'api.vfsSelectUI');
		});
		this.dialog.addEventListener("close", () =>
		{
			self.dialog = undefined;
		});
	}

	/**
	 * Set recent path into sessionStorage
	 * @param {string} _path
	 */
	private static _setRecentPaths(_path)
	{
		let recentPaths = egw.getSessionItem('api', 'vfsRecentPaths') ?
			egw.getSessionItem('api', 'vfsRecentPaths').split(',') : [];
		if (recentPaths.indexOf(_path) == -1) recentPaths.push(_path);
		egw.setSessionItem('api', 'vfsRecentPaths', recentPaths);
	}

	/**
	 * Get recent paths from sessionStorage
	 * @returns {Array} returns an array of recent paths
	 */
	private static _getRecentPaths()
	{
		return egw.getSessionItem('api', 'vfsRecentPaths') ?
			egw.getSessionItem('api', 'vfsRecentPaths').split(',') : [];
	}

	/**
	 * click handler
	 * @param {event object} e
	 */
	click(e)
	{
		this._content.call(this, null);
	}

	/**
	 * Set the dialog's mode.
	 * Valid options are in et2_vfsSelect.modes
	 *
	 * @param {string} mode 'open', 'open-multiple', 'saveas' or 'select-dir'
	 */
	set_mode(mode)
	{
		// Check mode
		if(jQuery.inArray(mode, this.modes) < 0)
		{
			this.egw().debug("warn", "Invalid mode for '%s': %s Valid options:", this.id,mode, this.modes);
			return;
		}
		this.options.mode = mode;
	}

	/**
	 * Set the label on the dialog's OK button.
	 *
	 * @param {string} label
	 */
	set_button_label(label)
	{
		this.options.button_label = label;
	}

	/**
	 * Set the caption for vfs-select button
	 *
	 * @param {string} caption string value as a caption
	 */
	set_button_caption(caption)
	{
		this.options.button_caption = caption;
	}

	/**
	 * Set the ID passed to the server side callback
	 *
	 * @param {string} id
	 */
	set_method_id(id)
	{
		this.options.method_id = id;
	}

	set_readonly(readonly)
	{
		this.options.readonly = Boolean(readonly);

		if(this.options.readonly)
		{
			this.button.hide();
		}
		else
		{
			this.button.show();
		}
	}

	set_value(value)
	{
		this.value = value;
	}

	getValue()
	{
		return this.value;
	}
};
et2_register_widget(et2_vfsSelect, ["vfs-select"]);