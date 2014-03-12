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
		//delete this.et2;
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
		// call parent
		this._super.apply(this, arguments);

		switch (name)
		{
			case 'addressbook.edit':
				var content = this.et2.getArrayMgr('content').data;
				if (typeof content.showsearchbuttons == 'undefined' || !content.showsearchbuttons)
				{
					this.show_custom_country($j('select[id*="adr_one_countrycode"]').get(0));
					this.show_custom_country($j('select[id*="adr_two_countrycode"]').get(0));

					// Instanciate infolog JS too - wrong app, so it won't be done automatically
					if(typeof window.app.infolog != 'object' && typeof window.app.classes['infolog'] == 'function')
					{
						window.app.infolog = new window.app.classes.infolog();
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
	 * Open CRM view
	 *
	 * @param _action
	 * @param _senders
	 */
	view: function(_action, _senders)
	{
		var index = _senders[0]._index;
		var id = _senders[0].id.split('::').pop();

		this.egw.open(id, 'addressbook', 'view', {index: index}, '_self', 'addressbook');
	},

	/**
	 * Set link filter for the already open & rendered infolog list
	 *
	 * @param {string} contact_id New contact ID
	 */
	view_set_infolog: function(contact_id)
	{
		// Find the infolog list
		var infolog = etemplate2.getById(
			$j(this.et2.getInstanceManager().DOMContainer).nextAll('.et2_container').attr('id')
		);
		var nm = infolog ? infolog.widgetContainer.getWidgetById('nm') : null;
		if(nm)
		{
			// Update the link filter to new contact
			nm.applyFilters({col_filter: {linked: 'addressbook:'+contact_id}});
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
			case 'edit':
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
			var ids = "";
			for (var i = 0; i < _senders.length; i++)
			{
				// Remove UID prefix for just contact_id
				var id = _senders[i].id.split('::');
				ids += "c" + id[1] + ((i < _senders.length - 1) ? "," : "");
			}
			var extra = {};
			extra[_action.data.url.indexOf('owner') > 0 ? 'owner' : 'participants'] = ids;

			// Use framework to add calendar entry
			egw.open('','calendar','add',extra);
		}
	},

	/**
	 * View infolog entries linked to selected contact - just one, infolog fails with multiple
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
		// Remove UID prefix for just contact_id
		var id = _senders[0].id.split('::');
		extras.action_id = id[1];

		egw.open('', 'infolog', 'list', extras, 'infolog');
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
			confirmdialog('Duplicate warning',content,et2_dialog.BUTTONs_OK_CANCEL);
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

	add_whole_list: function(list)
	{
		if (document.getElementById("exec[nm][email_type][email_home]").checked == true)
		{
			email_type = "email_home";
		}
		else
		{
			email_type = "email";
		}
		var request = new egw_json_request("addressbook.addressbook_ui.ajax_add_whole_list",list,email_type);
		request.sendRequest(true);
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
				$j(selectbox).trigger("liszt:updated");

				custom_field.style.display = "inline";
			}
			else
			{
				custom_field.style.display = "none";
			}
		}
	},

	add_new_list: function(owner)
	{
		var name = window.prompt(this.egw.lang('Name for the distribution list'));
		if (name)
		{
			egw.open('','addressbook', 'list', {
				'add_list': name,
				'owner': owner
			},'_self');
		}
	},

	filter2_onchange: function()
	{
		var filter2 = this.et2.getWidgetById('filter2');
		var widget = this.et2.getWidgetById('nm');

		if(filter2.get_value()=='add')
		{
			this.add_new_list(typeof widget == 'undefined' ? this.et2.getWidgetById('filter').value : widget.header.filter.get_value());
			this.value='';
		}
	},

	filter2_onchnage_email: function ()
	{
		this.form.submit();
		if (this.value && confirm('Add emails of whole distribution list?'))
		{
			this.add_whole_list(this.value);
		}
		else
		{
			this.form.submit();
		}
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
	 *
	 */
	 adv_search: function()
	 {
		var link = opener.location.href;
		link = link.replace(/#/,'');
		opener.location.href=link.replace(/\#/,'');
	 },

	/**
	 * Mail vCard
	 *
	 * @param {object} _action
	 * @param {array} _elems
	 */
	adb_mail_vcard: function(_action, _elems)
	{
		var app_registry = egw.link_get_registry('felamimail');
		var link = egw().link("/index.php","menuaction=felamimail.uicompose.compose");
		for (var i = 0; i < _elems.length; i++)
		{
			link += "&preset[file][]="+encodeURIComponent("vfs://default/apps/addressbook/"+_elems[i].id+"/.entry");
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
		// Go through selected & pull email addresses from data
		var emails = [];
		for(var i = 0; i < selected.length; i++)
		{
			// Pull data from global cache
			var data = egw.dataGetUIDdata(selected[i].id) || {data:{}};

			var email_business = data.data[action.getManager().getActionById('email_business').checked ? 'email' : ''];
			var email = data.data[action.getManager().getActionById('email_home').checked ? 'email_home' : ''];
			if(email_business)
			{
				emails.push(email_business);
			}
			if(email)
			{
				emails.push(email);
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
		return this._super.apply(this, arguments);
	}

});
