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

/*egw:uses
	/api/js/webodf/wodotexteditor/wodotexteditor/wodotexteditor.js;
	/api/js/webodf/wodotexteditor/wodotexteditor/webodf.js;
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
	 * path widget, by template
	 */
	path_widget: {},
	/**
	 * Are files cut into clipboard - need to be deleted at source on paste
	 */
	clipboard_is_cut: false,

	/*
	 * odf editor object
	 */
	editor: {},

	/**
	 * Constructor
	 *
	 * @memberOf app.filemanager
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);

		// Loading filemanager in its tab and home causes us problems with
		// unwanted destruction, so we check for already existing path widgets
		var lists = etemplate2.getByApplication('home');
		for(var i = 0; i < lists.length; i++)
		{
			if(lists[i].app == 'filemanager' && lists[i].widgetContainer.getWidgetById('path'))
			{
				this.path_widget[lists[i].uniqueId] = lists[i].widgetContainer.getWidgetById('path');
			}
		}
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		delete this.et2;

		delete this.editor;
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 * @param {string} name template name
	 */
	et2_ready: function(et2,name)
	{
		// call parent
		this._super.apply(this, arguments);

		var path_widget = this.et2.getWidgetById('path');
		if(path_widget)	// do NOT set not found path-widgets, as uploads works on first one only!
		{
			this.path_widget[et2.DOMContainer.id] = path_widget;
			// Bind to removal to remove from list
			jQuery(et2.DOMContainer).on('clear', function(e) {
				if (app.filemanager && app.filemanager.path_widget) delete app.filemanager.path_widget[e.target.id];
			});
		}

		if(this.et2.getWidgetById('nm'))
		{
			// Legacy JS only supports 2 arguments (event and widget), so set
			// to the actual function here
			this.et2.getWidgetById('nm').set_onfiledrop(jQuery.proxy(this.filedrop, this));
		}

		// get clipboard from browser localstore and update button tooltips
		this.clipboard_tooltips();

		// calling set_readonly for initial path
		if (this.et2.getArrayMgr('content').getEntry('initial_path_readonly'))
		{
			this.readonly = [this.et2.getArrayMgr('content').getEntry('nm[path]'), true];
		}
		if (typeof this.readonly != 'undefined')
		{
			this.set_readonly.apply(this, this.readonly);
			delete this.readonly;
		}

		if (name == "filemanager.editor")
		{
			this._init_odf_editor ();
		}
	},

	/**
	 * Set the application's state to the given state.
	 *
	 * Extended from parent to also handle view
	 *
	 *
	 * @param {{name: string, state: object}|string} state Object (or JSON string) for a state.
	 *	Only state is required, and its contents are application specific.
	 *
	 * @return {boolean} false - Returns false to stop event propagation
	 */
	setState: function(state)
	{
		// State should be an object, not a string, but we'll parse
		if(typeof state == "string")
		{
			if(state.indexOf('{') != -1 || state =='null')
			{
				state = JSON.parse(state);
			}
		}
		var result = this._super.call(this, state, 'filemanager.index');

		// This has to happen after the parent, changing to tile recreates
		// nm controller
		if(typeof state == "object" && state.state && state.state.view)
		{
			var et2 = etemplate2.getById('filemanager-index');
			if(et2)
			{
				this.et2 = et2.widgetContainer;
				this.change_view(state.state.view);
			}
		}
		return result;
	},

	/**
	 * Retrieve the current state of the application for future restoration
	 *
	 * Extended from parent to also set view
	 *
	 * @return {object} Application specific map representing the current state
	 */
	getState: function()
	{
		var state = this._super.apply(this);

		var et2 = etemplate2.getById('filemanager-index');
		if(et2)
		{
			var nm = et2.widgetContainer.getWidgetById('nm');
			state.view = nm.view;
		}

		return state;
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
	 * @param {egwActionObject[]} _elems selected items from actions
	 * @return array
	 */
	_elems2paths: function(_elems)
	{
		var paths = [];
		for (var i = 0; i < _elems.length; i++)
		{
			// If selected has no id, try parent.  This happens for the placeholder row
			// in empty directories.
			paths.push(_elems[i].id? this.id2path(_elems[i].id) : _elems[i]._context._parentId);
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
	 * @param {string} etemplate_name
	 * @return string
	 */
	get_path: function(etemplate_name)
	{
		if(!etemplate_name || typeof this.path_widget[etemplate_name] == 'undefined')
		{
			for(etemplate_name in this.path_widget) break;
		}
		return this.path_widget[etemplate_name] ? this.path_widget[etemplate_name].get_value() : null;
	},

	/**
	 * Open compose with already attached files
	 *
	 * @param {(string|string[])} attachments path(s)
	 * @param {object} params
	 */
	open_mail: function(attachments, params)
	{
		if (typeof attachments == 'undefined') attachments = this.get_clipboard_files();
		if (!params || typeof params != 'object') params = {};
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
		this.open_mail(this._elems2paths(_elems), {
			'preset[filemode]': _action.id.substr(5)
		});
	},

	/**
	 * Trigger Upload after each file is uploaded
	 * @param {type} _event
	 */
	uploadOnOne: function (_event)
	{
		this.upload(_event,1);
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
	 * Get any files that are in the system clipboard
	 *
	 * @return {string[]} Paths
	 */
	get_clipboard_files: function()
	{
		var clipboard_files = [];
		if (typeof window.localStorage != 'undefined' && typeof egw.getSessionItem('phpgwapi', 'egw_clipboard') != 'undefined')
		{
			var clipboard = JSON.parse(egw.getSessionItem('phpgwapi', 'egw_clipboard')) || {
				type:[],
				selected:[]
			};
			if(clipboard.type.indexOf('file') >= 0)
			{
				for(var i = 0; i < clipboard.selected.length; i++)
				{
					var split = clipboard.selected[i].id.split('::');
					if(split[0] == 'filemanager')
					{
						clipboard_files.push(this.id2path(clipboard.selected[i].id));
					}
				}
			}
		}
		return clipboard_files;
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
			if (button) button.set_statustext(this.get_clipboard_files().join(",\n"));
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
		var clipboard = JSON.parse(egw.getSessionItem('phpgwapi', 'egw_clipboard')) || {
			type:[],
			selected:[]
		};
		if(_action.id != "add")
		{
			clipboard = {
				type:[],
				selected:[]
			};
		};

		// When pasting we need to know the type of data - pull from actions
		var drag = _elems[0].getSelectedLinks('drag').links;
		for(var k in drag)
		{
			if(drag[k].enabled && drag[k].actionObj.dragType.length > 0)
			{
				clipboard.type = clipboard.type.concat(drag[k].actionObj.dragType);
			}
		}
		clipboard.type = jQuery.unique(clipboard.type);
		// egwAction is a circular structure and can't be stringified so just take what we want
		// Hopefully that's enough for the action handlers
		for(var k in _elems)
		{
			if(_elems[k].id) clipboard.selected.push({id:_elems[k].id, data:_elems[k].data});
		}

		// Save it in session
		egw.setSessionItem('phpgwapi', 'egw_clipboard', JSON.stringify(clipboard));

		this.clipboard_tooltips();
	},

	/**
	 * Paste files into current directory or mail them
	 *
	 * @param _type 'paste', 'linkpaste', 'mailpaste'
	 */
	paste: function(_type)
	{
		var clipboard_files = this.get_clipboard_files();
		if (clipboard_files.length == 0)
		{
			alert(this.egw.lang('Clipboard is empty!'));
			return;
		}
		switch(_type)
		{
			case 'mailpaste':
				this.open_mail(clipboard_files);
				break;

			case 'paste':
				this._do_action(this.clipboard_is_cut ? 'move' : 'copy', clipboard_files);

				if (this.clipboard_is_cut)
				{
					this.clipboard_is_cut = false;
					clipboard_files = [];
					this.clipboard_tooltips();
				}
				break;

			case 'linkpaste':
				this._do_action('symlink', clipboard_files);
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
		var path = this.get_path(_action && _action.parent.data.nextmatch.getInstanceManager().uniqueId || false);
		this._do_action(_action.id, paths,true, path);
	},

	/**
	 * Prompt user for directory to create
	 *
	 * @param {egwAction|undefined|jQuery.Event} action Action, event or undefined if called directly
	 * @param {egwActionObject[] | undefined} selected Selected row, or undefined if called directly
	 */
	createdir: function(action, selected)
	{
		var self = this;
		et2_dialog.show_prompt(function(button, dir){
			if (button && dir)
			{
				var path = self.get_path(action && action.parent ? action.parent.data.nextmatch.getInstanceManager().uniqueId : false);
				if(action && action instanceof egwAction)
				{
					var paths = self._elems2paths(selected);
					if(paths[0]) path = paths[0];
					// check if target is a file --> use it's directory instead
					if(selected[0].id || path)
					{
						var data = egw.dataGetUIDdata(selected[0].id || 'filemanager::'+path );
						if (data && data.data.mime != 'httpd/unix-directory')
						{
							path = self.dirname(path);
						}
					}
				}
				self._do_action('createdir', dir, true, path);	// true=synchronous request
				self.change_dir((path == '/' ? '' : path)+'/'+ dir);
			}
		},this.egw.lang('New directory'),this.egw.lang('Create directroy'));
	},

	/**
	 * Prompt user for directory to create
	 */
	symlink: function()
	{
		var self = this;
		et2_dialog.show_prompt(function (button, target) {
			if (button && target)
			{
				self._do_action('symlink', target);
			}
		},this.egw.lang('Link target'), this.egw.lang('Create link'));
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
			a = jQuery(a)
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
	 * @param {string} _dir directory to change to incl. '..' for one up
	 * @param {et2_widget} widget
	 */
	change_dir: function(_dir, widget)
	{
		for(var etemplate_name in this.path_widget) break;
		if (widget) etemplate_name = widget.getInstanceManager().uniqueId;

		switch (_dir)
		{
			case '..':
				_dir = this.dirname(this.get_path(etemplate_name));
				break;
			case '~':
				_dir = this.et2.getWidgetById('nm').options.settings.home_dir;
				break;
		}

		this.path_widget[etemplate_name].set_value(_dir);
	},

	/**
	 * Toggle view between tiles and rows
	 *
	 * @param {string|Event} [view] - Specify what to change the view to.  Either 'tile' or 'row'.
	 *	Or, if this is used as a callback view is actually the event, and we need to find the view.
	 * @param {et2_widget} [button_widget] - The widget that's calling
	 */
	change_view: function(view, button_widget)
	{
		var et2 = etemplate2.getById('filemanager-index');
		var nm = false;
		if(et2 && et2.widgetContainer.getWidgetById('nm'))
		{
			nm = et2.widgetContainer.getWidgetById('nm');
		}
		if(!nm)
		{
			egw.debug('warn', 'Could not find nextmatch to change view');

			return;
		}

		if(!button_widget)
		{
			button_widget = nm.getWidgetById('button[change_view]');
		}
		if(button_widget && button_widget.instanceOf(et2_button))
		{
			// Switch view based on button icon, since controller can get re-created
			if(typeof view != 'string')
			{
				view = button_widget.options.image.replace('list_','');
			}

			// Toggle button icon to the other view
			button_widget.set_image("list_"+(view == nm.controller.VIEW_ROW ? nm.controller.VIEW_TILE : nm.controller.VIEW_ROW));

			button_widget.set_statustext(view == nm.controller.VIEW_ROW ? this.egw.lang("Tile view") : this.egw.lang('List view'));
		}

		nm.set_view(view);
		// Put it into active filters (but don't refresh)
		nm.activeFilters.view = view;

		// Change template to match
		var template = view == nm.controller.VIEW_ROW ? 'filemanager.index.rows' : 'filemanager.tile';
		nm.set_template(template);

		// Wait for template to load, then refresh
		template = nm.getWidgetById(template);
		if(template && template.loading)
		{
			template.loading.done(function() {
				nm.applyFilters({view: view});
			});
		}
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
		var mime = this.et2._inst.widgetContainer.getWidgetById('$row');
		// try to get mime widget DOM node out of the row DOM
		var mime_dom = jQuery(_senders[0].iface.getDOMNode()).find("span#filemanager-index_\\$row");

		// symlinks dont have mime 'http/unix-directory', but server marks all directories with class 'isDir'
		if (data.data.mime == 'httpd/unix-directory' || data.data['class'] && data.data['class'].split(/ +/).indexOf('isDir') != -1)
		{
			this.change_dir(path,_action.parent.data.nextmatch || this.et2);
		}
		else if(mime && data.data.mime.match(mime.mime_regexp) && mime_dom.length>0)
		{
			mime_dom.click();
		}
		else if (_action.id == 'modify' && mime && data.data.mime.match(mime.mime_odf_regex))
		{
			egw.open_link(egw.link('/index.php', {
				menuaction: 'filemanager.filemanager_ui.editor',
				path: data.data.download_url
			}), '', '800x600');
		}
		else
		{
			// Build ViewerJS url
			if (data.data.mime.match(/application\/vnd\.oasis\.opendocument/)) var url = '/ViewerJS/#..' + data.data.download_url;

			egw.open({path: path, type: data.data.mime, download_url: url}, 'file','view',null,'_browser');
		}
		return false;
	},

	/**
	 * Edit prefs of current directory
	 *
	 * @param _action
	 * @param _senders
	 */
	editprefs: function(_action, _senders)
	{
		var path =  typeof _senders != 'undefined' ? this.id2path(_senders[0].id) : this.get_path(_action && _action.parent.data.nextmatch.getInstanceManager().uniqueId || false);

		egw().open_link(egw.link('/index.php', {
			menuaction: 'filemanager.filemanager_ui.file',
			path: path
		}), 'fileprefs', '510x425');
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


		// Target will be missing ID if directory is empty
		// so start with the current directory
		var nm_dst = this.get_path(_action.parent.data.nextmatch.getInstanceManager().uniqueId || false);

		// File(s) were dropped on a row, they want them inside
		if(_target)
		{
			var dst = '';
			var paths = this._elems2paths([_target]);
			if(paths[0]) dst = paths[0];

			// check if target is a file --> use it's directory instead
			if(_target.id)
			{
				var data = egw.dataGetUIDdata(_target.id);
				if (!data || data.data.mime != 'httpd/unix-directory')
				{
					dst = this.dirname(dst);
				}
			}
		}

		this._do_action(_action.id.replace("file_drop_",''), src, false, dst || nm_dst);
	},

	/**
	 * Handle a native / HTML5 file drop from system
	 *
	 * This is a callback from nextmatch to prevent the default link action, and just upload instead.
	 *
	 * @param {string} row_uid UID of the row the files were dropped on
	 * @param {Files[]} files
	 */
	filedrop: function(row_uid, files)
	{
		var self = this;
		var data = egw.dataGetUIDdata(row_uid);
		files = files || window.event.dataTransfer.files;

		var path = typeof data != 'undefined' && data.data.mime == "httpd/unix-directory" ? data.data.path : this.get_path();
		var widget = this.et2.getWidgetById('upload');

		// Override finish to specify a potentially different path
		var old_onfinishone = widget.options.onFinishOne;
		var old_onfinish = widget.options.onFinish;

		widget.options.onFinishOne = function(_event, _file_count) {
			self.upload(_event, _file_count, path);
		};

		widget.options.onFinish = function() {
			widget.options.onFinish = old_onfinish;
			widget.options.onFinishOne = old_onfinishone;
		};
		// This triggers the upload
		widget.set_value(files);

		// Return false to prevent the link
		return false;
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
		for(var id in this.path_widget)
		{
			var path = this.get_path(id);

			if (_path == path)
			{
				var ids = ['button[linkpaste]', 'button[paste]', 'button[createdir]', 'button[symlink]', 'upload'];
				for(var i=0; i < ids.length; ++i)
				{
					var widget = etemplate2.getById(id).widgetContainer.getWidgetById(ids[i]);
					if (widget)
					{
						widget.set_readonly(_ro);
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
		else if (widget.value.is_dir)	// true for "httpd/unix-directory" and "egw/*"
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
					};
			}
		}
	},

	/**
	 * Open file a file dialog from EPL, warn if EPL is not available
	 *
	 * @param {jQuery.event} _event
	 * @param {et2_widget} _widget
	 */
	fileafile: function(_event, _widget)
	{
		if (this.egw.user('apps').stylite)
		{
			this.egw.open_link('stylite.stylite_filemanager.upload', '_blank', '670x320');
		}
		else
		{
			et2_dialog.show_dialog(function(_button)
			{
				if (_button == et2_dialog.YES_BUTTON) window.open('http://www.egroupware.org/EPL', '_blank');
				return true;
			}, this.egw.lang('File a file is only available with an EPL subscription.')+"\n\n"+
				this.egw.lang('You can use regular upload [+] button to upload files.')+"\n\n"+
				this.egw.lang('Do you want more information about EPL subscription?'),
			this.egw.lang('File a file'), undefined, et2_dialog.BUTTONS_YES_NO, et2_dialog.QUESTION_MESSAGE);
		}
	},

	/**
	 * Initiate odf editor popup & load given file_path
	 *
	 */
	_init_odf_editor: function ()
	{
		var file_path = this.et2.getArrayMgr('content').getEntry('file_path');
		var self = this;

		var onEditorCreated = function (err ,editor)
		{
			if (err)
			{
				console.log('Something went wrong whilst loading editor.'+ err);
				return;
			}
			self.editor = editor;
			self.editor.openDocumentFromUrl(egw.webserverUrl+file_path);
		};

		var editorOptions = {
			allFeaturesEnabled: true
		};

		var editor = this.et2.getWidgetById('odfEditor');
		if (editor)
		{
			Wodo.createTextEditor('filemanager-editor_odfEditor', editorOptions, onEditorCreated);
		}
	},

	/**
	 * Method to close an opened document
	 *
	 * @param {object} _egwAction egw action object
	 */
	editor_close: function (_egwAction) {
		var self = this;
		var action = _egwAction.id;
		if (this.editor)
		{
			var closeFn = function ()
			{
				self.editor.closeDocument(function(){});
				if (action != 'new')
				{
					self.editor.destroy(function(){});
					window.close();
				}
			}

			// warn user about unsaved changes
			if (this.editor.isDocumentModified())
			{
				et2_dialog.show_dialog(
					function(_btn)
					{
						if (_btn == 2)
						{
							closeFn();
						}
					},
					'There are unsaved changes. Are you sure you want to close this document without saving them?',
					'unsaved changes',
					null,
					et2_dialog.BUTTONS_YES_NO,
					et2_dialog.WARNING_MESSAGE
				);
			}
			else
			{
				closeFn();
			}
		}
	},

	/**
	 * Method to create a new document
	 * @param {object} _egwAction egw action object
	 *
	 * @todo: creating new empty odt file
	 */
	editor_new: function (_egwAction) {
		return egw(window).message('Sorry creating new odt document is not fully implemented yet. Please try later.');
		var mimeType = 'application/vnd.oasis.opendocument.text';
		var bytes = new Uint8Array('');
		var blob = new Blob([bytes.buffer], {type:mimeType});
		var egwAction = _egwAction;
		var self = this;
		this.editor_file_operation({
			url: egw.webserverUrl+'/webdav.php?/home/'+egw.user('account_lid')+'/'+this.et2._inst.etemplate_exec_id+'.odt',
			method: 'PUT',
			success: function(_data) {
				egw(window).message('');
				self.editor_close(egwAction);
			},
			error: function (_err) {
				egw(window).message('Create new document faild because of %1', _err);
			},
			data: blob,
			processData: false,
			mimeType: mimeType
		});
	},

	/**
	 * Method call for saving edited document
	 */
	editor_save: function () {
		var self = this;
		var file_path = this.et2.getArrayMgr('content').getEntry('file_path');

		if (this.editor)
		{
			function saveByteArrayLocally(err, data) {
				if (err) {
					alert(err);
					return;
				}

				var mimetype = "application/vnd.oasis.opendocument.text",
					filename = file_path.split('/webdav.php'),
					blob = new Blob([data.buffer], {type: mimetype});

				self.editor_file_operation({
						url: egw.webserverUrl+file_path,
						method: 'PUT',
						processData: false,
						success: function(data) {
							egw(window).message(egw.lang('Document %1 successfully has been saved.', filename[1]));
							self.editor.setDocumentModified(false);

						},
						error: function () {},
						data: blob,
						mimeType: mimetype
				});
			}
			this.editor.getDocumentAsByteArray(saveByteArrayLocally);
		}
	},

	/**
	 * Method to delete loaded file in editor
	 * @param {type} _egwAction
	 */
	editor_delete: function (_egwAction) {
		var fullpath = this.et2.getArrayMgr('content').getEntry('file_path');
		fullpath = fullpath.split('/webdav.php')[1];
		var selected = fullpath.split('/');
		selected.pop();
		var path = selected.join('/');
		this._do_action('delete', [fullpath], false, path);

		this.editor_close(_egwAction);
	},

	/**
	 * Function to handle file operations (PGD) for editor
	 *
	 * @param {object} _params jquery ajax parameters
	 */
	editor_file_operation: function (_params)
	{
		var ajaxObj = {
			url: egw.webserverUrl+'/webdav.php?/home/'+egw.user('account_lid')+'/default.odt',
		};
		jQuery.extend(ajaxObj, _params);
		switch (ajaxObj && ajaxObj.cmd)
		{
			case 'PUT':
				jQuery.extend({},ajaxObj, {
					data: JSON.stringify(ajaxObj.data),
					contentType: 'application/json'
				});
				break;
			case 'GET':
				jQuery.extend({},ajaxObj, {
					dataType: 'json'
				});
				break;
			case 'DELETE':
				break;
		}
		jQuery.ajax(ajaxObj);
	},

	/**
	 * Function to check wheter selected file is editable. ATM only .odt is supported.
	 *
	 * @returns {boolean} returns true if is editable otherwise false
	 */
	isEditable: function (_egwAction, _senders) {
		var data = egw.dataGetUIDdata(_senders[0].id);
		var mime = this.et2._inst.widgetContainer.getWidgetById('$row');
		return data.data.mime.match(mime.mime_odf_regex)?true:false;
	}

});
