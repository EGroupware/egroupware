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
	 * et2 widget container
	 */
	et2: null,
	/**
	 * path widget
	 */

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
	 * @param _et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2)
	{
		// call parent
		this._super.apply(this, arguments);

		if (typeof et2.templates['addressbook.edit'] != 'undefined')
		{
			this.show_custom_country($j('select[id*="adr_one_countrycode"]').get(0));
			this.show_custom_country($j('select[id*="adr_two_countrycode"]').get(0));

			// Instanciate infolog JS too - wrong app, so it won't be done automatically
			if(typeof window.app.infolog != 'object' && typeof window.app.classes['infolog'] == 'function')
			{
				window.app.infolog = new window.app.classes.infolog();
			}
		}

		jQuery('select[id*="adr_one_countrycode"]').each(function() {
			app.addressbook.show_custom_country(this);
		});
		jQuery('select[id*="adr_two_countrycode"]').each(function() {
			app.addressbook.show_custom_country(this);
		});
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

	showphones: function(form)
	{
		if (form) {
			copyvalues(form,"tel_home","tel_home2");
			copyvalues(form,"tel_work","tel_work2");
			copyvalues(form,"tel_cell","tel_cell2");
			copyvalues(form,"tel_fax","tel_fax2");
		}
	},

	hidephones: function(form)
	{
		if (form) {
			copyvalues(form,"tel_home2","tel_home");
			copyvalues(form,"tel_work2","tel_work");
			copyvalues(form,"tel_cell2","tel_cell");
			copyvalues(form,"tel_fax2","tel_fax");
		}
	},

	copyvalues: function(form,src,dst)
	{
		var srcelement = getElement(form,src);  //ById("exec["+src+"]");
		var dstelement = getElement(form,dst);  //ById("exec["+dst+"]");
		if (srcelement && dstelement) {
			dstelement.value = srcelement.value;
		}
	},

	getElement: function(form,pattern)
	{
		for (i = 0; i < form.length; i++){
			if(form.elements[i].name){
				var found = form.elements[i].name.search("\\["+pattern+"\\]");
				if (found != -1){
					return form.elements[i];
				}
			}
		}
	},

	check_value: function(input, own_id)
	{
		var values = egw_json_getFormValues(input.form).exec;	// todo use eT2 method, if running under et2
		if(typeof values == 'undefined' && typeof etemplate2 != 'undefined') {
			var template = etemplate2.getByApplication('addressbook')[0];
			values = template.getValues(template.widgetContainer);
		}

		if (input.name.match(/n_/))
		{
			var value = '';
			if (values.n_prefix) value += values.n_prefix+" ";
			if (values.n_given)  value += values.n_given+" ";
			if (values.n_middle) value += values.n_middle+" ";
			if (values.n_family) value += values.n_family+" ";
			if (values.n_suffix) value += values.n_suffix;

			var name = document.getElementById("exec[n_fn]");
			if(name == null && template)
			{
				name = template.widgetContainer.getWidgetById('n_fn');
				name.set_value(value);
			}
			else
			{
				name.value = value;
			}
		}
		egw.json('addressbook.addressbook_ui.ajax_check_values', [values, input.name, own_id]).sendRequest(true, function(data) {
			if (data.msg && confirm(data.msg))
			{
				for(var id in data.doublicates)
				{
					egw.open(id, 'addressbook');
					//opener.egw_openWindowCentered2(egw_webserverUrl+'/index.php?menuaction=addressbook.addressbook_ui.edit&contact_id='+id, '_blank', 870, 480, 'yes', 'addressbook');
				}
			}
			if (typeof data.fileas_options == 'object')
			{
				var selbox = document.getElementById("exec[fileas_type]");
				if (selbox)
				{
					for (var i=0; i < data.fileas_options.length; i++)
					{
						selbox.options[i].text = data.fileas_options[i];
					}
				}
				else if (template && (selbox = template.widgetContainer.getWidgetById('fileas_type')))
				{
					selbox.set_select_options(data.fileas_sel_options);
				}
			}
		});
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
	 *
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
	 *
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
	 *
	 */
	adb_get_selection: function(form)
	{
		var use_all = document.getElementById("exec[use_all]");
		var action = document.getElementById("exec[action]");
		egw_openWindowCentered(egw().link("/index.php","menuaction=importexport.uiexport.export_dialog&appname=addressbook")+
				"&selection="+( use_all.checked  ? "use_all" : get_selected(form,"[rows][checked][]")),"Export",400,400);
		action.value="";
		use_all.checked = false;
		return false;
	},

	/**
	 *
	 */
	do_action: function(selbox)
	{
		if (selbox.value != "")
		{
			if (selbox.value == "infolog_add" && (ids = get_selected(selbox.form,"[rows][checked][]")) && !document.getElementById("exec[use_all]").checked)
			{
				win = window.open(egw().link("/index.php","menuaction=infolog.infolog_ui.edit&type=task&action=addressbook&action_id="+ids),_blank,width=750,height=550,left=100,top=200);
				win.focus();
			}
			else if (selbox.value == "cat_add")
			{
				win = window.open(egw().link("/etemplate/process_exec.php","menuaction=addressbook.addressbook_ui.cat_add"),_blank,width=300,height=400,left=100,top=200);
				win.focus();
			}
			else if (selbox.value == "remove_from_list")
			{
				if (confirm(lang('Remove selected contacts from distribution list'))) selbox.form.submit();
			}
			else if (selbox.value == "delete_list")
			{
				if (confirm(lang('Delete selected distribution list!'))) selbox.form.submit();
			}
			else if (selbox.value == "delete")
			{
				if (confirm(lang('Delete'))) selbox.form.submit();
			}
			else
			{
				selbox.form.submit();
			}
			selbox.value = "";
		}
	},

	n_fn_search: function ()
	{
		jQuery('table.editname').css('display','inline');
		//var focElem = document.getElementById(form::name('n_prefix'));
		if (!(typeof(focElem) == 'undefined') && typeof(focElem.focus)=='function')
		{
			//document.getElementById(form::name('n_prefix')).focus();
		}
	},

	xajax_et: function()
	{
		this.et2.getInstanceManager().submit(this.et2.getWidgetById('button[search]'));
		return false;
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
