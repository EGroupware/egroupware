/**
 * EGroupware - Preferences - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package preferences
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: app.js 47099 2014-05-27 13:36:40Z hnategh $
 */

/**
 * UI for Preferences
 *
 * @augments AppJS
 */
app.classes.preferences = AppJS.extend(
{
	appname: 'preferences',

	/**
	 * Constructor
	 *
	 * @memberOf app.preferences
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
		// call parent
		this._super.apply(this, arguments);
	},

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 *
	 * @param {etemplate2} _et2 newly ready object
	 * @param {string} _name template name
	 */
	et2_ready: function(_et2, _name)
	{
		// call parent
		this._super.apply(this, arguments);
		
		var app = this.et2.getWidgetById('appname');
		switch (app.get_value())
		{
			case 'calendar':
				var defAlarmWidgets = ['default-alarm', 'default-alarm-wholeday'];
				for(var key in defAlarmWidgets)
				{
					this.cal_def_alarm_onchange(null, this.et2.getWidgetById(defAlarmWidgets[key]));
				}
				break;
		}	
	},
	
	/**
	 * Set/Unset Calendar custom-default-alarm for regular and wholeday event preferences
	 * 
	 * @param {object} _egw
	 * @param {widget object} _widget
	 * @todo options need to be implemented in preferences to be able to set options for widget,
	 *		then node.options should be removed from here and set by template.
	 */
	cal_def_alarm_onchange: function (_egw,_widget)
	{
		var node = {};
		if (typeof _widget != 'undefined' && _widget != null)
		{
			switch (_widget.id)
			{
				case 'default-alarm':
					node = this.et2.getWidgetById('custom-default-alarm');
					break;
				case 'default-alarm-wholeday':
					 node = this.et2.getWidgetById('custom-default-alarm-wholeday');
			}
			if (typeof node != 'undefined' && node != null)
			{
				node.options.display_format = 'dhm';
				node.options.hours_per_day = 24;
				node.set_value(node.options.value);
				if (_widget.get_value() == -1)
				{
					jQuery(node.getParent().parentNode.parentNode).show();
				}
				else
				{
					jQuery(node.getParent().parentNode.parentNode).hide();
				}
			}
		}
	}
});