/**
 * EGroupware - Admin - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2013-14 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for Admin
 *
 * @augments AppJS
 */
app.classes.admin = AppJS.extend(
/**
 * @lends app.classes.admin
 */
{
	appname: 'admin',

	/**
	 * reference to iframe
	 *
	 * {et2_iframe}
	 */
	iframe: null,

	/**
	 * reference to nextmatch
	 *
	 * {et2_extension_nextmatch}
	 */
	nm: null,

	/**
	 * Reference to ACL edit dialog (not the list)
	 */
	acl_dialog: null,

	/**
	 * Constructor
	 *
	 * @memberOf app.classes.admin
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
		this.iframe = null;
		this.nm = null;
		this.acl_dialog = null;

		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} _et2
	 * @param {string} _name name of template loaded
	 */
	et2_ready: function(_et2, _name)
	{
		// call parent
		this._super.apply(this, arguments);

		switch(_name)
		{
			case 'admin.index':
				var iframe = this.iframe = this.et2.getWidgetById('iframe');
				this.nm = this.et2.getWidgetById('nm');
				if (iframe)
				{
					var self = this;
					jQuery(iframe.getDOMNode()).off('load.admin')
						.bind('load.admin', function(){
							self._hide_navbar.call(self);
						}
					);

					// Register app refresh now that iframe is available
					//register_app_refresh('admin',jQuery.proxy(this.refresh,this));
				}
				break;

			case 'admin.categories.index':
				break;
		}
	},

	/**
	 * Show given url in (visible) iframe or nextmatch with accounts (!_url)
	 *
	 * @param {string} [_url=] url to show in iframe or nothing for showing
	 */
	load: function(_url)
	{
		if (_url)
		{
			this.iframe.set_src(_url);
		}
		else
		{
			this.egw.app_header('');
		}
		this.iframe.set_disabled(!_url);
		this.nm.set_disabled(!!_url);
	},

	/**
	 * Observer method receives update notifications from all applications
	 *
	 * App is responsible for only reacting to "messages" it is interested in!
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
	 * @param {string} _targetapp which app's window should be refreshed, default current
	 * @return {false|*} false to stop regular refresh, thought all observers are run
	 */
	observer: function(_msg, _app, _id, _type, _msg_type, _targetapp)
	{
		switch(_app)
		{
			case 'admin':
				// invalidate client-side account-cache
				this.egw.invalidate_account(_id, _type);
				// group deleted, added or updated
				if (_id < 0)
				{
					var tree = this.et2.getWidgetById('tree');
					switch(_type)
					{
						case 'delete':
							tree.deleteItem('/groups/'+_id, false);
							return false;	// --> no regular refresh

						default:	// add, update, edit, null
							tree.refreshItem('/groups');
							return false;	// --> no regular refresh
					}
				}
		}
	},

	/**
	 * Special handling for egw_refresh() in admin, to refresh the iframe when
	 * the framework detects a simple refresh can be used (same URL).
	 *
	 * All parameters ignored.
	 *
	 * @param {string} _msg Message to display
	 * @param {string} _app Application being refreshed, should be 'admin'
	 * @param {string} _id Unique record ID.
	 * @param {string} _type Type of refresh.  Either 'edit', 'delete',
	 *	'add' or null
	 */
	refresh: function(_msg, _app, _id, _type)
	{
		var refresh_done = false;

		// group deleted, added or updated
		if (_app === 'admin' && _id < 0)
		{
			var tree = this.et2.getWidgetById('tree');
			switch(_type)
			{
				case 'delete':
					tree.deleteItem('/groups/'+_id, false);
					break;

				default:	// add, update, edit, null
					tree.refreshItem('/groups');
					break;
			}
		}

		// Try for intelligent et2 refresh inside iframe
		var node = _app && _id && this.iframe ? this.iframe.getDOMNode(this.iframe) : null;
		if(node && node.contentWindow && node.contentWindow.etemplate2)
		{
			var templates = node.contentWindow.etemplate2.getByApplication('admin');
			for(var i = 0; i < templates.length; i++)
			{
				templates[i].refresh(_msg,_app,_id,_type);
				refresh_done = true;
			}
		}

		// update of account list eg. from addressbook.edit
		if(!refresh_done && _app == 'admin' && _id)
		{
			var templates = etemplate2.getByApplication('admin');
			for(var i = 0; i < templates.length; i++)
			{
				templates[i].refresh(_msg,_app,_id,_type);
				refresh_done = true;
			}
		}

		// update iframe
		if (!refresh_done && framework)
		{
			var app = framework.getApplicationByName(_app);

			if (app && app.browser && app.browser.currentLocation)
			{
				this.linkHandler(app.browser.currentLocation);
			}
		}
	},

	/**
	 * Hide navbar for idots template
	 *
	 * Just a hack for old idots, not neccesary for jdots
	 */
	_hide_navbar: function()
	{
		var document = this.iframe.getDOMNode().contentDocument;

		if (!document) return;	// nothing we can do ...

		// set white background, as transparent one lets account-list show through
		document.getElementsByTagName('body')[0].style.backgroundColor = 'white';

		// hide navbar elements
		var ids2hide = ['divLogo', 'topmenu', 'divAppIconBar', 'divStatusBar', 'tdSidebox', 'divAppboxHeader'];
		for(var i=0; i < ids2hide.length; ++i)
		{
			var elem = document.getElementById(ids2hide[i]);
			if (elem) elem.style.display = 'none';
		}
	},

	/**
	 * Set location of iframe for given _action and _sender (row)
	 *
	 * @param _action
	 * @param _senders
	 */
	iframe_location: function(_action, _senders)
	{
		var id = _senders[0].id.split('::');
		var url = _action.data.url.replace(/(%24|\$)id/, id[1]);

		this.load(url);
	},

	/**
	 * Link hander for jDots template to just reload our iframe, instead of reloading whole admin app
	 *
	 * @param _url
	 * @return boolean true, if linkHandler took care of link, false otherwise
	 */
	linkHandler: function(_url)
	{
		var matches = _url.match(/menuaction=admin.admin_ui.index.*&load=([^&]+)/);
		if (_url !='about:blank' && (this.iframe != null && !_url.match('menuaction=admin.admin_ui.index') || matches))
		{
			if (matches)
			{
				_url = _url.replace(/menuaction=admin.admin_ui.index/, 'menuaction='+matches[1]).replace(/&(ajax=true|load=[^&]+)/g, '');
			}
			this.load(_url);
			return true;
		}
		// can not load our own index page, has to be done by framework
		return false;
	},

	/**
	 * Run an admin module / onclick callback for tree
	 *
	 * @param {string} _id id of clicked node
	 * @param {et2_tree} _widget reference to tree widget
	 */
	run: function(_id, _widget)
	{
		var link = _widget.getUserData(_id, 'link');

		if (_id == '/accounts' || _id.substr(0, 8) == '/groups/')
		{
			this.load();
			var parts = _id.split('/');
			this.et2.getWidgetById('nm').applyFilters({ filter: parts[2] ? parts[2] : '', search: ''});
		}
		else if (typeof link == 'undefined')
		{
			_widget.openItem(_id, 'toggle');
		}
		else if (link[0] == '/' || link.substr(0,4) == 'http')
		{
			this.load(link+(link.match(/\?/)?'&':'?')+'nonavbar=1');
		}
		else if (link.substr(0,11) == 'javascript:')
		{
			eval(link.substr(11));
		}
	},

	/**
	 * View, edit or delete a group callback for tree
	 *
	 * @param {object} _action egwAction
	 * @param {array} _senders egwActionObject _senders[0].id holds id
	 */
	group: function(_action, _senders)
	{
		switch(_action.id)
		{
			case 'view':
				this.run(_senders[0].id, this.et2.getWidgetById('tree'));
				break;

			case 'delete':
				var account_id = _senders[0].id.split('/')[2];
				this.egw.json('admin_account::ajax_delete_group', [account_id]).sendRequest();
				break;

			default:
				if (!_action.data.url)
				{
					alert('Missing url in action '+_action.id+'!');
					break;
				}
				var url = _action.data.url.replace('$id', _senders[0].id.split('/')[2]);
				if (url[0] != '/' && url.substr(0, 4) != 'http')
				{
					url = this.egw.link('/index.php', url);
				}
				if (_action.data.popup)
				{
					this.egw.open_link(url, '_blank', _action.data.popup);
				}
				else
				{
					this.load(url);
				}
				break;
		}
	},

	/**
	 * Modify an ACL entry
	 *
	 * @param {object} _action egwAction
	 * @param {array} _senders egwActionObject _senders[0].id holds the id "admin::app:account:location"
	 */
	acl: function(_action, _senders)
	{
		var ids = [];
		for(var i=0; i < _senders.length; ++i)
		{
			ids.push(_senders[i].id.split('::').pop());	// remove "admin::" prefix
		}

		// For edit, set some data from the list since it's already there
		var content = _senders[0].id ? jQuery.extend({}, egw.dataGetUIDdata(_senders[0].id).data) : {};

		switch(_action.id)
		{
			case 'delete':
				var app = egw.app_name();	// can be either admin or preferences!
				if (app != 'admin') app = 'preferences';
				var className = app+'_acl';
				var request = egw.json(className+'::ajax_change_acl', [ids], this._acl_callback,this,false,this)
					.sendRequest();
				break;

			case 'add':
				// No content, even if they clicked on a row
				// Defaults set by _acl_content() based on nm values
				content = {};
				// Fall through
			case 'edit':
				this._acl_dialog(content);
				break;
		}
	},

	/**
	 * Create the ACL edit dialog, including defaults & fetching what can be found
	 *
	 * @param content List of content for the dialog template
	 * @param sel_options optional select options
	 */
	_acl_dialog: function(content, sel_options)
	{
		if(typeof content == 'undefined') content = {};

		// Determine which application we're running as
		var app = egw.app_name();	// can be either admin or preferences!
		if (app != 'admin') app = 'preferences';
		var className = app+'_acl';
		var acl_rights = {};
		var readonlys = {acl: {}};

		// Select options are already here, just pull them and pass along
		sel_options = jQuery.extend({}, this.et2.getArrayMgr('sel_options').data||{}, {
			'apps': this.et2.getArrayMgr('sel_options').getEntry('filter2')
		},sel_options);

		// Some defaults
		if(this.et2 && this.et2.getWidgetById('nm'))
		{
			// This is which checkboxes are available for each app
			var acl_rights = this.et2.getWidgetById('nm').getArrayMgr('content').getEntry('acl_rights')||{};

			if(!content.acl_appname)
			{
				// Pre-set appname to currently selected
				content.acl_appname = this.et2.getWidgetById('filter2').getValue()||"";
			}
			if(!content.acl_account)
			{
				content.acl_account = this.et2.getWidgetById('nm').getArrayMgr('content').getEntry('account_id');
			}
			if(!content.acl_location)
			{
				content.acl_location = this.et2.getWidgetById('filter').getValue() == 'run' ? 'run' : null;
			}
			if(content.acl_location == 'run')
			{
				// These are the apps the account has access to
				// Fetch current values from server
				this.egw.json(className+'::ajax_get_app_list', [content.acl_account], function(data) {content.apps = data;},this,false,this)
					.sendRequest();
			}
			else
			{
				// Restrict application selectbox to only apps that support ACL
				sel_options.acl_appname = {};
				for(var app in acl_rights)
				{
					sel_options.acl_appname[app] = this.egw.lang(app);
				}
			}

		}
		if(content.acl_appname)
		{
			// Load checkboxes & their values
			content.acl_rights = content.acl_rights ? parseInt(content.acl_rights) : null;
			jQuery.extend(content, {acl:[],right:[],label:[]});
			for( var right in acl_rights[content.acl_appname])
			{
				// only user himself is allowed to grant private (16) rights
				if(right == '16' && content['acl_account'] != egw.user('account_id'))
				{
					readonlys.acl[content.acl.length] = true;
				}
				content.acl.push(content.acl_rights & right);
				content.right.push(right);
				content.label.push(egw.lang(acl_rights[content.acl_appname][right]));
			}
		}

		// Make sure selected values are there, account might not be in a default group
		// so not in cache
		if(content.acl_account)
		{
			var accounts = this.egw.accounts('both');
			var there = false;
			for(var i = 0; i < accounts.length; i++)
			{
				if(accounts[i].value == content.acl_account)
				{
					there = true;
					break;
				}
			}
			if(!there)
			{
				sel_options.acl_account = new Array().concat(sel_options.acl_account);
				this.egw.link_title('home-accounts', content.acl_account, function(title) {sel_options.acl_account.push({value: content.acl_account, label: title});});
			}
		}
		if(content.acl_location)
		{
			sel_options.acl_location = jQuery.extend({},sel_options.acl_location);
			this.egw.link_title('home-accounts', content.acl_location, function(title) {sel_options.acl_location[content.acl_location] = title;});
		}

		// Create the dialog
		this.acl_dialog = et2_createWidget("dialog", {
			callback: jQuery.proxy(function(_button_id, _value) {
				this.acl_dialog = null;
				if(_button_id != et2_dialog.OK_BUTTON) return;

				// Restore account if it's readonly in dialog
				if(!_value.acl_account) _value.acl_account = content.acl_account;

				// Only send the request if they entered everything
				if(_value.acl_account && (_value.acl_appname && _value.acl_location || _value.apps))
				{
					var id = _value.acl_appname+':'+_value.acl_account+':'+_value.acl_location;
					var rights = 0;
					for(var i in _value.acl)
					{
						rights += parseInt(_value.acl[i]);
					}
					if(_value.apps && !_value.acl_appname)
					{
						id = [];
						rights = 1;
						var removed = [];

						// Loop through all apps, remove the ones with no permission
						for(var app in sel_options.apps)
						{
							var run_id = app+":"+_value.acl_account+":run";
							if(_value.apps.indexOf(app) < 0 && content.apps.indexOf(app) >= 0)
							{
								removed.push(run_id);
							}
							else if (_value.apps.indexOf(app) >= 0 && content.apps.indexOf(app) < 0)
							{
								id.push(run_id);
							}
						}

						// Remove any removed
						if(removed.length > 0)
						{
							this.egw.json(className+'::ajax_change_acl', [removed, 0], this._acl_callback,this,false,this)
								.sendRequest();
						}
					}
					this.egw.json(className+'::ajax_change_acl', [id, rights], this._acl_callback,this,false,this)
						.sendRequest();
				}
			},this),
			title: egw.lang('Access control'),
			buttons: et2_dialog.BUTTONS_OK_CANCEL,
			value: {
				content: content,
				sel_options: sel_options,
				readonlys: readonlys
			},
			template: egw.webserverUrl+'/admin/templates/default/acl.edit.xet'
		}, et2_dialog._create_parent(app));
	},

	/**
	 * Change handler for ACL edit dialog application selectbox.
	 * Re-creates the dialog with the current values
	 *
	 * @param input
	 * @param widget
	 */
	acl_reopen_dialog: function(input, widget)
	{
		var content = {};
		if(this.acl_dialog != null)
		{
			content = this.acl_dialog.get_value() || {};

			// Destroy the dialog
			this.acl_dialog.free();
			this.acl_dialog = null;
		}
		// Re-open the dialog
		this._acl_dialog(content);
	},

	/**
	 * Callback called on successfull call of serverside ACL handling
	 *
	 * @param {object} _data returned from server
	 */
	_acl_callback: function(_data)
	{
		// Avoid the window / framework / app and just refresh the etemplate
		// Framework will try to refresh the opener
		this.et2.getInstanceManager().refresh(_data.msg, this.appname,_data.ids,_data.type);
	},

	/**
	 * Check to see if admin has taken away access to a category
	 *
	 * @@param {widget} button add/apply pressed button
	 */
	check_owner: function(button) {
		var select_owner = this.et2.getWidgetById('owner');
		var diff = [];

		if (typeof select_owner != 'undefined')
		{
			var owner = select_owner.get_value();
		}

		if(typeof owner != 'object')
		{
			owner = [owner];
		}
		// No owner probably means selectbox is read-only, so no need to check
		if(owner == null) return true;

		var all_users = owner.indexOf('0') >= 0;

		// If they checked all users, uncheck the others
		if(all_users) {
			select_owner.set_value(['0']);
			return true;
		}

		// Find out what changed
		var cat_original_owner = this.et2.getArrayMgr('content').getEntry('owner');
		if (cat_original_owner)
		{
			var selected_groups = select_owner.get_value().toString();

			for(var i =0;i < cat_original_owner.length;i++)
			{
				if (selected_groups.search(cat_original_owner[i]) < 0)
				{
					diff.push(cat_original_owner[i]);
				}
			}

			if (diff.length > 0)
			{
				var removed_cat_label = jQuery.map(select_owner.options.select_options, function (val, i)
				{
					for (var j=0; j <= diff.length;j++)
					{
						if (diff[j] == val.value)
						{
							return val.label;
						}
					}
				});

				// Somebody will lose permission, give warning.
				if(removed_cat_label)
				{
					var msg = this.egw.lang('Removing access for groups may cause problems for data in this category.  Are you sure?  Users in these groups may no longer have access:');
					return et2_dialog.confirm(button,msg + removed_cat_label.join(','));
				}
			}
		}
		return true;
	},

	/**
	 * Show icon based on icon-selectbox, hide placeholder (broken image), if no icon selected
	 *
	 * @param {widget} widget select box widget
	 */
	change_icon: function(widget)
	{
		var img = widget.getRoot().getWidgetById('icon_url');

		if (img)
		{
			img.set_src(widget.getValue());
		}
	},


	/**
	 * Add / edit an account
	 *
	 * @param {object} _action egwAction
	 * @param {array} _senders egwActionObject _senders[0].id holds account_id
	 */
	account: function(_action, _senders)
	{
		var params = jQuery.extend({}, this.egw.link_get_registry('addressbook', 'edit'));
		var popup = this.egw.link_get_registry('addressbook', 'edit_popup');

		if (_action.id == 'add')
		{
			params.owner = '0';
		}
		else
		{
			params.account_id = _senders[0].id.split('::').pop();	// get last :: separated part
		}
		this.egw.open_link(this.egw.link('/index.php', params), 'admin', popup);
	}
});
