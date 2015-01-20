/**
 * EGroupware eTemplate2 - JS VFS widgets
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright Nathan Gray 2012
 * @version $Id$
*/

"use strict";

/*egw:uses
	jquery.jquery;
	et2_core_inputWidget;
	et2_core_valueWidget;
	et2_widget_description;
	et2_widget_file;
	/etemplate/js/expose.js;
*/

/**
 * Class which implements the "vfs" XET-Tag
 *
 * @augments et2_valueWidget
 */
var et2_vfs = et2_valueWidget.extend([et2_IDetachedDOM],
{
	attributes: {
		"value": {
			"type": "any", // Object
			"description": "Array of (stat) information about the file"
		}
	},

	/**
	 * Mime type of directories
	 */
	DIR_MIME_TYPE: 'httpd/unix-directory',

	/**
	 * Constructor
	 *
	 * @memberOf et2_vfs
	 */
	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("ul"))
			.addClass('et2_vfs');

		this.setDOMNode(this.span[0]);
	},

	getValue: function() {
		return this.value;
	},

	set_value: function(_value) {
		if (typeof _value !== 'object')
		{
			this.egw().debug("warn", "%s only has path, needs full array", this.id, _value);
			this.span.empty().text(_value);
			return;
		}
		this.span.empty();
		this.value = _value;
		var path = _value.path ? _value.path : '/';
		// calculate path as parent of name, which can contain slashes
		// eg. _value.path=/home/ralf/sub/file, _value.name=sub/file --> path=/home/ralf
		// --> generate clickable fields for sub/ + file
		if(_value.path.indexOf(_value.name) >= 0)
		{
			path = path.substr(0, _value.path.length-_value.name.length-1);
			var path_offset = path.split('/').length;
			var path_parts = _value.path.split('/');
		}
		else
		{
			var path_offset = 0;
			var path_parts = [_value.name];
		}

		var text;
		for(var i = path_offset; i < path_parts.length; i++)
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
							var link_title = this.egw().link_title(path_parts[2],path_parts[3],
								function(title) {
									if(!title || this.value.name == title) return;
									$j('li',this.span).last().text(title);
								}, this
							);
							if(link_title && typeof link_title !== 'undefined') text = link_title;
						}
						break;
				}
			}
			var self = this;
			var data = {path: path, type: i < path_parts.length-1 ? this.DIR_MIME_TYPE : _value.mime };
			$j(document.createElement("li"))
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
	},

	/**
	 * Code for implementing et2_IDetachedDOM (data grid)
	 *
	 * @param {array} _attrs array of attribute-names to push further names onto
	 */
	getDetachedAttributes: function(_attrs)
	{
		_attrs.push("value");
	},

	getDetachedNodes: function()
	{
		return [this.span[0]];
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		this.span = jQuery(_nodes[0]);
		if(typeof _values["value"] != 'undefined')
		{
			this.set_value(_values["value"]);
		}
	}


});
et2_register_widget(et2_vfs, ["vfs"]);

/**
 * vfs-name
 * filename automatically urlencoded on return (urldecoded on display to user)
 *
 * @augments et2_textbox
 */
var et2_vfsName = et2_textbox.extend(
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_vfsName
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.input.addClass("et2_vfs");
	},
	set_value: function(_value) {
		if(_value.path)
		{
			_value = _value.path;
		}
		_value = egw.decodePath(_value);
		this._super.apply(this,[_value]);
	},
	getValue: function() {
		return egw.encodePath(this._super.apply(this));
	}
});
et2_register_widget(et2_vfsName, ["vfs-name"]);

/**
 * vfs-name
 * filename automatically urlencoded on return (urldecoded on display to user)
 *
 * @augments et2_textbox_ro
 */
var et2_vfsName_ro = et2_textbox_ro.extend(
{
	/**
	 * Constructor
	 *
	 * @memberOf et2_vfsName_ro
	 */
	init: function() {
		this._super.apply(this, arguments);
	},
	set_value: function(_value) {
		if(_value.path)
		{
			_value = _value.path;
		}
		_value = egw.decodePath(_value);
		this._super.apply(this,[_value]);
	},
	getValue: function() {
		return egw.encodePath(this._super.apply(this));
	}
});
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
 *
 * @augments et2_valueWidget
 */
var et2_vfsMime = expose(et2_valueWidget.extend([et2_IDetachedDOM],
{
	attributes: {
		"value": {
			"type": "any", // Object
			"description": "Array of (stat) information about the file"
		},
		"size": {
			"name": "Icon size",
			"type": "integer",
			"description": "Size of icon / thumbnail, in pixels",
			"default": et2_no_init
		},
		"expose_callback":{
			"name": "expose_callback",
			"type": "js",
			"default": et2_no_init,
			"description": "JS code which is executed when expose slides."
		},
		expose_view: {
			name: "Expose view",
			type: "boolean",
			default: true,
			description: "Clicking on an image would popup an expose view"
		}
	},

	legacyOptions:["size"],

	/**
	 * Constructor
	 *
	 * @memberOf et2_vfsMime
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.iconOverlayContainer = jQuery(document.createElement('span')).addClass('iconOverlayContainer');
		this.image = jQuery(document.createElement("img"));
		this.image.addClass("et2_vfs vfsMimeIcon");
		this.iconOverlayContainer.append(this.image);
		this.setDOMNode(this.iconOverlayContainer[0]);
	},
	
	/**
	 * Handler for expose slide action, from expose
	 * Returns data needed for the given index, or false to let expose handle it
	 *
	 * @param {Gallery} gallery
	 * @param {integer} index
	 * @param {DOMNode} slide
	 * @return {Array} array of objects consist of media contnet 
	 */
	expose_onslide: function(gallery, index, slide)
	{
		var content = false;
		if (this.options.expose_callback && typeof this.options.expose_callback == 'function')
		{
			//Call the callback to load more items
			content = this.options.expose_callback.call(this,[gallery, index]);
			if (content) this.add(content);
		}
		return content;
	},
	
	/**
	 * Function to get media content to feed the expose
	 * 
	 * @param {type} _value
	 * @returns {Array} return an array of object consists of media content
	 */
	getMedia: function (_value)
	{
		var base_url = egw.webserverUrl.match(/^\//,'ig')?egw(window).window.location.origin + egw.webserverUrl:egw.webserverUrl;
		var mediaContent = [];
		if (_value && _value.mime && _value.mime.match(/video/,'ig'))
		{
			mediaContent = [{
				title: _value.name,
				type: 'video/*',
				sources:[{
						href: base_url + _value.download_url,
						type: _value.mime,
				}]
			}];
		}
		else
		{
			mediaContent = [{
				title: _value.name,
				href: base_url + _value.download_url,
				type: _value.mime,
				thumbnail: _value.path && _value.mime ? egw(window).window.location.origin + this.egw().mime_icon(_value['mime'], _value['path']) : this.image.attr('src')
			}];
		}
		return mediaContent;
	},
	
	set_value: function(_value) {
		if (typeof _value !== 'object')
		{
			this.egw().debug("warn", "%s only has path, needs array with path & mime", this.id, _value);
			// Keep going, will be 'unknown type'
		}
		var src = this.egw().mime_icon(_value['mime'], _value['path']);
		if(src)
		{
			// Set size of thumbnail
			if(src.indexOf("thumbnail.php") > -1)
			{
				if(this.options.size)
				{
					src += "&thsize="+this.options.size;
				}
				this.image.css("max-width", "100%");
			}
			this.image.attr("src", src);
		}
		// add/remove link icon, if file is (not) a symlink
		if ((_value.mode & et2_vfsMode.prototype.types.l) == et2_vfsMode.prototype.types.l)
		{
			if (typeof this.overlayContainer == 'undefined')
			{

				this.overlayContainer = jQuery(document.createElement('span')).addClass('overlayContainer');
				this.overlayContainer.append(jQuery(document.createElement('img'))
					.addClass('overlay').attr('src', this.egw().image('link', 'etemplate')));
				this.iconOverlayContainer.append(this.overlayContainer);
			}
		}
		else if (typeof this.overlayContainer != 'undefined')
		{
			this.overlayContainer.remove();
			delete this.overlayContainer;
		}
	},
	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 * Override to add needed attributes
	 *
	 * @param {array} _attrs array of attribute-names to push further names onto
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("value", "class");
	},
	getDetachedNodes: function() {
		return [this.node, this.iconOverlayContainer[0], this.image[0]];
	},

	setDetachedAttributes: function(_nodes, _values) {
		this.iconOverlayContainer = jQuery(_nodes[1]);
		this.image = jQuery(_nodes[2]);
		this.node = _nodes[0];
		this.overlayContainer = _nodes[0].children[1];
		if(typeof _values['class'] != "undefined") {
			this.image.addClass(_values['class']);
		}
		if(typeof _values['value'] != "undefined") {
			this.set_value(_values['value']);
		}
	}
}));
et2_register_widget(et2_vfsMime, ["vfs-mime"]);

/**
 * vfs-size
 * Human readable file sizes
 *
 * @augments et2_description
 */
var et2_vfsSize = et2_description.extend({
	attributes: {
		"value": {
			"type": "integer"
		}
	},
	/**
	 * Constructor
	 *
	 * @memberOf et2_vfsSize
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.span.addClass("et2_vfs");
	},
	human_size: function(size) {
		if(typeof size !== "number")
		{
			size = parseInt(size);
		}
		if(!size)
		{
			size = 0;
		}
		var units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
		var i = 0;
		while(size >= 1024)
		{
			size /= 1024;
			++i;
		}
		return size.toFixed(i == 0 ? 0 : 1) + ' ' + units[i];
	},
	set_value: function(_value) {
		if(_value.size)
		{
			_value = _value.size;
		}
		jQuery(this.node).text(this.human_size(_value));
	},
	setDetachedAttributes: function(_nodes, _values)
	{
		if(typeof _values["value"] !== "undefined") {
			this.node = _nodes[0];
			this.set_value(_values["value"]);
			delete _values["value"];
		}
		this._super.apply(this, arguments);
	}
});
et2_register_widget(et2_vfsSize, ["vfs-size"]);


/**
 * vfs-mode: textual representation of permissions + extra bits
 *
 * @augments et2_description
 */
var et2_vfsMode = et2_description.extend({
	// Masks for file types
	types: {
		'l': 0xA000, // link
		's': 0xC000, // Socket
		'p': 0x1000, // FIFO pipe
		'c': 0x2000, // Character special
		'd': 0x4000, // Directory
		'b': 0x6000, // Block special
		'-': 0x8000 // Regular
	},
	// Sticky / UID / GID
	sticky: [
		{ mask: 0x200, "char": "T", position: 9 }, // Sticky
		{ mask: 0x400, "char": "S", position: 6 }, // sGID
		{ mask: 0x800, "char": "S", position: 3 } // SUID
	],
	perms: {
		'x': 0x1, // Execute
		'w': 0x2, // Write
		'r': 0x4  // Read
	},

	/**
	 * Constructor
	 *
	 * @memberOf et2_vfsMode
	 */
	init: function() {
		this._super.apply(this, arguments);
		this.span.addClass("et2_vfs");
	},

	/**
	 * Get text for file stuff
	 * Result will be like -rwxr--r--.  First char is type, then read, write, execute (or other bits) for
	 * user, group, world
	 *
	 * @param {number} _value vfs mode
	 */
	text_mode: function(_value) {
		var text = [];
		if(typeof _value != "number")
		{
			_value = parseInt(_value);
		}
		if(!_value) return "----------";

		// Figure out type
		var type = 'u'; // unknown
		for(var flag in this.types)
		{
			if((_value & this.types[flag]) == this.types[flag])
			{
				type = flag;
				break;
			}
		}

		// World, group, user - build string backwards
		for(var i = 0; i < 3; i++)
		{
			for(var perm in this.perms)
			{
				if(_value & this.perms[perm])
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
		for(var i = 0; i < this.sticky.length; i++)
		{
			if(this.sticky[i].mask & _value)
			{
				current = text[this.sticky[i].position];
				text[this.sticky[i].position] = this.sticky[i]["char"];
				if(current == 'x') text[this.sticky[i].position].toLowerCase();
			}
		}
		return type + text.join('');
	},
	set_value: function(_value) {
		if(_value.size)
		{
			_value = _value.size;
		}
		var text = this.text_mode(_value);
		jQuery(this.node).text(text);
	},

	setDetachedAttributes: function(_nodes, _values)
	{
		if(typeof _values["value"] !== "undefined") {
			this.node = _nodes[0];
			this.set_value(_values["value"]);
			delete _values["value"];
		}
		this._super.apply(this, arguments);
	}
});
et2_register_widget(et2_vfsMode, ["vfs-mode"]);


/**
 * vfs-uid / vfs-gid: Displays the name for an ID.
 * Same as read-only selectAccount, except if there's no user it shows "root"
 *
 * @augments et2_selectAccount_ro
 */
var et2_vfsUid = et2_selectAccount_ro.extend(
{
	/**
	 * @memberOf et2_vfsUid
	 * @param _node
	 * @param _value
	 */
	set_title: function(_node, _value) {
		if(_value == "")
		{
			arguments[1] = "root";
		}
		this._super.apply(this, arguments);
	}
});
et2_register_widget(et2_vfsUid, ["vfs-uid","vfs-gid"]);


/* vfs-upload aka VFS file:       displays either download and delete (x) links or a file upload
 *   + value is either a vfs path or colon separated $app:$id:$relative_path, eg: infolog:123:special/offer
 *   + if empty($id) / new entry, file is created in a hidden temporary directory in users home directory
 *     and calling app is responsible to move content of that dir to entry directory, after entry is saved
 *   + option: required mimetype or regular expression for mimetype to match, eg. '/^text\//i' for all text files
 *   + if path ends in a slash, multiple files can be uploaded, their original filename is kept then
 *
 * @augments et2_file
 */
var et2_vfsUpload = et2_file.extend(
{
	legacyOptions: ["mime"],

	asyncOptions: {
		url: egw.ajaxUrl("etemplate_widget_vfs::ajax_upload::etemplate")
	},

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param attrs
	 * @memberof et2_vfsUpload
	 */
	init: function(_parent, attrs) {
		this._super.apply(this, arguments);
		this.input.addClass("et2_vfs");

		// If the ID is a directory, allow multiple uploads
		if(this.options.id.substr(-1) == '/')
		{
			this.set_multiple(true);
		}
	},

	set_value: function(_value) {
	}
});
et2_register_widget(et2_vfsUpload, ["vfs-upload"]);


var et2_vfsSelect = et2_inputWidget.extend(
{
	// Allowed mode options
	modes: ['open','open-multiple','saveas','select-dir'],

	attributes: {
		"mode": {
			name: "Dialog mode",
			type: "string",
			description: "One of {open|open-multiple|saveas|select-dir}",
			default: "open-multiple"
		},
		"method": {
			name: "Server side callback",
			type: "string",
			description: "Server side callback to process selected value(s) in app.class.method or class::method format.  The first parameter will be Method ID, the second the file list."
		},
		"method_id": {
			name: "Method ID",
			type: "any",
			description: "optional parameter passed to server side callback.  Can be a string or a function.",
			default: ""
		},
		"path": {
			name: "Path",
			type: "string",
			description:"Start path in VFS.  Leave unset to use the last used path."
		},
		"mime": {
			name: "Mime type",
			type: "string",
			description: "Limit display to the given mime-type"
		},
		"button_label": {
			name: "Button label",
			description: "Set the label on the dialog's OK button.",
			default: "open"
		},
		"value": {
			"type": "any", // Object
			"description": "Array of paths (strings)"
		}, 
		"button_caption":{
			name: "button caption",
			type: "string",
			default: "Select files from Filemanager ...",
			description: "Caption for vfs-select button.",
			translate:true
		}
	},

	/**
	 * Constructor
	 *
	 * @param _parent
	 * @param _attrs
	 * @memberOf et2_vfsSelect
	 */
	init: function(_parent, _attrs) {
		// _super.apply is responsible for the actual setting of the params (some magic)
		this._super.apply(this, arguments);

		// Allow no child widgets
		this.supportedWidgetClasses = [];
		
		this.button = $j(document.createElement("button"))
			.attr("title", this.egw().lang("Select file(s) from VFS"))
			.addClass("et2_button et2_button_text et2_vfs_btn")
			.css("background-image","url("+this.egw().image("filemanager/navbar")+")");
			
		if (this.options.button_caption != "")
		{
			this.button.text(this.options.button_caption);
		}
		this.setDOMNode(egw.app('filemanager') ? this.button[0]:document.createElement('span'));
	},

	click: function(e) {

	    // No permission
	    if(!egw.app('filemanager')) return;

		var self = this;

		var attrs = {
			menuaction: 'filemanager.filemanager_select.select',
			mode: this.options.mode,
			method: this.options.method,
			label: this.options.button_label,
			id: typeof this.options.method_id == "function" ? this.options.method_id.call(): this.options.method_id
		};
		if(this.options.path)
		{
			attrs.path = this.options.path;
		}
		if(this.options.mime)
		{
			attrs.mime = this.options.mime;
		};

		// Open the filemanager select in a popup
		var popup = this.egw().open_link(
			this.egw().link('/index.php', attrs),
			'link_existing',
			'680x400'
		);
		if(popup)
		{
			// Update on close doesn't always (ever, in chrome) work, so poll
			var poll = self.egw().window.setInterval(
				function() {
					if(popup.closed) {
						self.egw().window.clearInterval(poll);

						// Update path to where the user wound up
						var path = popup.etemplate2.getByApplication('filemanager')[0].widgetContainer.getArrayMgr("content").getEntry('path') || '';
						self.options.path = path;

						// Get the selected files
						var files = popup.selected_files || [];
						self.value = files;

						// Fire a change event so any handlers run
						$j(self.node).change();
					}
				},1000
			);
		}
	},

	/**
	 * Set the dialog's mode.
	 * Valid options are in et2_vfsSelect.modes
	 *
	 * @param {string} mode 'open', 'open-multiple', 'saveas' or 'select-dir'
	 */
	set_mode: function(mode) {
		// Check mode
		if(jQuery.inArray(mode, this.modes) < 0)
		{
			this.egw().debug("warn", "Invalid mode for '%s': %s Valid options:", this.id,mode, this.modes);
			return;
		}
		this.options.mode = mode;
	},

	/**
	 * Set the label on the dialog's OK button.
	 *
	 * @param {string} label
	 */
	set_button_label: function(label)
	{
		this.options.button_label = label;
	},
	
	/**
	 * Set the caption for vfs-select button
	 * 
	 * @param {string} caption string value as a caption
	 */
	set_button_caption: function (caption)
	{
		this.options.button_caption = caption;
	},
	
	/**
	 * Set the ID passed to the server side callback
	 *
	 * @param {string} id
	 */
	set_method_id: function(id) {
		this.options.method_id = id;
	},

	getValue: function() {
		return this.value;
	}
});
et2_register_widget(et2_vfsSelect, ["vfs-select"]);
