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

/*egw:uses
	/vendor/bower-asset/jquery/dist/jquery.js;
	vfsSelectUI;
	et2_core_inputWidget;
	et2_core_valueWidget;
	et2_widget_description;
	et2_widget_file;
	expose;
*/

/**
 * Class which implements the "vfs" XET-Tag
 *
 * @augments et2_valueWidget
 */
var et2_vfs = (function(){ "use strict"; return et2_valueWidget.extend([et2_IDetachedDOM],
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
		this.span = jQuery(document.createElement("ul"))
			.addClass('et2_vfs');

		this.setDOMNode(this.span[0]);
	},

	getValue: function() {
		return this.value;
	},

	set_value: function(_value) {
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
		var path = _value.path ? _value.path : '/';
		// calculate path as parent of name, which can contain slashes
		// eg. _value.path=/home/ralf/sub/file, _value.name=sub/file --> path=/home/ralf
		// --> generate clickable fields for sub/ + file
		var sub_path = path.substr(0, _value.path.length-_value.name.length-1);
		if(_value.path.indexOf(_value.name) >= 0 && sub_path[sub_path.length-1] === '/')
		{
			path = sub_path;
			var path_offset = path.split('/').length;
			var path_parts = _value.path.split('/');
		}
		else
		{
			if(_value.path.indexOf(_value.name) >= 0)
			{
				// Remove name from end, so we can add it again later
				path = sub_path;
			}
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
									jQuery('li',this.span).last().text(title);
								}, this
							);
							if(link_title && typeof link_title !== 'undefined') text = link_title;
						}
						break;
				}
			}
			var self = this;
			var data = {path: path, type: i < path_parts.length-1 ? this.DIR_MIME_TYPE : _value.mime };
			jQuery(document.createElement("li"))
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


});}).call(this);
et2_register_widget(et2_vfs, ["vfs"]);

/**
 * vfs-name
 * filename automatically urlencoded on return (urldecoded on display to user)
 *
 * @augments et2_textbox
 */
var et2_vfsName = (function(){ "use strict"; return et2_textbox.extend(
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
		return egw.encodePath(this._super.apply(this)||'');
	}
});}).call(this);
et2_register_widget(et2_vfsName, ["vfs-name"]);

/**
 * vfs-name
 * filename automatically urlencoded on return (urldecoded on display to user)
 *
 * @augments et2_textbox_ro
 */
var et2_vfsName_ro = (function(){ "use strict"; return et2_textbox_ro.extend(
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
});}).call(this);
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
var et2_vfsMime = (function(){ "use strict"; return expose(et2_valueWidget.extend([et2_IDetachedDOM],
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
		},
		thumb_mime_size:{
			name: "Image thumbnail size",
			type: "string",
			default:"",
			description:" Size of thumbnail in pixel for specified mime type with syntax of: mime_type(s),size (eg. image,video,128)"
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
		var mediaContent = [{
			title: _value.name,
			type: _value.mime,
			href: _value.download_url
		}];
		// check if download_url is not already an url (some stream-wrappers allow to specify that!)
		if (_value.download_url && (_value.download_url[0] == '/' || _value.download_url.substr(0, 4) != 'http'))
		{
			mediaContent[0].href = base_url + _value.download_url;

			if (mediaContent[0].href && mediaContent[0].href.match(/\/webdav.php/,'ig'))
			{
				mediaContent[0].download_href = mediaContent[0].href + '?download';
			}
		}
		if (_value && _value.mime && _value.mime.match(/video\//,'ig'))
		{
			mediaContent[0].thumbnail = this.egw().mime_icon(_value.mime, _value.path, undefined, _value.mtime);
		}
		else
		{
			mediaContent[0].thumbnail = _value.path && _value.mime ?
				this.egw().mime_icon(_value.mime, _value.path, undefined, _value.mtime) :
				this.image.attr('src')+ '&thheight=128';
		}
		return mediaContent;
	},

	set_value: function(_value) {
		if (typeof _value !== 'object')
		{
			this.egw().debug("warn", "%s only has path, needs array with path & mime", this.id, _value);
			// Keep going, will be 'unknown type'
		}
		var src = this.egw().mime_icon(_value.mime, _value.path, undefined, _value.mtime);
		if(src)
		{
			// Set size of thumbnail
			if(src.indexOf("thumbnail.php") > -1)
			{
				if(this.options.size)
				{
					src += "&thsize="+this.options.size;
				}
				else if (this.options.thumb_mime_size)
				{
					var mime_size = this.options.thumb_mime_size.split(',');
					var mime_regex = RegExp(_value.mime.split('/')[0]);
					if (typeof mime_size != 'undefined' && jQuery.isArray(mime_size)
							&& !isNaN(mime_size[mime_size.length-1]) && isNaN(mime_size[0]) && this.options.thumb_mime_size.match(mime_regex[0], 'ig'))
					{
						src += "&thsize=" + mime_size[mime_size.length-1];
					}
				}
				this.image.css("max-width", "100%");
			}
			this.image.attr("src", src);
			// tooltip for mimetypes with available detailed thumbnail
			if (_value.mime.match(/application\/vnd\.oasis\.opendocument\.(text|presentation|spreadsheet|chart)/))
			{
				var tooltip_target = this.image.parent().parent().parent().length > 0 ?
					// Nextmatch row
					this.image.parent().parent().parent() :
					// Not in nextmatch
					this.image.parent();
				tooltip_target.tooltip({
					items:"img",
					position: {my:"right top", at:"left top", collision:"flipfit"},
					content: function(){
						return '<img src="'+this.src+'&thsize=512"/>';
					}
				});
			}
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
}));}).call(this);
et2_register_widget(et2_vfsMime, ["vfs-mime"]);

/**
 * vfs-size
 * Human readable file sizes
 *
 * @augments et2_description
 */
var et2_vfsSize = (function(){ "use strict"; return et2_description.extend({
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
});}).call(this);
et2_register_widget(et2_vfsSize, ["vfs-size"]);


/**
 * vfs-mode: textual representation of permissions + extra bits
 *
 * @augments et2_description
 */
var et2_vfsMode = (function(){ "use strict"; return et2_description.extend({
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
});}).call(this);
et2_register_widget(et2_vfsMode, ["vfs-mode"]);


/**
 * vfs-uid / vfs-gid: Displays the name for an ID.
 * Same as read-only selectAccount, except if there's no user it shows "root"
 *
 * @augments et2_selectAccount_ro
 */
var et2_vfsUid = (function(){ "use strict"; return et2_selectAccount_ro.extend(
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
});}).call(this);
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
var et2_vfsUpload = (function(){ "use strict"; return et2_file.extend(
{
	attributes: {
		"value": {
			"type": "any"	// Either nothing, or an object with file info
		},
		"path": {
			"name": "Path",
			"description": "Upload files to the specified VFS path",
			"type": "string",
			"default": ''
		}
	},
	legacyOptions: ["mime"],

	asyncOptions: {
		target: egw.ajaxUrl("EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_upload")
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
	},

	/**
	 * If there is a file / files in the specified location, display them
	 * Value is the information for the file[s] in the specified location.
	 *
	 * @param {Object[]} _value
	 */
	set_value: function(_value) {
		// Remove previous
		while(this._children.length > 0)
		{
			var node = this._children[this._children.length-1];
			this.removeChild(node);
			node.free();
		}
		this.progress.empty();
		this.list.empty();

		// Set new
		if(typeof _value == 'object' && _value && _value.length)
		{
			for(var i = 0; i < _value.length; i++)
			{
				this._addFile(_value[i]);
			}
		}
	},

	getDOMNode: function(sender) {
		if(sender !== this && sender._type.indexOf('vfs') >= 0 )
		{
			var value = sender.getValue && sender.getValue() || sender.options.value || {};
			var row =  jQuery("[data-path='"+(value.path.replace(/'/g, '&quot'))+"']",this.list);
			if(sender._type === 'vfs-mime')
			{
				return jQuery('.icon',row).get(0) || null;
			}
			else
			{
				return jQuery('.title',row).get(0) || null;
			}
		}
		else
		{
			return this._super.apply(this, arguments);
		}
	},

	/**
	 * Add in the request id
	 *
	 * @param {type} form
	 */
	beforeSend: function(form)
	{
		var instance = this.getInstanceManager();

		var extra = this._super.apply(this, arguments);
		extra.path = this.options.path;
		return extra;
	},

	/**
	 * A file upload is finished, update the UI
	 */
	finishUpload: function(file, response) {
		var result = this._super.apply(this, arguments);

		if(typeof response == 'string') response = jQuery.parseJSON(response);
		if(response.response[0] && typeof response.response[0].data.length == 'undefined') {
			for(var key in response.response[0].data) {
				var value = response.response[0].data[key];
				if(value && value.path)
				{
					this._addFile(value);
					jQuery("[data-file='"+file.fileName.replace(/'/g, '&quot')+"']",this.progress).hide();
				}
			}
		}
		return result;
	},

	_addFile: function(file_data) {
		if(jQuery("[data-path='"+file_data.path.replace(/'/g, '&quot')+"']").remove().length)
		{
			for(var child_index = this._children.length-1; child_index >= 0; child_index--)
			{
				var child = this._children[child_index];
				if(child.options.value.path === file_data.path)
				{
					this.removeChild(child);
					child.free();
				}
			}
		}
		var row = jQuery(document.createElement("tr"))
			.attr("data-path", file_data.path.replace(/'/g, '&quot'))
			.attr("draggable", "true")
			.appendTo(this.list);
		var mime = jQuery(document.createElement("td"))
			.addClass('icon')
			.appendTo(row);

		var title = jQuery(document.createElement("td"))
			.addClass('title')
			.appendTo(row);
		var mime = et2_createWidget('vfs-mime',{value: file_data},this);
		var vfs = et2_createWidget('vfs', {value: file_data}, this);

		// If already attached, need to do this explicitly
		if(this.isAttached())
		{
			mime.set_value(file_data);
			vfs.set_value(file_data);
			mime.doLoadingFinished();
			vfs.doLoadingFinished();
		}

		// Add in delete button
		if (!this.options.readonly)
		{
			var self = this;
			var delete_button = jQuery(document.createElement("td"))
				.appendTo(row);
			jQuery("<div />")
				.appendTo(delete_button)
				// We don't use ui-icon because it assigns a bg image
				.addClass("delete icon")
				.bind( 'click', function() {
					et2_createWidget("dialog", {
						callback: function(button) {
							if(button == et2_dialog.YES_BUTTON)
							{
								egw.json("filemanager_ui::ajax_action", [
										'delete',
										[row.attr('data-path').replace(/&quot/g, "'")],
										''
									],
									function(data) {
										if(data && data.errs == 0) {row.slideUp(row.remove);}
										if(data && data.msg) {
											self.egw().message(data.msg, data.errs == 0 ? 'success' : 'error');
										}
									}
								).sendRequest();
							}
						},
						message: self.egw().lang('Delete file')+'?',
						title: self.egw().lang('Confirmation required'),
						buttons: et2_dialog.BUTTONS_YES_NO,
						dialog_type: et2_dialog.QUESTION_MESSAGE,
						width: 250
					}, self);
				});
		}
	}
});}).call(this);
et2_register_widget(et2_vfsUpload, ["vfs-upload"]);


var et2_vfsSelect = (function(){ "use strict"; return et2_inputWidget.extend(
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
			description: "Server side callback to process selected value(s) in \n\
			app.class.method or class::method format.  The first parameter will \n\
			be Method ID, the second the file list. 'ckeditor' is reserved and it \n\
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
			description: "Custom icon to show on submit button.",
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
			description: "Extra buttons passed to dialog. It's co-related to method.",
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

		this.button = jQuery(document.createElement("button"))
			.attr("title", this.egw().lang("Select file(s) from VFS"))
			.addClass("et2_button et2_vfs_btn")
			.css("background-image","url("+this.egw().image("filemanager/navbar")+")");

		if(this.options.readonly)
		{
			this.button.hide();
		}

		if (this.options.button_caption != "")
		{
			this.button.text(this.options.button_caption);
		}
		this.setDOMNode(this.button[0]);
	},

	_content: function (_content, _callback)
	{
		egw(window).loading_prompt('vfs-select', true, '', 'body');
		var self = this;
		if (typeof app.vfsSelectUI !="undefined")
		{
			if (this.dialog && this.dialog.div) this.dialog.div.dialog('close');
			delete app.vfsSelectUI;
		}
		var attrs = {
			mode: this.options.mode,
			label: this.options.button_label,
			path: this.options.path || null,
			mime: this.options.mime || null,
			name: this.options.name,
			method: this.options.method
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
	},

	/**
	 * Builds file navigator dialog
	 *
	 * @param {object} _data content
	 */
	_buildDialog: function (_data)
	{
		if (!_data.content.mode.match(/open|open-multiple|saveas|select-dir/)) {
			egw.debug('Mode is not matched!');
			return;
		}
		var self = this;
		var buttons = [
			{
				text: egw.lang(_data.content.label),
				id:"submit",
				image: _data.content.mode.match(/saveas|select-dir/) ? "save" : this.options.button_icon
			}
		];
		if (this.options.extra_buttons && this.options.method)
		{
			var extra_buttons_action = [];
			for (var i=0; i < this.options.extra_buttons.length; i++)
			{
				buttons.push(this.options.extra_buttons[i]);
				extra_buttons_action[this.options.extra_buttons[i]['id']] = this.options.extra_buttons[i]['id'];
			}

		}
		buttons.push({text: egw.lang("Close"), id:"close"});
		var data = jQuery.extend(_data, {'currentapp': egw(window).app_name()});

		// define a mini app object for vfs select UI
		app.vfsSelectUI = new app.classes.vfsSelectUI;

		this.dialog = et2_createWidget("dialog",
		{
			callback: function(_button_id, _value)
			{
				if ((_button_id == 'submit' || (extra_buttons_action && extra_buttons_action[_button_id])) && _value)
				{
					var files = [];
					switch(_data.content.mode)
					{
						case 'open-multiple':
							if (_value.dir && _value.dir.selected)
							{
								for(var key in Object.keys(_value.dir.selected))
								{
									if (_value.dir.selected[key] != "")
									{
										files.push(_value.path+'/'+_value.dir.selected[key]);
									}
								}
							}
							break;
						case 'select-dir':
							files = _value.path;
							break;
						default:
							if (self.options.method === 'ckeditor') _value.path = _data.content.download_baseUrl;
							files = _value.path+'/'+_value.name;
							break;
					}
					self.value = files;
					if (self.options.method && self.options.method !== 'ckeditor')
					{
						egw(window).json(
							self.options.method,
							[self.options.method_id, files, _button_id],
							function(){
								jQuery(self.node).change();
							}
						).sendRequest(true);
					}
					else
					{
						jQuery(self.node).change();
					}
					delete app.vfsSelectUI;
				}
			},
			title: this.options.dialog_title,
			buttons: buttons,
			minWidth: 500,
			minHeight: 400,
			width:400,
			value: data,
			template: egw.webserverUrl+'/api/templates/default/vfsSelectUI.xet?1',
			resizable: false
		}, et2_dialog._create_parent('api'));
		this.dialog.template.uniqueId = 'api.vfsSelectUI';

		// Don't rely only on app_name to fetch et2 object as app_name may not
		// always represent current app of the window, e.g.: mail admin account.
		// Try to fetch et2 from its template name.
		var etemplate = jQuery('form').data('etemplate');
		var et2 = {};
		if (etemplate && etemplate.name && !app[egw(window).app_name()])
		{
			et2 = etemplate2.getByTemplate(etemplate.name)[0]
		}
		else
		{
			et2 = etemplate2.getByApplication(egw(window).app_name())[0];
		}
		// we need an etemplate_exec_id for better handling serverside parts of
		// widgets and since we can not have a etemplate_exec_id specifically
		// for dialog template our best shot is to inherit its parent etemplate_exec_id.
		this.dialog.template.etemplate_exec_id = et2.etemplate_exec_id;

		app.vfsSelectUI.et2 = this.dialog.template.widgetContainer;
		// Keep the dialog always at the top, seems CKEDITOR dialogs have very
		// high z-index set.
		this.dialog.div.parent().css({"z-index": 100000});
		app.vfsSelectUI.vfsSelectWidget = this;
		this.dialog.div.on('load', function(e) {
			app.vfsSelectUI.et2_ready(app.vfsSelectUI.et2, 'api.vfsSelectUI');
		});
	},

	/**
	 * click handler
	 * @param {event object} e
	 */
	click: function(e) {
		this._content.call(this, null);
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

	set_readonly: function(readonly) {
		this.options.readonly = Boolean(readonly);

		if(this.options.readonly)
		{
			this.button.hide();
		}
		else
		{
			this.button.show();
		}
	},

	getValue: function() {
		return this.value;
	}
});}).call(this);
et2_register_widget(et2_vfsSelect, ["vfs-select"]);
