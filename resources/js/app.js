/**
 * EGroupware - Resources - Javascript UI
 *
 * @link http://www.egroupware.org
 * @package resources
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id: app.js 44390 2013-11-04 20:54:23Z ralfbecker $
 */

/**
 * UI for resources
 *
 * @augments AppJS
 */
app.classes.resources = AppJS.extend(
{
	appname: 'resources',
	/**
	 * et2 widget container
	 */
	et2: null,
	/**
	 * path widget
	 */

	/**
	 * Constructor
	 *
	 * @memberOf app.resources
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
	 * @param et2 etemplate2 Newly ready object
	 */
	et2_ready: function(et2)
	{
		// call parent
		this._super.apply(this, arguments);

	},

	/**
	 * call calendar planner by selected resources
	 *
	 * @param {action} _action actions
	 * @param {action} _senders selected action
	 *
	 */
	view_calendar: function (_action,_senders)
	{

		var res_ids = [];
		var matches = [];
		var nm = _action.parent.data.nextmatch;
		var selection = nm.getSelection();

		var show_calendar = function(res_ids) {
			egw_message(this.egw.lang('%1 resource(s) View calendar',res_ids.length));
			var current_owners = (app.calendar ? app.calendar.state.owner || [] : []).join(',');
			if(current_owners)
			{
				current_owners += ',';
			}
			this.egw.open_link('calendar.calendar_uiviews.index&view=planner&sortby=user&owner='+current_owners+'r'+res_ids.join(',r')+'&ajax=true');
		}.bind(this);

		if(selection && selection.all)
		{
			// Get selected ids from nextmatch - it will ask server if user did 'select all'
			fetchAll(res_ids, nm, show_calendar)
		}
		else
		{
			for (var i=0;i<_senders.length;i++)
			{
				res_ids.push(_senders[i].id);
				matches = res_ids[i].match(/^(?:resources::)?([0-9]+)(:([0-9]+))?$/);
				if (matches)
				{
					res_ids[i] = matches[1];
				}
			}
			show_calendar(res_ids);
		}
	},

	/**
	 * Calendar sidebox hook change handler
	 *
	 */
	sidebox_change: function(ev, widget)
	{
		if(ev[0] != 'r') {
			widget.setSubChecked(ev,widget.getValue()[ev].value || false);
		}
		var owner = jQuery.extend([],app.calendar.state.owner) || [];
		for(var i = owner.length-1; i >= 0; i--)
		{
			if(owner[i][0] == 'r')
			{
				owner.splice(i,1);
			}
		}

		var value = widget.getValue();
		for(var key in value)
		{
			if(key[0] !== 'r') continue;
			if(value[key].value && owner.indexOf(key) === -1)
			{
				owner.push(key);
			}
		}
		app.calendar.update_state({owner: owner});
	},

	/**
	 * Book selected resource for calendar
	 *
	 * @param {action} _action actions
	 * @param {action} _senders selected action
	 */
	book: function(_action,_senders)
	{

		var res_ids =[], matches = [];

		for (var i=0;i<_senders.length;i++)
		{
			res_ids.push(_senders[i].id);
			matches = res_ids[i].match(/^(?:resources::)?([0-9]+)(:([0-9]+))?$/);
			if (matches)
			{
				res_ids[i] = matches[1];
			}
		}
		egw_message(this.egw.lang('%1 resource(s) booked',res_ids.length));

		this.egw.open_link('calendar.calendar_uiforms.edit&participants=r'+res_ids.join(',r'),'_blank','700x700');

	},

	/**
	 * set the picture_src to own_src by uploding own file
	 *
	 */
	select_picture_src: function ()
	{
		var rBtn = this.et2.getWidgetById('picture_src');
		if (typeof rBtn != 'undefined')
		{
			rBtn.set_value('own_src');
		}
	},

});