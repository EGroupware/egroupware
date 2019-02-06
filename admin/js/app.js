/**
 * EGroupware - Admin - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2013-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: app.js 56051 2016-05-06 07:58:37Z ralfbecker $
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
	 * Refarence to div to hold AJAX loadable pages
	 *
	 * {et2_box}
	 */
	ajax_target: null,

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
				this.groups = this.et2.getWidgetById('groups');
				this.groups.set_disabled(true);
				this.ajax_target = this.et2.getWidgetById('ajax_target');
				if (iframe)
				{
					var self = this;
					jQuery(iframe.getDOMNode()).off('load.admin')
						.bind('load.admin', function(){
							if (this.contentDocument.location.href.match(/(\/admin\/|\/admin\/index.php|menuaction=admin.admin_ui.index)/))
							{
								this.contentDocument.location.href = 'about:blank';	// stops redirect from admin/index.php
								self.load();	// load own top-level index aka user-list
							}
							self._hide_navbar.call(self);
						}
					);
				}
				if( this.ajax_target && this.et2.getArrayMgr('content').getEntry('ajax_target'))
				{
					this.load(this.et2.getArrayMgr('content').getEntry('ajax_target'));
				}
				break;

			case 'admin.customfield_edit':
				// Load settings appropriate to currently set type
				var widget = _et2.widgetContainer.getWidgetById('cf_type');
				this.cf_type_change(null,widget);
				break;

			case 'admin.mailaccount':
				this.account_hide_not_applying();
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
		if (this.iframe && this.iframe.getDOMNode().contentDocument.location.href
			.match(/menuaction=admin.admin_statistics.submit/) &&
			!_url.match(/statistics=(postpone|canceled|submitted)/))
		{
			this.egw.message(this.egw.lang('Please submit (or postpone) statistic first'), 'info');
			return;	// do not allow to leave statistics submit
		}
		// url outside EGroupware eg. eSyncPro linking to wikipedia
		if (_url && _url.indexOf(this.egw.webserverUrl) == -1)
		{
			window.open(_url, '_blank');
			return;
		}
		var ajax = false;
		if (_url)
		{
			// Try to load it without the iframe
			ajax = _url.match(/ajax=true/) && _url.match(/menuaction=/);
			if(ajax)
			{

				if(this.ajax_target.node.children.length)
				{
					// Node has children already?  Check for loading over an
					// existing etemplate, and remove it first
					jQuery(this.ajax_target.node.children).each(function() {
						var old = etemplate2.getById(this.id);
						if(old) old.clear();
					});
					jQuery(this.ajax_target.node).empty();
				}
				this.egw.json(
					framework.activeApp.getMenuaction('ajax_exec'),
					// It's important that the context is null, or etemplate2
					// won't load the template properly
					[_url], this._ajax_load_callback,null, true, this
				).sendRequest();
			}
			else
			{
				this.iframe.set_src(_url);
			}
		}
		else
		{
			this.egw.app_header('');
			// blank iframe, to not keep something running there
			this.iframe.getDOMNode().contentDocument.location.href = 'about:blank';
		}
		this.iframe.set_disabled(!_url || ajax);
		this.nm.set_disabled(!!_url || ajax);
		this.groups.set_disabled(true);
		this.ajax_target.set_disabled(!ajax);
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
				// if iframe is used --> refresh it
				var iframe_node = this.iframe ? this.iframe.getDOMNode() : undefined;
				var iframe_url = iframe_node ? iframe_node.contentDocument.location.href : undefined;
				if (_id && iframe_url != 'about:blank')
				{
					var refresh_done = false;
					// Try for intelligent et2 refresh inside iframe
					if(iframe_node && iframe_node.contentWindow && iframe_node.contentWindow.etemplate2)
					{
						var templates = iframe_node.contentWindow.etemplate2.getByApplication('admin');
						for(var i = 0; i < templates.length; i++)
						{
							templates[i].refresh(_msg,_app,_id,_type);
							refresh_done = true;
						}
					}
					if (!refresh_done)	// --> reload iframe
					{
						this.load(iframe_url);
					}
					return false;	// --> no regular refresh
				}
				// invalidate client-side account-cache
				this.egw.invalidate_account(_id, _type);
				// group deleted, added or updated
				if (_id < 0)
				{
					var tree = this.et2.getWidgetById('tree');
					var nm = this.et2.getWidgetById('nm');
					switch(_type)
					{
						case 'delete':
							tree.deleteItem('/groups/'+_id, false);
							if (nm) nm.getInstanceManager().submit();
							break;

						default:	// add, update, edit, null
							if (nm)
							{
								var activeFilters = nm.activeFilters;
								nm.getInstanceManager().submit();
								var nm = this.et2.getWidgetById('nm');
								nm.applyFilters(activeFilters);
							}

					}
					var refreshTree = this.et2.getWidgetById('tree');
					if (refreshTree) refreshTree.refreshItem('/groups');
					return false;	// --> no regular refresh
				}
				// not a user or group, eg. categories
				else if (!_id)
				{
					this.load();
					return false;	// --> no regular refresh needed
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
	 * Callback to load an etemplate
	 *
	 * @param {Object[]} _data
	 */
	_ajax_load_callback: function(_data)
	{
		if(!_data || _data.type != undefined) return;

		// Insert the content, etemplate will load into it
		jQuery(this.ajax_target.node).append(typeof _data === 'string' ? _data : _data[0]);
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
				_url = _url.replace(/menuaction=admin.admin_ui.index/, 'menuaction='+matches[1]).replace(/&(load=[^&]+)/g, '');
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

		this.groups.set_disabled(true);
		this.nm.set_disabled(false);

		if (_id == '/accounts' || _id.substr(0, 8) == '/groups/')
		{
			this.load();
			var parts = _id.split('/');
			this.et2.getWidgetById('nm').applyFilters({ filter: parts[2] ? parts[2] : '', search: ''});
		}
		else if (_id === '/groups')
		{
			this.load();
			this.group_list();
		}
		else if (typeof link == 'undefined')
		{
			_widget.openItem(_id, 'toggle');
		}
		else if (link[0] == '/' || link.substr(0,4) == 'http')
		{
			link += (link.indexOf('?') >= 0 ? '&' : '?')+'nonavbar=1';
			this.load(link);
		}
		else if (link.substr(0,11) == 'javascript:')
		{
			eval(link.substr(11));
		}
	},

	/**
	 * Show the group list in the main window
	 */
	group_list: function group_list()
	{
		this.nm.set_disabled(true);
		this.groups.set_disabled(false);
	},


	/**
	 * View, edit or delete a group callback for tree
	 *
	 * @param {object} _action egwAction
	 * @param {array} _senders egwActionObject _senders[0].id holds id
	 */
	group: function(_action, _senders)
	{
		// Tree IDs look like /groups/ID, nm uses admin::ID
		var from_nm = _senders[0].id.indexOf('::') > 0;
		var account_id = _senders[0].id.split(from_nm ? '::' : '/')[from_nm ? 1 : 2];

		switch(_action.id)
		{
			case 'view':
				this.run(from_nm ? '/groups/'+account_id : _senders[0].id, this.et2.getWidgetById('tree'));
				break;

			case 'delete':
				this.egw.json('admin_account::ajax_delete_group', [account_id]).sendRequest();
				break;

			default:
				if (!_action.data.url)
				{
					alert('Missing url in action '+_action.id+'!');
					break;
				}
				var url = unescape(_action.data.url).replace('$id', account_id);
				if (url[0] != '/' && url.substr(0, 4) != 'http')
				{
					url = this.egw.link('/index.php', url);
				}
				if (_action.data.popup || _action.data.width && _action.data.height)
				{
					this.egw.open_link(url, '_blank', _action.data.popup ? _action.data.popup : _action.data.width + 'x' + _action.data.height);
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
		// Get by ID, since this.et2 isn't always the ACL list
		var et2 = etemplate2.getById('admin-acl').widgetContainer;
		var className = app+'_acl';
		var acl_rights = {};
		var readonlys = {acl: {}};

		// Select options are already here, just pull them and pass along
		sel_options = et2.getArrayMgr('sel_options').data||{};

		// Some defaults
		if(et2 && et2.getWidgetById('nm'))
		{
			// This is which checkboxes are available for each app
			var acl_rights = et2.getWidgetById('nm').getArrayMgr('content').getEntry('acl_rights')||{};

			if(!content.acl_appname)
			{
				// Pre-set appname to currently selected
				content.acl_appname = et2.getWidgetById('filter2').getValue()||"";
			}
			if(!content.acl_account)
			{
				content.acl_account = et2.getWidgetById('nm').getArrayMgr('content').getEntry('account_id');
			}
			if(!content.acl_location)
			{
				content.acl_location = et2.getWidgetById('filter').getValue() == 'run' ? 'run' : null;
			}
			// If no admin rights, change UI to not allow adding access to apps
			if(content.acl_location == 'run' && !egw.user('apps')['admin'])
			{
				content.acl_location = null;
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
				sel_options.acl_appname = [];
				for(var app in acl_rights)
				{
					sel_options.acl_appname.push({value: app, label: this.egw.lang(app)});
				}
				// Sort list
				sel_options.acl_appname.sort(function(a,b) {
					if(a.label > b.label) return 1;
					if(a.label < b.label) return -1;
					return 0;
				});
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

		if(content.acl_account && !egw.user('apps')['admin'])
		{
			readonlys.acl_account = true;
		}
		if(content.acl_location)
		{
			sel_options.acl_location = jQuery.extend({},sel_options.acl_location);
			this.egw.link_title('api-accounts', content.acl_location, function(title) {sel_options.acl_location[content.acl_location] = title;});
		}

		// Create the dialog
		this.acl_dialog = et2_createWidget("dialog", {
			callback: jQuery.proxy(function(_button_id, _value) {
				this.acl_dialog = null;
				if(_button_id != et2_dialog.OK_BUTTON) return;

				// Restore account if it's readonly in dialog
				if(!_value.acl_account) _value.acl_account = content.acl_account;

				// Handle no applications selected
				if(typeof _value.apps == 'undefined' && content.acl_location == 'run')
				{
					_value.apps = [];
				}

				// Only send the request if they entered everything (or selected no apps)
				if(_value.acl_account && (_value.acl_appname && _value.acl_location || typeof _value.apps != 'undefined'))
				{
					var id = [];
					if(_value.acl_appname && _value.acl_account && _value.acl_location)
					{
						id = _value.acl_appname+':'+_value.acl_account+':'+_value.acl_location;
						if(content && content.id && id != content.id)
						{
							// Changed the account or location, remove previous or we
							// get a new line instead of an edit
							this.egw.json(className+'::ajax_change_acl', [content.id, 0], null,this,false,this)
								.sendRequest();
						}
						id = [id];
					}
					var rights = 0;
					for(var i in _value.acl)
					{
						rights += parseInt(_value.acl[i]);
					}
					if(typeof _value.apps != 'undefined' && !_value.acl_appname)
					{
						rights = 1;
						var removed = [];

						// Loop through all apps, remove the ones with no permission
						for(var idx in sel_options.filter2)
						{
							var app = sel_options.filter2[idx].value || false;
							if (!app) continue;
							var run_id = app+":"+_value.acl_account+":run";
							if(_value.apps.indexOf(app) < 0 && (content.apps.indexOf(app) >= 0 || content.apps.length == 0))
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
			title: this.egw.lang('Access control'),
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
		// Get by ID, since this.et2 isn't always the ACL list
		var et2 = etemplate2.getById('admin-acl').widgetContainer;
		et2.getInstanceManager().refresh(_data.msg, this.appname,_data.ids,_data.type);
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
	},

	/**
	 * Submit statistic
	 *
	 * Webkit browsers (Chrome, Safari, ...) do NOT allow to call form.submit() from within onclick of a submit button.
	 * Therefor we first store our own form action, replace it with egroupware.org submit url and set a timeout calling
	 * submit_statistic again with just the form, to do the second submit to our own webserver
	 *
	 * @param {DOM} form
	 * @param {string} submit_url
	 * @return {boolean}
	 */
	submit_statistic: function(form, submit_url)
	{
		var that = this;
		var submit = function(_button)
		{
			// submit to egroupware.org
			var method=form.method;
			form.method='POST';
			var action = form.action;
			form.action=submit_url;
			var target = form.target;
			form.target='_blank';
			form.submit();

			// submit to own webserver
			form.method=method;
			form.action=action;
			form.target=target;
			that.et2.getInstanceManager().submit('submit');
		};

		// Safari does NOT allow to call form.submit() outside of onclick callback
		// so we have to use browsers ugly synchron confirm
		if (navigator.userAgent.match(/Safari/) && !navigator.userAgent.match(/Chrome/))
		{
			if (confirm(this.egw.lang('Submit displayed information?')))
			{
				submit();
			}
		}
		else
		{
			et2_dialog.show_dialog(function(_button)
			{
				if (_button == et2_dialog.YES_BUTTON)
				{
					submit();
				}
			}, this.egw.lang('Submit displayed information?'), '', {},
				et2_dialog.BUTTON_YES_NO, et2_dialog.QUESTION_MESSAGE, undefined, egw);
		}
		return false;
	},

	/**
	 * Delete confirmation dialog
	 *
	 * @param {egw action} _action
	 */
	delete_category: function (_action)
	{
		var _buttons = [
				{"button_id": "delete[cancel]","text": this.egw.lang('Cancel'), id: 'delete[cancel]', image: 'cancel', "default":true},
				{"button_id": "delete[delete]","text": this.egw.lang('Delete'), id: 'delete[delete]', image: 'delete'},
				{"button_id": "delete[subs]","text": this.egw.lang('Delete including sub-entries'), id: 'delete[subs]', image: 'delete'}
		];
		var action = _action;
		var self = this;
		var delDialog_callBack = function (_buttons)
		{
			if (_buttons != "delete[cancel]")
			{
				if (self.et2._inst.name == "admin.categories.index")
				{
					var nm_widget = self.et2.getWidgetById('nm');
					if (nm_widget)
					{
						if (_buttons == "delete[delete]")
						{
							nm_action(action);
						}
						else
						{
							action.id = 'delete_sub';
							nm_action(action);
						}
					}
				}
				else
				{
					self.et2.getInstanceManager().submit(_buttons);
				}
			}
		};
		et2_dialog.show_dialog(delDialog_callBack,this.egw.lang("Are you sure you want to delete this category ?"),this.egw.lang("Delete"),{},_buttons,et2_dialog.WARNING_MESSAGE,null,'admin');
	},

	/**
	 * Change handler for when you change the type of a custom field.
	 * It toggles options / attributes as appropriate.
	 * @param {event object} e
	 * @param {widget object} widget
	 */
	cf_type_change: function(e,widget) {
		var root = widget.getRoot();
		var attributes = widget.getArrayMgr('content').getEntry('attributes['+widget.getValue()+']')||{};
		root.getWidgetById('cf_values').set_statustext(widget.egw().lang(widget.getArrayMgr('content').getEntry('options['+widget.getValue()+']')||''));
		jQuery(root.getWidgetById('cf_len').getDOMNode()).toggle(attributes.cf_len && true);
		jQuery(root.getWidgetById('cf_rows').getDOMNode()).toggle(attributes.cf_rows && true);
		jQuery(root.getWidgetById('cf_values').getParentDOMNode()).toggle(attributes.cf_values && true);
	},

	/**
	 * Activate none standard SMTP mail accounts for selected users
	 *
	 * @param {egw_action} _action
	 * @param {array} _selected selected users
	 * @todo remove under construction message
	 */
	emailadminActiveAccounts: function (_action, _selected){

		var menuaction = 'admin.admin_mail.ajax_activeAccounts';
		var accounts = [];
		var msg1 = egw.lang('%1 accounts being activated',Object.keys(_selected).length);

		for (var i=0;i< Object.keys(_selected).length;i++)
		{
			accounts[i] = {id:_selected[i]['id'].split('::')[1],qouta:"", domain:"", status:_action.id == 'active'?_action.id:''};
		}
		var callbackDialog = function (btn){
			if (btn === et2_dialog.YES_BUTTON)
			{
				// long task dialog for de/activation accounts
				et2_dialog.long_task(function(_val, _resp){
					if (_val && _resp.type !== 'error')
					{
						console.log(_val,_resp);
					}
					else
					{

					}
				}, msg1, 'Mail Acounts Activation', menuaction, accounts, 'admin');
			}
		};
		// confirmation dialog
		et2_dialog.show_dialog(callbackDialog, egw.lang('Are you sure you want to %1 mail for selected accounts?', egw.lang(_action.id)), egw.lang('Active Mail Accounts'), {},
			et2_dialog.BUTTON_YES_NO, et2_dialog.WARNING_MESSAGE, undefined, egw);
	},

	/**
	 * No SSL
	 */
	SSL_NONE: 0,
	/**
	 * STARTTLS on regular tcp connection/port
	 */
	SSL_STARTTLS: 1,
	/**
	 * SSL (inferior to TLS!)
	 */
	SSL_SSL: 3,
	/**
	 * require TLS version 1+, no SSL version 2 or 3
	 */
	SSL_TLS: 2,
	/**
	 * if set, verify certifcate (currently not implemented in Horde_Imap_Client!)
	 */
	SSL_VERIFY: 8,

	/**
	 * Resize window methode
	 *
	 * @returns {undefined}
	 */
	wizard_popup_resize: function ()
	{
		var $main_div = jQuery('#popupMainDiv');
		var $et2 = jQuery('.et2_container');
		var w = {
			width: egw_getWindowInnerWidth(),
			height: egw_getWindowInnerHeight()
		};
		// Use et2_container for width since #popupMainDiv is full width, but we still need
		// to take padding/margin into account
		var delta_width = w.width - ($et2.outerWidth(true) + ($main_div.outerWidth(true) - $main_div.width()));
		var delta_height = w.height - ($et2.outerHeight(true) + ($main_div.outerHeight(true) - $main_div.height()));
		if(delta_width != 0 || delta_height != 0)
		{
			window.resizeTo(egw_getWindowOuterWidth() - delta_width,egw_getWindowOuterHeight() - delta_height);
		}
	},

	/**
	 * Switch account wizard to manual entry
	 */
	wizard_manual: function()
	{
		jQuery('.emailadmin_manual').fadeToggle();// not sure how to to this et2-isch
		this.wizard_popup_resize(); // popup needs to be resized after toggling
	},

	/**
	 * onclick for continue button to show progress animation
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	wizard_detect: function(_event, _widget)
	{
		// we need to do a manual asynchronious submit to show progress animation
		// default synchronious submit stops animation!
		if (this.et2._inst.submit('button[continue]', true))	// true = async submit
		{
			var sieve_enabled = this.et2.getWidgetById('acc_sieve_enabled');
			if (!sieve_enabled || sieve_enabled.get_value())
			{
				jQuery('#admin-mailwizard_output').hide();
				jQuery('td.emailadmin_progress').show();
			}
		}
		return false;
	},

	/**
	 * Set default port, if imap ssl-type changes
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	wizard_imap_ssl_onchange: function(_event, _widget)
	{
		var ssl_type = _widget.get_value();
		this.et2.getWidgetById('acc_imap_port').set_value(
			ssl_type == this.SSL_SSL || ssl_type == this.SSL_TLS ? 993 : 143);
	},

	/**
	 * Set default port, if imap ssl-type changes
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	wizard_smtp_ssl_onchange: function(_event, _widget)
	{
		var ssl_type = _widget.get_value();
		this.et2.getWidgetById('acc_smtp_port').set_value(
			ssl_type == 'no' ? 25 : (ssl_type == this.SSL_SSL || ssl_type == this.SSL_TLS ? 465 : 587));
	},

	/**
	 * Set default port, if imap ssl-type changes
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	wizard_sieve_ssl_onchange: function(_event, _widget)
	{
		var ssl_type = _widget.get_value();
		this.et2.getWidgetById('acc_sieve_port').set_value(
			ssl_type == this.SSL_SSL || ssl_type == this.SSL_TLS ? 5190 : 4190);
		this.wizard_sieve_onchange(_event, _widget);
	},

	/**
	 * Enable sieve, if user changes some setting
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	wizard_sieve_onchange: function(_event, _widget)
	{
		this.et2.getWidgetById('acc_sieve_enabled').set_value(1);
	},

	/**
	 * Switch to select multiple accounts
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	edit_multiple: function(_event, _widget)
	{
		// hide multiple button
		_widget.set_disabled(true);

		// switch account-selection to multiple
		var account_id = this.et2.getWidgetById('account_id');
		account_id.set_multiple(true);
	},

	/**
	 * Hide not applying fields, used as:
	 * - onchange handler on account_id
	 * - called from et2_ready for emailadmin.account template
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	account_hide_not_applying: function(_event, _widget)
	{
		var account_id = this.et2.getWidgetById('account_id');
		var ids = account_id && account_id.get_value ? account_id.get_value() : [];
		if (typeof ids == 'string') ids = ids.split(',');

		var multiple = ids.length >= 2 || ids[0] === '' || ids[0] < 0;
		//alert('multiple='+(multiple?'true':'false')+': '+ids.join(','));

		// initial call
		if (typeof _widget == 'undefined')
		{
			if (!multiple)
			{
				jQuery('.emailadmin_no_single').hide();
			}
			if (!this.egw.user('apps').admin)
			{
				jQuery('.emailadmin_no_user,#button\\[multiple\\]').hide();
			}
			if (ids.length == 1)
			{
				// switch back to single selectbox
				account_id.set_multiple(false);
				this.et2.getWidgetById('button[multiple]').set_disabled(false);
			}
		}
		// switched to single user
		else if (!multiple)
		{
			jQuery('.emailadmin_no_single').fadeOut();
			// switch back to single selectbox
			account_id.set_multiple(false);
			this.et2.getWidgetById('button[multiple]').set_disabled(false);
		}
		// switched to multiple user
		else
		{
			jQuery('.emailadmin_no_single').fadeIn();
		}
		if (_event && _event.stopPropagation) _event.stopPropagation();
		return false;
	},

	/**
	 * Callback if user changed account selction
	 *
	 * @param {object} _event event-object or information about event
	 * @param {et2_baseWidget} _widget widget causing the event
	 */
	change_account: function(_event, _widget)
	{
		// todo check dirty and query user to a) save changes, b) discard changes, c) cancel selection
		_widget.getInstanceManager().submit();
	},

	/**
	 * Callback if user changes notification folders: unset use-default checkbox
	 *
	 * @param {object} _event
	 * @param {et2_widget} _widget
	 */
	change_folders: function(_event, _widget)
	{
		var use_default = this.et2.getWidgetById('notify_use_default');
		if (use_default) use_default.set_value(false);
	},

	/**
	 * default onExecute for admin actions
	 *
	 * @param {object} _action
	 * @param {object} _senders
	 */
	account_edit_action: function(_action, _senders)
	{
		if (_action.data.url)
		{
			this.egw.open_link(_action.data.url, _action.data.target || '_blank', _action.data.popup);
		}
	},

	/**
	 * Clear instance cache
	 *
	 * If there is an error on server-side, resend request with an parameter allowing
	 * cache to use different method not requiring eg. so much memory
	 */
	clear_cache: function()
	{
		this.egw.message(this.egw.lang('Clear cache and register hooks')+"\n"+this.egw.lang('Please wait...'),'info');

		this.egw.json('admin.admin_hooks.ajax_clear_cache').sendRequest(true, undefined, jQuery.proxy(function(_xmlhttp, _err)
		{
			this.egw.json('admin.admin_hooks.ajax_clear_cache&errored=1').sendRequest(true);
		}, this));
	},

	/**
	 * Export content of given field into relevant file
	 */
	smime_exportCert: function ()
	{
		var $a = jQuery(document.createElement('a')).appendTo('body').hide();
		var acc_id = this.et2.getArrayMgr("content").getEntry('acc_id');
		var url = window.egw_webserverUrl+'/index.php?';
			url += 'menuaction=mail.mail_ui.smimeExportCert';
			url += '&acc_id='+acc_id;
		$a.prop('href',url);
		$a.prop('download',"");
		$a[0].click();
		$a.remove();
	},

	/**
	 * Create certificate generator dialog
	 */
	smime_genCertificate: function ()
	{
		var self = this;
		et2_createWidget("dialog",
		{
			callback: function(_button_id, _value)
			{
				if (_button_id == 'create' && _value)
				{
					var isValid = true;
					var required = ['countryName', 'emailAddress'];
					var widget = {};
					// check the required fields
					for (var i=0;i<required.length;i++)
					{
						if (_value[required[i]]) continue;
						widget = this.template.widgetContainer.getWidgetById(required[i]);
						widget.set_validation_error('This field is required!');
						isValid = false;
					}
					// check mismatch passphrase
					if (_value.passphrase && _value.passphrase !== _value.passphraseConf)
					{
						var passphraseConf = this.template.widgetContainer.getWidgetById('passphrase');
						passphraseConf.set_validation_error('Confirm passphrase is not match!');
						isValid = false;
					}

					if (isValid)
					{
						egw.json('mail.mail_ui.ajax_smimeGenCertificate', _value, function(_cert){
							if (_cert)
							{
								for (var key in _cert)
								{
									if (!_cert[key]) continue;
									switch (key)
									{
										case 'cert':
											self.et2.getWidgetById('smime_cert').set_value(_cert[key]);
											break;
										case 'privkey':
											self.et2.getWidgetById('acc_smime_password').set_value(_cert[key]);
											break;
									}
								}
								self.egw.message('New certificate information has been generated, please save your account if you want to store it.');
							}
						}).sendRequest(true);
					}
					else
					{
						return false;
					}
				}
			},
			title: egw.lang('Generate Certificate'),
			buttons: [
				{text: this.egw.lang("Create"), id: "create", "class": "ui-priority-primary", "default": true},
				{text: this.egw.lang("Cancel"), id:"cancel"}
			],
			value:{
				content:{
					value: ''
			}},
			template: egw.webserverUrl+'/mail/templates/default/smimeCertGen.xet?'+Date.now(),
			resizable: false,
			position: 'left top'
		}, et2_dialog._create_parent('mail'));
	},

	/**
	 * Triggers upload for background image and updates its taglist
	 *
	 * @param {type} node
	 * @param {type} widget
	 */
	login_background_update: function(node, widget)
	{
		var taglist = widget._parent._children[0];
		egw.json('admin.admin_config.ajax_upload_anon_images',
			[widget.get_value(), taglist.get_value()],
			function(_data){
				taglist.set_value(_data);
		}).sendRequest()
	}
});
