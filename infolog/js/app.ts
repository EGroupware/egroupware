/**
 * EGroupware - Infolog - Javascript UI
 *
 * @link: https://www.egroupware.org
 * @package infolog
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
	/api/js/jsapi/egw_app.js
 */

import 'jquery';
import 'jqueryui';
import '../jsapi/egw_global';
import '../etemplate/et2_types';

import {EgwApp} from '../../api/js/jsapi/egw_app';
import {et2_dialog} from "../../api/js/etemplate/et2_widget_dialog";
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {et2_nextmatch} from "../../api/js/etemplate/et2_extension_nextmatch";
import {CRMView} from "../../addressbook/js/CRM";

/**
 * UI for Infolog
 *
 * @augments AppJS
 */
class InfologApp extends EgwApp
{

	// Hold on to ACL grants
	private _grants : any;

	/**
	 * Constructor
	 *
	 * @memberOf app.infolog
	 */
	constructor()
	{
		// call parent
		super('infolog');
	}

	/**
	 * Destructor
	 */
	destroy(_app)
	{
		// call parent
		super.destroy(_app);
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} _et2 newly ready object
	 * @param {string} _name template name
	 */
	et2_ready(_et2, _name)
	{
		// call parent
		super.et2_ready(_et2, _name);

		// CRM View
		if(typeof CRMView !== "undefined")
		{
			CRMView.view_ready(_et2, this);
		}

		switch(_name)
		{
			case 'infolog.index':
				this.filter_change();
				// Show / hide descriptions according to details filter
				var nm = this.et2.getWidgetById('nm');
				var filter2 = nm.getWidgetById('filter2');
				this.show_details(filter2.value == 'all',nm.getDOMNode(nm));
				// Remove the rule added by show_details() if the template is removed
				jQuery(_et2.DOMContainer).on('clear', jQuery.proxy(function() {egw.css(this);}, '#' + nm.getDOMNode(nm).id + ' .et2_box.infoDes'));

				// Enable decrypt on hover
				if(this.egw.user('apps').stylite)
				{
					this._get_stylite(function() {this.mailvelopeAvailable(function() {app.stylite?.decrypt_hover(nm);});});
				}
				break;
			case 'infolog.edit.print':
				if (this.et2.getArrayMgr('content').data.info_des.indexOf(this.begin_pgp_message) != -1)
				{
					this.mailvelopeAvailable(this.printEncrypt);
				}
				else
				{
					// Trigger print command if the infolog oppend for printing purpose
					this.infolog_print_preview_onload();
				}
				break;
			case 'infolog.edit':
				if (this.et2.getArrayMgr('content').data.info_des &&
					this.et2.getArrayMgr('content').data.info_des.indexOf(this.begin_pgp_message) != -1)
				{
					this._get_stylite(jQuery.proxy(function() {this.mailvelopeAvailable(jQuery.proxy(function() {
						this.toggleEncrypt();

						// Decrypt history on hover
						var history = this.et2.getWidgetById('history');
						app.stylite.decrypt_hover(history,'span');
						jQuery(history.getDOMNode(history))
							.tooltip('option','position',{my:'top left', at: 'top left', of: history.getDOMNode(history)});

					},this));},this));
					// This disables the diff in history
					var history = this.et2.getArrayMgr('content').getEntry('history');
					history['status-widgets'].De = 'description';
				}
				break;
		}
	}

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * InfoLog currently reacts to timesheet updates, as it might show time-sums.
	 * @todo only trigger update, if times are shown
	 *
	 * @param {string} _msg message (already translated) to show, eg. 'Entry deleted'
	 * @param {string} _app application name
	 * @param {(string|number)} _id id of entry to refresh or null
	 * @param {string} _type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: requires full reload for proper sorting
	 * @param {string} _msg_type 'error', 'warning' or 'success' (default)
	 * @param {object|null} _links app => array of ids of linked entries
	 * or null, if not triggered on server-side, which adds that info
	 */
	observer(_msg, _app, _id, _type, _msg_type, _links)
	{
		if (typeof _links != 'undefined')
		{
			if (typeof _links.infolog != 'undefined')
			{
				switch (_app)
				{
					case 'timesheet':
						var nm = this.et2 ? this.et2.getWidgetById('nm') : null;
						if (nm) nm.applyFilters();
						break;
				}
			}
		}
		// Refresh handler for Addressbook CRM view
		if (_app == 'infolog' && this.et2.getInstanceManager() && this.et2.getInstanceManager().app == 'addressbook' && this.et2.getInstanceManager().name == 'infolog.index')
		{
			this.et2._inst.refresh(_msg, _app, _id, _type);
		}
	}
	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * @param  pushData
	 * @param {string} pushData.app application name
	 * @param {(string|number)} pushData.id id of entry to refresh or null
	 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: ask server for data, add in intelligently
	 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param {number} pushData.account_id User that caused the notification
	 */
	push(pushData)
	{
		if(pushData.app !== this.appname) return;

		// pushData does not contain everything, just the minimum.
		let event = pushData.acl || {};

		if(pushData.type === 'delete')
		{
			return super.push(pushData);
		}

		// If we know about it and it's an update, just update.
		// This must be before all ACL checks, as responsible might have changed and entry need to be removed
		// (server responds then with null / no entry causing the entry to disapear)
		if (pushData.type !== "add" && this.egw.dataHasUID(this.uid(pushData)))
		{
			return this.et2.getInstanceManager().refresh("", pushData.app, pushData.id, pushData.type);
		}

		// check visibility - grants is ID => permission of people we're allowed to see
		if (typeof this._grants === 'undefined')
		{
			this._grants = egw.grants(this.appname);
		}
		// check user has a grant from owner or a responsible
		if (this._grants && typeof this._grants[pushData.acl.info_owner] === 'undefined' &&
			// responsible gets implicit access, so we need to check them too
			!pushData.acl.info_responsible.filter(res => typeof this._grants[res] !== 'undefined').length)
		{
			// No ACL access
			return;
		}

		// no responsible means, owner is responsible
		if (!pushData.acl.info_responsible || !pushData.acl.info_responsible.length)
		{
			pushData.acl.info_responsible = [pushData.acl.info_owner];
		}

		// Filter what's allowed down to those we care about
		let filters = {
			owner: {col: "info_owner", filter_values: []},
			responsible: {col: "info_responsible", filter_values: []}
		};
		if(this.et2)
		{
			this.et2.iterateOver( function(nm) {
				let value = nm.getValue();
				if(!value || !value.col_filter) return;

				for(let field_filter of Object.values(filters))
				{
					if(value.col_filter[field_filter.col])
					{
						field_filter.filter_values.push(value.col_filter[field_filter.col]);
					}
				}
			},this, et2_nextmatch);
		}

		// check filters against ACL data
		for(let field_filter of Object.values(filters))
		{
			// no filter set
			if (field_filter.filter_values.length == 0) continue;

			// acl value is a scalar (not array) --> check contained in filter
			if (pushData.acl && typeof pushData.acl[field_filter.col] !== 'object')
			{
				if (field_filter.filter_values.indexOf(pushData.acl[field_filter.col]) < 0)
				{
					return;
				}
				continue;
			}
			// acl value is an array (eg. info_responsible) --> check intersection with filter
			if(!field_filter.filter_values.filter(account => pushData.acl[field_filter.col].indexOf(account) >= 0).length)
			{
				return;
			}
		}

		// Pass actual refresh on to just nextmatch
		let nm = <et2_nextmatch>this.et2.getDOMWidgetById('nm');
		nm.refresh(pushData.id, pushData.type);
	}

	/**
	 * Retrieve the current state of the application for future restoration
	 *
	 * Reimplemented to add action/action_id from content set by server
	 * when eg. viewing infologs linked to contacts.
	 *
	 * @return {object} Application specific map representing the current state
	 */
	getState()
	{
		// call parent
		var state = super.getState();
		var nm : any = {};

		// Get index etemplate
		var et2 = etemplate2.getById('infolog-index');
		if(et2)
		{
			var content = et2.widgetContainer.getArrayMgr('content');
			nm = content && content.data && content.data.nm ? content.data.nm: {};
		}

		state.action = nm.action || null;
		state.action_id = nm.action_id || null;

		return state;
	}

	/**
	 * Set the application's state to the given state.
	 *
	 * Reimplemented to also reset action/action_id.
	 *
	 * @param {{name: string, state: object}|string} state Object (or JSON string) for a state.
	 *	Only state is required, and its contents are application specific.
	 *
	 * @return {boolean} false - Returns false to stop event propagation
	 */
	setState(state)
	{
		// as we have to set state.state.action, we have to set all other
		// for "No filter" favorite to work as expected
		var to_set = {col_filter: null, filter: '', filter2: '', cat_id: '', search: '', action: null};
		if (typeof state.state == 'undefined') state.state = {};
		for(var name in to_set)
		{
			if (typeof state.state[name] == 'undefined') state.state[name] = to_set[name];
		}
		return super.setState(state);
	}

	/**
	 * Enable or disable the date filter
	 *
	 * If the filter is set to something that needs dates, we enable the
	 * header_left template.  Otherwise, it is disabled.
	 */
	filter_change()
	{
		var filter = this.et2.getWidgetById('filter');
		var nm = this.et2.getWidgetById('nm');
		var dates = this.et2.getWidgetById('infolog.index.dates');
		if(nm && filter)
		{
			switch(filter.getValue())
			{
				case 'bydate':
				case 'duedate':

					if (filter && dates)
					{
						dates.set_disabled(false);
						window.setTimeout(function() {
							jQuery(dates.getWidgetById('startdate').getDOMNode()).find('input').focus();
						},0);
					}
					break;
				default:
					if (dates)
					{
						dates.set_disabled(true);
					}
					break;
			}
		}
	}

	/**
	 * show or hide the details of rows by selecting the filter2 option
	 * either 'all' for details or 'no_description' for no details
	 *
	 * @param {Event} event Change event
	 * @param {et2_nextmatch} nm The nextmatch widget that owns the filter
	 */
	filter2_change(event, nm)
	{
		var filter2 = nm.getWidgetById('filter2');

		if (nm && filter2)
		{
			// Show / hide descriptions
			this.show_details(filter2.get_value() === 'all', nm.getDOMNode(nm));
		}

		// Only change columns for a real user event, to avoid interfering with
		// favorites
		if (nm && filter2 && !nm.update_in_progress)
		{
			// Store selection as implicit preference
			egw.set_preference('infolog', nm.options.settings.columnselection_pref.replace('-details','')+'-details-pref', filter2.value);

			// Change preference location - widget is nextmatch
			nm.options.settings.columnselection_pref = nm.options.settings.columnselection_pref.replace('-details','') + (filter2.value == 'all' ? '-details' :'');

			// Load new preferences
			var colData = nm.columns.slice();
			for(var i = 0; i < nm.columns.length; i++) colData[i].visible=false;

			if(egw.preference(nm.options.settings.columnselection_pref,'infolog'))
			{
				nm.set_columns((<String>egw.preference(nm.options.settings.columnselection_pref,'infolog')).split(','));
			}
			nm._applyUserPreferences(nm.columns, colData);

			// Now apply them to columns
			for(var i = 0; i < colData.length; i++)
			{
				nm.dataview.getColumnMgr().columns[i].set_width(colData[i].width);
				nm.dataview.getColumnMgr().columns[i].set_visibility(colData[i].visible);
			}
			nm.dataview.getColumnMgr().updated();

			// Update page - set update_in_progress to true to avoid triggering
			// the change handler and looping if the user has a custom field
			// column change
			var in_progress = nm.update_in_progress;
			nm.update_in_progress = true;
			// Set the actual filter value here
			nm.activeFilters.filter2 = filter2.get_value();
			nm.dataview.updateColumns();
			nm.update_in_progress = in_progress;
		}
		return false;
	}

	/**
	 * Show or hide details by changing the CSS class
	 *
	 * @param {boolean} show
	 * @param {DOMNode} dom_node
	 */
	show_details(show, dom_node)
	{
		// Show / hide descriptions
        egw.css((dom_node && dom_node.id ? "#"+dom_node.id+' ' : '') + ".et2_box.infoDes","display:" + (show ? "block;" : "none;"));
		if (egwIsMobile())
		{
			var $select = jQuery('.infoDetails');
			(show)? $select.each(function(i,e){jQuery(e).hide();}): $select.each(function(i,e){jQuery(e).show();});
		}
	}

	/**
	 * Confirm delete
	 * If entry has children, asks if you want to delete children too
	 *
	 *@param _action
	 *@param _senders
	 */
	confirm_delete(_action, _senders)
	{
		let children = false;
		let child_button = jQuery('#delete_sub').get(0) || jQuery('[id*="delete_sub"]').get(0);
		this._action_all = _action.parent.data.nextmatch?.getSelection().all;
		this._action_ids = [];
		if(child_button)
		{
			for(let i = 0; i < _senders.length; i++)
			{
				this._action_ids.push(_senders[i].id.split("::").pop());

				if (jQuery(_senders[i].iface.getDOMNode()).hasClass('infolog_rowHasSubs'))
				{
					children = true;
					break;
				}
			}
			child_button.style.display = children ? 'block' : 'none';
		}
		nm_open_popup(_action, _senders);
	}

	private _action_ids = [];
	private _action_all = false;

	/**
	 * Callback for action using ids set(!) in this._action_ids and this._action_all
	 *
	 * @param _action
	 */
	actionCallback(_action)
	{
		egw.json("infolog.infolog_ui.ajax_action",[_action, this._action_ids, this._action_all]).sendRequest(true);
	}

	/**
	 * Add email from addressbook
	 *
	 * @param ab_id
	 * @param info_cc
	 */
	add_email_from_ab(ab_id,info_cc)
	{
		var ab = <HTMLSelectElement>document.getElementById(ab_id);

		if (!ab || !ab.value)
		{
			jQuery("tr.hiddenRow").css("display", "table-row");
		}
		else
		{
			var cc = <HTMLInputElement>document.getElementById(info_cc);

			for(var i=0; i < ab.options.length && ab.options[i].value != ab.value; ++i) ;

			if (i < ab.options.length)
			{
				cc.value += (cc.value?', ':'')+ab.options[i].text.replace(/^.* <(.*)>$/,'$1');
				ab.value = '';
				// @ts-ignore
				ab.onchange();
				jQuery("tr.hiddenRow").css("display", "none");
			}
		}
		return false;
	}

	/**
	* If one of info_status, info_percent or info_datecompleted changed --> set others to reasonable values
	*
	* @param {string} changed_id id of changed element
	* @param {string} status_id
	* @param {string} percent_id
	* @param {string} datecompleted_id
	*/
	status_changed(changed_id, status_id, percent_id, datecompleted_id)
	{
		// Make sure this doesn't get executed while template is loading
		if(this.et2 == null || this.et2.getInstanceManager() == null) return;

		var status = <HTMLInputElement>document.getElementById(status_id);
		var percent = <HTMLInputElement>document.getElementById(percent_id);
		var datecompleted = <HTMLInputElement>document.getElementById(datecompleted_id+'[str]');
		if(!datecompleted)
		{
			datecompleted = <HTMLInputElement>jQuery('#'+datecompleted_id +' input').get(0);
		}
		var completed;

		switch(changed_id)
		{
			case status_id:
				completed = status.value == 'done' || status.value == 'billed';
				if (completed || status.value == 'not-started' ||
					(status.value == 'ongoing') != (parseFloat(percent.value) > 0 && parseFloat(percent.value) < 100))
				{
					if(completed)
					{
						percent.value = '100';
					}
					else if (status.value == 'not-started')
					{
						percent.value = '0';
					}
					else if (!completed && (parseInt(percent.value) == 0 || parseInt(percent.value) == 100))
					{
						percent.value = '10';
					}
				}
				break;

			case percent_id:
				completed = parseInt(percent.value) == 100;
				if (completed != (status.value == 'done' || status.value == 'billed') ||
					(status.value == 'not-started') != (parseInt(percent.value) == 0))
				{
					status.value = parseInt(percent.value) == 0 ? (jQuery('[value="not-started"]',status).length ?
						'not-started':'ongoing') : (parseInt(percent.value) == 100 ? 'done' : 'ongoing');
				}
				break;

			case datecompleted_id+'[str]':
			case datecompleted_id:
				completed = datecompleted.value != '';
				if (completed != (status.value == 'done' || status.value == 'billed'))
				{
					status.value = completed ? 'done' : 'not-started';
				}
				if (completed != (parseInt(percent.value) == 100))
				{
					percent.value = completed ? '100' : '0';
				}
				break;
		}
		if (!completed && datecompleted && datecompleted.value != '')
		{
			datecompleted.value = '';
		}
		else if (completed && datecompleted && datecompleted.value == '')
		{
			// todo: set current date in correct format
		}
	}

	/**
	 * handle "print" action from "Actions" selectbox in edit infolog window.
	 * check if the template is dirty then submit the template otherwise just open new window as print.
	 *
	 */
	edit_actions()
	{
		var widget = this.et2.getWidgetById('action');
		var template = this.et2._inst;
		if (template)
		{
			var id = template.widgetContainer.getArrayMgr('content').data['info_id'];
		}
		if (widget)
		{
			switch (widget.get_value())
			{
				case 'print':
					if (template.isDirty())
					{
						template.submit();
					}
					egw.open(id,'infolog','edit',{print:1});
					break;
				case 'ical':
					template.postSubmit();
					break;
				default:
					template.submit();
			}
		}
	}

	/**
	 * Open infolog entry for printing
	 *
	 * @param {aciton object} _action
	 * @param {object} _selected
	 */
	infolog_menu_print(_action, _selected)
	{
		var id = _selected[0].id.replace(/^infolog::/g,'');
		egw.open(id,'infolog','edit',{print:1});
	}

	/**
	 * Trigger print() onload window
	 */
	infolog_print_preview_onload()
	{
		var that = this;
		jQuery('#infolog-edit-print').bind('load',function(){
			var isLoadingCompleted = true;
			jQuery('#infolog-edit-print').bind("DOMSubtreeModified",function(event){
					isLoadingCompleted = false;
					jQuery('#infolog-edit-print').unbind("DOMSubtreeModified");
			});
			setTimeout(function() {
				isLoadingCompleted = false;
			}, 1000);
			var interval = setInterval(function(){
				if (!isLoadingCompleted)
				{
					clearInterval(interval);
					that.infolog_print_preview();
				}
			}, 100);
		});
	}

	/**
	 * Trigger print() function to print the current window
	 */
	infolog_print_preview()
	{
		this.egw.message(this.egw.lang('Printing...'));
		this.egw.window.print();
	}

	/**
	 *
	 */
	add_link_sidemenu()
	{
		egw.open('','infolog','add');
	}

	/**
	 * Wrapper so add -> New actions in the context menu can pass current
	 * filter values into new edit dialog
	 *
	 * @see add_with_extras
	 *
	 * @param {egwAction} action
	 * @param {egwActionObject[]} selected
	 */
	add_action_handler(action, selected)
	{
		var nm = action.getManager().data.nextmatch || false;
		if(nm)
		{
			this.add_with_extras(nm,action.id,
				nm.getArrayMgr('content').getEntry('action'),
				nm.getArrayMgr('content').getEntry('action_id')
			);
		}
	}

	/**
	 * Opens a new edit dialog with some extra url parameters pulled from
	 * standard locations.  Done with a function instead of hardcoding so
	 * the values can be updated if user changes them in UI.
	 *
	 * @param {et2_widget} widget Originating/calling widget
	 * @param _type string Type of infolog entry
	 * @param _action string Special action for new infolog entry
	 * @param _action_id string ID for special action
	 */
	add_with_extras(widget,_type, _action, _action_id)
	{
		// We use widget.getRoot() instead of this.et2 for the case when the
		// addressbook tab is viewing a contact + infolog list, there's 2 infolog
		// etemplates
		var nm = widget.getRoot().getWidgetById('nm');
		var nm_value = nm.getValue() || {};

		// It's important that all these keys are here, they override the link
		// registry.
		var action_id = nm_value.action_id ? nm_value.action_id : (_action_id != '0' ? _action_id : "") || "";
		if(typeof action_id == "object" && typeof action_id.length == "undefined")
		{
			// Need a real array here
			action_id = jQuery.map(action_id,function(val) {return val;});
		}

		// No action?  Try the linked filter, in case it's set
		if(!_action && !_action_id)
		{
			if(nm_value.col_filter && nm_value.col_filter.linked)
			{
				var split = nm_value.col_filter.linked.split(':') || '';
				_action = split[0] || '';
				action_id = split[1] || '';
			}
		}
		var extras = {
			type: _type || nm_value.col_filter.info_type || "task",
			cat_id: nm_value.cat_id || "",
			action: nm_value.action || _action || "",
			// egw_link can handle arrays; but server is expecting CSV
			action_id: typeof action_id.join != "undefined" ? action_id.join(',') : action_id
		};
		egw.open('','infolog','add',extras);
	}

	/**
	 * Get title in order to set it as document title
	 * @returns {string}
	 */
	getWindowTitle()
	{
		var widget = this.et2.getWidgetById('info_subject');
		if(widget) return widget.options.value;
	}

	/**
	 * View parent entry with all children
	 *
	 * @param {aciton object} _action
	 * @param {object} _selected
	 */
	view_parent(_action, _selected)
	{
		var data = egw.dataGetUIDdata(_selected[0].id);
		if (data && data.data && data.data.info_id_parent)
		{
			egw.link_handler(egw.link('/index.php', {
				menuaction: "infolog.infolog_ui.index",
				action: "sp",
				action_id: data.data.info_id_parent,
				ajax: "true"
			}), "infolog");
		}
	}

	/**
	 * Mess with the query for parent widget to exclude self
	 *
	 * @param {Object} request
	 * @param {et2_link_entry} widget
	 * @returns {boolean}
	 */
	parent_query(request, widget)
	{
		// No ID yet, no need to filter
		if(!widget.getRoot().getArrayMgr('content').getEntry('info_id'))
		{
			return true;
		}
		if(!request.options)
		{
			request.options = {};
		}
		// Exclude self from results - no app needed since it's just one app
		request.options.exclude = [widget.getRoot().getArrayMgr('content').getEntry('info_id')];

		return true;
	}

	/**
	 * View a list of timesheets for the linked infolog entry
	 *
	 * Only one infolog entry at a time is allowed, we just pick the first one
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _selected
	 */
	timesheet_list(_action, _selected)
	{
		var extras = {
			link_app: 'infolog',
			link_id: false
		};
		for(var i = 0; i < _selected.length; i++)
		{
			// Remove UID prefix for just contact_id
			var ids = _selected[i].id.split('::');
			ids.shift();
			ids = ids.join('::');

			extras.link_id = ids;
			break;
		}

		egw.open("","timesheet","list", extras, 'timesheet');
	}

	/**
	 * Go to parent entry
	 *
	 * @param {aciton object} _action
	 * @param {object} _selected
	 */
	has_parent(_action, _selected)
	{
		var data = egw.dataGetUIDdata(_selected[0].id);

		return data && data.data && data.data.info_id_parent > 0;
	}

	/**
	 * Submit template if widget has a value
	 *
	 * Used for project-selection to update pricelist items from server
	 *
	 * @param {DOMNode} _node
	 * @param {et2_widget} _widget
	 */
	submit_if_not_empty(_node, _widget)
	{
		if (_widget.get_value()) this.et2._inst.submit();
	}

	/**
	 * Toggle encryption
	 *
	 * @param {jQuery.Event} _event
	 * @param {et2_button} _widget
	 * @param {DOMNode} _node
	 */
	toggleEncrypt(_event, _widget, _node)
	{
		if (!this.egw.user('apps').stylite)
		{
			this.egw.message(this.egw.lang('InfoLog encryption requires EPL Subscription')+': <a href="http://www.egroupware.org/EPL">www.egroupware.org/EPL</a>');
			return;
		}
		this._get_stylite(function() {app.stylite.toggleEncrypt.call(app.stylite,_event,_widget,_node);});
	}

	/**
	 * Make sure stylite javascript is loaded, and call the given callback when it is
	 *
	 * @param {function} callback
	 * @param {object} attrs
	 *
	 */
	_get_stylite(callback : Function, attrs? : any[])
	{
		// use app object from etemplate2, which might be private and not just window.app
		var app = this.et2.getInstanceManager().app_obj;

		if (!app.stylite)
		{
			var self = this;
			egw_LAB.script('stylite/js/infolog-encryption.js?'+this.et2.getArrayMgr('content').data.encryption_ts).wait(function()
			{
				app.stylite = new app.classes.stylite;
				app.stylite.et2 = self.et2;
				if(callback)
				{
					callback.apply(app.stylite,attrs);
				}
			});
		}
		else
		{
			app.stylite.et2 = this.et2;
			callback.apply(app.stylite,attrs);
		}
	}

	/**
	 * OnChange callback for responsible
	 *
	 * @param {jQuery.Event} _event
	 * @param {et2_widget} _widget
	 */
	onchangeResponsible(_event, _widget)
	{
		if (app.stylite && app.stylite.onchangeResponsible)
		{
			app.stylite.onchangeResponsible.call(app.stylite, _event, _widget);
		}
	}

	/**
	 * Action handler for context menu change responsible action
	 *
	 * We populate the dialog with the current value.
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _selected
	 */
	change_responsible(_action, _selected)
	{
		var et2 = _selected[0].manager.data.nextmatch.getInstanceManager();
		var responsible = et2.widgetContainer.getWidgetById('responsible');
		if(responsible)
		{
			responsible.set_value([]);
			et2.widgetContainer.getWidgetById('responsible_action[title]').set_value('');
			et2.widgetContainer.getWidgetById('responsible_action[title]').set_class('');
			et2.widgetContainer.getWidgetById('responsible_action[ok]').set_disabled(_selected.length !== 1);
			et2.widgetContainer.getWidgetById('responsible_action[add]').set_disabled(_selected.length === 1)
			et2.widgetContainer.getWidgetById('responsible_action[delete]').set_disabled(_selected.length === 1)
		}

		if(_selected.length === 1)
		{
			var data = egw.dataGetUIDdata(_selected[0].id);

			if(responsible && data && data.data)
			{
				et2.widgetContainer.getWidgetById('responsible_action[title]').set_value(data.data.info_subject);
				et2.widgetContainer.getWidgetById('responsible_action[title]').set_class(data.data.sub_class)
				responsible.set_value(data.data.info_responsible);
			}
		}

		nm_open_popup(_action, _selected);
	}

	/**
	 * Handle encrypted info_desc for print purpose
	 * and triggers print action after decryption
	 *
	 * @param {Keyring} _keyring Mailvelope keyring to use
	 */
	printEncrypt(_keyring)
	{
		//this.mailvelopeAvailable(this.toggleEncrypt);
		var info_desc = this.et2.getWidgetById('info_des');

		var self = this;
		mailvelope.createDisplayContainer('#infolog-edit-print_info_des', info_desc.value, _keyring).then(function(_container)
		{
			var $info_des_dom = jQuery(self.et2.getWidgetById('info_des').getDOMNode());
//			$info_des_dom.children('iframe').height($info_des_dom.height());
			$info_des_dom.children('span').hide();
			//Trigger print action
			self.infolog_print_preview();
		},
		function(_err)
		{
			self.egw.message(_err, 'error');
		});
	}

}

app.classes.infolog = InfologApp;
