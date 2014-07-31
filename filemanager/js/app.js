/**
 * EGroupware - Filemanager - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-14 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for filemanager
 *
 * @augments AppJS
 */
app.classes.filemanager = AppJS.extend(
{
	appname: 'filemanager',
	/**
	 * path widget
	 */
	path_widget: null,
	/**
	 * Array of pathes from files in clipboard
	 */
	clipboard_files: [],
	/**
	 * Are files cut into clipboard - need to be deleted at source on paste
	 */
	clipboard_is_cut: false,
	/**
	 * Key for storing clipboard in browser localstorage
	 */
	clipboard_localstore_key: 'filemanager.clipboard',

	/**
	 * Constructor
	 *
	 * @memberOf app.filemanager
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		delete this.et2;
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2)
	{
		// call parent
		this._super.apply(this, arguments);

		this.path_widget = this.et2.getWidgetById('path');

		// get clipboard from browser localstore and update button tooltips
		if (typeof window.localStorage != 'undefined' && typeof window.localStorage[this.clipboard_localstore_key] != 'undefined')
		{
			this.clipboard_files = JSON.parse(window.localStorage[this.clipboard_localstore_key]);
		}
		this.clipboard_tooltips();

		if (typeof this.readonly != 'undefined')
		{
			this.set_readonly.apply(this, this.readonly);
			delete this.readonly;
		}
	},

	/**
	 * Regexp to convert id to a path, use this.id2path(_id)
	 */
	remove_prefix: /^filemanager::/,
	/**
	 * Convert id to path (remove "filemanager::" prefix)
	 *
	 * @param {string} _id
	 * @returns string
	 */
	id2path: function(_id)
	{
		return _id.replace(this.remove_prefix, '');
	},

	/**
	 * Convert array of elems to array of paths
	 *
	 * @param {array} _elems selected items from actions
	 * @return array
	 */
	_elems2paths: function(_elems)
	{
		var paths = [];
		for (var i = 0; i < _elems.length; i++)
		{
			paths.push(this.id2path(_elems[i].id));
		}
		return paths;
	},

	/**
	 * Get directory of a path
	 *
	 * @param {string} _path
	 * @returns string
	 */
	dirname: function(_path)
	{
		var parts = _path.split('/');
		parts.pop();
		return parts.join('/') || '/';
	},

	/**
	 * Get name of a path
	 *
	 * @param {string} _path
	 * @returns string
	 */
	basename: function(_path)
	{
		return _path.split('/').pop();
	},

	/**
	 * Get current working directory
	 *
	 * @return string
	 */
	get_path: function()
	{
		return this.path_widget ? this.path_widget.get_value() : null;
	},

	/**
	 * Open compose with already attached files
	 *
	 * @param {(string|string[])} attachments path(s)
	 */
	open_mail: function(attachments)
	{
		if (typeof attachments == 'undefined') attachments = this.clipboard_files;
		var params = {};
		if (!(attachments instanceof Array)) attachments = [ attachments ];
		for(var i=0; i < attachments.length; i++)
		{
		   params['preset[file]['+i+']'] = 'vfs://default'+attachments[i];
		}
		egw.open('', 'mail', 'add', params);
	},

	/**
	 * Mail files action: open compose with already attached files
	 *
	 * @param _action
	 * @param _elems
	 */
	mail: function(_action, _elems)
	{
		this.open_mail(this._elems2paths(_elems));
	},

	/**
	 * Send names of uploaded files (again) to server, to process them: either copy to vfs or ask overwrite/rename
	 *
	 * @param {event} _event
	 * @param {number} _file_count
	 * @param {string=} _path where the file is uploaded to, default current directory
	 */
	upload: function(_event, _file_count, _path)
	{
		if(typeof _path == 'undefined')
		{
			_path = this.get_path();
		}
		if (_file_count && !jQuery.isEmptyObject(_event.data.getValue()))
		{
			var widget = _event.data;
			var request = egw.json('filemanager_ui::ajax_action', ['upload', widget.getValue(), _path],
				this._upload_callback, this, true, this
			).sendRequest();
			widget.set_value('');
		}
	},

	/**
	 * Finish callback for file a file dialog, to get the overwrite / rename prompt
	 *
	 * @param {event} _event
	 * @param {number} _file_count
	 */
	file_a_file_upload: function(_event, _file_count)
	{
		var widget = _event.data;
		var value = widget.getValue();
		var path = widget.getRoot().getWidgetById("path").getValue();
		var action = widget.getRoot().getWidgetById("action").getValue();
		var link = widget.getRoot().getWidgetById("entry").getValue();
		if(action == 'save_as' && link.app && link.id)
		{
			path = "/apps/"+link.app+"/"+link.id;
		}

		var props = widget.getInstanceManager().getValues(widget.getRoot());
		egw.json('filemanager_ui::ajax_action', [action == 'save_as' ? 'upload' : 'link', widget.getValue(), path, props],
				function(_data)
				{
					app.filemanager._upload_callback(_data);

					// Remove successful after a delay
					for(var file in _data.uploaded)
					{
						if(!_data.uploaded[file].confirm || _data.uploaded[file].confirmed)
						{
							// Remove that file from file widget...
							widget.remove_file(_data.uploaded[file].name);
						}
					}
					opener.egw_refresh('','filemanager',null,null,'filemanager');
				}, app.filemanager, true, this
		).sendRequest(true);
		return true;
	},


	/**
	 * Callback for server response to upload request:
	 * - display message and refresh list
	 * - ask use to confirm overwritting existing files or rename upload
	 *
	 * @param {object} _data values for attributes msg, files, ...
	 */
	_upload_callback: function(_data)
	{
		if (_data.msg || _data.uploaded) window.egw_refresh(_data.msg, this.appname);

		var that = this;
		for(var file in _data.uploaded)
		{
			if (_data.uploaded[file].confirm && !_data.uploaded[file].confirmed)
			{
				var buttons = [
					{text: this.egw.lang("Yes"), id: "overwrite", class: "ui-priority-primary", "default": true},
					{text: this.egw.lang("Rename"), id:"rename"},
					{text: this.egw.lang("Cancel"), id:"cancel"}
				];
				if (_data.uploaded[file].confirm === "is_dir")
					buttons.shift();
				var dialog = et2_dialog.show_prompt(function(_button_id, _value) {
					var uploaded = {};
					uploaded[this.my_data.file] = this.my_data.data;
					switch (_button_id)
					{
						case "overwrite":
							uploaded[this.my_data.file].confirmed = true;
							// fall through
						case "rename":
							uploaded[this.my_data.file].name = _value;
							delete uploaded[this.my_data.file].confirm;
							// send overwrite-confirmation and/or rename request to server
							egw.json('filemanager_ui::ajax_action', [this.my_data.action, uploaded, this.my_data.path, this.my_data.props],
								that._upload_callback, that, true, that
							).sendRequest();
							return;
						case "cancel":
							// Remove that file from every file widget...
							that.et2.iterateOver(function(_widget) {
								_widget.remove_file(this.my_data.data.name);
							}, this, et2_file);
					}
				},
				_data.uploaded[file].confirm === "is_dir" ?
					this.egw.lang("There's already a directory with that name!") :
					this.egw.lang('Do you want to overwrite existing file %1 in directory %2?', _data.uploaded[file].name, _data.path),
				this.egw.lang('File %1 already exists', _data.uploaded[file].name),
				_data.uploaded[file].name, buttons, file);
				// setting required data for callback in as my_data
				dialog.my_data = {
					action: _data.action,
					file: file,
					path: _data.path,
					data: _data.uploaded[file],
					props: _data.props
				};
			}
		}
	},

	/**
	 * Update clickboard tooltips in buttons
	 */
	clipboard_tooltips: function()
	{
		var paste_buttons = ['button[paste]', 'button[linkpaste]', 'button[mailpaste]'];
		for(var i=0; i < paste_buttons.length; ++i)
		{
			var button = this.et2.getWidgetById(paste_buttons[i]);
			if (button) button.set_statustext(this.clipboard_files.join(",\n"));
		}
	},

	/**
	 * Clip files into clipboard
	 *
	 * @param _action
	 * @param _elems
	 */
	clipboard: function(_action, _elems)
	{
		this.clipboard_is_cut = _action.id == "cut";
		if (_action.id != "add") this.clipboard_files = [];

		this.clipboard_files = this.clipboard_files.concat(this._elems2paths(_elems));

		if (_action.id == "add" && this.clipboard_files.length > 1)
		{
			// ToDo: make sure files are unique
		}
		this.clipboard_tooltips();

		// update localstore with files
		if (typeof window.localStorage != 'undefined')
		{
			window.localStorage[this.clipboard_localstore_key] = JSON.stringify(this.clipboard_files);
		}
	},

	/**
	 * Paste files into current directory or mail them
	 *
	 * @param _type 'paste', 'linkpaste', 'mailpaste'
	 */
	paste: function(_type)
	{
		if (this.clipboard_files.length == 0)
		{
			alert(this.egw.lang('Clipboard is empty!'));
			return;
		}
		switch(_type)
		{
			case 'mailpaste':
				this.open_mail(this.clipboard_files);
				break;

			case 'paste':
				this._do_action(this.clipboard_is_cut ? 'move' : 'copy', this.clipboard_files);

				if (this.clipboard_is_cut)
				{
					this.clipboard_is_cut = false;
					this.clipboard_files = [];
					this.clipboard_tooltips();
				}
				break;

			case 'linkpaste':
				this._do_action('symlink', this.clipboard_files);
				break;
		}
	},

	/**
	 * Pass action to server
	 *
	 * @param _action
	 * @param _elems
	 */
	action: function(_action, _elems)
	{
		var paths = this._elems2paths(_elems);
		this._do_action(_action.id, paths);
	},

	/**
	 * Prompt user for directory to create
	 */
	createdir: function()
	{
		var dir = prompt(this.egw.lang('New directory'));

		if (dir)
		{
			var path = this.get_path();
			this._do_action('createdir', dir, true);	// true=synchronous request
			this.change_dir(path+'/'+dir);
		}
	},

	/**
	 * Prompt user for directory to create
	 */
	symlink: function()
	{
		var target = prompt(this.egw.lang('Link target'));

		if (target)
		{
			this._do_action('symlink', target);
		}
	},

	/**
	 * Run a serverside action via an ajax call
	 *
	 * @param _type 'move_file', 'copy_file', ...
	 * @param _selected selected paths
	 * @param _sync send a synchronous ajax request
	 * @param _path defaults to current path
	 */
	_do_action: function(_type, _selected, _sync, _path)
	{
		if (typeof _path == 'undefined') _path = this.get_path();
		var request = egw.json('filemanager_ui::ajax_action', [_type, _selected, _path],
			this._do_action_callback, this, !_sync, this
		).sendRequest();
	},

	/**
	 * Callback for _do_action ajax call
	 *
	 * @param _data
	 */
	_do_action_callback: function(_data)
	{
		window.egw_refresh(_data.msg, this.appname);
	},

	/**
	 * Force download of a file by appending '?download' to it's download url
	 *
	 * @param _action
	 * @param _senders
	 */
	force_download: function(_action, _senders)
	{
		for(var i = 0; i < _senders.length; i++)
		{
			var data = egw.dataGetUIDdata(_senders[i].id);
			var url = data ? data.data.download_url : '/webdav.php'+this.id2path(_senders[i].id);
			if (url[0] == '/') url = egw.link(url);
			
			var a = document.createElement('a');
			if(typeof a.download == "undefined")
			{
				window.location = url+"?download";
				return false;
			}

			// Multiple file download for those that support it
			a = $j(a)
				.prop('href', url)
				.prop('download', data ? data.data.name : "")
				.appendTo(this.et2.getDOMNode());

			var evt = document.createEvent('MouseEvent');
			evt.initMouseEvent('click', true, true, window, 1, 0, 0, 0, 0, false, false, false, false, 0, null);
			a[0].dispatchEvent(evt);
			a.remove();
		}
		return false;
	},

	/**
	 * Check to see if the browser supports downloading multiple files
	 * (using a tag download attribute) to enable/disable the context menu
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	is_multiple_allowed: function(action, selected)
	{
		var allowed = typeof document.createElement('a').download != "undefined";
		
		if(typeof action == "undefined") return allowed;

		return (allowed || selected.length <= 1) && action.not_disableClass.apply(action, arguments);
	},


	/**
	 * Change directory
	 *
	 * @param _dir directory to change to incl. '..' for one up
	 */
	change_dir: function(_dir)
	{
		switch (_dir)
		{
			case '..':
				_dir = this.dirname(this.path_widget.getValue());
				break;
			case '~':
				_dir = this.et2.getWidgetById('nm').options.settings.home_dir;
				break;
		}
		this.path_widget.set_value(_dir);
	},

	/**
	 * Open/active an item
	 *
	 * @param _action
	 * @param _senders
	 */
	open: function(_action, _senders)
	{
		var data = egw.dataGetUIDdata(_senders[0].id);
		var path = this.id2path(_senders[0].id);
		// symlinks dont have mime 'http/unix-directory', but server marks all directories with class 'isDir'
		if (data.data.mime == 'httpd/unix-directory' || data.data['class'] && data.data['class'].split(/ +/).indexOf('isDir') != -1)
		{
			this.change_dir(path);
		}
		else
		{
			egw.open({path: path, type: data.data.mime}, 'file');
		}
	},

	/**
	 * Edit prefs of current directory
	 *
	 * @param _action
	 * @param _senders
	 */
	editprefs: function(_action, _senders)
	{
		var path =  typeof _senders != 'undefined' ? this.id2path(_senders[0].id) : this.path_widget.getValue();

		egw().open_link(egw.link('/index.php', {
			menuaction: 'filemanager.filemanager_ui.file',
			path: path
		}), 'fileprefs', '495x425');
	},

	/**
	 * File(s) droped
	 *
	 * @param _action
	 * @param _elems
	 * @param _target
	 * @returns
	 */
	drop: function(_action, _elems, _target)
	{
		var src = this._elems2paths(_elems);
		var dst = this.id2path(_target.id);
		//alert(_action.id+': '+src.join(', ')+' --> '+dst);

		// check if target is a file --> use it's directory instead
		var data = egw.dataGetUIDdata(_target.id);
		if (data.data.mime != 'httpd/unix-directory')
		{
			dst = this.dirname(dst);
		}

		this._do_action(_action.id == "file_drop_move" ? 'move' : 'copy', src, false, dst);
	},

	/**
	 * Handle a native / HTML5 file drop from system
	 *
	 * This is a callback from nextmatch to prevent the default link action, and just upload instead.
	 *
	 * @param {string} row_uid UID of the row the files were dropped on
	 * @param {et2_nextmatch} widget widget that got the drop
	 * @param {array} files
	 */
	filedrop: function(row_uid, widget ,files)
	{
		var self = this;
		var data = egw.dataGetUIDdata(row_uid);
		files = files || window.event.dataTransfer.files;

		var path = typeof data != 'undefined' && data.data.mime == "httpd/unix-directory" ? data.data.path : this.get_path();
		var widget = this.et2.getWidgetById('upload');

		// Override finish to specify a potentially different path
		var old_onfinish = widget.options.onFinish;

		widget.options.onFinish = function(_event, _file_count) {
			widget.options.onFinish = old_onfinish;
			self.upload(_event, _file_count, path);
		};
		// This triggers the upload
		widget.set_value(files);

		// Return false to prevent the link
		return false;
	},

	/**
	 * Get drag helper, called on drag start
	 *
	 * @param {egwAction} _action
	 * @param {array} _elems
	 * @return some dome objects
	 */
	drag: function(_action, _elems)
	{
		var icons = [];
		for (var i = 0; i < _elems.length; i++)
		{
			var data = egw.dataGetUIDdata(_elems[i].id);
			var src = egw.mime_icon(data.data.mime, data.data.path);

			if (_elems[i].getFocused())
			{
				icons.unshift(src);
			}
			else
			{
				icons.push(src);
			}
		}

		// Only take a maximum of 10 icons
		var maxCnt = 10;

		var div = $j(document.createElement("div"))
			.css({
				position: 'absolute',
				top: '0px',
				left: '0px',
				width: '300px'
			});

		var lastIcon = "";
		var idx = 0;

		for (var i = 0; i < icons.length; i++)
		{
			if (icons[i] != lastIcon)
			{
				lastIcon = icons[i];

				// Create a stack of images
				var img = $j(document.createElement('img'));
				img.css({
					position: 'absolute',
					'z-index': 10000-i,
					top: idx*5,
					left: idx*5,
					opacity: (maxCnt - idx) / maxCnt
				});
				img.attr('src', icons[i]);
				div.append(img);

				idx++;
				if (idx == maxCnt)
				{
					break;
				}
			}
		}
		var text = $j(document.createElement('div')).css({left: '30px', position: 'absolute'});
		// add filename or number of files for multiple files
		text.text(_elems.length > 1 ? _elems.length+' '+this.egw.lang('files') : this.basename(_elems[0].id));
		div.append(text);

		// Add notice of Ctrl key, if supported
		if(window.FileReader && 'draggable' in document.createElement('span') &&
			navigator && navigator.userAgent.indexOf('Chrome') >= 0)
		{
			var key = ["Mac68K","MacPPC","MacIntel"].indexOf(window.navigator.platform) < 0 ? 'Ctrl' : 'Command';
			text.append('<br />' + this.egw.lang('Hold %1 to drag files to your computer',key));
		}
		return div;
	},

	/**
	 * Change readonly state for given directory
	 *
	 * Get call/transported with each get_rows call, but should only by applied to UI if matching curent dir
	 *
	 * @param {string} _path
	 * @param {boolean} _ro
	 */
	set_readonly: function(_path, _ro)
	{
		//alert('set_readonly("'+_path+'", '+_ro+')');
		if (!this.path_widget)	// widget not yet ready, try later
		{
			this.readonly = [_path, _ro];
			return;
		}
		var path =  this.path_widget.getValue();

		if (_path == path)
		{
			var ids = ['button[linkpaste]', 'button[paste]', 'button[createdir]', 'button[symlink]', 'upload'];
			for(var i=0; i < ids.length; ++i)
			{
				var widget = this.et2.getWidgetById(ids[i]);
				if (widget)
				{
					if (widget._type == 'button' || widget._type == 'buttononly')
					{
						widget.set_readonly(_ro);
					}
					else
					{
						widget.set_disabled(_ro);
					}
				}
			}
		}
	},

	/**
	 * Row or filename in select-file dialog clicked
	 *
	 * @param {jQuery.event} event
	 * @param {et2_widget} widget
	 */
	select_clicked: function(event, widget)
	{
		if (!widget || typeof widget.value != 'object')
		{

		}
		else if (widget.value.is_dir)
		{
			var path = null;
			// Cannot do this, there are multiple widgets named path
			// widget.getRoot().getWidgetById("path");
			widget.getRoot().iterateOver(function(widget) {
				if(widget.id == "path") path = widget;
			},null, et2_textbox);
			if(path)
			{
				path.set_value(widget.value.path);
			}
		}
		else if (this.et2 && this.et2.getArrayMgr('content').getEntry('mode') != 'open-multiple')
		{
			var editfield = this.et2.getWidgetById('name');
			if(editfield)
			{
				editfield.set_value(widget.value.name);
			}
		}
		else
		{
			var file = widget.value.name;
			widget.getParent().iterateOver(function(widget)
			{
				if(widget.options.selected_value == file)
				{
					widget.set_value(widget.get_value() == file ? widget.options.unselected_value : file);
				}
			}, null, et2_checkbox);

		}
		// Stop event or it will toggle back off
		event.preventDefault();
		event.stopPropagation();
		return false;
	},
	
	/**
	 * Set Sudo button's label and change its onclick handler according to its action
	 * 
	 * @param {widget object} _widget sudo buttononly
	 * @param {string} _action string of action type {login|logout}
	 */
	set_sudoButton: function (_widget, _action)
	{
		var widget = _widget || this.et2.getWidgetById('sudouser');
		if (widget)
		{
			switch (_action)
			{
				case 'login':
					widget.set_label('Logout');
					this.et2._inst.submit(widget);
					break;
					
				default:
					widget.set_label('Superuser');
					widget.onclick = function(){
						jQuery('.superuser').css('display','inline');
					}
			}
		}
	}
});
