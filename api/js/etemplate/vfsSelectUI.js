/**
 * EGroupware - VFS SELECT Widget UI
 *
 * @link http://www.egroupware.org
 * @package et2_vfsSelect
 * @author Hadi Nategh <hn@egroupware.org>
 * @copyright (c) 2013-2017 by Hadi Nategh <hn@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for VFS Select widget
 *
 * @augments AppJS
 */
app.classes.vfsSelectUI = (function(){ "use strict"; return AppJS.extend(
{
	appname: 'filemanager',
	dirContent: {},
	vfsSelectWidget: {},
	path_widget: {},
	/**
	 * Constructor
	 *
	 * @memberOf app.filemanager
	 */
	init: function()
	{
		// call parent
		this._super.apply(this, arguments);

		this.egw.langRequireApp(this.egw.window, 'filemanager');
	},

	/**
	 * Destructor
	 */
	destroy: function()
	{
		delete this.path_widget;
		delete this.vfsSelectWidget;
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
		this.path_widget = this.et2.getWidgetById('path');
		this.dirContent = this.et2.getArrayMgr('content').data.dir;
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
		return this.path_widget.get_value();
	},

	/**
	 * Send names of uploaded files (again) to server,
	 * to process them: either copy to vfs or ask overwrite/rename
	 *
	 * @param {event} _event
	 */
	storeFile: function(_event)
	{
		var path = this.get_path();

		if (!jQuery.isEmptyObject(_event.data.getValue()))
		{
			var widget = _event.data;
			egw(window).json('EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelect_storeFile', [widget.getValue(), path],
				this._storeFile_callback, this, true, this
			).sendRequest(true);
			widget.set_value('');
		}
	},

	/**
	 * Callback for server response to storeFile request:
	 * - display message and refresh list
	 * - ask use to confirm overwritting existing files or rename upload
	 *
	 * @param {object} _data values for attributes msg, files, ...
	 */
	_storeFile_callback: function(_data)
	{
		if (_data.msg || _data.uploaded) egw(window).message(_data.msg);

		var that = this;
		for(var file in _data.uploaded)
		{
			if (_data.uploaded[file].confirm && !_data.uploaded[file].confirmed)
			{
				var buttons = [
					{text: this.egw.lang("Yes"), id: "overwrite", class: "ui-priority-primary", "default": true, image: 'check'},
					{text: this.egw.lang("Rename"), id:"rename", image: 'edit'},
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
							egw.json('EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelect_storeFile', [uploaded, this.my_data.path],
								that._storeFile_callback, that, true, that
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
					file: file,
					path: _data.path,
					data: _data.uploaded[file],
				};
			}
			else
			{
				this.submit();
			}
		}
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
				var path = self.get_path();
				self.egw.json('EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_create_dir', [dir, path], function(msg){
					self.egw.message(msg);
					self.change_dir((path == '/' ? '' : path)+'/'+ dir);
				}).sendRequest(false);
			}
		},this.egw.lang('New directory'),this.egw.lang('Create directory'));
	},

	/**
	 * Change directory
	 *
	 * @param {string} _dir directory to change to incl. '..' for one up
	 * @param {et2_widget} widget
	 */
	change_dir: function(_dir, widget)
	{
		switch (_dir)
		{
			case '..':
				_dir = this.dirname(this.get_path());
				break;

		}

		this.path_widget.set_value(_dir);
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
	 * Handles action and offer it to the submit
	 *
	 * @param {string} action action name
	 * @param {object} widget widget which action was called from
	 */
	do_action: function (action, widget)
	{
		if (!action) return;
		var field = '', value = '';
		switch (action)
		{
			case 'path': field = 'path'; value = widget.getValue(); break;
			case 'home': field = 'action'; value = 'home'; break;
			case 'app': field = 'app'; value = widget.getValue(); break;
			case 'mime': field = 'mime'; value = widget.getValue(); break;
		}
		this.submit(field, value);
	},

	/**
	 * Sumbits content value after modification
	 *
	 * @param {string} _field content field to be modified
	 * @param {any} _val value of field
	 * @param {function} _callback
	 */
	submit: function(_field, _val, _callback)
	{
		var arrMgrs = this.et2.getArrayMgrs();
		if (_field)
		{
			arrMgrs.content.data[_field] = _val;
			jQuery.extend(arrMgrs.content.data, arrMgrs.modifications.data);
			this.et2.setArrayMgrs(arrMgrs);
		}

		// preserve value of the name
		if (arrMgrs && this.et2.getWidgetById('name'))
		{
			arrMgrs.content.data['name'] = this.et2.getWidgetById('name').get_value();
		}

		this.vfsSelectWidget._content(arrMgrs.content.data, _callback);
	},

	/**
	 * search through dir content and set its content base on searched query
	 * @returns
	 */
	search: function(_widget)
	{
		var dir = this.et2.getWidgetById('dir');
		var query = _widget.get_value();
		if (query == "")
		{
			dir.set_value({content: this.dirContent});
			return;
		}
		var self = this;
		var searchQuery = function (_query)
		{
			var result = {};
			var reg = RegExp("^"+_query, 'ig');
			var key = 0;
			for (var i in self.dirContent)
			{
				if (typeof self.dirContent[i]['name'] != 'undefined' && self.dirContent[i]['name'].match(reg))
				{
					result[key] = self.dirContent[i];
					key++;
				}
				else if (typeof self.dirContent[i]['name'] == 'undefined' && isNaN(i))
				{
					result[i] = self.dirContent[i];
				}
			}
			return result;
		};
		dir.set_value({content: searchQuery(query)});
	}
});}).call(this);