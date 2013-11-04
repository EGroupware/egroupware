/**
 * EGroupware clientside Application javascript base object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

"use strict";

/*egw:uses
        egw_inheritance;
*/

/**
 * Object to collect instanciated appliction objects
 *
 * Attributes classes collects loaded application classes,
 * which can get instanciated:
 *
 *	app[appname] = new app.classes[appname]();
 *
 * On destruction only app[appname] gets deleted, app.classes[appname] need to be used again!
 *
 * @type object
 */
window.app = {classes: {}};

/**
 * Common base class for application javascript
 * Each app should extend as needed.
 *
 * All application javascript should be inside.  Intitialization goes in init(),
 * clean-up code goes in destroy().  Initialization is done once all js is loaded.
 *
 * var app.appname = AppJS.extend({
 *	// Actually set this one, the rest is example
 *	appname: appname,
 *
 *	internal_var: 1000,
 *
 *	init: function()
 *	{
 *		// Call the super
 *		this._super.apply(this, arguments);
 *
 *		// Init the stuff
 *		if ( egw.preference('dateformat', 'common') )
 *		{
 *			// etc
 *		}
 *	},
 *	_private: function()
 *	{
 *		// Underscore private by convention
 *	}
 * });
 */
var AppJS = Class.extend(
{
	/**
	 * Internal application name - override this
	 */
	appname: '',

	/**
	 * Internal reference to etemplate2 widget tree
	 */
	et2: null,

	/**
	 * Internal reference to egw client-side api object for current app and window
	 */
	egw: null,

	/**
	 * Initialization and setup goes here, but the etemplate2 object
	 * is not yet ready.
	 */
	init: function() {
		window.app[this.appname] = this;

		this.egw = egw(this.appname, window);
	},

	/**
	 * Clean up any created objects & references
	 */
	destroy: function() {
		delete this.et2;
		delete window.app[this.appname];
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().  Note that this can be called
	 * several times, with different et2 objects, as templates are loaded.
	 *
	 * @param et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2) {
		if(this.et2 !== null)
		{
			egw.debug('log', "Changed et2 object");
		}
		this.et2 = et2.widgetContainer;
	},

	/**
	 * Open an entry.
	 *
	 * Designed to be used with the action system as a callback
	 * eg: onExecute => app.<appname>.open
	 *
	 * @param _action
	 * @param _senders
	 */
	open: function(_action, _senders) {
		var id_app = _senders[0].id.split('::')
		egw.open(id_app[1], this.appname);
	 },

	/**
	 * A generic method to action to server asynchronously
	 *
	 * Designed to be used with the action system as a callback.
	 * In the PHP side, set the action
	 * 'onExecute' => 'javaScript:app.<appname>.action', and
	 * implement _do_action(action_id, selected)
	 *
	 * @param {egwAction} _action
	 * @param {egwActionObject[]} _elems
	 */
	action: function(_action, _elems)
	{
		// let user confirm select-all
		var select_all = _action.getManager().getActionById("select_all");
		var confirm_msg = (_elems.length > 1 || select_all && select_all.checked) &&
			typeof _action.data.confirm_multiple != 'undefined' ?
				_action.data.confirm_multiple : _action.data.confirm;

		if (typeof confirm_msg != 'undefined')
		{
			var that = this;
			var action_id = _action.id;
			et2_dialog.show_dialog(function(button_id,value)
			{
				if (button_id != et2_dialog.NO_BUTTON)
				{
					that._do_action(action_id, _elems);
				}
			}, confirm_msg, egw.lang('Confirmation required'), et2_dialog.BUTTONS_YES_NO, et2_dialog.QUESTION_MESSAGE);
		}
		else if (typeof this._do_action == 'function')
		{
			this._do_action(_action.id, _elems);
		}
		else
		{
			// If this is a nextmatch action, do an ajax submit setting the action
			var nm = null;
			var action = _action;
			while(nm == null && action.parent != null)
			{
				if(action.data.nextmatch) nm = action.data.nextmatch;
				action = action.parent;
			}
			if(nm != null)
			{
				var value = {};
				value[nm.options.settings.action_var] = _action.id;
				nm.set_value(value);
				nm.getInstanceManager().submit();
			}
		}
	}

});
