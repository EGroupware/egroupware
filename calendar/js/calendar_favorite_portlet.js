/* 
 * Egroupware - Calendar javascript for home favorite portlet(s)
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package calendar
 * @subpackage home
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */
/*egw:uses
	/calendar/js/app.js;
*/

/**
 * Custom code for calendar favorite home page portlets.
 * Since calendar doesn't use etemplate2, as well as having multiple different
 * views, we need some custom handling to detect and handle refreshes.
 *
 * Note we put the class in home.
 *
app.classes.home.calendar_favorite_portlet = app.classes.home.home_favorite_portlet.extend({

observer: function(_msg, _app, _id, _type, _msg_type, _targetapp)
{
	if(this.portlet.getWidgetById('nm'))
	{
		// List view, we can just update it
		this.portlet.getWidgetById('nm').refresh(_id,_type);
	}
	else if (_id)
	{
		// Calendar app should handle it in its observer()
	}
	else if (app.classes.calendar && app.calendar)
	{
		// No ID, probably a refresh of app.  Calendar will discard the cache.
		// Only make a request if:
		// - portlet date range is outside calendar state range
		// - portlet owner is not in calendar state owner
		// Otherwise, we'll kill the connection with several overlapping requests

		var value = [];
		var state = this.portlet.options.settings.favorite.state;
		if(state.owner == 0) state.owner = [egw.user('account_id')];
		this.portlet.iterateOver(function(view) {
			value.push({
				owner: view.options.owner,
				start_date: view.options.start_date,
				end_date: view.options.end_date
			})
			state.first = !state.first || state.first > view.options.start_date ? view.options.start_date : state.first;
			state.last = !state.last || state.last < view.options.end_date ? view.options.end_date : state.last;
		},this, et2_calendar_view);

		if(state.first < new Date(app.calendar.state.first) || state.last > new Date(app.calendar.state.last) ||
			state.owner != app.calendar.state.owner)
		{
			app.calendar.et2 = this.portlet._children[0]
			app.calendar._need_data(value, state);
		}
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
});*/