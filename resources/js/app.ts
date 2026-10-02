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
import type {EgwFrameworkApp, FilterInfo} from "../../kdots/js/EgwFrameworkApp";
import type {Et2Nextmatch} from "../../api/js/etemplate/Et2Nextmatch/Et2Nextmatch";
import type {CalendarApp} from "../../calendar/js/app";
// egw is an ambient global (declare global {} in egw_global.d.ts, unconditionally included
// via tsconfig's "**/*.d.ts") - no import needed or possible.

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
	 * Resources (filter2 = -1) is what the list shows by default, it is not a filter
	 *
	 * @param filterValues
	 * @param fwApp
	 */
	getFilterInfo(filterValues : { [id : string] : any }, fwApp : EgwFrameworkApp) : FilterInfo
	{
		const values = {...(filterValues ?? {})};
		values.col_filter = {...(values.col_filter ?? {})};
		if(values.filter2 == -1)
		{
			delete values.filter2;
		}
		return fwApp.filterInfo(values);
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
		let nm = <Et2Nextmatch>_action.parent.data.nextmatch;
		let selection = nm.getSelection();

		const show_calendar = (res_ids) => {
			egw(window).message(this.egw.lang('%1 resource(s) View calendar',res_ids.length));
			let current_owners = (app.calendar ? (<CalendarApp>app.calendar).state.owner || [] : []).join(',');
			if(current_owners)
			{
				current_owners += ',';
			}
			this.egw.open_link('calendar.calendar_uiviews.index&view=planner&sortby=user&owner='+current_owners+'r'+res_ids.join(',r')+'&ajax=true');
		};

		if(selection && selection.all)
		{
			// Get selected ids from nextmatch - it will ask server if user did 'select all'
			nm.fetchAllIds().then(show_calendar);
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
		// app.calendar.state can come back from a server round-trip with .owner as a plain
		// {"0":...,"1":...} object rather than a real array (depends on how the server last
		// JSON-encoded it) - Object.values() handles both, a spread would throw on the object case.
		let owner : string[] = Object.values((<CalendarApp>app.calendar).state.owner || []);
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
		(<CalendarApp>app.calendar).update_state({owner: owner});
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

	/**
	 * Submit the delete / un-delete dialog of the resource list
	 *
	 * The dialogs are real <et2-dialog>s, so Et2NextmatchActionController.openActionPopup() just sets
	 * their .selectedIds and shows them; the window.nm_popup_action/nm_popup_ids globals the legacy
	 * nm_submit_popup() used are never set.  Every button submits the dialog's own action, the clicked
	 * button lands in the submitted content (eg. delete_popup[promote]), from which
	 * resources_ui::index() picks delete_promote or restore_accessories.
	 *
	 * @param _event
	 * @param _widget the clicked button
	 * @param _action_id the nm action the dialog was opened for, "delete" or "restore"
	 * @return false to stop the button's own submit
	 */
	submit_popup(_event : Event, _widget, _action_id : string) : boolean
	{
		const dialog = <any>_widget.closest('et2-dialog');
		const nm = <Et2Nextmatch>_widget.getInstanceManager()?.widgetContainer?.getWidgetById('nm');
		if(!nm)
		{
			return false;
		}
		// Prefer the live selection - it still carries "select all", which the dialog's
		// .selectedIds (a plain array of ids) does not
		const selection = nm.getSelection();
		if(!selection.all && dialog?.selectedIds?.length)
		{
			selection.ids = dialog.selectedIds;
		}
		nm.executeAction(_action_id, selection, {nmAction: "submit"});
		dialog?.close();
		return false;
	}
}
app.classes.resources = resourcesApp;