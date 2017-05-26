/**
 * EGroupware - Addressbook - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package addressbook
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for Addressbook
 *
 * @augments AppJS
 */
app.classes.addressbook = AppJS.extend(
{
	appname: 'addressbook',

	/**
	 * Constructor
	 *
	 * @memberOf app.addressbook
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
	 * @param {etemplate2} et2 newly ready object
	 * @param {string} name
	 */
	et2_ready: function(et2, name)
	{
		// r49769 let's CRM view run under currentapp == "addressbook", which causes
		// app.addressbook.et2_ready called before app.infolog.et2_ready and therefore
		// app.addressbook.et2 would point to infolog template, if we not stop here
		if (name.match(/^infolog|tracker\./)) return;

		// call parent
		this._super.apply(this, arguments);

		switch (name)
		{
			case 'addressbook.edit':
				var content = this.et2.getArrayMgr('content').data;
				if (typeof content.showsearchbuttons == 'undefined' || !content.showsearchbuttons)
				{
					this.show_custom_country(jQuery('select[id*="adr_one_countrycode"]').get(0));
					this.show_custom_country(jQuery('select[id*="adr_two_countrycode"]').get(0));

					// Instanciate infolog JS too - wrong app, so it won't be done automatically
					if(typeof window.app.infolog != 'object' && typeof window.app.classes['infolog'] == 'function')
					{
						window.app.infolog = new window.app.classes.infolog();
					}
				}
				// Call check value if the AB got opened with presets
				if (window.location.href.match(/&presets\[email\]/g) && content.presets_fields)
				{
					for(var i=0;i< content.presets_fields.length;i++)
					{
						this.check_value(this.et2.getWidgetById(content.presets_fields),0);
					}
				}
				break;
		}

		jQuery('select[id*="adr_one_countrycode"]').each(function() {
			app.addressbook.show_custom_country(this);
		});
		jQuery('select[id*="adr_two_countrycode"]').each(function() {
			app.addressbook.show_custom_country(this);
		});
	},

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * App is responsible for only reacting to "messages" it is interested in!
	 *
	 * Addressbook checks for CRM view to update the displayed data if you edit
	 * that contact
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
	 * @return {false|*} false to stop regular refresh, thought all observers are run
	 */
	observer: function(_msg, _app, _id, _type, _msg_type, _links)
	{
		// Edit to the current entry
		var state = this.getState();
		if(_app === 'addressbook' && state && state.type && state.type === 'view' && state.id === _id)
		{
			var content = egw.dataGetUIDdata('addressbook::'+_id);
			if (content.data)
			{
				var view = etemplate2.getById('addressbook-view');
				if(view)
				{
					view.widgetContainer._children[0].set_value({content:content.data});
				}
			}
			return false;
		}
		else if(_app === 'calendar')
		{
			// Event changed, update any [known] contacts participating
			var content = egw.dataGetUIDdata(_app+'::'+_id);
			if (content && content.data && content.data.participant_types && content.data.participant_types.c)
			{
				for(var contact in content.data.participant_types.c)
				{
					// Refresh handles checking to see if the contact is known,
					// and updating it directly
					egw.dataRefreshUID('addressbook::'+contact);
				}
				return true;
			}
			else if (!content)
			{
				// No data on the event, we'll have to reload if calendar column is visible
				// to get the updated information
				var nm = etemplate2.getById('addressbook-index').widgetContainer.getWidgetById('nm');
				var pref = nm ? nm._getPreferences() : false;
				if(pref && pref.visible.indexOf('calendar_calendar') > -1)
				{
					nm.refresh(null,'update');
				}
			}
		}

		return true;
	},

	/**
	 * Open CRM view
	 *
	 * @param _action
	 * @param _senders
	 */
	view: function(_action, _senders)
	{
		var index = _senders[0]._index;
		var id = _senders[0].id.split('::').pop();
		var extras = {
			index: index
		};

		// CRM list
		if(_action.id != 'view')
		{
			extras.crm_list = _action.id.replace('view-','');
		}

		this.egw.open(id, 'addressbook', 'view', extras, '_self', 'addressbook');
	},

	/**
	 * Set link filter for the already open & rendered  list
	 *
	 * @param {Object} filter Object with key / value pairs of filters to set
	 */
	view_set_list: function(filter)
	{
		// Find the infolog list
		var list = etemplate2.getById(
			jQuery(this.et2.getInstanceManager().DOMContainer).nextAll('.et2_container').attr('id')
		);
		var nm = list ? list.widgetContainer.getWidgetById('nm') : null;
		if(nm)
		{
			nm.applyFilters(filter);
		}
	},

	/**
	 * Run an action from CRM view toolbar
	 *
	 * @param {object} _action
	 */
	view_actions: function(_action)
	{
		var id = this.et2.getArrayMgr('content').data.id;

		switch(_action.id)
		{
			case 'open':
				this.egw.open(id, 'addressbook', 'edit');
				break;
			case 'copy':
				this.egw.open(id, 'addressbook', 'edit', { makecp: 1});
				break;
			case 'cancel':
				this.egw.open(null, 'addressbook', 'list', null, '_self', 'addressbook');
				break;
			default:	// submit all other buttons back to server
				this.et2._inst.submit();
				break;
		}
	},

	/**
	 * Open the calender to view the selected contacts
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _senders
	 */
	view_calendar: function(_action, _senders)
	{
		var extras = {
			filter: 'all',
			cat_id: '',
			owner: []
		};
		var orgs = [];
		for(var i = 0; i < _senders.length; i++)
		{
			// Remove UID prefix for just contact_id
			var ids = _senders[i].id.split('::');
			ids.shift();
			ids = ids.join('::');

			// Orgs need to get all the contact IDs first
			if (ids.substr(0,9) == 'org_name:')
			{
				orgs.push(ids);
			}
			else
			{
				// Check to see if this is a user account, we prefer to use
				// account ID in calendar
				var data = this.egw.dataGetUIDdata(_senders[i].id);
				if(data && data.data && data.data.account_id)
				{
					extras.owner.push(data.data.account_id);
				}
				else
				{
					extras.owner.push('c'+ids);
				}
			}
		}

		if(orgs.length > 0)
		{
			// Get organisation contacts, then show infolog list
			this.egw.json('addressbook.addressbook_ui.ajax_organisation_contacts',
				[orgs],
				function(contacts) {
					for(var i = 0; i < contacts.length; i++)
					{
						extras.owner.push('c'+contacts[i]);
					}
					extras.owner = extras.owner.join(',');
					this.egw.open('','calendar','list',extras,'calendar');
				},this,true,this
			).sendRequest();
		}
		else
		{
			extras.owner = extras.owner.join(',');
			egw.open('', 'calendar', 'list', extras, 'calendar');
		}
	},
	/**
	 * Add appointment or show calendar for selected contacts, call default nm_action after some checks
	 *
	 * @param _action
	 * @param _senders
	 */
	add_cal: function(_action, _senders)
	{
		if (!_senders[0].id.match(/^(?:addressbook::)?[0-9]+$/))
		{
			// send org-view requests to server
			_action.data.nm_action = "submit";
			nm_action(_action, _senders);
		}
		else
		{
			var ids = egw.user('account_id')+',';
			for (var i = 0; i < _senders.length; i++)
			{
				// Remove UID prefix for just contact_id
				var id = _senders[i].id.split('::');
				ids += "c" + id[1] + ((i < _senders.length - 1) ? "," : "");
			}
			var extra = {};
			extra[_action.data && _action.data.url && _action.data.url.indexOf('owner') > 0 ? 'owner' : 'participants'] = ids;

			// Use framework to add calendar entry
			egw.open('','calendar','add',extra);
		}
	},

	/**
	 * View infolog entries linked to selected contact
	 * @param {egwAction} _action Select action
	 * @param {egwActionObject[]} _senders Selected contact(s)
	 */
	view_infolog: function(_action, _senders)
	{
		var extras = {
			action: 'addressbook',
			action_id: [],
			action_title: _senders.length > 1 ? this.egw.lang('selected contacts') : ''
		};
		var orgs = [];
		for(var i = 0; i < _senders.length; i++)
		{
			// Remove UID prefix for just contact_id
			var ids = _senders[i].id.split('::');
			ids.shift();
			ids = ids.join('::');

			// Orgs need to get all the contact IDs first
			if (ids.substr(0,9) == 'org_name:')
			{
				orgs.push(ids);
			}
			else
			{
				extras.action_id.push(ids);
			}
		}

		if(orgs.length > 0)
		{
			// Get organisation contacts, then show infolog list
			this.egw.json('addressbook.addressbook_ui.ajax_organisation_contacts',
				[orgs],
				function(contacts) {
					extras.action_id = extras.action_id.concat(contacts);
					this.egw.open('','infolog','list',extras,'infolog');
				},this,true,this
			).sendRequest();
		}
		else
		{
			egw.open('', 'infolog', 'list', extras, 'infolog');
		}
	},

	/**
	 * Add task for selected contacts, call default nm_action after some checks
	 *
	 * @param _action
	 * @param _senders
	 */
	add_task: function(_action, _senders)
	{
		if (!_senders[0].id.match(/^(addressbook::)?[0-9]+$/))
		{
			// send org-view requests to server
			_action.data.nm_action = "submit";
		}
		else
		{
			// call nm_action's popup
			_action.data.nm_action = "popup";
		}
		nm_action(_action, _senders);
	},

	/**
	 * [More...] in phones clicked: copy allways shown phone numbers to phone popup
	 *
	 * @param {jQuery.event} _event
	 * @param {et2_widget} _widget
	 */
	showphones: function(_event, _widget)
	{
		this._copyvalues({
			tel_home: 'tel_home2',
			tel_work: 'tel_work2',
			tel_cell: 'tel_cell2',
			tel_fax:  'tel_fax2'
		});
		jQuery('table.editphones').css('display','inline');

		_event.stopPropagation();
		return false;
	},

	/**
	 * [OK] in phone popup clicked: copy phone numbers back to always shown ones
	 *
	 * @param {jQuery.event} _event
	 * @param {et2_widget} _widget
	 */
	hidephones: function(_event, _widget)
	{
		this._copyvalues({
			tel_home2: 'tel_home',
			tel_work2: 'tel_work',
			tel_cell2: 'tel_cell',
			tel_fax2:  'tel_fax'
		});
		jQuery('table.editphones').css('display','none');

		_event.stopPropagation();
		return false;
	},

	/**
	 * Copy content of multiple fields
	 *
	 * @param {object} what object with src: dst pairs
	 */
	_copyvalues: function(what)
	{
		for(var name in what)
		{
			var src = this.et2.getWidgetById(name);
			var dst = this.et2.getWidgetById(what[name]);
			if (src && dst) dst.set_value(src.get_value ? src.get_value() : src.value);
		}
		// change tel_prefer according to what
		var tel_prefer = this.et2.getWidgetById('tel_prefer');
		if (tel_prefer)
		{
			var val = tel_prefer.get_value ? tel_prefer.get_value() : tel_prefer.value;
			if (typeof what[val] != 'undefined') tel_prefer.set_value(what[val]);
		}
	},

	/**
	 * Callback function to create confirm dialog for duplicates contacts
	 *
	 * @param {object} _data includes duplicates contacts information
	 *
	 */
	_confirmdialog_callback: function(_data)
	{
		var confirmdialog = function(_title, _value, _buttons, _egw_or_appname)
		{
			return et2_createWidget("dialog",
			{
				callback: function(_buttons, _value)
				{
					if (_buttons == et2_dialog.OK_BUTTON)
					{
						var id = '';
						var content = this.template.widgetContainer.getArrayMgr('content').data;
						for (var row in _value.grid)
						{
							if (_value.grid[row].confirm == "true" && typeof content.grid !='undefined')
							{
								id = this.options.value.content.grid[row].confirm;
								egw.open(id, 'addressbook');

							}
						}
					}
				},
				title: _title||egw.lang('Input required'),
				buttons: _buttons||et2_dialog.BUTTONS_OK_CANCEL,
				value: {
					content: {
						grid: _value
					}
				},
				template: egw.webserverUrl+'/addressbook/templates/default/dupconfirmdialog.xet'
			}, et2_dialog._create_parent(_egw_or_appname));
		};

		if (_data.msg && _data.doublicates)
		{
			var content = [];

			for(var id in _data.doublicates)
			{
				content.push({"confirm":id,"name":_data.doublicates[id]});
			}
			confirmdialog(this.egw.lang('Duplicate warning'),content,et2_dialog.BUTTONs_OK_CANCEL);
		}
		if (typeof _data.fileas_options == 'object' && this.et2)
		{
			var selbox = this.et2.getWidgetById('fileas_type');
			if (selbox)
			{
				selbox.set_select_options(_data.fileas_sel_options);
			}
		}
	},

	/**
	 * Callback if certain fields get changed
	 *
	 * @param {widget} widget widget
	 * @param {string} own_id Current AB id
	 */
	check_value: function(widget, own_id)
	{
		// if we edit an account, call account_change to let it do it's stuff too
		if (this.et2.getWidgetById('account_lid'))
		{
			this.account_change(null, widget);
		}

		var values = this.et2._inst.getValues(this.et2);

		if (widget.id.match(/n_/))
		{
			var value = '';
			if (values.n_prefix) value += values.n_prefix+" ";
			if (values.n_given)  value += values.n_given+" ";
			if (values.n_middle) value += values.n_middle+" ";
			if (values.n_family) value += values.n_family+" ";
			if (values.n_suffix) value += values.n_suffix;

			var name = this.et2.getWidgetById("n_fn");
			if (typeof name != 'undefined')	name.set_value(value);
		}
		egw.json('addressbook.addressbook_ui.ajax_check_values', [values, widget.id, own_id],this._confirmdialog_callback,this,true,this).sendRequest();
	},

	show_custom_country: function(selectbox)
	{
		if(!selectbox) return;
		var custom_field_name = selectbox.id.replace("countrycode", "countryname");
		var custom_field = document.getElementById(custom_field_name);
		if(custom_field && selectbox.value == "-custom-") {
			custom_field.style.display = "inline";
		}
		else if (custom_field)
		{
			if((selectbox.value == "" || selectbox.value == null) && custom_field.value != "")
			{
				selectbox.value = "-custom-";
				// Chosen needs this to update
				jQuery(selectbox).trigger("liszt:updated");

				custom_field.style.display = "inline";
			}
			else
			{
				custom_field.style.display = "none";
			}
		}
	},

	/**
	 * Add a new mailing list.  If any contacts are selected, they will be added.
	 *
	 * @param {egwAction} owner
	 * @param {egwActionObject[]} selected
	 */
	add_new_list: function(owner, selected)
	{
		if(!owner || typeof owner == 'object')
		{
			var filter = this.et2.getWidgetById('filter');
			owner = filter.getValue()||egw.preference('add_default','addressbook');
		}
		var contacts = [];
		if(selected && selected[0] && selected[0].getAllSelected())
		{
			// Action says all contacts selected, better ask the server for _all_ the IDs
			var fetching = fetchAll(selected, this.et2.getWidgetById('nm'), jQuery.proxy(
				function(contacts) {
					this._add_new_list_prompt(owner, contacts);
				}, this));
			if (fetching) return;
		}
		if(selected && selected.length)
		{
			for(var i = 0; i < selected.length; i++)
			{
				// Remove UID prefix for just contact_id
				var ids = selected[i].id.split('::');
				ids.shift();
				ids = ids.join('::');
				contacts.push(ids);
			}
		}
		this._add_new_list_prompt(owner, contacts);
	},

	/**
	 * Ask the user for a name, then create a new list with the provided contacts
	 * in it.
	 *
	 * @param {int} owner
	 * @param {String[]} contacts
	 */
	_add_new_list_prompt: function(owner, contacts)
	{
		var lists = this.et2.getWidgetById('filter2');
		et2_dialog.show_prompt(
			function(button, name) {
				if(button == et2_dialog.OK_BUTTON)
				{
					egw.json('addressbook.addressbook_ui.ajax_set_list',[0, name, owner, contacts],
						function(result)
						{
							if(typeof result == 'object') return; // This response not for us
							// Update list
							if(result)
							{
								lists.options.select_options.unshift({value:result,label:name});
								lists.set_select_options(lists.options.select_options);

								// Set to new list so they can see it easily
								lists.set_value(result);
								// Call cahnge event manually after setting the value
								// Not sure why our selectbox does not trigger change event
								jQuery(lists.node).change();
							}
						}
					).sendRequest(true);
				}
			},
			this.egw.lang('Name for the distribution list'),
			this.egw.lang('Add a new list')
		);
	},

	/**
	 * Rename the current distribution list selected in the nextmatch filter2
	 *
	 * Differences from add_new_list are in the dialog, parameters sent, and how the
	 * response is dealt with
	 *
	 * @param {egwAction} action Action selected in context menu (rename)
	 * @param {egwActionObject[]} selected The selected row(s).  Not used for this.
	 */
	rename_list: function(action, selected)
	{
		var lists = this.et2.getWidgetById('filter2');
		var list = lists.getValue() || 0;
		var value = null;
		for(var i = 0; i < lists.options.select_options.length; i++)
		{
			if(lists.options.select_options[i].value == list)
			{
				value = lists.options.select_options[i];
			}
		}
		et2_dialog.show_prompt(
			function(button, name) {
				if(button == et2_dialog.OK_BUTTON)
				{
					egw.json('addressbook.addressbook_ui.ajax_set_list',[list, name],
						function(result)
						{
							if(typeof result == 'object') return; // This response not for us
							// Update list
							if(result)
							{
								value.label = name;
								lists.set_select_options(lists.options.select_options);
							}
						}
					).sendRequest(true);
				}
			},
			this.egw.lang('Name for the distribution list'),
			this.egw.lang('Rename list'),
			value.label
		);
	},

	/**
	 * OnChange for distribution list selectbox
	 */
	filter2_onchange: function()
	{
		var filter = this.et2.getWidgetById('filter');
		var filter2 = this.et2.getWidgetById('filter2');
		var widget = this.et2.getWidgetById('nm');
		var filter2_val = filter2.get_value();

		if(filter2_val == 'add')
		{
			this.add_new_list(typeof widget == 'undefined' ? this.et2.getWidgetById('filter').value : widget.header.filter.get_value());
			this.value='';
		}
		// automatic switch to accounts addressbook or all addressbooks depending on distribution list is a group
		else if (filter2_val && (filter2_val < 0) !== (filter.get_value() === '0'))
		{
			// Change filter & filter2 at the same time
			widget.applyFilters({
				filter: filter2_val < 0 ? '0' : '',
				filter2: filter2_val
			});
			// Don't get rows here, let applyFilters() do it
			return false;
		}
		
		return true;
	},

	/**
	 * Method to enable actions by comparing a field with given value
	 */
	nm_compare_field: function()
	{
		var field = this.et2.getWidgetById('filter2');
		if (field) var val = field.get_value();
		if (val)
		{
			return nm_compare_field;
		}
		else
		{
			return false;
		}
	},

	/**
	 * Apply advanced search filters to index nextmatch
	 *
	 * @param {object} filters
	 */
	adv_search: function(filters)
	{
		var index = window.opener.etemplate2.getById('addressbook-index');
		if(!index)
		{
			alert('Could not find index');
			egw(window).close();
			return false;
		}
		var nm = index.widgetContainer.getWidgetById('nm');
		if(!index)
		{
			window.opener.egw.message('Could not find list', 'error');
			egw(window).close();
			return false;
		}
		// Reset filters first
		nm.activeFilters = {};
		nm.applyFilters(filters);
		return false;
	},

	/**
	 * Mail vCard
	 *
	 * @param {object} _action
	 * @param {array} _elems
	 */
	adb_mail_vcard: function(_action, _elems)
	{
		var app_registry = egw.link_get_registry('mail');
		var link = egw().link("/index.php","menuaction="+app_registry['add']['menuaction']);
		for (var i = 0; i < _elems.length; i++)
		{
			var idToUse = _elems[i].id;
			var idToUseArray = idToUse.split('::');
			idToUse = idToUseArray[1];
			link += "&preset[type][]="+encodeURIComponent("text/vcard; charset="+(egw.preference('vcard_charset', 'addressbook') || 'utf-8'));
			link += "&preset[file][]="+encodeURIComponent("vfs://default/apps/addressbook/"+idToUse+"/.entry");
		}
		if (typeof app_registry['view'] != 'undefined' && typeof app_registry['view_popup'] != 'undefined' )
		{
			var w_h =app_registry['view_popup'].split('x');
			if (w_h[1] == 'egw_getWindowOuterHeight()') w_h[1] = (screen.availHeight>egw_getWindowOuterHeight()?screen.availHeight:egw_getWindowOuterHeight());
			egw_openWindowCentered2(link, '_blank', w_h[0], w_h[1], 'yes');
		}

	},

	/**
	 * Action function to set business or private mail checkboxes to user preferences
	 *
	 * @param {egwAction} action Action user selected.
	 */
	mailCheckbox: function(action)
	{
		var preferences = {
			business: action.getManager().getActionById('email_business').checked ? true : false,
			private: action.getManager().getActionById('email_home').checked ? true : false
		};
		this.egw.set_preference('addressbook','preferredMail', preferences);
	},

	/**
	 * Action function to add the email address (business or home) of the selected
	 * contacts to a compose email popup window.
	 *
	 * Uses the egw API to handle the opening of the popup.
	 *
	 * @param {egwAction} action Action user selected.  Should have ID of either
	 *  'email_business' or 'email_home', from server side definition of actions.
	 * @param {egwActionObject[]} selected Selected rows
	 */
	addEmail: function(action, selected)
	{
		// Check for all selected.
		var nm = this.et2.getWidgetById('nm');
		if(fetchAll(selected, nm, jQuery.proxy(function(ids) {
			// fetchAll() returns just the ID, no prefix, so map it to match normal selected
			this.addEmail(action, ids.map(function(num) {return {id:'addressbook::'+num};}));
		}, this)))
		{
			// Need more IDs, will use the above callback when they're ready.
			return;
		}

		// Go through selected & pull email addresses from data
		var emails = [];
		for(var i = 0; i < selected.length; i++)
		{
			// Pull data from global cache
			var data = egw.dataGetUIDdata(selected[i].id) || {data:{}};

			var email_business = data.data[action.getManager().getActionById('email_business').checked ? 'email' : ''];
			var email = data.data[action.getManager().getActionById('email_home').checked ? 'email_home' : ''];
			// prefix email with full name
			var personal = data.data.n_fn || '';
			if (personal.match(/[^a-z0-9. -]/i)) personal = '"'+personal.replace(/"/, '\\"')+'"';

			if(email_business)
			{
				emails.push((personal?personal+' <':'')+email_business+(personal?'>':''));
			}
			if(email)
			{
				emails.push((personal?personal+' <':'')+email+(personal?'>':''));
			}
		}
		switch (action.id)
		{
			case "add_to_to":
				egw.open_link('mailto:' + emails.join(','));
				break;
			case "add_to_cc":
				egw.open_link('mailto:' + '?cc='  + emails.join(','));
				//egw.mailto('mailto:');
				break;
			case "add_to_bcc":
				egw.open_link('mailto:' + '?bcc=' + emails.join(','));
				break;
		}

		return false;
	},

	/**
	 * Merge the selected contacts into the target document.
	 *
	 * Normally we let the framework handle this, but in addressbook we want to
	 * interfere and customize things a little to ask about saving to infolog.
	 *
	 * @param {egwAction} action - The document they clicked
	 * @param {egwActionObject[]} selected - Rows selected
	 */
	merge_mail: function merge_mail(action, selected, target)
	{
		// Special processing for email documents - ask about infolog
		if(action && action.data && selected.length > 1)
		{
			var callback = function(button, value) {
				if(button == et2_dialog.OK_BUTTON)
				{
					var _action = jQuery.extend(true, {}, action);
					if(value.infolog)
					{
						_action.data.menuaction += '&to_app=infolog&info_type='+value.info_type;
					}
					nm_action(_action, selected, target);
				}
			};
			et2_createWidget("dialog",{
				callback: callback,
				title: action.caption,
				buttons: et2_dialog.BUTTONS_OK_CANCEL,
				type: et2_dialog.QUESTION_MESSAGE,
				template: egw.webserverUrl+'/addressbook/templates/default/mail_merge_dialog.xet',
				value: {content: {info_type: 'email'}, sel_options: this.et2.getArrayMgr('sel_options').data}
			});
		}
		else
		{
			// Normal processing for only one contact selected
			return nm_action(action, selected, target);
		}
	},

	/**
	 * Retrieve the current state of the application for future restoration
	 *
	 * Overridden from parent to handle viewing a contact.  In this case state
	 * will be {contact_id: #}
	 *
	 * @return {object} Application specific map representing the current state
	 */
	getState: function()
	{
		// Most likely we're in the list view
		var state = this._super.apply(this, arguments);

		if(jQuery.isEmptyObject(state))
		{
			// Not in a list view.  Try to find contact ID
			var etemplates = etemplate2.getByApplication('addressbook');
			for(var i = 0; i < etemplates.length; i++)
			{
				var content = etemplates[i].widgetContainer.getArrayMgr("content");
				if(content && content.getEntry('id'))
				{
					state = {app: 'addressbook', id: content.getEntry('id'), type: 'view'};
					break;
				}
			}
		}

		return state;
	},

	/**
	 * Set the application's state to the given state.
	 *
	 * Overridden from parent to stop the contact view's infolog nextmatch from
	 * being changed.
	 *
	 * @param {{name: string, state: object}|string} state Object (or JSON string) for a state.
	 *	Only state is required, and its contents are application specific.
	 *
	 * @return {boolean} false - Returns false to stop event propagation
	 */
	setState: function(state)
	{
		var current_state = this.getState();

		// State should be an object, not a string, but we'll parse
		if(typeof state == "string")
		{
			if(state.indexOf('{') != -1 || state =='null')
			{
				state = JSON.parse(state);
			}
		}


		// Redirect from view to list - parent would do this, but infolog nextmatch stops it
		if(current_state.app && current_state.id && (typeof state.state == 'undefined' || typeof state.state.app == 'undefined'))
		{
			// Redirect to list
			// 'blank' is the special name for no filters, send that instead of the nice translated name
			var safe_name = jQuery.isEmptyObject(state) || jQuery.isEmptyObject(state.state||state.filter) ? 'blank' : state.name.replace(/[^A-Za-z0-9-_]/g, '_');
			egw.open('',this.appname,'list',{'favorite': safe_name},this.appname);
			return false;
		}
		else if (jQuery.isEmptyObject(state))
		{
			// Regular handling first to clear everything but advanced search
			this._super.apply(this, arguments);

			// Clear advanced search, which is in session and etemplate
			egw.json('addressbook.addressbook_ui.ajax_clear_advanced_search',[], function() {
				framework.setWebsiteTitle('addressbook','');
				var index = etemplate2.getById('addressbook-index');
				if(index && index.widgetContainer)
				{
					var nm = index.widgetContainer.getWidgetById('nm');
					if(nm)
					{
						nm.applyFilters({
							advanced_search: false
						});
					}
				}
			},this).sendRequest(true);
			return false;
		}
		else if (state.state.grouped_view)
		{
			// Deal with grouped views that are not valid (not in list of options)
			// by faking viewing that organisation
			var index = etemplate2.getById('addressbook-index');
			if(index && index.widgetContainer)
			{
				var grouped = index.widgetContainer.getWidgetById('grouped_view');
				var options = false;
				if(grouped && grouped.options && grouped.options.select_options)
				{
					options = grouped.options.select_options;
				}

				// Check to see if it's not there
				if(options && (options.find && 
					!grouped.options.select_options.find(function(e) {console.log(e); return e.value === state.state.grouped_view;}) ||
					!options[state.state.grouped_view]
				))
				{
					var nm = index.widgetContainer.getWidgetById('nm');
					var action = nm.controller._actionManager.getActionById('view_org');
					var senders = [{_context: {_widget: nm}}];
					return nm_action(action, senders, {}, {ids:[state.state.grouped_view]});
				}
			}
		}
		return this._super.apply(this, arguments);
	},

	/**
	 * Field changed, call server validation
	 *
	 * @param {jQuery.Event} _ev
	 * @param {et2_button} _widget
	 */
	account_change: function(_ev, _widget)
	{
		switch(_widget.id)
		{
			case 'account_lid':
			case 'n_family':
			case 'n_given':
			case 'account_passwd':
			case 'account_passwd_2':
				var values = this.et2._inst.getValues(this.et2);
				var data = {
					account_id: this.et2.getArrayMgr('content').data.account_id,
					account_lid: values.account_lid,
					account_firstname: values.n_given,
					account_lastname: values.n_family,
					account_email: values.email,
					account_passwd: values.account_passwd,
					account_passwd_2: values.account_passwd_2
				};

				this.egw.message('');
				this.egw.json('admin_account::ajax_check', [data, _widget.id], function(_msg)
				{
					if (_msg && typeof _msg == 'string')
					{
						egw(window).message(_msg, 'error');	// context get's lost :(
						_widget.getDOMNode().focus();
					}
				}, this).sendRequest();
				break;
		}
	},

	/**
	 * Get title in order to set it as document title
	 * @returns {string}
	 */
	getWindowTitle: function()
	{
		var widget = this.et2.getWidgetById('n_fn');
		if(widget) return widget.options.value;
	},

	/**
	 * Enable/Disable geolocation action items in contextmenu base on address availabilty
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _selected selected rows
	 * @returns {boolean} return false if no address found
	 */
	geoLocation_enabled: function(_action, _selected)
	{
		// multiple selection is not supported
		if (_selected.length>1) return false;

		var url = this.getGeolocationConfig();

		// exit if no url or invalide url given
		if (!url || typeof url === 'undefined' || typeof url !== 'string')
		{
			egw.debug('warn','no url or invalid url given as geoLocationUrl');
			return false;
		}
		var content = egw.dataGetUIDdata(_selected[0].id);

		// Selected, but data not found
		if(!content || typeof content.data === 'undefined') return false;

		var type = _action.id === 'business'?'one':'two';
		var addrs = [
			content.data['adr_'+type+'_street'],
			content.data['adr_'+type+'_locality'],
			content.data['adr_'+type+'_postalcode']
		];

		var fields = '';
		// Replcae placeholders with acctual values
		for (var i=0;i < addrs.length; i++)
		{
			fields += addrs[i] ? addrs[i] : '';
		}
		return (url !== '' && fields !== '')?true:false;
	},

	/**
	 * Generate a geo location URL based on geolocation_url in
	 * site configuration
	 *
	 * @param {object} _dest_data
	 * @param {string} _dest_type type of destination address ('one'| 'two')
	 * @param {object} _src_data address data to be used as source contact data|coordination object
	 * @param {string} _src_type type of source address ('browser'|'one'|'two')
	 * @returns {Boolean|string} return url and return false if no address
	 */
	geoLocationUrl: function (_dest_data, _dest_type,_src_data, _src_type)
	{
		var dest_type = _dest_type || 'one';
		var url = this.getGeolocationConfig();

		// exit if no url or invalide url given
		if (!url || typeof url === 'undefined' || typeof url !== 'string')
		{
			egw.debug('warn','no url or invalid url given as geoLocationUrl');
			return false;
		}

		// array of placeholders with their representing values
		var	addrs = [

			[ // source address
				{id:'r0',val:_src_type === 'browser'?_src_data.latitude:_src_data['adr_'+_src_type+'_street']},
				{id:'t0',val:_src_type === 'browser'?_src_data.longitude:_src_data['adr_'+_src_type+'_locality']},
				{id:'c0',val:_src_type === 'browser'?'':_src_data['adr_'+_src_type+'_countrycode']},
				{id:'z0',val:_src_type === 'browser'?'':_src_data['adr_'+_src_type+'_postalcode']}
			],
			[ // destination address
				{id:'r1',val:_dest_data['adr_'+dest_type+'_street']},
				{id:'t1',val:_dest_data['adr_'+dest_type+'_locality']},
				{id:'c1',val:_dest_data['adr_'+dest_type+'_countrycode']},
				{id:'z1',val:_dest_data['adr_'+dest_type+'_postalcode']}
			]
		];

		var src_param = url.match(/{{%rs=.*%rs}}/ig);
		if (src_param[0])
		{
			src_param = src_param[0].replace(/{{%rs=/,'');
			src_param = src_param.replace(/%rs}}/,'');
			url = url.replace(/{{%rs=.*%rs}}/, src_param);
		}

		var d_param = url.match(/{{%d=.*%d}}/ig);
		if (d_param[0])
		{
			d_param = d_param[0].replace(/{{%d=/,'');
			d_param = d_param.replace(/%d}}/,'');
			url = url.replace(/{{%d=.*%d}}/, d_param);
		}

		// Replcae placeholders with acctual values
		for (var j=0;j<addrs.length;j++)
		{
			for (var i=0;i < addrs[j].length; i++)
			{
				url = url.replace('%'+addrs[j][i]['id'], addrs[j][i]['val']? addrs[j][i]['val'] : "");
			}
		}
		return url !== ''? url : false;
	},

	/**
	 * Open a popup base on selected address in provided map
	 *
	 * @param {object} _action
	 * @param {object} _selected
	 */
	geoLocationExec: function (_action, _selected)
	{
		var content = egw.dataGetUIDdata(_selected[0].id);
		var geolocation_src = egw.preference('geolocation_src','addressbook');
		var self = this;

		if (geolocation_src === 'browser' && navigator.geolocation)
		{
			navigator.geolocation.getCurrentPosition(function(position){
				if (position && position.coords)
				{
					var url = self.geoLocationUrl(content.data,_action.id === 'business'?'one':'two', position.coords, 'browser');
					window.open(url,'_blank');
				}
			});
		}
		else
		{
			egw.json('addressbook.addressbook_ui.ajax_get_contact', [egw.user('account_id')],function(_data){
				var url = self.geoLocationUrl(content.data,_action.id === 'business'?'one':'two', _data, geolocation_src === 'browser'?'one':geolocation_src);
				window.open(url,'_blank');
			}).sendRequest();

		}
	},

	/**
	 * Get geolocation_url stored in config|default url
	 *
	 * @returns {String}
	 */
	getGeolocationConfig: function()
	{
		// This default url should be identical to the first value of geolocation_url array
		// defined in addressbook_hooks::config
		var default_url = 'https://maps.here.com/directions/drive{{%rs=/%rs}}%r0,%t0,%z0,%c0{{%d=/%d}}%r1,%t1,%z1+%c1';
		var geo_url = egw.config('geolocation_url');
		if (geo_url) geo_url = geo_url[0];
		return geo_url || default_url;
	}
});
