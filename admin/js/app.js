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
		
		var iframe = this.iframe = this.et2.getWidgetById('admin_iframe');
		if (iframe) iframe.set_src(egw.webserverUrl+'/admin/index.php');
		
		var splitter = this.splitter = this.et2.getWidgetById('admin_splitter');
		if (splitter) 
		{
			window.setTimeout(function(){
				splitter.dock();
			}, 1);
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
			this.et2.getWidgetById('admin_nm').applyFilters({ filter: parts[2] ? parts[2] : '', search: ''});
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
	}
});
