/**
 * EGroupware - VFS SELECT Widget UI
 *
 * @link https://www.egroupware.org
 * @package et2_vfsSelect
 * @author Hadi Nategh <hn@egroupware.org>
 * @copyright (c) 2013-2021 by Hadi Nategh <hn@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {EgwApp} from "../jsapi/egw_app";
import {et2_vfs, et2_vfsPath, et2_vfsSelect} from "./et2_widget_vfs";
import {egw} from "../jsapi/egw_global";
import {et2_file} from "./et2_widget_file";
import {Et2Button} from "./Et2Button/Et2Button";
import {Et2Select} from "./Et2Select/Et2Select";
import {Et2Dialog} from "./Et2Dialog/Et2Dialog";
import {Et2Checkbox} from "./Et2Checkbox/Et2Checkbox";
import {Et2Searchbox} from "./Et2Textbox/Et2Searchbox";


/**
 * UI for VFS Select widget
 *
 */
export class vfsSelectUI extends EgwApp
{
	readonly appname: 'filemanager';
	private dirContent: {};
	private vfsSelectWidget : et2_vfsSelect;
	private path_widget: et2_vfsPath;

	/**
	 * Constructor
	 *
	 */
	constructor()
	{
		// call parent
		super('vfsSelectUI');

		this.egw.langRequireApp(this.egw.window, 'filemanager');
	}

	/**
	 * Destructor
	 */
	destroy(_app)
	{
		delete this.path_widget;
		delete this.vfsSelectWidget;
		// call parent
		super.destroy(_app);
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param et2 etemplate2 Newly ready object
	 * @param {string} name template name
	 */
	et2_ready(et2,name)
	{
		this.path_widget = <et2_vfsPath>this.et2.getWidgetById('path');
		this.dirContent = this.et2.getArrayMgr('content').data.dir;
	}

	/**
	 * Get directory of a path
	 *
	 * @param {string} _path
	 * @returns string
	 */
	dirname(_path)
	{
		let parts = _path.split('/');
		parts.pop();
		return parts.join('/') || '/';
	}

	/**
	 * Get name of a path
	 *
	 * @param {string} _path
	 * @returns string
	 */
	basename(_path)
	{
		return _path.split('/').pop();
	}

	/**
	 * Get current working directory
	 *
	 * @return string
	 */
	get_path()
	{
		return this.path_widget.get_value();
	}

	/**
	 * Send names of uploaded files (again) to server,
	 * to process them: either copy to vfs or ask overwrite/rename
	 *
	 * @param {event} _event
	 */
	storeFile(_event)
	{
		let path = this.get_path();

		if (!jQuery.isEmptyObject(_event.data.getValue()))
		{
			let widget = _event.data;
			egw(window).json('EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_vfsSelect_storeFile', [widget.getValue(), path],
				this._storeFile_callback, this, true, this
			).sendRequest(true);
			widget.set_value('');
		}
	}

	/**
	 * Callback for server response to storeFile request:
	 * - display message and refresh list
	 * - ask use to confirm overwritting existing files or rename upload
	 *
	 * @param {object} _data values for attributes msg, files, ...
	 */
	_storeFile_callback(_data : {msg : string, uploaded : any[], path : string})
	{
		if (_data.msg || _data.uploaded) egw(window).message(_data.msg);

		let that = this;
		for(let file in _data.uploaded)
		{
			if (_data.uploaded[file].confirm && !_data.uploaded[file].confirmed)
			{
				let buttons = [
					{
						label: this.egw.lang("Yes"),
						id: "overwrite",
						class: "ui-priority-primary",
						"default": true,
						image: 'check'
					},
					{label: this.egw.lang("Rename"), id: "rename", image: 'edit'},
					{label: this.egw.lang("Cancel"), id: "cancel"}
				];
				if(_data.uploaded[file].confirm === "is_dir")
				{
					buttons.shift();
				}
				let dialog = Et2Dialog.show_prompt(function(_button_id, _value)
					{
						let uploaded = {};
						uploaded[this.my_data.file] = this.my_data.data;
						switch(_button_id)
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
	}

	/**
	 * Prompt user for directory to create
	 *
	 * @param {egwAction|undefined|jQuery.Event} action Action, event or undefined if called directly
	 * @param {egwActionObject[] | undefined} selected Selected row, or undefined if called directly
	 */
	createdir(action, selected)
	{
		let self = this;
		Et2Dialog.show_prompt(function(button, dir)
		{
			if(button && dir)
			{
				let path = self.get_path();
				self.egw.json('EGroupware\\Api\\Etemplate\\Widget\\Vfs::ajax_create_dir', [dir, path], function(msg)
				{
					self.egw.message(msg);
					self.change_dir((path == '/' ? '' : path) + '/' + dir);
				}).sendRequest(false);
			}
		}, this.egw.lang('New directory'), this.egw.lang('Create directory'));
	}

	/**
	 * Change directory
	 *
	 * @param {string} _dir directory to change to incl. '..' for one up
	 */
	change_dir(_dir : string)
	{
		if (_dir == '..')
		{
			_dir = this.dirname(this.get_path());
		}
		this.path_widget.set_value(_dir);
	}

	/**
	 * Row or filename in select-file dialog clicked
	 *
	 * @param {jQuery.event} event
	 * @param {et2_widget} widget
	 */
	select_clicked(event : JQueryEventObject, widget : et2_vfs)
	{
		if (!widget || typeof widget.value != 'object')
		{

		}
		else if (widget.value.is_dir)	// true for "httpd/unix-directory" and "egw/*"
		{
			let path = widget.getRoot().getWidgetById("path");

			if(path)
			{
				path.set_value(widget.value.path);
			}
		}
		else if (this.et2 && this.et2.getArrayMgr('content').getEntry('mode') != 'open-multiple')
		{
			this.et2.setValueById('name', widget.value.name);
		}
		else
		{
			let file = widget.value.name;
			widget.getParent().iterateOver(function(widget)
			{
				if(widget.options.selectedValue === file)
				{
					widget.set_value(widget.get_value() === file ? widget.options.unselectedValue : file);
				}
			}, null, Et2Checkbox);

		}
		// Stop event or it will toggle back off
		event.preventDefault();
		event.stopPropagation();
		return false;
	}

	/**
	 * Handles action and offer it to the submit
	 *
	 * @param {string} action action name
	 * @param {object} widget widget which action was called from
	 */
	do_action(action : string, widget : Et2Button | Et2Select | et2_vfsPath)
	{
		if (!action) return;
		let field = '', value : string|string[] = '' ;
		switch (action)
		{
			case 'path': field = 'path'; value = (<et2_vfsPath>widget).getValue(); break;
			case 'home': field = 'action'; value = 'home'; break;
			case 'app': field = 'app'; value = (<Et2Select>widget).getValue(); break;
			case 'mime': field = 'mime'; value = (<Et2Select>widget).getValue(); break;
		}
		this.submit(field, value);
	}

	/**
	 * Sumbits content value after modification
	 *
	 * @param {string} _field content field to be modified
	 * @param {any} _val value of field
	 * @param {function} _callback
	 */
	submit(_field? : string, _val? : any, _callback? : Function)
	{
		let arrMgrs = this.et2.getArrayMgrs();
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
	}

	/**
	 * search through dir content and set its content base on searched query
	 * @returns
	 */
	search(_ev : Event, _widget : Et2Searchbox)
	{
		let dir = <et2_vfsPath>this.et2.getWidgetById('dir');
		let query = _widget.get_value();
		if (query == "")
		{
			dir.set_value({content: this.dirContent});
			return;
		}
		let self = this;
		let searchQuery = function (_query)
		{
			let result = {};
			let reg = RegExp(_query, 'ig');
			let key = 0;
			for (let i in self.dirContent)
			{
				if (typeof self.dirContent[i]['name'] != 'undefined' && self.dirContent[i]['name'].match(reg))
				{
					result[key] = self.dirContent[i];
					key++;
				}
				else if (typeof self.dirContent[i]['name'] == 'undefined' && isNaN(<number><unknown>i))
				{
					result[i] = self.dirContent[i];
				}
			}
			return result;
		};
		dir.set_value({content: searchQuery(query)});
	}
}
app.classes.vfsSelectUI = vfsSelectUI;