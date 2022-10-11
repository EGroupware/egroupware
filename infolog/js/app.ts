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

import {EgwApp} from '../../api/js/jsapi/egw_app';
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {et2_nextmatch} from "../../api/js/etemplate/et2_extension_nextmatch";
import {CRMView} from "../../addressbook/js/CRM";
import {et2_selectbox} from "../../api/js/etemplate/et2_widget_selectbox";
import {nm_open_popup} from "../../api/js/etemplate/et2_extension_nextmatch_actions.js";
import {egw} from "../../api/js/jsapi/egw_global";
import {et2_date} from "../../api/js/etemplate/et2_widget_date";

/**
 * UI for Infolog
 *
 * @augments AppJS
 */
class InfologApp extends EgwApp
{

	// These fields help with push filtering & access control to see if we care about a push message
	protected push_grant_fields = ["info_owner","info_responsible"];
	protected push_filter_fields = ["info_owner","info_responsible"]

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
				var nm = <et2_nextmatch>this.et2.getWidgetById('nm');
				var filter2 = <et2_selectbox> nm.getWidgetById('filter2');
				this.show_details(filter2.get_value() == 'all',nm.getDOMNode(nm));
				// Remove the rule added by show_details() if the template is removed
				jQuery(_et2.DOMContainer).on('clear', jQuery.proxy(function() {egw.css(this);}, '#' + nm.getDOMNode(nm).id + ' .et2_box.infoDes'));

				// Enable decrypt on hover
				if (this.egw.user('apps').stylite) {
					this.mailvelopeAvailable(function () {
						egw.applyFunc('app.stylite.decrypt_hover', [nm]);
					});
				}
				// blur count, if limit modified optimization used
				if (nm.getController()?.getTotalCount() === 9999)
				{
					this.blurCount(true);
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
					this.mailvelopeAvailable(() => {
						this.toggleEncrypt();

						// Decrypt history on hover
						var history = this.et2.getWidgetById('history');
						this.egw.applyFunc('app.stylite.decrypt_hover', [history, 'et2-description']);
					});
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
	 * Retrieve the current state of the application for future restoration
	 *
	 * Reimplemented to add action/action_id from content set by server
	 * when eg. viewing infologs linked to contacts.
	 *
	 * @return {object} Application specific map representing the current state
	 */
	getState()
	{
		let state = {
			action: null,
			action_id: null
		};
		let nm : any = {};

		// Get index etemplate
		var et2 = etemplate2.getById('infolog-index');
		if(et2)
		{
			state = et2.widgetContainer.getWidgetById("nm").getValue();
			let content = et2.widgetContainer.getArrayMgr('content');
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
		if (!this.et2) return;	// ignore calls before et2_ready
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
			egw.set_preference('infolog', nm.options.settings.columnselection_pref.replace('-details','')+'-details-pref', filter2.get_value());

			// Change preference location - widget is nextmatch
			nm.options.settings.columnselection_pref = nm.options.settings.columnselection_pref.replace('-details','') + (filter2.get_value() == 'all' ? '-details' :'');

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
        egw.css((dom_node && dom_node.id ? "#"+dom_node.id+' ' : '') + (egwIsMobile()? ".infoDescRow" : ".infoDes"),"display:" + (show ? "block;" : "none;"));
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
		let child_button = document.body.querySelector('#delete_sub') || document.body.querySelector('[id*="delete_sub"]');
		this._action_all = _action.parent.data.nextmatch?.getSelection().all;
		this._action_ids = [];
		if(child_button)
		{
			for(let i = 0; i < _senders.length; i++)
			{
				this._action_ids.push(_senders[i].id.split("::").pop());

				if(_senders[i].iface.getDOMNode().classList.contains("infolog_rowHasSubs"))
				{
					children = true;
					break;
				}
			}
			child_button.disabled = !children;
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
	* @param _event
	* @param {et2_widget} _widget
	*/
	statusChanged(_event, _widget)
	{
		// Make sure this doesn't get executed while template is loading
		if (!this.et2?.getInstanceManager()) return;

		const status = <et2_selectbox>this.et2.getWidgetById('info_status');
		const percent = <et2_selectbox>this.et2.getWidgetById('info_percent');
		const datecompleted = <et2_date>this.et2.getWidgetById('info_datecompleted');
		const old_status = status.get_value();
		const old_percent = parseInt(percent.get_value());
		let completed : boolean;

		switch(_widget.id)
		{
			case 'info_status':
				completed = old_status === 'done' || old_status === 'billed';
				if (completed || old_status === 'not-started' ||
					(old_status === 'ongoing') !== (0 < old_percent && old_percent < 100))
				{
					if (completed)
					{
						percent.set_value('100');
					}
					else if (old_status == 'not-started')
					{
						percent.set_value('0');
					}
					else if (!completed && !old_percent || old_percent === 100)
					{
						percent.set_value('10');
					}
				}
				break;

			case 'info_percent':
				completed = old_percent === 100;
				if (completed !== (old_status === 'done' || old_status === 'billed') ||
					(old_status === 'not-started') !== !old_percent)
				{
					status.set_value(!old_percent ? (old_status === 'not-started' ? 'not-started' : 'ongoing') :
						(old_percent === 100 ? 'done' : 'ongoing'));
				}
				break;

			case 'info_datecompleted':
				completed = !!datecompleted.get_value();
				if (completed !== (old_status === 'done' || old_status === 'billed'))
				{
					status.set_value(completed ? 'done' : 'not-started');
				}
				if (completed !== (old_percent === 100))
				{
					percent.set_value(completed ? '100' : '0');
				}
				break;
		}
		if (!completed && datecompleted && datecompleted.get_value())
		{
			datecompleted.set_value('');
		}
		else if (completed && datecompleted && !datecompleted.get_value())
		{
			const now = new Date();
			const localtime = new Date(now.valueOf()-now.getTimezoneOffset()*60000);
			datecompleted.set_value(localtime);
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
		return this.et2.getValueById("info_subject");
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
		if(!_node && _widget instanceof HTMLElement)
		{
			_node = _widget;
		}
		if(!this.egw.user('apps').stylite)
		{
			this.egw.message(this.egw.lang('InfoLog encryption requires EPL Subscription')+': <a href="https://www.egroupware.org/EPL">www.egroupware.org/EPL</a>');
			return;
		}
		this.egw.applyFunc('app.stylite.toggleEncrypt', [_event, _widget, _node]);
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

	/**
	 * Blur NM count (used for limit modified optimization not returning (an exact) count
	 *
	 * @param blur
	 */
	blurCount(blur : boolean)
	{
		document.querySelector('div#infolog-index_nm.et2_nextmatch .header_count')?.classList.toggle('blur_count', blur);
	}
}

app.classes.infolog = InfologApp;