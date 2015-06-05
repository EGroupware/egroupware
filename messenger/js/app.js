/**
 * EGroupware - messenger - Javascript UI
 *
 * @link http://www.egroupware.org
 * @author Hadi Nategh <hn[at]stylite.de>
 * @package messenger
 * @subpackage setup
 * @copyright (c) 2014 by Stylite AG
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: $
 */

app.classes.messenger = AppJS.extend(
{
	appname:'messenger',
	
	connection:{},
	/**
	 * Constructor
	 *
	 * @memberOf app.messenger
	 */
	init: function ()
	{
		var self = this;
		var scripts = ['/socket.io/socket.io/socket.io.js','messenger/js/easyrtc/easyrtc.js'];
		egw_LAB.script(scripts).wait(function(){
			// Ready to make connection
		});
		
	},
	
	/**
	 * Destructor
	 */
	destroy: function()
	{
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
	et2_ready: function (et2,name)
	{
		// call parent
		this._super.apply(this, arguments);
		switch (name)
		{
			case 'messenger.dialog':
				
				break;
			case 'messenger.index':
				
		}	
	},
	
	/**
	 * Initialize webrtc connection
	 */
	_init_webrtc: function ()
	{
		
	},
	
	makeCall: function ()
	{
		
	},
	
	/**
	 * Get account label of a specific account_id
	 * @param {type} _account_id
	 * @returns {account.label} account label
	 */
	getAccountName: function (_account_id)
	{
		var accounts = egw.accounts('accounts');
		for (var account in accounts)
		{
			if (account.value == _account_id) return account.label;
		}
	},

	userOnSelect: function (_rowId)
	{
		var id = _rowId[0].split('::')[1];
	},

	/**
	 * Index toolbar actions, handles selected toolbar actions and redirect them to
	 * its related functionallity.
	 *
	 * @param {action object} _action selected toolbar action
	 */
	toolbarActions: function (_action)
	{
		var nm = this.et2.getWidgetById('nm');
		if (nm && _action)
		{
			var uid = nm.getSelection()['ids'][0];
			if (!uid) return;

			switch (_action.id)
			{
				case 'call':
					// make call to selected uid
					this.makeCall(_action, {0:{id:uid}});
					break;
				case 'micro':
					//TODO
				case 'video':
					//TODO
				case 'video_call':
					//TODO
				case 'hangup':
			}
		}
	}
	
});