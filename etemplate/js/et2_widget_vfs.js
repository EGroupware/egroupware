/**
 * eGroupWare eTemplate2 - JS VFS widgets
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
*/

/**
 * Class which implements the "vfs" XET-Tag
 */
var et2_vfs = et2_valueWidget.extend([et2_IDetachedDOM], {

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

	init: function() {
		this._super.apply(this, arguments);

		this.value = "";
		this.span = $j(document.createElement("ul"))
			.addClass('et2_vfs');

		this.setDOMNode(this.span[0]);
	},

	set_value: function(_value) {
		var path = '';
		if(_value.path)
		{
			path = _value.path
		}
		else if (typeof _value !== 'object')
		{
			this.egw().debug("warn", "%s only has path, needs full array", this.id, _value);
			this.span.empty().text(_value);
			return;
		}
		var path_parts = path ? path.split('/') : [];

		this._make_path(path_parts);

		// Make it clickable
		var type = this.egw().get_mime_info(_value.mime) ? _value.mime : this.DIR_MIME_TYPE;
		jQuery("li",this.span).addClass("et2_clickable et2_link")
			.wrapInner('<a onclick="egw.open({path:\''+
				egw.encodePath(path.replace('"','&quot;').replace("'", "\\\'"))+
				'\',type:\''+type+'\'},\'file\');">'
			);
	},

	/**
	 * Create a list of clickable path components
	 */
	_make_path: function(path_parts) {
		// TODO: This is apparently not used in old etemplate
		var text;
		for(var i = 0; i < path_parts.length; i++)
		{
			text = decodeURIComponent(path_parts[i]);

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
							text = this.egw().link_title(path_parts[2],path_parts[3]);
						}
						break;
				}
			}
			path_parts[i] = text;

		// TODO: This is apparently not used in old etemplate
		/*
			var part = $j(document.createElement("li"))
				.addClass("vfsFilename")
				.text(text)
				.appendTo(this.span);
		*/
		}
		var part = $j(document.createElement("li"))
			.addClass("vfsFilename")
			.text(text)
			.appendTo(this.span);
	},

	/**
	 * Code for implementing et2_IDetachedDOM (data grid)
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
 */
var et2_vfsName = et2_textbox.extend({
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
 * vfs-mime
 * Icon for mimetype of file, or thumbnail
 */
var et2_vfsMime = et2_valueWidget.extend([et2_IDetachedDOM], {
	attributes: {
		"value": {
			"type": "any", // Object
			"description": "Array of (stat) information about the file"
		},
		"size": {
			"type": "integer",
			"description": "Size of icon / thumbnail, in pixels",
			"default": et2_no_init
		}
	},

	init: function() {
		this._super.apply(this, arguments);
		this.image = jQuery(document.createElement("img"));
		this.image.addClass("et2_vfs vfsMimeIcon");
		this.setDOMNode(this.image[0]);
	},
	set_value: function(_value) {
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
	},
	/**
	 * Implementation of "et2_IDetachedDOM" for fast viewing in gridview
	 * Override to add needed attributes
	 */
	getDetachedAttributes: function(_attrs) {
		_attrs.push("value", "class");
	},
	getDetachedNodes: function() {
		return [this.image[0]];
	},

	setDetachedAttributes: function(_nodes, _values) {
		this.image = jQuery(_nodes[0]);
		if(typeof _values['class'] != "undefined") {
			this.image.addClass(_values['class']);
		}
		if(typeof _values['value'] != "undefined") {
			this.set_value(_values['value']);
		}
	}
});
et2_register_widget(et2_vfsMime, ["vfs-mime"]);

/**
 * vfs-size
 * Human readable file sizes
 */
var et2_vfsSize = et2_description.extend({
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
			_value = _value.size
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
 * vfs-mode
 * Textual representation of permissions + extra bits
 */
var et2_vfsMode = et2_description.extend({
	// Masks for file types
	types: {
		'l': 0xA000, // link
		's': 0xC000,  // Socket
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
	init: function() {
		this._super.apply(this, arguments);
		this.span.addClass("et2_vfs");
	},

	/**
	 * Get text for file stuff
	 * Result will be like -rwxr--r--.  First char is type, then read, write, execute (or other bits) for
	 * user, group, world
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
			if(_value & this.types[flag])
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
			_value = _value.size
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
 * vfs-uid / vfs-gid
 * Displays the name for an ID.
 * Same as read-only selectAccount, except if there's no user it shows "root"
 */
var et2_vfsUid = et2_selectAccount_ro.extend({
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
 */
var et2_vfsUpload = et2_file.extend({
	
	asyncOptions: {
		url: egw_json_request.prototype._assembleAjaxUrl("etemplate_widget_vfs::ajax_upload::etemplate")
	},

	init: function() {
		this._super.apply(this, arguments);
		this.input.addClass("et2_vfs");
	},

	set_value: function(_value) {
	}
});
et2_register_widget(et2_vfsUpload, ["vfs-upload"]);
