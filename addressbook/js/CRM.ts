/**
 * EGroupware - Addressbook - Javascript UI
 *
 * @link: https://www.egroupware.org
 * @package addressbook
 * @author Hadi Nategh	<hn-AT-stylite.de>
 * @author Ralf Becker <rb-AT-egroupware.org>
 * @copyright (c) 2008-21 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

/*egw:uses
	/api/js/jsapi/egw_app.js
 */

import {EgwApp, PushData} from '../../api/js/jsapi/egw_app';
import {etemplate2} from "../../api/js/etemplate/etemplate2";
import {et2_nextmatch} from "../../api/js/etemplate/et2_extension_nextmatch";
import {egw} from "../../api/js/jsapi/egw_global.js";

/**
 * UI for Addressbook CRM view
 *
 */
export class CRMView extends EgwApp
{
	// List ID
	list_id: string = "";

	// Reference to the list
	nm: et2_nextmatch = null;

	// Which addressbook contact id(s) we are showing entries for
	contact_ids: string[] = [];

	// Private js for the list
	app_obj: EgwApp = null;

	// Hold on to the original push handler
	private _app_obj_push: (pushData: PushData) => void;

	// Push data key(s) to check for our contact ID in the entry's ACL data
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
			return CRMView.reconnect(app_obj);
		}

		// Make sure object is there, etemplate2 will pick it up and call our et2_ready
		let crm : CRMView = undefined;
		// @ts-ignore
		if(typeof et2.app_obj.crm == "undefined" && app.classes.crm)
		{
			// @ts-ignore
			crm = et2.app_obj.crm = new app.classes.crm();
		}
		if(typeof crm == "undefined")
		{
			egw.debug("error", "CRMView object is missing");
			return false;
		}


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
	 * Our CRM has become disconnected from its list, probably because something submitted.
	 * Find it, and get things working again.
	 *
	 * @param app_obj
	 */
	static reconnect(app_obj : EgwApp)
	{
		// Check
		let contact_ids = app_obj.et2.getArrayMgr("content").getEntry("action_id") || "";
		if(!contact_ids) return;

		for (let existing_app of EgwApp._instances)
		{
			if(existing_app instanceof CRMView && existing_app.list_id == app_obj.et2.getInstanceManager().uniqueId)
			{
				// List was reloaded.  Rebind.
				existing_app.app_obj.destroy(existing_app.app_obj.appname);
				if(!existing_app.nm?.getParent())
				{
					try
					{
						// This will probably not die cleanly, we had a reference when it was destroyed
						existing_app.nm.destroy();
					} catch (e) {}
				}
				return existing_app.set_view_obj(app_obj);
			}
		}
	}
	/**
	 * Set the associated private app JS
	 * We try and pull the needed info here
	 */
	set_view_obj(app_obj: EgwApp)
	{
		this.app_obj = app_obj;

		// Make sure object is there, etemplate2 will pick it up and call our et2_ready
		app_obj.et2.getInstanceManager().app_obj.crm = this

		// Make _sure_ we get notified if the list is removed (actions, refresh) - this is not always a full
		// destruction
		jQuery(app_obj.et2.getDOMNode()).on('clear', function() {
			this.nm = null;
		}.bind(this));

		// For easy reference later
		this.list_id = app_obj.et2.getInstanceManager().uniqueId;
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
		// (server responds then with null / no entry causing the entry to disappear)
		if (pushData.type !== "add" && this.egw.dataHasUID(this.uid(pushData)))
		{
			// Check to see if it's in OUR nextmatch
			let uid = this.uid(pushData);
			let known = Object.values(this.nm.controller._indexMap).filter(function(row) {return row.uid ==uid;});
			let type = pushData.type;
			if(known && known.length > 0)
			{
				if(!this.id_check(pushData.acl))
				{
					// Was ours, not anymore, and we know this now - no server needed.  Just remove from nm.
					type = et2_nextmatch.DELETE;
				}
				return this.nm.refresh(pushData.id, type);
			}
		}

		if(this.id_check(pushData.acl))
		{
			return this._app_obj_push(pushData);
		}

	}

	/**
	 * Check to see if the given entry is "ours"
	 *
	 * @param entry
	 */
	id_check(entry) : boolean
	{
		// Check if it's for one of our contacts
		for(let field of this.push_contact_ids)
		{
			if(entry && entry[field])
			{
				let val = typeof entry[field] == "string" ? [entry[field]] : entry[field];
				if(val.filter(v => this.contact_ids.indexOf(v) >= 0).length > 0)
				{
					return true;
				}
			}
		}
		return false;
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