/**
 * EGroupware - Admin - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2013 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

/**
 * UI for Admin
 *
 * @augments AppJS
 */
app.classes.admin = AppJS.extend(
{
	appname: 'admin',
	/**
	 * reference to splitter
	 */
	splitter: null,

	/**
	 * reference to splitter
	 */
	iframe: null,

	/**
	 * Constructor
	 *
	 * @memberOf app.filemanager
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
		this.splitter = null;

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
				if (iframe)
				{
					var self = this;
					jQuery(iframe.getDOMNode()).off('load.admin')
						.bind('load.admin', function(){
							self._hide_navbar.call(self);
							self.splitter.dock();
							self.splitter.resize();
						}
					);

					// Register app refresh now that iframe is available
					register_app_refresh('admin',jQuery.proxy(this.refresh,this));
				}
				this.splitter = this.et2.getWidgetById('splitter');
				break;

			case 'admin.categories.index':
				break;
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

		this.iframe.set_src(url);
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
			this.iframe.set_src(_url);
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
			this.splitter.undock();
			var parts = _id.split('/');
			this.et2.getWidgetById('nm').applyFilters({ filter: parts[2] ? parts[2] : '', search: ''});
		}
		else if (typeof link == 'undefined')
		{
			_widget.openItem(_id, 'toggle');
		}
		else if (link[0] == '/' || link.substr(0,4) == 'http')
		{
			this.splitter.dock();
			this.iframe.set_src(link+(link.match(/\?/)?'&':'?')+'nonavbar=1');
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

			case 'edit':
			case 'delete':
				this.splitter.dock();
				this.iframe.set_src(egw.link('/index.php', {
					menuaction: _action.id == 'edit' ? 'admin.uiaccounts.edit_group' : 'admin.uiaccounts.delete_group',
					account_id: _senders[0].id.split('/')[2]
				}));
				break;

			case 'acl':
				this.splitter.dock();
				this.iframe.set_src(egw.link('/index.php', {
					menuaction: 'admin.admin_acl.index',
					account_id: _senders[0].id.split('/')[2]
				}));
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
		var app = egw.app_name();	// can be either admin or preferences!
		if (app != 'admin') app = 'preferences';
		var className = app+'_acl';

		switch(_action.id)
		{
			case 'delete':
				var request = egw.json(className+'::ajax_change_acl', [ids], this._acl_callback,this,false,this)
					.sendRequest();
				break;

			case 'edit':
				// need to specify window to get correct opener, as admin has multiple windows open!
				egw('admin', window).open_link(egw.link('/index.php', {
					menuaction: app+'.'+className+'.acl',
					id: ids[0]
				}), 'acl', '300x300');
				break;

			case 'add':
				var current = ids[0].split(':');
				egw('admin', window).open_link(egw.link('/index.php', {
					menuaction: app+'.'+className+'.acl',
					app: current[0],
					account: current[1]
				}), 'acl', '250x250');
				break;
		}
	},

	/**
	 * Callback called on successfull call of serverside ACL handling
	 *
	 * @param {object} _data returned from server
	 */
	_acl_callback: function(_data)
	{
		window.egw_refresh(_data.msg, this.appname, _data.ids, _data.type);
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
		var content = this.et2.getArrayMgr('content').getEntry('owner');
		if (content)
		{
			var cat_original_owner = content.split(",");
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
									for (j=0; j <= diff.length;j++)
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
			params.account_id = _senders[0].id.substr(7);	// remove admin::
		}
		this.egw.open_link(this.egw.link('/index.php', params), 'admin', popup);
	}
});
