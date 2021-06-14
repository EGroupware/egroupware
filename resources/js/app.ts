/**
 * EGroupware - Resources - Javascript UI
 *
 * @link https://www.egroupware.org
 * @package resources
 * @author Hadi Nategh	<hn-AT-egroupware.org>
 * @copyright (c) 2008-21 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

import {EgwApp} from "../../api/js/jsapi/egw_app";
import {fetchAll} from "../../api/js/etemplate/et2_extension_nextmatch_actions.js";
import {egw} from "../../api/js/jsapi/egw_global";

/**
 * UI for resources
 */
class resourcesApp extends EgwApp
{

	/**
	 * Constructor
	 */
	constructor()
	{
		super('resources');
	}

	/**
	 * Destructor
	 */
	destroy(_app)
	{
		delete this.et2;
		super.destroy(_app);
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  If you must store a reference to the et2 object,
	 * make sure to clean it up in destroy().
	 */
	et2_ready(et2, name)
	{
		super.et2_ready(et2, name);
	}

	/**
	 * call calendar planner by selected resources
	 *
	 * @param {action} _action actions
	 * @param {action} _senders selected action
	 *
	 */
	view_calendar(_action,_senders)
	{
		let res_ids = [];
		let matches = [];
		let nm = _action.parent.data.nextmatch;
		let selection = nm.getSelection();

		let show_calendar = function(res_ids) {
			egw(window).message(this.egw.lang('%1 resource(s) View calendar',res_ids.length));
			let current_owners = (app.calendar ? app.calendar.state.owner || [] : []).join(',');
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
			for (let i=0;i<_senders.length;i++)
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
	}

	/**
	 * Calendar sidebox hook change handler
	 *
	 */
	sidebox_change(ev, widget)
	{
		if(ev[0] != 'r') {
			widget.setSubChecked(ev,widget.getValue()[ev].value || false);
		}
		let owner = jQuery.extend([],app.calendar.state.owner) || [];
		for(let i = owner.length-1; i >= 0; i--)
		{
			if(owner[i][0] == 'r')
			{
				owner.splice(i,1);
			}
		}

		let value = widget.getValue();
		for(let key in value)
		{
			if(key[0] !== 'r') continue;
			if(value[key].value && owner.indexOf(key) === -1)
			{
				owner.push(key);
			}
		}
		app.calendar.update_state({owner: owner});
	}

	/**
	 * Book selected resource for calendar
	 *
	 * @param {action} _action actions
	 * @param {action} _senders selected action
	 */
	book(_action,_senders)
	{

		let res_ids =[], matches = [];

		for (let i=0;i<_senders.length;i++)
		{
			res_ids.push(_senders[i].id);
			matches = res_ids[i].match(/^(?:resources::)?([0-9]+)(:([0-9]+))?$/);
			if (matches)
			{
				res_ids[i] = matches[1];
			}
		}
		egw(window).message(this.egw.lang('%1 resource(s) booked',res_ids.length));

		this.egw.open_link('calendar.calendar_uiforms.edit&participants=r'+res_ids.join(',r'),'_blank','700x700');
	}

	/**
	 * set the picture_src to own_src by uploding own file
	 *
	 */
	select_picture_src()
	{
		let rBtn = this.et2.getWidgetById('picture_src');
		if (typeof rBtn != 'undefined')
		{
			rBtn.set_value('own_src');
		}
	}
}
app.classes.resources = resourcesApp;