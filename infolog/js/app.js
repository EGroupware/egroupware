/**
 * EGroupware - Infolog - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package infolog
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for Infolog
 *
 * @augments AppJS
 */
app.classes.infolog = AppJS.extend(
{
	appname: 'infolog',

	/**
	 * Constructor
	 *
	 * @memberOf app.infolog
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
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} _et2 newly ready object
	 * @param {string} _name template name
	 */
	et2_ready: function(_et2, _name)
	{
		// call parent
		this._super.apply(this, arguments);

		switch(_name)
		{
			case 'infolog.index':
				this.filter_change();
				// Show / hide descriptions according to details filter
				var nm = this.et2.getWidgetById('nm');
				var filter2 = nm.getWidgetById('filter2');
				this.show_details(filter2.value == 'all',nm.getDOMNode(nm));
				break;
			case 'infolog.edit.print':
				// Trigger print command if the infolog oppend for printing porpuse
				this.infolog_print_preview_onload();
		}
	},

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
	observer: function(_msg, _app, _id, _type, _msg_type, _links)
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
		//Refresh handler for infologs integrated in calendar
		if (_app == 'infolog' && _id && _type !='delete')
		{
			var info_type = egw.dataGetUIDdata(_app+"::"+_id)?egw.dataGetUIDdata(_app+"::"+_id).data.info_type:false;
			var cal_show = egw.preference('cal_show','infolog')||false;

			if (info_type && cal_show)
			{
				var rex = RegExp(info_type,'gi');
				if (cal_show.match(rex))
				{
					//Trigger refresh the whole calendar if the changed infolog entry is integrated one
					if (typeof app['calendar'] != 'undefined') app.calendar.egw.window.location.reload();
				}
			}
		}
	},

	/**
	 * Retrieve the current state of the application for future restoration
	 *
	 * Reimplemented to add action/action_id from content set by server
	 * when eg. viewing infologs linked to contacts.
	 *
	 * @return {object} Application specific map representing the current state
	 */
	getState: function()
	{
		// call parent
		var state = this._super.apply(this, arguments);

		var nm = this.et2 ? this.et2.getArrayMgr('content').data.nm : {};
		state.action = nm.action || null;
		state.action_id = nm.action_id || null;

		return state;
	},

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
	setState: function(state)
	{
		if (typeof state.state.action == 'undefined') state.state.action = null;
		if (typeof state.state.search == 'undefined') state.state.search = null;

		return this._super.apply(this, arguments);
	},

	/**
	 * Enable or disable the date filter
	 *
	 * If the filter is set to something that needs dates, we enable the
	 * header_left template.  Otherwise, it is disabled.
	 */
	filter_change: function()
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
	},

	/**
	 * show or hide the details of rows by selecting the filter2 option
	 * either 'all' for details or 'no_description' for no details
	 *
	 * @param {Event} event Change event
	 * @param {et2_nextmatch} nm The nextmatch widget that owns the filter
	 */
	filter2_change: function(event, nm)
	{
		var filter2 = nm.getWidgetById('filter2');

		if (nm && filter2)
		{
			// Show / hide descriptions
			this.show_details(filter2.value == 'all', nm.getDOMNode(nm));

			// Store selection as implicit preference
			egw.set_preference('infolog', nm.options.settings.columnselection_pref.replace('-details','')+'-details-pref', filter2.value);

			// Change preference location - widget is nextmatch
			nm.options.settings.columnselection_pref = nm.options.settings.columnselection_pref.replace('-details','') + (filter2.value == 'all' ? '-details' :'');

			// Load new preferences
			var colData = nm.columns.slice();
			for(var i = 0; i < nm.columns.length; i++) colData[i].disabled=false;
			nm._applyUserPreferences(nm.columns, colData);

			// Now apply them to columns
			for(var i = 0; i < colData.length; i++)
			{
				nm.dataview.getColumnMgr().columns[i].set_width(colData[i].width);
				nm.dataview.getColumnMgr().columns[i].set_visibility(!colData[i].disabled);
			}
			nm.dataview.getColumnMgr().updated = true;
			// Update page
			nm.dataview.updateColumns();
		}
	},

	/**
	 * Show or hide details by changing the CSS class
	 *
	 * @param {boolean} show
	 * @param {DOMNode} dom_node
	 */
	show_details: function(show, dom_node)
	{
		// Show / hide descriptions
        egw.css((dom_node && dom_node.id ? "#"+dom_node.id+' ' : '') + ".et2_box.infoDes","display:" + (show ? "block;" : "none;"));
	},

	confirm_delete_2: function (_action, _senders)
	{
		var children = false;
		var child_button = jQuery('#delete_sub').get(0) || jQuery('[id*="delete_sub"]').get(0);
		if(child_button)
		{
			for(var i = 0; i < _senders.length; i++)
			{
				if ($j(_senders[i].iface.node).hasClass('infolog_rowHasSubs'))
				{
					children = true;
					break;
				}
			}
			child_button.style.display = children ? 'block' : 'none';
		}
		var callbackDeleteDialog = function (button_id)
		{
			if (button_id == et2_dialog.YES_BUTTON )
			{

			}
		};
		var confirmDeleteDialog = et2_dialog.show_dialog(callbackDeleteDialog, this.egw.lang("Do you really want to DELETE this Rule"),this.egw.lang("Delete"), {},et2_dialog.BUTTONS_YES_NO_CANCEL, et2_dialog.WARNING_MESSAGE);
	},

	/**
	 * Confirm delete
	 * If entry has children, asks if you want to delete children too
	 *
	 *@param _action
	 *@param _senders
	 */
	confirm_delete: function(_action, _senders)
	{
		var children = false;
		var child_button = jQuery('#delete_sub').get(0) || jQuery('[id*="delete_sub"]').get(0);
		if(child_button)
		{
			for(var i = 0; i < _senders.length; i++)
			{
				if ($j(_senders[i].iface.getDOMNode()).hasClass('infolog_rowHasSubs'))
				{
					children = true;
					break;
				}
			}
			child_button.style.display = children ? 'block' : 'none';
		}
		nm_open_popup(_action, _senders);
	},

	/**
	 * Add email from addressbook
	 *
	 * @param ab_id
	 * @param info_cc
	 */
	add_email_from_ab: function(ab_id,info_cc)
	{
		var ab = document.getElementById(ab_id);

		if (!ab || !ab.value)
		{
			jQuery("tr.hiddenRow").css("display", "table-row");
		}
		else
		{
			var cc = document.getElementById(info_cc);

			for(var i=0; i < ab.options.length && ab.options[i].value != ab.value; ++i) ;

			if (i < ab.options.length)
			{
				cc.value += (cc.value?', ':'')+ab.options[i].text.replace(/^.* <(.*)>$/,'$1');
				ab.value = '';
				ab.onchange();
				jQuery("tr.hiddenRow").css("display", "none");
			}
		}
		return false;
	},

	/**
	* If one of info_status, info_percent or info_datecompleted changed --> set others to reasonable values
	*
	* @param {string} changed_id id of changed element
	* @param {string} status_id
	* @param {string} percent_id
	* @param {string} datecompleted_id
	*/
	status_changed: function(changed_id, status_id, percent_id, datecompleted_id)
	{
		// Make sure this doesn't get executed while template is loading
		if(this.et2 == null || this.et2.getInstanceManager() == null) return;

		var status = document.getElementById(status_id);
		var percent = document.getElementById(percent_id);
		var datecompleted = document.getElementById(datecompleted_id+'[str]');
		if(!datecompleted)
		{
			datecompleted = jQuery('#'+datecompleted_id +' input').get(0);
		}
		var completed;

		switch(changed_id)
		{
			case status_id:
				completed = status.value == 'done' || status.value == 'billed';
				if (completed || status.value == 'not-started' ||
					(status.value == 'ongoing') != (percent.value > 0 && percent.value < 100))
				{
					percent.value = completed ? 100 : (status.value == 'not-started' ? 0 : 10);
				}
				break;

			case percent_id:
				completed = percent.value == 100;
				if (completed != (status.value == 'done' || status.value == 'billed') ||
					(status.value == 'not-started') != (percent.value == 0))
				{
					status.value = percent.value == 0 ? 'not-started' : (percent.value == 100 ? 'done' : 'ongoing');
				}
				break;

			case datecompleted_id+'[str]':
			case datecompleted_id:
				completed = datecompleted.value != '';
				if (completed != (status.value == 'done' || status.value == 'billed'))
				{
					status.value = completed ? 'done' : 'not-started';
				}
				if (completed != (percent.value == 100))
				{
					percent.value = completed ? 100 : 0;
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
	},

	/**
	 * handle "print" action from "Actions" selectbox in edit infolog window.
	 * check if the template is dirty then submit the template otherwise just open new window as print.
	 *
	 */
	edit_actions: function()
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
					egw_open(id,'infolog','edit',{print:1});
					break;
				default:
					template.submit();
			}
		}
	},

	/**
	 * Open infolog entry for printing
	 *
	 * @param {aciton object} _action
	 * @param {object} _selected
	 */
	infolog_menu_print: function(_action, _selected)
	{
		var id = _selected[0].id.replace(/^infolog::/g,'');
		egw_open(id,'infolog','edit',{print:1});
	},

	/**
	 * Trigger print() onload window
	 */
	infolog_print_preview_onload: function ()
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
	},

	/**
	 * Trigger print() function to print the current window
	 */
	infolog_print_preview: function()
	{
		this.egw.message('Printing...');
		this.egw.window.print();
	},

	/**
	 *
	 */
	add_link_sidemenu: function()
	{
		egw.open('','infolog','add');
	},

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
	add_with_extras: function(widget,_type, _action, _action_id)
	{
		// We use widget.getRoot() instead of this.et2 for the case when the
		// addressbook tab is viewing a contact + infolog list, there's 2 infolog
		// etemplates
		var nm = widget.getRoot().getWidgetById('nm');
		var nm_value = nm.getValue() || {};

		// It's important that all these keys are here, they override the link
		// registry.
		var extras = {
			type: _type || nm_value.filter || "",
			cat_id: nm_value.cat_id || "",
			action: _action || "",
			action_id: _action_id != '0' ? _action_id : "" || ""
		};
		egw.open('','infolog','add',extras);
	}
});
