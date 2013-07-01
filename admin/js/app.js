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
		
		var iframe = this.et2.getWidgetById('admin_iframe');
		if (iframe) iframe.set_src(egw.webserverUrl+'/admin/index.php');
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
		var iframe = this.et2.getWidgetById('admin_iframe');
		
		if (typeof link == 'undefined')
		{
			_widget.openItem(_id, 'toggle');	
		}
		else if (link[0] == '/' || link.substr(0,4) == 'http')
		{
			iframe.set_src(link+'&nonavbar=1');
		}
		else if (link.substr(0,11) == 'javascript:')
		{
			eval(link.substr(11));
		}
	}
});
