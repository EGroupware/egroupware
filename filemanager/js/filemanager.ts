/**
 * EGroupware - Filemanager - Javascript UI
 *
 * @link https://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-21 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
	/api/js/jsapi/egw_app.js;
 */
import {EgwApp, PushData} from "../../api/js/jsapi/egw_app";
import {et2_nextmatch} from "../../api/js/etemplate/et2_extension_nextmatch";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
import {et2_file} from "../../api/js/etemplate/et2_widget_file";
import {et2_nextmatch_controller} from "../../api/js/etemplate/et2_extension_nextmatch_controller";
import {egw} from "../../api/js/jsapi/egw_global";
import {et2_selectbox} from "../../api/js/etemplate/et2_widget_selectbox";
import {et2_textbox} from "../../api/js/etemplate/et2_widget_textbox";

/**
 * UI for filemanager
 *
 * This is the code of filemanager's app.ts to ensure proper loading/cache-invalidation for Collabora extending filemanagerAPP!
 */
export class filemanagerAPP extends EgwApp
{
	/**
	 * If pushData.acl has fields that can help filter based on ACL grants, list them
	 * here and we can check them and ignore push messages if there is no ACL for that entry.
	 * We don't use fields, but setting this allows our custom check to run.
	 * @protected
	 */
	protected push_grant_fields : string[] = ['acl'];

	/**
	 * If pushData.acl has fields that can help filter based on current nextmatch filters,
	 * list them here and we can check and ignore push messages if the nextmatch filters do not exclude them.
	 * We only check path, which is the ID, but setting this allows our custom check to run.
	 *
	 * @protected
	 */
	protected push_filter_fields : string[] = ['id'];

	/**
	 * path widget, by template
	 */
	path_widget : {} = {};
	/**
	 * Are files cut into clipboard - need to be deleted at source on paste
	 */
	clipboard_is_cut : boolean = false;

	/**
	 * Regexp to convert id to a path, use this.id2path(_id)
	 */
	private remove_prefix : RegExp = /^filemanager::/;

	private readonly;

	/**
	 * Constructor
	 *
	 * @memberOf app.filemanager
	 */
	constructor()
	{
		// call parent
		super('filemanager');

		// Loading filemanager in its tab and home causes us problems with
		// unwanted destruction, so we check for already existing path widgets
		let lists = etemplate2.getByApplication('home');
		for (let i = 0; i < lists.length; i++)
		{
			if(lists[i].app == 'filemanager' && lists[i].widgetContainer.getWidgetById('path'))
			{
				this.path_widget[lists[i].uniqueId] = lists[i].widgetContainer.getWidgetById('path');
			}
		}
	}

	/**
	 * Destructor
	 */
	destroy(_app)
	{
		delete this.et2;

		// call parent
		super.destroy(_app)
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
		// call parent
		super.et2_ready(et2, name);

		if (name === 'filemanager.admin')
		{
			this.changeMountScheme();
			return;
		}

		let path_widget = this.et2.getWidgetById('path');
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

		if (name == 'filemanager.index')
		{
			let fe = egw.link_get_registry('filemanager-editor');
			let new_widget =  this.et2.getWidgetById('new');
			if(fe && fe["edit"])
			{
				let new_options = this.et2.getArrayMgr('sel_options').getEntry('new');
				new_widget.set_select_options(new_options);
			}
			else if(new_widget)
			{
				new_widget.set_disabled(true);
			}
		}
	}

	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * Overridden here so we can handle notifications about file in subdirectories
	 *
	 * @param  pushData
	 * @param {string} pushData.app application name
	 * @param {(string|number)} pushData.id id of entry to refresh or null
	 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param {number} pushData.account_id User that caused the notification
	 */
	push(pushData : PushData)
	{
		super.push(pushData);

		// don't care about other apps data
		if(pushData.app !== this.appname)
		{
			return;
		}

		// Special handling only for sub-dirs, super.push() handled everything else
		if(pushData.id == this.get_path() || !pushData.id.toString().startsWith(this.get_path()))
		{
			return;
		}
		// Nextmatch controller has the sub-grids.
		let nm = <et2_nextmatch>this.et2?.getDOMWidgetById('nm');
		if(!nm)
		{
			return;
		}

		nm.controller._children.forEach((subgrid) =>
		{
			if(subgrid._parentId === this.dirname(pushData.id))
			{
				console.log(subgrid);
				let uid = pushData.id.toString().indexOf(nm.controller.dataStorePrefix) == 0 ? pushData.id : nm.controller.dataStorePrefix + "::" + pushData.id;
				debugger;
				nm._refresh_grid(pushData.type, subgrid, [pushData.id], uid);
			}

		});

	}

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
	setState(state)
	{
		// State should be an object, not a string, but we'll parse
		if(typeof state == "string")
		{
			if(state.indexOf('{') != -1 || state =='null')
			{
				state = JSON.parse(state);
			}
		}
		let result = super.setState(state, 'filemanager.index');

		// This has to happen after the parent, changing to tile recreates
		// nm controller
		if(typeof state == "object" && state.state && state.state.view)
		{
			let et2 = etemplate2.getById('filemanager-index');
			if(et2)
			{
				this.et2 = et2.widgetContainer;
				this.change_view(state.state.view);
			}
		}
		return result;
	}

	/**
	 * Retrieve the current state of the application for future restoration
	 *
	 * Extended from parent to also set view
	 *
	 * @return {object} Application specific map representing the current state
	 */
	getState()
	{
		let state = super.getState();

		let et2 = etemplate2.getById('filemanager-index');
		if(et2)
		{
			let nm = et2.widgetContainer.getWidgetById('nm');
			state.view = nm.view;
		}
		return state;
	}

	/**
	 * Check grants to see if we can quickly tell if this entry is not for us
	 *
	 * Overridden to check current user and their memberships against pushData.acl, which is a list of account IDs
	 * that should have at least read access.
	 *
	 * @param pushData
	 * @param grant_fields List of fields in pushData.acl with account IDs that might grant access eg: info_responsible
	 * @param appname Optional, to check against the grants for a different application.  Defaults to this.appname.
	 *
	 * @return boolean Entry has ACL access
	 */
	_push_grant_check(pushData : PushData, grant_fields : string[], appname? : string) : boolean
	{
		let grants = [this.egw.user("account_id"), ...this.egw.user("memberships")];

		// check user has a something in the pushData ACL list
		// Don't use strict comparison since sometimes account IDs are strings, sometimes ints
		return grants.filter(value => pushData.acl.find(acl => acl == value)).length > 0;
	}

	/**
	 * Check pushData path to see if we care about this entry based on current nextmatch path.
	 * This is not a definitive yes or no (the server will tell us when we ask), we just want to cheaply
	 * avoid a server call if we know it won't be in the list.
	 *
	 * @param pushData
	 * @param filter_fields List of filter field names eg: [owner, cat_id]
	 * @return boolean True if the nextmatch filters might include the entry, false if not
	 */
	_push_field_filter(pushData : PushData, nm : et2_nextmatch, filter_fields : string[]) : boolean
	{
		return pushData.id && this.dirname(<string>pushData.id) === this.get_path();
	}

	/**
	 * Convert id to path (remove "filemanager::" prefix)
	 */
	id2path(_id : string) : string
	{
		return _id.replace(this.remove_prefix, '');
	}

	/**
	 * Convert array of elems to array of paths
	 */
	_elems2paths(_elems) : string[]
	{
		let paths = [];
		for (let i = 0; i < _elems.length; i++)
		{
			// If selected has no id, try parent.  This happens for the placeholder row
			// in empty directories.
			paths.push(_elems[i].id? this.id2path(_elems[i].id) : _elems[i]._context._parentId);
		}
		return paths;
	}

	/**
	 * Get directory of a path
	 */
	dirname(_path : string) : string
	{
		let parts = _path.split('/');
		parts.pop();
		return parts.join('/') || '/';
	}

	/**
	 * Get name of a path
	 */
	basename(_path : string) : string
	{
		return _path.split('/').pop();
	}

	/**
	 * Get current working directory
	 */
	get_path(etemplate_name? : string) : string
	{
		if(!etemplate_name || typeof this.path_widget[etemplate_name] == 'undefined')
		{
			for(etemplate_name in this.path_widget) break;
		}
		let path_widget = this.path_widget[etemplate_name];
		return path_widget ? path_widget.get_value.apply(path_widget) : null;
	}

	/**
	 * Open compose with already attached files
	 *
	 * @param {(string|string[])} attachments path(s)
	 * @param {object} params
	 */
	open_mail(attachments : string | string[], params? : object)
	{
		if (typeof attachments == 'undefined') attachments = this.get_clipboard_files();
		if (!params || typeof params != 'object') params = {};
		if (!(attachments instanceof Array)) attachments = [ attachments ];
		let content = {data:{files:{file:[]}}};
		for(let i=0; i < attachments.length; i++)
		{
			params['preset[file]['+i+']'] = 'vfs://default'+attachments[i];
			content.data.files.file.push('vfs://default'+attachments[i]);
		}
		content.data.files["filemode"] = params['preset[filemode]'];
		// always open compose in html mode, as attachment links look a lot nicer in html
		params["mimeType"] = 'html';
		return egw.openWithinWindow("mail", "setCompose", content, params, /mail.mail_compose.compose/, true);
	}

	/**
	 * Mail files action: open compose with already attached files
	 *
	 * @param _action
	 * @param _elems
	 */
	mail(_action, _elems)
	{
		this.open_mail(this._elems2paths(_elems), {
			'preset[filemode]': _action.id.substr(5)
		});
	}

	/**
	 * Copy a share link to the system clipboard
	 *
	 * @param widget
	 */
	copy_share_link(ev, widget)
	{
		egw.copyTextToClipboard(widget.value, widget, ev).then((success) =>
		{
			if(success !== false)
			{
				egw.message(this.egw.lang('share link copied into clipboard'));
			}
		});
	}

	/**
	 * Mail files action: open compose with already linked files
	 * We're only interested in hidden upload shares here, open_mail can handle
	 * the rest
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _selected
	 */
	mail_share_link(_action, _selected)
	{
		if(_action.id !== 'mail_shareUploadDir')
		{
			return this.mail(_action, _selected);
		}
		let path = this.id2path(_selected[0].id);

		this.share_link(_action, _selected, null, false, false, this._mail_link_callback);

		return true;
	}

	/**
	 * Callback with the share link to append to an email
	 *
	 * @param {Object} _data
	 * @param {String} _data.share_link Link to the share
	 * @param {String} _data.title Title for the link
	 * @param {String} [_data.msg] Error message
	 */
	_mail_link_callback(_data)
	{
		debugger;
		if (_data.msg || !_data.share_link) window.egw_refresh(_data.msg, this.appname);

		let params = {
			'preset[body]': '<a href="'+_data.share_link + '">'+_data.title+'</a>',
			'mimeType': 'html'// always open compose in html mode, as attachment links look a lot nicer in html
		};
		let content = {
			mail_htmltext: ['<br /><a href="'+_data.share_link + '">'+_data.title+'</a>'],
			mail_plaintext: ["\n"+_data.share_link]
		};
		return egw.openWithinWindow("mail", "setCompose", content, params, /mail.mail_compose.compose/);
	}

	/**
	 * Trigger Upload after each file is uploaded
	 * @param {type} _event
	 */
	uploadOnOne(_event)
	{
		this.upload(_event,1);
	}

	/**
	 * Send names of uploaded files (again) to server, to process them: either copy to vfs or ask overwrite/rename
	 *
	 * @param {event} _event
	 * @param {number} _file_count
	 * @param {string=} _path where the file is uploaded to, default current directory
	 * @param {string} _conflict What to do if the file conflicts with one on the server
	 * @param {string} _target Upload processing target.  Sharing classes can override this.
	 */
	upload(_event, _file_count : number, _path? : string, _conflict = "ask", _target: string = 'filemanager_ui::ajax_action')
	{
		if(typeof _path == 'undefined')
		{
			_path = this.get_path();
		}
		if (_file_count && !jQuery.isEmptyObject(_event.data.getValue()))
		{
			let widget = _event.data;
			let value = widget.getValue();
			value.conflict = _conflict;
			egw.json(_target, ['upload', value, _path, {ui_path: this.egw.window.location.pathname}],
				this._upload_callback, this, true, this
			).sendRequest();
			widget.set_value('');
		}
	}

	/**
	 * Finish callback for file a file dialog, to get the overwrite / rename prompt
	 *
	 * @param {event} _event
	 * @param {number} _file_count
	 */
	file_a_file_upload(_event, _file_count : number) : boolean
	{
		let widget = _event.data;
		let path = widget.getRoot().getWidgetById("path").getValue();
		let action = widget.getRoot().getWidgetById("action").getValue();
		let link = widget.getRoot().getWidgetById("entry").getValue();
		if(action == 'save_as' && link.app && link.id)
		{
			path = "/apps/"+link.app+"/"+link.id;
		}

		let props = widget.getInstanceManager().getValues(widget.getRoot());
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
	}


	/**
	 * Callback for server response to upload request:
	 * - display message and refresh list
	 * - ask use to confirm overwritting existing files or rename upload
	 *
	 * @param {object} _data values for attributes msg, files, ...
	 */
	_upload_callback(_data)
	{
		if(_data.msg || _data.uploaded)
		{
			if(this.egw.pushAvailable())
			{
				this.egw.message(_data.msg, _data.errs > 0 ? "error" : "success");
			}
			else
			{
				window.egw_refresh(_data.msg, this.appname, undefined, undefined, undefined, undefined, undefined, _data.type);
			}
		}

		let that = this;
		for(let file in _data.uploaded)
		{
			if(_data.uploaded[file].confirm && !_data.uploaded[file].confirmed)
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
					{label: this.egw.lang("Cancel"), id: "cancel", image: "cancel"}
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
	}

	/**
	 * Get any files that are in the system clipboard
	 *
	 * @return {string[]} Paths
	 */
	get_clipboard_files()
	{
		let clipboard_files = [];
		if (typeof window.localStorage != 'undefined' && typeof egw.getSessionItem('phpgwapi', 'egw_clipboard') != 'undefined')
		{
			let clipboard = JSON.parse(egw.getSessionItem('phpgwapi', 'egw_clipboard')) || {
				type:[],
				selected:[]
			};
			if(clipboard.type.indexOf('file') >= 0)
			{
				for(let i = 0; i < clipboard.selected.length; i++)
				{
					let split = clipboard.selected[i].id.split('::');
					if(split[0] == 'filemanager')
					{
						clipboard_files.push(this.id2path(clipboard.selected[i].id));
					}
				}
			}
		}
		return clipboard_files;
	}

	/**
	 * Update clickboard tooltips in buttons
	 */
	clipboard_tooltips()
	{
		let paste_buttons = ['button[paste]', 'button[linkpaste]', 'button[mailpaste]'];
		for(let i=0; i < paste_buttons.length; ++i)
		{
			let button = this.et2.getWidgetById(paste_buttons[i]);
			if (button) button.set_statustext(this.get_clipboard_files().join(",\n"));
		}
	}

	/**
	 * Clip files into clipboard
	 *
	 * @param _action
	 * @param _elems
	 */
	clipboard(_action, _elems)
	{
		this.clipboard_is_cut = _action.id == "cut";
		let clipboard = JSON.parse(egw.getSessionItem('phpgwapi', 'egw_clipboard')) || {
			type:[],
			selected:[]
		};
		if(_action.id != "add")
		{
			clipboard = {
				type:[],
				selected:[]
			};
		}

		// When pasting we need to know the type of data - pull from actions
		let drag = _elems[0].getSelectedLinks('drag').links;
		for(let k in drag)
		{
			if(drag[k].enabled && drag[k].actionObj.dragType.length > 0)
			{
				clipboard.type = clipboard.type.concat(drag[k].actionObj.dragType);
			}
		}
		clipboard.type = jQuery.unique(clipboard.type);
		// egwAction is a circular structure and can't be stringified so just take what we want
		// Hopefully that's enough for the action handlers
		for(let k in _elems)
		{
			if(_elems[k].id) clipboard.selected.push({id:_elems[k].id, data:_elems[k].data});
		}

		// Save it in session
		egw.setSessionItem('phpgwapi', 'egw_clipboard', JSON.stringify(clipboard));

		this.clipboard_tooltips();
	}

	/**
	 * Paste files into current directory or mail them
	 *
	 * @param _type 'paste', 'linkpaste', 'mailpaste'
	 */
	paste(_type : string)
	{
		let clipboard_files = this.get_clipboard_files();
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
	}

	/**
	 * Pass action to server
	 *
	 * @param _action
	 * @param _elems
	 */
	action(_action, _elems)
	{
		let paths = this._elems2paths(_elems);
		let path = this.get_path(_action && _action.parent.data.nextmatch.getInstanceManager().uniqueId || false);
		this._do_action(_action.id, paths,true, path);
	}

	/**
	 * Prompt user for directory to create
	 *
	 * @param {egwAction|undefined} action Action, event or undefined if called directly
	 * @param {egwActionObject[] | undefined} selected Selected row, or undefined if called directly
	 */
	createdir(action, selected)
	{
		let self = this;
		Et2Dialog.show_prompt(function(button, dir)
		{
			if(button && dir)
			{
				let path = self.get_path(action && action.parent ? action.parent.data.nextmatch.getInstanceManager().uniqueId : false);
				if(action && action instanceof egwAction)
				{
					let paths = self._elems2paths(selected);
					if(paths[0])
					{
						path = paths[0];
					}
					// check if target is a file --> use it's directory instead
					if(selected[0].id || path)
					{
						let data = egw.dataGetUIDdata(selected[0].id || 'filemanager::' + path);
						if(data && data.data.mime != 'httpd/unix-directory')
						{
							path = self.dirname(path);
						}
					}
				}
				self._do_action('createdir', egw.encodePathComponent(dir), false, path);
			}
		}, 'New directory', 'Create directory');
	}

	/**
	 * Prompt user for directory to create
	 */
	symlink()
	{
		let self = this;
		Et2Dialog.show_prompt(function(button, target)
		{
			if(button && target)
			{
				self._do_action('symlink', target);
			}
		}, 'Link target', 'Create link');
	}

	/**
	 * Run a serverside action via an ajax call
	 *
	 * @param _type 'move_file', 'copy_file', ...
	 * @param _selected selected paths
	 * @param _sync send a synchronous ajax request
	 * @param _path defaults to current path
	 */
	_do_action(_type, _selected, _sync?, _path?)
	{
		if (typeof _path == 'undefined') _path = this.get_path();
		egw.json('filemanager_ui::ajax_action', [_type, _selected, _path],
			this._do_action_callback, this, !_sync, this
		).sendRequest();
	}

	/**
	 * Callback for _do_action ajax call
	 *
	 * @param _data
	 */
	_do_action_callback(_data)
	{
		if(_data.action == "delete" && this.egw.pushAvailable())
		{
			// No need to refresh, push will handle it
			this.egw.message(_data.msg)
			return;
		}
		window.egw_refresh(_data.msg, this.appname, undefined, undefined, undefined, undefined, undefined, _data.type);
	}

	/**
	 * Force download of a file by appending '?download' to it's download url
	 *
	 * @param _action
	 * @param _senders
	 */
	force_download(_action, _senders) : boolean
	{
		for(let i = 0; i < _senders.length; i++)
		{
			let data = egw.dataGetUIDdata(_senders[i].id);
			let url = data ? data.data.download_url : '/webdav.php'+this.id2path(_senders[i].id);
			if (url[0] == '/') url = egw.link(url);

			let a = document.createElement('a');
			if(typeof a.download == "undefined")
			{
				window.location = <Location><unknown>(url+"?download");
				return false;
			}

			// Multiple file download for those that support it
			let $a = jQuery(a)
				.prop('href', url)
				.prop('download', data ? data.data.name : "")
				.appendTo(this.et2.getDOMNode());

			window.setTimeout(jQuery.proxy(function() {
				let evt = document.createEvent('MouseEvent');
				evt.initMouseEvent('click', true, true, window, 1, 0, 0, 0, 0, false, false, false, false, 0, null);
				this[0].dispatchEvent(evt);
				this.remove();
			}, $a), 100*i);
		}
		return false;
	}

	/**
	 * Check to see if the browser supports downloading multiple files
	 * (using a tag download attribute) to enable/disable the context menu
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	is_multiple_allowed(action, selected) : boolean
	{
		let allowed = typeof document.createElement('a').download != "undefined";

		if(typeof action == "undefined") return allowed;

		return (allowed || selected.length <= 1) && action.not_disableClass.apply(action, arguments);
	}


	/**
	 * Change directory
	 *
	 * @param {string} _dir directory to change to incl. '..' for one up
	 * @param {et2_widget} widget
	 */
	change_dir(_dir, widget?)
	{
		for(var etemplate_name in this.path_widget) break;
		if (widget) etemplate_name = widget.getInstanceManager().uniqueId;

		// Make sure everything is in place for changing directory
		if(!this.et2 || typeof etemplate_name !== 'string' ||
			typeof this.path_widget[etemplate_name] === 'undefined')
		{
			return false;
		}

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
	}

	/**
	 * Toggle view between tiles and rows
	 *
	 * @param {string|Event} [view] - Specify what to change the view to.  Either 'tile' or 'row'.
	 *	Or, if this is used as a callback view is actually the event, and we need to find the view.
	 * @param {et2_widget} [button_widget] - The widget that's calling
	 */
	change_view(view, button_widget?)
	{
		let et2 = etemplate2.getById('filemanager-index');
		let nm : et2_nextmatch;
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
			button_widget = (<et2_nextmatch><unknown>nm).getWidgetById('button[change_view]');
		}
		if(button_widget)
		{
			// Switch view based on button icon, since controller can get re-created
			if(typeof view != 'string')
			{
				view = button_widget.image.split('list_')[1].replace('.svg', '');
			}

			// Toggle button icon to the other view
			//todo: nm.controller needs to be changed to nm.getController after merging typescript branch into master
			button_widget.image = ("list_" + (view == et2_nextmatch_controller.VIEW_ROW ? et2_nextmatch_controller.VIEW_TILE : et2_nextmatch_controller.VIEW_ROW));

			button_widget.statustext = (view == et2_nextmatch_controller.VIEW_ROW ? this.egw.lang("Tile view") : this.egw.lang('List view'));
		}

		nm.set_view(view);
		// Put it into active filters (but don't refresh)
		nm.activeFilters["view"]= view;

		// Change template to match
		let template : any = view == et2_nextmatch_controller.VIEW_ROW ? 'filemanager.index.rows' : 'filemanager.tile';
		nm.set_template(template);

		// Wait for template to load, then refresh
		template = nm.getWidgetById(template);
		if(template && template.loading)
		{
			template.loading.then(function() {
				nm.applyFilters({view: view});
			});
		}
	}

	/**
	 * Open/active an item
	 *
	 * @param _action
	 * @param _senders
	 */
	open(_action, _senders)
	{
		let data = egw.dataGetUIDdata(_senders[0].id);
		let path = this.id2path(_senders[0].id);
		this.et2 = this.et2 ? this.et2 : etemplate2.getById('filemanager-index').widgetContainer;
		let mime = this.et2._inst.widgetContainer.getWidgetById('$row');
		// try to get mime widget DOM node out of the row DOM
		let mime_dom = jQuery(_senders[0].iface.getDOMNode()).find("span#filemanager-index_\\$row");
		let fe = egw.file_editor_prefered_mimes();

		// symlinks dont have mime 'http/unix-directory', but server marks all directories with class 'isDir'
		if (data.data.mime == 'httpd/unix-directory' || data.data['class'] && data.data['class'].split(/ +/).indexOf('isDir') != -1)
		{
			this.change_dir(path,_action.parent.data.nextmatch || this.et2);
		}
		else if(mime && data.data.mime.match(mime.mime_regexp) && mime_dom.length>0)
		{
			mime_dom.click();
		}
		else if (mime && this.isEditable(_action, _senders) && fe && fe.edit)
		{

			egw.open_link(egw.link('/index.php', {
				menuaction: fe.edit.menuaction,
				path: decodeURIComponent(data.data.download_url)
			}), '', fe.edit_popup);
		}
		else
		{
			egw.open({path: path, type: data.data.mime, download_url: data.data.download_url}, 'file','view',null,'_browser');
		}
		return false;
	}

	/**
	 * Edit prefs of current directory
	 *
	 * @param _action
	 * @param _senders
	 */
	editprefs(_action, _senders)
	{
		let path =  typeof _senders != 'undefined' ? this.id2path(_senders[0].id) : this.get_path(_action && _action.parent.data.nextmatch.getInstanceManager().uniqueId || false);

		egw().open_link(egw.link('/index.php', {
			menuaction: 'filemanager.filemanager_ui.file',
			path: path
		}), 'fileprefs', '510x425');
	}

	/**
	 * Callback to check if the paste action is enabled.  We also update the
	 * clipboard historical targets here as well
	 *
	 * @param {egwAction} _action  drop action we're checking
	 * @param {egwActionObject[]} _senders selected files
	 * @param {egwActionObject} _target Drop or context menu activated on this one
	 *
	 * @returns boolean true if enabled, false otherwise
	 */
	paste_enabled(_action, _senders, _target)
	{
		// Need files in the clipboard for this
		let clipboard_files = this.get_clipboard_files();
		if(clipboard_files.length === 0)
		{
			return false;
		}

		// Parent action (paste) gets run through here as well, but needs no
		// further processing
		if(_action.id == 'paste') return true;

		if(_action.canHaveChildren.indexOf('drop') == -1)
		{
			_action.canHaveChildren.push('drop');
		}
		let actions = [];

		// Current directory
		let current_dir = this.get_path();
		let dir = egw.dataGetUIDdata('filemanager::'+current_dir);
		let path_widget = etemplate2.getById('filemanager-index').widgetContainer.getWidgetById('button[createdir]');
		actions.push({
			id:_action.id+'_current', caption: current_dir, path: current_dir,
			enabled: dir && dir.data && dir.data.class && dir.data.class.indexOf('noEdit') === -1 ||
				!dir && path_widget && !path_widget.options?.readonly
		});

		// Target, if directory
		let target_dir = this.id2path(_target.id);
		dir = egw.dataGetUIDdata(_target.id);
		actions.push({
			             id: _action.id + '_target',
			             caption: target_dir,
			             path: target_dir,
			             enabled: _target && _target.iface && jQuery(_target.iface.getDOMNode()).hasClass('isDir') &&
				             (dir && dir.data && dir.data.class && dir.data.class.indexOf('noEdit') === -1 || !dir)
		             });

		// Last 10 folders
		let previous_dsts = jQuery.extend([], <any><unknown>egw.preference('drop_history', this.appname));
		let action_index = 0;
		for (let i = 0; i < 10; i++)
		{
			let path = i < previous_dsts.length ? previous_dsts[i] : '';
			actions.push({
				             id: _action.id + '_target_' + action_index++,
				             caption: path,
				             path: path,
				             group: 2,
				             enabled: path && !(current_dir && path === current_dir || target_dir && path === target_dir)
			});
		}

		// Common stuff, every action needs these
		for(let i = 0; i < actions.length; i++)
		{
			//actions[i].type = 'drop',
			actions[i].acceptedTypes = _action.acceptedTypes;
			actions[i].no_lang = true;
			actions[i].hideOnDisabled = true;
		}

		_action.updateActions(actions);

		// Create paste action
		// This injects the clipboard data and calls the original handler
		let paste_exec = function(action, selected) {
			// Add in clipboard as a sender
			let clipboard = JSON.parse(egw.getSessionItem('phpgwapi', 'egw_clipboard'));

			// Set a flag so apps can tell the difference, if they need to
			action.set_onExecute(action.parent.onExecute.fnct);
			action.execute(clipboard.selected,selected[0]);

			// Clear the clipboard, the files are not there anymore
			if(action.id.indexOf('move') !== -1)
			{
				egw.setSessionItem('phpgwapi', 'egw_clipboard', JSON.stringify({
					type:[],
					selected:[]
				}));
			}
		};
		for(let i = 0; i < actions.length; i++)
		{
			_action.getActionById(actions[i].id).onExecute = jQuery.extend(true, {}, _action.onExecute);

			_action.getActionById(actions[i].id).set_onExecute(paste_exec);
		}
		return actions.length > 0;
	}

	/**
	 * File(s) droped
	 *
	 * @param _action
	 * @param _elems
	 * @param _target
	 * @returns
	 */
	drop(_action, _elems, _target)
	{
		let src = this._elems2paths(_elems);

		// Target will be missing ID if directory is empty
		// so start with the current directory
		let parent = _action;
		let nm = _target ? _target.manager.data.nextmatch : null;
		while(!nm && parent.parent)
		{
			parent = parent.parent;
			if(parent.data.nextmatch) nm = parent.data.nextmatch;
		}
		let nm_dst = this.get_path(nm.getInstanceManager().uniqueId || false);
		let dst;
		// Action specifies a destination, target does not matter
		if(_action.data && _action.data.path)
		{
			dst = _action.data.path;
		}
		// File(s) were dropped on a row, they want them inside
		else if(_target)
		{
			dst = '';
			let paths = this._elems2paths([_target]);
			if(paths[0]) dst = paths[0];

			// check if target is a file --> use it's directory instead
			if(_target.id)
			{
				let data = egw.dataGetUIDdata(_target.id);
				if(!data || data.data.mime != 'httpd/unix-directory')
				{
					dst = this.dirname(dst);
				}
			}
		}

		// Remember the target for next time
		let previous_dsts = jQuery.extend([], egw.preference('drop_history', this.appname));
		previous_dsts.unshift(dst);
		previous_dsts = Array.from(new Set(previous_dsts)).slice(0, 9);
		egw.set_preference(this.appname, 'drop_history', previous_dsts);

		// Actual action id will be something like file_drop_{move|copy|link}[_other_id],
		// but we need to send move, copy or link
		let action_id = _action.id.replace("file_drop_", '').split('_', 1)[0];
		this._do_action(action_id, src, false, dst || nm_dst);
	}

	/**
	 * Handle a native / HTML5 file drop from system
	 *
	 * This is a callback from nextmatch to prevent the default link action, and just upload instead.
	 *
	 * @param {string} row_uid UID of the row the files were dropped on
	 * @param {Files[]} files
	 */
	filedrop(row_uid, files) : boolean
	{
		let self = this;
		let data = egw.dataGetUIDdata(row_uid);
		files = files || window.event.dataTransfer.files;

		let path = typeof data != 'undefined' && data.data.mime == "httpd/unix-directory" ? data.data.path : this.get_path();
		let widget = this.et2.getWidgetById('upload');

		// Override finish to specify a potentially different path
		let old_onfinishone = widget.options.onFinishOne;
		let old_onfinish = widget.options.onFinish;

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
	}

	/**
	 * Change readonly state for given directory
	 *
	 * Get call/transported with each get_rows call, but should only by applied to UI if matching curent dir
	 *
	 * @param {string} _path
	 * @param {boolean} _ro
	 */
	set_readonly(_path, _ro)
	{
		//alert('set_readonly("'+_path+'", '+_ro+')');
		if (!this.path_widget)	// widget not yet ready, try later
		{
			this.readonly = [_path, _ro];
			return;
		}
		for(let id in this.path_widget)
		{
			let path = this.get_path(id);

			if (_path == path)
			{
				let ids = ['button[linkpaste]', 'button[paste]', 'button[createdir]', 'button[symlink]', 'upload', 'new'];
				for(let i=0; i < ids.length; ++i)
				{
					let widget = etemplate2.getById(id).widgetContainer.getWidgetById(ids[i]);
					if (widget)
					{
						widget.set_readonly(_ro);
					}
				}
			}
		}
	}

	/**
	 * Row or filename in select-file dialog clicked
	 *
	 * @param {jQuery.event} event
	 * @param {et2_widget} widget
	 */
	select_clicked(event, widget) : boolean
	{
		if (widget?.value?.is_dir)	// true for "httpd/unix-directory" and "egw/*"
		{
			let path = null;
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
			let editfield = this.et2.getWidgetById('name');
			if(editfield)
			{
				editfield.set_value(widget.value.name);
			}
		}
		else
		{
			let file = widget.value.name;
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
	}

	/**
	 * Set Sudo button's label and change its onclick handler according to its action
	 *
	 * @param {widget object} _widget sudo buttononly
	 * @param {string} _action string of action type {login|logout}
	 */
	set_sudoButton(_widget, _action: string)
	{
		let widget = _widget || this.et2.getWidgetById('sudouser');
		if (widget)
		{
			switch (_action)
			{
				case 'login':
					widget.set_label('Logout');
					widget.getRoot().getInstanceManager().submit(widget);
					break;

				default:
					widget.set_label('Superuser');
					widget.onclick = function(){
						jQuery('.superuser').css('display','inline');
					};
			}
		}
	}

	/**
	 * Open file a file dialog from EPL, warn if EPL is not available
	 */
	fileafile()
	{
		if (this.egw.user('apps').stylite)
		{
			this.egw.open_link('/index.php?menuaction=stylite.stylite_filemanager.upload&path='+this.get_path(), '_blank', '670x320');
		}
		else
		{
			// This is shown if stylite code is there, but the app is not available
			Et2Dialog.show_dialog(function(_button)
				{
					if(_button == Et2Dialog.YES_BUTTON)
					{
						window.open('http://www.egroupware.org/EPL', '_blank');
					}
					return true;
				}, this.egw.lang('this feature is only available in epl version.') + "\n\n" +
				this.egw.lang('You can use regular upload [+] button to upload files.') + "\n\n" +
				this.egw.lang('Do you want more information about EPL subscription?'),
				this.egw.lang('File a file'), undefined, Et2Dialog.BUTTONS_YES_NO, Et2Dialog.QUESTION_MESSAGE);
		}
	}

	/**
	 * create a share-link for the given entry
	 * Overriden from parent to handle empty directories
	 *
	 * @param {egwAction} _action egw actions
	 * @param {egwActionObject[]} _senders selected nm row
	 * @param {egwActionObject} _target Drag source.  Not used here.
	 * @param {Boolean} _writable Allow edit access from the share.
	 * @param {Boolean} _files Allow access to files from the share.
	 * @param {Function} _callback Callback with results
	 * @returns {Boolean} returns false if not successful
	 */
	share_link(_action, _senders, _target, _writable, _files, _callback)
	{
		// Check to see if we're in the empty row (No matches found.) and use current path
		let path = _senders[0].id;
		if(!path)
		{
			_senders[0] = {id: this.get_path()};
		}
		// Pass along any action data
		let _extra = {};
		for(let i in _action.data)
		{
			if(i.indexOf('share') == 0)
			{
				_extra[i] = _action.data[i];
			}
		}
		super.share_link(_action, _senders, _target, _writable, _files, _callback, _extra);
	}

	/**
	 * Share-link callback
	 * @param {object} _data
	 */
	_share_link_callback(_data)
	{
		if(_data.msg || _data.share_link) window.egw_refresh(_data.msg, this.appname);
		console.log("_data", _data);
		let app = this;

		let copy_link_to_clipboard = function (evt)
		{
			let $target = jQuery(evt.target);
			$target.select();
			try
			{
				let successful = document.execCommand('copy');
				if(successful)
				{
					egw.message(app.egw.lang('Share link copied into clipboard'));
					return true;
				}
			}
			catch(e)
			{
			}
			egw.message('Failed to copy the link!');
		};
		jQuery("body").on("click", "[name=share_link]", copy_link_to_clipboard);
		let dialog = new Et2Dialog(this.egw);
		dialog.transformAttributes({
			callback: function()
			{
				jQuery("body").off("click", "[name=share_link]", copy_link_to_clipboard);
				return true;
			},
			title: _data.title ? _data.title : (_data.writable || _data.action === 'shareWritableLink' ?
												this.egw.lang("Writable share link") : this.egw.lang("Readonly share link")
			),
			buttons: Et2Dialog.BUTTONS_OK,
			template: _data.template,
			width: 450,
			value: {content: {"share_link": _data.share_link}}
		});
		document.body.appendChild(dialog);
		dialog.addEventListener("load", () =>
		{
			dialog.template.widgetContainer.getWidgetById("share_link").onclick = copy_link_to_clipboard;
		});
	}

	/**
	 * Check if a row can have the Hidden Uploads action
	 * Needs to be a directory
	 */
	hidden_upload_enabled(_action : egwAction, _senders : egwActionObject[])
	{
		if(_senders[0].id == 'nm')
		{
			return false;
		}
		let data = egw.dataGetUIDdata(_senders[0].id);
		let readonly = (data?.data.class || '').split(/ +/).indexOf('noEdit') >= 0;

		// symlinks dont have mime 'http/unix-directory', but server marks all directories with class 'isDir'
		return (!_senders[0].id || data.data.is_dir && !readonly);
	}

	hiddenUploadOnOne(event)
	{
		let path = event.data?.options?.uploadPath || this.get_path() + "/Upload";
		this.upload(event, 1, path, 'rename', 'EGroupware\\Filemanager\\Sharing\\HiddenUpload::ajax_action');
	}

	/**
	 * View the link from an existing share
	 * (EPL only)
	 *
	 * @param {egwAction} _action The shareLink action
	 * @param {egwActionObject[]} _senders The row clicked on
	 */
	view_link(_action, _senders) : boolean
	{
		let id = egw.dataGetUIDdata(_senders[0].id).data.share_id;
		egw.json('stylite_filemanager::ajax_view_link', [id],
			this._share_link_callback, this, true, this).sendRequest();
		return true;
	}

	/**
	 * This function copies the selected file/folder entry as webdav link into clipboard
	 *
	 * @param {object} _action egw actions
	 * @param {object} _senders selected nm row
	 * @returns {Boolean} returns false if not successful
	 */
	copy_link(_action, _senders) : boolean
	{
		let data = egw.dataGetUIDdata(_senders[0].id);
		let url = data ? data.data.download_url : '/webdav.php'+this.id2path(_senders[0].id);
		if (url[0] == '/') url = egw.link(url);
		if (url.substr(0,4) == 'http'  && url.indexOf('://') <= 5) {
			// it's already a full url
		}
		else
		{
			let hostUrl = new URL(window.location.href);
			url = hostUrl.origin + url;
		}

		if (url)
		{
			let elem = jQuery(document.createElement('div'));
			let range;
			elem.text(url);
			elem.appendTo('body');
			if (document.selection)
			{
				range = document.body.createTextRange();
				range.moveToElementText(elem);
				range.select();
			}
			else if (window.getSelection)
			{
				range = document.createRange();
				range.selectNode(elem[0]);
				window.getSelection().removeAllRanges();
				window.getSelection().addRange(range);
			}

			let successful = false;
			try {
				successful = document.execCommand('copy');
				if (successful)
				{
					egw.message(this.egw.lang('WebDav link copied into clipboard'));
					window.getSelection().removeAllRanges();

					return true;
				}
			}
			catch (e) {}
			egw.message('Failed to copy the link!');
			elem.remove();
			return false;
		}
	}

	/**
	 * Function to check wheter selected file is editable. ATM only .odt is supported.
	 *
	 * @param {object} _egwAction egw action object
	 * @param {object} _senders object of selected row
	 *
	 * @returns {boolean} returns true if is editable otherwise false
	 */
	isEditable(_egwAction, _senders) : boolean
	{
		if (_senders.length>1) return false;
		let data = egw.dataGetUIDdata(_senders[0].id);
		let mime = this.et2.getInstanceManager().widgetContainer.getWidgetById('$row');
		let fe = egw.file_editor_prefered_mimes(data.data.mime);
		if (fe && fe.mime && !fe.mime[data.data.mime]) return false;
		return !!data.data.mime.match(mime.mime_odf_regex);
	}

	/**
	 * Method to create a new document
	 * @param {object} _action either action or node
	 * @param {object} _selected either widget or selected row
	 *
	 * @return {boolean} returns true
	 */
	create_new(_action, _selected) : boolean
	{
		let fe = egw.link_get_registry('filemanager-editor');
		if (fe && fe["edit"])
		{
			egw.open_link(egw.link('/index.php', {
				menuaction: fe["edit"].menuaction
			}), '', fe["popup_edit"]);
		}
		return true;
	}

	/**
	 * Mount scheme change --> enable/disable user, pass and host
	 */
	changeMountScheme()
	{
		const grid = this.et2.getWidgetById('mounts');
		const scheme = (<et2_selectbox>grid.getWidgetById('url[scheme]'))?.get_value();

		['url[user]', 'url[pass]', 'url[host]', 'colon', 'at'].forEach((name) => {
			(<et2_textbox>grid.getWidgetById(name))?.set_disabled(scheme !== 'webdavs' && scheme !== 'smb');
		});
		if (scheme === 'vfs')
		{
			['url[user]', 'at'].forEach((name) => {
				(<et2_textbox>grid.getWidgetById(name))?.set_disabled(false);
			});

		}
	}
}