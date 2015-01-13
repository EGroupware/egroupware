/* 
 * Egroupware - Calendar javascript for home favorite portlet(s)
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage home
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

/**
 * Custom code for calendar favorite home page portlets.
 * Since calendar doesn't use etemplate2, as well as having multiple different
 * views, we need some custom handling to detect and handle refreshes.
 *
 * Note we put the class in home.
 */
app.classes.home.calendar_favorite_portlet = app.classes.home.home_favorite_portlet.extend({

observer: function(_msg, _app, _id, _type, _msg_type, _targetapp)
{
	if(this.portlet.getWidgetById('nm'))
	{
		// List view, we can just update it
		this.portlet.getWidgetById('nm').refresh(_id,_type);
	}
	else
	{
		// No intelligence since we don't have access to the state 
		// (app.calendar.getState() is for the calendar tab, not home)
		// just refresh on every calendar or infolog change
		if(_app == 'calendar' || _app == 'infolog')
		{
			app.home.refresh(this.portlet.id);
		}
	}
}
});