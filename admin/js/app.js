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
app.admin = AppJS.extend(
{
	appname: 'admin',
	/**
	 * et2 widget container
	 */
	et2: null,
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
		delete this.et2;
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
	et2_ready: function(_et2)
	{
		// call parent
		this._super.apply(this, arguments);

		this.et2 = _et2.widgetContainer;
		
		var iframe = this.iframe = this.et2.getWidgetById('iframe');
		if (iframe) 
		{
			iframe.set_src(egw.webserverUrl+'/admin/index.php');
			var self = this;
			jQuery(iframe.getDOMNode()).bind('load', function(){
				self._hide_navbar.call(self);
			});
		}
		var splitter = this.splitter = this.et2.getWidgetById('splitter');
		if (splitter) 
		{
			window.setTimeout(function(){
				splitter.dock();
			}, 1);
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
	 * Run an admin module / onclick callback for tree
	 * 
	 * @param string _id id of clicked node
	 * @param et2_tree _widget reference to tree widget
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
			this.iframe.set_src(link+'&nonavbar=1');
		}
		else if (link.substr(0,11) == 'javascript:')
		{
			eval(link.substr(11));
		}
	},
	
	/**
	 * View, edit or delete a group callback for tree
	 * 
	 * @param Object _action egwAction
	 * @param Object _senders egwActionObject _senders[0].id holds id
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
	 * @param Object _action egwAction
	 * @param Object _senders egwActionObject _senders[0].id holds the id "admin::app:account:location"
	 */
	acl: function(_action, _senders)
	{
		var ids = [];
		for(var i=0; i < _senders.length; ++i)
		{
			ids.push(_senders[i].id.substr(7));	// remove "admin::" prefix
		}

		switch(_action.id)
		{
			case 'delete':
				var request = new egw_json_request('admin_acl::ajax_change_acl', [ids], this);
				request.sendRequest(false, this._acl_callback, this);
				break;
				
			case 'edit':
				// need to specify window to get correct opener, as admin has multiple windows open!
				egw('admin', window).open_link(egw.link('/index.php', {
					menuaction: 'admin.admin_acl.acl',
					id: ids[0],
				}), 'acl', '300x300');
				break;

			case 'add':
				var current = ids[0].split(':');
				egw('admin', window).open_link(egw.link('/index.php', {
					menuaction: 'admin.admin_acl.acl',
					app: current[0],
					account: current[1]
				}), 'acl', '250x250');
				break;
		}
	},
	
	/**
	 * Callback called on successfull call of serverside ACL handling
	 * 
	 * @param string _data returned from server
	 */
	_acl_callback: function(_data)
	{
		window.egw_refresh(_data.msg, this.appname, _data.ids, _data.type);
	},
});
