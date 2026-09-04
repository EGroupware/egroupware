/**
 * EGroupware - Filemanager - Javascript UI
 *
 * @link https://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2008-21 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {EgwApp} from "../../api/js/jsapi/egw_app";
import type {PushData} from "../../api/js/jsapi/egw_app";
import type {Et2Nextmatch} from "../../api/js/etemplate/Et2Nextmatch/Et2Nextmatch";
import type {et2_nextmatch} from "../../api/js/etemplate/et2_extension_nextmatch";
import type {Et2DatagridUpdateType, Et2DatagridView} from "../../api/js/etemplate/Et2Datagrid/Et2Datagrid.types";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {Et2Dialog} from "../../api/js/etemplate/Et2Dialog/Et2Dialog";
// et2_file is a real, distinct legacy widget implementation (not a shim), passed as a runtime
// instanceof-filter value to iterateOver() below - see doc/ai/projects/app-ts-modernization.md.
import {et2_file} from "../../api/js/etemplate/et2_widget_file";
import type {et2_selectbox} from "../../api/js/etemplate/legacy-shims/et2_widget_selectbox";
// et2_textbox/et2_checkbox are also passed as runtime instanceof-filter values to iterateOver()
// below, alongside being used as cast types - so they stay value imports, like et2_file above.
import {et2_textbox} from "../../api/js/etemplate/legacy-shims/et2_widget_textbox";
import {et2_checkbox} from "../../api/js/etemplate/legacy-shims/et2_widget_checkbox";
import {MIME_REGEX} from "../../api/js/etemplate/Expose/ExposeMixin";
// egwAction is used both as a type and as a runtime `instanceof` check below - value import needed.
import {egwAction} from "../../api/js/egw_action/egw_action";
import type {egwActionObject} from "../../api/js/egw_action/egw_action";
import type {Et2VfsUpload} from "../../api/js/etemplate/Et2Vfs/Et2VfsUpload";
import type {Et2Button} from "../../api/js/etemplate/Et2Button/Et2Button";
// egw/app are ambient globals (declare global {} in egw_global.d.ts, unconditionally included
// via tsconfig's "**/*.d.ts") - no import needed or possible.

const VIEW_ROW : Et2DatagridView = "row";
const VIEW_TILE : Et2DatagridView = "tile";

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
				// uniqueId is private on etemplate2 with no public accessor
				this.path_widget[(<any>lists[i]).uniqueId] = lists[i].widgetContainer.getWidgetById('path');
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

		switch (name)
		{
			case 'filemanager.admin':
				this.changeMountScheme();
				return;
			case 'filemanager.jobs':
				// app.admin is typed generically as EgwApp; enableAppToolbar() is AdminApp-specific
				(<any>app.admin).enableAppToolbar(et2,name);
				return;
			case 'filemanager.index':
				if(egwIsMobile && egwIsMobile())
				{
					// Slot toolbar for landscape
					const orientation = window.matchMedia("(orientation: landscape)");
					orientation.addEventListener("change", this.handleOrientationChange.bind(this));
					this.handleOrientationChange(orientation);
				}
				break;
		}
		if(name === 'filemanager.index' && !et2.DOMContainer.closest("egw-app"))
		{
			// Anonymous view - with no egw-app to slot into, the toolbar gets relocated after
			// the form instead. Move just the toolbar back before it (not the form - moving the
			// form disconnects/reconnects the nextmatch and breaks tile view's first render).
			const toolbar = document.querySelector("#filemanager-index_filemanager-index-app-toolbar");
			if(toolbar && toolbar.parentElement === et2.DOMContainer.parentElement)
			{
				et2.DOMContainer.parentElement.insertBefore(toolbar, et2.DOMContainer);
			}
			const button_widget: Et2Button = document?.querySelector("#filemanager-index_toolbar_button\\[change_view\\]")
			if (button_widget)
			{
				const view = this.normalizeView(et2.app_obj?.filemanager?.nm?.view || VIEW_TILE);
				this.updateChangeViewButton(view, button_widget);
			}

		}

		let path_widget = this.et2.getWidgetById('path');
		if(path_widget)	// do NOT set not found path-widgets, as uploads works on first one only!
		{
			this.path_widget[et2.DOMContainer.id] = path_widget;
			// Bind to removal to remove from list
			et2.DOMContainer.addEventListener('clear', (e) =>
			{
				if (this.path_widget) delete this.path_widget[(<HTMLElement>e.target).id];
			});
		}

		if(this.et2.getWidgetById('nm'))
		{
			const nm : Et2Nextmatch = this.et2.getWidgetById('nm');
			const nm_node = nm.getDOMNode();

			// Native OS file-drop onto rows is surfaced as et2-filedrop.
			// Reuse the existing node reference and route the event into the
			// legacy filedrop() handler (row_uid, files).
			if(nm_node)
			{
				nm_node.addEventListener("et2-filedrop", (e : CustomEvent) =>
				{
					// Filemanager overrides the framework default (upload+link):
					// drop on a directory row uploads into that folder instead of
					// linking. Cancel the default so it doesn't also run.
					if(e.cancelable)
					{
						e.preventDefault();
					}
					this.filedrop(e.detail?.rowUid ?? "", e.detail?.files ?? []);
				});
			}

			// Drive tile view's optional meta rows from the visible columns.
			// By the time et2_ready() runs the nextmatch's firstUpdated() (and
			// thus its columns) have resolved, so apply now; et2-columns-changed
			// covers later column-selection changes.
			if(nm_node)
			{
				nm_node.addEventListener("et2-columns-changed", () => this.updateTileColumns(nm));
			}
			this.scheduleChangeViewButtonUpdate(nm, this.et2.getArrayMgr('content').getEntry('nm[view]'));
			this.updateTileColumns(nm);
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
			if(fe && fe["edit"] && new_widget)
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

		const pushedPath = String(pushData.id || "");
		// Special handling only for sub-dirs, super.push() handled everything else
		if(pushedPath == this.get_path() || !pushedPath.startsWith(this.get_path()))
		{
			return;
		}
		let nm = <Et2Nextmatch><unknown>this.et2?.getDOMWidgetById('nm');
		if(!nm)
		{
			return;
		}

		void nm.refreshChildRows(this.dirname(pushedPath), [pushedPath], pushData.type as Et2DatagridUpdateType);
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
		const path = state.state?.path || "~";

		// NM used to have path as a child widget, but now path is outside so we do some extra stuff.
		// Update the path widget without dispatching a change event; super.setState() applies the
		// restored filters once below.
		let etemplate_name;
		for(etemplate_name in this.path_widget) break;
		let dir = path;
		if(dir === "~")
		{
			dir = this.et2.getArrayMgr("content").getEntry("nm[home_dir]");
		}
		if(typeof etemplate_name === "string" && this.path_widget[etemplate_name])
		{
			this.path_widget[etemplate_name].set_value(dir);
		}
		const upload = this.et2.getWidgetById('upload');
		if(upload)
		{
			// Et2VfsUpload needs the trailing /
			upload.path = dir + '/';
		}
		if(typeof state.state === "undefined") state.state = {};

		state.state.col_filter ??= {};
		// Client side uses dir, not path
		state.state.col_filter.dir = dir;

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

			// Path used to be a child of nm, but its outside the nm now
			state.path = et2.widgetContainer.getWidgetById('path').value;
			delete state.col_filter.dir;
		}
		return state;
	}

	/**
	 * Link hander for jDots template to just reload our iframe, instead of reloading whole admin app
	 *
	 * @param {String} _url
	 * @return {boolean|string} true, if linkHandler took care of link, false for default processing or url to navigate to
	 */
	linkHandler(url)
	{
		const query = new URLSearchParams(decodeURI(url).split("?")[1]);
		if(query && query.has("path") && this.change_dir(query.get("path")) === undefined)
		{
			return true;
		}
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
	_push_field_filter(pushData : PushData, nm : et2_nextmatch | Et2Nextmatch, filter_fields : string[]) : boolean
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

	handlePathChange(_ev : Event, widget)
	{
		if(widget.getValue() == '')
		{
			this.change_dir('~', widget);
		}
		const upload = this.et2.getWidgetById('upload');
		if(upload)
		{
			// Et2VfsUpload needs the trailing /
			upload.path = widget.getValue() + '/';
		}
		this.nm && this.nm.applyFilters({col_filter: {dir: widget.getValue()}});

		return true;
	}

	/**
	 * Check if any NM filter or search in app-toolbar needs to be updated to reflect NM internal state
	 *
	 * Reimplement to use path instead of col_filter[dir]
	 *
	 * @param app_toolbar
	 * @param id
	 * @param value
	 */
	checkNmFilterChanged(app_toolbar, id : string, value : string)
	{
		super.checkNmFilterChanged(app_toolbar, id, value);

		if (id === 'dir')
		{
			super.checkNmFilterChanged(app_toolbar, 'path', value);
		}
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

		// Stop nm from refreshing, we'll get it on the push
		_event.stopPropagation();
		return false;
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
		if(_file_count && _event.detail)
		{
			let widget = _event.target;
			widget.loading = true;
			_event.detail.accepted = false; // Turn off removable, it's too late now
			const widgetValue = widget.getValue();
			const value = {};
			value[_event.detail.tempName] = {...widgetValue[_event.detail.tempName]};
			delete widgetValue[_event.detail.tempName];
			widget.value = widgetValue;
			value["conflict"] = _conflict;
			widget.requestUpdate("loading");
			egw.request(_target, ['upload', value, _path, {ui_path: this.egw.window.location.pathname}])
				.then(data => this._upload_callback(data))
				.finally(() =>
				{
					widget.loading = false;
					widget.requestUpdate("loading", true);
				});
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
		egw.request('filemanager_ui::ajax_action', [action == 'save_as' ? 'upload' : 'link', widget.getValue(), path, props])
			.then((_data) =>
			{
				// app.filemanager is typed generically as EgwApp; _upload_callback is filemanagerAPP-specific
				(<filemanagerAPP>app.filemanager)._upload_callback(_data);

				// Remove successful
				const widgetValue = widget.getValue();
				const value = {};
				widget.value = widgetValue;
				for(const file in _data.uploaded)
				{
					delete widgetValue[file];
				}
				widget.value = widgetValue;
				opener.egw_refresh('','filemanager',null,null,'filemanager');
			});
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
				// Et2Dialog.show_prompt()'s internal wrapper invokes this callback via
				// "_callback.call(this, ...)" with its own "this" (carrying "my_data") - an arrow
				// function here would ignore that and capture the outer method's "this" instead, so
				// this must stay a plain function. Same for the nested iterateOver() callback below,
				// which is likewise called with an explicit "this" as its context argument.
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
								egw.request('filemanager_ui::ajax_action', [this.my_data.action, uploaded, this.my_data.path, this.my_data.props])
									.then(data => that._upload_callback(data));
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
				// setting required data for callback in as my_data - not a real Et2Dialog property,
				// just an ad-hoc data bag the show_prompt() callback below reads back via "this.my_data"
				(<any>dialog).my_data = {
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
		clipboard.type = [...new Set(clipboard.type)];
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
		Et2Dialog.show_prompt((button, dir) =>
		{
			if(button && dir)
			{
				let path = this.get_path(action && action.parent ? action.parent.data.nextmatch.getInstanceManager().uniqueId : false);
				if(action && action instanceof egwAction)
				{
					let paths = this._elems2paths(selected);
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
							path = this.dirname(path);
						}
					}
				}
				this._do_action('createdir', egw.encodePathComponent(dir), false, path);
			}
		}, 'New directory', 'Create directory');
	}

	/**
	 * Prompt user for directory to create
	 */
	symlink()
	{
		Et2Dialog.show_prompt((button, target) =>
		{
			if(button && target)
			{
				this._do_action('symlink', target);
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
		if (_sync)
		{
			// Genuinely synchronous request - egw.request() is always async
			egw.json('filemanager_ui::ajax_action', [_type, _selected, _path],
				this._do_action_callback, this, false, this
			).sendRequest(false);
		}
		else
		{
			egw.request('filemanager_ui::ajax_action', [_type, _selected, _path])
				.then(data => this._do_action_callback(data));
		}
	}

	/**
	 * Callback for _do_action ajax call
	 *
	 * @param _data
	 */
	_do_action_callback(_data)
	{
		if(this.egw.pushAvailable())
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
			a.href = url;
			a.download = data ? data.data.name : "";
			this.et2.getDOMNode().appendChild(a);

			window.setTimeout(() =>
			{
				a.click();
				a.remove();
			}, 100*i);
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
		let etemplate_name;
		for(etemplate_name in this.path_widget) break;
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
				_dir = this.et2.getArrayMgr("content").getEntry("nm[home_dir]")
				break;
		}

		this.path_widget[etemplate_name].set_value(_dir);

		// Dispatch a change event for the nm
		this.path_widget[etemplate_name].dispatchEvent(new Event("change"));
	}

	private normalizeView(view) : Et2DatagridView
	{
		return view === VIEW_TILE ? VIEW_TILE : VIEW_ROW;
	}

	private templateForView(view : Et2DatagridView) : string
	{
		return view == VIEW_ROW ? "filemanager.index.rows" : "filemanager.tile";
	}

	private updateChangeViewButton(view : Et2DatagridView, button_widget? : Et2Button)
	{
		button_widget = button_widget || this.et2?.getWidgetById?.('button[change_view]') ||
			<Et2Button><unknown>document?.getElementById("filemanager-index_toolbar_button[change_view]");
		if(!button_widget)
		{
			return;
		}

		button_widget.image = egw.image("list_" + (view == VIEW_ROW ? VIEW_TILE : VIEW_ROW));
		//@ts-ignore statustext inherited from et2-widget
		button_widget.statustext = (view == VIEW_ROW ? this.egw.lang("Tile view") : this.egw.lang('List view'));
	}

	private scheduleChangeViewButtonUpdate(nm? : Et2Nextmatch, fallbackView? : string)
	{
		nm = nm || this.nm || this.et2?.getWidgetById?.('nm');
		// nm may still be the legacy et2_extension_nextmatch widget (eg. the Home favorite
		// portlet's filemanager.home.rows), which has no updateComplete promise and no
		// change-view button to update.
		nm?.updateComplete?.then(() => this.updateChangeViewButton(this.normalizeView(nm.view || fallbackView)));
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
		let nm : Et2Nextmatch;
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
			button_widget = (<Et2Nextmatch><unknown>nm).getWidgetById('button[change_view]');
		}
		if(button_widget)
		{
			// Switch view based on button icon, since the widget can get re-created
			if(typeof view != 'string')
			{
				view = button_widget.image.match(/\/(list|list_row)\.svg$/) ? VIEW_ROW : VIEW_TILE;
			}
			view = this.normalizeView(view);

			this.updateChangeViewButton(view, button_widget);
		}
		else
		{
			view = this.normalizeView(view);
			this.updateChangeViewButton(view);
		}

		nm.setView(view, this.templateForView(view)).then(() => nm.whenColumnsReady()).then(() =>
		{
			if(view === VIEW_TILE)
			{
				nm.collapseExpandedRows();
			}

			// View switches only change presentation. Keep NM/app state current
			// without re-fetching the same directory data or clearing row actions.
			nm.applyFilters({view: view}, {reload: false, clearActions: false});
			this.scheduleChangeViewButtonUpdate(nm, view);
			this.updateTileColumns(nm);

			// {reload: false} above means get_rows() never runs server-side, so it never
			// persists the view via its own nm_view preference save. Save it directly here
			// so we can skip the unneeded reload
			this.egw.set_preference('filemanager', 'nm_view', view);
		});
	}

	/**
	 * Drive the tile view's optional meta rows (modified, owner, comment, size)
	 * from the user's currently visible columns.
	 *
	 * The tile template (filemanager.tile) declares these as columns and the
	 * CSS gates each meta row behind a --filemanager-tile-*-display custom
	 * property that defaults to "none".  Here we set those properties to match
	 * which columns are currently visible, so the tiles honour the user's column
	 * selection both on initial load and when switching between tile and list view.
	 *
	 * Columns are resolved asynchronously and, because filemanager preloads its
	 * rows (setInitialRows, not reload), no loading event fires - so we wait for
	 * the nextmatch's update cycle and retry briefly until the columns appear.
	 *
	 * @param {Et2Nextmatch} [nm] - The nextmatch to read columns from.  Defaults to this.nm.
	 */
	updateTileColumns(nm? : Et2Nextmatch)
	{
		nm = nm || <Et2Nextmatch>this.nm;
		if(!nm)
		{
			return;
		}
		const host = nm.getDOMNode();
		if(!host || typeof host.style?.setProperty !== "function")
		{
			return;
		}

		// Map tile column key -> CSS custom property controlling its meta row
		const columnProperty = {
			size: "--filemanager-tile-size-display",
			mtime: "--filemanager-tile-mtime-display",
			uid: "--filemanager-tile-owner-display",
			comment: "--filemanager-tile-comment-display"
		};

		// Only tile view has these meta rows - clear everything for list view
		if(nm.view !== VIEW_TILE)
		{
			host.style.removeProperty("--filemanager-tile-meta-display");
			for(const property of Object.values(columnProperty))
			{
				host.style.removeProperty(property);
			}
			return;
		}

		const apply = () =>
		{
			// Currently visible columns, as reported by the nextmatch value
			const selectcols : string[] = nm.getValue?.()?.selectcols || [];
			let anyVisible = false;
			for(const [key, property] of Object.entries(columnProperty))
			{
				const visible = selectcols.indexOf(key) !== -1;
				host.style.setProperty(property, visible ? "flex" : "none");
				anyVisible = anyVisible || visible;
			}
			// Show the meta container only if at least one optional row is visible
			host.style.setProperty("--filemanager-tile-meta-display", anyVisible ? "flex" : "none");
		};

		// Et2Nextmatch resolves whenColumnsReady() once the template columns
		// are derived (getValue().selectcols populated).  It's a side-channel
		// promise, independent of updateComplete, so awaiting it can't stall the
		// load - and unlike an event, a resolved promise is safe to await late.
		nm.whenColumnsReady().then(() => apply());
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
		// no row data: sender is a placeholder row (grid hasn't loaded it yet, eg. a
		// double-click into a directory before the new rows finished loading) - ignore
		if (!data?.data) return false;
		let path = this.id2path(_senders[0].id);
		this.et2 = this.et2 ? this.et2 : etemplate2.getById('filemanager-index').widgetContainer;
		// try to get mime widget DOM node out of the row DOM
		let mime_dom : HTMLElement = _senders[0].iface.getDOMNode().querySelector("et2-vfs-mime");
		// egw.get_file_editor_prefered_mimes() needs the actual mime type - see note on the
		// ambient-global "egw" import at the top of the file
		let fe : any = egw.file_editor_prefered_mimes(data.data.mime);

		// symlinks dont have mime 'http/unix-directory', but server marks all directories with class 'isDir'
		if (data.data.mime == 'httpd/unix-directory' || data.data['class'] && data.data['class'].split(/ +/).indexOf('isDir') != -1)
		{
			this.change_dir(path,_action.parent.data.nextmatch || this.et2);
		}
		else if(data.data.mime.match(MIME_REGEX) && mime_dom)
		{
			mime_dom.click();
		}
		else if (this.isEditable(_action, _senders) && fe && fe.edit)
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
				!dir && path_widget && !path_widget.readonly
		});

		// Target, if directory
		let target_dir = this.id2path(_target.id);
		dir = egw.dataGetUIDdata(_target.id);
		actions.push({
			             id: _action.id + '_target',
			             caption: target_dir,
			             path: target_dir,
			             enabled: _target && _target.iface && _target.iface.getDOMNode().classList.contains('isDir') &&
				             (dir && dir.data && dir.data.class && dir.data.class.indexOf('noEdit') === -1 || !dir)
		             });

		// Last 10 folders
		// egw.preference() can come back as a plain {"0":...,"1":...} object rather than a real
		// array (depends on how the server last JSON-encoded it) - Object.values() handles both.
		let previous_dsts : string[] = Object.values(egw.preference('drop_history', this.appname) || []);
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
		let paste_exec = (action, selected) => {
			// Add in clipboard as a sender
			let clipboard = JSON.parse(egw.getSessionItem('phpgwapi', 'egw_clipboard'));

			// Set a flag so apps can tell the difference, if they need to
			action.set_onExecute(action.parent.onExecute.functionToPerform);
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
			_action.getActionById(actions[i].id).onExecute = _action.onExecute.clone();

			_action.getActionById(actions[i].id).set_onExecute(paste_exec);
		}

		// Changing the actions like this is incompatible with re-using the menu
		// Remove the menu it so it will be re-generated
		const nm_action = _action.getManager();
		if(actions.length > 0 && nm_action && nm_action.data?.menu)
		{
			nm_action.data.menu.remove();
			delete nm_action.data.menu;
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
		// egw.preference() can come back as a plain {"0":...,"1":...} object rather than a real
		// array (depends on how the server last JSON-encoded it) - Object.values() handles both.
		let previous_dsts : string[] = Object.values(egw.preference('drop_history', this.appname) || []);
		previous_dsts.unshift(dst);
		previous_dsts = Array.from(new Set(previous_dsts)).slice(0, 9);
		egw.set_preference(this.appname, 'drop_history', previous_dsts);

		// Actual action id will be something like file_drop_{move|copy|link}[_other_id],
		// but we need to send move, copy or link
		let action_id = _action.id.replace("file_drop_", '').split('_', 1)[0];

		// Check for existing
		let existingChecks = [];
		src.forEach((src_path, index) =>
		{
			if(action_id == 'link')
			{
				return;
			}

			// Get mimetype
			const mimetype = _elems[index]?.data?.mime || "";
			const originalName = this.basename(src_path);
			const path = (dst || nm_dst) + "/";
			existingChecks.push(
				Et2Dialog.confirm_file(this.et2.getInstanceManager()._etemplate_exec_id, path, originalName, mimetype, false, this.et2.egw())
					.then(name =>
					{
						// No conflict
						if(name == null)
						{
							return;
						}
						src.splice(index, 1);
						if(name == false)
						{
							// Skip
							return;
						}
						// Just this file to new target
						this._do_action(action_id, [src_path], false, path + name);
					})
			);
		});
		Promise.all(existingChecks).then(() =>
		{
			if(src.length > 0)
			{
				this._do_action(action_id, src, false, dst || nm_dst);
			}
		});
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
		let data = egw.dataGetUIDdata(row_uid);
		files = files || (<DragEvent>window.event).dataTransfer.files;

		const path = typeof data != 'undefined' && data.data.mime == "httpd/unix-directory" ? data.data.path : this.get_path();
		const widget = <Et2VfsUpload>this.et2.getWidgetById('upload');
		const oldPath = widget.path;

		// Set target sub-directory
		widget.path = path + '/';

		// Restore original path when finished
		widget.addEventListener("change", (e) =>
		{
			widget.path = oldPath;
		}, {once: true});

		// This triggers the upload
		Object.values(files).forEach((file : File) => widget.addFile(file));

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
						if (ids[i] === 'upload')
						{
							widget.disabled = _ro
						} else
						{
						widget.set_readonly(_ro);
						}
					}
				}
			}
		}
	}

	/**
	 * Row or filename in select-file dialog clicked
	 *
	 * @param {Event} event
	 * @param {et2_widget} widget
	 */
	select_clicked(event, widget) : boolean
	{
		if (widget?.fileInfo?.is_dir)	// true for "httpd/unix-directory" and "egw/*"
		{
			let path = null;
			// Cannot do this, there are multiple widgets named path
			// widget.getRoot().getWidgetById("path");
			widget.getRoot().iterateOver((widget) => {
				if(widget.id == "path") path = widget;
			},null, et2_textbox);
			// this is the old filemanager.select UI
			if (!path && (path = widget.getRoot().getWidgetById("path")))
			{
				path.set_value(widget.fileInfo.path);
				widget.getInstanceManager().submit();
				return;
			}
			if(path)
			{
				path.set_value(widget.fileInfo.path);
			}
		}
		else if (this.et2 && this.et2.getArrayMgr('content').getEntry('mode') != 'open-multiple')
		{
			let editfield = this.et2.getWidgetById('name');
			if(editfield)
			{
				editfield.set_value(widget.fileInfo.name);
			}
		}
		else
		{
			let file = widget.fileInfo.name;
			widget.getParent().iterateOver((widget) =>
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
					widget.onclick = () =>
					{
						document.querySelectorAll<HTMLElement>('.superuser').forEach(el => el.style.display = 'inline');
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
			Et2Dialog.show_dialog((_button) =>
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
		if(_data.msg)
		{
			this.egw.message(_data.msg);
		}
		if(_data.share_link)
		{
			this.et2.getInstanceManager().refresh();
		}
		let dialog = new Et2Dialog(this.egw);
		dialog.transformAttributes({
			title: _data.title ? _data.title : (_data.writable || _data.action === 'shareWritableLink' ?
												this.egw.lang("Writable share link") : this.egw.lang("Readonly share link")
			),
			buttons: Et2Dialog.BUTTONS_OK,
			template: _data.template,
			width: 450,
			value: {content: {"share_link": _data.share_link}}
		});
		document.body.appendChild(dialog);
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
		let readonly = (data?.data?.class || '').split(/ +/).indexOf('noEdit') >= 0;

		// symlinks dont have mime 'http/unix-directory', but server marks all directories with class 'isDir'
		return (!_senders[0].id || data?.data?.is_dir && !readonly);
	}

	hiddenUploadComplete(event)
	{
		if(!event.detail || Object.values(event.detail).length == 0)
		{
			return;
		}

		this.egw.message(
			this.egw.lang("The uploaded file is only visible to the person sharing these files with you, not to yourself or other people knowing this sharing link."),
			'info'
		);
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
		egw.request('stylite_filemanager::ajax_view_link', [id])
			.then(data => this._share_link_callback(data));
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
			// document.selection/createTextRange were IE-only fallbacks for browsers with no
			// window.getSelection() - no longer needed, every currently-supported browser has it.
			const elem = document.createElement('div');
			elem.textContent = url;
			document.body.appendChild(elem);
			const range = document.createRange();
			range.selectNode(elem);
			window.getSelection().removeAllRanges();
			window.getSelection().addRange(range);

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
		// no row data: the sender is not a file row (eg. the datagrid's empty-list
		// placeholder, which still offers mkdir/paste/share) - nothing to edit
		let data = egw.dataGetUIDdata(_senders[0]?.id);
		if (!data?.data?.mime) return false;
		let fe : any = egw.file_editor_prefered_mimes(data.data.mime);
		return !!(fe?.mime && typeof fe.mime[data.data.mime] !== "undefined");
	}

	checkInvoice(_egwAction, _senders) : boolean
	{
		if (_senders.length>1) return false;
		let data = egw.dataGetUIDdata(_senders[0]?.id);
		return !!(data?.data?.mime && ['application/pdf', 'text/xml', 'application/xml'].includes(data.data.mime));
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

	/**
	 * Create a new job
	 */
	addJob()
	{
		this.openDialog('filemanager.\\EGroupware\\Filemanager\\Jobs.edit');
	}

	/**
	 * Mobile portrait orientation is not wide enough for the path, so we put it below
	 *
	 * @param event
	 */
	handleOrientationChange(event)
	{
		const toolbar = this.et2.getWidgetById("toolbar");
		toolbar.slot = event?.matches ? "main-header" : "";
		// kdots slotting will append it to the bottom, we need top
		toolbar.parentElement.prepend(toolbar);
	}
}
