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
							if (this.contentDocument.location.href.match(/(\/admin\/|\/admin\/index.php|menuaction=admin.admin_ui.index)/))
							{
								this.contentDocument.location.href = 'about:blank';	// stops redirect from admin/index.php
								self.load();	// load own top-level index aka user-list
							}
							self._hide_navbar.call(self);
						}
					);
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
		if (this.iframe && this.iframe.getDOMNode().contentDocument.location.href.match(/menuaction=admin.admin_statistics.submit/))
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
		if (_url)
		{
			this.iframe.set_src(_url);
		}
		else
		{
			this.egw.app_header('');
			// blank iframe, to not keep something running there
			this.iframe.getDOMNode().contentDocument.location.href = 'about:blank';
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
		// Get by ID, since this.et2 isn't always the ACL list
		var et2 = etemplate2.getById('admin-acl').widgetContainer;
		var className = app+'_acl';
		var acl_rights = {};
		var readonlys = {acl: {}};

		// Select options are already here, just pull them and pass along
		sel_options = jQuery.extend({}, et2.getArrayMgr('sel_options').data||{}, {
			'apps': et2.getArrayMgr('sel_options').getEntry('filter2')
		},sel_options);
		// Remove 'All applications'
		delete sel_options.apps[''];

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

		if(content.acl_account && !egw.user('apps')['admin'])
		{
			readonlys.acl_account = true;
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

				// Handle no applications selected
				if(typeof _value.apps == 'undefined' && content.acl_location == 'run')
				{
					_value.apps = [];
				}

				// Only send the request if they entered everything (or selected no apps)
				if(_value.acl_account && (_value.acl_appname && _value.acl_location || typeof _value.apps != 'undefined'))
				{
					if(_value.acl_appname && _value.acl_account && _value.acl_location)
					{
						var id = _value.acl_appname+':'+_value.acl_account+':'+_value.acl_location;
						if(content && content.id && id != content.id)
						{
							// Changed the account or location, remove previous or we
							// get a new line instead of an edit
							this.egw.json(className+'::ajax_change_acl', [content.id, 0], null,this,false,this)
								.sendRequest();
						}
					}
					var rights = 0;
					for(var i in _value.acl)
					{
						rights += parseInt(_value.acl[i]);
					}
					if(typeof _value.apps != 'undefined' && !_value.acl_appname)
					{
						id = [];
						rights = 1;
						var removed = [];

						// Loop through all apps, remove the ones with no permission
						for(var app in sel_options.apps)
						{
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
	 * @param {string} confirm_msg
	 * @param {string} action own action, if called via window_set_timeout
	 * @param {string} exec_id own exec_id
	 * @return {boolean}
	 */
	submit_statistic: function(form,submit_url,confirm_msg,action,exec_id)
	{
		if (submit_url) {
			if (!confirm(confirm_msg)) return false;

			var own_action = form.action;
			var own_exec_id = form['etemplate_exec_id'].value;
			var that = this;

			// submit to own webserver
			window.setTimeout(function() {
				that.submit_statistic.call(this, form, '', '', own_action, own_exec_id);
			},100);

			// submit to egroupware.org
			form.action=submit_url;
			form['etemplate_exec_id'].value='';
			form.target='_blank';
		} else {
			// submit to own webserver
			form.action = action;
			form['etemplate_exec_id'].value=exec_id;
			form.target='';

			form.submit();
		}

		return true;
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
				{"button_id": "delete[subs]","text": this.egw.lang('Delete including sub-enteries'), id: 'delete[subs]', image: 'delete'}
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
	}
});
