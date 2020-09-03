/**
 * EGroupware - Addressbook - Javascript UI
 *
 * @link: https://www.egroupware.org
 * @package addressbook
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @copyright (c) 2008-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
	/api/js/jsapi/egw_app.js
 */

import 'jquery';
import 'jqueryui';
import '../jsapi/egw_global';
import '../etemplate/et2_types';

import {EgwApp, PushData} from '../../api/js/jsapi/egw_app';
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {et2_nextmatch} from "../../api/js/etemplate/et2_extension_nextmatch";

/**
 * UI for Addressbook CRM view
 *
 */
export class CRMView extends EgwApp
{
	// Reference to the list
	nm: et2_nextmatch = null;

	// Which addressbook contact id(s) we are showing entries for
	contact_ids: string[] = [];

	// Private js for the list
	app_obj: EgwApp = null;

	// Hold on to the original push handler
	private _app_obj_push: (pushData: PushData) => void;

	// Push data key(s) to check for our contact ID
	private push_contact_ids = ["contact_id"];

	/**
	 * Constructor
	 *
	 * CRM is part of addressbook
	 */
	constructor()
	{
		// call parent
		super('addressbook');
	}

	/**
	 * Destructor
	 */
	destroy(_app)
	{
		this.nm = null;
		if(this.app_obj != null)
		{
			this.app_obj.destroy(_app);
		}

		// call parent
		super.destroy(_app);
	}

	/**
	 * A template from an app is ready, looks like it might be a CRM view.
	 * Check it, get CRM ready, and bind accordingly
	 *
	 * @param et2
	 * @param appname
	 */
	static view_ready(et2: etemplate2, app_obj: EgwApp)
	{
		// Check to see if the template is for a CRM view
		if(et2.app == app_obj.appname)
		{
			return false;
		}

		// Make sure object is there, etemplate2 will pick it up and call our et2_ready
		if(typeof et2.app_obj.crm == "undefined" && app.classes.crm)
		{
			et2.app_obj.crm = new app.classes.crm();
		}
		if(typeof et2.app_obj.crm == "undefined")
		{
			egw.debug("error", "CRMView object is missing");
			return false;
		}

		let crm = et2.app_obj.crm;

		// We can set this now
		crm.set_view_obj(app_obj);
	}

	/**
	 * This function is called when the etemplate2 object is loaded
	 * and ready.  The associated app [is supposed to have] already called its own et2_ready(),
	 * so any changes done here will override the app.
	 *
	 * @param {etemplate2} et2 newly ready object
	 * @param {string} name Template name
	 */
	et2_ready(et2, name)
	{
		// call parent
		super.et2_ready(et2, name);

	}

	/**
	 * Set the associated private app JS
	 * We try and pull the needed info here
	 */
	set_view_obj(app_obj: EgwApp)
	{
		this.app_obj = app_obj;

		// For easy reference later
		this.nm = <et2_nextmatch>app_obj.et2.getDOMWidgetById('nm');

		let contact_ids = app_obj.et2.getArrayMgr("content").getEntry("action_id") || "";
		if(typeof contact_ids == "string")
		{
			contact_ids = contact_ids.split(",");
		}
		this.set_contact_ids(contact_ids);

		// Override the push handler
		this._override_push(app_obj);
	}

	/**
	 * Set or change which contact IDs we are showing entries for
	 */
	set_contact_ids(ids: string[])
	{
		this.contact_ids = ids;
		let filter = {action_id: this.contact_ids};

		if(this.nm !== null)
		{
			this.nm.applyFilters(filter);
		}
	}

	/**
	 * Handle a push notification about entry changes from the websocket
	 *
	 * @param  pushData
	 * @param {string} pushData.app application name
	 * @param {(string|number)} pushData.id id of entry to refresh or null
	 * @param {string} pushData.type either 'update', 'edit', 'delete', 'add' or null
	 * - update: request just modified data from given rows.  Sorting is not considered,
	 *		so if the sort field is changed, the row will not be moved.
	 * - edit: rows changed, but sorting may be affected.  Requires full reload.
	 * - delete: just delete the given rows clientside (no server interaction neccessary)
	 * - add: ask server for data, add in intelligently
	 * @param {object|null} pushData.acl Extra data for determining relevance.  eg: owner or responsible to decide if update is necessary
	 * @param {number} pushData.account_id User that caused the notification
	 */
	push(pushData)
	{
		if(pushData.app !== this.app_obj.appname || !this.nm) return;

		// If we know about it and it's an update, just update.
		// This must be before all ACL checks, as contact might have changed and entry needs to be removed
		// (server responds then with null / no entry causing the entry to disapear)
		if (pushData.type !== "add" && this.egw.dataHasUID(this.uid(pushData)))
		{
			return this.nm.refresh(pushData.id, pushData.type);
		}

		// Check if it's for one of our contacts
		for(let field of this.push_contact_ids)
		{
			if(pushData.acl && pushData.acl[field])
			{
				let val = typeof pushData.acl[field] == "string" ? [pushData.acl[field]] : pushData.acl[field];
				if(val.filter(v => this.contact_ids.indexOf(v) >= 0).length > 0)
				{
					return this._app_obj_push(pushData);
				}
			}
		}
	}

	/**
	 * Override the list's push handler to do nothing, we'll call it if we want it.
	 *
	 * @param app_obj
	 * @private
	 */
	_override_push(app_obj : EgwApp)
	{
		this._app_obj_push = app_obj.push.bind(app_obj);
		app_obj.push = function(pushData) {return false;};
	}
}


app.classes.crm = CRMView;