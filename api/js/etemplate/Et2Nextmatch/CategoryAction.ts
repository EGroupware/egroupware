/**
 * EGroupware eTemplate2 - change the categories of the selected rows from one dialog
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link https://www.egroupware.org
 */
import {Et2Dialog} from "../Et2Dialog/Et2Dialog";
import type {EgwAction} from "../../egw_action/EgwAction";
import type {EgwActionObject} from "../../egw_action/EgwActionObject";

/**
 * What Nextmatch::category_action() puts in data['categories']
 */
export interface CategoryActionSettings
{
	application : string;
	/** several categories per entry, or exactly one - see below */
	multiple : boolean;
	globals : boolean;
	parentCat : number | null;
	/** action-id prefix, 'cat_' for most apps, 'status_' for records */
	prefix : string;
}

/**
 * Backs `nm_action = "categories"`.
 *
 * Replaces a sub-menu holding one entry per category - which in addressbook meant TWO such
 * sub-menus, "Add category" and "Delete category", ~150 entries each - with a single picker that
 * has search, hierarchy and (where the app supports it) multi-select.
 *
 * CARDINALITY IS NOT UNIFORM, and the server-side handlers already diverge to match:
 * - addressbook stores a comma-separated cat_id, and its action() adds to / removes from that
 *   list (cat_add_<id> / cat_del_<id>);
 * - infolog, timesheet, projectmanager and records store exactly one, and their action() simply
 *   assigns it (cat_<id>).
 * So the dialog offers Add/Remove/Replace for the first shape and Set/Remove for the second, and
 * sends the action ids those handlers already understand. Nothing about the storage is unified.
 *
 * "Remove" on a single-category app is new only in the sense of being reachable: the handlers
 * already do the right thing for an empty value (infolog even has a lang('removed category')
 * branch for it), but category_action() never emitted a "None" entry, so there was no way to
 * clear a category from the list.
 */
export class CategoryAction
{
	static async open(egw, action : EgwAction, senders : EgwActionObject[] = [])
	{
		const settings : CategoryActionSettings = action.data?.categories;
		if(!settings)
		{
			return;
		}
		const nm = action.parent?.data?.nextmatch || action.data?.nextmatch;
		const all = nm?.getSelection?.().all === true;

		const ADD = "add", REMOVE = "remove", REPLACE = "replace";
		const buttons = settings.multiple ? [
			{button_id: ADD, id: "dialog[add]", label: egw.lang("Add"), image: "add", "default": true},
			{button_id: REMOVE, id: "dialog[remove]", label: egw.lang("Remove"), image: "delete"},
			{button_id: REPLACE, id: "dialog[replace]", label: egw.lang("Replace"), image: "edit"},
			{button_id: Et2Dialog.CANCEL_BUTTON, id: "dialog[cancel]", label: egw.lang("Cancel"), image: "cancel"}
		] : [
			{button_id: ADD, id: "dialog[set]", label: egw.lang("Set"), image: "check", "default": true},
			{button_id: REMOVE, id: "dialog[remove]", label: egw.lang("Remove"), image: "delete"},
			{button_id: Et2Dialog.CANCEL_BUTTON, id: "dialog[cancel]", label: egw.lang("Cancel"), image: "cancel"}
		];

		const dialog = new Et2Dialog(egw);
		dialog.transformAttributes({
			title: (<any>action).caption || egw.lang("Categories"),
			buttons,
			width: 400,
			value: {
				content: {
					summary: senders.length > 1 || all ?
							 egw.lang("%1 selected entries", all ? "" : senders.length).trim() : "",
					selected: settings.multiple ? [] : ""
				}
			},
			template: egw.webserverUrl + "/api/templates/default/category_action.xet"
		});
		document.body.appendChild(dialog);
		await dialog.updateComplete;

		const select : any = (<any>dialog)._template_widget?.widgetContainer?.getWidgetById("selected");
		if(select)
		{
			select.application = settings.application;
			select.globalCategories = settings.globals;
			if(settings.parentCat)
			{
				select.parentCat = settings.parentCat;
			}
			select.multiple = !!settings.multiple;
		}

		const [rawButton, content] = await dialog.getComplete();
		dialog.remove();
		// button_id is declared number|Object on Et2Dialog, but our own verb buttons use string
		// ids - cast rather than fight the widened declared type (same as LinkAction does)
		const button = <unknown>rawButton as string;
		if(button !== ADD && button !== REMOVE && button !== REPLACE)
		{
			return;
		}

		const picked = [].concat((<any>content)?.selected ?? []).filter(v => v !== "" && v !== null);
		// Remove with nothing picked means "clear the category", which is a real operation on a
		// single-category app. On a multiple one it would be ambiguous, so require a selection.
		if(!picked.length && !(button === REMOVE && !settings.multiple))
		{
			return;
		}

		const ids = senders.map(sender => (sender?.id || "").split("::").pop()).filter(Boolean);
		const menuaction = action.data?.menuaction ||
			settings.application + "." + settings.application + "_ui.ajax_action";

		for(const actionId of CategoryAction._actionIds(settings, button, picked))
		{
			// one request per picked category, because that is the granularity the existing
			// per-app action() handlers work at - no server change needed to get this far
			await egw.request(menuaction, [actionId, ids, all, {}]);
		}
	}

	/**
	 * Build the action ids the app's own action() already understands.
	 */
	private static _actionIds(settings : CategoryActionSettings, button : string, picked : string[]) : string[]
	{
		if(!settings.multiple)
		{
			// single-value: assign the one picked category, or the bare prefix to clear it
			return [button === "remove" ? settings.prefix : settings.prefix + picked[0]];
		}
		switch(button)
		{
			case "add":
				return picked.map(id => settings.prefix + "add_" + id);
			case "remove":
				return picked.map(id => settings.prefix + "del_" + id);
			case "replace":
				// deliberately ONE request setting the whole list, not a del-everything followed
				// by an add: that would write every entry twice and log two history entries
				return [settings.prefix + "set_" + picked.join(",")];
		}
		return [];
	}
}
